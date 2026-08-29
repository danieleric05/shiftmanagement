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
            ->component('Dashboard/Servant')
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
            ->component('Dashboard/Servant')
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

    public function test_coordinateur_peut_voir_son_propre_shift_via_mon_shift(): void
    {
        $organisation = Organisation::factory()->create();
        $coordinateurRole = Role::factory()->create(['slug' => 'coordonnateur_equipe', 'nom' => "Coordonnateur d'équipe"]);
        $coordinateur = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role_id' => $coordinateurRole->id,
        ]);

        $sonShift = Shift::create([
            'organisation_id' => $organisation->id,
            'nom' => 'Shift Géré',
            'jour' => 'mardi',
            'heure_debut' => '07:00',
            'heure_fin' => '11:00',
            'statut' => 'actif',
        ]);

        $sonShift->shiftMembers()->create([
            'user_id' => $coordinateur->id,
            'role_id' => $coordinateurRole->id,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        $this->actingAs($coordinateur)
            ->get("/mon-shift/{$sonShift->id}")
            ->assertOk();
    }

    public function test_coordinateur_peut_voir_en_lecture_seule_un_shift_quil_ne_gere_pas(): void
    {
        $organisation = Organisation::factory()->create();
        $coordinateurRole = Role::factory()->create(['slug' => 'coordonnateur_equipe', 'nom' => "Coordonnateur d'équipe"]);
        $coordinateur = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role_id' => $coordinateurRole->id,
        ]);

        $autreShift = Shift::create([
            'organisation_id' => $organisation->id,
            'nom' => 'Shift Non Géré',
            'jour' => 'mercredi',
            'heure_debut' => '07:00',
            'heure_fin' => '11:00',
            'statut' => 'actif',
        ]);

        $this->actingAs($coordinateur)
            ->get("/mon-shift/{$autreShift->id}")
            ->assertOk();
    }

    public function test_coordinateur_ne_peut_pas_modifier_le_recrutement_dun_shift_quil_ne_gere_pas(): void
    {
        $organisation = Organisation::factory()->create();
        $coordinateurRole = Role::factory()->create(['slug' => 'coordonnateur_equipe', 'nom' => "Coordonnateur d'équipe"]);
        $coordinateur = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role_id' => $coordinateurRole->id,
        ]);

        $autreShift = Shift::create([
            'organisation_id' => $organisation->id,
            'nom' => 'Shift Non Géré',
            'jour' => 'mercredi',
            'heure_debut' => '07:00',
            'heure_fin' => '11:00',
            'statut' => 'actif',
        ]);

        $this->actingAs($coordinateur)
            ->put("/recrutement/{$autreShift->id}", ['nombre_a_recruter' => 2])
            ->assertForbidden();
    }

    public function test_administrateur_voit_son_dashboard_avec_la_liste_des_shifts_et_postes_vacants(): void
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
            ->has('shifts', 1)
            ->where('shifts.0.postes_total', 1)
            ->where('shifts.0.postes_vacants', 1)
        );
    }

    private function makeTemplateAvecPostesGenres(Organisation $organisation): ShiftTemplate
    {
        $template = ShiftTemplate::create(['organisation_id' => $organisation->id, 'nom' => 'Temple Standard']);
        $template->positions()->create(['nom' => "Coordonnateur d'équipe", 'ordre' => 1]);
        $template->positions()->create(['nom' => "Coordonnatrice d'équipe", 'ordre' => 2]);
        $template->positions()->create(['nom' => 'Scelleur', 'ordre' => 3]);
        $template->positions()->create(['nom' => 'Servant', 'ordre' => 4]);
        $template->positions()->create(['nom' => 'Servante', 'ordre' => 5]);

        return $template;
    }

    public function test_les_postes_vacants_saffichent_toujours_apres_les_postes_occupes(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeUser('administrateur', $organisation);
        $template = $this->makeTemplateAvecPostesGenres($organisation);

        $shift = Shift::create([
            'organisation_id' => $organisation->id, 'shift_template_id' => $template->id,
            'nom' => 'Mardi Matin Frères', 'jour' => 'mardi', 'heure_debut' => '07:00', 'heure_fin' => '11:00', 'statut' => 'actif',
        ]);

        // "Coordonnateur d'équipe" (ordre 0) reste vacant ; deux Servants
        // (ordre 4) sont occupés : ils doivent malgré tout passer avant lui.
        $shift->positions()->create(['nom' => "Coordonnateur d'équipe", 'ordre' => 0]);
        $posteOccupe1 = $shift->positions()->create(['nom' => 'Servant', 'ordre' => 4]);
        $posteOccupe2 = $shift->positions()->create(['nom' => 'Servant', 'ordre' => 4]);

        foreach ([$posteOccupe1, $posteOccupe2] as $poste) {
            $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);
            $poste->assignments()->create(['servant_id' => $servant->id, 'date_debut' => now()->toDateString(), 'statut' => 'actif']);
        }

        $response = $this->actingAs($admin)->get("/shifts/{$shift->id}");

        $response->assertInertia(fn ($page) => $page
            ->component('Shifts/Show')
            ->has('positions', 3)
            ->where('positions.0.titulaire', fn ($t) => $t !== null)
            ->where('positions.1.titulaire', fn ($t) => $t !== null)
            ->where('positions.2.nom', "Coordonnateur d'équipe")
            ->where('positions.2.titulaire', null)
        );
    }

    public function test_ajouter_un_poste_ne_propose_que_les_postes_du_bon_genre(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeUser('administrateur', $organisation);
        $template = $this->makeTemplateAvecPostesGenres($organisation);

        $shiftFreres = Shift::create([
            'organisation_id' => $organisation->id, 'shift_template_id' => $template->id,
            'nom' => 'Mardi Matin Frères', 'jour' => 'mardi', 'heure_debut' => '07:00', 'heure_fin' => '11:00', 'statut' => 'actif',
        ]);

        $response = $this->actingAs($admin)->get("/shifts/{$shiftFreres->id}");

        $response->assertInertia(fn ($page) => $page
            ->component('Shifts/Show')
            ->where('postesDisponibles', fn ($postes) => collect($postes)->pluck('nom')->all() === [
                "Coordonnateur d'équipe", 'Scelleur', 'Servant',
            ])
        );
    }

    public function test_administrateur_peut_ajouter_puis_supprimer_un_poste_vacant(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeUser('administrateur', $organisation);
        $template = $this->makeTemplateAvecPostesGenres($organisation);
        $posteCoordo = $template->positions()->where('nom', "Coordonnateur d'équipe")->first();
        $posteCoordoSoeur = $template->positions()->where('nom', "Coordonnatrice d'équipe")->first();

        $shift = Shift::create([
            'organisation_id' => $organisation->id, 'shift_template_id' => $template->id,
            'nom' => 'Mardi Matin Frères', 'jour' => 'mardi', 'heure_debut' => '07:00', 'heure_fin' => '11:00', 'statut' => 'actif',
        ]);

        // Refusé : poste du mauvais genre pour ce Shift.
        $this->actingAs($admin)->post("/shifts/{$shift->id}/postes", [
            'shift_template_position_id' => $posteCoordoSoeur->id,
        ])->assertStatus(422);

        $this->actingAs($admin)->post("/shifts/{$shift->id}/postes", [
            'shift_template_position_id' => $posteCoordo->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('shift_positions', ['shift_id' => $shift->id, 'nom' => "Coordonnateur d'équipe"]);

        $position = $shift->positions()->where('nom', "Coordonnateur d'équipe")->firstOrFail();

        $this->actingAs($admin)->delete("/shifts/{$shift->id}/postes/{$position->id}")->assertRedirect();
        $this->assertDatabaseMissing('shift_positions', ['id' => $position->id]);
    }

    public function test_impossible_de_supprimer_un_poste_occupe(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeUser('administrateur', $organisation);
        $template = $this->makeTemplateAvecPostesGenres($organisation);

        $shift = Shift::create([
            'organisation_id' => $organisation->id, 'shift_template_id' => $template->id,
            'nom' => 'Mardi Matin Frères', 'jour' => 'mardi', 'heure_debut' => '07:00', 'heure_fin' => '11:00', 'statut' => 'actif',
        ]);
        $position = $shift->positions()->create(['nom' => 'Servant', 'ordre' => 1]);
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);
        $position->assignments()->create(['servant_id' => $servant->id, 'date_debut' => now()->toDateString(), 'statut' => 'actif']);

        $this->actingAs($admin)->delete("/shifts/{$shift->id}/postes/{$position->id}")->assertStatus(422);
        $this->assertDatabaseHas('shift_positions', ['id' => $position->id]);
    }
}
