<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\Servant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
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

    public function test_administrateur_voit_le_journal_dactivite_de_son_organisation(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeUser('administrateur', $organisation);

        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);
        $servant->update(['statut' => 'actif']);

        $autreOrganisation = Organisation::factory()->create();
        Servant::factory()->create(['organisation_id' => $autreOrganisation->id]);

        $response = $this->actingAs($admin)->get('/parametres/journal');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/ActivityLog/Index')
            ->has('activites.data', 2)
        );
    }

    public function test_membre_ne_peut_pas_voir_le_journal_dactivite(): void
    {
        $membre = $this->makeUser('membre');

        $this->actingAs($membre)->get('/parametres/journal')->assertForbidden();
    }
}
