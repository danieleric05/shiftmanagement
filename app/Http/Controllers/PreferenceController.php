<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PreferenceController extends Controller
{
    /**
     * Enregistre l'ordre des colonnes de la liste des servants pour
     * l'utilisateur connecté (Conseil du Temple : administrateur et
     * super_admin uniquement). `colonnes: null` réinitialise l'ordre par
     * défaut. L'ordre envoyé doit être une permutation exacte de la liste
     * blanche User::COLONNES_SERVANTS (ni doublon, ni clé inconnue, ni oubli).
     */
    public function updateColonnesServants(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->estAdministrateur(), 403);

        $validated = $request->validate([
            'colonnes' => ['present', 'nullable', 'array', 'list', 'size:'.count(User::COLONNES_SERVANTS)],
            'colonnes.*' => ['required', 'string', 'distinct:strict', Rule::in(User::COLONNES_SERVANTS)],
        ]);

        $preferences = $user->preferences ?? [];
        $colonnes = $validated['colonnes'];

        if ($colonnes === null || $colonnes === User::COLONNES_SERVANTS) {
            unset($preferences['colonnes_servants']);
        } else {
            $preferences['colonnes_servants'] = $colonnes;
        }

        $user->forceFill(['preferences' => $preferences === [] ? null : $preferences])->save();

        return back();
    }
}
