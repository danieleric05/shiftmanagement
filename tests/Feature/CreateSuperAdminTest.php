<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private function prerequis(): array
    {
        $organisation = Organisation::factory()->create();
        $role = Role::factory()->create(['slug' => 'super_admin', 'nom' => 'Super Administrateur']);

        return [$organisation, $role];
    }

    /** Dernière ligne non vide de la sortie : le mot de passe temporaire. */
    private function motDePasseAffiche(): string
    {
        $lignes = array_values(array_filter(array_map('trim', explode("\n", Artisan::output()))));

        return end($lignes);
    }

    public function test_cree_un_super_admin_avec_changement_de_mot_de_passe_obligatoire(): void
    {
        [$organisation, $role] = $this->prerequis();

        $code = Artisan::call('app:create-super-admin', ['email' => 'chef@exemple.com', '--name' => 'Jean Dupont']);

        $this->assertSame(0, $code);
        $user = User::where('email', 'chef@exemple.com')->firstOrFail();
        $this->assertTrue($user->must_change_password);
        $this->assertSame($role->id, $user->role_id);
        $this->assertTrue($user->hasRole('super_admin'));
        $this->assertSame($organisation->id, $user->organisation_id);
        $this->assertSame('Jean Dupont', $user->name);
        $this->assertSame('Jean', $user->prenom);
        $this->assertSame('Dupont', $user->nom);
        $this->assertFalse($user->is_platform_owner);
        $this->assertStringContainsString('changement obligatoire à la première connexion', Artisan::output());
    }

    public function test_le_mot_de_passe_affiche_permet_l_authentification(): void
    {
        $this->prerequis();

        Artisan::call('app:create-super-admin', ['email' => 'chef@exemple.com']);
        $motDePasse = $this->motDePasseAffiche();

        $this->assertSame(16, strlen($motDePasse));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]+$/', $motDePasse);
        $user = User::where('email', 'chef@exemple.com')->firstOrFail();
        $this->assertTrue(Hash::check($motDePasse, $user->password));
        $this->assertNotSame($motDePasse, $user->password);
        $this->assertSame(0, DB::table('activity_log')->count());
    }

    public function test_refuse_un_email_deja_utilise(): void
    {
        $this->prerequis();
        User::factory()->create(['email' => 'chef@exemple.com']);

        $code = Artisan::call('app:create-super-admin', ['email' => 'chef@exemple.com']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('existe déjà', Artisan::output());
        $this->assertSame(1, User::where('email', 'chef@exemple.com')->count());
    }

    public function test_refuse_sans_role_super_admin(): void
    {
        Organisation::factory()->create();

        $code = Artisan::call('app:create-super-admin', ['email' => 'chef@exemple.com']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Lancer d\'abord RoleSeeder et OrganisationSeeder', Artisan::output());
        $this->assertFalse(User::where('email', 'chef@exemple.com')->exists());
    }

    public function test_refuse_sans_organisation(): void
    {
        Role::factory()->create(['slug' => 'super_admin', 'nom' => 'Super Administrateur']);

        $code = Artisan::call('app:create-super-admin', ['email' => 'chef@exemple.com']);

        $this->assertSame(1, $code);
        $this->assertFalse(User::where('email', 'chef@exemple.com')->exists());
    }

    public function test_refuse_un_email_invalide(): void
    {
        $this->prerequis();

        $code = Artisan::call('app:create-super-admin', ['email' => 'pas-un-email']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('invalide', Artisan::output());
        $this->assertSame(0, User::count());
    }

    public function test_option_platform_owner_sans_organisation_ni_role(): void
    {
        $code = Artisan::call('app:create-super-admin', ['email' => 'owner@exemple.com', '--platform-owner' => true]);

        $this->assertSame(0, $code);
        $user = User::where('email', 'owner@exemple.com')->firstOrFail();
        $this->assertTrue($user->is_platform_owner);
        $this->assertNull($user->organisation_id);
        $this->assertNull($user->role_id);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check($this->motDePasseAffiche(), $user->password));
    }
}
