<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\Shift;
use App\Models\ShiftPosition;
use App\Models\ShiftTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RenameCoordonnateurPositionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_07_000000_rename_coordonnateur_positions.php');
    }

    public function test_renomme_les_postes_coordonnateur_sans_toucher_au_role(): void
    {
        $organisation = Organisation::factory()->create();
        $role = Role::factory()->create(['slug' => 'coordonnateur_equipe', 'nom' => "Coordonnateur d'équipe"]);
        $template = ShiftTemplate::create(['organisation_id' => $organisation->id, 'nom' => 'Temple Standard']);

        $tCoordo = $template->positions()->create(['nom' => "Coordonnateur d'équipe", 'ordre' => 0]);
        $tCoordoSoeur = $template->positions()->create(['nom' => 'Coordonnatrice d’équipe', 'ordre' => 0]);
        $tDoublon = $template->positions()->create(['nom' => 'Coordonnateur', 'ordre' => 0]);
        $tAutre = $template->positions()->create(['nom' => 'Coordonnateur du baptistère', 'ordre' => 2]);

        $shift = Shift::create([
            'organisation_id' => $organisation->id, 'shift_template_id' => $template->id,
            'nom' => 'Mardi Matin Frères', 'jour' => 'mardi', 'heure_debut' => '07:00', 'heure_fin' => '11:00', 'statut' => 'actif',
        ]);
        $sCoordo = $shift->positions()->create(['nom' => "Coordonnateur d'équipe", 'ordre' => 0, 'shift_template_position_id' => $tCoordo->id]);
        $sSupprime = $shift->positions()->create(['nom' => "Coordonnatrice d'équipe", 'ordre' => 0]);
        $sSupprime->delete();

        $migration = $this->migration();
        $migration->up();

        $this->assertSame('Coordonnateur', $tCoordo->fresh()->nom);
        $this->assertSame('Coordonnatrice', $tCoordoSoeur->fresh()->nom);
        $this->assertSame('Coordonnateur', $tDoublon->fresh()->nom);
        $this->assertSame('Coordonnateur du baptistère', $tAutre->fresh()->nom);
        $this->assertSame('Coordonnateur', $sCoordo->fresh()->nom);
        $this->assertSame('Coordonnatrice', ShiftPosition::withTrashed()->find($sSupprime->id)->nom);
        $this->assertSame("Coordonnateur d'équipe", $role->fresh()->nom);

        // Relance sans effet.
        $avant = [
            DB::table('shift_template_positions')->orderBy('id')->pluck('nom', 'id')->all(),
            DB::table('shift_positions')->orderBy('id')->pluck('nom', 'id')->all(),
        ];
        $migration->up();
        $apres = [
            DB::table('shift_template_positions')->orderBy('id')->pluck('nom', 'id')->all(),
            DB::table('shift_positions')->orderBy('id')->pluck('nom', 'id')->all(),
        ];
        $this->assertSame($avant, $apres);

        // down() restaure le libellé d'origine.
        $migration->down();
        $this->assertSame("Coordonnateur d'équipe", $tCoordo->fresh()->nom);
        $this->assertSame("Coordonnatrice d'équipe", $tCoordoSoeur->fresh()->nom);
        $this->assertSame("Coordonnateur d'équipe", $sCoordo->fresh()->nom);
        $this->assertSame("Coordonnatrice d'équipe", ShiftPosition::withTrashed()->find($sSupprime->id)->nom);
        $this->assertSame('Coordonnateur du baptistère', $tAutre->fresh()->nom);
    }
}
