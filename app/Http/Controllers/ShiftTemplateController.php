<?php

namespace App\Http\Controllers;

use App\Models\ShiftTemplate;
use App\Models\ShiftTemplatePosition;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShiftTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $templates = ShiftTemplate::where('organisation_id', $request->user()->organisation_id)
            ->withCount('positions')
            ->orderBy('nom')
            ->get()
            ->map(fn (ShiftTemplate $template) => [
                'id' => $template->id,
                'nom' => $template->nom,
                'description' => $template->description,
                'positions_count' => $template->positions_count,
            ]);

        return Inertia::render('ShiftTemplates/Index', [
            'templates' => $templates,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('ShiftTemplates/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $validated['organisation_id'] = $request->user()->organisation_id;

        $template = ShiftTemplate::create($validated);

        return redirect()->route('shift-templates.show', $template)->with('success', 'Modèle de Shift créé avec succès.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, ShiftTemplate $shiftTemplate)
    {
        $this->authorize('view', $shiftTemplate);

        return Inertia::render('ShiftTemplates/Show', [
            'template' => [
                'id' => $shiftTemplate->id,
                'nom' => $shiftTemplate->nom,
                'description' => $shiftTemplate->description,
            ],
            'positions' => $shiftTemplate->positions()->get(['id', 'nom', 'ordre']),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, ShiftTemplate $shiftTemplate)
    {
        $this->authorize('update', $shiftTemplate);

        return Inertia::render('ShiftTemplates/Edit', [
            'template' => [
                'id' => $shiftTemplate->id,
                'nom' => $shiftTemplate->nom,
                'description' => $shiftTemplate->description,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ShiftTemplate $shiftTemplate)
    {
        $this->authorize('update', $shiftTemplate);

        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $shiftTemplate->update($validated);

        return redirect()->route('shift-templates.show', $shiftTemplate)->with('success', 'Modèle mis à jour avec succès.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, ShiftTemplate $shiftTemplate)
    {
        $this->authorize('delete', $shiftTemplate);

        $shiftTemplate->delete();

        return redirect()->route('shift-templates.index')->with('success', 'Modèle supprimé avec succès.');
    }

    /**
     * Ajouter un poste au modèle.
     */
    public function storePosition(Request $request, ShiftTemplate $shiftTemplate)
    {
        $this->authorize('update', $shiftTemplate);

        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
        ]);

        $ordre = $shiftTemplate->positions()->max('ordre') + 1;

        $shiftTemplate->positions()->create([
            'nom' => $validated['nom'],
            'ordre' => $ordre,
        ]);

        return back()->with('success', 'Poste ajouté avec succès.');
    }

    /**
     * Retirer un poste du modèle.
     */
    public function destroyPosition(Request $request, ShiftTemplate $shiftTemplate, ShiftTemplatePosition $position)
    {
        $this->authorize('update', $shiftTemplate);

        abort_if($position->shift_template_id !== $shiftTemplate->id, 404);

        $position->delete();

        return back()->with('success', 'Poste supprimé avec succès.');
    }
}
