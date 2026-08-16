<?php

namespace App\Console\Commands;

use App\Models\Interview;
use App\Notifications\EntretienRappel;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:envoyer-rappels-entretiens')]
#[Description('Notifie les coordinateurs des entretiens planifiés pour le lendemain')]
class EnvoyerRappelsEntretiens extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $entretiens = Interview::where('statut', 'planifie')
            ->whereDate('date_entretien', now()->addDay()->toDateString())
            ->with(['candidate', 'planifiePar'])
            ->get();

        foreach ($entretiens as $entretien) {
            $entretien->planifiePar->notify(new EntretienRappel($entretien));
        }

        $this->info("{$entretiens->count()} rappel(s) d'entretien envoyé(s).");
    }
}
