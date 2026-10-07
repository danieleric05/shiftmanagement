<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Organisation;
use App\Models\Role;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftPosition;
use App\Models\ShiftTemplate;
use App\Models\ShiftTemplatePosition;
use App\Models\ShiftTransferRequest;
use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ReintegrationServantTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $organisation;

    private User $admin;

    private ShiftTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = Organisation::factory()->create();
        $this->admin = $this->makeUser('administrateur');
        $this->template = ShiftTemplate::create(['organisation_id' => $this->organisation->id, 'nom' => 'Temple Standard']);
    }

    private function makeUser(string $slug, ?Organisation $organisation = null): User
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['nom' => $slug, 'gere_shifts' => $slug === 'coordonnateur_equipe']);

        return User::factory()->create([
            'organisation_id' => ($organisation ?? $this->organisation)->id,
            'role_id' => $role->id,
        ]);
    }

    private function makeShift(string $nom, ?Organisation $organisation = null): Shift
    {
        return Shift::create([
            'organisation_id' => ($organisation ?? $this->organisation)->id,
            'shift_template_id' => $this->template->id,
            'nom' => $nom,
            'jour' => 'mardi',
            'heure_debut' => '07:00',
            'heure_fin' => '11:00',
            'statut' => 'actif',
        ]);
    }

    /**
     * Servant affecté sur $shift puis relevé (même mécanique que
     * ShiftTransferRequestController::resolve() pour une relève).
     */
    private function servantReleve(array $attributs = [], ?Shift $shift = null): Servant
    {
        $shift ??= $this->makeShift('Mardi Matin Frères');

        $servant = Servant::factory()->create([
            'organisation_id' => $shift->organisation_id,
            'genre' => 'homme',
            'statut' => 'actif',
            ...$attributs,
        ]);

        $position = ShiftPosition::create(['shift_id' => $shift->id, 'nom' => 'Servant', 'ordre' => 10]);
        Assignment::create([
            'shift_position_id' => $position->id,
            'servant_id' => $servant->id,
            'date_debut' => now()->subYear()->toDateString(),
            'statut' => 'actif',
        ]);

        $admin = $shift->organisation_id === $this->organisation->id ? $this->admin : $this->makeUser('administrateur', Organisation::find($shift->organisation_id));

        $demande = ShiftTransferRequest::create([
            'organisation_id' => $shift->organisation_id,
            'type' => 'releve',
            'shift_id' => $shift->id,
            'servant_id' => $servant->id,
            'demandeur_id' => $admin->id,
            'motif' => 'Indisponible',
            'date_demande' => now()->toDateString(),
            'statut' => 'en_attente',
        ]);

        $this->actingAs($admin)->patch("/transferts/{$demande->id}/resoudre", [
            'resultat' => 'Relevé',
            'resultat_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('assignments', ['servant_id' => $servant->id, 'statut' => 'termine']);

        return $servant->fresh();
    }

    public function test_reintegration_simple_conserve_l_historique_et_journalise(): void
    {
        $servant = $this->servantReleve(['statut' => 'retire']);
        $releve = ShiftTransferRequest::where('servant_id', $servant->id)->firstOrFail();
        $this->assertTrue($servant->estReleve());

        $this->actingAs($this->admin)
            ->post("/servants/{$servant->id}/reintegrer", ['commentaire' => 'De retour de mission'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $servant->refresh();
        $releve->refresh();

        $this->assertFalse($servant->estReleve());
        // Parcours sans étape restante : « Ancien » (actif).
        $this->assertSame('actif', $servant->statut);
        $this->assertNotNull($releve->reintegre_le);
        $this->assertSame($this->admin->id, $releve->reintegre_par_id);
        $this->assertSame('De retour de mission', $releve->reintegration_commentaire);
        // Historique de la relève conservé (ni supprimée, ni modifiée).
        $this->assertSame('traitee', $releve->statut);
        $this->assertSame('releve', $releve->type);
        $this->assertNull($releve->deleted_at);
        $this->assertStringContainsString('Réintégré(e) par', $servant->notes);
        $this->assertSame(0, $servant->assignationsActives()->count());

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Servant::class,
            'subject_id' => $servant->id,
            'event' => 'reintegration',
            'causer_id' => $this->admin->id,
        ]);

        // Historique visible sur la page des relevés et sur la fiche.
        $this->actingAs($this->admin)->get('/transferts/releves')
            ->assertInertia(fn ($page) => $page->where('releves.data.0.reintegre_le', now()->format('Y-m-d'))
                ->where('releves.data.0.peut_reintegrer', false));
        $this->actingAs($this->admin)->get("/servants/{$servant->id}")
            ->assertInertia(fn ($page) => $page->where('estReleve', false)->has('releves', 1));
    }

    public function test_statut_ancien_meme_si_parcours_incomplet(): void
    {
        $servant = $this->servantReleve(['statut' => 'suspendu']);
        $etape = WorkflowStep::create(['cle' => 'entretien', 'nom' => 'Entretien', 'ordre' => 1]);
        $servant->workflowSteps()->create(['workflow_step_id' => $etape->id, 'statut' => 'en_cours']);

        $this->actingAs($this->admin)->post("/servants/{$servant->id}/reintegrer")->assertRedirect();

        // Le parcours n'a plus aucun effet : « Ancien » dans tous les cas.
        $this->assertSame('actif', $servant->fresh()->statut);
        $this->assertSame('actif', Activity::where('event', 'reintegration')->firstOrFail()->properties['nouveau_statut']);
    }

    public function test_servant_au_statut_releve_sans_demande_peut_etre_reintegre(): void
    {
        $servant = Servant::factory()->create(['organisation_id' => $this->organisation->id, 'statut' => 'suspendu']);
        $this->assertTrue($servant->estReleve());

        $this->actingAs($this->admin)->post("/servants/{$servant->id}/reintegrer")->assertRedirect();

        $servant->refresh();
        $this->assertSame('actif', $servant->statut);
        $this->assertFalse($servant->estReleve());
        $this->assertStringContainsString('statut « Relevé »', $servant->notes);
        $this->actingAs($this->admin)->post("/servants/{$servant->id}/reintegrer")->assertStatus(422);
    }

    public function test_reintegration_remet_ancien_dans_tous_les_cas(): void
    {
        $shift = $this->makeShift('Mardi Matin Frères');

        foreach (['en_formation', 'recommande'] as $statut) {
            $servant = $this->servantReleve(['statut' => $statut], $shift);

            $this->actingAs($this->admin)->post("/servants/{$servant->id}/reintegrer")->assertRedirect();

            $this->assertSame('actif', $servant->fresh()->statut);
        }
    }

    public function test_servant_permutant_sans_releve_peut_etre_reintegre(): void
    {
        $servant = Servant::factory()->create(['organisation_id' => $this->organisation->id, 'statut' => 'retire']);

        $this->actingAs($this->admin)->get("/servants/{$servant->id}")
            ->assertInertia(fn ($page) => $page->where('peutReintegrer', true)->where('peutChangerStatut', false));

        $this->actingAs($this->admin)->post("/servants/{$servant->id}/reintegrer")->assertRedirect();

        $this->assertSame('actif', $servant->fresh()->statut);
        $this->assertStringContainsString('statut « Permutant »', $servant->fresh()->notes);
    }

    public function test_reintegration_avec_affectation_a_un_shift_et_un_poste(): void
    {
        $servantPoste = ShiftTemplatePosition::create(['shift_template_id' => $this->template->id, 'nom' => 'Servant', 'ordre' => 10]);
        $servant = $this->servantReleve();
        $destination = $this->makeShift('Jeudi Soir Frères');

        $this->actingAs($this->admin)->post("/servants/{$servant->id}/reintegrer", [
            'shift_id' => $destination->id,
            'shift_template_position_id' => $servantPoste->id,
        ])->assertRedirect()->assertSessionHas('success');

        $affectation = $servant->assignationsActives()->with('shiftPosition')->firstOrFail();
        $this->assertSame($destination->id, $affectation->shiftPosition->shift_id);
        $this->assertSame('Servant', $affectation->shiftPosition->nom);
        $this->assertFalse($servant->fresh()->estReleve());
    }

    public function test_genre_incompatible_refuse_et_rien_n_est_modifie(): void
    {
        $servantePoste = ShiftTemplatePosition::create(['shift_template_id' => $this->template->id, 'nom' => 'Servante', 'ordre' => 11]);
        $servant = $this->servantReleve(['statut' => 'retire']);
        $soeurs = $this->makeShift('Jeudi Soir Sœurs');

        $this->actingAs($this->admin)->post("/servants/{$servant->id}/reintegrer", [
            'shift_id' => $soeurs->id,
            'shift_template_position_id' => $servantePoste->id,
        ])->assertStatus(422);

        // Transaction annulée : toujours relevé, statut inchangé, aucune affectation.
        $servant->refresh();
        $this->assertTrue($servant->estReleve());
        $this->assertSame('retire', $servant->statut);
        $this->assertSame(0, $servant->assignationsActives()->count());
    }

    public function test_poste_unique_deja_present_refuse(): void
    {
        $coordo = ShiftTemplatePosition::create(['shift_template_id' => $this->template->id, 'nom' => 'Coordonnateur', 'ordre' => 1]);
        $servant = $this->servantReleve();
        $destination = $this->makeShift('Jeudi Soir Frères');
        ShiftPosition::create(['shift_id' => $destination->id, 'shift_template_position_id' => $coordo->id, 'nom' => 'Coordonnateur', 'ordre' => 1]);

        $this->actingAs($this->admin)->post("/servants/{$servant->id}/reintegrer", [
            'shift_id' => $destination->id,
            'shift_template_position_id' => $coordo->id,
        ])->assertStatus(422);

        $this->assertTrue($servant->fresh()->estReleve());
        $this->assertSame(1, ShiftPosition::where('shift_id', $destination->id)->count());
    }

    public function test_refus_si_le_servant_n_est_pas_releve(): void
    {
        $servant = Servant::factory()->create(['organisation_id' => $this->organisation->id, 'statut' => 'actif']);

        $this->actingAs($this->admin)->post("/servants/{$servant->id}/reintegrer")->assertStatus(422);

        $this->assertDatabaseMissing('activity_log', ['event' => 'reintegration']);
    }

    public function test_double_reintegration_refusee(): void
    {
        $servant = $this->servantReleve();

        $this->actingAs($this->admin)->post("/servants/{$servant->id}/reintegrer")->assertRedirect();
        // La seconde requête (concurrente ou rejouée) relit l'état sous verrou : refusée.
        $this->actingAs($this->admin)->post("/servants/{$servant->id}/reintegrer")->assertStatus(422);

        $this->assertSame(1, Activity::where('event', 'reintegration')->count());
    }

    public function test_peut_etre_releve_puis_reintegre_plusieurs_fois(): void
    {
        $shift = $this->makeShift('Mardi Matin Frères');
        $servant = $this->servantReleve([], $shift);
        $this->actingAs($this->admin)->post("/servants/{$servant->id}/reintegrer")->assertRedirect();

        // Nouvelle relève du même servant.
        $demande = ShiftTransferRequest::create([
            'organisation_id' => $this->organisation->id,
            'type' => 'releve',
            'shift_id' => $shift->id,
            'servant_id' => $servant->id,
            'demandeur_id' => $this->admin->id,
            'motif' => 'Nouvelle absence',
            'date_demande' => now()->toDateString(),
            'statut' => 'en_attente',
        ]);
        $this->actingAs($this->admin)->patch("/transferts/{$demande->id}/resoudre", [
            'resultat' => 'Relevé à nouveau',
            'resultat_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertTrue($servant->fresh()->estReleve());
        $this->actingAs($this->admin)->post("/servants/{$servant->id}/reintegrer")->assertRedirect();

        $this->assertFalse($servant->fresh()->estReleve());
        $this->assertSame(2, ShiftTransferRequest::where('servant_id', $servant->id)->whereNotNull('reintegre_le')->count());
        $this->assertSame(2, Activity::where('event', 'reintegration')->count());
    }

    public function test_super_admin_peut_reintegrer(): void
    {
        $servant = $this->servantReleve();
        $superAdmin = $this->makeUser('super_admin');

        $this->actingAs($superAdmin)->post("/servants/{$servant->id}/reintegrer")->assertRedirect();

        $this->assertFalse($servant->fresh()->estReleve());
    }

    public function test_secretaire_coordonnateur_et_autres_recoivent_403(): void
    {
        $servant = $this->servantReleve();

        foreach (['secretaire', 'coordonnateur_equipe', 'autres'] as $slug) {
            $this->actingAs($this->makeUser($slug))
                ->post("/servants/{$servant->id}/reintegrer")
                ->assertForbidden();
        }

        $this->assertTrue($servant->fresh()->estReleve());

        // Le bouton n'est pas proposé au secrétaire (page des relevés).
        $this->actingAs($this->makeUser('secretaire'))->get('/transferts/releves')
            ->assertInertia(fn ($page) => $page->where('releves.data.0.peut_reintegrer', false));
    }

    public function test_isolation_par_organisation(): void
    {
        $autreOrganisation = Organisation::factory()->create();
        $servantAutre = $this->servantReleve([], $this->makeShift('Mardi Matin Frères', $autreOrganisation));

        $this->actingAs($this->admin)->post("/servants/{$servantAutre->id}/reintegrer")->assertForbidden();
        $this->assertTrue($servantAutre->fresh()->estReleve());

        // Un shift d'une autre organisation est refusé comme destination.
        $poste = ShiftTemplatePosition::create(['shift_template_id' => $this->template->id, 'nom' => 'Servant', 'ordre' => 10]);
        $servant = $this->servantReleve();
        $shiftAutre = $this->makeShift('Jeudi Soir Frères', $autreOrganisation);

        $this->actingAs($this->admin)->post("/servants/{$servant->id}/reintegrer", [
            'shift_id' => $shiftAutre->id,
            'shift_template_position_id' => $poste->id,
        ])->assertSessionHasErrors('shift_id');
        $this->assertTrue($servant->fresh()->estReleve());
    }
}
