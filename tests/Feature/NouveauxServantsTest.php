<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\Servant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NouveauxServantsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $roleSlug, Organisation $organisation): User
    {
        $role = Role::firstOrCreate(['slug' => $roleSlug], ['nom' => $roleSlug]);
        if ($roleSlug === 'coordonnateur_equipe') {
            $role->update(['gere_shifts' => true]);
        }

        return User::factory()->create([
            'organisation_id' => $organisation->id,
            'role_id' => $role->id,
        ]);
    }

    public function test_la_vue_nouveaux_ne_liste_que_les_servants_recommandes_de_lorganisation(): void
    {
        $organisation = Organisation::factory()->create();
        $recommande = Servant::factory()->create(['organisation_id' => $organisation->id, 'statut' => 'recommande']);
        Servant::factory()->create(['organisation_id' => $organisation->id, 'statut' => 'actif']);
        Servant::factory()->create(['organisation_id' => Organisation::factory()->create()->id, 'statut' => 'recommande']);

        foreach (['administrateur', 'super_admin', 'secretaire'] as $slug) {
            $this->actingAs($this->makeUser($slug, $organisation))->get('/servants/nouveaux')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Servants/Index')
                    ->where('nouveaux', true)
                    ->has('servants.data', 1)
                    ->where('servants.data.0.id', $recommande->id));
        }
    }

    public function test_les_autres_roles_nont_pas_acces_a_la_vue_nouveaux(): void
    {
        $organisation = Organisation::factory()->create();

        $this->actingAs($this->makeUser('coordonnateur_equipe', $organisation))->get('/servants/nouveaux')->assertForbidden();
        $this->actingAs($this->makeUser('membre', $organisation))->get('/servants/nouveaux')->assertForbidden();
    }
}
