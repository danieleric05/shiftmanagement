<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\Servant;
use App\Models\Shift;
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
        $coordo = Role::factory()->create(['slug' => 'coordonnateur_equipe', 'nom' => "Coordonnateur d'équipe", 'gere_shifts' => true]);

        $this->actingAs($admin)->post('/parametres/utilisateurs', [
            'nom' => 'Coordo',
            'prenom' => 'Nouveau',
            'email' => 'coordo@example.com',
            'password' => 'MotDePasse123!',
            'role_id' => $coordo->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'coordo@example.com',
            'nom' => 'Coordo',
            'prenom' => 'Nouveau',
            'role_id' => $coordo->id,
            'organisation_id' => $admin->organisation_id,
            'must_change_password' => true,
        ]);
    }

    public function test_administrateur_peut_changer_le_role_et_suspendre_un_compte(): void
    {
        $admin = $this->makeAdmin();
        $autreRole = Role::factory()->create(['slug' => 'autre_role', 'nom' => 'Autre rôle']);
        $user = User::factory()->create(['organisation_id' => $admin->organisation_id, 'role_id' => $admin->role_id]);

        $this->actingAs($admin)->put("/parametres/utilisateurs/{$user->id}", [
            'nom' => 'Membre',
            'prenom' => 'Nouveau',
            'role_id' => $autreRole->id,
            'statut' => 'suspendu',
            'telephone' => '0700000000',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role_id' => $autreRole->id,
            'statut' => 'suspendu',
        ]);
    }

    public function test_administrateur_ne_peut_pas_suspendre_son_propre_compte(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->put("/parametres/utilisateurs/{$admin->id}", [
            'nom' => 'Admin',
            'prenom' => 'Test',
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
        $coordoRole = Role::factory()->create(['slug' => 'coordonnateur_equipe', 'nom' => "Coordonnateur d'équipe", 'gere_shifts' => true]);
        $coordo = User::factory()->create(['organisation_id' => $organisation->id, 'role_id' => $coordoRole->id]);

        $this->actingAs($coordo)->get('/parametres/utilisateurs')->assertForbidden();
    }

    public function test_administrateur_peut_affecter_puis_retirer_un_shift_depuis_la_page_utilisateurs(): void
    {
        $admin = $this->makeAdmin();
        $coordoRole = Role::factory()->create(['slug' => 'coordonnateur_equipe', 'nom' => "Coordonnateur d'équipe", 'gere_shifts' => true]);
        $coordo = User::factory()->create(['organisation_id' => $admin->organisation_id, 'role_id' => $coordoRole->id]);
        $shift = Shift::create([
            'organisation_id' => $admin->organisation_id, 'nom' => 'Shift Test',
            'jour' => 'mardi', 'heure_debut' => '07:00', 'heure_fin' => '11:00', 'statut' => 'actif',
        ]);

        $this->actingAs($admin)->post("/shifts/{$shift->id}/membres", ['user_id' => $coordo->id])->assertRedirect();

        $response = $this->actingAs($admin)->get('/parametres/utilisateurs');
        $response->assertInertia(fn ($page) => $page
            ->where('users.data', fn ($users) => collect($users)->firstWhere('id', $coordo->id)['shifts_geres'][0]['shift_nom'] === 'Shift Test')
        );

        $affectationId = $coordo->shiftMemberships()->first()->id;
        $this->actingAs($admin)->delete("/shifts/{$shift->id}/membres/{$affectationId}")->assertRedirect();

        $this->assertDatabaseHas('shift_members', ['id' => $affectationId, 'statut' => 'termine']);
    }

    private function idsListes($response): array
    {
        return collect($response->viewData('page')['props']['users']['data'])->pluck('id')->all();
    }

    public function test_recherche_par_nom_et_par_email(): void
    {
        $admin = $this->makeAdmin();
        $alice = User::factory()->create(['organisation_id' => $admin->organisation_id, 'role_id' => $admin->role_id, 'name' => 'Alice Kouassi', 'nom' => 'Kouassi', 'prenom' => 'Alice', 'email' => 'alice@example.com']);
        $bob = User::factory()->create(['organisation_id' => $admin->organisation_id, 'role_id' => $admin->role_id, 'name' => 'Bob Yao', 'nom' => 'Yao', 'prenom' => 'Bob', 'email' => 'contact.bob@exemple.ci']);

        $parNom = $this->actingAs($admin)->get('/parametres/utilisateurs?recherche=kouas')->assertOk();
        $this->assertSame([$alice->id], $this->idsListes($parNom));
        $parNom->assertInertia(fn ($page) => $page->where('filtreRecherche', 'kouas')->where('users.total', 1));

        $parEmail = $this->actingAs($admin)->get('/parametres/utilisateurs?recherche=exemple.ci')->assertOk();
        $this->assertSame([$bob->id], $this->idsListes($parEmail));

        $aucun = $this->actingAs($admin)->get('/parametres/utilisateurs?recherche=introuvable')->assertOk();
        $this->assertSame([], $this->idsListes($aucun));
    }

    public function test_filtre_par_role(): void
    {
        $admin = $this->makeAdmin();
        $coordoRole = Role::factory()->create(['slug' => 'coordonnateur_equipe', 'nom' => "Coordonnateur d'équipe", 'gere_shifts' => true]);
        $coordo = User::factory()->create(['organisation_id' => $admin->organisation_id, 'role_id' => $coordoRole->id]);

        $response = $this->actingAs($admin)->get("/parametres/utilisateurs?role={$coordoRole->id}")->assertOk();
        $this->assertSame([$coordo->id], $this->idsListes($response));
    }

    public function test_la_liste_et_la_recherche_sont_limitees_a_l_organisation(): void
    {
        $admin = $this->makeAdmin();
        $collegue = User::factory()->create(['organisation_id' => $admin->organisation_id, 'role_id' => $admin->role_id, 'name' => 'Marie Dupont', 'email' => 'marie@orga-a.ci']);
        $autreOrganisation = Organisation::factory()->create();
        $etranger = User::factory()->create(['organisation_id' => $autreOrganisation->id, 'role_id' => $admin->role_id, 'name' => 'Marie Martin', 'email' => 'marie@orga-b.ci']);

        $liste = $this->actingAs($admin)->get('/parametres/utilisateurs')->assertOk();
        $this->assertNotContains($etranger->id, $this->idsListes($liste));

        $recherche = $this->actingAs($admin)->get('/parametres/utilisateurs?recherche=marie')->assertOk();
        $this->assertSame([$collegue->id], $this->idsListes($recherche));
    }

    public function test_super_admin_masque_pour_un_administrateur(): void
    {
        $admin = $this->makeAdmin();
        $superRole = Role::factory()->create(['slug' => 'super_admin', 'nom' => 'Super Administrateur']);
        $super = User::factory()->create(['organisation_id' => $admin->organisation_id, 'role_id' => $superRole->id, 'name' => 'Super Patron', 'email' => 'super@example.com']);

        $liste = $this->actingAs($admin)->get('/parametres/utilisateurs')->assertOk();
        $this->assertNotContains($super->id, $this->idsListes($liste));
        $liste->assertInertia(fn ($page) => $page->where('roles', fn ($roles) => collect($roles)->pluck('slug')->doesntContain('super_admin')));

        $recherche = $this->actingAs($admin)->get('/parametres/utilisateurs?recherche=super')->assertOk();
        $this->assertSame([], $this->idsListes($recherche));

        $parRole = $this->actingAs($admin)->get("/parametres/utilisateurs?role={$superRole->id}")->assertOk();
        $this->assertSame([], $this->idsListes($parRole));

        // Ni modification ni suppression par requête directe.
        $this->actingAs($admin)->put("/parametres/utilisateurs/{$super->id}", [
            'nom' => 'Patron', 'prenom' => 'Super', 'role_id' => $admin->role_id, 'statut' => 'suspendu',
        ])->assertForbidden();
        $this->actingAs($admin)->delete("/parametres/utilisateurs/{$super->id}")->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $super->id, 'role_id' => $superRole->id, 'statut' => 'actif']);

        // Le super administrateur, lui, voit tous les comptes.
        $vueSuper = $this->actingAs($super)->get('/parametres/utilisateurs')->assertOk();
        $this->assertContains($super->id, $this->idsListes($vueSuper));
        $this->assertContains($admin->id, $this->idsListes($vueSuper));
    }

    public function test_la_pagination_conserve_la_recherche(): void
    {
        $admin = $this->makeAdmin();
        User::factory()->count(35)->create(['organisation_id' => $admin->organisation_id, 'role_id' => $admin->role_id, 'email' => fn () => fake()->unique()->userName().'@paginer.ci']);

        $response = $this->actingAs($admin)->get('/parametres/utilisateurs?recherche=paginer')->assertOk();
        $users = $response->viewData('page')['props']['users'];
        $this->assertSame(35, $users['total']);
        $this->assertCount(30, $users['data']);
        $this->assertStringContainsString('recherche=paginer', $users['next_page_url']);
    }
}
