<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Interview;
use App\Models\Organisation;
use App\Models\Role;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftMember;
use App\Models\User;
use Database\Seeders\WorkflowStepSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterviewTest extends TestCase
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
        $coordinateurRole = Role::firstOrCreate(['slug' => 'coordonnateur_equipe'], ['nom' => 'coordonnateur_equipe', 'gere_shifts' => true]);

        ShiftMember::create([
            'shift_id' => $shift->id,
            'user_id' => $user->id,
            'role_id' => $coordinateurRole->id,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);
    }

    private function makeCandidate(Organisation $organisation, Shift $shift): Candidate
    {
        return Candidate::create([
            'organisation_id' => $organisation->id,
            'nom' => 'Koffi',
            'prenom' => 'Jean',
            'telephone' => '0700000000',
            'shift_souhaite_id' => $shift->id,
            'statut' => 'nouveau',
        ]);
    }

    public function test_coordinateur_peut_planifier_un_entretien_pour_son_shift(): void
    {
        $organisation = Organisation::factory()->create();
        $coordinateur = $this->makeUser('coordonnateur_equipe', $organisation);
        $shift = $this->makeShift($organisation);
        $this->rendreCoordinateur($coordinateur, $shift);
        $candidat = $this->makeCandidate($organisation, $shift);

        $response = $this->actingAs($coordinateur)->post('/entretiens', [
            'candidate_id' => $candidat->id,
            'shift_souhaite_id' => $shift->id,
            'date_entretien' => now()->addDays(3)->toDateString(),
            'heure_entretien' => '18:00',
            'engagement_vu' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('interviews', [
            'candidate_id' => $candidat->id,
            'shift_souhaite_id' => $shift->id,
            'planifie_par' => $coordinateur->id,
            'statut' => 'planifie',
        ]);
        $this->assertDatabaseHas('candidates', ['id' => $candidat->id, 'statut' => 'entretien_planifie']);
    }

    public function test_coordinateur_ne_peut_pas_resoudre_un_entretien(): void
    {
        $organisation = Organisation::factory()->create();
        $coordinateur = $this->makeUser('coordonnateur_equipe', $organisation);
        $shift = $this->makeShift($organisation);
        $this->rendreCoordinateur($coordinateur, $shift);
        $candidat = $this->makeCandidate($organisation, $shift);

        $entretien = Interview::create([
            'organisation_id' => $organisation->id,
            'candidate_id' => $candidat->id,
            'shift_souhaite_id' => $shift->id,
            'planifie_par' => $coordinateur->id,
            'date_entretien' => now()->toDateString(),
            'statut' => 'planifie',
        ]);

        $this->actingAs($coordinateur)->patch("/entretiens/{$entretien->id}/resoudre", [
            'resultat' => 'Validé',
            'valide' => true,
            'shift_affecte_id' => $shift->id,
        ])->assertForbidden();

        $this->assertDatabaseHas('interviews', ['id' => $entretien->id, 'statut' => 'planifie']);
    }

    public function test_administrateur_resout_un_entretien_valide_et_convertit_le_candidat_en_servant(): void
    {
        $this->seed(WorkflowStepSeeder::class);

        $organisation = Organisation::factory()->create();
        $admin = $this->makeUser('administrateur', $organisation);
        $shift = $this->makeShift($organisation);
        $candidat = $this->makeCandidate($organisation, $shift);

        $entretien = Interview::create([
            'organisation_id' => $organisation->id,
            'candidate_id' => $candidat->id,
            'shift_souhaite_id' => $shift->id,
            'planifie_par' => $admin->id,
            'date_entretien' => now()->toDateString(),
            'statut' => 'planifie',
        ]);

        $response = $this->actingAs($admin)->patch("/entretiens/{$entretien->id}/resoudre", [
            'resultat' => 'Entretien concluant',
            'valide' => true,
            'shift_affecte_id' => $shift->id,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('interviews', [
            'id' => $entretien->id,
            'statut' => 'realise',
            'shift_affecte_id' => $shift->id,
            'decideur_id' => $admin->id,
        ]);

        $candidat->refresh();
        $this->assertSame('converti', $candidat->statut);
        $this->assertNotNull($candidat->servant_id);

        $servant = Servant::find($candidat->servant_id);
        $this->assertNotNull($servant);
        $this->assertSame('recommande', $servant->statut);
        $this->assertSame('homme', $servant->genre);
        $this->assertSame(12, $servant->workflowSteps()->count());

        $etapeEntretien = $servant->workflowSteps()
            ->whereHas('workflowStep', fn ($q) => $q->where('cle', 'entretien'))
            ->first();
        $this->assertSame('termine', $etapeEntretien->statut);
    }

    public function test_conversion_vers_un_shift_soeurs_donne_un_servant_de_genre_femme(): void
    {
        $this->seed(WorkflowStepSeeder::class);

        $organisation = Organisation::factory()->create();
        $admin = $this->makeUser('administrateur', $organisation);
        $shift = $this->makeShift($organisation, 'Mardi Matin Sœurs');
        $candidat = $this->makeCandidate($organisation, $shift);

        $entretien = Interview::create([
            'organisation_id' => $organisation->id,
            'candidate_id' => $candidat->id,
            'shift_souhaite_id' => $shift->id,
            'planifie_par' => $admin->id,
            'date_entretien' => now()->toDateString(),
            'statut' => 'planifie',
        ]);

        $this->actingAs($admin)->patch("/entretiens/{$entretien->id}/resoudre", [
            'resultat' => 'Entretien concluant',
            'valide' => true,
            'shift_affecte_id' => $shift->id,
        ])->assertRedirect();

        $candidat->refresh();
        $servant = Servant::find($candidat->servant_id);
        $this->assertSame('femme', $servant->genre);
    }

    public function test_administrateur_resout_un_entretien_refuse_sans_creer_de_servant(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeUser('administrateur', $organisation);
        $shift = $this->makeShift($organisation);
        $candidat = $this->makeCandidate($organisation, $shift);

        $entretien = Interview::create([
            'organisation_id' => $organisation->id,
            'candidate_id' => $candidat->id,
            'shift_souhaite_id' => $shift->id,
            'planifie_par' => $admin->id,
            'date_entretien' => now()->toDateString(),
            'statut' => 'planifie',
        ]);

        $this->actingAs($admin)->patch("/entretiens/{$entretien->id}/resoudre", [
            'resultat' => 'Non concluant',
            'valide' => false,
        ])->assertRedirect();

        $candidat->refresh();
        $this->assertSame('entretien_realise', $candidat->statut);
        $this->assertNull($candidat->servant_id);
        $this->assertDatabaseCount('servants', 0);
    }
}
