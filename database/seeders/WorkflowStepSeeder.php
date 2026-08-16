<?php

namespace Database\Seeders;

use App\Models\WorkflowStep;
use Illuminate\Database\Seeder;

class WorkflowStepSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $etapes = [
            ['cle' => 'recommandation', 'nom' => 'Recommandation'],
            ['cle' => 'rendez_vous', 'nom' => 'Rendez-vous'],
            ['cle' => 'entretien', 'nom' => 'Entretien'],
            ['cle' => 'mise_a_part', 'nom' => 'Mise à part'],
            ['cle' => 'youth_protection', 'nom' => 'Youth Protection'],
            ['cle' => 'photo', 'nom' => 'Photo'],
            ['cle' => 'badge', 'nom' => 'Badge'],
            ['cle' => 'orientation', 'nom' => 'Orientation'],
            ['cle' => 'formation', 'nom' => 'Formation'],
            ['cle' => 'disponible', 'nom' => 'Disponible'],
            ['cle' => 'affectation', 'nom' => 'Affectation'],
            ['cle' => 'actif', 'nom' => 'Actif'],
        ];

        foreach ($etapes as $ordre => $etape) {
            WorkflowStep::updateOrCreate(
                ['cle' => $etape['cle']],
                ['nom' => $etape['nom'], 'ordre' => $ordre + 1]
            );
        }
    }
}
