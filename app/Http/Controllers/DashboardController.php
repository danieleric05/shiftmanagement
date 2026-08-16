<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Candidate;
use App\Models\GovernanceRequest;
use App\Models\Interview;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftMember;
use App\Models\ShiftPosition;
use App\Models\ShiftRecruitmentNeed;
use App\Models\ShiftTransferRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $roleSlug = $user->role?->slug;

        if (in_array($roleSlug, ['administrateur', 'super_admin'], true)) {
            return $this->admin($request);
        }

        if (in_array($roleSlug, ['chef_equipe', 'chef_adjoint', 'coordinateur', 'coordinateur_adjoint'], true)) {
            return $this->chefEquipe($request);
        }

        return $this->membre($request);
    }

    private function admin(Request $request)
    {
        $organisationId = $request->user()->organisation_id;

        $shifts = Shift::where('organisation_id', $organisationId)
            ->withCount(['membresActifs as membres_count'])
            ->with(['positions.assignments' => fn ($q) => $q->where('statut', 'actif')->with('servant')])
            ->orderByJourCalendrier()
            ->orderBy('heure_debut')
            ->get()
            ->map(function (Shift $shift) {
                $positions = $shift->positions->map(function (ShiftPosition $position) {
                    $assignment = $position->assignments->first();

                    return [
                        'id' => $position->id,
                        'nom' => $position->nom,
                        'ordre' => $position->ordre,
                        'assignment_id' => $assignment?->id,
                        'titulaire' => $assignment ? [
                            'id' => $assignment->servant->id,
                            'nom_complet' => $assignment->servant->nomComplet(),
                            'depuis' => $assignment->date_debut->format('Y-m-d'),
                        ] : null,
                    ];
                });

                return [
                    'id' => $shift->id,
                    'nom' => $shift->nom,
                    'jour' => $shift->jour,
                    'membres_count' => $shift->membres_count,
                    'chef_equipe' => $shift->chefEquipe()?->name,
                    'postes_total' => $positions->count(),
                    'postes_vacants' => $positions->whereNull('titulaire')->count(),
                    'positions' => $positions,
                ];
            });

        $servantsDisponibles = Servant::where('organisation_id', $organisationId)
            ->where('statut', 'actif')
            ->orderBy('nom')
            ->get()
            ->map(fn (Servant $servant) => [
                'id' => $servant->id,
                'nom_complet' => $servant->nomComplet(),
            ]);

        return Inertia::render('Dashboard/Admin', [
            'stats' => [
                'shifts_actifs' => Shift::where('organisation_id', $organisationId)->where('statut', 'actif')->count(),
                'membres_actifs' => ShiftMember::whereHas('shift', fn ($q) => $q->where('organisation_id', $organisationId))
                    ->where('statut', 'actif')->distinct('user_id')->count('user_id'),
            ],
            'servants' => [
                'actifs' => Servant::where('organisation_id', $organisationId)->where('statut', 'actif')->count(),
                'en_formation' => Servant::where('organisation_id', $organisationId)->where('statut', 'en_formation')->count(),
                'recommandes' => Servant::where('organisation_id', $organisationId)->where('statut', 'recommande')->count(),
                'suspendus' => Servant::where('organisation_id', $organisationId)->where('statut', 'suspendu')->count(),
            ],
            'demandes' => [
                'avis' => GovernanceRequest::where('organisation_id', $organisationId)->where('type', 'avis')->where('statut', 'en_attente')->count(),
                'retraits' => GovernanceRequest::where('organisation_id', $organisationId)->where('type', 'retrait')->where('statut', 'en_attente')->count(),
            ],
            'shifts' => $shifts,
            'servantsDisponibles' => $servantsDisponibles,
            'transferts' => $this->resumeTransferts($organisationId),
            'recrutement' => $this->resumeRecrutement($organisationId),
            'entretiensAVenir' => $this->resumeEntretiens($organisationId),
        ]);
    }

    private function chefEquipe(Request $request)
    {
        $user = $request->user();
        $shiftIds = $user->shiftsGeres();

        $shifts = $user->shiftMemberships()
            ->where('statut', 'actif')
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['chef_equipe', 'chef_adjoint', 'coordinateur', 'coordinateur_adjoint']))
            ->with('shift')
            ->get()
            ->map(function (ShiftMember $sm) {
                $shift = $sm->shift;

                return [
                    'id' => $shift->id,
                    'nom' => $shift->nom,
                    'jour' => $shift->jour,
                    'heure_debut' => substr($shift->heure_debut, 0, 5),
                    'heure_fin' => substr($shift->heure_fin, 0, 5),
                    'membres' => $shift->membresActifs()->with(['user', 'role'])->get()->map(fn (ShiftMember $m) => [
                        'name' => $m->user->name,
                        'role' => $m->role->nom,
                    ]),
                ];
            });

        return Inertia::render('Dashboard/ChefEquipe', [
            'shifts' => $shifts,
            'transferts' => $this->resumeTransferts($user->organisation_id, $shiftIds),
            'recrutement' => $this->resumeRecrutement($user->organisation_id, $shiftIds),
            'entretiensAVenir' => $this->resumeEntretiens($user->organisation_id, $shiftIds),
        ]);
    }

    /**
     * Résumé des demandes de relève/permutation des 2 dernières semaines
     * (chapitre 5 : dashboard). $shiftIds = null pour l'administrateur (toute l'organisation).
     */
    private function resumeTransferts(int $organisationId, ?Collection $shiftIds = null): array
    {
        $base = ShiftTransferRequest::where('organisation_id', $organisationId)
            ->when($shiftIds !== null, fn ($q) => $q->whereIn('shift_id', $shiftIds));

        $recentes = (clone $base)->recentes(14)
            ->with(['shift', 'shiftDestination', 'servant'])
            ->orderByDesc('date_demande')
            ->get()
            ->map(fn (ShiftTransferRequest $d) => [
                'id' => $d->id,
                'type' => $d->type,
                'shift' => $d->shift->nom,
                'shift_destination' => $d->shiftDestination?->nom,
                'servant' => $d->servant->nomComplet(),
                'statut' => $d->statut,
                'date_demande' => $d->date_demande->format('Y-m-d'),
            ]);

        return [
            'releves_en_attente' => (clone $base)->where('type', 'releve')->enAttente()->count(),
            'permutations_en_attente' => (clone $base)->where('type', 'permutation')->enAttente()->count(),
            'recentes' => $recentes,
        ];
    }

    /**
     * Besoins de recrutement actifs (nombre à recruter > 0) avec le nombre de
     * candidats actuellement en cours par shift.
     */
    private function resumeRecrutement(int $organisationId, ?Collection $shiftIds = null): array
    {
        $besoins = ShiftRecruitmentNeed::where('organisation_id', $organisationId)
            ->when($shiftIds !== null, fn ($q) => $q->whereIn('shift_id', $shiftIds))
            ->where('nombre_a_recruter', '>', 0)
            ->with('shift')
            ->get();

        $candidatsActifsParShift = Candidate::where('organisation_id', $organisationId)
            ->whereIn('statut', ['nouveau', 'appele', 'entretien_planifie'])
            ->when($shiftIds !== null, fn ($q) => $q->whereIn('shift_souhaite_id', $shiftIds))
            ->get()
            ->groupBy('shift_souhaite_id')
            ->map->count();

        return [
            'total_a_recruter' => (int) $besoins->sum('nombre_a_recruter'),
            'shifts' => $besoins->map(fn (ShiftRecruitmentNeed $b) => [
                'shift' => $b->shift->nom,
                'nombre_a_recruter' => $b->nombre_a_recruter,
                'candidats_actifs' => $candidatsActifsParShift->get($b->shift_id, 0),
            ]),
        ];
    }

    /**
     * Entretiens planifiés à venir (chapitre 5 : dashboard).
     */
    private function resumeEntretiens(int $organisationId, ?Collection $shiftIds = null)
    {
        return Interview::where('organisation_id', $organisationId)
            ->when($shiftIds !== null, fn ($q) => $q->whereIn('shift_souhaite_id', $shiftIds))
            ->where('statut', 'planifie')
            ->where('date_entretien', '>=', now()->toDateString())
            ->orderBy('date_entretien')
            ->with(['candidate', 'shiftSouhaite'])
            ->limit(10)
            ->get()
            ->map(fn (Interview $i) => [
                'id' => $i->id,
                'candidat' => $i->candidate->nomComplet(),
                'shift_souhaite' => $i->shiftSouhaite?->nom,
                'date_entretien' => $i->date_entretien->format('Y-m-d'),
                'heure_entretien' => $i->heure_entretien,
            ]);
    }

    private function membre(Request $request)
    {
        $servant = $request->user()->servant;

        if (! $servant) {
            return Inertia::render('Dashboard/Membre', [
                'servant' => null,
                'affectations' => [],
            ]);
        }

        $affectations = $servant->assignationsActives()
            ->with('shiftPosition.shift')
            ->get()
            ->map(fn (Assignment $assignment) => [
                'id' => $assignment->id,
                'poste' => $assignment->shiftPosition->nom,
                'shift' => $assignment->shiftPosition->shift->nom,
                'jour' => $assignment->shiftPosition->shift->jour,
                'heure_debut' => substr($assignment->shiftPosition->shift->heure_debut, 0, 5),
                'heure_fin' => substr($assignment->shiftPosition->shift->heure_fin, 0, 5),
                'depuis' => $assignment->date_debut->format('Y-m-d'),
            ]);

        return Inertia::render('Dashboard/Membre', [
            'servant' => [
                'nom_complet' => $servant->nomComplet(),
                'statut' => $servant->statut,
            ],
            'affectations' => $affectations,
        ]);
    }
}
