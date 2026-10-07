<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\Servant;
use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ChangementStatutServantTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $organisation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = Organisation::factory()->create();
    }

    private function makeUser(string $slug, ?Organisation $organisation = null): User
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['nom' => $slug, 'gere_shifts' => $slug === 'coordonnateur_equipe']);

        return User::factory()->create([
            'organisation_id' => ($organisation ?? $this->organisation)->id,
            'role_id' => $role->id,
        ]);
    }

    private function makeServant(string $statut = 'recommande'): Servant
    {
        return Servant::factory()->create(['organisation_id' => $this->organisation->id, 'statut' => $statut]);
    }

    public function test_conseil_change_librement_le_statut_dans_les_deux_sens(): void
    {
        foreach (['administrateur', 'super_admin'] as $slug) {
            $user = $this->makeUser($slug);
            $servant = $this->makeServant('recommande');

            foreach (['en_formation', 'actif', 'recommande', 'actif', 'en_formation', 'recommande'] as $statut) {
                $this->actingAs($user)
                    ->patch("/servants/{$servant->id}/statut", ['statut' => $statut])
                    ->assertRedirect()
                    ->assertSessionHasNoErrors();

                $this->assertSame($statut, $servant->fresh()->statut);
            }
        }
    }

    public function test_changement_journalise_avec_statut_avant_apres(): void
    {
        $admin = $this->makeUser('administrateur');
        $servant = $this->makeServant('en_formation');

        $this->actingAs($admin)->patch("/servants/{$servant->id}/statut", ['statut' => 'actif'])
            ->assertSessionHas('success', 'Statut mis à jour : « Ancien ».');

        $log = Activity::where('event', 'changement_statut')->firstOrFail();
        $this->assertSame($servant->id, $log->subject_id);
        $this->assertSame($admin->id, $log->causer_id);
        $this->assertSame('en_formation', $log->properties['ancien_statut']);
        $this->assertSame('actif', $log->properties['nouveau_statut']);
    }

    public function test_plus_de_blocage_ancien_si_parcours_incomplet(): void
    {
        $admin = $this->makeUser('administrateur');
        $servant = $this->makeServant('en_formation');
        $etape = WorkflowStep::create(['cle' => 'entretien', 'nom' => 'Entretien', 'ordre' => 1]);
        $servant->workflowSteps()->create(['workflow_step_id' => $etape->id, 'statut' => 'en_attente']);

        $this->actingAs($admin)->patch("/servants/{$servant->id}/statut", ['statut' => 'actif'])
            ->assertSessionHasNoErrors();

        $this->assertSame('actif', $servant->fresh()->statut);
    }

    public function test_secretaire_coordonnateur_et_autres_ne_changent_pas_le_statut(): void
    {
        $servant = $this->makeServant('recommande');

        foreach (['secretaire', 'coordonnateur_equipe', 'autres'] as $slug) {
            $this->actingAs($this->makeUser($slug))
                ->patch("/servants/{$servant->id}/statut", ['statut' => 'actif'])
                ->assertForbidden();
        }

        // La secrétaire modifie la fiche : le champ statut est ignoré.
        $secretaire = $this->makeUser('secretaire');
        $this->actingAs($secretaire)->put("/servants/{$servant->id}", [
            'nom' => 'Kouassi',
            'prenom' => 'Paul',
            'statut' => 'actif',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $servant->refresh();
        $this->assertSame('Kouassi', $servant->nom);
        $this->assertSame('recommande', $servant->statut);
        $this->assertSame(0, Activity::where('event', 'changement_statut')->count());

        // Le sélecteur n'est pas proposé à la secrétaire.
        $this->actingAs($secretaire)->get("/servants/{$servant->id}/edit")
            ->assertInertia(fn (Assert $page) => $page->where('peutChangerStatut', false));
        $this->actingAs($secretaire)->get("/servants/{$servant->id}")
            ->assertInertia(fn (Assert $page) => $page->where('peutChangerStatut', false));
    }

    public function test_selecteur_propose_au_conseil_avec_les_trois_statuts(): void
    {
        $admin = $this->makeUser('administrateur');
        $servant = $this->makeServant('en_formation');

        $this->actingAs($admin)->get("/servants/{$servant->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('peutChangerStatut', true)
                ->where('statutsModifiables', [
                    ['value' => 'recommande', 'label' => 'Recommandé'],
                    ['value' => 'en_formation', 'label' => 'Nouveau'],
                    ['value' => 'actif', 'label' => 'Ancien'],
                ]));
        $this->actingAs($admin)->get("/servants/{$servant->id}/edit")
            ->assertInertia(fn (Assert $page) => $page->where('peutChangerStatut', true));
    }

    public function test_valeur_invalide_refusee(): void
    {
        $admin = $this->makeUser('administrateur');
        $servant = $this->makeServant('recommande');

        foreach (['suspendu', 'retire', 'inconnu', ''] as $statut) {
            $this->actingAs($admin)->patch("/servants/{$servant->id}/statut", ['statut' => $statut])
                ->assertSessionHasErrors('statut');
        }

        // Même règle via le formulaire d'édition.
        $this->actingAs($admin)->put("/servants/{$servant->id}", [
            'nom' => $servant->nom,
            'prenom' => $servant->prenom,
            'statut' => 'suspendu',
        ])->assertSessionHasErrors('statut');

        $this->assertSame('recommande', $servant->fresh()->statut);
    }

    public function test_servant_releve_ou_permutant_non_modifiable_par_ce_chemin(): void
    {
        $admin = $this->makeUser('administrateur');

        foreach (['suspendu', 'retire'] as $statut) {
            $servant = $this->makeServant($statut);

            $this->actingAs($admin)->patch("/servants/{$servant->id}/statut", ['statut' => 'actif'])
                ->assertSessionHasErrors('statut');
            $this->actingAs($admin)->put("/servants/{$servant->id}", [
                'nom' => $servant->nom,
                'prenom' => $servant->prenom,
                'statut' => 'actif',
            ])->assertSessionHasErrors('statut');

            $this->assertSame($statut, $servant->fresh()->statut);
            $this->actingAs($admin)->get("/servants/{$servant->id}")
                ->assertInertia(fn (Assert $page) => $page->where('peutChangerStatut', false));

            // L'édition de la fiche reste possible sans toucher au statut.
            $this->actingAs($admin)->put("/servants/{$servant->id}", [
                'nom' => 'Renommé',
                'prenom' => $servant->prenom,
                'statut' => $statut,
            ])->assertSessionHasNoErrors();
            $this->assertSame('Renommé', $servant->fresh()->nom);
        }
    }

    public function test_organisation_verifiee(): void
    {
        $admin = $this->makeUser('administrateur');
        $autre = Servant::factory()->create(['organisation_id' => Organisation::factory()->create()->id, 'statut' => 'recommande']);

        $this->actingAs($admin)->patch("/servants/{$autre->id}/statut", ['statut' => 'actif'])->assertForbidden();

        $this->assertSame('recommande', $autre->fresh()->statut);
    }

    public function test_csv_affiche_les_libelles(): void
    {
        $admin = $this->makeUser('administrateur');
        Servant::factory()->create(['organisation_id' => $this->organisation->id, 'nom' => 'Aaa', 'statut' => 'en_formation']);
        Servant::factory()->create(['organisation_id' => $this->organisation->id, 'nom' => 'Bbb', 'statut' => 'actif']);

        $csv = $this->actingAs($admin)->get('/rapports/servants.csv')->assertOk()->streamedContent();

        $this->assertStringContainsString('Nouveau', $csv);
        $this->assertStringContainsString('Ancien', $csv);
        $this->assertStringNotContainsString('En formation', $csv);
        $this->assertSame('Nouveau', Servant::LIBELLES_STATUT['en_formation']);
    }
}
