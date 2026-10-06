<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformOwnerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_proprietaire_de_plateforme_est_redirige_du_tableau_de_bord_vers_les_licences(): void
    {
        $proprietaire = User::factory()->create([
            'organisation_id' => null,
            'role_id' => null,
            'is_platform_owner' => true,
        ]);

        $this->actingAs($proprietaire)
            ->get(route('dashboard'))
            ->assertRedirect(route('owner.licenses.index'));
    }

    public function test_la_page_des_licences_repond_pour_le_proprietaire(): void
    {
        $proprietaire = User::factory()->create([
            'organisation_id' => null,
            'role_id' => null,
            'is_platform_owner' => true,
        ]);

        $this->actingAs($proprietaire)
            ->get(route('owner.licenses.index'))
            ->assertOk();
    }

    public function test_un_proprietaire_qui_est_aussi_administrateur_garde_son_tableau_de_bord(): void
    {
        $organisation = Organisation::factory()->create();
        $role = Role::factory()->create(['slug' => 'administrateur']);
        $compte = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role_id' => $role->id,
            'is_platform_owner' => true,
        ]);

        $this->actingAs($compte)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_un_administrateur_garde_son_tableau_de_bord(): void
    {
        $organisation = Organisation::factory()->create();
        $role = Role::factory()->create(['slug' => 'administrateur']);
        $admin = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role_id' => $role->id,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk();
    }
}
