<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Données du bandeau de compte à rebours de la licence (props partagées
 * `licence.compteARebours`), réservé au Conseil du Temple.
 */
class BandeauLicenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-07 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeUser(string $roleSlug, ?Organisation $organisation): User
    {
        $role = Role::firstOrCreate(['slug' => $roleSlug], ['nom' => $roleSlug, 'gere_shifts' => $roleSlug === 'coordonnateur_equipe']);

        return User::factory()->create([
            'organisation_id' => $organisation?->id,
            'role_id' => $role->id,
        ]);
    }

    private function organisation(?string $expiration): Organisation
    {
        return Organisation::factory()->create(['license_expires_at' => $expiration]);
    }

    public function test_jours_restants_et_niveau_info_au_dela_de_60_jours(): void
    {
        $admin = $this->makeUser('administrateur', $this->organisation('2027-01-15 12:00:00'));

        $this->actingAs($admin)->get(route('servants.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('licence.expired', false)
                ->where('licence.compteARebours.joursRestants', 100)
                ->where('licence.compteARebours.niveau', 'info')
                ->where('licence.compteARebours.expiresAtIso', Carbon::parse('2027-01-15 12:00:00')->toIso8601String()));
    }

    public function test_niveau_attention_a_60_jours_ou_moins(): void
    {
        $admin = $this->makeUser('super_admin', $this->organisation('2026-12-06 12:00:00'));

        $this->actingAs($admin)->get(route('servants.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('licence.compteARebours.joursRestants', 60)
                ->where('licence.compteARebours.niveau', 'attention'));
    }

    public function test_niveau_urgent_a_7_jours_ou_moins(): void
    {
        $admin = $this->makeUser('administrateur', $this->organisation('2026-10-10 18:00:00'));

        $this->actingAs($admin)->get(route('servants.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('licence.compteARebours.joursRestants', 3)
                ->where('licence.compteARebours.niveau', 'urgent'));
    }

    public function test_aucun_compte_a_rebours_sans_date_d_expiration(): void
    {
        $admin = $this->makeUser('administrateur', $this->organisation(null));

        $this->actingAs($admin)->get(route('servants.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('licence.expired', false)
                ->where('licence.compteARebours', null));
    }

    public function test_licence_expiree_laisse_la_place_au_bandeau_existant(): void
    {
        $admin = $this->makeUser('administrateur', $this->organisation('2026-10-01 12:00:00'));

        $this->actingAs($admin)->get(route('servants.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('licence.expired', true)
                ->where('licence.compteARebours', null));
    }

    public function test_role_non_concerne_sans_compte_a_rebours(): void
    {
        $secretaire = $this->makeUser('secretaire', $this->organisation('2026-10-10 12:00:00'));

        $this->actingAs($secretaire)->get(route('servants.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('licence.expired', false)
                ->where('licence.compteARebours', null));
    }

    public function test_utilisateur_sans_organisation_sans_licence(): void
    {
        $owner = $this->makeUser('administrateur', null);
        $owner->forceFill(['is_platform_owner' => true])->save();

        $this->actingAs($owner)->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('licence', null));
    }
}
