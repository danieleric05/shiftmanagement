<?php

namespace Database\Seeders;

use App\Models\Horaire;
use App\Models\Organisation;
use App\Models\Shift;
use App\Models\ShiftTemplate;
use Illuminate\Database\Seeder;

class ShiftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organisation = Organisation::first();
        $template = ShiftTemplate::where('organisation_id', $organisation?->id)->where('nom', 'Temple Standard')->first();

        if (! $organisation || ! $template) {
            return;
        }

        $matin = Horaire::where('organisation_id', $organisation->id)->where('nom', 'Matin')->first();
        $soir = Horaire::where('organisation_id', $organisation->id)->where('nom', 'Soir')->first();

        $jours = ['mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        $creneaux = [
            'Matin' => $matin,
            'Soir' => $soir,
        ];

        foreach ($jours as $jour) {
            foreach ($creneaux as $nomCreneau => $horaire) {
                if (! $horaire) {
                    continue;
                }

                $shift = Shift::updateOrCreate(
                    [
                        'organisation_id' => $organisation->id,
                        'jour' => $jour,
                        'nom' => ucfirst($jour).' '.$nomCreneau,
                    ],
                    [
                        'shift_template_id' => $template->id,
                        'heure_debut' => $horaire->heure_debut,
                        'heure_fin' => $horaire->heure_fin,
                        'statut' => 'actif',
                    ]
                );

                if ($shift->positions()->count() === 0) {
                    foreach ($template->positions as $position) {
                        $shift->positions()->create([
                            'shift_template_position_id' => $position->id,
                            'nom' => $position->nom,
                            'ordre' => $position->ordre,
                        ]);
                    }
                }
            }
        }
    }
}
