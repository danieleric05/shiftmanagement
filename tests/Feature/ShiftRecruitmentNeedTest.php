<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\Shift;
use App\Models\ShiftMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftRecruitmentNeedTest extends TestCase
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
        $coordinateurRole = Role::firstOrCreate(['slug' => 'coordonnateur_equipe'], ['nom' => 'coordonnateur_equipe']);

        ShiftMember::create([
            'shift_id' => $shift->id,
            'user_id' => $user->id,
            'role_id' => $coordinateurRole->id,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);
    }

    public function test_coordinateur_peut_definir_le_besoin_de_son_shift(): void
    {
        $organisation = Organisation::factory()->create();
        $coordinateur = $this->makeUser('coordonnateur_equipe', $organisation);
        $shift = $this->makeShift($organisation);
        $this->rendreCoordinateur($coordinateur, $shift);

        $response = $this->actingAs($coordinateur)->put("/recrutement/{$shift->id}", [
            'nombre_a_recruter' => 3,
            'echeance' => now()->addMonths(2)->toDateString(),
            'notes' => 'Priorité sœurs',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shift_recruitment_needs', [
            'shift_id' => $shift->id,
            'nombre_a_recruter' => 3,
            'updated_by' => $coordinateur->id,
        ]);
    }

    public function test_coordinateur_ne_peut_pas_definir_le_besoin_dun_shift_quil_ne_gere_pas(): void
    {
        $organisation = Organisation::factory()->create();
        $coordinateur = $this->makeUser('coordonnateur_equipe', $organisation);
        $shiftGere = $this->makeShift($organisation, 'Shift géré');
        $this->rendreCoordinateur($coordinateur, $shiftGere);
        $autreShift = $this->makeShift($organisation, 'Shift non géré');

        $response = $this->actingAs($coordinateur)->put("/recrutement/{$autreShift->id}", [
            'nombre_a_recruter' => 2,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('shift_recruitment_needs', ['shift_id' => $autreShift->id]);
    }

    public function test_administrateur_peut_definir_le_besoin_de_nimporte_quel_shift(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeUser('administrateur', $organisation);
        $shift = $this->makeShift($organisation);

        $response = $this->actingAs($admin)->put("/recrutement/{$shift->id}", [
            'nombre_a_recruter' => 5,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shift_recruitment_needs', [
            'shift_id' => $shift->id,
            'nombre_a_recruter' => 5,
        ]);
    }

    public function test_upsert_ne_cree_pas_de_doublon(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeUser('administrateur', $organisation);
        $shift = $this->makeShift($organisation);

        $this->actingAs($admin)->put("/recrutement/{$shift->id}", ['nombre_a_recruter' => 2]);
        $this->actingAs($admin)->put("/recrutement/{$shift->id}", ['nombre_a_recruter' => 4]);

        $this->assertDatabaseCount('shift_recruitment_needs', 1);
        $this->assertDatabaseHas('shift_recruitment_needs', ['shift_id' => $shift->id, 'nombre_a_recruter' => 4]);
    }
}
