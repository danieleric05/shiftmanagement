<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Organisation;
use App\Models\Role;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftPosition;
use App\Models\ShiftTransferRequest;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Notifications\NouvelleDemandeTransfert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class SuppressionServantTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $organisation;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->organisation = Organisation::factory()->create();
        $this->admin = $this->makeUser('administrateur');
    }

    private function makeUser(string $slug, ?Organisation $organisation = null): User
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['nom' => $slug, 'gere_shifts' => $slug === 'coordonnateur_equipe']);

        return User::factory()->create([
            'organisation_id' => ($organisation ?? $this->organisation)->id,
            'role_id' => $role->id,
        ]);
    }

    /**
     * Servant complet : photo, compte lié optionnel, affectation active,
     * parcours, relève + permutation (dont une soft-deleted), notification.
     *
     * @return array{servant: Servant, position: ShiftPosition, demandes: list<int>}
     */
    private function servantComplet(?Organisation $organisation = null, ?User $compte = null): array
    {
        $organisation ??= $this->organisation;
        $photo = UploadedFile::fake()->image('portrait.jpg')->store("servants/{$organisation->id}", 'local');

        $servant = Servant::factory()->create([
            'organisation_id' => $organisation->id,
            'user_id' => $compte?->id,
            'nom' => 'Kouassi',
            'prenom' => 'Jean',
            'genre' => 'homme',
            'telephone' => '0102030405',
            'photo' => $photo,
            'statut' => 'actif',
        ]);

        $shift = Shift::create([
            'organisation_id' => $organisation->id,
            'nom' => 'Mardi Matin Frères',
            'jour' => 'mardi',
            'heure_debut' => '07:00',
            'heure_fin' => '11:00',
            'statut' => 'actif',
        ]);
        $autreShift = Shift::create([
            'organisation_id' => $organisation->id,
            'nom' => 'Jeudi Soir Frères',
            'jour' => 'jeudi',
            'heure_debut' => '17:00',
            'heure_fin' => '21:00',
            'statut' => 'actif',
        ]);
        $position = ShiftPosition::create(['shift_id' => $shift->id, 'nom' => 'Servant', 'ordre' => 10]);
        Assignment::create(['shift_position_id' => $position->id, 'servant_id' => $servant->id, 'date_debut' => '2025-01-01', 'statut' => 'actif']);
        $anciennePosition = ShiftPosition::create(['shift_id' => $autreShift->id, 'nom' => 'Servant', 'ordre' => 10]);
        Assignment::create(['shift_position_id' => $anciennePosition->id, 'servant_id' => $servant->id, 'date_debut' => '2024-01-01', 'date_fin' => '2024-12-31', 'statut' => 'termine']);

        $etape = WorkflowStep::firstOrCreate(['cle' => 'entretien'], ['nom' => 'Entretien', 'ordre' => 1]);
        $servant->workflowSteps()->create(['workflow_step_id' => $etape->id, 'statut' => 'termine']);

        $demandeur = User::where('organisation_id', $organisation->id)->first() ?? $this->makeUser('administrateur', $organisation);
        $releve = ShiftTransferRequest::create([
            'organisation_id' => $organisation->id, 'type' => 'releve', 'shift_id' => $shift->id, 'servant_id' => $servant->id,
            'demandeur_id' => $demandeur->id, 'motif' => 'Absent', 'date_demande' => now()->toDateString(), 'statut' => 'traitee',
        ]);
        $permutation = ShiftTransferRequest::create([
            'organisation_id' => $organisation->id, 'type' => 'permutation', 'shift_id' => $shift->id, 'shift_destination_id' => $autreShift->id,
            'servant_id' => $servant->id, 'demandeur_id' => $demandeur->id, 'motif' => 'Horaires', 'date_demande' => now()->toDateString(), 'statut' => 'en_attente',
        ]);
        $permutation->delete(); // soft-deleted : doit aussi disparaître.

        $demandeur->notify(new NouvelleDemandeTransfert($releve));

        return ['servant' => $servant, 'position' => $position, 'demandes' => [$releve->id, $permutation->id]];
    }

    public function test_suppression_definitive_efface_tout_et_journalise_sans_donnees_personnelles(): void
    {
        ['servant' => $servant, 'position' => $position, 'demandes' => $demandes] = $this->servantComplet();
        $photo = $servant->photo;
        $assignmentIds = Assignment::where('servant_id', $servant->id)->pluck('id');
        Storage::disk('local')->assertExists($photo);
        $this->assertGreaterThan(0, Activity::where('subject_type', Servant::class)->where('subject_id', $servant->id)->count());
        $this->assertSame(1, $this->admin->notifications()->count());

        $this->actingAs($this->admin)
            ->delete("/servants/{$servant->id}", ['confirmation' => 'Jean Kouassi'])
            ->assertRedirect(route('servants.index'))
            ->assertSessionHas('success');

        // Disparition réelle, y compris des lignes soft-deleted.
        $this->assertDatabaseMissing('servants', ['id' => $servant->id]);
        $this->assertNull(Servant::withTrashed()->find($servant->id));
        $this->assertDatabaseMissing('assignments', ['servant_id' => $servant->id]);
        $this->assertDatabaseMissing('servant_workflow_steps', ['servant_id' => $servant->id]);
        $this->assertSame(0, ShiftTransferRequest::withTrashed()->whereIn('id', $demandes)->count());
        $this->assertSame(0, $this->admin->notifications()->count());
        $this->assertSame(0, Activity::where('subject_type', Servant::class)->where('subject_id', $servant->id)->count());
        $this->assertSame(0, Activity::where('subject_type', Assignment::class)->whereIn('subject_id', $assignmentIds)->count());
        $this->assertSame(0, Activity::where('subject_type', ShiftTransferRequest::class)->whereIn('subject_id', $demandes)->count());
        // Le poste occupé ne survit pas à son occupant (suppression douce).
        $this->assertSoftDeleted('shift_positions', ['id' => $position->id]);

        Storage::disk('local')->assertMissing($photo);

        $audit = Activity::where('event', 'suppression_definitive_servant')->sole();
        $this->assertSame($this->admin->id, $audit->causer_id);
        $this->assertSame($this->organisation->id, $audit->subject_id);
        $this->assertSame($servant->id, $audit->properties['servant_id']);
        $this->assertSame($this->organisation->id, $audit->properties['organisation_id']);
        $this->assertSame(2, $audit->properties['entrees_supprimees']['affectations']);
        $this->assertSame(2, $audit->properties['entrees_supprimees']['demandes_changement']);
        $this->assertSame(1, $audit->properties['entrees_supprimees']['etapes_parcours']);

        $brut = json_encode($audit->properties).$audit->description;
        foreach (['Kouassi', 'Jean', '0102030405', 'portrait', $photo] as $donneePersonnelle) {
            $this->assertStringNotContainsString($donneePersonnelle, $brut);
        }

        // L'audit reste visible dans le journal d'activité de l'organisation.
        $this->actingAs($this->admin)->get('/parametres/journal')
            ->assertInertia(fn ($page) => $page->where('activites.data.0.evenement', 'suppression_definitive_servant'));
    }

    public function test_le_compte_utilisateur_lie_est_conserve(): void
    {
        $membreConseil = $this->makeUser('administrateur');
        ['servant' => $servant] = $this->servantComplet(null, $membreConseil);

        $this->actingAs($this->admin)
            ->delete("/servants/{$servant->id}", ['confirmation' => 'SUPPRIMER'])
            ->assertRedirect()
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'compte de connexion du membre'));

        $this->assertDatabaseMissing('servants', ['id' => $servant->id]);
        $this->assertDatabaseHas('users', ['id' => $membreConseil->id, 'email' => $membreConseil->email]);
        $this->assertTrue(Activity::where('event', 'suppression_definitive_servant')->sole()->properties['compte_utilisateur_conserve']);
    }

    public function test_confirmation_requise(): void
    {
        ['servant' => $servant] = $this->servantComplet();

        $this->actingAs($this->admin)->delete("/servants/{$servant->id}")->assertSessionHasErrors('confirmation');
        $this->actingAs($this->admin)->delete("/servants/{$servant->id}", ['confirmation' => 'supprimer pas'])->assertSessionHasErrors('confirmation');
        $this->actingAs($this->admin)->delete("/servants/{$servant->id}", ['confirmation' => 'Paul Kouassi'])->assertSessionHasErrors('confirmation');

        $this->assertDatabaseHas('servants', ['id' => $servant->id, 'deleted_at' => null]);
        Storage::disk('local')->assertExists($servant->photo);
        $this->assertSame(0, Activity::where('event', 'suppression_definitive_servant')->count());
    }

    public function test_autres_roles_recoivent_403(): void
    {
        ['servant' => $servant] = $this->servantComplet();

        foreach (['secretaire', 'coordonnateur_equipe', 'autres'] as $slug) {
            $this->actingAs($this->makeUser($slug))
                ->delete("/servants/{$servant->id}", ['confirmation' => 'SUPPRIMER'])
                ->assertForbidden();
        }

        $this->assertDatabaseHas('servants', ['id' => $servant->id, 'deleted_at' => null]);

        // Le bouton n'est pas proposé (aucune donnée de suppression) au secrétaire.
        $this->actingAs($this->makeUser('secretaire'))->get("/servants/{$servant->id}")
            ->assertInertia(fn ($page) => $page->where('suppression', null));
    }

    public function test_super_admin_peut_supprimer(): void
    {
        ['servant' => $servant] = $this->servantComplet();

        $this->actingAs($this->makeUser('super_admin'))
            ->delete("/servants/{$servant->id}", ['confirmation' => 'SUPPRIMER'])
            ->assertRedirect();

        $this->assertNull(Servant::withTrashed()->find($servant->id));
    }

    public function test_autre_organisation_refusee(): void
    {
        $autreOrganisation = Organisation::factory()->create();
        ['servant' => $servant] = $this->servantComplet($autreOrganisation);

        $this->actingAs($this->admin)
            ->delete("/servants/{$servant->id}", ['confirmation' => 'SUPPRIMER'])
            ->assertForbidden();

        $this->assertDatabaseHas('servants', ['id' => $servant->id, 'deleted_at' => null]);
    }

    public function test_la_fiche_expose_le_bilan_et_l_anonymisation_reste_disponible(): void
    {
        ['servant' => $servant] = $this->servantComplet();

        $this->actingAs($this->admin)->get("/servants/{$servant->id}")
            ->assertInertia(fn ($page) => $page->where('suppression.bilan.affectations', 2)
                ->where('suppression.bilan.demandes_changement', 2)
                ->where('suppression.bilan.releves', 1)
                ->where('suppression.bilan.permutations', 1)
                ->where('suppression.compte_lie', false));

        $this->actingAs($this->admin)->patch("/servants/{$servant->id}/anonymiser")->assertRedirect();
        $this->assertSame('Anonymisé', $servant->fresh()->nom);
    }
}
