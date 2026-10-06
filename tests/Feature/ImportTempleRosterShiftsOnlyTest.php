<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Organisation;
use App\Models\Pieu;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftPosition;
use App\Models\ShiftRecruitmentNeed;
use App\Models\ShiftTemplate;
use App\Models\ShiftTransferRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\PendingCommand;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * temple:import-roster --shifts-seulement, sur un fichier .xlsx entièrement
 * fictif généré dans le test (même structure que l'onglet "FINAL Liste").
 */
class ImportTempleRosterShiftsOnlyTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $organisation;

    private ShiftTemplate $template;

    private string $fichier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = Organisation::factory()->create();
        $this->template = ShiftTemplate::create(['organisation_id' => $this->organisation->id, 'nom' => 'Modèle fictif']);
        $this->template->positions()->create(['nom' => 'Servant', 'ordre' => 1]);
        $this->template->positions()->create(['nom' => 'Servante', 'ordre' => 2]);

        $this->fichier = $this->genererFichier();
    }

    protected function tearDown(): void
    {
        @unlink($this->fichier);

        parent::tearDown();
    }

    /**
     * 20 sections (5 jours × matin/soir × Frères/Sœurs), deux servant(e)s
     * fictif(ve)s par section, avec pieu et téléphone inventés.
     */
    private function genererFichier(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('FINAL Liste');

        $ligne = 1;
        $sheet->fromArray(['SHIFT', 'N°', 'NUM', 'NOM', 'PRENOMS', '', 'PIEU', 'TEL 1', 'TEL 2'], null, 'A'.$ligne++);

        $numero = 1;
        foreach (['MARDI', 'MERCREDI', 'JEUDI', 'VENDREDI', 'SAMEDI'] as $jour) {
            foreach (['MATIN', 'SOIR'] as $moment) {
                foreach (['FRERE', 'SOEUR'] as $genre) {
                    $sheet->fromArray(["{$jour} {$moment} {$genre}", null, null, '*'], null, 'A'.$ligne++);
                    for ($i = 1; $i <= 2; $i++) {
                        $sheet->fromArray(
                            ['', null, $numero, "FICTIF{$numero}", 'TEST', '', 'PIEUX IMAGINAIRE', '0100000'.$numero, '', 1, 1, 0, 1],
                            null,
                            'A'.$ligne++,
                        );
                        $numero++;
                    }
                }
            }
        }

        $chemin = tempnam(sys_get_temp_dir(), 'roster_fictif_').'.xlsx';
        (new Xlsx($spreadsheet))->save($chemin);

        return $chemin;
    }

    private function lancer(array $options = []): PendingCommand
    {
        return $this->artisan('temple:import-roster', [
            'file' => $this->fichier,
            '--organisation' => $this->organisation->id,
            ...$options,
        ]);
    }

    public function test_apercu_sans_force_n_ecrit_rien(): void
    {
        $this->lancer(['--shifts-seulement' => true])
            ->expectsOutputToContain('Aperçu uniquement')
            ->assertSuccessful();

        $this->assertSame(0, Shift::count());
        $this->assertSame(0, Servant::count());
    }

    public function test_avec_force_cree_uniquement_les_20_shifts(): void
    {
        $this->lancer(['--shifts-seulement' => true, '--force' => true])
            ->expectsOutputToContain('Import appliqué')
            ->expectsOutputToContain('0 (--shifts-seulement)')
            ->assertSuccessful();

        $shifts = Shift::where('organisation_id', $this->organisation->id)->get();
        $this->assertCount(20, $shifts);
        $this->assertTrue($shifts->every(fn (Shift $s) => $s->shift_template_id === $this->template->id));
        $this->assertSame(10, $shifts->filter->estSoeurs()->count());
        $this->assertTrue($shifts->contains('nom', 'Samedi Soir Sœurs'));
        $this->assertTrue($shifts->contains('nom', 'Mardi Matin Frères'));

        $this->assertSame(0, Servant::withTrashed()->count());
        $this->assertSame(0, ShiftPosition::withTrashed()->count());
        $this->assertSame(0, Assignment::count());
        $this->assertSame(0, Pieu::count());
        $this->assertSame(0, ShiftRecruitmentNeed::count());
        $this->assertSame(0, ShiftTransferRequest::withTrashed()->count());
    }

    public function test_relance_sans_doublon(): void
    {
        $this->lancer(['--shifts-seulement' => true, '--force' => true])->assertSuccessful();
        $ids = Shift::orderBy('id')->pluck('id')->all();

        $this->lancer(['--shifts-seulement' => true, '--force' => true])->assertSuccessful();

        $this->assertSame($ids, Shift::orderBy('id')->pluck('id')->all());
        $this->assertSame(0, ShiftPosition::withTrashed()->count());
    }

    public function test_n_altere_pas_les_donnees_existantes(): void
    {
        $shift = Shift::create([
            'organisation_id' => $this->organisation->id,
            'nom' => 'Mardi Matin Frères',
            'jour' => 'mardi',
            'heure_debut' => '07:00',
            'heure_fin' => '11:00',
        ]);
        $autreShift = Shift::create([
            'organisation_id' => $this->organisation->id,
            'nom' => 'Dimanche Fictif Frères',
            'jour' => 'dimanche',
            'heure_debut' => '07:00',
            'heure_fin' => '11:00',
        ]);
        $servant = Servant::factory()->create(['organisation_id' => $this->organisation->id, 'genre' => 'homme']);
        $position = $shift->positions()->create(['nom' => 'Servant', 'ordre' => 1]);
        Assignment::create(['shift_position_id' => $position->id, 'servant_id' => $servant->id, 'date_debut' => now()->toDateString(), 'statut' => 'actif']);
        ShiftRecruitmentNeed::create(['organisation_id' => $this->organisation->id, 'shift_id' => $shift->id, 'nombre_a_recruter' => 3]);

        $this->lancer(['--shifts-seulement' => true, '--force' => true])->assertSuccessful();

        $this->assertSame(21, Shift::count());
        $this->assertModelExists($autreShift);
        $this->assertSame('07:00', substr((string) $shift->fresh()->heure_debut, 0, 5));
        $this->assertNull($shift->fresh()->shift_template_id);
        $this->assertSame(1, Servant::count());
        $this->assertModelExists($servant);
        $this->assertSame(1, ShiftPosition::count());
        $this->assertSame(1, Assignment::count());
        $this->assertSame(3, ShiftRecruitmentNeed::sole()->nombre_a_recruter);
    }

    public function test_sans_option_le_comportement_reste_inchange(): void
    {
        $ancien = Shift::create([
            'organisation_id' => $this->organisation->id,
            'nom' => 'Dimanche Démo Frères',
            'jour' => 'dimanche',
            'heure_debut' => '07:00',
            'heure_fin' => '11:00',
        ]);

        $this->lancer(['--force' => true])
            ->expectsOutputToContain('Servant(e)s importé(e)s')
            ->assertSuccessful();

        $this->assertNull(Shift::withTrashed()->find($ancien->id));
        $this->assertSame(20, Shift::count());
        $this->assertSame(40, Servant::count());
        $this->assertSame(40, ShiftPosition::count());
        $this->assertSame(40, Assignment::count());
        $this->assertSame(1, Pieu::count());
    }
}
