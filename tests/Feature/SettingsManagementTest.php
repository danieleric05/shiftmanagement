<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Pieu;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsManagementTest extends TestCase
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

    public function test_administrateur_peut_gerer_les_pieux(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post('/parametres/pieux', ['nom' => 'Pieu de Cocody'])
            ->assertRedirect();

        $pieu = Pieu::first();
        $this->assertDatabaseHas('pieux', ['id' => $pieu->id, 'nom' => 'Pieu de Cocody']);

        $this->actingAs($admin)->put("/parametres/pieux/{$pieu->id}", ['nom' => 'Pieu de Yopougon'])
            ->assertRedirect();
        $this->assertDatabaseHas('pieux', ['id' => $pieu->id, 'nom' => 'Pieu de Yopougon']);

        $this->actingAs($admin)->delete("/parametres/pieux/{$pieu->id}")->assertRedirect();
        $this->assertDatabaseMissing('pieux', ['id' => $pieu->id]);
    }

    public function test_administrateur_peut_gerer_les_horaires(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post('/parametres/horaires', [
            'nom' => 'Matin',
            'heure_debut' => '07:00',
            'heure_fin' => '11:00',
        ])->assertRedirect();

        $this->assertDatabaseHas('horaires', [
            'organisation_id' => $admin->organisation_id,
            'nom' => 'Matin',
        ]);

        $horaire = \App\Models\Horaire::first();

        $this->actingAs($admin)->put("/parametres/horaires/{$horaire->id}", [
            'nom' => 'Matin (révisé)',
            'heure_debut' => '06:30',
            'heure_fin' => '10:30',
        ])->assertRedirect();

        $this->assertDatabaseHas('horaires', [
            'id' => $horaire->id,
            'nom' => 'Matin (révisé)',
        ]);

        $this->actingAs($admin)->delete("/parametres/horaires/{$horaire->id}")->assertRedirect();
        $this->assertDatabaseMissing('horaires', ['id' => $horaire->id]);
    }

    public function test_administrateur_peut_gerer_les_etapes_du_parcours(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post('/parametres/parcours', [
            'cle' => 'entretien_final',
            'nom' => 'Entretien final',
        ])->assertRedirect();

        $etape = WorkflowStep::where('cle', 'entretien_final')->first();
        $this->assertNotNull($etape);

        $this->actingAs($admin)->put("/parametres/parcours/{$etape->id}", [
            'nom' => 'Entretien final (révisé)',
            'ordre' => 5,
        ])->assertRedirect();

        $this->assertDatabaseHas('workflow_steps', [
            'id' => $etape->id,
            'nom' => 'Entretien final (révisé)',
            'ordre' => 5,
        ]);

        $this->actingAs($admin)->delete("/parametres/parcours/{$etape->id}")->assertRedirect();
        $this->assertDatabaseMissing('workflow_steps', ['id' => $etape->id]);
    }

    public function test_administrateur_peut_modifier_un_role(): void
    {
        $admin = $this->makeAdmin();
        $role = Role::where('slug', 'administrateur')->first();

        $this->actingAs($admin)->get('/parametres/roles')->assertOk();

        $this->actingAs($admin)->put("/parametres/roles/{$role->id}", [
            'nom' => 'Administrateur (révisé)',
            'description' => 'Nouvelle description.',
        ])->assertRedirect();

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'nom' => 'Administrateur (révisé)',
        ]);
    }

    public function test_membre_ne_peut_pas_acceder_aux_parametres(): void
    {
        $organisation = Organisation::factory()->create();
        $membreRole = Role::factory()->create(['slug' => 'membre', 'nom' => 'Membre']);
        $membre = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role_id' => $membreRole->id,
        ]);

        $this->actingAs($membre)->get('/parametres')->assertForbidden();
        $this->actingAs($membre)->get('/parametres/roles')->assertForbidden();
    }

    public function test_administrateur_peut_telecharger_le_mode_emploi(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get('/manuel');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
