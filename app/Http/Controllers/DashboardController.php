<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Interview;
use App\Models\Shift;
use App\Models\ShiftRecruitmentNeed;
use App\Models\ShiftTransferRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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

        if ($roleSlug === 'coordonnateur_equipe') {
            return $this->chefEquipe($request);
        }

        if ($roleSlug === 'secretaire') {
            return redirect()->route('candidates.index');
        }

        return $this->membre($request);
    }

    /**
     * Tableau de bord des dirigeants (administrateur) : reproduit la structure
     * de l'onglet "TABLEAU DE BORD" du cahier des charges — une liste de liens
     * vers les shifts, et un résumé actionnable des relèves, permutations,
     * besoins de recrutement et entretiens. Le détail (roster, historique
     * complet) reste sur les pages dédiées, jamais dupliqué ici.
     */
    private function admin(Request $request)
    {
        $organisationId = $request->user()->organisation_id;

        return Inertia::render('Dashboard/Admin', [
            'shifts' => $this->listeShifts($organisationId),
            'releves' => $this->resumeTransferts($organisationId, 'releve'),
            'permutations' => $this->resumeTransferts($organisationId, 'permutation'),
            'besoins' => $this->resumeRecrutement($organisationId),
            'entretiens' => $this->resumeEntretiens($organisationId),
        ]);
    }

    private function chefEquipe(Request $request)
    {
        $user = $request->user();
        $shiftIds = $user->shiftsGeres();

        return Inertia::render('Dashboard/ChefEquipe', [
            'shifts' => $this->listeShifts($user->organisation_id, null, $shiftIds),
            'releves' => $this->resumeTransferts($user->organisation_id, 'releve', $shiftIds),
            'permutations' => $this->resumeTransferts($user->organisation_id, 'permutation', $shiftIds),
            'besoins' => $this->resumeRecrutement($user->organisation_id, $shiftIds),
            'entretiens' => $this->resumeEntretiens($user->organisation_id, $shiftIds),
        ]);
    }

    /**
     * Liste compacte des shifts (section "SHIFTS" du tableau de bord) : juste
     * de quoi afficher un lien vers la fiche de chaque shift, sans le roster.
     *
     * $shiftIds filtre les shifts retournés (null = tous, pour l'administrateur
     * et pour le coordinateur qui doit voir tous les shifts en lecture seule).
     * $mesShiftIds sert uniquement à marquer, pour un coordinateur, lesquels
     * de ces shifts il gère réellement (le reste restant consultable en
     * lecture seule via shifts.mine.show).
     */
    private function listeShifts(int $organisationId, ?Collection $shiftIds = null, ?Collection $mesShiftIds = null): Collection
    {
        return Shift::where('organisation_id', $organisationId)
            ->when($shiftIds !== null, fn ($q) => $q->whereIn('id', $shiftIds))
            ->withCount('positions as postes_total')
            ->with(['positions' => fn ($q) => $q->select('id', 'shift_id')
                ->withCount(['assignments' => fn ($a) => $a->where('statut', 'actif')]),
            ])
            ->orderByJourCalendrier()
            ->orderBy('heure_debut')
            ->get()
            ->map(fn (Shift $shift) => [
                'id' => $shift->id,
                'nom' => $shift->nom,
                'jour' => $shift->jour,
                'heure_debut' => substr($shift->heure_debut, 0, 5),
                'heure_fin' => substr($shift->heure_fin, 0, 5),
                'postes_total' => $shift->postes_total,
                'postes_vacants' => $shift->positions->where('assignments_count', 0)->count(),
                'gere' => $mesShiftIds === null || $mesShiftIds->contains($shift->id),
                'genre' => Str::contains(Str::lower($shift->nom), ['sœur', 'soeur']) ? 'soeurs' : 'freres',
            ]);
    }

    /**
     * Demandes de relève ou de permutation (2 dernières semaines), avec les
     * colonnes du cahier des charges. Le résultat/date est saisi ici même par
     * l'administrateur puis répercuté sur la page du shift.
     */
    private function resumeTransferts(int $organisationId, string $type, ?Collection $shiftIds = null): array
    {
        $base = ShiftTransferRequest::where('organisation_id', $organisationId)
            ->where('type', $type)
            ->when($shiftIds !== null, fn ($q) => $q->whereIn('shift_id', $shiftIds));

        $recentes = (clone $base)
            ->with(['shift', 'shiftDestination', 'servant'])
            ->orderByDesc('date_demande')
            ->limit(5)
            ->get()
            ->map(fn (ShiftTransferRequest $d) => [
                'id' => $d->id,
                'shift' => $d->shift->nom,
                'shift_destination' => $d->shiftDestination?->nom,
                'servant' => $d->servant->nomComplet(),
                'coordonnees' => $d->servant->telephone,
                'motif' => $d->motif,
                'date_demande' => $d->date_demande->format('Y-m-d'),
                'discussion_servant' => $d->discussion_servant,
                'approuve_deux_shifts' => $d->approuve_deux_shifts,
                'statut' => $d->statut,
                'resultat' => $d->resultat,
                'resultat_date' => $d->resultat_date?->format('Y-m-d'),
            ]);

        return [
            'en_attente' => (clone $base)->enAttente()->count(),
            'recentes' => $recentes,
        ];
    }

    /**
     * Besoins en nouveaux servants pour les 2 prochains mois, résumés en
     * "Sœurs recherchées" / "Frères recherchés" (le genre du shift se déduit
     * de son nom, ex. "Mardi Matin Sœurs").
     */
    private function resumeRecrutement(int $organisationId, ?Collection $shiftIds = null): array
    {
        $besoins = ShiftRecruitmentNeed::where('organisation_id', $organisationId)
            ->when($shiftIds !== null, fn ($q) => $q->whereIn('shift_id', $shiftIds))
            ->where('nombre_a_recruter', '>', 0)
            ->with('shift')
            ->get();

        $estShiftSoeurs = fn (string $nom) => Str::contains(Str::lower($nom), ['sœur', 'soeur']);

        return [
            'soeurs_recherchees' => (int) $besoins->filter(fn ($b) => $estShiftSoeurs($b->shift->nom))->sum('nombre_a_recruter'),
            'freres_recherches' => (int) $besoins->reject(fn ($b) => $estShiftSoeurs($b->shift->nom))->sum('nombre_a_recruter'),
        ];
    }

    /**
     * Entretiens programmés (à venir), avec les colonnes du cahier des
     * charges : heure, engagement vu, et la saisie résultat + affectation.
     */
    private function resumeEntretiens(int $organisationId, ?Collection $shiftIds = null): Collection
    {
        return Interview::where('organisation_id', $organisationId)
            ->when($shiftIds !== null, fn ($q) => $q->whereIn('shift_souhaite_id', $shiftIds))
            ->where('statut', 'planifie')
            ->where('date_entretien', '>=', now()->toDateString())
            ->orderBy('date_entretien')
            ->with(['candidate', 'shiftSouhaite'])
            ->limit(5)
            ->get()
            ->map(fn (Interview $i) => [
                'id' => $i->id,
                'candidat' => $i->candidate->nomComplet(),
                'shift_souhaite' => $i->shiftSouhaite?->nom,
                'date_entretien' => $i->date_entretien->format('Y-m-d'),
                'heure_entretien' => $i->heure_entretien,
                'engagement_vu' => $i->engagement_vu,
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
