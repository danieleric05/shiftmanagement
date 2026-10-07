<?php

namespace App\Console\Commands;

use App\Models\Assignment;
use App\Models\Organisation;
use App\Models\Pieu;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftMember;
use App\Models\ShiftPosition;
use App\Models\ShiftRecruitmentNeed;
use App\Models\ShiftTemplate;
use App\Models\ShiftTransferRequest;
use App\Support\NomSuspect;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Importe le roster complet des servants (20 shifts) depuis le fichier maître
 * "LISTE GLOBALE DES SERVANTS DU TEMPLE" et remplace les shifts/servants de
 * démonstration existants. Le fichier source (noms, téléphones réels) n'est
 * jamais commité au dépôt : cette commande le lit à l'emplacement fourni.
 *
 * --shifts-seulement : ne crée QUE les shifts du fichier (Frères/Sœurs ×
 * mardi..samedi × matin/soir), sans aucun(e) servant(e). Le mode ne supprime
 * jamais rien (même comportement que --keep-existing) et reste idempotent
 * (firstOrCreate sur organisation + nom). Pour chaque shift créé :
 *  - créé : le Shift lui-même, rattaché au modèle de shift de l'organisation
 *    (shift_template_id) — c'est ce modèle qui fournit les postes proposés à
 *    l'affectation (Coordonnateur/trice, Servant(e), Scelleur...), comme pour
 *    un shift créé depuis l'application ;
 *  - non créé : aucun ShiftPosition (dans l'application, un poste n'existe
 *    qu'une fois occupé : ShiftController@assign le crée au moment de
 *    l'affectation — en pré-créer laisserait des postes vacants orphelins),
 *    aucune affectation, aucun ShiftRecruitmentNeed (l'écran Recrutement
 *    affiche 0 tant qu'aucun besoin n'est saisi), aucun pieu (les pieux ne
 *    viennent que des fiches servants), aucun titre de leadership (porté par
 *    les servants), aucune permutation/relève.
 */
class ImportTempleRoster extends Command
{
    protected $signature = 'temple:import-roster
        {file : Chemin vers LISTE GLOBALE DES SERVANTS DU TEMPLE (.xlsx)}
        {--organisation= : ID de l\'organisation cible (par défaut : la première)}
        {--keep-existing : Ajoute les shifts/servant(e)s du fichier sans toucher à ceux déjà en base}
        {--shifts-seulement : Crée uniquement les shifts du fichier, sans servant(e)s ; ne supprime jamais rien}
        {--force : Applique réellement les changements (sinon la transaction est annulée)}';

    protected $description = "Importe les 20 shifts et leurs servant(e)s depuis le fichier maître (remplace l'existant par défaut, --keep-existing pour ajouter sans toucher à ce qui existe déjà, --shifts-seulement pour ne créer que les shifts)";

    /** Abbréviations de pieu (colonne G) -> nom canonique, d'après l'onglet "By Stake" du fichier. */
    private array $pieuxMap = [
        'SELMER' => 'Selmer',
        'BASSAM' => 'Grand Bassam', 'GR BASSAM' => 'Grand Bassam', 'GR BASSM' => 'Grand Bassam', 'GRAND BASAM' => 'Grand Bassam',
        'NIANG S' => 'Niangon Sud',
        'ANONKO' => 'Anonkoua', 'ANONKOI' => 'Anonkoua',
        'COCODY' => 'Cocody',
        'TOIT ROUGE' => 'Toit-Rouge', 'TOIT ROUNGE' => 'Toit-Rouge', 'TOIT-ROU' => 'Toit-Rouge',
        'ABOBO E' => 'Abobo Est',
        'DOKUI' => 'Dokui',
        'YAMASOUKRO' => 'Yamoussoukro', 'YAMOUS' => 'Yamoussoukro',
        'NIANG C' => 'Niangon Centre', 'CENTRE' => 'Niangon Centre',
        'KOUMASSI' => 'Koumassi', 'KAWMASSI' => 'Koumassi',
        'YOPOUG' => 'Yopougon',
        'PORT BO' => 'Port-Bouët', 'PORT-BO' => 'Port-Bouët',
        'NIANG N' => 'Niangon Nord', 'NIANGON N' => 'Niangon Nord',
        'ABOBO O' => 'Abobo Ouest', 'ABOBO  W' => 'Abobo Ouest', 'ABOBO W' => 'Abobo Ouest',
        'DABOU' => 'Dabou District', 'DABOU D' => 'Dabou District',
        'ABOBO' => 'Abobo',
        'ATTIE' => 'Attié',
        'BELLE VILLE' => 'Belle Ville',
        'BOMOUNIN' => 'Bomounin',
        'BOUAKE' => 'Bouaké', 'ROUAKE' => 'Bouaké',
        'EBIMPE' => 'Ebimpé',
        '4 ETAGES' => '4 Étages',
        'NIANG 4' => 'Niangon 4',
        'YOPOUGON ATTIE' => 'Yopougon Attié',
    ];

    private array $joursMap = [
        'MARDI' => 'mardi', 'MERCREDI' => 'mercredi', 'JEUDI' => 'jeudi',
        'VENDREDI' => 'vendredi', 'SAMEDI' => 'samedi',
    ];

    private array $pieuxCache = [];

    private array $stats = [
        'shifts' => 0, 'servants' => 0, 'pieux_crees' => 0, 'pieux_inconnus' => [],
        'actifs' => 0, 'en_formation' => 0, 'shifts_lus' => [], 'servants_ignores' => 0,
    ];

    private function shiftsSeulement(): bool
    {
        return (bool) $this->option('shifts-seulement');
    }

    public function handle(): int
    {
        $path = $this->argument('file');
        if (! is_file($path)) {
            $this->error("Fichier introuvable : {$path}");

            return self::FAILURE;
        }

        $organisationId = (int) ($this->option('organisation') ?: Organisation::query()->value('id'));
        if (! $organisationId) {
            $this->error('Aucune organisation trouvée. Précisez --organisation=ID.');

            return self::FAILURE;
        }

        $this->info("Lecture de {$path}...");
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('FINAL Liste') ?? $spreadsheet->getSheet(0);
        $rows = $sheet->toArray(null, true, false, false);

        if ($this->shiftsSeulement()) {
            // Aucune combinaison n'est refusée : ce mode n'efface rien, il
            // neutralise donc le remplacement par défaut (comme --keep-existing).
            $this->comment('Mode --shifts-seulement : seuls les shifts sont créés, aucun(e) servant(e), rien n\'est supprimé.');
        }

        $applique = false;

        try {
            DB::transaction(function () use ($rows, $organisationId, &$applique) {
                if (! $this->option('keep-existing') && ! $this->shiftsSeulement()) {
                    $this->remplacerDonneesExistantes($organisationId);
                }
                $this->importerRoster($rows, $organisationId);

                if ($this->option('force')) {
                    $applique = true;

                    return;
                }

                // Pas de --force : on annule pour permettre une vérification en toute sécurité.
                throw new \RuntimeException('__DRY_RUN__');
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== '__DRY_RUN__') {
                throw $e;
            }
        }

        $this->afficherResume($applique);

        return self::SUCCESS;
    }

    /**
     * Supprime définitivement les shifts/servants existants de l'organisation
     * (et tout ce qui en dépend) avant de recharger le roster réel. Décision
     * validée avec l'utilisateur : les shifts/servants actuels ne sont que
     * des données de démonstration.
     */
    private function remplacerDonneesExistantes(int $organisationId): void
    {
        $shiftIds = Shift::withTrashed()->where('organisation_id', $organisationId)->pluck('id');
        $servantIds = Servant::withTrashed()->where('organisation_id', $organisationId)->pluck('id');

        Assignment::whereIn('shift_position_id', ShiftPosition::whereIn('shift_id', $shiftIds)->pluck('id'))->delete();
        ShiftPosition::whereIn('shift_id', $shiftIds)->delete();
        ShiftMember::whereIn('shift_id', $shiftIds)->delete();
        ShiftRecruitmentNeed::whereIn('shift_id', $shiftIds)->delete();
        ShiftTransferRequest::withTrashed()->whereIn('shift_id', $shiftIds)->forceDelete();
        DB::table('servant_workflow_steps')->whereIn('servant_id', $servantIds)->delete();
        Servant::withTrashed()->where('organisation_id', $organisationId)->forceDelete();
        Shift::withTrashed()->where('organisation_id', $organisationId)->forceDelete();
    }

    private function importerRoster(array $rows, int $organisationId): void
    {
        $currentShift = null;
        $ordrePosition = 0;

        foreach ($rows as $row) {
            $a = trim((string) ($row[0] ?? ''));
            $c = trim((string) ($row[2] ?? ''));
            $d = trim((string) ($row[3] ?? ''));

            if ($a !== '' && ! is_numeric($c) && $this->estEnteteDeShift($a)) {
                $currentShift = $this->resoudreShift($a, $organisationId);
                $this->stats['shifts_lus'][$currentShift->id] = true;
                $ordrePosition = 0;

                continue;
            }

            if ($currentShift === null || ! is_numeric($c) || $d === '') {
                continue;
            }

            if ($this->shiftsSeulement()) {
                $this->stats['servants_ignores']++;

                continue;
            }

            $ordrePosition++;
            $this->importerServant($row, $currentShift, $organisationId, $ordrePosition, $a);
        }
    }

    /**
     * Une ligne d'en-tête de section annonce un jour + un genre (ex.
     * "SAMEDI SOIR FRERE") sur une ligne sans numéro de servant (colonne C
     * vide) — contrairement au marqueur '*' en colonne D, présent sur la
     * plupart des sections mais absent d'au moins une (Samedi Soir Frère).
     */
    private function estEnteteDeShift(string $entete): bool
    {
        $texte = strtoupper($entete);
        $aUnJour = collect(array_keys($this->joursMap))->contains(fn ($jour) => str_contains($texte, $jour));

        return $aUnJour && (str_contains($texte, 'FRERE') || str_contains($texte, 'SOEUR'));
    }

    private function resoudreShift(string $entete, int $organisationId): Shift
    {
        $tokens = preg_split('/\s+/', strtoupper(trim($entete)));
        $jour = $this->joursMap[$tokens[0]] ?? 'mardi';
        $moment = str_contains($entete, 'MATIN') ? 'Matin' : 'Soir';
        $genre = str_contains(strtoupper($entete), 'SOEUR') ? 'Sœurs' : 'Frères';

        $heures = $moment === 'Matin' ? ['06:30', '12:30'] : ['12:30', '17:30'];
        $nom = ucfirst($jour)." {$moment} {$genre}";

        // firstOrCreate : une relance accidentelle de la commande (notamment avec
        // --keep-existing en production) ne doit pas dupliquer les shifts déjà créés.
        $shift = Shift::firstOrCreate(
            ['organisation_id' => $organisationId, 'nom' => $nom],
            [
                'jour' => $jour,
                'heure_debut' => $heures[0],
                'heure_fin' => $heures[1],
                'statut' => 'actif',
                'shift_template_id' => ShiftTemplate::where('organisation_id', $organisationId)->value('id'),
            ],
        );

        if ($shift->wasRecentlyCreated) {
            $this->stats['shifts']++;
        }

        return $shift;
    }

    private function importerServant(array $row, Shift $shift, int $organisationId, int $ordre, string $annotation): void
    {
        $nom = $this->normaliserNom((string) ($row[3] ?? ''));
        $prenom = $this->normaliserNom((string) ($row[4] ?? ''));
        $pieuAbbrev = trim((string) ($row[6] ?? ''));
        $phone1 = $this->normaliserTelephone((string) ($row[7] ?? ''));
        $phone2 = $this->normaliserTelephone((string) ($row[8] ?? ''));
        $appele = $this->versBool($row[9] ?? null);
        $wa1 = $this->versBool($row[10] ?? null);
        $wa2 = $this->versBool($row[11] ?? null);
        $form1 = $this->versBool($row[12] ?? null);
        $form2 = $this->versBool($row[13] ?? null);
        $form3 = $this->versBool($row[14] ?? null);
        $leader = trim((string) ($row[15] ?? '')) ?: null;
        $tech = is_numeric($row[16] ?? null) ? (int) $row[16] : null;
        $anglais = is_numeric($row[17] ?? null) ? (int) $row[17] : null;
        $choix2 = trim((string) ($row[18] ?? '')) ?: null;
        $notesBrutes = trim((string) ($row[19] ?? ''));

        foreach (['Nom' => $nom, 'Prénom' => $prenom] as $champ => $valeur) {
            if ($raison = NomSuspect::raison($valeur)) {
                $this->warn("Nom suspect (ligne importée quand même) : {$champ} « {$valeur} » ({$raison}).");
            }
        }

        $notes = trim(implode(' — ', array_filter([$notesBrutes, $annotation !== '' ? "Annotation import : {$annotation}" : null])));

        $genre = str_contains($shift->nom, 'Sœurs') ? 'femme' : 'homme';
        $statut = ($appele || $form1) ? 'actif' : 'en_formation';
        $this->stats[$statut === 'actif' ? 'actifs' : 'en_formation']++;

        $servant = Servant::create([
            'organisation_id' => $organisationId,
            'nom' => $nom,
            'prenom' => $prenom,
            'genre' => $genre,
            'telephone' => $phone1 ?: null,
            'telephone_appel' => $phone2 ?: null,
            'pieu_id' => $pieuAbbrev !== '' ? $this->resoudrePieu($pieuAbbrev, $organisationId) : null,
            'statut' => $statut,
            'titre_leadership' => $leader,
            'appele' => $appele,
            'whatsapp_1' => $wa1,
            'whatsapp_2' => $wa2,
            'formation_1' => $form1,
            'formation_2' => $form2,
            'formation_3' => $form3,
            'niveau_technique' => $tech,
            'niveau_anglais' => $anglais,
            'jour_alternatif' => $choix2,
            'notes' => $notes ?: null,
        ]);

        $this->stats['servants']++;

        $position = ShiftPosition::create([
            'shift_id' => $shift->id,
            'nom' => $genre === 'homme' ? 'Servant' : 'Servante',
            'ordre' => $ordre,
        ]);

        Assignment::create([
            'shift_position_id' => $position->id,
            'servant_id' => $servant->id,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);
    }

    private function resoudrePieu(string $abbrev, int $organisationId): int
    {
        $cle = strtoupper(trim($abbrev));
        if (isset($this->pieuxCache[$cle])) {
            return $this->pieuxCache[$cle];
        }

        $ville = $this->pieuxMap[$cle] ?? null;
        if ($ville === null) {
            $this->stats['pieux_inconnus'][$cle] = true;
            $ville = ucwords(strtolower($abbrev));
        }

        $pieu = Pieu::firstOrCreate(
            ['organisation_id' => $organisationId, 'nom' => $ville, 'type' => 'pieu'],
        );

        if ($pieu->wasRecentlyCreated) {
            $this->stats['pieux_crees']++;
        }

        return $this->pieuxCache[$cle] = $pieu->id;
    }

    /**
     * Les colonnes 0/1 du fichier source sont lues par PhpSpreadsheet tantôt
     * en bool natif, tantôt en entier/chaîne selon le formatage de la
     * cellule — on accepte les deux représentations.
     */
    private function versBool(mixed $valeur): bool
    {
        return $valeur === true || $valeur === 1 || $valeur === '1';
    }

    private function normaliserNom(string $valeur): string
    {
        $valeur = preg_replace('/\s+/', ' ', trim($valeur));

        return mb_convert_case($valeur, MB_CASE_TITLE, 'UTF-8');
    }

    private function normaliserTelephone(string $valeur): string
    {
        return trim(preg_replace('/\s+/', '', $valeur));
    }

    private function afficherResume(bool $applique): void
    {
        $this->newLine();
        $this->info($applique ? 'Import appliqué (--force).' : 'Aperçu uniquement (transaction annulée — relancez avec --force pour appliquer).');
        if ($this->shiftsSeulement()) {
            $this->table(['Élément', 'Total'], [
                ['Shifts lus dans le fichier', count($this->stats['shifts_lus'])],
                ['Shifts créés', $this->stats['shifts']],
                ['Shifts déjà présents (inchangés)', count($this->stats['shifts_lus']) - $this->stats['shifts']],
                ['Servant(e)s', '0 (--shifts-seulement)'],
                ['Lignes servant(e)s ignorées', $this->stats['servants_ignores']],
            ]);
            $this->line('Créé : shifts rattachés au modèle de shift (postes proposés à l\'affectation). Non créé : postes occupés, affectations, besoins de recrutement, pieux, permutations/relèves. Rien n\'a été supprimé.');

            return;
        }

        $this->table(['Élément', 'Total'], [
            ['Shifts créés', $this->stats['shifts']],
            ['Servant(e)s importé(e)s', $this->stats['servants']],
            ['  dont statut ancien', $this->stats['actifs']],
            ['  dont statut nouveau', $this->stats['en_formation']],
            ['Pieux créés', $this->stats['pieux_crees']],
        ]);

        if ($this->stats['pieux_inconnus'] !== []) {
            $this->warn('Abréviations de pieu non reconnues (utilisées telles quelles, à vérifier dans Paramètres > Pieux) : '.implode(', ', array_keys($this->stats['pieux_inconnus'])));
        }
    }
}
