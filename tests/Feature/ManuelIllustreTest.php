<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\User;
use App\Support\ManuelFigures;
use Database\Seeders\ManuelDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use RuntimeException;
use Tests\TestCase;

class ManuelIllustreTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_manuel_se_telecharge_en_pdf(): void
    {
        $organisation = Organisation::factory()->create();
        $role = Role::firstOrCreate(['slug' => 'administrateur'], ['nom' => 'Conseil du Temple']);
        $admin = User::factory()->create(['organisation_id' => $organisation->id, 'role_id' => $role->id]);

        $this->actingAs($admin)->get('/manuel')->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_la_vue_contient_toutes_les_figures_numerotees(): void
    {
        $html = View::make('manuel.index', ['genereLe' => 'x', 'figures' => ManuelFigures::charger()])->render();

        $this->assertSame(count(ManuelFigures::LISTE), substr_count($html, '<table class="fig">'));
        $this->assertStringContainsString('Figure 1 — Page de connexion', $html);
        $this->assertStringContainsString('data:image/jpeg;base64,', $html);
    }

    public function test_repli_sans_image(): void
    {
        $figures = ManuelFigures::charger(sys_get_temp_dir().'/dossier-inexistant');

        $this->assertNull($figures['01-connexion']['src']);

        $html = View::make('manuel.index', ['genereLe' => 'x', 'figures' => $figures])->render();
        $this->assertStringNotContainsString('<table class="fig"', $html);
        $this->assertStringContainsString('Connexion, sécurité et rôles', $html);
    }

    public function test_le_seeder_refuse_en_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        (new ManuelDemoSeeder)->run();
    }

    public function test_le_seeder_refuse_avec_une_url_de_production(): void
    {
        config(['app.url' => 'https://shifts.daertech.ci']);

        $this->expectException(RuntimeException::class);
        (new ManuelDemoSeeder)->run();
    }
}
