<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\Servant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(?Organisation $organisation = null): User
    {
        $organisation ??= Organisation::factory()->create();
        $role = Role::factory()->create(['slug' => 'administrateur', 'nom' => 'administrateur']);

        return User::factory()->create([
            'organisation_id' => $organisation->id,
            'role_id' => $role->id,
        ]);
    }

    public function test_administrateur_peut_lister_les_utilisateurs(): void
    {
        $admin = $this->makeAdmin();
        User::factory()->create(['organisation_id' => $admin->organisation_id, 'role_id' => $admin->role_id]);

        $this->actingAs($admin)->get('/parametres/utilisateurs')->assertOk();
    }

    public function test_administrateur_peut_creer_un_compte_avec_un_role(): void
    {
        $admin = $this->makeAdmin();
        $coordo = Role::factory()->create(['slug' => 'coordonnateur_equipe', 'nom' => "Coordonnateur d'équipe"]);

        $this->actingAs($admin)->post('/parametres/utilisateurs', [
            'name' => 'Nouveau Coordo',
            'email' => 'coordo@example.com',
            'password' => 'MotDePasse123!',
            'role_id' => $coordo->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'coordo@example.com',
            'role_id' => $coordo->id,
            'organisation_id' => $admin->organisation_id,
            'must_change_password' => true,
        ]);
    }

    public function test_administrateur_peut_changer_le_role_et_suspendre_un_compte(): void
    {
        $admin = $this->makeAdmin();
        $secretaire = Role::factory()->create(['slug' => 'secretaire', 'nom' => 'Secrétaire']);
        $user = User::factory()->create(['organisation_id' => $admin->organisation_id, 'role_id' => $admin->role_id]);

        $this->actingAs($admin)->put("/parametres/utilisateurs/{$user->id}", [
            'role_id' => $secretaire->id,
            'statut' => 'suspendu',
            'telephone' => '0700000000',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role_id' => $secretaire->id,
            'statut' => 'suspendu',
        ]);
    }

    public function test_administrateur_ne_peut_pas_suspendre_son_propre_compte(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->put("/parametres/utilisateurs/{$admin->id}", [
            'role_id' => $admin->role_id,
            'statut' => 'suspendu',
        ])->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'statut' => 'actif']);
    }

    public function test_un_compte_suspendu_est_deconnecte_a_la_requete_suivante(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create(['organisation_id' => $admin->organisation_id, 'role_id' => $admin->role_id, 'statut' => 'suspendu']);

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_administrateur_peut_supprimer_un_compte_non_lie_a_un_servant(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create(['organisation_id' => $admin->organisation_id, 'role_id' => $admin->role_id]);

        $this->actingAs($admin)->delete("/parametres/utilisateurs/{$user->id}")->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_suppression_refusee_si_le_compte_est_lie_a_un_servant(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create(['organisation_id' => $admin->organisation_id, 'role_id' => $admin->role_id]);
        Servant::factory()->create(['organisation_id' => $admin->organisation_id, 'user_id' => $user->id]);

        $this->actingAs($admin)->delete("/parametres/utilisateurs/{$user->id}")->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_coordonnateur_n_a_pas_acces_a_la_gestion_des_utilisateurs(): void
    {
        $organisation = Organisation::factory()->create();
        $coordoRole = Role::factory()->create(['slug' => 'coordonnateur_equipe', 'nom' => "Coordonnateur d'équipe"]);
        $coordo = User::factory()->create(['organisation_id' => $organisation->id, 'role_id' => $coordoRole->id]);

        $this->actingAs($coordo)->get('/parametres/utilisateurs')->assertForbidden();
    }
}
