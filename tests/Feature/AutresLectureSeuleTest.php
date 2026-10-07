<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Horaire;
use App\Models\Organisation;
use App\Models\Pieu;
use App\Models\Role;
use App\Models\Servant;
use App\Models\ServantWorkflowStep;
use App\Models\Shift;
use App\Models\ShiftMember;
use App\Models\ShiftPosition;
use App\Models\ShiftTemplate;
use App\Models\ShiftTransferRequest;
use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Rôle « Autres » : consultation en lecture seule de toute l'organisation
 * (hors configuration), sans aucune action d'écriture.
 */
class AutresLectureSeuleTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $organisation;

    private User $autres;

    private User $admin;

    private Shift $shift;

    private ShiftPosition $position;

    private Assignment $assignment;

    private Servant $servant;

    private ShiftTemplate $template;

    private ShiftTransferRequest $releve;

    private ShiftTransferRequest $permutation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = Organisation::factory()->create();
        $this->autres = $this->makeUser('autres', $this->organisation);
        $this->admin = $this->makeUser('administrateur', $this->organisation);

        $this->template = ShiftTemplate::create(['organisation_id' => $this->organisation->id, 'nom' => 'Temple Standard']);
        $templatePosition = $this->template->positions()->create(['nom' => 'Servant', 'ordre' => 1]);

        $this->shift = $this->makeShift($this->organisation, 'Mardi Matin Frères', $this->template->id);
        $shiftDestination = $this->makeShift($this->organisation, 'Jeudi Soir Frères', $this->template->id);

        $this->position = ShiftPosition::create([
            'shift_id' => $this->shift->id,
            'shift_template_position_id' => $templatePosition->id,
            'nom' => 'Servant',
            'ordre' => 1,
        ]);

        $this->servant = Servant::factory()->create([
            'organisation_id' => $this->organisation->id,
            'genre' => 'homme',
            'statut' => 'actif',
        ]);

        $this->assignment = Assignment::create([
            'shift_position_id' => $this->position->id,
            'servant_id' => $this->servant->id,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        $this->releve = $this->makeDemande('releve', $this->shift);
        $this->permutation = $this->makeDemande('permutation', $this->shift, $shiftDestination);
    }

    private function makeUser(string $roleSlug, Organisation $organisation): User
    {
        return User::factory()->create([
            'organisation_id' => $organisation->id,
            'role_id' => $this->role($roleSlug)->id,
        ]);
    }

    /**
     * secretaire/autres sont créés par les migrations ; les autres rôles
     * (seedés hors migrations) sont créés à la volée pour les tests.
     */
    private function role(string $slug): Role
    {
        return Role::firstOrCreate(['slug' => $slug], ['nom' => $slug, 'gere_shifts' => $slug === 'coordonnateur_equipe']);
    }

    private function makeShift(Organisation $organisation, string $nom, ?int $templateId = null): Shift
    {
        return Shift::create([
            'organisation_id' => $organisation->id,
            'shift_template_id' => $templateId,
            'nom' => $nom,
            'jour' => 'mardi',
            'heure_debut' => '07:00',
            'heure_fin' => '11:00',
            'statut' => 'actif',
        ]);
    }

    private function makeDemande(string $type, Shift $shift, ?Shift $destination = null, ?Servant $servant = null): ShiftTransferRequest
    {
        return ShiftTransferRequest::create([
            'organisation_id' => $shift->organisation_id,
            'type' => $type,
            'shift_id' => $shift->id,
            'shift_destination_id' => $destination?->id,
            'servant_id' => ($servant ?? $this->servant)->id,
            'demandeur_id' => $this->admin->id,
            'motif' => 'Test',
            'date_demande' => now()->toDateString(),
            'statut' => 'en_attente',
        ]);
    }

    public function test_le_role_autres_existe_avec_les_bons_libelles(): void
    {
        $role = Role::where('slug', 'autres')->firstOrFail();

        $this->assertSame('Autres', $role->nom);
        $this->assertSame('Consultation en lecture seule (hors configuration).', $role->description);
        $this->assertFalse((bool) $role->gere_shifts);
        $this->assertTrue($this->autres->estEnLectureSeule());
        $this->assertTrue($this->autres->consulteToutesLesDonnees());
        $this->assertFalse($this->autres->gereServantsEtPermutations());
        $this->assertFalse($this->admin->estEnLectureSeule());
    }

    public function test_pages_de_consultation_accessibles(): void
    {
        $pages = [
            '/dashboard',
            '/shifts',
            "/shifts/{$this->shift->id}",
            '/servants',
            '/servants/nouveaux',
            "/servants/{$this->servant->id}",
            '/transferts',
            '/transferts?type=releve',
            '/transferts?type=appel',
            '/transferts/releves',
            '/recrutement',
            '/rapports',
            '/rapports/servants.csv',
            '/rapports/shifts-remplissage.pdf',
            '/profile',
        ];

        foreach ($pages as $page) {
            $this->actingAs($this->autres)->get($page)->assertOk();
        }
    }

    public function test_dashboard_reutilise_la_vue_administrateur_en_lecture_seule(): void
    {
        $this->actingAs($this->autres)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Admin')
                ->where('auth.role', 'autres')
                ->where('auth.lectureSeule', true)
                ->has('shifts', 2)
                ->where('releves.en_attente', 1)
                ->where('permutations.en_attente', 1));

        $this->actingAs($this->admin)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('auth.lectureSeule', false));
    }

    public function test_voit_toute_lorganisation_comme_un_administrateur(): void
    {
        $this->actingAs($this->autres)->get('/transferts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ShiftTransfers/Index')
                ->has('demandes.data', 2)
                ->where('estAdministrateur', false)
                ->where('consulteTout', true)
                ->where('compteurs.releves', 1)
                ->where('compteurs.permutations', 1)
                ->where('shifts', [])
                ->where('servants', [])
                ->where('demandes.data.0.peut_valider_origine', false)
                ->where('demandes.data.0.peut_valider_destination', false));

        $this->actingAs($this->autres)->get('/recrutement')
            ->assertInertia(fn (Assert $page) => $page->has('shifts', 2));

        $this->actingAs($this->autres)->get("/shifts/{$this->shift->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Shifts/Show')
                ->has('positions', 1)
                ->where('servantsDisponibles', [])
                ->where('postesDisponibles', []));

        $this->actingAs($this->autres)->get("/servants/{$this->servant->id}")
            ->assertInertia(fn (Assert $page) => $page->component('Servants/Show')->where('compte', null));
    }

    public function test_isolation_par_organisation(): void
    {
        $autreOrganisation = Organisation::factory()->create();
        $shiftEtranger = $this->makeShift($autreOrganisation, 'Shift Étranger');
        $servantEtranger = Servant::factory()->create(['organisation_id' => $autreOrganisation->id, 'genre' => 'homme']);
        $this->makeDemande('releve', $shiftEtranger, null, $servantEtranger);

        $this->actingAs($this->autres)->get("/shifts/{$shiftEtranger->id}")->assertForbidden();
        $this->actingAs($this->autres)->get("/servants/{$servantEtranger->id}")->assertForbidden();
        $this->actingAs($this->autres)->get("/servants/{$servantEtranger->id}/photo")->assertForbidden();

        $this->actingAs($this->autres)->get('/transferts')
            ->assertInertia(fn (Assert $page) => $page->has('demandes.data', 2));
        $this->actingAs($this->autres)->get('/servants')
            ->assertInertia(fn (Assert $page) => $page->has('servants', 1));
        $this->actingAs($this->autres)->get('/shifts')
            ->assertInertia(fn (Assert $page) => $page->has('shifts.data', 2));
        $this->actingAs($this->autres)->get('/recrutement')
            ->assertInertia(fn (Assert $page) => $page->has('shifts', 2));
    }

    public function test_pages_interdites(): void
    {
        $pieu = Pieu::create(['organisation_id' => $this->organisation->id, 'nom' => 'Pieu Test']);
        $horaire = Horaire::create(['organisation_id' => $this->organisation->id, 'nom' => 'Matin', 'heure_debut' => '07:00', 'heure_fin' => '11:00']);

        $pages = [
            '/parametres',
            '/parametres/journal',
            '/parametres/pieux',
            '/parametres/horaires',
            '/parametres/utilisateurs',
            '/parametres/roles',
            '/parametres/parcours',
            '/manuel',
            '/owner/licences',
            '/shift-templates',
            '/shift-templates/create',
            "/shift-templates/{$this->template->id}",
            "/shift-templates/{$this->template->id}/edit",
            "/shifts/{$this->shift->id}/edit",
            '/servants/create',
            "/servants/{$this->servant->id}/edit",
            "/servants/{$this->servant->id}/export",
            "/mon-shift/{$this->shift->id}",
            "/mes-servants/{$this->servant->id}",
        ];

        foreach ($pages as $page) {
            $this->actingAs($this->autres)->get($page)->assertForbidden();
        }

        $this->assertNotNull($pieu);
        $this->assertNotNull($horaire);
    }

    public function test_aucune_route_decriture_nest_ouverte(): void
    {
        $etape = WorkflowStep::firstOrCreate(['cle' => 'entretien'], ['nom' => 'Entretien', 'ordre' => 1]);
        $etapeServant = ServantWorkflowStep::create([
            'servant_id' => $this->servant->id,
            'workflow_step_id' => $etape->id,
            'statut' => 'en_attente',
        ]);
        $membre = ShiftMember::create([
            'shift_id' => $this->shift->id,
            'user_id' => $this->admin->id,
            'role_id' => $this->role('coordonnateur_equipe')->id,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);
        $pieu = Pieu::create(['organisation_id' => $this->organisation->id, 'nom' => 'Pieu Test']);
        $horaire = Horaire::create(['organisation_id' => $this->organisation->id, 'nom' => 'Matin', 'heure_debut' => '07:00', 'heure_fin' => '11:00']);
        $templatePosition = $this->template->positions()->first();
        $role = Role::where('slug', 'secretaire')->firstOrFail();
        $sid = $this->servant->id;
        $shid = $this->shift->id;

        $routes = [
            // Servants
            ['post', '/servants', ['nom' => 'X', 'prenom' => 'Y']],
            ['put', "/servants/{$sid}", ['nom' => 'X', 'prenom' => 'Y', 'statut' => 'actif']],
            ['delete', "/servants/{$sid}", []],
            ['patch', "/servants/{$sid}/anonymiser", []],
            ['post', "/servants/{$sid}/compte", ['email' => 'x@example.com', 'password' => 'Password123!']],
            ['delete', "/servants/{$sid}/compte", []],
            ['post', "/servants/{$sid}/parcours/demarrer", []],
            ['post', "/servants/{$sid}/parcours", ['workflow_step_id' => $etape->id]],
            ['patch', "/servants/{$sid}/parcours/{$etapeServant->id}", ['statut' => 'termine']],
            ['delete', "/servants/{$sid}/parcours/{$etapeServant->id}", []],
            // Shifts et affectations
            ['put', "/shifts/{$shid}", ['nom' => 'X']],
            ['delete', "/shifts/{$shid}", []],
            ['post', "/shifts/{$shid}/membres", ['user_id' => $this->autres->id]],
            ['delete', "/shifts/{$shid}/membres/{$membre->id}", []],
            ['post', "/shifts/{$shid}/postes", ['shift_template_position_id' => $templatePosition->id, 'servant_id' => $sid]],
            ['delete', "/shifts/{$shid}/postes/{$this->position->id}", []],
            ['post', "/shifts/{$shid}/postes/{$this->position->id}/affectation", ['servant_id' => $sid]],
            ['delete', "/shifts/{$shid}/postes/{$this->position->id}/affectation/{$this->assignment->id}", []],
            // Modèles de shift
            ['post', '/shift-templates', ['nom' => 'X']],
            ['put', "/shift-templates/{$this->template->id}", ['nom' => 'X']],
            ['delete', "/shift-templates/{$this->template->id}", []],
            ['post', "/shift-templates/{$this->template->id}/postes", ['nom' => 'X']],
            // Demandes de changement
            ['post', '/transferts', ['shift_id' => $shid, 'type' => 'releve', 'servant_id' => $sid, 'motif' => 'X', 'date_demande' => now()->toDateString()]],
            ['patch', "/transferts/{$this->releve->id}", ['notes' => 'X']],
            ['patch', "/transferts/{$this->permutation->id}/valider-origine", ['accepte' => true]],
            ['patch', "/transferts/{$this->permutation->id}/valider-destination", ['accepte' => true]],
            ['patch', "/transferts/{$this->releve->id}/resoudre", ['resultat' => 'X', 'resultat_date' => now()->toDateString()]],
            ['delete', "/transferts/{$this->releve->id}", []],
            // Recrutement
            ['put', "/recrutement/{$shid}", ['nombre_a_recruter' => 3]],
            // Configuration
            ['post', '/parametres/pieux', ['nom' => 'X']],
            ['put', "/parametres/pieux/{$pieu->id}", ['nom' => 'X']],
            ['delete', "/parametres/pieux/{$pieu->id}", []],
            ['post', '/parametres/horaires', ['nom' => 'X']],
            ['delete', "/parametres/horaires/{$horaire->id}", []],
            ['post', '/parametres/utilisateurs', ['email' => 'x@example.com']],
            ['delete', "/parametres/utilisateurs/{$this->admin->id}", []],
            ['post', '/parametres/roles', ['nom' => 'X']],
            ['put', "/parametres/roles/{$role->id}", ['nom' => 'X']],
            ['delete', "/parametres/roles/{$role->id}", []],
            ['post', '/parametres/parcours', ['nom' => 'X']],
            // Propriétaire de plateforme
            ['post', '/owner/organisations', ['nom' => 'X']],
            ['patch', "/owner/licences/{$this->organisation->id}", []],
        ];

        foreach ($routes as [$methode, $uri, $donnees]) {
            $this->actingAs($this->autres)->{$methode}($uri, $donnees)->assertForbidden();
        }

        $this->assertDatabaseCount('servants', 1);
        $this->assertSame('en_attente', $this->releve->fresh()->statut);
        $this->assertNull($this->permutation->fresh()->validation_chef_origine);
        $this->assertDatabaseHas('assignments', ['id' => $this->assignment->id, 'statut' => 'actif']);
        $this->assertDatabaseMissing('shift_recruitment_needs', ['shift_id' => $shid]);
    }

    public function test_membre_coordinateur_dun_shift_reste_en_lecture_seule(): void
    {
        // Même inscrit comme coordinateur d'un shift, le rôle « Autres » ne gère rien.
        ShiftMember::create([
            'shift_id' => $this->shift->id,
            'user_id' => $this->autres->id,
            'role_id' => $this->role('coordonnateur_equipe')->id,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        $this->assertTrue($this->autres->shiftsGeres()->isEmpty());
        $this->assertFalse($this->autres->gereDesShifts());

        $this->actingAs($this->autres)
            ->patch("/transferts/{$this->permutation->id}/valider-origine", ['accepte' => true])
            ->assertForbidden();
        $this->actingAs($this->autres)->put("/recrutement/{$this->shift->id}", ['nombre_a_recruter' => 2])->assertForbidden();
        $this->actingAs($this->autres)->get("/mon-shift/{$this->shift->id}")->assertForbidden();
    }

    public function test_le_role_est_protege_et_verrouille_sur_la_page_roles(): void
    {
        $superAdmin = $this->makeUser('super_admin', $this->organisation);
        $role = Role::where('slug', 'autres')->firstOrFail();

        $this->actingAs($superAdmin)->get('/parametres/roles')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where(
                'roles',
                fn ($roles) => collect($roles)->contains(fn ($r) => $r['slug'] === 'autres' && $r['protege'] === true && $r['modifiable'] === false)
                    && collect($roles)->contains(fn ($r) => $r['slug'] === 'secretaire' && $r['modifiable'] === true)
            ));

        $this->actingAs($superAdmin)->put("/parametres/roles/{$role->id}", ['nom' => 'Renommé', 'gere_shifts' => true])->assertStatus(422);
        $this->actingAs($superAdmin)->delete("/parametres/roles/{$role->id}")->assertStatus(422);

        $role->refresh();
        $this->assertSame('Autres', $role->nom);
        $this->assertFalse((bool) $role->gere_shifts);
    }

    public function test_non_regression_des_autres_roles(): void
    {
        $secretaire = $this->makeUser('secretaire', $this->organisation);
        $coordinateur = $this->makeUser('coordonnateur_equipe', $this->organisation);

        // Administrateur : inchangé.
        foreach (['/shifts', "/shifts/{$this->shift->id}", "/shifts/{$this->shift->id}/edit", '/servants/create', "/servants/{$this->servant->id}/export", '/rapports', '/parametres'] as $page) {
            $this->actingAs($this->admin)->get($page)->assertOk();
        }
        $this->actingAs($this->admin)->get("/shifts/{$this->shift->id}")
            ->assertInertia(fn (Assert $page) => $page->has('servantsDisponibles', 1));

        // Secrétaire : servants + changements, pas de shifts ni de rapports.
        foreach (['/servants', '/servants/nouveaux', '/servants/create', "/servants/{$this->servant->id}", '/transferts', '/transferts/releves'] as $page) {
            $this->actingAs($secretaire)->get($page)->assertOk();
        }
        foreach (['/shifts', '/rapports', '/recrutement', '/parametres'] as $page) {
            $this->actingAs($secretaire)->get($page)->assertForbidden();
        }
        $this->actingAs($secretaire)->get('/transferts')
            ->assertInertia(fn (Assert $page) => $page->where('estAdministrateur', true)->has('servants', 1));

        // Coordonnateur : recrutement + permutations, pas de liste servants/shifts/rapports.
        $this->actingAs($coordinateur)->get('/recrutement')->assertOk();
        $this->actingAs($coordinateur)->get('/transferts')->assertOk();
        $this->actingAs($coordinateur)->get('/transferts/releves')->assertForbidden();
        foreach (['/servants', '/shifts', '/rapports', '/parametres'] as $page) {
            $this->actingAs($coordinateur)->get($page)->assertForbidden();
        }
    }

    public function test_profil_seul_le_mot_de_passe_est_modifiable(): void
    {
        $nom = $this->autres->name;
        $email = $this->autres->email;

        $this->actingAs($this->autres)->get('/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Profile/Edit')->where('auth.lectureSeule', true));

        $this->actingAs($this->autres)
            ->patch('/profile', ['name' => 'Nouveau Nom', 'email' => 'nouveau@example.com'])
            ->assertForbidden();
        $this->autres->refresh();
        $this->assertSame($nom, $this->autres->name);
        $this->assertSame($email, $this->autres->email);

        $this->actingAs($this->autres)
            ->delete('/profile', ['password' => 'password'])
            ->assertForbidden();
        $this->assertNotNull($this->autres->fresh());
        $this->assertAuthenticatedAs($this->autres);

        $this->actingAs($this->autres)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'NouveauMotDePasse123!',
                'password_confirmation' => 'NouveauMotDePasse123!',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');
        $this->assertTrue(Hash::check('NouveauMotDePasse123!', $this->autres->fresh()->password));
    }

    public function test_mot_de_passe_temporaire_modifiable_par_le_role_autres(): void
    {
        $this->autres->update(['must_change_password' => true]);

        $this->actingAs($this->autres)->get('/dashboard')->assertRedirect('/profile');
        $this->actingAs($this->autres)->get('/profile')->assertOk();

        $this->actingAs($this->autres)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'NouveauMotDePasse123!',
                'password_confirmation' => 'NouveauMotDePasse123!',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->autres->refresh();
        $this->assertFalse((bool) $this->autres->must_change_password);
        $this->assertTrue(Hash::check('NouveauMotDePasse123!', $this->autres->password));
        $this->actingAs($this->autres)->get('/dashboard')->assertOk();
    }

    public function test_un_administrateur_peut_toujours_modifier_son_profil(): void
    {
        $this->actingAs($this->admin)->get('/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('auth.lectureSeule', false));

        $this->actingAs($this->admin)
            ->patch('/profile', ['name' => 'Admin Renommé', 'email' => $this->admin->email])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertSame('Admin Renommé', $this->admin->fresh()->name);
    }
}
