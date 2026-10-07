<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Pieu;
use App\Models\Role;
use App\Models\Servant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ServantsPaginationTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $organisation;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = Organisation::factory()->create();
        $this->admin = $this->utilisateur('administrateur', $this->organisation);
    }

    private function utilisateur(string $slug, Organisation $organisation): User
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['nom' => $slug]);

        return User::factory()->create(['organisation_id' => $organisation->id, 'role_id' => $role->id]);
    }

    private function servant(array $attributs = [], ?Organisation $organisation = null): Servant
    {
        return Servant::factory()->create(array_merge([
            'organisation_id' => ($organisation ?? $this->organisation)->id,
            'statut' => 'actif',
        ], $attributs));
    }

    private function creerServants(int $nombre): void
    {
        for ($i = 1; $i <= $nombre; $i++) {
            $this->servant(['nom' => sprintf('Nom%03d', $i), 'prenom' => 'Pre']);
        }
    }

    public function test_la_liste_est_paginee_par_30(): void
    {
        $this->creerServants(65);

        $this->actingAs($this->admin)->get(route('servants.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Servants/Index')
                ->has('servants.data', 30)
                ->where('servants.total', 65)
                ->where('servants.data.0.nom', 'Nom001')
                ->where('servants.data.29.nom', 'Nom030'));

        $this->actingAs($this->admin)->get(route('servants.index', ['page' => 3]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('servants.data', 5)
                ->where('servants.data.0.nom', 'Nom061'));
    }

    public function test_page_hors_limites_redirige_vers_la_derniere_page_en_gardant_les_parametres(): void
    {
        $this->creerServants(35);

        $this->actingAs($this->admin)->get(route('servants.index', ['page' => 9, 'tri' => 'prenom', 'sens' => 'desc']))
            ->assertRedirect('/servants?page=2&tri=prenom&sens=desc');
    }

    public function test_les_liens_de_pagination_conservent_recherche_filtres_et_tri(): void
    {
        $this->creerServants(40);

        $this->actingAs($this->admin)->get(route('servants.index', ['recherche' => 'Nom', 'statut' => 'actif', 'tri' => 'nom', 'sens' => 'desc']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('servants.next_page_url', fn ($url) => str_contains($url, 'recherche=Nom')
                    && str_contains($url, 'statut=actif')
                    && str_contains($url, 'tri=nom')
                    && str_contains($url, 'sens=desc')
                    && str_contains($url, 'page=2')));
    }

    public function test_la_recherche_porte_sur_le_nom_et_le_prenom_sur_tout_le_jeu(): void
    {
        $this->creerServants(40);
        $cible = $this->servant(['nom' => 'Zzyzx', 'prenom' => 'Quentin']);
        $this->servant(['nom' => 'Autre', 'prenom' => 'Zzyzxette']);

        $this->actingAs($this->admin)->get(route('servants.index', ['recherche' => 'zzyzx']))
            ->assertInertia(fn (Assert $page) => $page->where('servants.total', 2));

        $this->actingAs($this->admin)->get(route('servants.index', ['recherche' => 'Quentin']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('servants.data', 1)
                ->where('servants.data.0.id', $cible->id)
                ->where('filtreRecherche', 'Quentin'));
    }

    public function test_la_recherche_traite_les_jokers_litteralement(): void
    {
        $this->servant(['nom' => 'Dupont']);
        $this->servant(['nom' => 'Dup%ont']);

        $this->actingAs($this->admin)->get(route('servants.index', ['recherche' => 'p%o']))
            ->assertInertia(fn (Assert $page) => $page->where('servants.total', 1));
    }

    public function test_filtres_statut_et_pieu(): void
    {
        $pieuA = Pieu::create(['organisation_id' => $this->organisation->id, 'nom' => 'Pieu A', 'type' => 'pieu']);
        $pieuB = Pieu::create(['organisation_id' => $this->organisation->id, 'nom' => 'Pieu B', 'type' => 'pieu']);
        $this->servant(['statut' => 'actif', 'pieu_id' => $pieuA->id]);
        $this->servant(['statut' => 'en_formation', 'pieu_id' => $pieuA->id]);
        $this->servant(['statut' => 'en_formation', 'pieu_id' => $pieuB->id]);

        $this->actingAs($this->admin)->get(route('servants.index', ['statut' => 'en_formation', 'pieu' => $pieuA->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('servants.total', 1)
                ->where('servants.data.0.pieu', 'Pieu A')
                ->where('servants.data.0.statut', 'en_formation')
                ->where('filtreStatut', 'en_formation')
                ->where('filtrePieu', $pieuA->id));

        // Valeur de statut inconnue : filtre ignoré.
        $this->actingAs($this->admin)->get(route('servants.index', ['statut' => 'inconnu']))
            ->assertInertia(fn (Assert $page) => $page->where('servants.total', 3)->where('filtreStatut', null));
    }

    public function test_tri_par_statut_suit_le_libelle_affiche(): void
    {
        $this->servant(['nom' => 'A', 'statut' => 'suspendu']);
        $this->servant(['nom' => 'B', 'statut' => 'recommande']);
        $this->servant(['nom' => 'C', 'statut' => 'retire']);
        $this->servant(['nom' => 'D', 'statut' => 'en_formation']);
        $this->servant(['nom' => 'E', 'statut' => 'actif']);

        $statuts = fn (string $sens) => $this->actingAs($this->admin)
            ->get(route('servants.index', ['tri' => 'statut', 'sens' => $sens]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tri', ['cle' => 'statut', 'sens' => $sens])
                ->where('servants.data', fn ($lignes) => collect($lignes)->pluck('statut')->all() === ($sens === 'asc'
                    // Ancien, Nouveau, Permutant, Recommandé, Relevé
                    ? ['actif', 'en_formation', 'retire', 'recommande', 'suspendu']
                    : ['suspendu', 'recommande', 'retire', 'en_formation', 'actif'])));

        $statuts('asc');
        $statuts('desc');
    }

    public function test_tri_par_pieu_et_par_prenom_est_stable(): void
    {
        $pieuA = Pieu::create(['organisation_id' => $this->organisation->id, 'nom' => 'Alpha', 'type' => 'pieu']);
        $pieuZ = Pieu::create(['organisation_id' => $this->organisation->id, 'nom' => 'Zeta', 'type' => 'pieu']);
        $this->servant(['nom' => 'Un', 'prenom' => 'Paul', 'pieu_id' => $pieuZ->id]);
        $this->servant(['nom' => 'Deux', 'prenom' => 'Anne', 'pieu_id' => $pieuA->id]);

        $this->actingAs($this->admin)->get(route('servants.index', ['tri' => 'pieu', 'sens' => 'desc']))
            ->assertInertia(fn (Assert $page) => $page->where('servants.data.0.pieu', 'Zeta'));

        $this->actingAs($this->admin)->get(route('servants.index', ['tri' => 'prenom', 'sens' => 'asc']))
            ->assertInertia(fn (Assert $page) => $page->where('servants.data.0.prenom', 'Anne'));
    }

    public function test_tri_invalide_ignore_et_tri_par_defaut_nom_prenom_id(): void
    {
        $b = $this->servant(['nom' => 'Martin', 'prenom' => 'Bob']);
        $a = $this->servant(['nom' => 'Martin', 'prenom' => 'Alice']);
        $c = $this->servant(['nom' => 'Martin', 'prenom' => 'Alice']);
        $this->servant(['nom' => 'Abel', 'prenom' => 'Zoe']);

        $this->actingAs($this->admin)->get(route('servants.index', ['tri' => 'telephone; drop table', 'sens' => 'sideways']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tri', ['cle' => null, 'sens' => 'asc'])
                ->where('servants.data', fn ($lignes) => collect($lignes)->pluck('id')->slice(1)->values()->all() === [$a->id, $c->id, $b->id]));
    }

    public function test_compteurs_et_pieux_portent_sur_toute_lorganisation(): void
    {
        $pieu = Pieu::create(['organisation_id' => $this->organisation->id, 'nom' => 'Pieu Z', 'type' => 'pieu']);
        Pieu::create(['organisation_id' => $this->organisation->id, 'nom' => 'Pieu vide', 'type' => 'pieu']);
        $autre = Organisation::factory()->create();
        $pieuAutre = Pieu::create(['organisation_id' => $autre->id, 'nom' => 'Pieu étranger', 'type' => 'pieu']);
        $this->creerServants(35);
        $this->servant(['statut' => 'en_formation', 'pieu_id' => $pieu->id]);
        $this->servant(['statut' => 'recommande']);
        $this->servant(['statut' => 'suspendu']);
        $this->servant(['statut' => 'actif'], $autre);
        $this->servant(['pieu_id' => $pieuAutre->id], $autre);

        $this->actingAs($this->admin)->get(route('servants.index', ['statut' => 'suspendu', 'recherche' => 'zzz']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('servants.total', 0)
                ->where('compteurs', ['actifs' => 35, 'en_formation' => 1, 'recommandes' => 1, 'suspendus' => 1])
                ->where('pieux', fn ($pieux) => collect($pieux)->pluck('nom')->all() === ['Pieu Z']));
    }

    public function test_vue_recommandes_paginee_comme_la_liste(): void
    {
        for ($i = 1; $i <= 32; $i++) {
            $this->servant(['nom' => sprintf('Rec%03d', $i), 'statut' => 'recommande']);
        }
        $this->servant(['nom' => 'Ancien', 'statut' => 'actif']);

        $this->actingAs($this->admin)->get(route('servants.nouveaux'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('nouveaux', true)
                ->has('servants.data', 30)
                ->where('servants.total', 32));

        // Le filtre statut de l'URL est sans effet sur la vue Recommandés.
        $this->actingAs($this->admin)->get(route('servants.nouveaux', ['statut' => 'actif', 'page' => 2, 'tri' => 'nom', 'sens' => 'desc']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('servants.data', 2)
                ->where('filtreStatut', null)
                ->where('servants.data.0.nom', 'Rec002'));

        $this->actingAs($this->admin)->get(route('servants.nouveaux', ['page' => 5]))
            ->assertRedirect('/servants/nouveaux?page=2');
    }

    public function test_isolation_par_organisation_et_aucune_donnee_personnelle_en_props(): void
    {
        $autre = Organisation::factory()->create();
        $this->servant(['nom' => 'Mien', 'telephone' => '0102030405']);
        $this->servant(['nom' => 'Etranger'], $autre);

        $reponse = $this->actingAs($this->admin)->get(route('servants.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('servants.total', 1)
                ->has('servants.data.0', fn (Assert $s) => $s->hasAll(['id', 'nom', 'prenom', 'statut', 'pieu'])->etc()));

        $this->assertStringNotContainsString('0102030405', $reponse->getContent());
        $this->assertStringNotContainsString('Etranger', $reponse->getContent());
    }

    public function test_droits_inchanges_secretaire_autres_et_coordonnateur(): void
    {
        $this->servant();

        $this->actingAs($this->utilisateur('secretaire', $this->organisation))->get(route('servants.index'))->assertOk();
        $this->actingAs($this->utilisateur('autres', $this->organisation))->get(route('servants.index'))->assertOk();
        $this->actingAs($this->utilisateur('coordonnateur_equipe', $this->organisation))->get(route('servants.index'))->assertForbidden();
    }
}
