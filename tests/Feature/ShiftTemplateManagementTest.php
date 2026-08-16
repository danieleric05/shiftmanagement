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

class ShiftTemplateManagementTest extends TestCase
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

    public function test_administrateur_peut_creer_un_modele_et_lui_ajouter_des_postes(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post('/shift-templates', [
            'nom' => 'Temple Standard',
        ]);

        $template = ShiftTemplate::first();
        $response->assertRedirect(route('shift-templates.show', $template));

        $this->actingAs($admin)->post("/shift-templates/{$template->id}/postes", [
            'nom' => 'Présidence',
        ])->assertRedirect();

        $this->assertDatabaseHas('shift_template_positions', [
            'shift_template_id' => $template->id,
            'nom' => 'Présidence',
            'ordre' => 1,
        ]);
    }

    public function test_creer_un_shift_depuis_un_modele_genere_les_postes_automatiquement(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeAdmin($organisation);

        $template = ShiftTemplate::create(['organisation_id' => $organisation->id, 'nom' => 'Temple Standard']);
        $template->positions()->create(['nom' => 'Présidence', 'ordre' => 1]);
        $template->positions()->create(['nom' => 'Greffier', 'ordre' => 2]);

        $response = $this->actingAs($admin)->post('/shifts', [
            'nom' => 'Shift A - Mardi matin',
            'jour' => 'mardi',
            'heure_debut' => '07:00',
            'heure_fin' => '11:00',
            'shift_template_id' => $template->id,
        ]);

        $shift = Shift::first();
        $response->assertRedirect(route('shifts.show', $shift));

        $this->assertDatabaseCount('shift_positions', 2);
        $this->assertDatabaseHas('shift_positions', ['shift_id' => $shift->id, 'nom' => 'Présidence']);
        $this->assertDatabaseHas('shift_positions', ['shift_id' => $shift->id, 'nom' => 'Greffier']);
    }

    public function test_administrateur_peut_affecter_et_retirer_un_servant_dun_poste(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeAdmin($organisation);

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
        foreach ($template->positions as $templatePosition) {
            $shift->positions()->create([
                'shift_template_position_id' => $templatePosition->id,
                'nom' => $templatePosition->nom,
                'ordre' => $templatePosition->ordre,
            ]);
        }

        $servant = Servant::factory()->create(['organisation_id' => $organisation->id, 'statut' => 'actif']);
        $position = $shift->positions()->first();

        $this->actingAs($admin)->post("/shifts/{$shift->id}/postes/{$position->id}/affectation", [
            'servant_id' => $servant->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('assignments', [
            'shift_position_id' => $position->id,
            'servant_id' => $servant->id,
            'statut' => 'actif',
        ]);

        $assignment = $position->assignments()->first();

        $this->actingAs($admin)
            ->delete("/shifts/{$shift->id}/postes/{$position->id}/affectation/{$assignment->id}")
            ->assertRedirect();

        $this->assertDatabaseHas('assignments', [
            'id' => $assignment->id,
            'statut' => 'termine',
        ]);
    }

    public function test_administrateur_peut_modifier_supprimer_un_modele_et_retirer_un_poste(): void
    {
        $admin = $this->makeAdmin();

        $template = ShiftTemplate::create(['organisation_id' => $admin->organisation_id, 'nom' => 'Temple Standard']);
        $position = $template->positions()->create(['nom' => 'Présidence', 'ordre' => 1]);

        $this->actingAs($admin)->put("/shift-templates/{$template->id}", [
            'nom' => 'Temple Standard (révisé)',
            'description' => 'Nouvelle description.',
        ])->assertRedirect();

        $this->assertDatabaseHas('shift_templates', [
            'id' => $template->id,
            'nom' => 'Temple Standard (révisé)',
        ]);

        $this->actingAs($admin)->delete("/shift-templates/{$template->id}/postes/{$position->id}")->assertRedirect();
        $this->assertDatabaseMissing('shift_template_positions', ['id' => $position->id]);

        $this->actingAs($admin)->delete("/shift-templates/{$template->id}")->assertRedirect();
        $this->assertSoftDeleted('shift_templates', ['id' => $template->id]);
    }

    public function test_administrateur_ne_peut_pas_voir_un_modele_dune_autre_organisation(): void
    {
        $admin = $this->makeAdmin();
        $autreOrganisation = Organisation::factory()->create();
        $templateAutreOrg = ShiftTemplate::create(['organisation_id' => $autreOrganisation->id, 'nom' => 'Autre Modèle']);

        $this->actingAs($admin)->get("/shift-templates/{$templateAutreOrg->id}")->assertForbidden();
        $this->actingAs($admin)->delete("/shift-templates/{$templateAutreOrg->id}")->assertForbidden();
    }
}
