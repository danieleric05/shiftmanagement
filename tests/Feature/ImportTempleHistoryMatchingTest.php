<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Organisation;
use App\Models\Role;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftPosition;
use App\Models\ShiftTransferRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Appariement des noms de temple:import-history (noms entièrement fictifs).
 */
class ImportTempleHistoryMatchingTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $organisation;

    /** @var array<string, Shift> */
    private array $shifts = [];

    /** @var array<int, string> */
    private array $fichiers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = Organisation::factory()->create();
        $role = Role::factory()->create(['slug' => 'administrateur', 'nom' => 'Administrateur']);
        User::factory()->create(['organisation_id' => $this->organisation->id, 'role_id' => $role->id]);

        foreach (['mardi' => 'Mardi Matin Frères', 'jeudi' => 'Jeudi Matin Frères', 'samedi' => 'Samedi Matin Frères', 'vendredi' => 'Vendredi Soir Sœurs'] as $jour => $nom) {
            $this->shifts[$nom] = Shift::create([
                'organisation_id' => $this->organisation->id,
                'nom' => $nom,
                'jour' => $jour,
                'heure_debut' => '08:00',
                'heure_fin' => '12:00',
            ]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->fichiers as $fichier) {
            @unlink($fichier);
        }

        parent::tearDown();
    }

    private function servant(string $nom, string $prenom, string $shiftNom): Servant
    {
        $servant = Servant::factory()->create([
            'organisation_id' => $this->organisation->id,
            'nom' => $nom,
            'prenom' => $prenom,
        ]);
        $position = ShiftPosition::create(['shift_id' => $this->shifts[$shiftNom]->id, 'nom' => 'Servant', 'ordre' => 1]);
        Assignment::create([
            'shift_position_id' => $position->id,
            'servant_id' => $servant->id,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        return $servant;
    }

    /** @param array<int, array<int, string>> $lignes */
    private function docx(array $lignes): string
    {
        $xmlLignes = '';
        foreach ($lignes as $cellules) {
            $xmlLignes .= '<w:tr>';
            foreach ($cellules as $c) {
                $xmlLignes .= '<w:tc><w:p><w:r><w:t>'.htmlspecialchars($c, ENT_XML1).'</w:t></w:r></w:p></w:tc>';
            }
            $xmlLignes .= '</w:tr>';
        }

        $chemin = tempnam(sys_get_temp_dir(), 'hist').'.docx';
        $zip = new \ZipArchive;
        $zip->open($chemin, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:tbl>'
            .$xmlLignes.'</w:tbl></w:body></w:document>');
        $zip->close();

        return $this->fichiers[] = $chemin;
    }

    private function changements(array $lignes): string
    {
        $entete = ['Servants', 'Contact', 'Equipe', 'coordonnateur', 'contact', 'Nouveau shift', 'coordonnateur', 'contact'];

        return $this->docx(array_merge([$entete], array_map(
            fn ($l) => [$l[0], '', $l[1], '', '', $l[2], '', ''],
            $lignes,
        )));
    }

    private function releves(array $lignes = []): string
    {
        $entete = ['No', 'Equipes', 'Membre', 'Nouveau statut', "Date d'effet", 'TIS'];
        // Le fichier doit contenir au moins une ligne de données après l'en-tête.
        $lignes = $lignes ?: [['1', 'VENDREDI SOIR SR', '', '', '', '']];

        return $this->docx(array_merge([$entete], $lignes));
    }

    private function importer(string $changements, ?string $releves = null): void
    {
        $code = Artisan::call('temple:import-history', [
            'changements' => $changements,
            'releves' => $releves ?? $this->releves(),
            '--organisation' => $this->organisation->id,
            '--date-defaut' => '2026-07-01',
            '--force' => true,
        ]);

        $this->assertSame(0, $code);
    }

    private function permutationDe(Servant $servant): ?ShiftTransferRequest
    {
        return ShiftTransferRequest::where('type', 'permutation')->where('servant_id', $servant->id)->first();
    }

    public function test_une_lettre_d_ecart_est_toleree_si_le_candidat_est_dans_le_shift_d_origine(): void
    {
        $servant = $this->servant('Tanoe', 'Mirabelle Hugo', 'Mardi Matin Frères');

        $this->importer($this->changements([['Tanoé Mirabele Hugo', 'MARDI MAT FR', 'JEUDI MAT FR']]));

        $permutation = $this->permutationDe($servant);
        $this->assertNotNull($permutation);
        $this->assertSame($this->shifts['Mardi Matin Frères']->id, $permutation->shift_id);
        $this->assertSame($this->shifts['Jeudi Matin Frères']->id, $permutation->shift_destination_id);
    }

    public function test_une_initiale_de_prenom_est_toleree_si_le_candidat_est_dans_le_shift_de_destination(): void
    {
        $servant = $this->servant('Brou Kalidou', 'Fernand', 'Jeudi Matin Frères');

        $this->importer($this->changements([['Brou Kalidou. F', 'MARDI MAT FR', 'JEUDI MAT FR']]));

        $this->assertNotNull($this->permutationDe($servant));
    }

    public function test_le_titre_soeur_est_retire_avant_comparaison(): void
    {
        $servante = $this->servant('Ahoua', 'Nadege', 'Vendredi Soir Sœurs');

        $this->importer($this->changements([['Sœur Ahoua Nadège', 'VENDREDI SOIR SR', 'MARDI MAT FR']]));

        $this->assertNotNull($this->permutationDe($servante));
    }

    public function test_deux_candidats_tolerants_rendent_la_ligne_ignoree(): void
    {
        $a = $this->servant('Gnamien', 'Clarisse Ines', 'Mardi Matin Frères');
        $b = $this->servant('Gnamien', 'Clarisse Irene', 'Mardi Matin Frères');

        $this->importer($this->changements([['Gnamien Clarisse I', 'MARDI MAT FR', 'JEUDI MAT FR']]));

        $this->assertSame(0, ShiftTransferRequest::count());
        $this->assertStringContainsString('[Changement] Gnamien Clarisse I', Artisan::output());
    }

    public function test_deux_candidats_stricts_ne_declenchent_pas_le_repli(): void
    {
        $this->servant('Kacou', 'Ange Marius', 'Mardi Matin Frères');
        $this->servant('Kacou', 'Ange Mathis', 'Mardi Matin Frères');

        $this->importer($this->changements([['Kacou Ange', 'MARDI MAT FR', 'JEUDI MAT FR']]));

        $this->assertSame(0, ShiftTransferRequest::count());
    }

    public function test_un_candidat_unique_hors_des_shifts_de_la_ligne_est_refuse(): void
    {
        $this->servant('Diby Serafin', 'Joel', 'Samedi Matin Frères');

        $this->importer($this->changements([['Diby Serafin. J', 'MARDI MAT FR', 'JEUDI MAT FR']]));

        $this->assertSame(0, ShiftTransferRequest::count());
        $this->assertStringContainsString('[Changement] Diby Serafin. J', Artisan::output());
    }

    public function test_une_relance_ne_cree_aucun_doublon(): void
    {
        $servant = $this->servant('Tanoe', 'Mirabelle Hugo', 'Mardi Matin Frères');
        $changements = $this->changements([['Tanoe Mirabelle Hugo', 'MARDI MAT FR', 'JEUDI MAT FR']]);
        $releves = $this->releves([
            ['1', 'VENDREDI SOIR SR', 'Sr. Kone, Edwige', 'Relevée', '15/06/2026', 'OK'],
            ['2', '', 'Yao Bintou', 'Relevée', '20/06/2026', ''],
        ]);

        $this->importer($changements, $releves);
        $this->assertSame(1, ShiftTransferRequest::where('type', 'permutation')->count());
        $this->assertSame(2, ShiftTransferRequest::where('type', 'releve')->count());
        $servantsApresPremierImport = Servant::count();

        $this->importer($changements, $releves);

        $this->assertSame(1, ShiftTransferRequest::where('type', 'permutation')->where('servant_id', $servant->id)->count());
        $this->assertSame(2, ShiftTransferRequest::where('type', 'releve')->count());
        $this->assertSame($servantsApresPremierImport, Servant::count());
        $this->assertMatchesRegularExpression('/déjà présentes.*\|\s*3\s*\|/u', Artisan::output());
    }

    public function test_une_relance_importe_seulement_la_nouvelle_ligne_appariee(): void
    {
        $existant = $this->servant('Tanoe', 'Mirabelle Hugo', 'Mardi Matin Frères');
        $changements = $this->changements([['Tanoe Mirabelle Hugo', 'MARDI MAT FR', 'JEUDI MAT FR']]);
        $this->importer($changements);

        $nouveau = $this->servant('Brou Kalidou', 'Fernand', 'Mardi Matin Frères');
        $this->importer($this->changements([
            ['Tanoe Mirabelle Hugo', 'MARDI MAT FR', 'JEUDI MAT FR'],
            ['Brou Kalidou. F', 'MARDI MAT FR', 'JEUDI MAT FR'],
        ]));

        $this->assertSame(1, ShiftTransferRequest::where('servant_id', $existant->id)->count());
        $this->assertSame(1, ShiftTransferRequest::where('servant_id', $nouveau->id)->count());
    }
}
