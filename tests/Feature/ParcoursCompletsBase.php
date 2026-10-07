<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Organisation;
use App\Models\Pieu;
use App\Models\Role;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftMember;
use App\Models\ShiftPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Socle commun des parcours complets (ParcoursCompletsXxxTest) : une
 * organisation, deux shifts et des utilisateurs par rôle. Les parcours
 * enchaînent des requêtes HTTP successives comme le ferait un utilisateur.
 */
abstract class ParcoursCompletsBase extends TestCase
{
    use RefreshDatabase;

    protected Organisation $organisation;

    protected Shift $shiftA;

    protected Shift $shiftB;

    protected Pieu $pieu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = Organisation::factory()->create(['license_expires_at' => now()->addYear()]);
        $this->shiftA = $this->makeShift('Mardi Matin Frères');
        $this->shiftB = $this->makeShift('Jeudi Soir Frères');
        $this->pieu = Pieu::create(['organisation_id' => $this->organisation->id, 'nom' => 'Pieu A']);
    }

    protected function makeUser(string $slug, ?Organisation $organisation = null, array $attributs = []): User
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['nom' => $slug, 'gere_shifts' => $slug === 'coordonnateur_equipe']);

        return User::factory()->create([
            'organisation_id' => ($organisation ?? $this->organisation)->id,
            'role_id' => $role->id,
            ...$attributs,
        ]);
    }

    protected function makeShift(string $nom, ?Organisation $organisation = null): Shift
    {
        return Shift::create([
            'organisation_id' => ($organisation ?? $this->organisation)->id,
            'nom' => $nom,
            'jour' => 'mardi',
            'heure_debut' => '07:00',
            'heure_fin' => '11:00',
            'statut' => 'actif',
        ]);
    }

    protected function makeCoordonnateur(Shift $shift): User
    {
        $coordonnateur = $this->makeUser('coordonnateur_equipe', $shift->organisation);

        ShiftMember::create([
            'shift_id' => $shift->id,
            'user_id' => $coordonnateur->id,
            'role_id' => $coordonnateur->role_id,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        return $coordonnateur;
    }

    protected function makeServantAffecte(Shift $shift, string $poste = 'Poste', array $attributs = []): Servant
    {
        $servant = Servant::factory()->create([
            'organisation_id' => $shift->organisation_id,
            'genre' => 'homme',
            'statut' => 'actif',
            ...$attributs,
        ]);
        $position = ShiftPosition::create(['shift_id' => $shift->id, 'nom' => $poste, 'ordre' => 1]);
        Assignment::create([
            'shift_position_id' => $position->id,
            'servant_id' => $servant->id,
            'date_debut' => now()->subMonth()->toDateString(),
            'statut' => 'actif',
        ]);

        return $servant;
    }

    /** Données de formulaire valides pour modifier un compte (UserController::update). */
    protected function donneesCompte(User $cible, array $surcharges = []): array
    {
        return [
            'nom' => $cible->nom ?: 'Nom',
            'prenom' => $cible->prenom ?: 'Prénom',
            'role_id' => $cible->role_id,
            'statut' => $cible->statut ?? 'actif',
            ...$surcharges,
        ];
    }
}
