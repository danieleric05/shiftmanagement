<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Interview;
use App\Models\Organisation;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecretaryAccessTest extends TestCase
{
    use RefreshDatabase;

    private function makeSecretaire(?Organisation $organisation = null): User
    {
        $organisation ??= Organisation::factory()->create();
        $role = Role::factory()->create(['slug' => 'secretaire', 'nom' => 'Secrétaire']);

        return User::factory()->create([
            'organisation_id' => $organisation->id,
            'role_id' => $role->id,
        ]);
    }

    public function test_secretaire_peut_voir_les_candidats_et_les_entretiens(): void
    {
        $secretaire = $this->makeSecretaire();

        $this->actingAs($secretaire)->get('/candidats')->assertOk();
        $this->actingAs($secretaire)->get('/entretiens')->assertOk();
    }

    public function test_secretaire_peut_ajouter_un_candidat_pour_nimporte_quel_shift(): void
    {
        $organisation = Organisation::factory()->create();
        $secretaire = $this->makeSecretaire($organisation);
        $shift = Shift::create([
            'organisation_id' => $organisation->id,
            'nom' => 'Shift Test',
            'jour' => 'mardi',
            'heure_debut' => '07:00',
            'heure_fin' => '11:00',
            'statut' => 'actif',
        ]);

        $response = $this->actingAs($secretaire)->post('/candidats', [
            'nom' => 'Koffi',
            'prenom' => 'Jean',
            'telephone' => '0700000000',
            'shift_souhaite_id' => $shift->id,
            'date_appel' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('candidates', ['nom' => 'Koffi', 'shift_souhaite_id' => $shift->id]);
    }

    public function test_secretaire_na_pas_acces_aux_shifts_ni_aux_servants_ni_a_la_gouvernance(): void
    {
        $secretaire = $this->makeSecretaire();

        $this->actingAs($secretaire)->get('/shifts')->assertForbidden();
        $this->actingAs($secretaire)->get('/servants')->assertForbidden();
        $this->actingAs($secretaire)->get('/gouvernance')->assertForbidden();
        $this->actingAs($secretaire)->get('/rapports')->assertForbidden();
    }

    public function test_secretaire_ne_peut_pas_resoudre_un_entretien(): void
    {
        $organisation = Organisation::factory()->create();
        $secretaire = $this->makeSecretaire($organisation);
        $shift = Shift::create([
            'organisation_id' => $organisation->id,
            'nom' => 'Shift Test',
            'jour' => 'mardi',
            'heure_debut' => '07:00',
            'heure_fin' => '11:00',
            'statut' => 'actif',
        ]);
        $candidat = Candidate::create([
            'organisation_id' => $organisation->id,
            'nom' => 'Koffi',
            'prenom' => 'Jean',
            'shift_souhaite_id' => $shift->id,
            'statut' => 'nouveau',
        ]);
        $entretien = Interview::create([
            'organisation_id' => $organisation->id,
            'candidate_id' => $candidat->id,
            'shift_souhaite_id' => $shift->id,
            'planifie_par' => $secretaire->id,
            'date_entretien' => now()->toDateString(),
            'statut' => 'planifie',
        ]);

        $this->actingAs($secretaire)->patch("/entretiens/{$entretien->id}/resoudre", [
            'resultat' => 'Validé',
            'valide' => true,
        ])->assertForbidden();
    }

    public function test_dashboard_dune_secretaire_redirige_vers_les_candidats(): void
    {
        $secretaire = $this->makeSecretaire();

        $this->actingAs($secretaire)->get('/dashboard')->assertRedirect(route('candidates.index'));
    }
}
