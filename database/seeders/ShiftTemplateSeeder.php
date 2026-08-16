<?php

namespace Database\Seeders;

use App\Models\Organisation;
use App\Models\ShiftTemplate;
use Illuminate\Database\Seeder;

class ShiftTemplateSeeder extends Seeder
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

        $template = ShiftTemplate::updateOrCreate(
            ['organisation_id' => $organisation->id, 'nom' => 'Temple Standard'],
            ['description' => 'Modèle de postes standard appliqué à tous les Shifts du Temple.']
        );

        $postes = [
            'Membre de la Présidence',
            'Matronne',
            'Greffier',
            'Secrétaire',
            'Coordinateur Principal',
            'Coordinateur Adjoint',
            'Coordinateur Adjoint Formation',
            'Coordinateur Adjoint des OP',
            'Coordinateur Adjoint du Baptistaire',
            'Coordinatrice Principale',
            'Coordinatrice Adjointe',
            'Coordinatrice Adjointe Formation',
            'Coordinatrice Adjointe des OP',
            'Coordinatrice Adjointe du Baptistaire',
            'Servant',
            'Servante',
        ];

        $template->positions()->whereNotIn('nom', $postes)->delete();

        foreach ($postes as $ordre => $nom) {
            $template->positions()->updateOrCreate(
                ['nom' => $nom],
                ['ordre' => $ordre + 1]
            );
        }
    }
}
