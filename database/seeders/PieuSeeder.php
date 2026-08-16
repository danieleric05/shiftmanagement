<?php

namespace Database\Seeders;

use App\Models\Organisation;
use App\Models\Pieu;
use Illuminate\Database\Seeder;

class PieuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organisation = Organisation::first();

        if (! $organisation) {
            return;
        }

        foreach (['Pieu de Cocody', 'Pieu de Yopougon', 'Pieu d\'Abobo'] as $nom) {
            Pieu::updateOrCreate(
                ['organisation_id' => $organisation->id, 'nom' => $nom],
                []
            );
        }
    }
}
