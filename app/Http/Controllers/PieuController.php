<?php

namespace App\Http\Controllers;

use App\Models\Pieu;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PieuController extends Controller
{
    /**
     * Type de parent attendu pour chaque niveau (une Mission est toujours
     * racine, un District se rattache à une Mission, un Pieu à un District).
     */
    private const TYPE_PARENT_ATTENDU = [
        'mission' => null,
        'district' => 'mission',
        'pieu' => 'district',
    ];

    public function index(Request $request)
    {
        return Inertia::render('Settings/Pieux/Index', [
            'pieux' => Pieu::where('organisation_id', $request->user()->organisation_id)
                ->with('parent:id,nom,type')
                ->orderByRaw("FIELD(type, 'mission', 'district', 'pieu')")
                ->orderBy('nom')
                ->get(['id', 'nom', 'type', 'parent_id']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validerDonnees($request);

        Pieu::create([
            ...$validated,
            'organisation_id' => $request->user()->organisation_id,
        ]);

        return back()->with('success', 'Unité ajoutée avec succès.');
    }

    public function update(Request $request, Pieu $pieu)
    {
        $this->authorize('update', $pieu);

        $validated = $this->validerDonnees($request, $pieu);

        $pieu->update($validated);

        return back()->with('success', 'Unité mise à jour avec succès.');
    }

    public function destroy(Request $request, Pieu $pieu)
    {
        $this->authorize('delete', $pieu);

        abort_if($pieu->enfants()->exists(), 422, 'Cette unité a des unités rattachées : détachez-les avant de la supprimer.');

        $pieu->delete();

        return back()->with('success', 'Unité supprimée avec succès.');
    }

    /**
     * @return array{nom: string, type: string, parent_id: int|null}
     */
    private function validerDonnees(Request $request, ?Pieu $pieu = null): array
    {
        $organisationId = $request->user()->organisation_id;

        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['mission', 'district', 'pieu'])],
            'parent_id' => array_filter([
                'nullable',
                Rule::exists('pieux', 'id')->where('organisation_id', $organisationId),
                $pieu ? Rule::notIn([$pieu->id]) : null,
            ]),
        ]);

        $validated['parent_id'] ??= null;

        if ($validated['parent_id'] !== null) {
            $typeAttendu = self::TYPE_PARENT_ATTENDU[$validated['type']];
            $parent = Pieu::find($validated['parent_id']);

            abort_if(
                $typeAttendu === null || $parent?->type !== $typeAttendu,
                422,
                match ($validated['type']) {
                    'district' => 'Le parent d\'un District doit être une Mission.',
                    'pieu' => 'Le parent d\'un Pieu doit être un District.',
                    default => 'Une Mission ne peut pas avoir de parent.',
                }
            );
        }

        return $validated;
    }
}
