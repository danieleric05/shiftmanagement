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
 * Paramètres → Licence : consultation en lecture seule de la licence de sa
 * propre organisation, réservée au Conseil du Temple et au Super Administrateur.
 */
class LicenceOrganisationTest extends TestCase
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

    private function organisation(?string $expiration, string $nom = 'Temple de test'): Organisation
    {
        return Organisation::factory()->create(['nom' => $nom, 'license_expires_at' => $expiration]);
    }

    public function test_licence_valide(): void
    {
        $admin = $this->makeUser('administrateur', $this->organisation('2027-01-15 12:00:00'));

        $this->actingAs($admin)->get(route('settings.licence.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Licence/Index')
                ->where('licence.organisation', 'Temple de test')
                ->where('licence.etat', 'valide')
                ->where('licence.niveau', 'info')
                ->where('licence.joursRestants', 100)
                ->where('licence.expiresAtIso', Carbon::parse('2027-01-15 12:00:00')->toIso8601String()));
    }

    public function test_licence_expire_bientot(): void
    {
        $admin = $this->makeUser('super_admin', $this->organisation('2026-10-10 18:00:00'));

        $this->actingAs($admin)->get(route('settings.licence.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('licence.etat', 'expire_bientot')
                ->where('licence.niveau', 'urgent')
                ->where('licence.joursRestants', 3));
    }

    public function test_licence_attention_expire_bientot(): void
    {
        $admin = $this->makeUser('administrateur', $this->organisation('2026-12-06 12:00:00'));

        $this->actingAs($admin)->get(route('settings.licence.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('licence.etat', 'expire_bientot')
                ->where('licence.niveau', 'attention')
                ->where('licence.joursRestants', 60));
    }

    public function test_licence_expiree_reste_consultable(): void
    {
        $admin = $this->makeUser('administrateur', $this->organisation('2026-10-01 12:00:00'));

        $this->actingAs($admin)->get(route('settings.licence.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('licence.etat', 'expiree')
                ->where('licence.niveau', 'expire')
                ->where('licence.joursRestants', null));
    }

    public function test_licence_sans_date(): void
    {
        $admin = $this->makeUser('administrateur', $this->organisation(null));

        $this->actingAs($admin)->get(route('settings.licence.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('licence.etat', 'sans_date')
                ->where('licence.niveau', null)
                ->where('licence.expiresAtIso', null)
                ->where('licence.joursRestants', null));
    }

    public function test_isolation_par_organisation(): void
    {
        $autre = $this->organisation('2026-10-08 12:00:00', 'Autre temple');
        $admin = $this->makeUser('administrateur', $this->organisation('2027-01-15 12:00:00', 'Mon temple'));

        $this->actingAs($admin)->get(route('settings.licence.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('licence.organisation', 'Mon temple')
                ->where('licence.etat', 'valide'));

        $this->assertNotSame('Mon temple', $autre->nom);
        // Aucun paramètre ne permet de viser une autre organisation.
        $this->actingAs($admin)->get('/parametres/licence?organisation='.$autre->id)
            ->assertInertia(fn (Assert $page) => $page->where('licence.organisation', 'Mon temple'));
    }

    public function test_roles_non_autorises_refuses(): void
    {
        $organisation = $this->organisation('2027-01-15 12:00:00');

        foreach (['secretaire', 'coordonnateur_equipe', 'autres'] as $slug) {
            $this->actingAs($this->makeUser($slug, $organisation))
                ->get(route('settings.licence.index'))
                ->assertForbidden();
        }
    }

    public function test_invite_redirige_vers_connexion(): void
    {
        $this->get(route('settings.licence.index'))->assertRedirect(route('login'));
    }

    public function test_proprietaire_sans_organisation_refuse(): void
    {
        $owner = $this->makeUser('administrateur', null);
        $owner->forceFill(['is_platform_owner' => true])->save();

        $this->actingAs($owner)->get(route('settings.licence.index'))->assertForbidden();
    }

    public function test_carte_licence_dans_parametres_selon_le_role(): void
    {
        $organisation = $this->organisation('2027-01-15 12:00:00');

        foreach (['administrateur', 'super_admin'] as $slug) {
            $this->actingAs($this->makeUser($slug, $organisation))
                ->get(route('settings.index'))
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Settings/Index')
                    ->where('afficherLicence', true));
        }

        $owner = $this->makeUser('super_admin', null);
        $this->actingAs($owner)->get(route('settings.index'))
            ->assertInertia(fn (Assert $page) => $page->where('afficherLicence', false));

        $this->actingAs($this->makeUser('secretaire', $organisation))
            ->get(route('settings.index'))
            ->assertForbidden();
    }
}
