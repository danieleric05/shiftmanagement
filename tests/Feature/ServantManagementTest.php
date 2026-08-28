<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\Servant;
use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

        // Aucune étape de parcours n'est plus créée automatiquement : elles
        // sont ajoutées manuellement depuis la fiche du servant.
        $this->assertDatabaseCount('servant_workflow_steps', 0);
    }

    public function test_administrateur_peut_ajouter_puis_retirer_une_etape_du_parcours(): void
    {
        $admin = $this->makeAdmin();
        $step = WorkflowStep::create(['cle' => 'entretien', 'nom' => 'Entretien', 'ordre' => 1]);
        $servant = Servant::factory()->create(['organisation_id' => $admin->organisation_id]);

        $this->actingAs($admin)->post("/servants/{$servant->id}/parcours", [
            'workflow_step_id' => $step->id,
        ])->assertRedirect();

        $etape = $servant->workflowSteps()->first();
        $this->assertNotNull($etape);
        $this->assertSame('en_attente', $etape->statut);

        $this->actingAs($admin)->delete("/servants/{$servant->id}/parcours/{$etape->id}")->assertRedirect();
        $this->assertDatabaseMissing('servant_workflow_steps', ['id' => $etape->id]);
    }

    public function test_impossible_dajouter_deux_fois_la_meme_etape(): void
    {
        $admin = $this->makeAdmin();
        $step = WorkflowStep::create(['cle' => 'entretien', 'nom' => 'Entretien', 'ordre' => 1]);
        $servant = Servant::factory()->create(['organisation_id' => $admin->organisation_id]);
        $servant->workflowSteps()->create(['workflow_step_id' => $step->id, 'statut' => 'en_attente']);

        $this->actingAs($admin)->post("/servants/{$servant->id}/parcours", [
            'workflow_step_id' => $step->id,
        ])->assertSessionHasErrors('workflow_step_id');

        $this->assertDatabaseCount('servant_workflow_steps', 1);
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
            'commentaire' => 'Entretien réalisé.',
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
        $this->assertSoftDeleted('servants', ['id' => $servant->id]);
    }

    public function test_ne_peut_pas_passer_actif_si_le_parcours_nest_pas_termine(): void
    {
        $admin = $this->makeAdmin();
        $step = WorkflowStep::create(['cle' => 'entretien', 'nom' => 'Entretien', 'ordre' => 1]);
        $servant = Servant::factory()->create([
            'organisation_id' => $admin->organisation_id,
            'statut' => 'en_formation',
        ]);
        $servant->workflowSteps()->create([
            'workflow_step_id' => $step->id,
            'statut' => 'en_cours',
        ]);

        $this->actingAs($admin)->put("/servants/{$servant->id}", [
            'nom' => $servant->nom,
            'prenom' => $servant->prenom,
            'statut' => 'actif',
        ])->assertSessionHasErrors('statut');

        $this->assertDatabaseHas('servants', [
            'id' => $servant->id,
            'statut' => 'en_formation',
        ]);
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

    public function test_administrateur_peut_uploader_une_photo_de_servant(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post('/servants', [
            'nom' => 'Kouassi',
            'prenom' => 'Jean',
            'photo' => UploadedFile::fake()->image('portrait.jpg'),
        ]);

        $response->assertRedirect();
        $servant = Servant::where('nom', 'Kouassi')->firstOrFail();
        $this->assertNotNull($servant->photo);
        Storage::disk('local')->assertExists($servant->photo);

        $this->actingAs($admin)->get("/servants/{$servant->id}/photo")->assertOk();
    }

    /**
     * PHP ne parse $_FILES que sur les requêtes POST : un vrai PUT/PATCH multipart
     * (comme le ferait `$this->put()`, qui contourne le pipeline HTTP réel) ne
     * reproduirait pas ce bug. On simule ici exactement ce qu'Inertia envoie
     * réellement pour un formulaire avec fichier : POST + `_method` spoofé.
     */
    public function test_administrateur_peut_uploader_une_photo_en_modifiant_un_servant(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();
        $servant = Servant::factory()->create(['organisation_id' => $admin->organisation_id]);

        $response = $this->actingAs($admin)->post("/servants/{$servant->id}", [
            '_method' => 'put',
            'nom' => $servant->nom,
            'prenom' => $servant->prenom,
            'statut' => 'recommande',
            'photo' => UploadedFile::fake()->image('portrait.jpg'),
        ]);

        $response->assertRedirect();
        $this->assertNotNull($servant->fresh()->photo);
        Storage::disk('local')->assertExists($servant->fresh()->photo);
    }

    public function test_photo_dun_servant_nest_pas_accessible_depuis_une_autre_organisation(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();
        $autreOrganisation = Organisation::factory()->create();
        $autreAdmin = User::factory()->create([
            'organisation_id' => $autreOrganisation->id,
            'role_id' => Role::where('slug', 'administrateur')->first()->id,
        ]);

        $response = $this->actingAs($admin)->post('/servants', [
            'nom' => 'Kouassi',
            'prenom' => 'Jean',
            'photo' => UploadedFile::fake()->image('portrait.jpg'),
        ]);
        $servant = Servant::where('nom', 'Kouassi')->firstOrFail();

        $this->actingAs($autreAdmin)->get("/servants/{$servant->id}/photo")->assertForbidden();
    }

    public function test_passer_un_servant_au_statut_retire_cloture_ses_affectations_actives(): void
    {
        $admin = $this->makeAdmin();
        $servant = Servant::factory()->create(['organisation_id' => $admin->organisation_id, 'statut' => 'actif']);
        $shift = \App\Models\Shift::create([
            'organisation_id' => $admin->organisation_id,
            'nom' => 'Shift Test',
            'jour' => 'mardi',
            'heure_debut' => '07:00',
            'heure_fin' => '11:00',
            'statut' => 'actif',
        ]);
        $position = \App\Models\ShiftPosition::create(['shift_id' => $shift->id, 'nom' => 'Poste', 'ordre' => 1]);
        $assignment = \App\Models\Assignment::create([
            'shift_position_id' => $position->id,
            'servant_id' => $servant->id,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        $this->actingAs($admin)->put("/servants/{$servant->id}", [
            'nom' => $servant->nom,
            'prenom' => $servant->prenom,
            'statut' => 'retire',
        ])->assertRedirect();

        $this->assertDatabaseHas('servants', ['id' => $servant->id, 'statut' => 'retire']);
        $this->assertDatabaseHas('assignments', ['id' => $assignment->id, 'statut' => 'termine']);
    }
}
