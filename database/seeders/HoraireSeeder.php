<?php

namespace Database\Seeders;

use App\Models\Horaire;
use App\Models\Organisation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HoraireSeeder extends Seeder
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

        $horaires = [
            ['nom' => 'Matin', 'heure_debut' => '07:00', 'heure_fin' => '11:00'],
            ['nom' => 'Soir', 'heure_debut' => '11:00', 'heure_fin' => '19:00'],
        ];

        Horaire::where('organisation_id', $organisation->id)
            ->whereNotIn('nom', array_column($horaires, 'nom'))
            ->delete();

        foreach ($horaires as $horaire) {
            Horaire::updateOrCreate(
                ['organisation_id' => $organisation->id, 'nom' => $horaire['nom']],
                $horaire
            );
        }
    }
}
