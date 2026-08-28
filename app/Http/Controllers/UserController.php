<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class UserController extends Controller
{
    /**
     * Liste des comptes de connexion de l'organisation, tous rôles confondus
     * (chapitre : aucune vue n'existait pour voir qui détient quel rôle).
     */
    public function index(Request $request)
    {
        $organisationId = $request->user()->organisation_id;

        $users = User::where('organisation_id', $organisationId)
            ->with('servant')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'telephone' => $u->telephone,
                'role_id' => $u->role_id,
                'statut' => $u->statut,
                'must_change_password' => $u->must_change_password,
                'servant_id' => $u->servant?->id,
                'servant_nom' => $u->servant?->nomComplet(),
            ]);

        return Inertia::render('Settings/Users/Index', [
            'users' => $users,
            'roles' => Role::orderBy('nom')->get(['id', 'slug', 'nom']),
        ]);
    }

    /**
     * Créer un compte directement rattaché à un rôle (administrateur,
     * coordonnateur d'équipe, secrétaire…), indépendamment de tout servant —
     * seul chemin existant jusqu'ici (storeAccount sur un servant) forçait le
     * rôle "membre".
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'role_id' => ['required', 'exists:roles,id'],
            'telephone' => ['nullable', 'string', 'max:50'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $validated['role_id'],
            'telephone' => $validated['telephone'] ?? null,
            'organisation_id' => $request->user()->organisation_id,
            'email_verified_at' => now(),
            'must_change_password' => true,
        ]);

        return back()->with('success', 'Compte créé avec succès.');
    }

    public function update(Request $request, User $user)
    {
        abort_if($user->organisation_id !== $request->user()->organisation_id, 403);

        $validated = $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
            'statut' => ['required', 'in:actif,suspendu'],
            'telephone' => ['nullable', 'string', 'max:50'],
        ]);

        if ($user->id === $request->user()->id && $validated['statut'] === 'suspendu') {
            abort(422, 'Vous ne pouvez pas suspendre votre propre compte.');
        }

        $user->update($validated);

        return back()->with('success', 'Compte mis à jour avec succès.');
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($user->organisation_id !== $request->user()->organisation_id, 403);
        abort_if($user->id === $request->user()->id, 422, 'Vous ne pouvez pas supprimer votre propre compte.');
        abort_if($user->servant()->exists(), 422, 'Ce compte est lié à un servant : révoquez-le depuis la fiche du servant plutôt que depuis cette page.');

        $user->delete();

        return back()->with('success', 'Compte supprimé avec succès.');
    }
}
