<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Organisation;
use App\Models\Role;
use App\Models\Shift;
use App\Models\ShiftMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateTest extends TestCase
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

    private function rendreCoordinateur(User $user, Shift $shift): void
    {
        $coordinateurRole = Role::firstOrCreate(['slug' => 'coordinateur'], ['nom' => 'coordinateur']);

        ShiftMember::create([
            'shift_id' => $shift->id,
            'user_id' => $user->id,
            'role_id' => $coordinateurRole->id,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);
    }

    public function test_coordinateur_peut_ajouter_un_candidat_pour_son_shift(): void
    {
        $organisation = Organisation::factory()->create();
        $coordinateur = $this->makeUser('coordinateur', $organisation);
        $shift = $this->makeShift($organisation);
        $this->rendreCoordinateur($coordinateur, $shift);

        $response = $this->actingAs($coordinateur)->post('/candidats', [
            'nom' => 'Koffi',
            'prenom' => 'Jean',
            'telephone' => '0700000000',
            'shift_souhaite_id' => $shift->id,
            'date_appel' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('candidates', [
            'nom' => 'Koffi',
            'prenom' => 'Jean',
            'shift_souhaite_id' => $shift->id,
            'statut' => 'nouveau',
        ]);
    }

    public function test_coordinateur_ne_peut_pas_ajouter_un_candidat_pour_un_shift_quil_ne_gere_pas(): void
    {
        $organisation = Organisation::factory()->create();
        $coordinateur = $this->makeUser('coordinateur', $organisation);
        $shiftGere = $this->makeShift($organisation, 'Shift géré');
        $this->rendreCoordinateur($coordinateur, $shiftGere);
        $autreShift = $this->makeShift($organisation, 'Shift non géré');

        $response = $this->actingAs($coordinateur)->post('/candidats', [
            'nom' => 'Koffi',
            'prenom' => 'Jean',
            'shift_souhaite_id' => $autreShift->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('candidates', ['nom' => 'Koffi']);
    }

    public function test_coordinateur_ne_peut_pas_supprimer_un_candidat(): void
    {
        $organisation = Organisation::factory()->create();
        $coordinateur = $this->makeUser('coordinateur', $organisation);
        $shift = $this->makeShift($organisation);
        $this->rendreCoordinateur($coordinateur, $shift);

        $candidat = Candidate::create([
            'organisation_id' => $organisation->id,
            'nom' => 'Koffi',
            'prenom' => 'Jean',
            'shift_souhaite_id' => $shift->id,
            'statut' => 'nouveau',
        ]);

        $this->actingAs($coordinateur)->delete("/candidats/{$candidat->id}")->assertForbidden();
        $this->assertDatabaseHas('candidates', ['id' => $candidat->id]);
    }

    public function test_administrateur_peut_supprimer_un_candidat(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeUser('administrateur', $organisation);
        $shift = $this->makeShift($organisation);

        $candidat = Candidate::create([
            'organisation_id' => $organisation->id,
            'nom' => 'Koffi',
            'prenom' => 'Jean',
            'shift_souhaite_id' => $shift->id,
            'statut' => 'nouveau',
        ]);

        $this->actingAs($admin)->delete("/candidats/{$candidat->id}")->assertRedirect();
        $this->assertSoftDeleted('candidates', ['id' => $candidat->id]);
    }
}
