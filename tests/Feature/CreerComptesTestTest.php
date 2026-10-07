<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\Shift;
use App\Models\ShiftMember;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CreerComptesTestTest extends TestCase
{
    use RefreshDatabase;

    private const EMAILS = [
        'administrateur' => 'test.conseil@staging.daertech.ci',
        'secretaire' => 'test.secretaire@staging.daertech.ci',
        'coordonnateur_equipe' => 'test.coordonnateur@staging.daertech.ci',
        'autres' => 'test.autres@staging.daertech.ci',
        'super_admin' => 'test.superadmin@staging.daertech.ci',
    ];

    private Organisation $organisation;

    private Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->organisation = Organisation::factory()->create();
        $this->shift = Shift::create([
            'organisation_id' => $this->organisation->id, 'nom' => 'Mardi Matin', 'jour' => 'mardi',
            'heure_debut' => '07:00', 'heure_fin' => '11:00', 'statut' => 'actif',
        ]);

        config(['services.test_accounts.enabled' => true, 'app.url' => 'https://staging.daertech.ci']);
    }

    /** @return array<string, string> e-mail => mot de passe affiché */
    private function motsDePasseAffiches(): array
    {
        $mots = [];
        foreach (explode("\n", Artisan::output()) as $ligne) {
            if (preg_match('/^(test\.\S+@staging\.daertech\.ci)\s+(\S{16})$/', trim($ligne), $m)) {
                $mots[$m[1]] = $m[2];
            }
        }

        return $mots;
    }

    public function test_refuse_sans_le_drapeau(): void
    {
        config(['services.test_accounts.enabled' => false]);

        $this->assertSame(1, Artisan::call('app:creer-comptes-test'));
        $this->assertStringContainsString('TEST_ACCOUNTS_ENABLED=true', Artisan::output());
        $this->assertSame(0, User::where('email', 'like', 'test.%')->count());

        $this->assertSame(1, Artisan::call('app:creer-comptes-test', ['--supprimer' => true]));
    }

    public function test_refuse_si_app_url_est_la_production_meme_avec_le_drapeau(): void
    {
        config(['app.url' => 'https://shifts.daertech.ci']);

        $this->assertSame(1, Artisan::call('app:creer-comptes-test'));
        $this->assertStringContainsString('production', Artisan::output());
        $this->assertSame(0, User::where('email', 'like', 'test.%')->count());
    }

    public function test_cree_les_cinq_comptes_avec_les_bons_roles(): void
    {
        $this->assertSame(0, Artisan::call('app:creer-comptes-test'));

        foreach (self::EMAILS as $slug => $email) {
            $user = User::where('email', $email)->firstOrFail();
            $this->assertSame($slug, $user->role->slug);
            $this->assertSame($this->organisation->id, $user->organisation_id);
            $this->assertFalse($user->must_change_password);
            $this->assertFalse($user->accesSuspendu());
            $this->assertStringStartsWith('TEST', $user->name);
        }
    }

    public function test_le_mot_de_passe_affiche_permet_la_connexion(): void
    {
        Artisan::call('app:creer-comptes-test');
        $mots = $this->motsDePasseAffiches();

        $this->assertCount(5, $mots);
        foreach (self::EMAILS as $email) {
            $this->assertMatchesRegularExpression('/^[a-zA-Z2-9]{16}$/', $mots[$email]);
            $this->assertTrue(Hash::check($mots[$email], User::where('email', $email)->value('password')));
        }
        $this->assertCount(5, array_unique($mots));

        $this->post('/login', ['email' => self::EMAILS['secretaire'], 'password' => $mots[self::EMAILS['secretaire']]])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_idempotente_ne_recree_pas_et_n_affiche_pas_de_mot_de_passe(): void
    {
        Artisan::call('app:creer-comptes-test');
        $avant = User::whereIn('email', self::EMAILS)->pluck('password', 'email')->all();

        $this->assertSame(0, Artisan::call('app:creer-comptes-test'));
        $this->assertStringContainsString('Déjà existant', Artisan::output());
        $this->assertSame([], $this->motsDePasseAffiches());

        $this->assertSame(5, User::whereIn('email', self::EMAILS)->count());
        $this->assertSame($avant, User::whereIn('email', self::EMAILS)->pluck('password', 'email')->all());
        $this->assertSame(1, ShiftMember::count());
    }

    public function test_reinitialiser_regenere_les_mots_de_passe(): void
    {
        Artisan::call('app:creer-comptes-test');
        $anciens = $this->motsDePasseAffiches();

        $this->assertSame(0, Artisan::call('app:creer-comptes-test', ['--reinitialiser' => true]));
        $nouveaux = $this->motsDePasseAffiches();

        $this->assertCount(5, $nouveaux);
        foreach (self::EMAILS as $email) {
            $this->assertNotSame($anciens[$email], $nouveaux[$email]);
            $this->assertTrue(Hash::check($nouveaux[$email], User::where('email', $email)->value('password')));
            $this->assertFalse(Hash::check($anciens[$email], User::where('email', $email)->value('password')));
        }
        $this->assertSame(5, User::whereIn('email', self::EMAILS)->count());
        $this->assertSame(1, ShiftMember::count());
    }

    public function test_supprimer_retire_les_comptes_et_leurs_liens_sans_toucher_aux_autres(): void
    {
        $autre = User::factory()->create(['organisation_id' => $this->organisation->id]);
        Artisan::call('app:creer-comptes-test');
        $this->assertSame(1, ShiftMember::count());

        $this->assertSame(0, Artisan::call('app:creer-comptes-test', ['--supprimer' => true]));

        $this->assertSame(0, User::whereIn('email', self::EMAILS)->count());
        $this->assertSame(0, ShiftMember::count());
        $this->assertNotNull(User::find($autre->id));
        $this->assertNotNull(Shift::find($this->shift->id), 'Le shift réel est conservé.');

        // Relancer la suppression à vide ne casse rien.
        $this->assertSame(0, Artisan::call('app:creer-comptes-test', ['--supprimer' => true]));
    }

    public function test_cree_puis_supprime_un_shift_de_test_si_l_organisation_n_en_a_aucun(): void
    {
        $this->shift->forceDelete();

        Artisan::call('app:creer-comptes-test');
        $this->assertSame(1, Shift::where('nom', 'like', 'TEST %')->count());

        Artisan::call('app:creer-comptes-test', ['--supprimer' => true]);
        $this->assertSame(0, Shift::withTrashed()->count());
    }

    public function test_le_coordonnateur_voit_son_shift(): void
    {
        Artisan::call('app:creer-comptes-test');
        $coordonnateur = User::where('email', self::EMAILS['coordonnateur_equipe'])->firstOrFail();

        $this->assertTrue($coordonnateur->gereDesShifts());
        $this->assertEquals([$this->shift->id], $coordonnateur->shiftsGeres()->all());

        $this->actingAs($coordonnateur)->get('/dashboard')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard/ChefEquipe'));
        $this->actingAs($coordonnateur)->get("/mon-shift/{$this->shift->id}")->assertOk();
    }

    public function test_le_role_coordonnateur_garde_gere_shifts(): void
    {
        $this->assertTrue((bool) Role::where('slug', 'coordonnateur_equipe')->value('gere_shifts'));
    }
}
