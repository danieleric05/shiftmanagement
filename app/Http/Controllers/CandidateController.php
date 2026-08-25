<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\Shift;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CandidateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Candidate::where('organisation_id', $user->organisation_id)
            ->with('shiftSouhaite');

        if (! $user->estAdministrateurOuSecretaire()) {
            $query->whereIn('shift_souhaite_id', $user->shiftsGeres());
        }

        $candidats = $query->orderByDesc('created_at')->get()->map(fn (Candidate $c) => [
            'id' => $c->id,
            'nom_complet' => $c->nomComplet(),
            'telephone' => $c->telephone,
            'shift_souhaite' => $c->shiftSouhaite?->nom,
            'shift_souhaite_id' => $c->shift_souhaite_id,
            'date_appel' => $c->date_appel?->format('Y-m-d'),
            'statut' => $c->statut,
            'notes' => $c->notes,
        ]);

        $shiftsDisponibles = $user->estAdministrateurOuSecretaire()
            ? Shift::where('organisation_id', $user->organisation_id)->orderByJourCalendrier()->get(['id', 'nom'])
            : Shift::where('organisation_id', $user->organisation_id)->whereIn('id', $user->shiftsGeres())->orderByJourCalendrier()->get(['id', 'nom']);

        return Inertia::render('Candidates/Index', [
            'candidats' => $candidats,
            'shifts' => $shiftsDisponibles,
            'estAdministrateur' => $user->estAdministrateur(),
            'peutCreerCandidat' => ! $user->hasRole('secretaire'),
            'compteurs' => [
                'actifs' => $candidats->whereIn('statut', ['nouveau', 'appele', 'entretien_planifie'])->count(),
                'convertis' => $candidats->where('statut', 'converti')->count(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'shift_souhaite_id' => ['required', 'exists:shifts,id'],
            'date_appel' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $shift = Shift::findOrFail($validated['shift_souhaite_id']);
        $this->ensureGereShift($request, $shift);

        Candidate::create([
            ...$validated,
            'organisation_id' => $request->user()->organisation_id,
            'statut' => 'nouveau',
        ]);

        return back()->with('success', 'Candidat ajouté avec succès.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Candidate $candidate)
    {
        abort_if($candidate->organisation_id !== $request->user()->organisation_id, 403);
        if ($candidate->shift_souhaite_id) {
            $this->ensureGereShift($request, Shift::findOrFail($candidate->shift_souhaite_id));
        }

        $validated = $request->validate([
            'telephone' => ['nullable', 'string', 'max:30'],
            'date_appel' => ['nullable', 'date'],
            'statut' => ['required', 'in:nouveau,appele,entretien_planifie,entretien_realise,converti,abandonne'],
            'notes' => ['nullable', 'string'],
        ]);

        $candidate->update($validated);

        return back()->with('success', 'Candidat mis à jour avec succès.');
    }

    /**
     * Remove the specified resource from storage (administrateur uniquement).
     */
    public function destroy(Request $request, Candidate $candidate)
    {
        abort_if($candidate->organisation_id !== $request->user()->organisation_id, 403);
        abort_unless($request->user()->estAdministrateur(), 403);

        $candidate->delete();

        return back()->with('success', 'Candidat supprimé avec succès.');
    }

    private function ensureGereShift(Request $request, Shift $shift): void
    {
        $user = $request->user();
        abort_unless($user->estAdministrateurOuSecretaire() || $user->shiftsGeres()->contains($shift->id), 403);
    }
}
