<?php

namespace Tests\Feature;

use App\Models\Role;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CoordonnateurGereShiftsTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_seeder_cree_le_role_coordonnateur_avec_le_droit_gere_shifts(): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertTrue((bool) Role::where('slug', 'coordonnateur_equipe')->value('gere_shifts'));
        $this->assertFalse((bool) Role::where('slug', 'secretaire')->value('gere_shifts'));
        $this->assertFalse((bool) Role::where('slug', 'autres')->value('gere_shifts'));
        $this->assertFalse((bool) Role::where('slug', 'administrateur')->value('gere_shifts'));
    }

    public function test_la_migration_corrige_un_role_coordonnateur_existant_sans_le_droit(): void
    {
        $this->seed(RoleSeeder::class);
        DB::table('roles')->where('slug', 'coordonnateur_equipe')->update(['gere_shifts' => false]);

        $migration = require database_path('migrations/2026_10_12_000000_ensure_coordonnateur_role_gere_shifts.php');
        $migration->up();

        $this->assertTrue((bool) Role::where('slug', 'coordonnateur_equipe')->value('gere_shifts'));

        // Idempotente, et ne touche pas aux autres rôles.
        $migration->up();
        $this->assertFalse((bool) Role::where('slug', 'secretaire')->value('gere_shifts'));
    }
}
