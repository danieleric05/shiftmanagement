<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\Servant;
use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServantManagementTest extends TestCase
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

    public function test_administrateur_peut_creer_un_servant(): void
    {
        $admin = $this->makeAdmin();
        WorkflowStep::create(['cle' => 'recommandation', 'nom' => 'Recommandation', 'ordre' => 1]);
        WorkflowStep::create(['cle' => 'entretien', 'nom' => 'Entretien', 'ordre' => 2]);

        $response = $this->actingAs($admin)->post('/servants', [
            'nom' => 'Kouassi',
            'prenom' => 'Jean',
            'telephone' => '0102030405',
        ]);

        $this->assertDatabaseHas('servants', [
            'nom' => 'Kouassi',
            'prenom' => 'Jean',
            'organisation_id' => $admin->organisation_id,
            'statut' => 'recommande',
        ]);

        $servant = Servant::first();
        $response->assertRedirect(route('servants.show', $servant));

        $this->assertDatabaseCount('servant_workflow_steps', 2);
        $this->assertDatabaseHas('servant_workflow_steps', [
            'servant_id' => $servant->id,
            'statut' => 'en_cours',
        ]);
    }

    public function test_administrateur_peut_mettre_a_jour_une_etape_du_parcours(): void
    {
        $admin = $this->makeAdmin();
        $step = WorkflowStep::create(['cle' => 'entretien', 'nom' => 'Entretien', 'ordre' => 1]);
        $servant = Servant::factory()->create(['organisation_id' => $admin->organisation_id]);
        $etape = $servant->workflowSteps()->create([
            'workflow_step_id' => $step->id,
            'statut' => 'en_attente',
        ]);

        $this->actingAs($admin)->patch("/servants/{$servant->id}/parcours/{$etape->id}", [
            'statut' => 'termine',
            'date' => '2026-07-29',
            'commentaire' => "Entretien réalisé.",
        ])->assertRedirect();

        $this->assertDatabaseHas('servant_workflow_steps', [
            'id' => $etape->id,
            'statut' => 'termine',
            'responsable_id' => $admin->id,
        ]);
    }

    public function test_membre_ne_peut_pas_creer_un_servant(): void
    {
        $organisation = Organisation::factory()->create();
        $role = Role::factory()->create(['slug' => 'membre', 'nom' => 'membre']);
        $membre = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role_id' => $role->id,
        ]);

        $response = $this->actingAs($membre)->post('/servants', [
            'nom' => 'Kouassi',
            'prenom' => 'Jean',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('servants', ['nom' => 'Kouassi']);
    }

    public function test_administrateur_peut_creer_puis_revoquer_un_compte_de_connexion(): void
    {
        $admin = $this->makeAdmin();
        Role::factory()->create(['slug' => 'membre', 'nom' => 'Membre']);
        $servant = Servant::factory()->create(['organisation_id' => $admin->organisation_id]);

        $this->actingAs($admin)->post("/servants/{$servant->id}/compte", [
            'email' => 'jean.kouassi@example.com',
            'password' => 'mot-de-passe-sur',
        ])->assertRedirect();

        $servant->refresh();
        $this->assertNotNull($servant->user_id);
        $this->assertDatabaseHas('users', [
            'id' => $servant->user_id,
            'email' => 'jean.kouassi@example.com',
        ]);

        $userId = $servant->user_id;

        $this->actingAs($admin)->delete("/servants/{$servant->id}/compte")->assertRedirect();

        $servant->refresh();
        $this->assertNull($servant->user_id);
        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    public function test_ne_peut_pas_creer_un_second_compte_pour_le_meme_servant(): void
    {
        $admin = $this->makeAdmin();
        Role::factory()->create(['slug' => 'membre', 'nom' => 'Membre']);
        $servant = Servant::factory()->create(['organisation_id' => $admin->organisation_id]);

        $this->actingAs($admin)->post("/servants/{$servant->id}/compte", [
            'email' => 'premier@example.com',
            'password' => 'mot-de-passe-sur',
        ])->assertRedirect();

        $this->actingAs($admin)->post("/servants/{$servant->id}/compte", [
            'email' => 'second@example.com',
            'password' => 'mot-de-passe-sur',
        ])->assertStatus(422);
    }

    public function test_administrateur_peut_modifier_et_supprimer_un_servant(): void
    {
        $admin = $this->makeAdmin();
        $servant = Servant::factory()->create([
            'organisation_id' => $admin->organisation_id,
            'statut' => 'recommande',
        ]);

        $this->actingAs($admin)->put("/servants/{$servant->id}", [
            'nom' => $servant->nom,
            'prenom' => $servant->prenom,
            'statut' => 'actif',
        ])->assertRedirect();

        $this->assertDatabaseHas('servants', [
            'id' => $servant->id,
            'statut' => 'actif',
        ]);

        $this->actingAs($admin)->delete("/servants/{$servant->id}")->assertRedirect();
        $this->assertDatabaseMissing('servants', ['id' => $servant->id]);
    }

    public function test_administrateur_ne_peut_pas_voir_un_servant_dune_autre_organisation(): void
    {
        $admin = $this->makeAdmin();
        $autreOrganisation = Organisation::factory()->create();
        $servantAutreOrg = Servant::factory()->create(['organisation_id' => $autreOrganisation->id]);

        $this->actingAs($admin)->get("/servants/{$servantAutreOrg->id}")->assertForbidden();
        $this->actingAs($admin)->put("/servants/{$servantAutreOrg->id}", [
            'nom' => 'Intrus',
            'prenom' => 'Test',
            'statut' => 'actif',
        ])->assertForbidden();
    }
}
