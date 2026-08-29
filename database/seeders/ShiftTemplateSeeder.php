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

        // Rang de chaque rôle (le Coordonnateur/Coordonnatrice d'équipe est
        // toujours en tête de liste, quel que soit l'ordre d'ajout des postes
        // sur un shift déjà peuplé de Servants importés — cf.
        // ShiftController::postesDisponiblesPourShift). Les deux variantes de
        // genre d'un même rôle partagent le même rang : elles ne coexistent
        // jamais sur un même shift (filtrées par genre), une éventuelle
        // égalité entre elles est donc sans conséquence.
        $rangs = [
            0 => ["Coordonnateur d'équipe", "Coordonnatrice d'équipe"],
            1 => ['Coordonnateur Adjoint de la formation', 'Coordonnatrice Adjointe de la formation'],
            2 => ['Coordonnateur du baptistère', 'Coordonnatrice du baptistère'],
            3 => ['Coordonnateur des OPs', 'Coordonnatrice des OPs'],
            4 => ['Scelleur'],
            5 => ['Servant', 'Servante'],
        ];

        $tousLesPostes = array_merge(...array_values($rangs));
        $template->positions()->whereNotIn('nom', $tousLesPostes)->delete();

        foreach ($rangs as $ordre => $noms) {
            foreach ($noms as $nom) {
                $template->positions()->updateOrCreate(['nom' => $nom], ['ordre' => $ordre]);
            }
        }
    }
}
