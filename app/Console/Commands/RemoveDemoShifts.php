<?php

namespace App\Console\Commands;

use App\Models\Organisation;
use App\Models\Shift;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Supprime les anciens shifts de démonstration (créés avant l'import du
 * fichier maître, nommés sans "Frères"/"Sœurs", ex. "Mardi Matin"), en
 * conservant les 20 vrais shifts importés. La suppression est réelle
 * (forceDelete) : les postes, membres, affectations et besoins de
 * recrutement liés à ces shifts partent avec (cascade FK) — les fiches
 * Servant elles-mêmes ne sont jamais touchées, elles perdent juste leur
 * affectation à ces anciens shifts.
 */
class RemoveDemoShifts extends Command
{
    protected $signature = 'temple:remove-demo-shifts
        {--organisation= : ID de l\'organisation cible (par défaut : la première)}
        {--force : Applique réellement la suppression (sinon la transaction est annulée)}';

    protected $description = "Supprime les anciens shifts de démonstration (sans 'Frères'/'Sœurs' dans le nom), en conservant les 20 vrais shifts importés";

    public function handle(): int
    {
        $organisationId = (int) ($this->option('organisation') ?: Organisation::query()->value('id'));
        if (! $organisationId) {
            $this->error('Aucune organisation trouvée. Précisez --organisation=ID.');

            return self::FAILURE;
        }

        $shifts = Shift::where('organisation_id', $organisationId)
            ->where('nom', 'not like', '%Frères%')
            ->where('nom', 'not like', '%Sœurs%')
            ->withCount(['positions', 'membresActifs'])
            ->get();

        if ($shifts->isEmpty()) {
            $this->info("Aucun shift de démonstration trouvé (tous les shifts contiennent déjà 'Frères' ou 'Sœurs').");

            return self::SUCCESS;
        }

        $this->table(
            ['Shift', 'Postes (+ affectations)', 'Membres de coordination'],
            $shifts->map(fn (Shift $s) => [$s->nom, $s->positions_count, $s->membresActifs_count]),
        );

        $applique = false;

        try {
            DB::transaction(function () use ($shifts, &$applique) {
                Shift::whereIn('id', $shifts->pluck('id'))->forceDelete();

                if ($this->option('force')) {
                    $applique = true;

                    return;
                }

                throw new \RuntimeException('__DRY_RUN__');
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== '__DRY_RUN__') {
                throw $e;
            }
        }

        $this->newLine();
        $this->info($applique
            ? "Supprimé (--force) : {$shifts->count()} shift(s) de démonstration."
            : 'Aperçu uniquement (transaction annulée — relancez avec --force pour appliquer).');

        return self::SUCCESS;
    }
}
