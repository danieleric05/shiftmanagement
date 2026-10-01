<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Organisation;
use App\Models\Pieu;
use App\Models\Role;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftMember;
use App\Models\ShiftPosition;
use App\Models\ShiftTransferRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Parcours métier de bout en bout, rôle par rôle : chaque test enchaîne les
 * actions réelles d'un utilisateur (requêtes HTTP successives) et vérifie à
 * la fois les réponses et l'état de la base.
 */
class ParcoursMetierTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $organisation;

    private Shift $shiftOrigine;

    private Shift $shiftDestination;

    private Pieu $pieu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = Organisation::factory()->create();
        $this->shiftOrigine = $this->makeShift($this->organisation, 'Mardi Matin Frères');
        $this->shiftDestination = $this->makeShift($this->organisation, 'Jeudi Soir Frères');
        $this->pieu = Pieu::create(['organisation_id' => $this->organisation->id, 'nom' => 'Pieu A']);
    }

    // ------------------------------------------------------------------
    // Helpers (repris de SecretaireAccessTest / CoordonnateurTransfertsTest /
    // ShiftTransferRequestTest)
    // ------------------------------------------------------------------

    private function makeUser(string $roleSlug, ?Organisation $organisation = null): User
    {
        $role = Role::firstOrCreate(['slug' => $roleSlug], ['nom' => $roleSlug]);
        if ($roleSlug === 'coordonnateur_equipe' && ! $role->gere_shifts) {
            $role->update(['gere_shifts' => true]);
        }

        return User::factory()->create([
            'organisation_id' => ($organisation ?? $this->organisation)->id,
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

    private function makeCoordonnateur(Shift $shift): User
    {
        $coordonnateur = $this->makeUser('coordonnateur_equipe', $shift->organisation);

        ShiftMember::create([
            'shift_id' => $shift->id,
            'user_id' => $coordonnateur->id,
            'role_id' => $coordonnateur->role_id,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        return $coordonnateur;
    }

    /**
     * Crée un servant avec une affectation active sur un poste du shift donné.
     */
    private function makeServantAffecte(Shift $shift, string $poste = 'Poste'): Servant
    {
        $servant = Servant::factory()->create(['organisation_id' => $shift->organisation_id, 'genre' => 'homme']);
        $position = ShiftPosition::create(['shift_id' => $shift->id, 'nom' => $poste, 'ordre' => 1]);
        Assignment::create([
            'shift_position_id' => $position->id,
            'servant_id' => $servant->id,
            'date_debut' => now()->subMonth()->toDateString(),
            'statut' => 'actif',
        ]);

        return $servant;
    }

    private function permutationPayload(Servant $servant): array
    {
        return [
            'shift_id' => $this->shiftOrigine->id,
            'shift_destination_id' => $this->shiftDestination->id,
            'type' => 'permutation',
            'servant_id' => $servant->id,
            'motif' => 'Changement de disponibilités',
            'date_demande' => now()->toDateString(),
        ];
    }

    /**
     * Enchaîne : création de la permutation par le coordonnateur d'origine,
     * validation origine, puis validation destination par le coordonnateur
     * du shift de destination. Retourne la demande prête pour la décision finale.
     */
    private function permutationDoublementValidee(Servant $servant): ShiftTransferRequest
    {
        $coordOrigine = $this->makeCoordonnateur($this->shiftOrigine);
        $coordDestination = $this->makeCoordonnateur($this->shiftDestination);

        $this->actingAs($coordOrigine)->post('/transferts', $this->permutationPayload($servant))->assertRedirect();
        $demande = ShiftTransferRequest::where('servant_id', $servant->id)->where('type', 'permutation')->firstOrFail();

        $this->actingAs($coordOrigine)->patch("/transferts/{$demande->id}/valider-origine", ['accepte' => true])->assertRedirect();
        $this->actingAs($coordDestination)->patch("/transferts/{$demande->id}/valider-destination", ['accepte' => true])->assertRedirect();

        $demande->refresh();
        $this->assertTrue($demande->validationsChefsCompletes());

        return $demande;
    }

    /**
     * Décision finale favorable sur la permutation, puis vérification que le
     * servant a bien quitté le shift d'origine pour le poste de destination.
     */
    private function rendreDecisionFinale(User $decideur, ShiftTransferRequest $demande, Servant $servant): void
    {
        $posteDestination = ShiftPosition::create(['shift_id' => $this->shiftDestination->id, 'nom' => 'Poste destination', 'ordre' => 1]);
        $affectationOrigine = Assignment::where('servant_id', $servant->id)->where('statut', 'actif')->firstOrFail();

        $this->actingAs($decideur)->patch("/transferts/{$demande->id}/resoudre", [
            'resultat' => 'Permutation accordée',
            'resultat_date' => now()->toDateString(),
            'favorable' => true,
            'shift_position_destination_id' => $posteDestination->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('shift_transfer_requests', [
            'id' => $demande->id,
            'statut' => 'traitee',
            'resultat' => 'Permutation accordée',
            'favorable' => true,
            'decideur_id' => $decideur->id,
        ]);
        $this->assertDatabaseHas('assignments', ['id' => $affectationOrigine->id, 'statut' => 'termine']);
        $this->assertDatabaseHas('assignments', [
            'shift_position_id' => $posteDestination->id,
            'servant_id' => $servant->id,
            'statut' => 'actif',
        ]);
    }

    // ------------------------------------------------------------------
    // 1. Secrétaire
    // ------------------------------------------------------------------

    public function test_parcours_secretaire(): void
    {
        $secretaire = $this->makeUser('secretaire');

        // Connexion → tableau de bord → redirigée vers la gestion des servants.
        $this->post('/login', ['email' => $secretaire->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($secretaire);
        $this->get('/dashboard')->assertRedirect(route('servants.index'));
        $this->get('/servants')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Servants/Index'));

        // Création d'un servant (statut « recommandé » par défaut).
        $this->post('/servants', [
            'nom' => 'Kouassi',
            'prenom' => 'Paul',
            'genre' => 'homme',
            'pieu_id' => $this->pieu->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $servant = Servant::where('nom', 'Kouassi')->firstOrFail();
        $this->assertSame($this->organisation->id, $servant->organisation_id);
        $this->assertSame('recommande', $servant->statut);
        $this->assertSame($this->pieu->id, $servant->pieu_id);

        // Il apparaît dans la vue « Nouveaux ».
        $this->get('/servants/nouveaux')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Servants/Index')
            ->where('nouveaux', true)
            ->has('servants', 1)
            ->where('servants.0.id', $servant->id));

        // Modification de la fiche : passage en formation → sort des « Nouveaux ».
        $this->put("/servants/{$servant->id}", [
            'nom' => 'Kouassi',
            'prenom' => 'Paul-Henri',
            'genre' => 'homme',
            'statut' => 'en_formation',
            'telephone' => '0102030405',
            'pieu_id' => $this->pieu->id,
        ])->assertRedirect(route('servants.show', $servant))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('servants', [
            'id' => $servant->id,
            'prenom' => 'Paul-Henri',
            'statut' => 'en_formation',
            'telephone' => '0102030405',
        ]);
        $this->get('/servants/nouveaux')->assertInertia(fn (Assert $page) => $page->has('servants', 0));

        // Relève : enregistrement puis résolution.
        $this->post('/transferts', [
            'shift_id' => $this->shiftOrigine->id,
            'type' => 'releve',
            'servant_id' => $servant->id,
            'motif' => 'Déménagement',
            'date_demande' => now()->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $releve = ShiftTransferRequest::where('servant_id', $servant->id)->where('type', 'releve')->firstOrFail();
        $this->assertSame('en_attente', $releve->statut);
        $this->assertSame($secretaire->id, $releve->demandeur_id);

        $this->patch("/transferts/{$releve->id}/resoudre", [
            'resultat' => 'Relève accordée',
            'resultat_date' => now()->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('shift_transfer_requests', [
            'id' => $releve->id,
            'statut' => 'traitee',
            'decideur_id' => $secretaire->id,
        ]);
        $this->get('/transferts/releves')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('ShiftTransfers/Releves')
            ->has('releves.data', 1));

        // Appel : enregistrement puis résolution favorable → affecté au poste choisi.
        $this->post('/transferts', [
            'shift_id' => $this->shiftOrigine->id,
            'type' => 'appel',
            'servant_id' => $servant->id,
            'motif' => 'Appel au service',
            'date_demande' => now()->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $appel = ShiftTransferRequest::where('servant_id', $servant->id)->where('type', 'appel')->firstOrFail();
        $this->assertSame('en_attente', $appel->statut);
        $this->assertSame($secretaire->id, $appel->demandeur_id);

        $posteAppel = ShiftPosition::create(['shift_id' => $this->shiftOrigine->id, 'nom' => 'Poste appel', 'ordre' => 1]);
        $this->patch("/transferts/{$appel->id}/resoudre", [
            'resultat' => 'Appel accepté',
            'resultat_date' => now()->toDateString(),
            'favorable' => true,
            'shift_position_destination_id' => $posteAppel->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('shift_transfer_requests', [
            'id' => $appel->id,
            'statut' => 'traitee',
            'favorable' => true,
            'decideur_id' => $secretaire->id,
        ]);
        $this->assertDatabaseHas('assignments', [
            'shift_position_id' => $posteAppel->id,
            'servant_id' => $servant->id,
            'statut' => 'actif',
        ]);

        // Permutation : la secrétaire peut en créer une (validations des coordonnateurs ensuite).
        $this->post('/transferts', [...$this->permutationPayload($servant), 'motif' => 'Permutation secrétaire'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $permutation = ShiftTransferRequest::where('servant_id', $servant->id)->where('type', 'permutation')->firstOrFail();
        $this->assertSame('en_attente', $permutation->statut);
        $this->assertSame($secretaire->id, $permutation->demandeur_id);
        $this->assertSame($this->shiftDestination->id, $permutation->shift_destination_id);
        $this->get('/transferts?type=permutation')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('ShiftTransfers/Index')
            ->has('demandes.data', 1)
            ->where('demandes.data.0.id', $permutation->id));

        // Zones réservées à l'administrateur.
        foreach (['/parametres', '/parametres/roles', '/parametres/utilisateurs', '/parametres/pieux', '/rapports', '/shifts', '/shift-templates'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->delete("/servants/{$servant->id}")->assertForbidden();
        $this->patch("/servants/{$servant->id}/anonymiser")->assertForbidden();
        $this->get("/servants/{$servant->id}/export")->assertForbidden();
        $this->post("/servants/{$servant->id}/compte", ['email' => 'x@example.com', 'password' => 'Password123!'])->assertForbidden();
        $this->assertDatabaseHas('servants', ['id' => $servant->id, 'nom' => 'Kouassi', 'user_id' => null, 'deleted_at' => null]);

        // Pas de pieu d'une autre organisation, ni à la création ni en modification.
        $pieuEtranger = Pieu::create(['organisation_id' => Organisation::factory()->create()->id, 'nom' => 'Pieu B']);
        $this->post('/servants', ['nom' => 'Autre', 'prenom' => 'Jean', 'pieu_id' => $pieuEtranger->id])
            ->assertSessionHasErrors('pieu_id');
        $this->put("/servants/{$servant->id}", [
            'nom' => 'Kouassi', 'prenom' => 'Paul-Henri', 'statut' => 'en_formation', 'pieu_id' => $pieuEtranger->id,
        ])->assertSessionHasErrors('pieu_id');
        $this->assertDatabaseMissing('servants', ['pieu_id' => $pieuEtranger->id]);
    }

    // ------------------------------------------------------------------
    // 2. Coordonnateur d'équipe
    // ------------------------------------------------------------------

    public function test_parcours_coordonnateur(): void
    {
        $coordOrigine = $this->makeCoordonnateur($this->shiftOrigine);
        $coordDestination = $this->makeCoordonnateur($this->shiftDestination);
        $servant = $this->makeServantAffecte($this->shiftOrigine);
        $servantAutreShift = $this->makeServantAffecte($this->shiftDestination, 'Poste B');

        // Dashboard : uniquement les permutations.
        $this->actingAs($coordOrigine)->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/ChefEquipe')
            ->where('permutations.en_attente', 0)
            ->missing('releves')
            ->missing('appels'));

        // Création d'une permutation depuis son shift.
        $this->actingAs($coordOrigine)->post('/transferts', $this->permutationPayload($servant))
            ->assertRedirect()->assertSessionHasNoErrors();
        $demande = ShiftTransferRequest::where('servant_id', $servant->id)->firstOrFail();
        $this->assertSame('permutation', $demande->type);
        $this->assertSame($coordOrigine->id, $demande->demandeur_id);
        $this->assertSame('en_attente', $demande->statut);

        // Visible sur le dashboard de l'origine ET de la destination.
        foreach ([$coordOrigine, $coordDestination] as $coord) {
            $this->actingAs($coord)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/ChefEquipe')
                ->where('permutations.en_attente', 1)
                ->where('permutations.recentes.0.id', $demande->id)
                ->missing('releves')
                ->missing('appels'));
        }

        // Chacun ne valide que son côté.
        $this->actingAs($coordOrigine)->patch("/transferts/{$demande->id}/valider-destination", ['accepte' => true])->assertForbidden();
        $this->actingAs($coordDestination)->patch("/transferts/{$demande->id}/valider-origine", ['accepte' => true])->assertForbidden();

        $this->actingAs($coordOrigine)->patch("/transferts/{$demande->id}/valider-origine", ['accepte' => true])->assertRedirect();
        $this->actingAs($coordDestination)->patch("/transferts/{$demande->id}/valider-destination", ['accepte' => true])->assertRedirect();

        $this->assertDatabaseHas('shift_transfer_requests', [
            'id' => $demande->id,
            'validation_chef_origine' => true,
            'validation_chef_origine_par_id' => $coordOrigine->id,
            'validation_chef_destination' => true,
            'validation_chef_destination_par_id' => $coordDestination->id,
            'statut' => 'en_attente',
        ]);

        // Décision finale interdite aux coordonnateurs.
        foreach ([$coordOrigine, $coordDestination] as $coord) {
            $this->actingAs($coord)->patch("/transferts/{$demande->id}/resoudre", [
                'resultat' => 'OK', 'resultat_date' => now()->toDateString(), 'favorable' => false,
            ])->assertForbidden();
        }
        $this->assertDatabaseHas('shift_transfer_requests', ['id' => $demande->id, 'statut' => 'en_attente', 'decideur_id' => null]);

        // Ni relève, ni appel, ni historique des relèves.
        foreach (['releve', 'appel'] as $type) {
            $this->actingAs($coordOrigine)->post('/transferts', [
                'shift_id' => $this->shiftOrigine->id, 'type' => $type, 'servant_id' => $servant->id,
                'motif' => 'Test', 'date_demande' => now()->toDateString(),
            ])->assertForbidden();
            $this->actingAs($coordOrigine)->get("/transferts?type={$type}")->assertForbidden();
        }
        $this->actingAs($coordOrigine)->get('/transferts/releves')->assertForbidden();
        $this->assertSame(0, ShiftTransferRequest::whereIn('type', ['releve', 'appel'])->count());

        // Fiche d'un servant de SON shift : modifiable.
        $this->actingAs($coordOrigine)->get("/servants/{$servant->id}/edit")->assertOk();
        $this->actingAs($coordOrigine)->put("/servants/{$servant->id}", [
            'nom' => $servant->nom,
            'prenom' => 'Modifié',
            'genre' => 'homme',
            'statut' => $servant->statut,
            'telephone' => '0700000000',
        ])->assertRedirect(route('servants.mine.show', $servant))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('servants', ['id' => $servant->id, 'prenom' => 'Modifié', 'telephone' => '0700000000']);

        // Servant d'un autre shift : 403.
        $this->actingAs($coordOrigine)->get("/servants/{$servantAutreShift->id}/edit")->assertForbidden();
        $this->actingAs($coordOrigine)->put("/servants/{$servantAutreShift->id}", [
            'nom' => $servantAutreShift->nom, 'prenom' => 'Pirate', 'statut' => $servantAutreShift->statut,
        ])->assertForbidden();
        $this->assertDatabaseMissing('servants', ['id' => $servantAutreShift->id, 'prenom' => 'Pirate']);

        // Besoin de recrutement : son shift uniquement.
        $this->actingAs($coordOrigine)->get('/recrutement')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Recruitment/Index')
            ->has('shifts', 1)
            ->where('shifts.0.shift_id', $this->shiftOrigine->id));
        $this->actingAs($coordOrigine)->put("/recrutement/{$this->shiftOrigine->id}", [
            'nombre_a_recruter' => 3, 'echeance' => now()->addMonth()->toDateString(), 'notes' => 'Urgent',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('shift_recruitment_needs', [
            'shift_id' => $this->shiftOrigine->id,
            'nombre_a_recruter' => 3,
            'updated_by' => $coordOrigine->id,
        ]);
        $this->actingAs($coordOrigine)->put("/recrutement/{$this->shiftDestination->id}", ['nombre_a_recruter' => 5])->assertForbidden();
        $this->assertDatabaseMissing('shift_recruitment_needs', ['shift_id' => $this->shiftDestination->id]);
    }

    // ------------------------------------------------------------------
    // 3. Conseil du Temple (administrateur)
    // ------------------------------------------------------------------

    public function test_parcours_conseil_du_temple(): void
    {
        $admin = $this->makeUser('administrateur');
        $servant = $this->makeServantAffecte($this->shiftOrigine);

        // Une décision finale avant les deux validations est refusée.
        $coordOrigine = $this->makeCoordonnateur($this->shiftOrigine);
        $this->actingAs($coordOrigine)->post('/transferts', [...$this->permutationPayload($servant), 'motif' => 'Prématurée'])->assertRedirect();
        $prematuree = ShiftTransferRequest::where('motif', 'Prématurée')->firstOrFail();
        $this->actingAs($admin)->patch("/transferts/{$prematuree->id}/resoudre", [
            'resultat' => 'Trop tôt', 'resultat_date' => now()->toDateString(), 'favorable' => false,
        ])->assertStatus(422);
        $this->assertDatabaseHas('shift_transfer_requests', ['id' => $prematuree->id, 'statut' => 'en_attente']);
        $prematuree->delete();

        // Après les deux validations : décision finale.
        $demande = $this->permutationDoublementValidee($servant);
        $this->rendreDecisionFinale($admin, $demande, $servant);

        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Admin')
            ->has('releves')
            ->has('appels')
            ->where('permutations.en_attente', 0));

        // Pages d'administration accessibles.
        foreach (['/parametres/utilisateurs', '/parametres/pieux', '/rapports', '/shift-templates', '/recrutement', '/shifts', '/servants', '/transferts/releves'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }

        // Gestion des rôles réservée au super admin.
        $this->actingAs($admin)->get('/parametres/roles')->assertForbidden();

        // Impossible d'attribuer le rôle super_admin (création ou modification).
        $superAdminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['nom' => 'Super Administrateur']);
        $this->actingAs($admin)->post('/parametres/utilisateurs', [
            'nom' => 'Escalade', 'prenom' => 'Eve', 'email' => 'eve@example.com',
            'password' => 'Password123!', 'role_id' => $superAdminRole->id,
        ])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'eve@example.com']);

        $secretaire = $this->makeUser('secretaire');
        $this->actingAs($admin)->put("/parametres/utilisateurs/{$secretaire->id}", [
            'nom' => 'Sec', 'prenom' => 'Re', 'role_id' => $superAdminRole->id, 'statut' => 'actif',
        ])->assertForbidden();
        $this->assertNotSame($superAdminRole->id, $secretaire->fresh()->role_id);

        $this->actingAs($admin)->put("/parametres/utilisateurs/{$admin->id}", [
            'nom' => 'Admin', 'prenom' => 'Moi', 'role_id' => $superAdminRole->id, 'statut' => 'actif',
        ])->assertForbidden();
        $this->assertSame('administrateur', $admin->fresh()->role->slug);

        // Mais peut attribuer un rôle ordinaire.
        $coordRole = Role::firstOrCreate(['slug' => 'coordonnateur_equipe'], ['nom' => 'coordonnateur_equipe', 'gere_shifts' => true]);
        $this->actingAs($admin)->post('/parametres/utilisateurs', [
            'nom' => 'Nouveau', 'prenom' => 'Coord', 'email' => 'coord@example.com',
            'password' => 'Password123!', 'role_id' => $coordRole->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'coord@example.com', 'role_id' => $coordRole->id, 'organisation_id' => $this->organisation->id]);
    }

    /**
     * Le Conseil soumet la permutation mais ne valide pas à la place des
     * coordonnateurs : seuls les coordonnateurs des shifts concernés valident,
     * puis le Conseil rend la décision finale.
     */
    public function test_conseil_soumet_mais_seuls_les_coordonnateurs_valident_la_permutation(): void
    {
        $admin = $this->makeUser('administrateur');
        $superAdmin = $this->makeUser('super_admin');
        $secretaire = $this->makeUser('secretaire');
        $coordOrigine = $this->makeCoordonnateur($this->shiftOrigine);
        $coordDestination = $this->makeCoordonnateur($this->shiftDestination);
        $servant = $this->makeServantAffecte($this->shiftOrigine);

        $this->actingAs($admin)->post('/transferts', $this->permutationPayload($servant))
            ->assertRedirect()->assertSessionHasNoErrors();
        $demande = ShiftTransferRequest::where('servant_id', $servant->id)->firstOrFail();
        $this->assertSame($admin->id, $demande->demandeur_id);

        // Ni le Conseil, ni le super admin, ni le secrétaire ne valident à la place des coordonnateurs.
        foreach ([$admin, $superAdmin, $secretaire] as $user) {
            $this->actingAs($user)->patch("/transferts/{$demande->id}/valider-origine", ['accepte' => true])->assertForbidden();
            $this->actingAs($user)->patch("/transferts/{$demande->id}/valider-destination", ['accepte' => true])->assertForbidden();
        }

        $this->actingAs($admin)->get('/transferts')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('demandes.data.0.peut_valider_origine', false)
            ->where('demandes.data.0.peut_valider_destination', false)
            ->where('demandes.data.0.suivi.etat.libelle', 'En attente des coordonnateurs de Mardi Matin Frères et de Jeudi Soir Frères')
            ->has('demandes.data.0.suivi.etapes', 4)
            ->where('demandes.data.0.suivi.etapes.0.cle', 'initiation')
            ->where('demandes.data.0.suivi.etapes.0.libelle', "Initiée par {$admin->name} (administrateur)")
            ->where('demandes.data.0.suivi.etapes.0.detail', 'Le '.now()->format('d/m/Y'))
            ->where('demandes.data.0.suivi.etapes.1.libelle', 'Validation du coordonnateur du shift Mardi Matin Frères')
            ->where('demandes.data.0.suivi.etapes.1.statut', 'en_attente')
            ->where('demandes.data.0.suivi.etapes.2.libelle', 'Validation du coordonnateur du shift Jeudi Soir Frères')
            ->where('demandes.data.0.suivi.etapes.3.cle', 'decision')
            ->where('demandes.data.0.suivi.etapes.3.detail', 'En attente'));

        // Décision finale refusée tant que les deux validations ne sont pas faites.
        $refusPrematuree = fn () => $this->actingAs($admin)->patch("/transferts/{$demande->id}/resoudre", [
            'resultat' => 'Trop tôt', 'resultat_date' => now()->toDateString(), 'favorable' => false,
        ])->assertStatus(422);
        $refusPrematuree();

        $this->actingAs($coordOrigine)->get('/transferts')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('demandes.data.0.peut_valider_origine', true)
            ->where('demandes.data.0.peut_valider_destination', false));
        $this->actingAs($coordOrigine)->patch("/transferts/{$demande->id}/valider-origine", ['accepte' => true])->assertRedirect();
        $this->actingAs($admin)->get('/transferts')->assertInertia(fn (Assert $page) => $page
            ->where('demandes.data.0.suivi.etat.libelle', 'En attente du coordonnateur de Jeudi Soir Frères')
            ->where('demandes.data.0.suivi.etapes.1.statut', 'fait')
            ->where('demandes.data.0.suivi.etapes.1.detail', "Validée par {$coordOrigine->name} le ".now()->format('d/m/Y'))
            ->where('demandes.data.0.suivi.etapes.2.statut', 'en_attente'));
        // Une seule validation par coordonnateur.
        $this->actingAs($coordOrigine)->patch("/transferts/{$demande->id}/valider-origine", ['accepte' => false])->assertStatus(422);
        $refusPrematuree();

        $this->actingAs($coordDestination)->get('/transferts')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('demandes.data.0.peut_valider_origine', false)
            ->where('demandes.data.0.peut_valider_destination', true));
        $this->actingAs($coordDestination)->patch("/transferts/{$demande->id}/valider-destination", ['accepte' => true])->assertRedirect();

        $demande->refresh();
        $this->assertTrue($demande->validationsChefsCompletes());
        $this->assertSame($coordOrigine->id, $demande->validation_chef_origine_par_id);
        $this->assertSame($coordDestination->id, $demande->validation_chef_destination_par_id);
        $this->assertSame('en_attente', $demande->statut);

        $this->actingAs($admin)->get('/transferts')->assertInertia(fn (Assert $page) => $page
            ->where('demandes.data.0.suivi.etat.libelle', 'Prête pour décision du Conseil')
            ->has('demandes.data.0.suivi.etapes', 5)
            ->where('demandes.data.0.suivi.etapes.2.detail', "Validée par {$coordDestination->name} le ".now()->format('d/m/Y'))
            ->where('demandes.data.0.suivi.etapes.3.cle', 'double_validation')
            ->where('demandes.data.0.suivi.etapes.3.libelle', 'Validée par les deux coordonnateurs')
            ->where('demandes.data.0.suivi.etapes.4.statut', 'en_attente'));
        $this->actingAs($admin)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('permutations.recentes.0.suivi_etat.libelle', 'Prête pour décision du Conseil')
            ->where('permutations.recentes.0.pret_pour_decision', true));

        $this->rendreDecisionFinale($admin, $demande, $servant);

        $demande->refresh()->load(['shift', 'shiftDestination', 'demandeur.role', 'decideur', 'validateurOrigine', 'validateurDestination']);
        $suivi = $demande->suiviPermutation();
        $this->assertSame('Tranchée (favorable)', $suivi['etat']['libelle']);
        $this->assertSame('fait', $suivi['etapes'][4]['statut']);
        $this->assertSame("Favorable — Permutation accordée par {$admin->name} le ".now()->format('d/m/Y'), $suivi['etapes'][4]['detail']);
        $this->actingAs($admin)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('permutations.recentes.0.suivi_etat.libelle', 'Tranchée (favorable)')
            ->where('permutations.recentes.0.pret_pour_decision', false));
    }

    /**
     * Exception : un membre du Conseil qui coordonne lui-même un shift
     * (rôle gere_shifts sur ce shift) valide pour ce shift-là seulement.
     */
    public function test_conseil_coordonnateur_dun_shift_valide_uniquement_ce_shift(): void
    {
        $admin = $this->makeUser('administrateur');
        $coordRole = Role::firstOrCreate(['slug' => 'coordonnateur_equipe'], ['nom' => 'coordonnateur_equipe', 'gere_shifts' => true]);
        $coordRole->update(['gere_shifts' => true]);
        ShiftMember::create([
            'shift_id' => $this->shiftOrigine->id,
            'user_id' => $admin->id,
            'role_id' => $coordRole->id,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);
        $servant = $this->makeServantAffecte($this->shiftOrigine);

        $this->actingAs($admin)->post('/transferts', $this->permutationPayload($servant))->assertRedirect();
        $demande = ShiftTransferRequest::where('servant_id', $servant->id)->firstOrFail();

        $this->actingAs($admin)->patch("/transferts/{$demande->id}/valider-destination", ['accepte' => true])->assertForbidden();
        $this->actingAs($admin)->patch("/transferts/{$demande->id}/valider-origine", ['accepte' => true])->assertRedirect();
        $this->assertTrue($demande->fresh()->validation_chef_origine);
    }

    // ------------------------------------------------------------------
    // 4. Super administrateur
    // ------------------------------------------------------------------

    public function test_parcours_super_admin(): void
    {
        $superAdmin = $this->makeUser('super_admin');
        $servant = $this->makeServantAffecte($this->shiftOrigine);

        $this->actingAs($superAdmin)->get('/parametres/roles')->assertOk();

        // Tout ce que fait le Conseil : décision finale sur une permutation validée.
        $demande = $this->permutationDoublementValidee($servant);
        $this->rendreDecisionFinale($superAdmin, $demande, $servant);

        foreach (['/dashboard', '/parametres/utilisateurs', '/parametres/pieux', '/rapports', '/shift-templates', '/recrutement', '/shifts', '/servants', '/servants/nouveaux', '/transferts', '/transferts/releves'] as $url) {
            $this->actingAs($superAdmin)->get($url)->assertOk();
        }

        // ... et en plus, attribuer le rôle super_admin.
        $superAdminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['nom' => 'Super Administrateur']);
        $this->actingAs($superAdmin)->post('/parametres/utilisateurs', [
            'nom' => 'Second', 'prenom' => 'Super', 'email' => 'super2@example.com',
            'password' => 'Password123!', 'role_id' => $superAdminRole->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'super2@example.com', 'role_id' => $superAdminRole->id]);

        // Gestion des servants réservée à l'administrateur : export/anonymisation.
        $this->actingAs($superAdmin)->get("/servants/{$servant->id}/export")->assertOk();
    }

    // ------------------------------------------------------------------
    // 5. Utilisateur sans rôle / servant connecté
    // ------------------------------------------------------------------

    public function test_parcours_utilisateur_sans_role_et_servant_connecte(): void
    {
        $sansRole = User::factory()->create(['organisation_id' => $this->organisation->id, 'role_id' => null]);

        $this->actingAs($sansRole)->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Servant')
            ->where('servant', null)
            ->has('affectations', 0));

        $compte = User::factory()->create(['organisation_id' => $this->organisation->id, 'role_id' => null]);
        $servant = $this->makeServantAffecte($this->shiftOrigine);
        $servant->update(['user_id' => $compte->id]);

        $this->actingAs($compte)->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Servant')
            ->where('servant.nom_complet', $servant->nomComplet())
            ->has('affectations', 1)
            ->where('affectations.0.shift', $this->shiftOrigine->nom));

        $urls = [
            '/servants', '/servants/nouveaux', '/servants/create', "/servants/{$servant->id}", "/servants/{$servant->id}/edit",
            "/mes-servants/{$servant->id}", "/mon-shift/{$this->shiftOrigine->id}", '/transferts', '/transferts/releves',
            '/recrutement', '/shifts', "/shifts/{$this->shiftOrigine->id}", '/shift-templates', '/rapports',
            '/parametres', '/parametres/utilisateurs', '/parametres/roles', '/parametres/pieux',
        ];

        foreach ([$sansRole, $compte] as $user) {
            foreach ($urls as $url) {
                $this->actingAs($user)->get($url)->assertForbidden();
            }

            $this->actingAs($user)->post('/transferts', [
                'shift_id' => $this->shiftOrigine->id, 'type' => 'releve', 'servant_id' => $servant->id,
                'motif' => 'Auto-relève', 'date_demande' => now()->toDateString(),
            ])->assertForbidden();
            $this->actingAs($user)->put("/servants/{$servant->id}", [
                'nom' => $servant->nom, 'prenom' => 'Moi', 'statut' => 'actif',
            ])->assertForbidden();
            $this->actingAs($user)->post('/servants', ['nom' => 'X', 'prenom' => 'Y'])->assertForbidden();
            $this->actingAs($user)->put("/recrutement/{$this->shiftOrigine->id}", ['nombre_a_recruter' => 9])->assertForbidden();
        }

        $this->assertDatabaseCount('shift_transfer_requests', 0);
        $this->assertDatabaseCount('shift_recruitment_needs', 0);
        $this->assertDatabaseMissing('servants', ['id' => $servant->id, 'prenom' => 'Moi']);
    }

    // ------------------------------------------------------------------
    // 6. Isolation entre organisations
    // ------------------------------------------------------------------

    public function test_isolation_entre_organisations(): void
    {
        // Ressources de l'organisation A.
        $adminA = $this->makeUser('administrateur');
        $servantA = $this->makeServantAffecte($this->shiftOrigine);
        $servantA->update(['pieu_id' => $this->pieu->id]);
        $demandeA = ShiftTransferRequest::create([
            'organisation_id' => $this->organisation->id,
            'type' => 'permutation',
            'shift_id' => $this->shiftOrigine->id,
            'shift_destination_id' => $this->shiftDestination->id,
            'servant_id' => $servantA->id,
            'demandeur_id' => $adminA->id,
            'motif' => 'Org A',
            'date_demande' => now()->toDateString(),
            'statut' => 'en_attente',
        ]);

        // Utilisateurs de l'organisation B, avec chacun un rôle élevé chez eux.
        $organisationB = Organisation::factory()->create();
        $shiftB = $this->makeShift($organisationB, 'Shift B');
        $servantB = Servant::factory()->create(['organisation_id' => $organisationB->id, 'genre' => 'homme']);
        $coordB = $this->makeCoordonnateur($shiftB);
        $utilisateursB = [
            'administrateur' => $this->makeUser('administrateur', $organisationB),
            'super_admin' => $this->makeUser('super_admin', $organisationB),
            'secretaire' => $this->makeUser('secretaire', $organisationB),
            'coordonnateur_equipe' => $coordB,
        ];

        $refuse = fn ($response) => $this->assertContains($response->status(), [403, 404], 'Statut inattendu : '.$response->status());

        foreach ($utilisateursB as $slug => $userB) {
            $this->actingAs($userB);

            // Lectures.
            foreach ([
                "/servants/{$servantA->id}", "/servants/{$servantA->id}/edit", "/servants/{$servantA->id}/export",
                "/servants/{$servantA->id}/photo", "/mes-servants/{$servantA->id}",
                "/shifts/{$this->shiftOrigine->id}", "/mon-shift/{$this->shiftOrigine->id}",
            ] as $url) {
                $refuse($this->get($url));
            }

            // Écritures.
            $refuse($this->put("/servants/{$servantA->id}", ['nom' => 'Pirate', 'prenom' => 'B', 'statut' => 'actif']));
            $refuse($this->delete("/servants/{$servantA->id}"));
            $refuse($this->patch("/servants/{$servantA->id}/anonymiser"));
            $refuse($this->patch("/transferts/{$demandeA->id}", ['notes' => 'Pirate']));
            $refuse($this->patch("/transferts/{$demandeA->id}/valider-origine", ['accepte' => true]));
            $refuse($this->patch("/transferts/{$demandeA->id}/valider-destination", ['accepte' => true]));
            $refuse($this->patch("/transferts/{$demandeA->id}/resoudre", ['resultat' => 'Pirate', 'resultat_date' => now()->toDateString(), 'favorable' => false]));
            $refuse($this->delete("/transferts/{$demandeA->id}"));
            $refuse($this->put("/recrutement/{$this->shiftOrigine->id}", ['nombre_a_recruter' => 7]));
            $refuse($this->put("/parametres/pieux/{$this->pieu->id}", ['nom' => 'Pirate']));
            $refuse($this->delete("/parametres/pieux/{$this->pieu->id}"));
            $refuse($this->put("/parametres/utilisateurs/{$adminA->id}", ['nom' => 'P', 'prenom' => 'P', 'role_id' => $adminA->role_id, 'statut' => 'suspendu']));
            $refuse($this->delete("/parametres/utilisateurs/{$adminA->id}"));

            // Création d'une demande sur un shift / servant de A.
            $refuse($this->post('/transferts', [
                'shift_id' => $this->shiftOrigine->id, 'shift_destination_id' => $this->shiftDestination->id,
                'type' => 'permutation', 'servant_id' => $servantA->id, 'motif' => 'Pirate', 'date_demande' => now()->toDateString(),
            ]));
            // Shift de B mais servant de A (le coordonnateur est de toute façon exclu des relèves).
            $refuse($this->post('/transferts', [
                'shift_id' => $shiftB->id, 'type' => 'releve', 'servant_id' => $servantA->id,
                'motif' => 'Pirate', 'date_demande' => now()->toDateString(),
            ]));

            // Les listes de B ne contiennent rien de A.
            if ($slug === 'coordonnateur_equipe') {
                // La liste générale des servants lui est fermée.
                $this->get('/servants')->assertForbidden();
            } else {
                $this->get('/servants')->assertOk()->assertInertia(fn (Assert $page) => $page
                    ->has('servants', 1)
                    ->where('servants.0.id', $servantB->id));
            }
            $this->get('/transferts')->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('demandes.data', 0)
                ->where('servants', fn ($servants) => collect($servants)->pluck('id')->doesntContain($servantA->id))
                ->where('shifts', fn ($shifts) => collect($shifts)->pluck('id')->intersect([$this->shiftOrigine->id, $this->shiftDestination->id])->isEmpty()));
        }

        // Rien n'a bougé dans l'organisation A.
        $this->assertDatabaseHas('servants', ['id' => $servantA->id, 'nom' => $servantA->nom, 'deleted_at' => null]);
        $this->assertDatabaseHas('shift_transfer_requests', [
            'id' => $demandeA->id, 'statut' => 'en_attente', 'notes' => null,
            'validation_chef_origine' => null, 'validation_chef_destination' => null, 'deleted_at' => null,
        ]);
        $this->assertDatabaseMissing('shift_transfer_requests', ['motif' => 'Pirate']);
        $this->assertDatabaseMissing('shift_recruitment_needs', ['shift_id' => $this->shiftOrigine->id]);
        $this->assertDatabaseHas('pieux', ['id' => $this->pieu->id, 'nom' => 'Pieu A']);
        $this->assertDatabaseHas('users', ['id' => $adminA->id, 'statut' => 'actif']);
    }
}
