<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $roleSlug, ?Organisation $organisation = null): User
    {
        $organisation ??= Organisation::factory()->create();
        $role = Role::factory()->create(['slug' => $roleSlug, 'nom' => $roleSlug]);

        return User::factory()->create([
            'organisation_id' => $organisation->id,
            'role_id' => $role->id,
        ]);
    }

    public function test_administrateur_peut_creer_un_shift(): void
    {
        $admin = $this->makeUser('administrateur');

        $response = $this->actingAs($admin)->post('/shifts', [
            'nom' => 'Shift A - Mardi matin',
            'jour' => 'mardi',
            'heure_debut' => '07:00',
            'heure_fin' => '11:00',
        ]);

        $this->assertDatabaseHas('shifts', [
            'nom' => 'Shift A - Mardi matin',
            'organisation_id' => $admin->organisation_id,
        ]);

        $shift = Shift::first();
        $response->assertRedirect(route('shifts.show', $shift));
    }

    public function test_membre_ne_peut_pas_creer_un_shift(): void
    {
        $membre = $this->makeUser('membre');

        $response = $this->actingAs($membre)->post('/shifts', [
            'nom' => 'Shift interdit',
            'jour' => 'mardi',
            'heure_debut' => '07:00',
            'heure_fin' => '11:00',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('shifts', ['nom' => 'Shift interdit']);
    }

    public function test_administrateur_peut_affecter_et_retirer_un_membre(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeUser('administrateur', $organisation);
        $servantRole = Role::factory()->create(['slug' => 'servant', 'nom' => 'Servant']);
        $membre = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role_id' => $servantRole->id,
        ]);

        $shift = Shift::create([
            'organisation_id' => $organisation->id,
            'nom' => 'Shift Test',
            'jour' => 'mardi',
            'heure_debut' => '07:00',
            'heure_fin' => '11:00',
            'statut' => 'actif',
        ]);

        $this->actingAs($admin)->post("/shifts/{$shift->id}/membres", [
            'user_id' => $membre->id,
            'role_id' => $servantRole->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('shift_members', [
            'shift_id' => $shift->id,
            'user_id' => $membre->id,
            'statut' => 'actif',
        ]);

        $affectation = $shift->shiftMembers()->first();

        $this->actingAs($admin)
            ->delete("/shifts/{$shift->id}/membres/{$affectation->id}")
            ->assertRedirect();

        $this->assertDatabaseHas('shift_members', [
            'id' => $affectation->id,
            'statut' => 'termine',
        ]);
    }

    public function test_membre_voit_son_dashboard_avec_ses_affectations(): void
    {
        $organisation = Organisation::factory()->create();
        $membreRole = Role::factory()->create(['slug' => 'membre', 'nom' => 'Membre']);
        $membre = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role_id' => $membreRole->id,
        ]);

        $servant = Servant::factory()->create([
            'organisation_id' => $organisation->id,
            'user_id' => $membre->id,
            'statut' => 'actif',
        ]);

        $shift = Shift::create([
            'organisation_id' => $organisation->id,
            'nom' => 'Shift Test',
            'jour' => 'mardi',
            'heure_debut' => '07:00',
            'heure_fin' => '11:00',
            'statut' => 'actif',
        ]);

        $position = $shift->positions()->create(['nom' => 'Présidence', 'ordre' => 1]);

        $position->assignments()->create([
            'servant_id' => $servant->id,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        $response = $this->actingAs($membre)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard/Membre')
            ->where('servant.nom_complet', $servant->nomComplet())
            ->has('affectations', 1)
        );
    }

    public function test_membre_sans_servant_lie_voit_un_dashboard_vide(): void
    {
        $membre = $this->makeUser('membre');

        $response = $this->actingAs($membre)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard/Membre')
            ->where('servant', null)
        );
    }

    public function test_administrateur_peut_modifier_et_supprimer_un_shift(): void
    {
        $admin = $this->makeUser('administrateur');

        $shift = Shift::create([
            'organisation_id' => $admin->organisation_id,
            'nom' => 'Shift Test',
            'jour' => 'mardi',
            'heure_debut' => '07:00',
            'heure_fin' => '11:00',
            'statut' => 'actif',
        ]);

        $this->actingAs($admin)->put("/shifts/{$shift->id}", [
            'nom' => 'Shift Test (modifié)',
            'jour' => 'mercredi',
            'heure_debut' => '08:00',
            'heure_fin' => '12:00',
            'statut' => 'inactif',
        ])->assertRedirect();

        $this->assertDatabaseHas('shifts', [
            'id' => $shift->id,
            'nom' => 'Shift Test (modifié)',
            'jour' => 'mercredi',
            'statut' => 'inactif',
        ]);

        $this->actingAs($admin)->delete("/shifts/{$shift->id}")->assertRedirect();
        $this->assertSoftDeleted('shifts', ['id' => $shift->id]);
    }

    public function test_administrateur_peut_ajouter_et_supprimer_un_poste_ad_hoc(): void
    {
        $admin = $this->makeUser('administrateur');

        $shift = Shift::create([
            'organisation_id' => $admin->organisation_id,
            'nom' => 'Shift Test',
            'jour' => 'mardi',
            'heure_debut' => '07:00',
            'heure_fin' => '11:00',
            'statut' => 'actif',
        ]);

        $this->actingAs($admin)->post("/shifts/{$shift->id}/postes", [
            'nom' => 'Servant (poste supplémentaire)',
        ])->assertRedirect();

        $this->assertDatabaseHas('shift_positions', [
            'shift_id' => $shift->id,
            'nom' => 'Servant (poste supplémentaire)',
        ]);

        $position = $shift->positions()->first();

        $this->actingAs($admin)->delete("/shifts/{$shift->id}/postes/{$position->id}")->assertRedirect();
        $this->assertDatabaseMissing('shift_positions', ['id' => $position->id]);
    }

    public function test_administrateur_ne_peut_pas_creer_un_shift_avec_un_modele_dune_autre_organisation(): void
    {
        $admin = $this->makeUser('administrateur');
        $autreOrganisation = Organisation::factory()->create();

        $templateAutreOrg = ShiftTemplate::create([
            'organisation_id' => $autreOrganisation->id,
            'nom' => 'Modèle Autre Org',
        ]);

        $response = $this->actingAs($admin)->post('/shifts', [
            'nom' => 'Shift interdit',
            'jour' => 'mardi',
            'heure_debut' => '07:00',
            'heure_fin' => '11:00',
            'shift_template_id' => $templateAutreOrg->id,
        ]);

        $response->assertSessionHasErrors('shift_template_id');
        $this->assertDatabaseMissing('shifts', ['nom' => 'Shift interdit']);
    }

    public function test_administrateur_ne_peut_pas_voir_un_shift_dune_autre_organisation(): void
    {
        $admin = $this->makeUser('administrateur');
        $autreOrganisation = Organisation::factory()->create();

        $shiftAutreOrg = Shift::create([
            'organisation_id' => $autreOrganisation->id,
            'nom' => 'Shift Autre Org',
            'jour' => 'mardi',
            'heure_debut' => '07:00',
            'heure_fin' => '11:00',
            'statut' => 'actif',
        ]);

        $this->actingAs($admin)->get("/shifts/{$shiftAutreOrg->id}")->assertForbidden();
        $this->actingAs($admin)->delete("/shifts/{$shiftAutreOrg->id}")->assertForbidden();
    }

    public function test_administrateur_voit_son_dashboard_avec_stats_servants_et_postes(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeUser('administrateur', $organisation);

        $template = ShiftTemplate::create(['organisation_id' => $organisation->id, 'nom' => 'Temple Standard']);
        $template->positions()->create(['nom' => 'Présidence', 'ordre' => 1]);

        $shift = Shift::create([
            'organisation_id' => $organisation->id,
            'shift_template_id' => $template->id,
            'nom' => 'Shift Test',
            'jour' => 'mardi',
            'heure_debut' => '07:00',
            'heure_fin' => '11:00',
            'statut' => 'actif',
        ]);
        $shift->positions()->create(['shift_template_position_id' => $template->positions->first()->id, 'nom' => 'Présidence', 'ordre' => 1]);

        Servant::factory()->create(['organisation_id' => $organisation->id, 'statut' => 'actif']);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard/Admin')
            ->where('servants.actifs', 1)
            ->has('shifts', 1)
        );
    }
}
