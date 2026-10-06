<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Pieu;
use App\Models\Role;
use App\Models\Servant;
use App\Models\User;
use Database\Seeders\WorkflowStepSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ServantPieuSelectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Pieu $mission;

    private Pieu $district;

    private Pieu $pieu;

    protected function setUp(): void
    {
        parent::setUp();

        $organisation = Organisation::factory()->create();
        $role = Role::factory()->create(['slug' => 'administrateur', 'nom' => 'administrateur']);
        $this->admin = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role_id' => $role->id,
        ]);

        $this->mission = Pieu::create(['organisation_id' => $organisation->id, 'nom' => 'Mission Abidjan', 'type' => 'mission']);
        $this->district = Pieu::create(['organisation_id' => $organisation->id, 'nom' => 'District Nord', 'type' => 'district', 'parent_id' => $this->mission->id]);
        $this->pieu = Pieu::create(['organisation_id' => $organisation->id, 'nom' => 'Pieu Cocody', 'type' => 'pieu', 'parent_id' => $this->district->id]);
    }

    public function test_le_formulaire_de_creation_ne_propose_que_les_pieux(): void
    {
        $this->actingAs($this->admin)->get('/servants/create')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Servants/Create')
                ->has('pieux', 1)
                ->where('pieux.0.id', $this->pieu->id)
            );
    }

    public function test_le_formulaire_dedition_ne_propose_que_les_pieux_et_expose_le_rattachement_actuel(): void
    {
        $servant = Servant::factory()->create([
            'organisation_id' => $this->admin->organisation_id,
            'pieu_id' => $this->district->id,
        ]);

        $this->actingAs($this->admin)->get("/servants/{$servant->id}/edit")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Servants/Edit')
                ->has('pieux', 1)
                ->where('pieux.0.id', $this->pieu->id)
                ->where('uniteActuelle.id', $this->district->id)
                ->where('uniteActuelle.type', 'district')
            );
    }

    public function test_creation_refuse_un_district_une_mission_ou_un_pieu_etranger(): void
    {
        $pieuEtranger = Pieu::create(['organisation_id' => Organisation::factory()->create()->id, 'nom' => 'Pieu B', 'type' => 'pieu']);

        foreach ([$this->district, $this->mission, $pieuEtranger] as $unite) {
            $this->actingAs($this->admin)
                ->post('/servants', ['nom' => 'Kouassi', 'prenom' => 'Jean', 'pieu_id' => $unite->id])
                ->assertSessionHasErrors('pieu_id');
        }

        $this->assertDatabaseCount('servants', 0);
    }

    public function test_creation_accepte_un_pieu_de_lorganisation(): void
    {
        $this->seed(WorkflowStepSeeder::class);

        $this->actingAs($this->admin)
            ->post('/servants', ['nom' => 'Kouassi', 'prenom' => 'Jean', 'pieu_id' => $this->pieu->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('servants', ['nom' => 'Kouassi', 'pieu_id' => $this->pieu->id]);
    }

    public function test_un_servant_rattache_a_un_district_reste_modifiable_sans_changer_pieu_id(): void
    {
        $servant = Servant::factory()->create([
            'organisation_id' => $this->admin->organisation_id,
            'pieu_id' => $this->district->id,
            'statut' => 'recommande',
        ]);

        $this->actingAs($this->admin)->put("/servants/{$servant->id}", [
            'nom' => 'Nouveau nom',
            'prenom' => $servant->prenom,
            'statut' => 'recommande',
            'pieu_id' => $this->district->id,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('servants', [
            'id' => $servant->id,
            'nom' => 'Nouveau nom',
            'pieu_id' => $this->district->id,
        ]);
    }

    public function test_modification_refuse_de_passer_a_une_mission(): void
    {
        $servant = Servant::factory()->create([
            'organisation_id' => $this->admin->organisation_id,
            'pieu_id' => $this->district->id,
            'statut' => 'recommande',
        ]);

        $this->actingAs($this->admin)->put("/servants/{$servant->id}", [
            'nom' => $servant->nom,
            'prenom' => $servant->prenom,
            'statut' => 'recommande',
            'pieu_id' => $this->mission->id,
        ])->assertSessionHasErrors('pieu_id');

        $this->assertDatabaseHas('servants', ['id' => $servant->id, 'pieu_id' => $this->district->id]);
    }
}
