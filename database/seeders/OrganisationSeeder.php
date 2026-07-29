<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Organisation;

class OrganisationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Organisation::updateOrCreate(
            ['nom' => 'Organisation par défaut'],
            ['pays' => 'Côte d\'Ivoire', 'langue' => 'fr', 'statut' => 'actif']
        );
    }
}
