<?php

namespace App\Http\Controllers;

use App\Models\ShiftTemplate;
use App\Models\ShiftTemplatePosition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
     * Corriger le nom d'un poste du modèle (erreur de saisie à la création).
     * Le nom d'un ShiftPosition est une copie figée à la création (pas une
     * référence dynamique) : on la propage donc explicitement à tous les
     * postes déjà créés sur de vrais Shifts à partir de ce poste de modèle,
     * sinon la correction resterait invisible sur le roster.
     */
    public function updatePosition(Request $request, ShiftTemplate $shiftTemplate, ShiftTemplatePosition $position)
    {
        $this->authorize('update', $shiftTemplate);

        abort_if($position->shift_template_id !== $shiftTemplate->id, 404);

        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($position, $validated) {
            $position->update($validated);
            $position->shiftPositions()->update(['nom' => $validated['nom']]);
        });

        return back()->with('success', 'Poste mis à jour avec succès, y compris sur les Shifts qui l\'utilisent déjà.');
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

    /**
     * Monter/descendre un poste d'un rang dans la liste (ordre d'affichage
     * sur les Shifts qui utilisent ce modèle). Renormalise au passage tous
     * les rangs en 0..n-1, ce qui élimine aussi les égalités de rang entre
     * variantes masculine/féminine d'un même poste (elles avancent ensemble
     * la première fois que l'une des deux est déplacée).
     */
    public function movePosition(Request $request, ShiftTemplate $shiftTemplate, ShiftTemplatePosition $position)
    {
        $this->authorize('update', $shiftTemplate);

        abort_if($position->shift_template_id !== $shiftTemplate->id, 404);

        $validated = $request->validate([
            'direction' => ['required', 'in:haut,bas'],
        ]);

        $positions = $shiftTemplate->positions()->orderBy('ordre')->orderBy('id')->get()->values();
        $index = $positions->search(fn (ShiftTemplatePosition $p) => $p->id === $position->id);
        $cible = $validated['direction'] === 'haut' ? $index - 1 : $index + 1;

        if ($index !== false && $cible >= 0 && $cible < $positions->count()) {
            [$positions[$index], $positions[$cible]] = [$positions[$cible], $positions[$index]];

            $positions->each(function (ShiftTemplatePosition $p, int $i) {
                if ($p->ordre !== $i) {
                    $p->update(['ordre' => $i]);
                }
            });
        }

        return back()->with('success', 'Poste déplacé avec succès.');
    }
}
