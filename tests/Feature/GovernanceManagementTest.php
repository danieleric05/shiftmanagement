<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\GovernanceRequest;
use App\Models\Organisation;
use App\Models\Role;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GovernanceManagementTest extends TestCase
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

    public function test_administrateur_peut_creer_une_demande_de_retrait(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeAdmin($organisation);
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);

        $response = $this->actingAs($admin)->post('/gouvernance', [
            'servant_id' => $servant->id,
            'type' => 'retrait',
            'motif' => 'Déménagement du servant.',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('governance_requests', [
            'servant_id' => $servant->id,
            'type' => 'retrait',
            'statut' => 'en_attente',
            'demandeur_id' => $admin->id,
        ]);
    }

    public function test_valider_une_demande_de_retrait_retire_le_servant(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeAdmin($organisation);
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id, 'statut' => 'actif']);

        $demande = GovernanceRequest::create([
            'organisation_id' => $organisation->id,
            'servant_id' => $servant->id,
            'type' => 'retrait',
            'motif' => 'Déménagement.',
            'demandeur_id' => $admin->id,
            'statut' => 'en_attente',
        ]);

        $this->actingAs($admin)->patch("/gouvernance/{$demande->id}/valider", [
            'decision_commentaire' => 'Confirmé.',
        ])->assertRedirect();

        $this->assertDatabaseHas('governance_requests', [
            'id' => $demande->id,
            'statut' => 'validee',
            'decideur_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('servants', [
            'id' => $servant->id,
            'statut' => 'retire',
        ]);
    }

    public function test_valider_une_demande_de_retrait_termine_les_affectations_actives_du_servant(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeAdmin($organisation);
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id, 'statut' => 'actif']);

        $shift = Shift::create([
            'organisation_id' => $organisation->id,
            'nom' => 'Shift Test',
            'jour' => 'mardi',
            'heure_debut' => '07:00',
            'heure_fin' => '11:00',
            'statut' => 'actif',
        ]);
        $position = ShiftPosition::create(['shift_id' => $shift->id, 'nom' => 'Poste', 'ordre' => 1]);
        $assignment = Assignment::create([
            'shift_position_id' => $position->id,
            'servant_id' => $servant->id,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        $demande = GovernanceRequest::create([
            'organisation_id' => $organisation->id,
            'servant_id' => $servant->id,
            'type' => 'retrait',
            'motif' => 'Déménagement.',
            'demandeur_id' => $admin->id,
            'statut' => 'en_attente',
        ]);

        $this->actingAs($admin)->patch("/gouvernance/{$demande->id}/valider", [])->assertRedirect();

        $this->assertDatabaseHas('assignments', [
            'id' => $assignment->id,
            'statut' => 'termine',
        ]);
    }

    public function test_rejeter_une_demande_ne_modifie_pas_le_statut_du_servant(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeAdmin($organisation);
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id, 'statut' => 'actif']);

        $demande = GovernanceRequest::create([
            'organisation_id' => $organisation->id,
            'servant_id' => $servant->id,
            'type' => 'avis',
            'motif' => 'Observation.',
            'demandeur_id' => $admin->id,
            'statut' => 'en_attente',
        ]);

        $this->actingAs($admin)->patch("/gouvernance/{$demande->id}/rejeter", [])->assertRedirect();

        $this->assertDatabaseHas('governance_requests', [
            'id' => $demande->id,
            'statut' => 'rejetee',
        ]);

        $this->assertDatabaseHas('servants', [
            'id' => $servant->id,
            'statut' => 'actif',
        ]);
    }

    public function test_administrateur_voit_la_page_de_gouvernance_avec_les_compteurs(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeAdmin($organisation);
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);

        GovernanceRequest::create([
            'organisation_id' => $organisation->id,
            'servant_id' => $servant->id,
            'type' => 'avis',
            'motif' => 'Observation.',
            'demandeur_id' => $admin->id,
            'statut' => 'en_attente',
        ]);

        $response = $this->actingAs($admin)->get('/gouvernance');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Governance/Index')
            ->where('compteurs.avis', 1)
            ->has('demandes', 1)
        );
    }

    public function test_administrateur_ne_peut_pas_valider_une_demande_dune_autre_organisation(): void
    {
        $admin = $this->makeAdmin();
        $autreOrganisation = Organisation::factory()->create();
        $servantAutreOrg = Servant::factory()->create(['organisation_id' => $autreOrganisation->id]);

        $demandeAutreOrg = GovernanceRequest::create([
            'organisation_id' => $autreOrganisation->id,
            'servant_id' => $servantAutreOrg->id,
            'type' => 'avis',
            'motif' => 'Observation.',
            'demandeur_id' => $admin->id,
            'statut' => 'en_attente',
        ]);

        $this->actingAs($admin)->patch("/gouvernance/{$demandeAutreOrg->id}/valider", [])->assertForbidden();
    }
}
