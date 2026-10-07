<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftMember;
use App\Models\ShiftTransferRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Refonte des tableaux (phase 2) : tri serveur de Changement et des relevés
 * (liste blanche, conservation des filtres et de la pagination), modale du
 * recrutement (droits et enregistrement inchangés), props des rapports.
 */
class TableauxChangementRecrutementTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $organisation;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = Organisation::factory()->create();
        $this->admin = $this->makeUser('administrateur');
    }

    private function makeUser(string $slug): User
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['nom' => $slug, 'gere_shifts' => $slug === 'coordonnateur_equipe']);

        return User::factory()->create(['organisation_id' => $this->organisation->id, 'role_id' => $role->id]);
    }

    private function makeShift(string $nom): Shift
    {
        return Shift::create([
            'organisation_id' => $this->organisation->id, 'nom' => $nom,
            'jour' => 'mardi', 'heure_debut' => '07:00', 'heure_fin' => '11:00', 'statut' => 'actif',
        ]);
    }

    private function demande(array $attributs): ShiftTransferRequest
    {
        return ShiftTransferRequest::create([
            'organisation_id' => $this->organisation->id,
            'demandeur_id' => $this->admin->id,
            'motif' => 'Motif',
            'statut' => 'en_attente',
            ...$attributs,
        ]);
    }

    /**
     * Trois demandes en attente aux valeurs distinctes sur chaque colonne triable.
     *
     * @return array{a: ShiftTransferRequest, b: ShiftTransferRequest, c: ShiftTransferRequest}
     */
    private function jeuDeDemandes(): array
    {
        $alpha = $this->makeShift('Alpha');
        $bravo = $this->makeShift('Bravo');
        $charlie = $this->makeShift('Charlie');

        $servantA = Servant::factory()->create(['organisation_id' => $this->organisation->id, 'nom' => 'Bamba', 'prenom' => 'Awa', 'genre' => 'homme']);
        $servantB = Servant::factory()->create(['organisation_id' => $this->organisation->id, 'nom' => 'Coulibaly', 'prenom' => 'Ali', 'genre' => 'homme']);
        $servantC = Servant::factory()->create(['organisation_id' => $this->organisation->id, 'nom' => 'Aka', 'prenom' => 'Jean', 'genre' => 'homme']);

        return [
            // a : relève, Bravo, Bamba, 2026-01-02
            'a' => $this->demande(['type' => 'releve', 'shift_id' => $bravo->id, 'servant_id' => $servantA->id, 'date_demande' => '2026-01-02']),
            // b : permutation, Charlie, Coulibaly, 2026-01-03
            'b' => $this->demande(['type' => 'permutation', 'shift_id' => $charlie->id, 'shift_destination_id' => $alpha->id, 'servant_id' => $servantB->id, 'date_demande' => '2026-01-03']),
            // c : appel, Alpha, Aka, 2026-01-01
            'c' => $this->demande(['type' => 'appel', 'shift_id' => $alpha->id, 'servant_id' => $servantC->id, 'date_demande' => '2026-01-01']),
        ];
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function trisDemandes(): array
    {
        return [
            'date_demande' => ['date_demande', ['c', 'a', 'b']],
            'type' => ['type', ['c', 'b', 'a']],
            'servant' => ['servant', ['c', 'a', 'b']],
            'shift' => ['shift', ['c', 'a', 'b']],
        ];
    }

    /**
     * @param  list<string>  $ordreAsc
     */
    #[DataProvider('trisDemandes')]
    public function test_changement_trie_chaque_colonne_dans_les_deux_sens(string $cle, array $ordreAsc): void
    {
        $demandes = $this->jeuDeDemandes();

        foreach (['asc' => $ordreAsc, 'desc' => array_reverse($ordreAsc)] as $sens => $ordre) {
            $ids = array_map(fn (string $k) => $demandes[$k]->id, $ordre);

            $this->actingAs($this->admin)
                ->get("/transferts?tri={$cle}&sens={$sens}")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('ShiftTransfers/Index')
                    ->where('tri', ['cle' => $cle, 'sens' => $sens])
                    ->where('demandes.data', fn ($data) => collect($data)->pluck('id')->all() === $ids));
        }
    }

    public function test_changement_tri_par_statut_accepte(): void
    {
        $this->jeuDeDemandes();

        $this->actingAs($this->admin)
            ->get('/transferts?tri=statut&sens=desc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tri', ['cle' => 'statut', 'sens' => 'desc'])
                ->has('demandes.data', 3));
    }

    public function test_changement_ordre_par_defaut_date_de_demande_decroissante(): void
    {
        $d = $this->jeuDeDemandes();

        $this->actingAs($this->admin)
            ->get('/transferts')
            ->assertInertia(fn (Assert $page) => $page
                ->where('tri', ['cle' => null, 'sens' => 'asc'])
                ->where('demandes.data', fn ($data) => collect($data)->pluck('id')->all() === [$d['b']->id, $d['a']->id, $d['c']->id]));
    }

    public function test_changement_cle_hors_liste_blanche_ou_sens_invalide_ignores(): void
    {
        $d = $this->jeuDeDemandes();
        $parDefaut = [$d['b']->id, $d['a']->id, $d['c']->id];

        foreach (['/transferts?tri=motif;drop&sens=asc', '/transferts?tri=organisation_id', '/transferts?tri[]=type'] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('tri.cle', null)
                    ->where('demandes.data', fn ($data) => collect($data)->pluck('id')->all() === $parDefaut));
        }

        // Sens invalide : la clé est conservée, le sens retombe sur « asc ».
        $this->actingAs($this->admin)->get('/transferts?tri=servant&sens=DROP')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('tri', ['cle' => 'servant', 'sens' => 'asc']));
    }

    public function test_changement_tri_conserve_type_recherche_et_pagination(): void
    {
        $shift = $this->makeShift('Shift Long');

        for ($i = 1; $i <= 32; $i++) {
            $servant = Servant::factory()->create(['organisation_id' => $this->organisation->id, 'nom' => sprintf('Kone%02d', $i), 'prenom' => 'Test']);
            $this->demande(['type' => 'releve', 'shift_id' => $shift->id, 'servant_id' => $servant->id, 'date_demande' => '2026-02-01']);
        }
        $autre = Servant::factory()->create(['organisation_id' => $this->organisation->id, 'nom' => 'Kone99', 'prenom' => 'Appel']);
        $this->demande(['type' => 'appel', 'shift_id' => $shift->id, 'servant_id' => $autre->id, 'date_demande' => '2026-02-01']);

        $this->actingAs($this->admin)
            ->get('/transferts?type=releve&recherche=Kone&tri=servant&sens=desc&page=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filtreType', 'releve')
                ->where('filtreRecherche', 'Kone')
                ->where('tri', ['cle' => 'servant', 'sens' => 'desc'])
                ->where('demandes.total', 32)
                ->has('demandes.data', 2)
                ->where('demandes.data.0.servant', 'Test Kone02')
                ->where('demandes.data.1.servant', 'Test Kone01')
                ->where('demandes.links', fn ($links) => collect($links)
                    ->pluck('url')->filter()
                    ->every(fn ($url) => str_contains($url, 'type=releve') && str_contains($url, 'recherche=Kone')
                        && str_contains($url, 'tri=servant') && str_contains($url, 'sens=desc'))));
    }

    public function test_coordonnateur_garde_son_perimetre_avec_le_tri(): void
    {
        $d = $this->jeuDeDemandes();
        $coordo = $this->makeUser('coordonnateur_equipe');
        ShiftMember::create([
            'shift_id' => $d['b']->shift_id, 'user_id' => $coordo->id, 'role_id' => $coordo->role_id,
            'date_debut' => now()->toDateString(), 'statut' => 'actif',
        ]);

        $this->actingAs($coordo)->get('/transferts?tri=shift&sens=asc')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('demandes.data', 1)
                ->where('demandes.data.0.id', $d['b']->id));
    }

    // ---- Servant(e)s relevé(e)s ----

    /**
     * @return array{a: ShiftTransferRequest, b: ShiftTransferRequest, c: ShiftTransferRequest}
     */
    private function jeuDeReleves(): array
    {
        $alpha = $this->makeShift('Alpha');
        $bravo = $this->makeShift('Bravo');
        $charlie = $this->makeShift('Charlie');

        $releve = fn (Shift $shift, string $nom, string $date) => $this->demande([
            'type' => 'releve', 'shift_id' => $shift->id,
            'servant_id' => Servant::factory()->create(['organisation_id' => $this->organisation->id, 'nom' => $nom, 'prenom' => 'X'])->id,
            'date_demande' => '2026-01-01', 'statut' => 'traitee', 'resultat' => 'Relevé', 'resultat_date' => $date,
        ]);

        return [
            'a' => $releve($bravo, 'Bamba', '2026-03-02'),
            'b' => $releve($charlie, 'Coulibaly', '2026-03-03'),
            'c' => $releve($alpha, 'Aka', '2026-03-01'),
        ];
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function trisReleves(): array
    {
        return [
            'resultat_date' => ['resultat_date', ['c', 'a', 'b']],
            'servant' => ['servant', ['c', 'a', 'b']],
            'shift' => ['shift', ['c', 'a', 'b']],
        ];
    }

    /**
     * @param  list<string>  $ordreAsc
     */
    #[DataProvider('trisReleves')]
    public function test_releves_trie_chaque_colonne_dans_les_deux_sens(string $cle, array $ordreAsc): void
    {
        $releves = $this->jeuDeReleves();

        foreach (['asc' => $ordreAsc, 'desc' => array_reverse($ordreAsc)] as $sens => $ordre) {
            $ids = array_map(fn (string $k) => $releves[$k]->id, $ordre);

            $this->actingAs($this->admin)
                ->get("/transferts/releves?tri={$cle}&sens={$sens}")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('ShiftTransfers/Releves')
                    ->where('tri', ['cle' => $cle, 'sens' => $sens])
                    ->where('releves.data', fn ($data) => collect($data)->pluck('id')->all() === $ids));
        }
    }

    public function test_releves_ordre_par_defaut_et_liste_blanche(): void
    {
        $r = $this->jeuDeReleves();
        $parDefaut = [$r['b']->id, $r['a']->id, $r['c']->id];

        foreach (['/transferts/releves', '/transferts/releves?tri=motif', '/transferts/releves?tri=date_demande&sens=asc'] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('tri.cle', null)
                    ->where('releves.data', fn ($data) => collect($data)->pluck('id')->all() === $parDefaut));
        }
    }

    public function test_releves_pagination_conserve_le_tri(): void
    {
        $shift = $this->makeShift('Shift Long');
        for ($i = 1; $i <= 31; $i++) {
            $this->demande([
                'type' => 'releve', 'shift_id' => $shift->id,
                'servant_id' => Servant::factory()->create(['organisation_id' => $this->organisation->id, 'nom' => sprintf('Kone%02d', $i), 'prenom' => 'X'])->id,
                'date_demande' => '2026-01-01', 'statut' => 'traitee', 'resultat' => 'Relevé', 'resultat_date' => '2026-03-01',
            ]);
        }

        $this->actingAs($this->admin)->get('/transferts/releves?tri=servant&sens=asc&page=2')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('releves.data', 1)
                ->where('releves.data.0.servant', 'X Kone31')
                ->where('releves.links', fn ($links) => collect($links)->pluck('url')->filter()
                    ->every(fn ($url) => str_contains($url, 'tri=servant') && str_contains($url, 'sens=asc'))));
    }

    // ---- Recrutement (modale) ----

    public function test_recrutement_enregistrement_via_la_modale_inchange(): void
    {
        $shift = $this->makeShift('Alpha');

        $this->actingAs($this->admin)
            ->put("/recrutement/{$shift->id}", ['nombre_a_recruter' => 3, 'echeance' => '2026-12-01', 'notes' => 'Urgent'])
            ->assertRedirect();

        $this->assertDatabaseHas('shift_recruitment_needs', ['shift_id' => $shift->id, 'nombre_a_recruter' => 3, 'notes' => 'Urgent']);

        $this->actingAs($this->admin)->get('/recrutement')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Recruitment/Index')
                ->where('shifts.0.nombre_a_recruter', 3)
                ->where('shifts.0.echeance', '2026-12-01')
                ->where('shifts.0.notes', 'Urgent')
                ->where('compteurs.total_a_recruter', 3));
    }

    public function test_recrutement_droits_inchanges(): void
    {
        $shift = $this->makeShift('Alpha');
        $autres = $this->makeUser('autres');
        $coordo = $this->makeUser('coordonnateur_equipe');

        // Rôle « Autres » : lecture seule.
        $this->actingAs($autres)->get('/recrutement')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('auth.lectureSeule', true)->has('shifts', 1));
        $this->actingAs($autres)->put("/recrutement/{$shift->id}", ['nombre_a_recruter' => 2])->assertForbidden();

        // Coordonnateur sans shift géré : ni liste ni modification.
        $this->actingAs($coordo)->get('/recrutement')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('shifts', 0));
        $this->actingAs($coordo)->put("/recrutement/{$shift->id}", ['nombre_a_recruter' => 2])->assertForbidden();

        $this->assertDatabaseCount('shift_recruitment_needs', 0);
    }

    public function test_recrutement_validation_inchangee(): void
    {
        $shift = $this->makeShift('Alpha');

        $this->actingAs($this->admin)
            ->put("/recrutement/{$shift->id}", ['nombre_a_recruter' => -1])
            ->assertSessionHasErrors('nombre_a_recruter');
    }

    // ---- Rapports ----

    public function test_rapports_props_inchangees(): void
    {
        $this->makeShift('Alpha');

        $this->actingAs($this->admin)->get('/rapports')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Index')
                ->has('servantsParStatut', 5)
                ->has('remplissageShifts', 1, fn (Assert $s) => $s
                    ->where('nom', 'Alpha')
                    ->where('jour', 'mardi')
                    ->where('postes_total', 0)
                    ->where('postes_vacants', 0)
                    ->where('taux_remplissage', null))
                ->has('avancementFormation', fn (Assert $a) => $a
                    ->has('total_etapes')
                    ->has('etapes_terminees')
                    ->has('taux_avancement')));
    }
}
