<?php

namespace App\Http\Controllers;

use App\Models\Pieu;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PieuController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Settings/Pieux/Index', [
            'pieux' => Pieu::where('organisation_id', $request->user()->organisation_id)
                ->orderBy('nom')
                ->get(['id', 'nom']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
        ]);

        Pieu::create([
            ...$validated,
            'organisation_id' => $request->user()->organisation_id,
        ]);

        return back()->with('success', 'Pieu ajouté avec succès.');
    }

    public function update(Request $request, Pieu $pieu)
    {
        $this->ensureSameOrganisation($request, $pieu);

        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
        ]);

        $pieu->update($validated);

        return back()->with('success', 'Pieu mis à jour avec succès.');
    }

    public function destroy(Request $request, Pieu $pieu)
    {
        $this->ensureSameOrganisation($request, $pieu);

        $pieu->delete();

        return back()->with('success', 'Pieu supprimé avec succès.');
    }

    private function ensureSameOrganisation(Request $request, Pieu $pieu): void
    {
        abort_if($pieu->organisation_id !== $request->user()->organisation_id, 403);
    }
}
