<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Organisation;
use App\Models\Role;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftMember;
use App\Models\ShiftPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServantVisibilityTest extends TestCase
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

    private function affecterServant(Servant $servant, Shift $shift): void
    {
        $position = ShiftPosition::create(['shift_id' => $shift->id, 'nom' => 'Poste', 'ordre' => 1]);

        Assignment::create([
            'shift_position_id' => $position->id,
            'servant_id' => $servant->id,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);
    }

    public function test_le_chef_du_shift_voit_le_parcours_du_servant_qui_y_est_affecte(): void
    {
        $organisation = Organisation::factory()->create();
        $chef = $this->makeUser('chef_equipe', $organisation);
        $shift = $this->makeShift($organisation, 'Shift Géré');
        $this->rendreChefEquipe($chef, $shift);

        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);
        $this->affecterServant($servant, $shift);

        $this->actingAs($chef)->get("/mes-servants/{$servant->id}")->assertOk();
    }

    public function test_un_chef_qui_ne_gere_pas_le_shift_du_servant_na_pas_acces(): void
    {
        $organisation = Organisation::factory()->create();
        $chef = $this->makeUser('chef_equipe', $organisation);
        $sonShift = $this->makeShift($organisation, 'Shift Géré');
        $this->rendreChefEquipe($chef, $sonShift);

        $autreShift = $this->makeShift($organisation, 'Autre Shift');
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);
        $this->affecterServant($servant, $autreShift);

        $this->actingAs($chef)->get("/mes-servants/{$servant->id}")->assertForbidden();
    }

    public function test_ladministrateur_voit_le_parcours_de_nimporte_quel_servant(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeUser('administrateur', $organisation);
        $shift = $this->makeShift($organisation, 'Shift');
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);
        $this->affecterServant($servant, $shift);

        $this->actingAs($admin)->get("/mes-servants/{$servant->id}")->assertOk();
    }
}
