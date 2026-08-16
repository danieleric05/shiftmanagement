<?php

namespace Database\Seeders;

use App\Models\Organisation;
use App\Models\Servant;
use App\Models\Shift;
use Illuminate\Database\Seeder;

class AssignmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Affectations réelles connues (extraites du fichier Excel d'origine) :
     * seules les cases Mardi Matin/Soir des postes Membre de la Présidence
     * et Matronne sont pourvues pour le moment, le reste des postes/Shifts
     * reste vacant tant que l'information n'est pas fournie.
     */
    public function run(): void
    {
        $organisation = Organisation::first();

        if (! $organisation) {
            return;
        }

        $affectations = [
            ['shift' => 'Mardi Matin', 'poste' => 'Membre de la Présidence', 'prenom' => 'Pdt', 'nom' => 'ALLEN'],
            ['shift' => 'Mardi Soir', 'poste' => 'Membre de la Présidence', 'prenom' => 'Pdt', 'nom' => 'AYEKOUE'],
            ['shift' => 'Mardi Matin', 'poste' => 'Matronne', 'prenom' => 'Sr', 'nom' => 'ALLEN'],
            ['shift' => 'Mardi Soir', 'poste' => 'Matronne', 'prenom' => 'Pdt', 'nom' => 'DEMENETIAUX'],
        ];

        foreach ($affectations as $affectation) {
            $shift = Shift::where('organisation_id', $organisation->id)
                ->where('nom', $affectation['shift'])
                ->first();

            if (! $shift) {
                continue;
            }

            $position = $shift->positions()->where('nom', $affectation['poste'])->first();

            if (! $position) {
                continue;
            }

            $servant = Servant::updateOrCreate(
                [
                    'organisation_id' => $organisation->id,
                    'nom' => $affectation['nom'],
                    'prenom' => $affectation['prenom'],
                ],
                ['statut' => 'actif']
            );

            if ($position->assignments()->where('statut', 'actif')->exists()) {
                continue;
            }

            $position->assignments()->create([
                'servant_id' => $servant->id,
                'date_debut' => now()->toDateString(),
                'statut' => 'actif',
            ]);
        }
    }
}
