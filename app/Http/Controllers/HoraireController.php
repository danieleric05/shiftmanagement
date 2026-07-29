<?php

namespace App\Http\Controllers;

use App\Models\Horaire;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HoraireController extends Controller
{
    public function index(Request $request)
    {
        $horaires = Horaire::where('organisation_id', $request->user()->organisation_id)
            ->orderBy('heure_debut')
            ->get()
            ->map(fn (Horaire $horaire) => [
                'id' => $horaire->id,
                'nom' => $horaire->nom,
                'heure_debut' => substr($horaire->heure_debut, 0, 5),
                'heure_fin' => substr($horaire->heure_fin, 0, 5),
            ]);

        return Inertia::render('Settings/Horaires/Index', [
            'horaires' => $horaires,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i', 'after:heure_debut'],
        ]);

        Horaire::create([
            ...$validated,
            'organisation_id' => $request->user()->organisation_id,
        ]);

        return back()->with('success', 'Horaire ajouté avec succès.');
    }

    public function update(Request $request, Horaire $horaire)
    {
        $this->ensureSameOrganisation($request, $horaire);

        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i', 'after:heure_debut'],
        ]);

        $horaire->update($validated);

        return back()->with('success', 'Horaire mis à jour avec succès.');
    }

    public function destroy(Request $request, Horaire $horaire)
    {
        $this->ensureSameOrganisation($request, $horaire);

        $horaire->delete();

        return back()->with('success', 'Horaire supprimé avec succès.');
    }

    private function ensureSameOrganisation(Request $request, Horaire $horaire): void
    {
        abort_if($horaire->organisation_id !== $request->user()->organisation_id, 403);
    }
}
