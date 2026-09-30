<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftMember;
use App\Models\ShiftTransferRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Le coordonnateur d'équipe ne gère que les permutations de ses shifts :
 * relèves et appels restent réservés à l'administrateur et au secrétaire.
 */
class CoordonnateurTransfertsTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $organisation;

    private Shift $shift;

    private Shift $shiftDestination;

    private Servant $servant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = Organisation::factory()->create();
        $this->shift = $this->makeShift('Shift Origine');
        $this->shiftDestination = $this->makeShift('Shift Destination');
        $this->servant = Servant::factory()->create(['organisation_id' => $this->organisation->id, 'genre' => 'homme']);
    }

    private function makeUser(string $roleSlug): User
    {
        $role = Role::firstOrCreate(['slug' => $roleSlug], ['nom' => $roleSlug]);
        if ($roleSlug === 'coordonnateur_equipe') {
            $role->update(['gere_shifts' => true]);
        }

        return User::factory()->create([
            'organisation_id' => $this->organisation->id,
            'role_id' => $role->id,
        ]);
    }

    private function makeShift(string $nom): Shift
    {
        return Shift::create([
            'organisation_id' => $this->organisation->id,
            'nom' => $nom,
            'jour' => 'mardi',
            'heure_debut' => '07:00',
            'heure_fin' => '11:00',
            'statut' => 'actif',
        ]);
    }

    private function makeCoordonnateur(): User
    {
        $coordonnateur = $this->makeUser('coordonnateur_equipe');

        ShiftMember::create([
            'shift_id' => $this->shift->id,
            'user_id' => $coordonnateur->id,
            'role_id' => $coordonnateur->role_id,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        return $coordonnateur;
    }

    private function makeDemande(string $type, User $demandeur): ShiftTransferRequest
    {
        return ShiftTransferRequest::create([
            'organisation_id' => $this->organisation->id,
            'type' => $type,
            'shift_id' => $this->shift->id,
            'shift_destination_id' => $type === 'permutation' ? $this->shiftDestination->id : null,
            'servant_id' => $this->servant->id,
            'demandeur_id' => $demandeur->id,
            'motif' => 'Test',
            'date_demande' => now()->toDateString(),
            'statut' => 'en_attente',
        ]);
    }

    private function payload(string $type): array
    {
        return [
            'shift_id' => $this->shift->id,
            'shift_destination_id' => $type === 'permutation' ? $this->shiftDestination->id : null,
            'type' => $type,
            'servant_id' => $this->servant->id,
            'motif' => 'Test',
            'date_demande' => now()->toDateString(),
        ];
    }

    public function test_coordonnateur_ne_peut_pas_creer_de_releve_ni_dappel(): void
    {
        $coordonnateur = $this->makeCoordonnateur();

        $this->actingAs($coordonnateur)->post('/transferts', $this->payload('releve'))->assertForbidden();
        $this->actingAs($coordonnateur)->post('/transferts', $this->payload('appel'))->assertForbidden();

        $this->assertDatabaseCount('shift_transfer_requests', 0);
    }

    public function test_coordonnateur_peut_creer_une_permutation_sur_son_shift(): void
    {
        $coordonnateur = $this->makeCoordonnateur();

        $this->actingAs($coordonnateur)->post('/transferts', $this->payload('permutation'))->assertRedirect();

        $this->assertDatabaseHas('shift_transfer_requests', ['type' => 'permutation', 'demandeur_id' => $coordonnateur->id]);
    }

    public function test_coordonnateur_ne_voit_que_les_permutations_dans_la_liste(): void
    {
        $coordonnateur = $this->makeCoordonnateur();
        $admin = $this->makeUser('administrateur');
        $this->makeDemande('releve', $admin);
        $this->makeDemande('appel', $admin);
        $permutation = $this->makeDemande('permutation', $admin);

        $this->actingAs($coordonnateur)->get('/transferts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ShiftTransfers/Index')
                ->has('demandes.data', 1)
                ->where('demandes.data.0.id', $permutation->id)
                ->where('compteurs.permutations', 1)
                ->missing('compteurs.releves')
                ->missing('compteurs.appels'));

        $this->actingAs($coordonnateur)->get('/transferts?type=permutation')->assertOk();
    }

    public function test_coordonnateur_recoit_403_sur_les_filtres_et_la_page_des_releves(): void
    {
        $coordonnateur = $this->makeCoordonnateur();

        $this->actingAs($coordonnateur)->get('/transferts?type=releve')->assertForbidden();
        $this->actingAs($coordonnateur)->get('/transferts?type=appel')->assertForbidden();
        $this->actingAs($coordonnateur)->get('/transferts/releves')->assertForbidden();
    }

    public function test_coordonnateur_ne_peut_ni_modifier_ni_resoudre_ni_supprimer_une_releve_ou_un_appel(): void
    {
        $coordonnateur = $this->makeCoordonnateur();

        foreach (['releve', 'appel'] as $type) {
            $demande = $this->makeDemande($type, $coordonnateur);

            $this->actingAs($coordonnateur)->patch("/transferts/{$demande->id}", ['notes' => 'x'])->assertForbidden();
            $this->actingAs($coordonnateur)->patch("/transferts/{$demande->id}/resoudre", [
                'resultat' => 'OK', 'resultat_date' => now()->toDateString(), 'favorable' => false,
            ])->assertForbidden();
            $this->actingAs($coordonnateur)->delete("/transferts/{$demande->id}")->assertForbidden();

            $this->assertDatabaseHas('shift_transfer_requests', ['id' => $demande->id, 'statut' => 'en_attente', 'notes' => null]);
        }
    }

    public function test_coordonnateur_peut_modifier_et_valider_une_permutation_de_son_shift(): void
    {
        $coordonnateur = $this->makeCoordonnateur();
        $demande = $this->makeDemande('permutation', $this->makeUser('administrateur'));

        $this->actingAs($coordonnateur)->patch("/transferts/{$demande->id}", ['notes' => 'Vu'])->assertRedirect();
        $this->actingAs($coordonnateur)->patch("/transferts/{$demande->id}/valider-origine", ['accepte' => true])->assertRedirect();

        $this->assertDatabaseHas('shift_transfer_requests', [
            'id' => $demande->id,
            'notes' => 'Vu',
            'validation_chef_origine' => true,
        ]);
    }

    public function test_dashboard_coordonnateur_nexpose_que_les_permutations(): void
    {
        $coordonnateur = $this->makeCoordonnateur();

        $this->actingAs($coordonnateur)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/ChefEquipe')
                ->has('permutations')
                ->missing('releves')
                ->missing('appels'));
    }

    public function test_secretaire_et_administrateur_gardent_lacces_aux_releves_et_appels(): void
    {
        foreach (['secretaire', 'administrateur'] as $slug) {
            $user = $this->makeUser($slug);

            $this->actingAs($user)->post('/transferts', $this->payload('releve'))->assertRedirect();
            $this->actingAs($user)->post('/transferts', $this->payload('appel'))->assertRedirect();
            $this->actingAs($user)->get('/transferts?type=releve')->assertOk();
            $this->actingAs($user)->get('/transferts?type=appel')->assertOk();
            $this->actingAs($user)->get('/transferts/releves')->assertOk();
            $this->actingAs($user)->get('/transferts')
                ->assertInertia(fn (Assert $page) => $page
                    ->has('compteurs.releves')
                    ->has('compteurs.appels')
                    ->has('compteurs.permutations'));

            $releve = $this->makeDemande('releve', $user);
            $this->actingAs($user)->patch("/transferts/{$releve->id}/resoudre", [
                'resultat' => 'Relevé', 'resultat_date' => now()->toDateString(),
            ])->assertRedirect();
            $this->assertDatabaseHas('shift_transfer_requests', ['id' => $releve->id, 'statut' => 'traitee']);
        }
    }
}
