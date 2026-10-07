<?php

namespace Tests\Feature;

use App\Models\Assignment;
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
 * Refonte des tableaux (phase 2) : tri serveur de la liste des Shifts (liste
 * blanche, conservation de la recherche, du jour et de la pagination) ;
 * props des tableaux de bord et droits inchangés.
 */
class TableauxShiftsDashboardTest extends TestCase
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

    private function role(string $slug): Role
    {
        return Role::firstOrCreate(['slug' => $slug], ['nom' => $slug, 'gere_shifts' => $slug === 'coordonnateur_equipe']);
    }

    private function makeUser(string $roleSlug, ?Organisation $organisation = null): User
    {
        return User::factory()->create([
            'organisation_id' => ($organisation ?? $this->organisation)->id,
            'role_id' => $this->role($roleSlug)->id,
        ]);
    }

    private function makeShift(string $nom, string $jour = 'mardi', string $heure = '07:00', ?Organisation $organisation = null): Shift
    {
        return Shift::create([
            'organisation_id' => ($organisation ?? $this->organisation)->id,
            'nom' => $nom,
            'jour' => $jour,
            'heure_debut' => $heure,
            'heure_fin' => '23:00',
            'statut' => 'actif',
        ]);
    }

    /**
     * Trois Shifts aux valeurs distinctes sur chaque colonne triable.
     */
    private function jeuDeShifts(): void
    {
        $this->makeShift('Alpha Sœurs', 'mercredi', '09:00');
        $this->makeShift('Bravo', 'lundi', '14:00');
        $this->makeShift('Charlie', 'vendredi', '07:00');
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: list<string>}>
     */
    public static function trisShifts(): array
    {
        return [
            'jour croissant' => ['jour', 'asc', ['Bravo', 'Alpha Sœurs', 'Charlie']],
            'jour décroissant' => ['jour', 'desc', ['Charlie', 'Alpha Sœurs', 'Bravo']],
            'nom croissant' => ['nom', 'asc', ['Alpha Sœurs', 'Bravo', 'Charlie']],
            'nom décroissant' => ['nom', 'desc', ['Charlie', 'Bravo', 'Alpha Sœurs']],
            'heure croissante' => ['heure', 'asc', ['Charlie', 'Alpha Sœurs', 'Bravo']],
            'heure décroissante' => ['heure', 'desc', ['Bravo', 'Alpha Sœurs', 'Charlie']],
            // Frères avant Sœurs ; à genre égal, ordre du calendrier.
            'genre croissant' => ['genre', 'asc', ['Bravo', 'Charlie', 'Alpha Sœurs']],
            'genre décroissant' => ['genre', 'desc', ['Alpha Sœurs', 'Bravo', 'Charlie']],
        ];
    }

    /**
     * @param  list<string>  $attendu
     */
    #[DataProvider('trisShifts')]
    public function test_tri_serveur_des_shifts_par_chaque_cle_et_dans_les_deux_sens(string $cle, string $sens, array $attendu): void
    {
        $this->jeuDeShifts();

        $this->actingAs($this->admin)->get("/shifts?tri={$cle}&sens={$sens}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Shifts/Index')
                ->where('tri', ['cle' => $cle, 'sens' => $sens])
                ->where('shifts.data', fn ($data) => collect($data)->pluck('nom')->all() === $attendu));
    }

    public function test_ordre_par_defaut_inchange_jour_du_calendrier_puis_heure(): void
    {
        $this->jeuDeShifts();
        $this->makeShift('Delta', 'lundi', '08:00');

        $this->actingAs($this->admin)->get('/shifts')
            ->assertInertia(fn (Assert $page) => $page
                ->where('tri', ['cle' => null, 'sens' => 'asc'])
                ->where('shifts.data', fn ($data) => collect($data)->pluck('nom')->all() === ['Delta', 'Bravo', 'Alpha Sœurs', 'Charlie']));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function trisRefuses(): array
    {
        return [
            'colonne hors liste blanche' => ['tri=postes_total&sens=desc'],
            'injection SQL' => ['tri=nom;drop%20table%20shifts&sens=desc'],
            'colonne technique' => ['tri=id&sens=desc'],
            'tableau' => ['tri[]=nom&sens=desc'],
        ];
    }

    #[DataProvider('trisRefuses')]
    public function test_tri_hors_liste_blanche_ignore_ordre_par_defaut(string $query): void
    {
        $this->jeuDeShifts();

        $this->actingAs($this->admin)->get("/shifts?{$query}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tri', ['cle' => null, 'sens' => 'asc'])
                ->where('shifts.data', fn ($data) => collect($data)->pluck('nom')->all() === ['Bravo', 'Alpha Sœurs', 'Charlie']));

        $this->assertDatabaseCount('shifts', 3);
    }

    public function test_sens_invalide_retombe_sur_croissant(): void
    {
        $this->jeuDeShifts();

        $this->actingAs($this->admin)->get('/shifts?tri=nom&sens=sideways')
            ->assertInertia(fn (Assert $page) => $page
                ->where('tri', ['cle' => 'nom', 'sens' => 'asc'])
                ->where('shifts.data.0.nom', 'Alpha Sœurs'));
    }

    public function test_recherche_jour_et_tri_conserves_dans_la_pagination(): void
    {
        foreach (range(1, 25) as $i) {
            $this->makeShift(sprintf('Shift Lundi %02d', $i), 'lundi', sprintf('%02d:00', $i % 24));
        }
        $this->makeShift('Shift Mardi', 'mardi');

        $this->actingAs($this->admin)->get('/shifts?recherche=Lundi&jour=lundi&tri=nom&sens=desc')
            ->assertInertia(fn (Assert $page) => $page
                ->where('shifts.total', 25)
                ->has('shifts.data', 20)
                ->where('shifts.data.0.nom', 'Shift Lundi 25')
                ->where('filtreRecherche', 'Lundi')
                ->where('filtreJour', 'lundi')
                ->where('tri', ['cle' => 'nom', 'sens' => 'desc'])
                ->where('shifts.next_page_url', fn ($url) => str_contains($url, 'recherche=Lundi')
                    && str_contains($url, 'jour=lundi')
                    && str_contains($url, 'tri=nom')
                    && str_contains($url, 'sens=desc'))
                ->where('shifts.links', fn ($links) => collect($links)->filter(fn ($l) => $l['url'] !== null)
                    ->every(fn ($l) => str_contains($l['url'], 'tri=nom') && str_contains($l['url'], 'sens=desc'))));

        $this->actingAs($this->admin)->get('/shifts?recherche=Lundi&jour=lundi&tri=nom&sens=desc&page=2')
            ->assertInertia(fn (Assert $page) => $page
                ->has('shifts.data', 5)
                ->where('shifts.data.4.nom', 'Shift Lundi 01'));

        // Page hors limites : redirection vers la dernière page en conservant recherche, jour et tri.
        $this->actingAs($this->admin)->get('/shifts?recherche=Lundi&jour=lundi&tri=nom&sens=desc&page=9')
            ->assertRedirect('/shifts?recherche=Lundi&jour=lundi&tri=nom&sens=desc&page=2');
    }

    public function test_ordre_stable_entre_les_pages_a_valeurs_egales(): void
    {
        foreach (range(1, 25) as $i) {
            $this->makeShift("Shift {$i}", 'jeudi', '10:00');
        }

        $ids = collect([1, 2])->flatMap(function (int $page) {
            $props = $this->actingAs($this->admin)->get("/shifts?tri=jour&sens=desc&page={$page}")->viewData('page')['props'];

            return collect($props['shifts']['data'])->pluck('id');
        });

        $this->assertCount(25, $ids);
        $this->assertCount(25, $ids->unique());
    }

    public function test_droits_inchanges_sur_la_liste_et_la_fiche_des_shifts(): void
    {
        $shift = $this->makeShift('Shift Mardi');
        $position = $shift->positions()->create(['nom' => 'Servant', 'ordre' => 1]);
        $servant = Servant::factory()->create(['organisation_id' => $this->organisation->id, 'genre' => 'homme', 'statut' => 'actif']);

        $autres = $this->makeUser('autres');
        $coordonnateur = $this->makeUser('coordonnateur_equipe');
        $sansRole = User::factory()->create(['organisation_id' => $this->organisation->id, 'role_id' => null]);

        // Liste triée : administrateur et « Autres » seulement.
        $this->actingAs($autres)->get('/shifts?tri=nom&sens=desc')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('tri', ['cle' => 'nom', 'sens' => 'desc']));
        $this->actingAs($coordonnateur)->get('/shifts?tri=nom')->assertForbidden();
        $this->actingAs($sansRole)->get('/shifts?tri=nom')->assertForbidden();

        // Affectation à un rôle vacant (fenêtre « Affecter ») : administrateur uniquement.
        $this->actingAs($autres)->post("/shifts/{$shift->id}/postes/{$position->id}/affectation", ['servant_id' => $servant->id])->assertForbidden();
        $this->actingAs($coordonnateur)->post("/shifts/{$shift->id}/postes/{$position->id}/affectation", ['servant_id' => $servant->id])->assertForbidden();
        $this->assertDatabaseMissing('assignments', ['shift_position_id' => $position->id]);

        $this->actingAs($this->admin)->post("/shifts/{$shift->id}/postes/{$position->id}/affectation", ['servant_id' => $servant->id])->assertRedirect();
        $this->assertDatabaseHas('assignments', ['shift_position_id' => $position->id, 'servant_id' => $servant->id, 'statut' => 'actif']);

        // Fiche d'un autre organisme : interdite.
        $autreOrganisation = Organisation::factory()->create();
        $etranger = $this->makeShift('Shift Étranger', organisation: $autreOrganisation);
        $this->actingAs($this->admin)->get("/shifts/{$etranger->id}")->assertForbidden();
        $this->actingAs($this->admin)->get('/shifts?tri=nom')
            ->assertInertia(fn (Assert $page) => $page->where('shifts.total', 1));
    }

    public function test_props_du_tableau_de_bord_conseil_inchangees(): void
    {
        $shift = $this->makeShift('Mardi Matin Sœurs');
        $destination = $this->makeShift('Jeudi Soir');
        $servant = Servant::factory()->create(['organisation_id' => $this->organisation->id, 'genre' => 'femme']);

        foreach (['releve', 'permutation', 'appel'] as $type) {
            ShiftTransferRequest::create([
                'organisation_id' => $this->organisation->id,
                'type' => $type,
                'shift_id' => $shift->id,
                'shift_destination_id' => $type === 'permutation' ? $destination->id : null,
                'servant_id' => $servant->id,
                'demandeur_id' => $this->admin->id,
                'motif' => 'Motif',
                'date_demande' => now()->toDateString(),
                'statut' => 'en_attente',
            ]);
        }

        $champsDemande = ['id', 'shift', 'shift_destination', 'servant', 'coordonnees', 'motif', 'date_demande', 'discussion_servant',
            'approuve_deux_shifts', 'statut', 'resultat', 'resultat_date', 'suivi_etat', 'pret_pour_decision'];

        $this->actingAs($this->admin)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Admin')
                ->has('shifts', 2)
                ->has('shifts.0', fn (Assert $s) => $s->hasAll(['id', 'nom', 'jour', 'heure_debut', 'heure_fin', 'postes_total', 'postes_vacants', 'gere', 'genre']))
                ->where('shifts.0.genre', 'soeurs')
                ->has('releves.recentes', 1)
                ->has('releves.recentes.0', fn (Assert $d) => $d->hasAll($champsDemande))
                ->where('releves.en_attente', 1)
                ->has('permutations.recentes.0', fn (Assert $d) => $d->hasAll($champsDemande))
                ->where('permutations.recentes.0.shift_destination', 'Jeudi Soir')
                ->has('appels.recentes', 1)
                ->has('besoins', fn (Assert $b) => $b->hasAll(['freres_recherches', 'soeurs_recherchees'])));

        // Le résultat d'une relève se saisit toujours par la même route (désormais depuis une fenêtre modale).
        $releve = ShiftTransferRequest::where('type', 'releve')->firstOrFail();
        $this->actingAs($this->makeUser('autres'))->patch(route('shift-transfers.resolve', $releve), [
            'resultat' => 'Accordée', 'resultat_date' => now()->toDateString(),
        ])->assertForbidden();
    }

    public function test_props_des_tableaux_de_bord_coordonnateur_et_servant_inchangees(): void
    {
        $shift = $this->makeShift('Mardi Matin');
        $coordonnateur = $this->makeUser('coordonnateur_equipe');
        ShiftMember::create([
            'shift_id' => $shift->id,
            'user_id' => $coordonnateur->id,
            'role_id' => $coordonnateur->role_id,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        $this->actingAs($coordonnateur)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/ChefEquipe')
                ->has('shifts', 1)
                ->where('shifts.0.gere', true)
                ->has('permutations', fn (Assert $p) => $p->hasAll(['en_attente', 'recentes']))
                ->has('besoins', fn (Assert $b) => $b->hasAll(['freres_recherches', 'soeurs_recherchees'])));

        $compte = User::factory()->create(['organisation_id' => $this->organisation->id, 'role_id' => null]);
        $servant = Servant::factory()->create(['organisation_id' => $this->organisation->id, 'user_id' => $compte->id]);
        $position = $shift->positions()->create(['nom' => 'Servant', 'ordre' => 1]);
        Assignment::create(['shift_position_id' => $position->id, 'servant_id' => $servant->id, 'date_debut' => now()->toDateString(), 'statut' => 'actif']);

        $this->actingAs($compte)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Servant')
                ->has('servant', fn (Assert $s) => $s->hasAll(['nom_complet', 'statut']))
                ->has('affectations', 1)
                ->has('affectations.0', fn (Assert $a) => $a->hasAll(['id', 'poste', 'shift', 'jour', 'heure_debut', 'heure_fin', 'depuis'])));
    }
}
