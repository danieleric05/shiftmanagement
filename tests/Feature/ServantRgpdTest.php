<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Organisation;
use App\Models\Role;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServantRgpdTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(?Organisation $organisation = null): User
    {
        $organisation ??= Organisation::factory()->create();
        $role = Role::factory()->create(['slug' => 'administrateur', 'nom' => 'administrateur']);

        return User::factory()->create([
            'organisation_id' => $organisation->id,
            'role_id' => $role->id,
        ]);
    }

    private function makeChef(Organisation $organisation): User
    {
        $role = Role::firstOrCreate(['slug' => 'chef_equipe'], ['nom' => 'chef_equipe']);

        return User::factory()->create([
            'organisation_id' => $organisation->id,
            'role_id' => $role->id,
        ]);
    }

    public function test_administrateur_peut_anonymiser_un_servant(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();
        $photo = UploadedFile::fake()->image('portrait.jpg')->store("servants/{$admin->organisation_id}", 'local');

        $servant = Servant::factory()->create([
            'organisation_id' => $admin->organisation_id,
            'nom' => 'Kouassi',
            'prenom' => 'Jean',
            'telephone' => '0102030405',
            'adresse' => '12 rue des Fleurs',
            'date_naissance' => '1990-01-01',
            'photo' => $photo,
            'statut' => 'actif',
        ]);

        $shift = Shift::create([
            'organisation_id' => $admin->organisation_id,
            'nom' => 'Shift Test',
            'jour' => 'mardi',
            'heure_debut' => '07:00',
            'heure_fin' => '11:00',
            'statut' => 'actif',
        ]);
        $position = ShiftPosition::create(['shift_id' => $shift->id, 'nom' => 'Poste', 'ordre' => 1]);
        Assignment::create([
            'shift_position_id' => $position->id,
            'servant_id' => $servant->id,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        $this->actingAs($admin)->patch("/servants/{$servant->id}/anonymiser")->assertRedirect();

        $servant->refresh();
        $this->assertNotEquals('Kouassi', $servant->nom);
        $this->assertNull($servant->telephone);
        $this->assertNull($servant->adresse);
        $this->assertNull($servant->date_naissance);
        $this->assertNull($servant->photo);
        $this->assertEquals('retire', $servant->statut);
        Storage::disk('local')->assertMissing($photo);

        $this->assertDatabaseHas('assignments', [
            'servant_id' => $servant->id,
            'statut' => 'termine',
        ]);

        $this->assertDatabaseHas('servants', ['id' => $servant->id]);
    }

    public function test_chef_ne_peut_pas_anonymiser_un_servant(): void
    {
        $organisation = Organisation::factory()->create();
        $chef = $this->makeChef($organisation);
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);

        $this->actingAs($chef)->patch("/servants/{$servant->id}/anonymiser")->assertForbidden();
    }

    public function test_administrateur_peut_exporter_les_donnees_dun_servant(): void
    {
        $admin = $this->makeAdmin();
        $servant = Servant::factory()->create([
            'organisation_id' => $admin->organisation_id,
            'nom' => 'Kouassi',
            'prenom' => 'Jean',
        ]);

        $response = $this->actingAs($admin)->get("/servants/{$servant->id}/export");

        $response->assertOk();
        $response->assertJsonPath('identite.nom', 'Kouassi');
        $response->assertHeader('Content-Disposition');
    }

    public function test_chef_ne_peut_pas_exporter_les_donnees_dun_servant(): void
    {
        $organisation = Organisation::factory()->create();
        $chef = $this->makeChef($organisation);
        $servant = Servant::factory()->create(['organisation_id' => $organisation->id]);

        $this->actingAs($chef)->get("/servants/{$servant->id}/export")->assertForbidden();
    }
}
