<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\Interview;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\WorkflowStep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InterviewController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Interview::where('organisation_id', $user->organisation_id)
            ->with(['candidate', 'shiftSouhaite', 'shiftAffecte', 'planifiePar', 'decideur']);

        if (! $user->estAdministrateurOuSecretaire()) {
            $query->whereIn('shift_souhaite_id', $user->shiftsGeres());
        }

        $entretiens = $query->orderBy('date_entretien')->get()->map(fn (Interview $i) => [
            'id' => $i->id,
            'candidat' => $i->candidate->nomComplet(),
            'candidate_id' => $i->candidate_id,
            'shift_souhaite' => $i->shiftSouhaite?->nom,
            'date_entretien' => $i->date_entretien->format('Y-m-d'),
            'heure_entretien' => $i->heure_entretien,
            'engagement_vu' => $i->engagement_vu,
            'statut' => $i->statut,
            'resultat' => $i->resultat,
            'shift_affecte' => $i->shiftAffecte?->nom,
            'planifie_par' => $i->planifiePar->name,
            'decideur' => $i->decideur?->name,
        ]);

        $shiftsDisponibles = $user->estAdministrateurOuSecretaire()
            ? Shift::where('organisation_id', $user->organisation_id)->orderByJourCalendrier()->get(['id', 'nom'])
            : Shift::where('organisation_id', $user->organisation_id)->whereIn('id', $user->shiftsGeres())->orderByJourCalendrier()->get(['id', 'nom']);

        $candidatsDisponibles = Candidate::where('organisation_id', $user->organisation_id)
            ->whereIn('statut', ['nouveau', 'appele'])
            ->when(! $user->estAdministrateurOuSecretaire(), fn ($q) => $q->whereIn('shift_souhaite_id', $user->shiftsGeres()))
            ->get(['id', 'nom', 'prenom']);

        return Inertia::render('Interviews/Index', [
            'entretiens' => $entretiens,
            'shifts' => $shiftsDisponibles,
            'candidats' => $candidatsDisponibles,
            'estAdministrateur' => $user->estAdministrateur(),
            'compteurs' => [
                'a_venir' => $entretiens->where('statut', 'planifie')->count(),
                'realises' => $entretiens->where('statut', 'realise')->count(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $shift = Shift::findOrFail($request->input('shift_souhaite_id'));
        $this->authorize('create', [Interview::class, $shift]);

        $validated = $request->validate([
            'candidate_id' => ['required', 'exists:candidates,id'],
            'shift_souhaite_id' => ['required', 'exists:shifts,id'],
            'date_entretien' => ['required', 'date'],
            'heure_entretien' => ['nullable', 'date_format:H:i'],
            'engagement_vu' => ['nullable', 'boolean'],
        ]);

        $candidate = Candidate::findOrFail($validated['candidate_id']);
        abort_if($candidate->organisation_id !== $request->user()->organisation_id, 403);

        Interview::create([
            ...$validated,
            'organisation_id' => $request->user()->organisation_id,
            'planifie_par' => $request->user()->id,
            'statut' => 'planifie',
        ]);

        $candidate->update(['statut' => 'entretien_planifie']);

        return back()->with('success', 'Entretien planifié avec succès.');
    }

    /**
     * Saisir le résultat de l'entretien (administrateur uniquement) et, si validé,
     * convertir le candidat en Servant en réutilisant le pattern de bootstrap
     * du parcours d'intégration (cf. ServantController::store()).
     */
    public function resolve(Request $request, Interview $interview)
    {
        abort_if($interview->organisation_id !== $request->user()->organisation_id, 403);
        $this->authorize('resolve', $interview);

        $validated = $request->validate([
            'resultat' => ['required', 'string'],
            'valide' => ['required', 'boolean'],
            'shift_affecte_id' => ['required_if:valide,true', 'nullable', 'exists:shifts,id'],
        ]);

        DB::transaction(function () use ($interview, $validated, $request) {
            $interview->update([
                'statut' => 'realise',
                'resultat' => $validated['resultat'],
                'shift_affecte_id' => $validated['shift_affecte_id'] ?? null,
                'decideur_id' => $request->user()->id,
                'decided_at' => now(),
            ]);

            $candidate = $interview->candidate;

            if ($validated['valide']) {
                $servant = Servant::create([
                    'organisation_id' => $candidate->organisation_id,
                    'nom' => $candidate->nom,
                    'prenom' => $candidate->prenom,
                    'telephone' => $candidate->telephone,
                    'statut' => 'recommande',
                ]);

                foreach (WorkflowStep::orderBy('ordre')->get() as $index => $step) {
                    $servant->workflowSteps()->create([
                        'workflow_step_id' => $step->id,
                        'statut' => $step->cle === 'entretien' ? 'termine' : ($index === 0 ? 'en_cours' : 'en_attente'),
                    ]);
                }

                $candidate->update(['statut' => 'converti', 'servant_id' => $servant->id]);
            } else {
                $candidate->update(['statut' => 'entretien_realise']);
            }
        });

        return back()->with('success', 'Résultat de l\'entretien enregistré avec succès.');
    }
}
