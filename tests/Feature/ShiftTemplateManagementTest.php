<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftTemplate;
use App\Models\ShiftTemplatePosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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

        $servant = Servant::factory()->create(['organisation_id' => $organisation->id, 'statut' => 'actif', 'genre' => 'homme']);
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

        // Un shift n'a pas de nombre de postes fixe : le poste ne reste pas
        // affiché comme vacant, il disparaît (suppression douce).
        $this->assertSoftDeleted('shift_positions', ['id' => $position->id]);
    }

    public function test_administrateur_peut_corriger_le_nom_dun_poste(): void
    {
        $admin = $this->makeAdmin();
        $template = ShiftTemplate::create(['organisation_id' => $admin->organisation_id, 'nom' => 'Temple Standard']);
        $position = $template->positions()->create(['nom' => 'Coordinateur d\'équipe', 'ordre' => 0]);

        $this->actingAs($admin)->put("/shift-templates/{$template->id}/postes/{$position->id}", [
            'nom' => 'Coordonnateur',
        ])->assertRedirect();

        $this->assertDatabaseHas('shift_template_positions', [
            'id' => $position->id,
            'nom' => 'Coordonnateur',
        ]);
    }

    public function test_corriger_le_nom_dun_poste_se_propage_aux_shifts_qui_lutilisent_deja(): void
    {
        $admin = $this->makeAdmin();
        $template = ShiftTemplate::create(['organisation_id' => $admin->organisation_id, 'nom' => 'Temple Standard']);
        $positionModele = $template->positions()->create(['nom' => 'Coordinateur d\'équipe', 'ordre' => 0]);

        $shift = Shift::create([
            'organisation_id' => $admin->organisation_id, 'shift_template_id' => $template->id,
            'nom' => 'Shift Test', 'jour' => 'mardi', 'heure_debut' => '07:00', 'heure_fin' => '11:00', 'statut' => 'actif',
        ]);
        $posteShift = $shift->positions()->create([
            'shift_template_position_id' => $positionModele->id,
            'nom' => 'Coordinateur d\'équipe',
            'ordre' => 0,
        ]);

        $this->actingAs($admin)->put("/shift-templates/{$template->id}/postes/{$positionModele->id}", [
            'nom' => 'Coordonnateur',
        ])->assertRedirect();

        $this->assertDatabaseHas('shift_positions', [
            'id' => $posteShift->id,
            'nom' => 'Coordonnateur',
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

    public function test_administrateur_peut_deplacer_un_poste_dans_la_liste(): void
    {
        $admin = $this->makeAdmin();
        $template = ShiftTemplate::create(['organisation_id' => $admin->organisation_id, 'nom' => 'Temple Standard']);
        $premier = $template->positions()->create(['nom' => 'Coordonnateur', 'ordre' => 0]);
        $second = $template->positions()->create(['nom' => 'Scelleur', 'ordre' => 1]);
        $troisieme = $template->positions()->create(['nom' => 'Servant', 'ordre' => 2]);

        // Faire descendre le premier poste doit le placer après le deuxième.
        $this->actingAs($admin)->patch("/shift-templates/{$template->id}/postes/{$premier->id}/deplacer", [
            'direction' => 'bas',
        ])->assertRedirect();

        $ordreFinal = $template->positions()->orderBy('ordre')->pluck('nom')->all();
        $this->assertSame(['Scelleur', 'Coordonnateur', 'Servant'], $ordreFinal);

        // Redescendre encore (déjà en 2e position, doit passer en dernier).
        $this->actingAs($admin)->patch("/shift-templates/{$template->id}/postes/{$premier->id}/deplacer", [
            'direction' => 'bas',
        ])->assertRedirect();

        $ordreFinal = $template->positions()->orderBy('ordre')->pluck('nom')->all();
        $this->assertSame(['Scelleur', 'Servant', 'Coordonnateur'], $ordreFinal);

        // Déjà en dernière position : un nouveau "bas" ne change rien.
        $this->actingAs($admin)->patch("/shift-templates/{$template->id}/postes/{$premier->id}/deplacer", [
            'direction' => 'bas',
        ])->assertRedirect();

        $ordreFinal = $template->positions()->orderBy('ordre')->pluck('nom')->all();
        $this->assertSame(['Scelleur', 'Servant', 'Coordonnateur'], $ordreFinal);
    }

    public function test_administrateur_peut_reordonner_les_postes_par_glisser_deposer(): void
    {
        $admin = $this->makeAdmin();
        $template = ShiftTemplate::create(['organisation_id' => $admin->organisation_id, 'nom' => 'Temple Standard']);
        $a = $template->positions()->create(['nom' => 'A', 'ordre' => 0]);
        $b = $template->positions()->create(['nom' => 'B', 'ordre' => 1]);
        $c = $template->positions()->create(['nom' => 'C', 'ordre' => 2]);

        $this->actingAs($admin)->patch("/shift-templates/{$template->id}/postes/reordonner", [
            'positions' => [$c->id, $a->id, $b->id],
        ])->assertRedirect();

        $this->assertSame(['C', 'A', 'B'], $template->positions()->orderBy('ordre')->pluck('nom')->all());
    }

    public function test_reordonner_refuse_une_liste_de_postes_incomplete_ou_etrangere(): void
    {
        $admin = $this->makeAdmin();
        $template = ShiftTemplate::create(['organisation_id' => $admin->organisation_id, 'nom' => 'Temple Standard']);
        $a = $template->positions()->create(['nom' => 'A', 'ordre' => 0]);
        $b = $template->positions()->create(['nom' => 'B', 'ordre' => 1]);

        // Liste incomplète (il manque $b).
        $this->actingAs($admin)->patch("/shift-templates/{$template->id}/postes/reordonner", [
            'positions' => [$a->id],
        ])->assertStatus(422);

        // Poste appartenant à un autre modèle : rejeté par la validation
        // (Rule::exists scopé au modèle), pas par le abort_unless applicatif.
        $autreTemplate = ShiftTemplate::create(['organisation_id' => $admin->organisation_id, 'nom' => 'Autre Modèle']);
        $etranger = $autreTemplate->positions()->create(['nom' => 'Étranger', 'ordre' => 0]);

        $this->actingAs($admin)->patch("/shift-templates/{$template->id}/postes/reordonner", [
            'positions' => [$a->id, $etranger->id],
        ])->assertSessionHasErrors('positions.1');

        $this->assertSame(['A', 'B'], $template->positions()->orderBy('ordre')->pluck('nom')->all());
    }

    public function test_administrateur_ne_peut_pas_voir_un_modele_dune_autre_organisation(): void
    {
        $admin = $this->makeAdmin();
        $autreOrganisation = Organisation::factory()->create();
        $templateAutreOrg = ShiftTemplate::create(['organisation_id' => $autreOrganisation->id, 'nom' => 'Autre Modèle']);

        $this->actingAs($admin)->get("/shift-templates/{$templateAutreOrg->id}")->assertForbidden();
        $this->actingAs($admin)->delete("/shift-templates/{$templateAutreOrg->id}")->assertForbidden();
    }

    /**
     * Noms du modèle dans l'ordre persisté (rang puis homme avant femme,
     * tel que l'affiche la page du modèle).
     */
    private function nomsAffiches(ShiftTemplate $template): array
    {
        return ShiftTemplatePosition::enBlocs($template->positions()->get())
            ->flatten()
            ->pluck('nom')
            ->all();
    }

    public function test_la_page_du_modele_affiche_le_poste_femme_sous_son_poste_homme_avec_le_meme_numero(): void
    {
        $admin = $this->makeAdmin();
        $template = ShiftTemplate::create(['organisation_id' => $admin->organisation_id, 'nom' => 'Temple Standard']);
        // Créés femme d'abord, au même rang : l'affichage doit quand même
        // placer l'homme au-dessus.
        $template->positions()->create(['nom' => 'Servante', 'ordre' => 2]);
        $template->positions()->create(['nom' => 'Coordonnatrice', 'ordre' => 0]);
        $template->positions()->create(['nom' => 'Scelleur', 'ordre' => 1]);
        $template->positions()->create(['nom' => 'Servant', 'ordre' => 2]);
        $template->positions()->create(['nom' => 'Coordonnateur', 'ordre' => 0]);

        $this->actingAs($admin)->get("/shift-templates/{$template->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ShiftTemplates/Show')
                ->where('positions.0.nom', 'Coordonnateur')
                ->where('positions.0.numero', 1)
                ->where('positions.1.nom', 'Coordonnatrice')
                ->where('positions.1.numero', 1)
                ->where('positions.2.nom', 'Scelleur')
                ->where('positions.2.numero', 2)
                ->where('positions.3.nom', 'Servant')
                ->where('positions.3.numero', 3)
                ->where('positions.4.nom', 'Servante')
                ->where('positions.4.numero', 3)
                ->where('positions.4.bloc', 2)
            );
    }

    public function test_deplacer_un_poste_homme_emmene_sa_femme_et_le_scelleur_se_deplace_seul(): void
    {
        $admin = $this->makeAdmin();
        $template = ShiftTemplate::create(['organisation_id' => $admin->organisation_id, 'nom' => 'Temple Standard']);
        $coordonnateur = $template->positions()->create(['nom' => 'Coordonnateur', 'ordre' => 0]);
        $template->positions()->create(['nom' => 'Coordonnatrice', 'ordre' => 0]);
        $scelleur = $template->positions()->create(['nom' => 'Scelleur', 'ordre' => 1]);
        $template->positions()->create(['nom' => 'Servant', 'ordre' => 2]);
        $servante = $template->positions()->create(['nom' => 'Servante', 'ordre' => 2]);

        // Le couple Coordonnateur/Coordonnatrice descend d'un bloc, ensemble.
        $this->actingAs($admin)->patch("/shift-templates/{$template->id}/postes/{$coordonnateur->id}/deplacer", [
            'direction' => 'bas',
        ])->assertRedirect();

        $this->assertSame(['Scelleur', 'Coordonnateur', 'Coordonnatrice', 'Servant', 'Servante'], $this->nomsAffiches($template));
        $this->assertSame(
            ['Scelleur' => 0, 'Coordonnateur' => 1, 'Coordonnatrice' => 1, 'Servant' => 2, 'Servante' => 2],
            $template->positions()->orderBy('ordre')->orderBy('id')->pluck('ordre', 'nom')->all()
        );

        // Le Scelleur descend seul, sans s'intercaler dans un couple.
        $this->actingAs($admin)->patch("/shift-templates/{$template->id}/postes/{$scelleur->id}/deplacer", [
            'direction' => 'bas',
        ])->assertRedirect();

        $this->assertSame(['Coordonnateur', 'Coordonnatrice', 'Scelleur', 'Servant', 'Servante'], $this->nomsAffiches($template));

        // Monter la Servante fait monter tout le couple Servant/Servante.
        $this->actingAs($admin)->patch("/shift-templates/{$template->id}/postes/{$servante->id}/deplacer", [
            'direction' => 'haut',
        ])->assertRedirect();

        $this->assertSame(['Coordonnateur', 'Coordonnatrice', 'Servant', 'Servante', 'Scelleur'], $this->nomsAffiches($template));
    }

    public function test_reordonner_par_glisser_deposer_garde_les_couples_contigus(): void
    {
        $admin = $this->makeAdmin();
        $template = ShiftTemplate::create(['organisation_id' => $admin->organisation_id, 'nom' => 'Temple Standard']);
        $coordonnateur = $template->positions()->create(['nom' => 'Coordonnateur', 'ordre' => 0]);
        $coordonnatrice = $template->positions()->create(['nom' => 'Coordonnatrice', 'ordre' => 0]);
        $scelleur = $template->positions()->create(['nom' => 'Scelleur', 'ordre' => 1]);
        $servant = $template->positions()->create(['nom' => 'Servant', 'ordre' => 2]);
        $servante = $template->positions()->create(['nom' => 'Servante', 'ordre' => 2]);

        // Liste reçue qui sépare les couples (femme avant homme, Scelleur
        // intercalé) : le serveur recolle chaque couple, homme en tête.
        $this->actingAs($admin)->patch("/shift-templates/{$template->id}/postes/reordonner", [
            'positions' => [$servante->id, $scelleur->id, $coordonnatrice->id, $servant->id, $coordonnateur->id],
        ])->assertRedirect();

        $this->assertSame(['Servant', 'Servante', 'Scelleur', 'Coordonnateur', 'Coordonnatrice'], $this->nomsAffiches($template));
        $this->assertSame($servant->fresh()->ordre, $servante->fresh()->ordre);
        $this->assertSame($coordonnateur->fresh()->ordre, $coordonnatrice->fresh()->ordre);
        $this->assertNotSame($scelleur->fresh()->ordre, $servant->fresh()->ordre);
    }

    public function test_ajouter_un_poste_femme_le_place_juste_sous_son_poste_homme(): void
    {
        $admin = $this->makeAdmin();
        $template = ShiftTemplate::create(['organisation_id' => $admin->organisation_id, 'nom' => 'Temple Standard']);
        $template->positions()->create(['nom' => 'Coordonnateur Adjoint de la formation', 'ordre' => 0]);
        $template->positions()->create(['nom' => 'Scelleur', 'ordre' => 1]);
        $template->positions()->create(['nom' => 'Servant', 'ordre' => 2]);

        $this->actingAs($admin)->post("/shift-templates/{$template->id}/postes", ['nom' => 'Coordonnatrice Adjointe de la formation'])
            ->assertRedirect();
        $this->actingAs($admin)->post("/shift-templates/{$template->id}/postes", ['nom' => 'Servante'])
            ->assertRedirect();
        // Poste personnalisé sans pendant : ajouté librement en fin de liste.
        $this->actingAs($admin)->post("/shift-templates/{$template->id}/postes", ['nom' => 'Accueil'])
            ->assertRedirect();

        $this->assertSame([
            'Coordonnateur Adjoint de la formation',
            'Coordonnatrice Adjointe de la formation',
            'Scelleur',
            'Servant',
            'Servante',
            'Accueil',
        ], $this->nomsAffiches($template));

        $this->assertDatabaseHas('shift_template_positions', ['shift_template_id' => $template->id, 'nom' => 'Coordonnatrice Adjointe de la formation', 'ordre' => 0]);
        $this->assertDatabaseHas('shift_template_positions', ['shift_template_id' => $template->id, 'nom' => 'Servante', 'ordre' => 2]);
        $this->assertDatabaseHas('shift_template_positions', ['shift_template_id' => $template->id, 'nom' => 'Accueil', 'ordre' => 3]);
    }

    public function test_ajouter_un_poste_homme_le_place_au_dessus_de_sa_femme_deja_presente(): void
    {
        $admin = $this->makeAdmin();
        $template = ShiftTemplate::create(['organisation_id' => $admin->organisation_id, 'nom' => 'Temple Standard']);
        $template->positions()->create(['nom' => 'Coordonnatrice', 'ordre' => 0]);
        $template->positions()->create(['nom' => 'Scelleur', 'ordre' => 1]);

        $this->actingAs($admin)->post("/shift-templates/{$template->id}/postes", ['nom' => 'Coordonnateur'])
            ->assertRedirect();

        $this->assertSame(['Coordonnateur', 'Coordonnatrice', 'Scelleur'], $this->nomsAffiches($template));
    }
}
