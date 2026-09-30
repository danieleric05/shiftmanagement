<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Pieu;
use App\Models\Role;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SecretaireAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretaire_gere_les_servants_mais_pas_les_utilisateurs(): void
    {
        $secretaire = User::factory()->create([
            'organisation_id' => Organisation::factory()->create()->id,
            'role_id' => Role::where('slug', 'secretaire')->firstOrFail()->id,
        ]);

        $this->actingAs($secretaire)->get('/servants')->assertOk();
        $this->actingAs($secretaire)->get('/transferts')->assertOk();
        $this->actingAs($secretaire)->get('/parametres/utilisateurs')->assertForbidden();
        $this->actingAs($secretaire)->get('/parametres/roles')->assertForbidden();
    }

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

    public function test_email_du_compte_nest_expose_qua_ladministrateur(): void
    {
        $organisation = Organisation::factory()->create();
        $compte = User::factory()->create(['organisation_id' => $organisation->id, 'email' => 'servant@example.com']);
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id, 'user_id' => $compte->id]);

        $this->actingAs($this->makeUser('secretaire', $organisation))
            ->get("/servants/{$servant->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('compte', null));

        $this->actingAs($this->makeUser('administrateur', $organisation))
            ->get("/servants/{$servant->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('compte.email', 'servant@example.com'));
    }

    public function test_permutation_vers_un_shift_dune_autre_organisation_est_refusee(): void
    {
        $organisation = Organisation::factory()->create();
        $secretaire = $this->makeUser('secretaire', $organisation);
        $shiftOrigine = $this->makeShift($organisation, 'Shift Origine');
        $shiftEtranger = $this->makeShift(Organisation::factory()->create(), 'Shift Étranger');
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id, 'genre' => 'homme']);

        $this->actingAs($secretaire)->post('/transferts', [
            'shift_id' => $shiftOrigine->id,
            'shift_destination_id' => $shiftEtranger->id,
            'type' => 'permutation',
            'servant_id' => $servant->id,
            'motif' => 'Test',
            'date_demande' => now()->toDateString(),
        ])->assertSessionHasErrors('shift_destination_id');

        $this->assertDatabaseMissing('shift_transfer_requests', ['shift_destination_id' => $shiftEtranger->id]);
    }

    public function test_pieu_dune_autre_organisation_est_refuse(): void
    {
        $organisation = Organisation::factory()->create();
        $secretaire = $this->makeUser('secretaire', $organisation);
        $pieuEtranger = Pieu::create(['organisation_id' => Organisation::factory()->create()->id, 'nom' => 'Pieu Étranger']);

        $this->actingAs($secretaire)->post('/servants', [
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'pieu_id' => $pieuEtranger->id,
        ])->assertSessionHasErrors('pieu_id');
        $this->assertDatabaseMissing('servants', ['pieu_id' => $pieuEtranger->id]);

        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);
        $this->actingAs($secretaire)->put("/servants/{$servant->id}", [
            'nom' => $servant->nom,
            'prenom' => $servant->prenom,
            'statut' => $servant->statut,
            'pieu_id' => $pieuEtranger->id,
        ])->assertSessionHasErrors('pieu_id');
        $this->assertNull($servant->fresh()->pieu_id);
    }
}
