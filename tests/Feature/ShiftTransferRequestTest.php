<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Organisation;
use App\Models\Role;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftMember;
use App\Models\ShiftPosition;
use App\Models\ShiftTransferRequest;
use App\Models\User;
use App\Notifications\DemandeTransfertResolue;
use App\Notifications\NouvelleDemandeTransfert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ShiftTransferRequestTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $roleSlug, ?Organisation $organisation = null): User
    {
        $organisation ??= Organisation::factory()->create();
        $role = Role::factory()->create(['slug' => $roleSlug, 'nom' => $roleSlug, 'gere_shifts' => $roleSlug === 'coordonnateur_equipe']);

        return User::factory()->create([
            'organisation_id' => $organisation->id,
            'role_id' => $role->id,
        ]);
    }

    private function makeShift(Organisation $organisation, string $nom = 'Shift Test'): Shift
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

    /**
     * Fait de $user le coordinateur du $shift donné (rôle par shift, indépendant du rôle global).
     */
    private function rendreCoordinateur(User $user, Shift $shift): void
    {
        $coordinateurRole = Role::firstOrCreate(['slug' => 'coordonnateur_equipe'], ['nom' => 'coordonnateur_equipe', 'gere_shifts' => true]);

        ShiftMember::create([
            'shift_id' => $shift->id,
            'user_id' => $user->id,
            'role_id' => $coordinateurRole->id,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);
    }

    public function test_coordinateur_peut_creer_une_releve_sur_son_shift(): void
    {
        $organisation = Organisation::factory()->create();
        $coordinateur = $this->makeUser('coordonnateur_equipe', $organisation);
        $shift = $this->makeShift($organisation);
        $this->rendreCoordinateur($coordinateur, $shift);
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);

        $response = $this->actingAs($coordinateur)->post('/transferts', [
            'shift_id' => $shift->id,
            'type' => 'releve',
            'servant_id' => $servant->id,
            'motif' => 'Absence prolongée',
            'date_demande' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shift_transfer_requests', [
            'shift_id' => $shift->id,
            'type' => 'releve',
            'servant_id' => $servant->id,
            'demandeur_id' => $coordinateur->id,
            'statut' => 'en_attente',
        ]);
    }

    public function test_coordinateur_ne_peut_pas_creer_de_demande_sur_un_shift_quil_ne_gere_pas(): void
    {
        $organisation = Organisation::factory()->create();
        $coordinateur = $this->makeUser('coordonnateur_equipe', $organisation);
        $shiftGere = $this->makeShift($organisation, 'Shift géré');
        $this->rendreCoordinateur($coordinateur, $shiftGere);
        $autreShift = $this->makeShift($organisation, 'Shift non géré');
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);

        $response = $this->actingAs($coordinateur)->post('/transferts', [
            'shift_id' => $autreShift->id,
            'type' => 'releve',
            'servant_id' => $servant->id,
            'motif' => 'Test',
            'date_demande' => now()->toDateString(),
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('shift_transfer_requests', ['shift_id' => $autreShift->id]);
    }

    public function test_administrateur_peut_creer_une_permutation_entre_deux_shifts(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeUser('administrateur', $organisation);
        $shiftOrigine = $this->makeShift($organisation, 'Shift Origine');
        $shiftDestination = $this->makeShift($organisation, 'Shift Destination');
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id, 'genre' => 'homme']);

        $response = $this->actingAs($admin)->post('/transferts', [
            'shift_id' => $shiftOrigine->id,
            'shift_destination_id' => $shiftDestination->id,
            'type' => 'permutation',
            'servant_id' => $servant->id,
            'motif' => 'Déménagement',
            'date_demande' => now()->toDateString(),
            'approuve_deux_shifts' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shift_transfer_requests', [
            'shift_id' => $shiftOrigine->id,
            'shift_destination_id' => $shiftDestination->id,
            'type' => 'permutation',
            'approuve_deux_shifts' => 1,
        ]);
    }

    public function test_impossible_de_creer_une_permutation_vers_un_shift_du_mauvais_genre(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeUser('administrateur', $organisation);
        $shiftOrigine = $this->makeShift($organisation, 'Shift Origine');
        $shiftDestination = $this->makeShift($organisation, 'Mardi Matin Sœurs');
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id, 'genre' => 'homme']);

        $this->actingAs($admin)->post('/transferts', [
            'shift_id' => $shiftOrigine->id,
            'shift_destination_id' => $shiftDestination->id,
            'type' => 'permutation',
            'servant_id' => $servant->id,
            'motif' => 'Déménagement',
            'date_demande' => now()->toDateString(),
        ])->assertStatus(422);

        $this->assertDatabaseMissing('shift_transfer_requests', [
            'shift_id' => $shiftOrigine->id,
            'shift_destination_id' => $shiftDestination->id,
        ]);
    }

    public function test_coordinateur_ne_peut_pas_resoudre_une_demande(): void
    {
        $organisation = Organisation::factory()->create();
        $coordinateur = $this->makeUser('coordonnateur_equipe', $organisation);
        $shift = $this->makeShift($organisation);
        $this->rendreCoordinateur($coordinateur, $shift);
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);

        $demande = ShiftTransferRequest::create([
            'organisation_id' => $organisation->id,
            'type' => 'releve',
            'shift_id' => $shift->id,
            'servant_id' => $servant->id,
            'demandeur_id' => $coordinateur->id,
            'motif' => 'Test',
            'date_demande' => now()->toDateString(),
            'statut' => 'en_attente',
        ]);

        $this->actingAs($coordinateur)->patch("/transferts/{$demande->id}/resoudre", [
            'resultat' => 'Accepté',
            'resultat_date' => now()->toDateString(),
        ])->assertForbidden();

        $this->assertDatabaseHas('shift_transfer_requests', ['id' => $demande->id, 'statut' => 'en_attente']);
    }

    public function test_administrateur_peut_resoudre_une_demande(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeUser('administrateur', $organisation);
        $shift = $this->makeShift($organisation);
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);

        $demande = ShiftTransferRequest::create([
            'organisation_id' => $organisation->id,
            'type' => 'releve',
            'shift_id' => $shift->id,
            'servant_id' => $servant->id,
            'demandeur_id' => $admin->id,
            'motif' => 'Test',
            'date_demande' => now()->toDateString(),
            'statut' => 'en_attente',
        ]);

        $this->actingAs($admin)->patch("/transferts/{$demande->id}/resoudre", [
            'resultat' => 'Accepté, remplaçant trouvé',
            'resultat_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('shift_transfer_requests', [
            'id' => $demande->id,
            'statut' => 'traitee',
            'decideur_id' => $admin->id,
        ]);
    }

    public function test_administrateur_ne_peut_pas_resoudre_une_demande_dune_autre_organisation(): void
    {
        $admin = $this->makeUser('administrateur');
        $autreOrganisation = Organisation::factory()->create();
        $shiftAutreOrg = $this->makeShift($autreOrganisation);
        $servantAutreOrg = Servant::factory()->create(['organisation_id' => $autreOrganisation->id]);
        $autreAdmin = User::factory()->create([
            'organisation_id' => $autreOrganisation->id,
            'role_id' => Role::where('slug', 'administrateur')->first()->id,
        ]);

        $demandeAutreOrg = ShiftTransferRequest::create([
            'organisation_id' => $autreOrganisation->id,
            'type' => 'releve',
            'shift_id' => $shiftAutreOrg->id,
            'servant_id' => $servantAutreOrg->id,
            'demandeur_id' => $autreAdmin->id,
            'motif' => 'Test',
            'date_demande' => now()->toDateString(),
            'statut' => 'en_attente',
        ]);

        $this->actingAs($admin)->patch("/transferts/{$demandeAutreOrg->id}/resoudre", [
            'resultat' => 'Accepté',
            'resultat_date' => now()->toDateString(),
        ])->assertForbidden();
    }

    public function test_creer_une_demande_notifie_les_administrateurs_de_lorganisation(): void
    {
        Notification::fake();

        $organisation = Organisation::factory()->create();
        $coordinateur = $this->makeUser('coordonnateur_equipe', $organisation);
        $shift = $this->makeShift($organisation);
        $this->rendreCoordinateur($coordinateur, $shift);
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);
        $admin = $this->makeUser('administrateur', $organisation);

        $this->actingAs($coordinateur)->post('/transferts', [
            'shift_id' => $shift->id,
            'type' => 'releve',
            'servant_id' => $servant->id,
            'motif' => 'Absence prolongée',
            'date_demande' => now()->toDateString(),
        ]);

        Notification::assertSentTo($admin, NouvelleDemandeTransfert::class);
        Notification::assertNotSentTo($coordinateur, NouvelleDemandeTransfert::class);
    }

    public function test_resoudre_une_demande_notifie_le_demandeur(): void
    {
        Notification::fake();

        $organisation = Organisation::factory()->create();
        $admin = $this->makeUser('administrateur', $organisation);
        $coordinateur = $this->makeUser('coordonnateur_equipe', $organisation);
        $shift = $this->makeShift($organisation);
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);

        $demande = ShiftTransferRequest::create([
            'organisation_id' => $organisation->id,
            'type' => 'releve',
            'shift_id' => $shift->id,
            'servant_id' => $servant->id,
            'demandeur_id' => $coordinateur->id,
            'motif' => 'Test',
            'date_demande' => now()->toDateString(),
            'statut' => 'en_attente',
        ]);

        $this->actingAs($admin)->patch("/transferts/{$demande->id}/resoudre", [
            'resultat' => 'Accepté',
            'resultat_date' => now()->toDateString(),
        ]);

        Notification::assertSentTo($coordinateur, DemandeTransfertResolue::class);
    }

    public function test_coordinateur_ne_voit_que_les_demandes_de_son_shift_dans_lhistorique(): void
    {
        $organisation = Organisation::factory()->create();
        $coordinateur = $this->makeUser('coordonnateur_equipe', $organisation);
        $shiftGere = $this->makeShift($organisation, 'Shift géré');
        $this->rendreCoordinateur($coordinateur, $shiftGere);
        $autreShift = $this->makeShift($organisation, 'Shift non géré');
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);

        ShiftTransferRequest::create([
            'organisation_id' => $organisation->id, 'type' => 'releve', 'shift_id' => $shiftGere->id,
            'servant_id' => $servant->id, 'demandeur_id' => $coordinateur->id, 'motif' => 'Test',
            'date_demande' => now()->toDateString(), 'statut' => 'en_attente',
        ]);
        ShiftTransferRequest::create([
            'organisation_id' => $organisation->id, 'type' => 'releve', 'shift_id' => $autreShift->id,
            'servant_id' => $servant->id, 'demandeur_id' => $coordinateur->id, 'motif' => 'Test',
            'date_demande' => now()->toDateString(), 'statut' => 'en_attente',
        ]);

        $response = $this->actingAs($coordinateur)->get('/transferts');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('ShiftTransfers/Index')
            ->has('demandes.data', 1)
        );
    }

    public function test_resoudre_une_releve_termine_laffectation_active_du_servant(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeUser('administrateur', $organisation);
        $shift = $this->makeShift($organisation);
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);
        $position = ShiftPosition::create(['shift_id' => $shift->id, 'nom' => 'Poste', 'ordre' => 1]);
        $assignment = Assignment::create([
            'shift_position_id' => $position->id,
            'servant_id' => $servant->id,
            'date_debut' => now()->subMonths(2)->toDateString(),
            'statut' => 'actif',
        ]);

        $demande = ShiftTransferRequest::create([
            'organisation_id' => $organisation->id,
            'type' => 'releve',
            'shift_id' => $shift->id,
            'servant_id' => $servant->id,
            'demandeur_id' => $admin->id,
            'motif' => 'Test',
            'date_demande' => now()->toDateString(),
            'statut' => 'en_attente',
        ]);

        $this->actingAs($admin)->patch("/transferts/{$demande->id}/resoudre", [
            'resultat' => 'Relevé avec succès',
            'resultat_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('assignments', ['id' => $assignment->id, 'statut' => 'termine']);
    }

    public function test_resoudre_un_appel_favorable_affecte_le_servant_au_poste_choisi(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeUser('administrateur', $organisation);
        $shift = $this->makeShift($organisation);
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id, 'genre' => 'homme']);
        $position = ShiftPosition::create(['shift_id' => $shift->id, 'nom' => 'Poste appelé', 'ordre' => 1]);

        $demande = ShiftTransferRequest::create([
            'organisation_id' => $organisation->id,
            'type' => 'appel',
            'shift_id' => $shift->id,
            'servant_id' => $servant->id,
            'demandeur_id' => $admin->id,
            'motif' => 'Test appel',
            'date_demande' => now()->toDateString(),
            'statut' => 'en_attente',
        ]);

        $this->actingAs($admin)->patch("/transferts/{$demande->id}/resoudre", [
            'resultat' => 'Appel accepté',
            'resultat_date' => now()->toDateString(),
            'favorable' => true,
            'shift_position_destination_id' => $position->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('assignments', [
            'shift_position_id' => $position->id,
            'servant_id' => $servant->id,
            'statut' => 'actif',
        ]);
    }

    public function test_resoudre_un_appel_defavorable_ne_cree_aucune_affectation(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeUser('administrateur', $organisation);
        $shift = $this->makeShift($organisation);
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);

        $demande = ShiftTransferRequest::create([
            'organisation_id' => $organisation->id,
            'type' => 'appel',
            'shift_id' => $shift->id,
            'servant_id' => $servant->id,
            'demandeur_id' => $admin->id,
            'motif' => 'Test appel',
            'date_demande' => now()->toDateString(),
            'statut' => 'en_attente',
        ]);

        $this->actingAs($admin)->patch("/transferts/{$demande->id}/resoudre", [
            'resultat' => 'Appel décliné',
            'resultat_date' => now()->toDateString(),
            'favorable' => false,
        ])->assertRedirect();

        $this->assertDatabaseMissing('assignments', ['servant_id' => $servant->id]);
    }

    public function test_les_demandes_traitees_napparaissent_plus_sur_la_page_transferts(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeUser('administrateur', $organisation);
        $shift = $this->makeShift($organisation);
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);

        ShiftTransferRequest::create([
            'organisation_id' => $organisation->id, 'type' => 'releve', 'shift_id' => $shift->id,
            'servant_id' => $servant->id, 'demandeur_id' => $admin->id, 'motif' => 'Traitée',
            'date_demande' => now()->toDateString(), 'statut' => 'traitee',
            'resultat' => 'Ok', 'resultat_date' => now()->toDateString(),
        ]);
        ShiftTransferRequest::create([
            'organisation_id' => $organisation->id, 'type' => 'releve', 'shift_id' => $shift->id,
            'servant_id' => $servant->id, 'demandeur_id' => $admin->id, 'motif' => 'En attente',
            'date_demande' => now()->toDateString(), 'statut' => 'en_attente',
        ]);

        $this->actingAs($admin)->get('/transferts')->assertInertia(fn ($page) => $page
            ->component('ShiftTransfers/Index')
            ->has('demandes.data', 1)
            ->where('demandes.data.0.motif', 'En attente')
        );
    }

    public function test_page_des_servants_releves_liste_les_relevees_traitees(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeUser('administrateur', $organisation);
        $shift = $this->makeShift($organisation);
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);

        ShiftTransferRequest::create([
            'organisation_id' => $organisation->id, 'type' => 'releve', 'shift_id' => $shift->id,
            'servant_id' => $servant->id, 'demandeur_id' => $admin->id, 'motif' => 'Relève test',
            'date_demande' => now()->toDateString(), 'statut' => 'traitee',
            'resultat' => 'Relevé', 'resultat_date' => now()->toDateString(),
        ]);

        $this->actingAs($admin)->get('/transferts/releves')->assertInertia(fn ($page) => $page
            ->component('ShiftTransfers/Releves')
            ->has('releves.data', 1)
        );
    }
}
