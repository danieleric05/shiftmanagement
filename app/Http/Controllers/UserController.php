<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Shift;
use App\Models\ShiftMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
            ->with(['servant', 'shiftMemberships' => fn ($q) => $q->where('statut', 'actif')->with('shift')])
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                // Rétro-compatible avec les comptes créés avant l'ajout de ces
                // colonnes (ou via une factory de test) : à défaut de valeur
                // stockée, on déduit prénom/nom de `name` à la volée, même
                // convention que la migration de rétro-remplissage.
                'nom' => $u->nom ?: (trim(Str::after($u->name, ' ')) ?: $u->name),
                'prenom' => $u->prenom ?: Str::before($u->name, ' '),
                'email' => $u->email,
                'telephone' => $u->telephone,
                'role_id' => $u->role_id,
                'statut' => $u->statut,
                'must_change_password' => $u->must_change_password,
                'servant_id' => $u->servant?->id,
                'servant_nom' => $u->servant?->nomComplet(),
                'shifts_geres' => $u->shiftMemberships->map(fn (ShiftMember $sm) => [
                    'affectation_id' => $sm->id,
                    'shift_id' => $sm->shift_id,
                    'shift_nom' => $sm->shift->nom,
                ]),
            ]);

        $rolesQuery = Role::orderBy('nom');
        // Idem que sur la page Rôles : un simple administrateur ne doit pas
        // pouvoir attribuer le rôle Super Administrateur à un compte.
        if ($request->user()->role->slug !== 'super_admin') {
            $rolesQuery->where('slug', '!=', 'super_admin');
        }

        return Inertia::render('Settings/Users/Index', [
            'users' => $users,
            'roles' => $rolesQuery->get(['id', 'slug', 'nom', 'gere_shifts']),
            'shifts' => Shift::where('organisation_id', $organisationId)->orderByJourCalendrier()->get(['id', 'nom']),
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
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'role_id' => ['required', 'exists:roles,id'],
            'telephone' => ['nullable', 'string', 'max:50'],
        ]);

        $this->assurerRoleAttribuable($request, $validated['role_id']);

        User::create([
            'name' => "{$validated['prenom']} {$validated['nom']}",
            'nom' => $validated['nom'],
            'prenom' => $validated['prenom'],
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
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'role_id' => ['required', 'exists:roles,id'],
            'statut' => ['required', 'in:actif,suspendu'],
            'telephone' => ['nullable', 'string', 'max:50'],
        ]);

        if ($user->id === $request->user()->id && $validated['statut'] === 'suspendu') {
            abort(422, 'Vous ne pouvez pas suspendre votre propre compte.');
        }

        $this->assurerRoleAttribuable($request, $validated['role_id']);

        $user->update([
            ...$validated,
            'name' => "{$validated['prenom']} {$validated['nom']}",
        ]);

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

    /**
     * Seul un Super Administrateur peut attribuer le rôle Super Administrateur
     * à un compte — sinon un simple administrateur pourrait se l'auto-attribuer
     * via une requête directe, même en ayant masqué le rôle de l'UI.
     */
    private function assurerRoleAttribuable(Request $request, int $roleId): void
    {
        $slug = Role::whereKey($roleId)->value('slug');

        abort_if($slug === 'super_admin' && $request->user()->role->slug !== 'super_admin', 403);
    }
}
