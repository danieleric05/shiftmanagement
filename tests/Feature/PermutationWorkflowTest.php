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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermutationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $roleSlug, Organisation $organisation): User
    {
        $role = Role::firstOrCreate(['slug' => $roleSlug], ['nom' => $roleSlug]);

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
        $role = Role::firstOrCreate(['slug' => 'chef_equipe'], ['nom' => 'chef_equipe']);

        ShiftMember::create([
            'shift_id' => $shift->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);
    }

    /**
     * @return array{organisation: Organisation, admin: User, chefOrigine: User, chefDestination: User,
     *     shiftOrigine: Shift, shiftDestination: Shift, servant: Servant, positionOrigine: ShiftPosition,
     *     positionDestination: ShiftPosition, demande: ShiftTransferRequest}
     */
    private function setupPermutation(): array
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeUser('administrateur', $organisation);
        $chefOrigine = $this->makeUser('chef_equipe', $organisation);
        $chefDestination = $this->makeUser('chef_equipe', $organisation);
        $shiftOrigine = $this->makeShift($organisation, 'Shift Origine');
        $shiftDestination = $this->makeShift($organisation, 'Shift Destination');
        $this->rendreChefEquipe($chefOrigine, $shiftOrigine);
        $this->rendreChefEquipe($chefDestination, $shiftDestination);

        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);
        $positionOrigine = ShiftPosition::create(['shift_id' => $shiftOrigine->id, 'nom' => 'Poste', 'ordre' => 1]);
        Assignment::create([
            'shift_position_id' => $positionOrigine->id,
            'servant_id' => $servant->id,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);
        $positionDestination = ShiftPosition::create(['shift_id' => $shiftDestination->id, 'nom' => 'Poste', 'ordre' => 1]);

        $demande = ShiftTransferRequest::create([
            'organisation_id' => $organisation->id,
            'type' => 'permutation',
            'shift_id' => $shiftOrigine->id,
            'shift_destination_id' => $shiftDestination->id,
            'servant_id' => $servant->id,
            'demandeur_id' => $admin->id,
            'motif' => 'Déménagement',
            'date_demande' => now()->toDateString(),
            'statut' => 'en_attente',
        ]);

        return compact('organisation', 'admin', 'chefOrigine', 'chefDestination', 'shiftOrigine', 'shiftDestination', 'servant', 'positionOrigine', 'positionDestination', 'demande');
    }

    public function test_le_chef_dorigine_ne_peut_pas_valider_a_la_place_du_chef_de_destination(): void
    {
        ['chefOrigine' => $chefOrigine, 'demande' => $demande] = $this->setupPermutation();

        $this->actingAs($chefOrigine)
            ->patch("/transferts/{$demande->id}/valider-destination", ['accepte' => true])
            ->assertForbidden();
    }

    public function test_resoudre_est_bloque_tant_que_les_deux_chefs_nont_pas_valide(): void
    {
        ['admin' => $admin, 'chefOrigine' => $chefOrigine, 'demande' => $demande] = $this->setupPermutation();

        $this->actingAs($chefOrigine)->patch("/transferts/{$demande->id}/valider-origine", ['accepte' => true])->assertRedirect();

        $this->actingAs($admin)->patch("/transferts/{$demande->id}/resoudre", [
            'resultat' => 'Favorable',
            'resultat_date' => now()->toDateString(),
            'favorable' => true,
        ])->assertStatus(422);
    }

    public function test_un_refus_dun_chef_cloture_directement_la_demande(): void
    {
        ['chefOrigine' => $chefOrigine, 'demande' => $demande, 'servant' => $servant, 'positionOrigine' => $positionOrigine] = $this->setupPermutation();

        $this->actingAs($chefOrigine)->patch("/transferts/{$demande->id}/valider-origine", ['accepte' => false])->assertRedirect();

        $demande->refresh();
        $this->assertSame('traitee', $demande->statut);
        $this->assertFalse($demande->favorable);

        // Le servant reste sur son poste d'origine.
        $this->assertDatabaseHas('assignments', [
            'shift_position_id' => $positionOrigine->id,
            'servant_id' => $servant->id,
            'statut' => 'actif',
        ]);
    }

    public function test_permutation_favorable_deplace_le_servant_vers_le_poste_de_destination(): void
    {
        [
            'admin' => $admin, 'chefOrigine' => $chefOrigine, 'chefDestination' => $chefDestination,
            'demande' => $demande, 'servant' => $servant,
            'positionOrigine' => $positionOrigine, 'positionDestination' => $positionDestination,
        ] = $this->setupPermutation();

        $this->actingAs($chefOrigine)->patch("/transferts/{$demande->id}/valider-origine", ['accepte' => true])->assertRedirect();
        $this->actingAs($chefDestination)->patch("/transferts/{$demande->id}/valider-destination", ['accepte' => true])->assertRedirect();

        $this->actingAs($admin)->patch("/transferts/{$demande->id}/resoudre", [
            'resultat' => 'Entretien concluant',
            'resultat_date' => now()->toDateString(),
            'favorable' => true,
            'shift_position_destination_id' => $positionDestination->id,
        ])->assertRedirect();

        $demande->refresh();
        $this->assertSame('traitee', $demande->statut);
        $this->assertTrue($demande->favorable);

        $this->assertDatabaseHas('assignments', [
            'shift_position_id' => $positionOrigine->id,
            'servant_id' => $servant->id,
            'statut' => 'termine',
        ]);
        $this->assertDatabaseHas('assignments', [
            'shift_position_id' => $positionDestination->id,
            'servant_id' => $servant->id,
            'statut' => 'actif',
        ]);
    }

    public function test_permutation_defavorable_ne_deplace_pas_le_servant(): void
    {
        [
            'admin' => $admin, 'chefOrigine' => $chefOrigine, 'chefDestination' => $chefDestination,
            'demande' => $demande, 'servant' => $servant, 'positionOrigine' => $positionOrigine,
        ] = $this->setupPermutation();

        $this->actingAs($chefOrigine)->patch("/transferts/{$demande->id}/valider-origine", ['accepte' => true])->assertRedirect();
        $this->actingAs($chefDestination)->patch("/transferts/{$demande->id}/valider-destination", ['accepte' => true])->assertRedirect();

        $this->actingAs($admin)->patch("/transferts/{$demande->id}/resoudre", [
            'resultat' => 'Entretien non concluant',
            'resultat_date' => now()->toDateString(),
            'favorable' => false,
        ])->assertRedirect();

        $this->assertDatabaseHas('assignments', [
            'shift_position_id' => $positionOrigine->id,
            'servant_id' => $servant->id,
            'statut' => 'actif',
        ]);
    }

    public function test_une_releve_nest_pas_soumise_a_la_double_validation(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeUser('administrateur', $organisation);
        $shift = $this->makeShift($organisation, 'Shift');
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);

        $demande = ShiftTransferRequest::create([
            'organisation_id' => $organisation->id,
            'type' => 'releve',
            'shift_id' => $shift->id,
            'servant_id' => $servant->id,
            'demandeur_id' => $admin->id,
            'motif' => 'Absence',
            'date_demande' => now()->toDateString(),
            'statut' => 'en_attente',
        ]);

        $this->actingAs($admin)->patch("/transferts/{$demande->id}/resoudre", [
            'resultat' => 'Remplaçant trouvé',
            'resultat_date' => now()->toDateString(),
        ])->assertRedirect();

        $demande->refresh();
        $this->assertSame('traitee', $demande->statut);
    }
}
