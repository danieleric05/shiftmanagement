<?php

namespace Tests\Feature;

use App\Models\Organisation;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * La migration modifie le schéma (ALTER TABLE) : sous MySQL, cela valide
 * implicitement toute transaction en cours, d'où DatabaseMigrations plutôt
 * que RefreshDatabase pour cette classe.
 */
class SplitUserStatutAndAccessMigrationTest extends TestCase
{
    use DatabaseMigrations;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_11_000000_split_user_statut_and_access.php');
    }

    private function inserer(string $email, string $statut): int
    {
        return DB::table('users')->insertGetId([
            'name' => $email, 'email' => $email, 'password' => 'x',
            'organisation_id' => Organisation::factory()->create()->id,
            'statut' => $statut, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_suspendu_devient_acces_suspendu_et_ancien_relance_puis_down(): void
    {
        $migration = $this->migration();

        // État d'avant : ENUM('actif', 'suspendu'), sans acces_suspendu.
        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'acces_suspendu'));

        $suspendu = $this->inserer('suspendu@example.com', 'suspendu');
        $actif = $this->inserer('actif@example.com', 'actif');

        $migration->up();

        $this->assertTrue(Schema::hasColumn('users', 'acces_suspendu'));
        $this->assertEquals(['statut' => 'actif', 'acces_suspendu' => 1], (array) DB::table('users')->where('id', $suspendu)->first(['statut', 'acces_suspendu']));
        $this->assertEquals(['statut' => 'actif', 'acces_suspendu' => 0], (array) DB::table('users')->where('id', $actif)->first(['statut', 'acces_suspendu']));

        // Relance sans effet (aucun compte débloqué, aucune erreur).
        $migration->up();
        $this->assertEquals(1, DB::table('users')->where('id', $suspendu)->value('acces_suspendu'));
        $this->assertEquals(0, DB::table('users')->where('id', $actif)->value('acces_suspendu'));

        // Les nouveaux statuts sont acceptés par la colonne.
        DB::table('users')->where('id', $actif)->update(['statut' => 'recommande']);

        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'acces_suspendu'));
        $this->assertSame('suspendu', DB::table('users')->where('id', $suspendu)->value('statut'));
        $this->assertSame('actif', DB::table('users')->where('id', $actif)->value('statut'));

        // Remettre le schéma à jour pour la suite (rollback de fin de test).
        $migration->up();
        $this->assertEquals(1, DB::table('users')->where('id', $suspendu)->value('acces_suspendu'));
    }
}
