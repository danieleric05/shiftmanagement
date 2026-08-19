<?php

namespace App\Console\Commands;

use App\Models\Organisation;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftTransferRequest;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importe l'historique des relèves et des changements/permutations de shift
 * depuis les deux fichiers Word "actualisés", en les rattachant aux servants
 * et shifts déjà importés par temple:import-roster. Les lignes dont le
 * servant ou le shift ne peut pas être identifié avec certitude sont
 * ignorées et listées en fin de commande — jamais rattachées au hasard.
 */
class ImportTempleHistory extends Command
{
    protected $signature = 'temple:import-history
        {changements : Chemin vers FICHE ACTUALISEE DES CHANGEMENTS DE SHIFTS DES SERVANTS (.docx)}
        {releves : Chemin vers LISTE ACTUALISEE DES SERVANTS RELEVES PAR SHIFT (.docx)}
        {--organisation= : ID de l\'organisation cible (par défaut : la première)}
        {--date-defaut= : Date à utiliser pour les changements de shift, qui n\'ont pas de date par ligne (AAAA-MM-JJ)}
        {--force : Applique réellement les changements (sinon la transaction est annulée)}';

    protected $description = "Importe l'historique des relèves et permutations depuis les fichiers Word, en les rattachant aux servants/shifts déjà importés";

    private array $joursMap = [
        'MARDI' => 'mardi', 'MERCREDI' => 'mercredi', 'JEUDI' => 'jeudi',
        'VENDREDI' => 'vendredi', 'VEND' => 'vendredi', 'SAMEDI' => 'samedi',
    ];

    /** @var array<int, array{id: int, mots: array<int, string>}> */
    private array $index = [];

    private array $shiftCache = [];

    private array $stats = ['permutations' => 0, 'releves' => 0, 'servants_crees' => 0];

    private array $nonApparies = [];

    public function handle(): int
    {
        $cheminChangements = $this->argument('changements');
        $cheminReleves = $this->argument('releves');

        foreach ([$cheminChangements, $cheminReleves] as $chemin) {
            if (! is_file($chemin)) {
                $this->error("Fichier introuvable : {$chemin}");

                return self::FAILURE;
            }
        }

        $organisationId = (int) ($this->option('organisation') ?: Organisation::query()->value('id'));
        if (! $organisationId) {
            $this->error('Aucune organisation trouvée. Précisez --organisation=ID.');

            return self::FAILURE;
        }

        $demandeurId = User::where('organisation_id', $organisationId)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['administrateur', 'super_admin']))
            ->value('id');
        if (! $demandeurId) {
            $this->error('Aucun administrateur trouvé pour enregistrer ces demandes historiques.');

            return self::FAILURE;
        }

        $dateDefaut = $this->option('date-defaut') ?: now()->toDateString();

        $this->construireIndexServants($organisationId);

        $applique = false;

        try {
            DB::transaction(function () use ($cheminChangements, $cheminReleves, $organisationId, $demandeurId, $dateDefaut, &$applique) {
                $this->importerChangements($cheminChangements, $organisationId, $demandeurId, $dateDefaut);
                $this->importerReleves($cheminReleves, $organisationId, $demandeurId);

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

        $this->afficherResume($applique);

        return self::SUCCESS;
    }

    private function construireIndexServants(int $organisationId): void
    {
        Servant::where('organisation_id', $organisationId)->get(['id', 'nom', 'prenom'])
            ->each(function (Servant $s) {
                $this->index[] = [
                    'id' => $s->id,
                    'mots' => $this->motsNormalises("{$s->nom} {$s->prenom}"),
                ];
            });
    }

    /** @return array<int, string> */
    private function motsNormalises(string $valeur): array
    {
        $valeur = str_replace(['Sr.', 'Fr.', 'Sr ', 'Fr ', "'"], ' ', $valeur);
        $valeur = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valeur) ?: $valeur;
        $valeur = strtoupper($valeur);
        $mots = preg_split('/[^A-Z]+/', $valeur, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique($mots));
    }

    /**
     * Un nom de fichier Word correspond à un servant si l'ensemble de ses
     * mots normalisés est entièrement inclus dans l'ensemble des mots du
     * servant (ou l'inverse) — et qu'un seul servant satisfait ce critère.
     */
    private function trouverServant(string $nomBrut): ?int
    {
        $mots = $this->motsNormalises($nomBrut);
        if ($mots === []) {
            return null;
        }

        $candidats = array_filter($this->index, function ($entree) use ($mots) {
            $motsManquants = array_diff($mots, $entree['mots']);
            $motsEnPlus = array_diff($entree['mots'], $mots);

            return $motsManquants === [] || $motsEnPlus === [];
        });

        return count($candidats) === 1 ? array_values($candidats)[0]['id'] : null;
    }

    /**
     * Crée une fiche servant minimale (nom/prénom + statut retiré) pour une
     * personne relevée absente du roster actuel, et l'ajoute à l'index pour
     * qu'une mention ultérieure du même nom dans le document soit réutilisée
     * plutôt que dupliquée.
     */
    private function creerServantMinimal(string $nomBrut, int $organisationId, Shift $shift): int
    {
        $nomBrut = trim(str_replace(['Sr.', 'Fr.'], '', $nomBrut));
        if (str_contains($nomBrut, ',')) {
            [$nom, $prenom] = array_map('trim', explode(',', $nomBrut, 2));
        } else {
            $mots = explode(' ', $nomBrut, 2);
            $nom = trim($mots[0]);
            $prenom = trim($mots[1] ?? '');
        }

        $servant = Servant::create([
            'organisation_id' => $organisationId,
            'nom' => $nom !== '' ? $nom : $nomBrut,
            'prenom' => $prenom !== '' ? $prenom : '—',
            'genre' => str_contains($shift->nom, 'Sœurs') ? 'femme' : 'homme',
            'statut' => 'retire',
            'notes' => "Fiche créée automatiquement depuis l'historique des relèves (import) — aucune autre donnée disponible dans le fichier source.",
        ]);

        $this->stats['servants_crees']++;
        $this->index[] = ['id' => $servant->id, 'mots' => $this->motsNormalises($nomBrut)];

        return $servant->id;
    }

    private function trouverShift(string $label): ?Shift
    {
        $cle = strtoupper(trim($label));
        if (isset($this->shiftCache[$cle])) {
            return $this->shiftCache[$cle];
        }

        $texte = strtoupper($label);
        $jour = null;
        foreach ($this->joursMap as $abbrev => $valeur) {
            if (str_starts_with($texte, $abbrev)) {
                $jour = $valeur;
                break;
            }
        }

        if ($jour === null) {
            return $this->shiftCache[$cle] = null;
        }

        $moment = str_contains($texte, 'MAT') ? 'Matin' : (str_contains($texte, 'SOIR') ? 'Soir' : null);
        $genre = (str_contains($texte, 'SR') || str_contains($texte, 'SOEUR') || str_contains($texte, 'SŒUR')) ? 'Sœurs'
            : ((str_contains($texte, 'FR') || str_contains($texte, 'FRERE')) ? 'Frères' : null);

        if ($moment === null || $genre === null) {
            return $this->shiftCache[$cle] = null;
        }

        $nom = ucfirst($jour)." {$moment} {$genre}";
        $shift = Shift::where('nom', $nom)->first();

        return $this->shiftCache[$cle] = $shift;
    }

    /**
     * FICHE ACTUALISEE DES CHANGEMENTS DE SHIFTS DES SERVANTS : un tableau
     * unique, colonnes Servants | Contact | Equipe | coordonnateur | contact
     * | Nouveau shift | coordonnateur | contact. Les lignes à une seule
     * cellule non vide (ex. "MARDI") ne sont que des séparateurs de jour.
     */
    private function importerChangements(string $path, int $organisationId, int $demandeurId, string $dateDefaut): void
    {
        foreach ($this->extraireLignesTableau($path) as $cells) {
            $cells = array_pad($cells, 8, '');
            [$servantBrut, , $equipe, , , $nouveauShift] = $cells;

            if (trim($servantBrut) === '' || trim($equipe) === '' || trim($nouveauShift) === '') {
                continue;
            }

            $servantId = $this->trouverServant($servantBrut);
            $shift = $this->trouverShift($equipe);
            $shiftDestination = $this->trouverShift($nouveauShift);

            if (! $servantId || ! $shift || ! $shiftDestination) {
                $this->nonApparies[] = "[Changement] {$servantBrut} | {$equipe} -> {$nouveauShift}";

                continue;
            }

            ShiftTransferRequest::create([
                'organisation_id' => $organisationId,
                'type' => 'permutation',
                'shift_id' => $shift->id,
                'shift_destination_id' => $shiftDestination->id,
                'servant_id' => $servantId,
                'demandeur_id' => $demandeurId,
                'motif' => 'Changement de shift (import historique — période Juin-Juillet 2026)',
                'date_demande' => $dateDefaut,
                'approuve_deux_shifts' => true,
                'statut' => 'traitee',
                'resultat' => 'Changement effectué',
                'resultat_date' => $dateDefaut,
                'decideur_id' => $demandeurId,
            ]);

            $this->stats['permutations']++;
        }
    }

    /**
     * LISTE ACTUALISEE DES SERVANTS RELEVES PAR SHIFT : colonnes No | Equipes
     * | Membre | Nouveau statut | Date d'effet | TIS. "Equipes" n'est rempli
     * que sur la première ligne de chaque shift — on le propage aux lignes
     * suivantes tant qu'il n'est pas redéfini.
     */
    private function importerReleves(string $path, int $organisationId, int $demandeurId): void
    {
        $equipeCourante = null;

        foreach ($this->extraireLignesTableau($path) as $cells) {
            $cells = array_pad($cells, 6, '');
            [, $equipe, $membre, $nouveauStatut, $dateEffet, $tis] = $cells;

            if (trim($equipe) !== '') {
                $equipeCourante = $equipe;
            }

            if (trim($membre) === '' || trim($dateEffet) === '') {
                continue;
            }

            $shift = $equipeCourante ? $this->trouverShift($equipeCourante) : null;
            $date = $this->parserDate($dateEffet);

            if (! $shift || ! $date) {
                $this->nonApparies[] = "[Relève] {$membre} | {$equipeCourante} | {$dateEffet}";

                continue;
            }

            // Une personne relevée n'apparaît normalement plus dans le roster actuel :
            // on crée une fiche minimale plutôt que d'abandonner l'historique.
            $servantId = $this->trouverServant($membre) ?? $this->creerServantMinimal($membre, $organisationId, $shift);

            $traitee = strtoupper(trim($tis)) === 'OK';

            ShiftTransferRequest::create([
                'organisation_id' => $organisationId,
                'type' => 'releve',
                'shift_id' => $shift->id,
                'servant_id' => $servantId,
                'demandeur_id' => $demandeurId,
                'motif' => 'Relève (import historique)',
                'date_demande' => $date,
                'statut' => $traitee ? 'traitee' : 'en_attente',
                'resultat' => $traitee ? trim($nouveauStatut ?: 'Relevé(e)') : null,
                'resultat_date' => $traitee ? $date : null,
                'decideur_id' => $traitee ? $demandeurId : null,
            ]);

            $this->stats['releves']++;
        }
    }

    private function parserDate(string $valeur): ?string
    {
        $valeur = trim(preg_replace('/[\s\x{00A0}]+/u', '', $valeur));
        if (! preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $valeur, $m)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }

    /** @return array<int, array<int, string>> */
    private function extraireLignesTableau(string $path): array
    {
        $zip = new \ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $dom = new \DOMDocument;
        $dom->loadXML($xml);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $lignes = [];
        foreach ($xpath->query('//w:tbl/w:tr') as $tr) {
            $cellules = [];
            foreach ($xpath->query('.//w:tc', $tr) as $tc) {
                $texte = '';
                foreach ($xpath->query('.//w:t', $tc) as $t) {
                    $texte .= $t->textContent;
                }
                $cellules[] = trim(preg_replace('/\s+/', ' ', $texte));
            }
            $lignes[] = $cellules;
        }

        // Ignore la ligne d'en-tête de colonnes et les séparateurs de jour (une seule cellule non vide).
        return array_slice(array_filter($lignes, fn ($c) => count(array_filter($c, fn ($v) => $v !== '')) > 1), 1);
    }

    private function afficherResume(bool $applique): void
    {
        $this->newLine();
        $this->info($applique ? 'Import appliqué (--force).' : 'Aperçu uniquement (transaction annulée — relancez avec --force pour appliquer).');
        $this->table(['Élément', 'Total'], [
            ['Permutations importées', $this->stats['permutations']],
            ['Relèves importées', $this->stats['releves']],
            ['  dont fiches servant créées (personnes relevées absentes du roster)', $this->stats['servants_crees']],
            ['Lignes non appariées (ignorées)', count($this->nonApparies)],
        ]);

        if ($this->nonApparies !== []) {
            $this->warn('Lignes ignorées (servant ou shift non identifié avec certitude) :');
            foreach ($this->nonApparies as $ligne) {
                $this->line("  - {$ligne}");
            }
        }
    }
}
