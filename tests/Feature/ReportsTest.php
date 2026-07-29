<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\Servant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(?Organisation $organisation = null): User
    {
        $organisation ??= Organisation::factory()->create();
        $role = Role::factory()->create(['slug' => 'administrateur', 'nom' => 'administrateur']);

        return User::factory()->create([
            'organisation_id' => $organisation->id,
            'role_id' => $role->id,
        ]);
    }

    public function test_administrateur_voit_la_page_des_rapports(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeAdmin($organisation);
        Servant::factory()->create(['organisation_id' => $organisation->id, 'statut' => 'actif']);

        $response = $this->actingAs($admin)->get('/rapports');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/Index')
            ->where('servantsParStatut.actif', 1)
        );
    }

    public function test_export_csv_des_servants(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeAdmin($organisation);
        Servant::factory()->create(['organisation_id' => $organisation->id]);

        $response = $this->actingAs($admin)->get('/rapports/servants.csv');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_export_csv_neutralise_les_formules_dans_les_champs_servants(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = $this->makeAdmin($organisation);
        Servant::factory()->create([
            'organisation_id' => $organisation->id,
            'nom' => '=cmd|\'/c calc\'!A1',
            'telephone' => '+221770000000',
        ]);

        $response = $this->actingAs($admin)->get('/rapports/servants.csv');

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString("'=cmd", $content);
        $this->assertStringContainsString("'+221770000000", $content);
    }

    public function test_export_pdf_du_remplissage_des_shifts(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get('/rapports/shifts-remplissage.pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
