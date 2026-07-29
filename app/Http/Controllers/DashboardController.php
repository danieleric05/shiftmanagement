<?php

namespace App\Http\Controllers;

use App\Models\GovernanceRequest;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftMember;
use App\Models\ShiftPosition;
use Illuminate\Http\Request;
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

        if (in_array($roleSlug, ['chef_equipe', 'chef_adjoint'], true)) {
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
        ]);
    }

    private function chefEquipe(Request $request)
    {
        $user = $request->user();

        $shifts = $user->shiftMemberships()
            ->where('statut', 'actif')
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['chef_equipe', 'chef_adjoint']))
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
            ->map(fn (\App\Models\Assignment $assignment) => [
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
