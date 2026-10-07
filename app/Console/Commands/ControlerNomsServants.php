<?php

namespace App\Console\Commands;

use App\Models\Servant;
use App\Support\NomSuspect;
use Illuminate\Console\Command;

/**
 * Liste, en lecture seule, les fiches dont le nom ou le prénom paraît anormal
 * (ex. « Bla :-) »). Aucune donnée n'est modifiée.
 */
class ControlerNomsServants extends Command
{
    protected $signature = 'temple:controler-noms {--organisation= : ID de l\'organisation (par défaut : toutes)}';

    protected $description = 'Liste (lecture seule) les servant(e)s dont le nom ou le prénom semble suspect (smiley, chiffre, symbole...)';

    public function handle(): int
    {
        $lignes = [];

        Servant::query()
            ->when($this->option('organisation'), fn ($q, $id) => $q->where('organisation_id', (int) $id))
            ->orderBy('id')
            ->each(function (Servant $servant) use (&$lignes): void {
                foreach (['nom' => 'Nom', 'prenom' => 'Prénom'] as $champ => $libelle) {
                    $raison = NomSuspect::raison($servant->{$champ});
                    if ($raison !== null) {
                        $lignes[] = [$servant->id, $libelle, (string) $servant->{$champ}, $raison, trim($servant->prenom.' '.$servant->nom)];
                    }
                }
            });

        if ($lignes === []) {
            $this->info('Aucun nom suspect.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Champ', 'Valeur', 'Raison', 'Fiche'], $lignes);
        $this->warn(count($lignes).' valeur(s) à vérifier. Corrigez-les depuis la fiche du servant(e) (rien n\'a été modifié).');

        return self::SUCCESS;
    }
}
