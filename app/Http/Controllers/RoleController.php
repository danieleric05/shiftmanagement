<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class RoleController extends Controller
{
    /**
     * Rôles porteurs de permissions codées en dur (routes, policies) : ne
     * peuvent être ni renommés (slug) ni supprimés depuis cette page.
     */
    private const SLUGS_PROTEGES = ['super_admin', 'administrateur', 'coordonnateur_equipe', 'secretaire'];

    public function index()
    {
        return Inertia::render('Settings/Roles/Index', [
            'roles' => Role::withCount(['users', 'shiftMembers'])
                ->orderBy('nom')
                ->get(['id', 'slug', 'nom', 'description', 'gere_shifts'])
                ->map(fn (Role $role) => [
                    'id' => $role->id,
                    'slug' => $role->slug,
                    'nom' => $role->nom,
                    'description' => $role->description,
                    'gere_shifts' => $role->gere_shifts,
                    'protege' => in_array($role->slug, self::SLUGS_PROTEGES, true),
                    'utilise' => $role->users_count > 0 || $role->shift_members_count > 0,
                ]),
        ]);
    }

    /**
     * Créer un rôle personnalisé (ex. "Équipe du bureau"). Un rôle
     * personnalisé n'obtient aucune permission particulière au-delà de
     * l'accès de base (tableau de bord, profil) tant qu'aucune route n'est
     * explicitement ouverte pour son slug — à la différence des 4 rôles
     * protégés dont les permissions sont codées dans les routes/policies.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'gere_shifts' => ['nullable', 'boolean'],
        ]);

        $slug = Str::slug($validated['nom'], '_');

        if ($slug === '' || Role::where('slug', $slug)->exists()) {
            return back()->withErrors(['nom' => 'Un rôle avec un nom proche existe déjà, choisissez un nom différent.'])->withInput();
        }

        $role = Role::create([
            'slug' => $slug,
            'nom' => $validated['nom'],
            'description' => $validated['description'] ?? null,
            'gere_shifts' => $validated['gere_shifts'] ?? false,
        ]);

        return back()->with('success', "Rôle « {$role->nom} » créé avec succès.");
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'gere_shifts' => ['nullable', 'boolean'],
        ]);

        $validated['gere_shifts'] = $validated['gere_shifts'] ?? false;

        $role->update($validated);

        return back()->with('success', 'Rôle mis à jour avec succès.');
    }

    public function destroy(Role $role)
    {
        abort_if(in_array($role->slug, self::SLUGS_PROTEGES, true), 422, 'Ce rôle est protégé et ne peut pas être supprimé.');
        abort_if($role->users()->exists() || $role->shiftMembers()->exists(), 422, 'Ce rôle est encore attribué à des comptes : il ne peut pas être supprimé.');

        $role->delete();

        return back()->with('success', 'Rôle supprimé avec succès.');
    }
}
