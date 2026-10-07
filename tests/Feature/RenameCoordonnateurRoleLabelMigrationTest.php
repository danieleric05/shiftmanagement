<?php

namespace Tests\Feature;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenameCoordonnateurRoleLabelMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_10_000000_rename_coordonnateur_role_label.php');
    }

    public function test_renomme_le_role_coordonnateur_relance_et_down(): void
    {
        $role = Role::factory()->create(['slug' => 'coordonnateur_equipe', 'nom' => "Coordonnateur d'équipe"]);
        $autre = Role::factory()->create(['slug' => 'role_personnalise', 'nom' => "Coordonnateur d'équipe"]);

        $migration = $this->migration();
        $migration->up();

        $this->assertSame('Coordonnateur', $role->fresh()->nom);
        $this->assertSame('coordonnateur_equipe', $role->fresh()->slug);
        // Seul le slug coordonnateur_equipe est concerné.
        $this->assertSame("Coordonnateur d'équipe", $autre->fresh()->nom);

        // Relance sans effet.
        $migration->up();
        $this->assertSame('Coordonnateur', $role->fresh()->nom);

        // down() restaure le libellé d'origine.
        $migration->down();
        $this->assertSame("Coordonnateur d'équipe", $role->fresh()->nom);
        $this->assertSame("Coordonnateur d'équipe", $autre->fresh()->nom);
    }

    public function test_apostrophe_typographique_et_nom_personnalise(): void
    {
        $role = Role::factory()->create(['slug' => 'coordonnateur_equipe', 'nom' => 'Coordonnateur d’équipe']);

        $this->migration()->up();
        $this->assertSame('Coordonnateur', $role->fresh()->nom);

        // Un nom personnalisé n'est jamais écrasé.
        $role->update(['nom' => 'Chef de Shift']);
        $this->migration()->up();
        $this->assertSame('Chef de Shift', $role->fresh()->nom);
    }
}
