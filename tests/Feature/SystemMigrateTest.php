<?php

namespace Tests\Feature;

use App\Http\Controllers\SystemMigrateController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class SystemMigrateTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'jeton-de-deploiement-de-test-0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.deploy.token' => self::TOKEN]);
    }

    private function appeler(?string $jeton = self::TOKEN)
    {
        $headers = $jeton === null ? [] : ['X-Deploy-Token' => $jeton];

        return $this->postJson('/system/migrate', [], $headers);
    }

    public function test_refuse_sans_jeton(): void
    {
        $this->appeler(null)->assertForbidden()->assertExactJson(['message' => 'Accès refusé.']);
    }

    public function test_refuse_un_mauvais_jeton(): void
    {
        $this->appeler('mauvais-jeton')->assertForbidden()->assertExactJson(['message' => 'Accès refusé.']);
    }

    public function test_route_desactivee_si_le_jeton_n_est_pas_configure(): void
    {
        foreach ([null, ''] as $configure) {
            config(['services.deploy.token' => $configure]);

            $this->appeler('')->assertForbidden();
            $this->appeler(null)->assertForbidden();
            $this->appeler(self::TOKEN)->assertForbidden();
        }
    }

    public function test_get_n_est_pas_autorise(): void
    {
        $this->get('/system/migrate', ['X-Deploy-Token' => self::TOKEN])->assertStatus(405);
    }

    public function test_rien_a_migrer(): void
    {
        $reponse = $this->appeler()->assertOk();

        $this->assertSame(['migrated', 'output'], array_keys($reponse->json()));
        $this->assertFalse($reponse->json('migrated'));
        $this->assertIsString($reponse->json('output'));
    }

    public function test_applique_une_migration_en_attente(): void
    {
        app('migrator')->path(base_path('tests/fixtures/migrations'));

        $reponse = $this->appeler()->assertOk();

        $this->assertTrue($reponse->json('migrated'));
        $this->assertStringContainsString('2099_01_01_000000_system_migrate_probe', $reponse->json('output'));
        $this->assertDatabaseHas('migrations', ['migration' => '2099_01_01_000000_system_migrate_probe']);

        // Relancé : plus rien à migrer.
        $this->assertFalse($this->appeler()->assertOk()->json('migrated'));
    }

    public function test_la_protection_csrf_ne_s_applique_pas(): void
    {
        $route = Route::getRoutes()->getByName('system.migrate');

        $this->assertNotNull($route);
        $this->assertSame(['POST'], $route->methods());
        $this->assertContains(PreventRequestForgery::class, $route->excludedMiddleware());

        // Requête brute, sans session ni jeton CSRF.
        $this->call('POST', '/system/migrate', [], [], [], ['HTTP_X_DEPLOY_TOKEN' => self::TOKEN])->assertOk();
    }

    public function test_limitation_de_debit(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->appeler('mauvais-jeton')->assertForbidden();
        }

        $this->appeler()->assertStatus(429);
    }

    public function test_la_reponse_ne_divulgue_ni_jeton_ni_configuration(): void
    {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32))]);

        $contenu = $this->appeler()->assertOk()->getContent();

        $this->assertStringNotContainsString(self::TOKEN, $contenu);
        $this->assertStringNotContainsString((string) config('app.key'), $contenu);
        $this->assertStringNotContainsString((string) config('database.connections.mysql.database'), $contenu);
        $this->assertStringNotContainsString('DB_', $contenu);
    }

    public function test_erreur_generique_sans_detail_en_cas_d_echec(): void
    {
        Artisan::shouldReceive('call')->andThrow(new RuntimeException('SQLSTATE détail secret mot_de_passe'));

        $reponse = $this->appeler()->assertStatus(500);

        $reponse->assertExactJson(['message' => 'La migration a échoué. Consulter les logs.']);
        $this->assertStringNotContainsString('secret', $reponse->getContent());

        // Le verrou est libéré même après un échec.
        $this->assertTrue(Cache::lock(SystemMigrateController::LOCK_NAME, 10)->get());
    }

    public function test_refuse_une_execution_simultanee(): void
    {
        $verrou = Cache::lock(SystemMigrateController::LOCK_NAME, 10);
        $this->assertTrue($verrou->get());

        $this->appeler()->assertStatus(409);

        $verrou->release();

        $this->appeler()->assertOk();
        // Libéré après l'exécution.
        $this->assertTrue(Cache::lock(SystemMigrateController::LOCK_NAME, 10)->get());
    }

    public function test_repli_sur_le_cache_fichier_si_la_table_cache_est_absente(): void
    {
        config([
            'cache.default' => 'database',
            'cache.stores.database.lock_table' => 'table_cache_inexistante',
            'cache.stores.database.table' => 'table_cache_inexistante',
        ]);
        Cache::forgetDriver('database');
        $this->assertFalse(DB::connection()->getSchemaBuilder()->hasTable('table_cache_inexistante'));
        // En réel, `migrate` crée la table `cache` avant `optimize:clear` ; ici on simule Artisan
        // pour n'éprouver que le repli du verrou et de la limitation de débit.
        Artisan::shouldReceive('call')->twice()->andReturn(0);
        Artisan::shouldReceive('output')->twice()->andReturn('');

        $this->appeler()->assertOk()->assertExactJson(['migrated' => false, 'output' => '']);

        // Le verrou fichier a été libéré ; on nettoie le compteur de débit fichier.
        $this->assertTrue(Cache::store('file')->lock(SystemMigrateController::LOCK_NAME, 10)->get());
        Cache::store('file')->lock(SystemMigrateController::LOCK_NAME)->forceRelease();
        $this->assertTrue(Cache::store('file')->has('system-migrate'));
        Cache::store('file')->forget('system-migrate');
        Cache::store('file')->forget('system-migrate:timer');
    }
}
