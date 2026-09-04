<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Organisation;
use App\Models\Role;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftMember;
use App\Models\ShiftPosition;
use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServantVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $roleSlug, Organisation $organisation): User
    {
        $role = Role::firstOrCreate(['slug' => $roleSlug], ['nom' => $roleSlug, 'gere_shifts' => $roleSlug === 'coordonnateur_equipe']);

        return User::factory()->create([
            'organisation_id' => $organisation->id,
            'role_id' => $role->id,
        ]);
    }

    private function makeShift(Organisation $organisation, string $nom): Shift
    {
        return Shift::create([
            'organisation_id' => $organisation->id,
            'nom' => $nom,
            'jour' => 'mardi',
            'heure_debut' => '07:00',
            'heure_fin' => '11:00',
            'statut' => 'actif',
        ]);
    }

    private function rendreChefEquipe(User $user, Shift $shift): void
    {
        $role = Role::firstOrCreate(['slug' => 'coordonnateur_equipe'], ['nom' => 'coordonnateur_equipe', 'gere_shifts' => true]);

        ShiftMember::create([
            'shift_id' => $shift->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);
    }

    private function affecterServant(Servant $servant, Shift $shift): void
    {
        $position = ShiftPosition::create(['shift_id' => $shift->id, 'nom' => 'Poste', 'ordre' => 1]);

        Assignment::create([
            'shift_position_id' => $position->id,
            'servant_id' => $servant->id,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);
    }

    public function test_le_chef_du_shift_voit_le_parcours_du_servant_qui_y_est_affecte(): void
    {
        $organisation = Organisation::factory()->create();
        $chef = $this->makeUser('coordonnateur_equipe', $organisation);
        $shift = $this->makeShift($organisation, 'Shift Géré');
        $this->rendreChefEquipe($chef, $shift);

        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);
        $this->affecterServant($servant, $shift);

        $this->actingAs($chef)->get("/mes-servants/{$servant->id}")->assertOk();
    }

    public function test_un_chef_qui_ne_gere_pas_le_shift_du_servant_na_pas_acces(): void
    {
        $organisation = Organisation::factory()->create();
        $chef = $this->makeUser('coordonnateur_equipe', $organisation);
        $sonShift = $this->makeShift($organisation, 'Shift Géré');
        $this->rendreChefEquipe($chef, $sonShift);

        $autreShift = $this->makeShift($organisation, 'Autre Shift');
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);
        $this->affecterServant($servant, $autreShift);

        $this->actingAs($chef)->get("/mes-servants/{$servant->id}")->assertForbidden();
    }

    public function test_ladministrateur_voit_le_parcours_de_nimporte_quel_servant(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeUser('administrateur', $organisation);
        $shift = $this->makeShift($organisation, 'Shift');
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);
        $this->affecterServant($servant, $shift);

        $this->actingAs($admin)->get("/mes-servants/{$servant->id}")->assertOk();
    }

    public function test_le_chef_du_shift_peut_modifier_le_servant_qui_y_est_affecte(): void
    {
        $organisation = Organisation::factory()->create();
        $chef = $this->makeUser('coordonnateur_equipe', $organisation);
        $shift = $this->makeShift($organisation, 'Shift Géré');
        $this->rendreChefEquipe($chef, $shift);

        $servant = Servant::factory()->create([
            'organisation_id' => $organisation->id,
            'statut' => 'recommande',
        ]);
        $this->affecterServant($servant, $shift);

        $this->actingAs($chef)->get("/servants/{$servant->id}/edit")->assertOk();

        $this->actingAs($chef)->put("/servants/{$servant->id}", [
            'nom' => $servant->nom,
            'prenom' => $servant->prenom,
            'statut' => 'recommande',
            'titre_leadership' => 'Coordonnateur du baptistère',
        ])->assertRedirect(route('servants.mine.show', $servant));

        $this->assertDatabaseHas('servants', [
            'id' => $servant->id,
            'titre_leadership' => 'Coordonnateur du baptistère',
        ]);
    }

    public function test_un_chef_qui_ne_gere_pas_le_shift_du_servant_ne_peut_pas_le_modifier(): void
    {
        $organisation = Organisation::factory()->create();
        $chef = $this->makeUser('coordonnateur_equipe', $organisation);
        $sonShift = $this->makeShift($organisation, 'Shift Géré');
        $this->rendreChefEquipe($chef, $sonShift);

        $autreShift = $this->makeShift($organisation, 'Autre Shift');
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);
        $this->affecterServant($servant, $autreShift);

        $this->actingAs($chef)->get("/servants/{$servant->id}/edit")->assertForbidden();
        $this->actingAs($chef)->put("/servants/{$servant->id}", [
            'nom' => $servant->nom,
            'prenom' => $servant->prenom,
            'statut' => $servant->statut,
        ])->assertForbidden();
    }

    public function test_le_chef_peut_mettre_a_jour_une_etape_du_parcours_mais_pas_gerer_le_compte(): void
    {
        $organisation = Organisation::factory()->create();
        $chef = $this->makeUser('coordonnateur_equipe', $organisation);
        $shift = $this->makeShift($organisation, 'Shift Géré');
        $this->rendreChefEquipe($chef, $shift);

        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);
        $this->affecterServant($servant, $shift);
        $step = WorkflowStep::create(['cle' => 'entretien', 'nom' => 'Entretien', 'ordre' => 1]);
        $etape = $servant->workflowSteps()->create([
            'workflow_step_id' => $step->id,
            'statut' => 'en_attente',
        ]);

        $this->actingAs($chef)->patch("/servants/{$servant->id}/parcours/{$etape->id}", [
            'statut' => 'termine',
        ])->assertRedirect();

        $this->assertDatabaseHas('servant_workflow_steps', [
            'id' => $etape->id,
            'statut' => 'termine',
            'responsable_id' => $chef->id,
        ]);

        $this->actingAs($chef)->post("/servants/{$servant->id}/compte", [
            'email' => 'nouveau@example.com',
            'password' => 'mot-de-passe-sur',
        ])->assertForbidden();
    }

    public function test_le_chef_du_shift_peut_demarrer_le_parcours_du_servant_qui_y_est_affecte(): void
    {
        $this->seed(\Database\Seeders\WorkflowStepSeeder::class);

        $organisation = Organisation::factory()->create();
        $chef = $this->makeUser('coordonnateur_equipe', $organisation);
        $shift = $this->makeShift($organisation, 'Shift Géré');
        $this->rendreChefEquipe($chef, $shift);

        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);
        $this->affecterServant($servant, $shift);

        $this->actingAs($chef)->post("/servants/{$servant->id}/parcours/demarrer")->assertRedirect();

        $this->assertSame(12, $servant->workflowSteps()->count());
    }

    public function test_seul_un_administrateur_peut_cocher_letape_formation(): void
    {
        $organisation = Organisation::factory()->create();
        $chef = $this->makeUser('coordonnateur_equipe', $organisation);
        $admin = $this->makeUser('administrateur', $organisation);
        $shift = $this->makeShift($organisation, 'Shift Géré');
        $this->rendreChefEquipe($chef, $shift);

        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);
        $this->affecterServant($servant, $shift);
        $step = WorkflowStep::create(['cle' => 'formation', 'nom' => 'Formation', 'ordre' => 1]);
        $etape = $servant->workflowSteps()->create([
            'workflow_step_id' => $step->id,
            'statut' => 'en_attente',
        ]);

        $this->actingAs($chef)->patch("/servants/{$servant->id}/parcours/{$etape->id}", [
            'statut' => 'termine',
        ])->assertForbidden();

        $this->actingAs($admin)->patch("/servants/{$servant->id}/parcours/{$etape->id}", [
            'statut' => 'termine',
        ])->assertRedirect();

        $this->assertDatabaseHas('servant_workflow_steps', [
            'id' => $etape->id,
            'statut' => 'termine',
            'responsable_id' => $admin->id,
        ]);
    }
}
