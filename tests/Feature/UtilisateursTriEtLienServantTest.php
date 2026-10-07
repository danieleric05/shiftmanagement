<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Paramètres → Utilisateurs : tri serveur (liste blanche), délier un compte de
 * son servant(e) et suppression d'un compte lié (la fiche servant est conservée).
 */
class UtilisateursTriEtLienServantTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $organisation;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = Organisation::factory()->create();
        $this->admin = $this->makeUser('administrateur', ['name' => 'Admin Principal', 'nom' => 'Principal', 'prenom' => 'Admin']);
    }

    private function role(string $slug, ?string $nom = null, bool $gereShifts = false): Role
    {
        return Role::firstOrCreate(['slug' => $slug], ['nom' => $nom ?? $slug, 'gere_shifts' => $gereShifts]);
    }

    private function makeUser(string $roleSlug, array $attributs = [], ?Organisation $organisation = null): User
    {
        return User::factory()->create([
            'organisation_id' => ($organisation ?? $this->organisation)->id,
            'role_id' => $this->role($roleSlug)->id,
            ...$attributs,
        ]);
    }

    /**
     * Trois comptes aux valeurs distinctes sur chaque colonne triable.
     *
     * @return array{a: User, b: User, c: User}
     */
    private function jeuDeComptes(): array
    {
        $roleB = $this->role('role_bravo', 'Bravo', true);
        $roleC = $this->role('role_charlie', 'Charlie');
        $roleA = $this->role('role_alpha', 'Alpha');

        $a = User::factory()->create(['organisation_id' => $this->organisation->id, 'role_id' => $roleB->id, 'name' => 'Zoé Alpha', 'nom' => 'Alpha', 'prenom' => 'Zoé', 'email' => 'c-compte@example.com', 'statut' => 'recommande', 'acces_suspendu' => false]);
        $b = User::factory()->create(['organisation_id' => $this->organisation->id, 'role_id' => $roleC->id, 'name' => 'Yann Bravo', 'nom' => 'Bravo', 'prenom' => 'Yann', 'email' => 'a-compte@example.com', 'statut' => 'actif', 'acces_suspendu' => true]);
        $c = User::factory()->create(['organisation_id' => $this->organisation->id, 'role_id' => $roleA->id, 'name' => 'Xavier Charlie', 'nom' => 'Charlie', 'prenom' => 'Xavier', 'email' => 'b-compte@example.com', 'statut' => 'en_formation', 'acces_suspendu' => false]);

        // Shifts gérés actifs : a = 2, b = 0, c = 1 (un retrait terminé ne compte pas).
        $shifts = collect(['Shift 1', 'Shift 2'])->map(fn ($nom) => Shift::create([
            'organisation_id' => $this->organisation->id, 'nom' => $nom,
            'jour' => 'mardi', 'heure_debut' => '07:00', 'heure_fin' => '11:00', 'statut' => 'actif',
        ]));
        foreach ($shifts as $shift) {
            ShiftMember::create(['shift_id' => $shift->id, 'user_id' => $a->id, 'role_id' => $a->role_id, 'statut' => 'actif', 'date_debut' => now()->toDateString()]);
        }
        ShiftMember::create(['shift_id' => $shifts[0]->id, 'user_id' => $c->id, 'role_id' => $c->role_id, 'statut' => 'actif', 'date_debut' => now()->toDateString()]);
        ShiftMember::create(['shift_id' => $shifts[1]->id, 'user_id' => $b->id, 'role_id' => $b->role_id, 'statut' => 'termine', 'date_debut' => now()->toDateString()]);

        // Servants liés : a → « Mbappe », b → « Abel », c → aucun.
        Servant::factory()->create(['organisation_id' => $this->organisation->id, 'user_id' => $a->id, 'nom' => 'Mbappe', 'prenom' => 'Kylian']);
        Servant::factory()->create(['organisation_id' => $this->organisation->id, 'user_id' => $b->id, 'nom' => 'Abel', 'prenom' => 'Jean']);

        return compact('a', 'b', 'c');
    }

    /**
     * @return list<int>
     */
    private function ordre($response, array $comptes): array
    {
        $ids = collect($comptes)->map(fn (User $u) => $u->id)->flip();
        $lettres = collect($comptes)->mapWithKeys(fn (User $u, string $lettre) => [$u->id => $lettre]);

        return collect($response->viewData('page')['props']['users']['data'])
            ->pluck('id')
            ->filter(fn ($id) => $ids->has($id))
            ->map(fn ($id) => $lettres[$id])
            ->values()
            ->all();
    }

    public static function colonnesTriables(): array
    {
        return [
            'nom' => ['nom', ['a', 'b', 'c'], ['c', 'b', 'a']],
            'prénom' => ['prenom', ['c', 'b', 'a'], ['a', 'b', 'c']],
            'e-mail' => ['email', ['b', 'c', 'a'], ['a', 'c', 'b']],
            'rôle' => ['role', ['c', 'a', 'b'], ['b', 'a', 'c']],
            // Ancien (actif) < Nouveau (en_formation) < Recommandé (recommande).
            'statut' => ['statut', ['b', 'c', 'a'], ['a', 'c', 'b']],
            // Égalité départagée par le nom complet (Xavier Charlie < Zoé Alpha).
            'accès' => ['acces', ['c', 'a', 'b'], ['b', 'c', 'a']],
            'shifts gérés' => ['shifts', ['b', 'c', 'a'], ['a', 'c', 'b']],
            // Sans servant lié en premier dans l'ordre croissant (NULL).
            'servant lié' => ['servant', ['c', 'b', 'a'], ['a', 'b', 'c']],
        ];
    }

    #[DataProvider('colonnesTriables')]
    public function test_tri_serveur_sur_chaque_colonne_dans_les_deux_sens(string $cle, array $croissant, array $decroissant): void
    {
        $comptes = $this->jeuDeComptes();

        $asc = $this->actingAs($this->admin)->get("/parametres/utilisateurs?tri={$cle}&sens=asc")->assertOk();
        $this->assertSame($croissant, $this->ordre($asc, $comptes));
        $asc->assertInertia(fn (Assert $page) => $page->where('tri', ['cle' => $cle, 'sens' => 'asc']));

        $desc = $this->actingAs($this->admin)->get("/parametres/utilisateurs?tri={$cle}&sens=desc")->assertOk();
        $this->assertSame($decroissant, $this->ordre($desc, $comptes));
        $desc->assertInertia(fn (Assert $page) => $page->where('tri', ['cle' => $cle, 'sens' => 'desc']));
    }

    public function test_colonne_inconnue_ou_sens_invalide_retombent_sur_l_ordre_par_defaut(): void
    {
        $comptes = $this->jeuDeComptes();

        // Ordre par défaut : nom complet (name) croissant.
        $defaut = ['c', 'b', 'a'];

        foreach (['tri=password', 'tri=users.id;drop', 'tri[]=nom', 'tri=nom&sens=sideways', ''] as $requete) {
            $reponse = $this->actingAs($this->admin)->get("/parametres/utilisateurs?{$requete}")->assertOk();

            if ($requete === 'tri=nom&sens=sideways') {
                // Clé valide, sens invalide : sens croissant.
                $this->assertSame(['a', 'b', 'c'], $this->ordre($reponse, $comptes));
                $reponse->assertInertia(fn (Assert $page) => $page->where('tri', ['cle' => 'nom', 'sens' => 'asc']));

                continue;
            }

            $this->assertSame($defaut, $this->ordre($reponse, $comptes), "Requête : {$requete}");
            $reponse->assertInertia(fn (Assert $page) => $page->where('tri', ['cle' => null, 'sens' => 'asc']));
        }
    }

    public function test_la_pagination_conserve_tri_recherche_et_filtres(): void
    {
        $role = $this->role('membre_test', 'Membre test');
        User::factory()->count(35)->sequence(fn ($s) => ['email' => "dupont{$s->index}@example.com"])->create([
            'organisation_id' => $this->organisation->id,
            'role_id' => $role->id,
            'statut' => 'actif',
        ]);

        $reponse = $this->actingAs($this->admin)
            ->get("/parametres/utilisateurs?recherche=dupont&role={$role->id}&statut=actif&tri=email&sens=desc")
            ->assertOk();

        $props = $reponse->viewData('page')['props'];
        $this->assertSame(35, $props['users']['total']);
        $this->assertSame('dupont9@example.com', $props['users']['data'][0]['email']);

        $suivante = $props['users']['next_page_url'];
        foreach (['recherche=dupont', "role={$role->id}", 'statut=actif', 'tri=email', 'sens=desc', 'page=2'] as $fragment) {
            $this->assertStringContainsString($fragment, $suivante);
        }

        $page2 = $this->actingAs($this->admin)->get($suivante)->assertOk();
        $this->assertCount(5, $page2->viewData('page')['props']['users']['data']);
    }

    public function test_tri_stable_entre_les_pages_pour_des_valeurs_identiques(): void
    {
        $role = $this->role('membre_test', 'Membre test');
        User::factory()->count(40)->create([
            'organisation_id' => $this->organisation->id,
            'role_id' => $role->id,
            'statut' => 'actif',
        ]);

        $p1 = collect($this->actingAs($this->admin)->get('/parametres/utilisateurs?tri=statut')->viewData('page')['props']['users']['data'])->pluck('id');
        $p2 = collect($this->actingAs($this->admin)->get('/parametres/utilisateurs?tri=statut&page=2')->viewData('page')['props']['users']['data'])->pluck('id');

        $this->assertCount(0, $p1->intersect($p2));
        $this->assertSame(41, $p1->count() + $p2->count());
    }

    // ---------------------------------------------------------------
    // Délier un compte de son servant(e)
    // ---------------------------------------------------------------

    public function test_administrateur_delie_un_compte_de_son_servant(): void
    {
        $user = $this->makeUser('membre_test', ['name' => 'Jean Kouadio']);
        $servant = Servant::factory()->create(['organisation_id' => $this->organisation->id, 'user_id' => $user->id, 'nom' => 'Kouadio', 'prenom' => 'Jean']);

        $this->actingAs($this->admin)
            ->from('/parametres/utilisateurs')
            ->delete(route('settings.users.servant.unlink', $user))
            ->assertRedirect('/parametres/utilisateurs')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('servants', ['id' => $servant->id, 'user_id' => null, 'nom' => 'Kouadio']);

        $entree = Activity::where('event', 'deliaison_compte_servant')->sole();
        $this->assertSame($this->admin->id, $entree->causer_id);
        $this->assertSame($user->id, $entree->properties['user_id']);
        $this->assertSame($servant->id, $entree->properties['servant_id']);
        // Pas de données personnelles superflues dans le journal.
        $this->assertStringNotContainsString('Kouadio', $entree->description);
        $this->assertStringNotContainsString('Jean', $entree->description);
    }

    public function test_delier_un_compte_sans_servant_est_refuse(): void
    {
        $user = $this->makeUser('membre_test');

        $this->actingAs($this->admin)->delete(route('settings.users.servant.unlink', $user))->assertStatus(422);
        $this->assertSame(0, Activity::where('event', 'deliaison_compte_servant')->count());
    }

    public static function rolesNonAutorises(): array
    {
        return [['coordonnateur_equipe'], ['secretaire'], ['autres']];
    }

    #[DataProvider('rolesNonAutorises')]
    public function test_les_autres_roles_ne_peuvent_pas_delier(string $role): void
    {
        $acteur = $this->makeUser($role);
        $user = $this->makeUser('membre_test');
        $servant = Servant::factory()->create(['organisation_id' => $this->organisation->id, 'user_id' => $user->id]);

        $this->actingAs($acteur)->delete(route('settings.users.servant.unlink', $user))->assertForbidden();

        $this->assertDatabaseHas('servants', ['id' => $servant->id, 'user_id' => $user->id]);
    }

    public function test_un_compte_d_une_autre_organisation_ne_peut_pas_etre_delie(): void
    {
        $autre = Organisation::factory()->create();
        $user = $this->makeUser('membre_test', [], $autre);
        $servant = Servant::factory()->create(['organisation_id' => $autre->id, 'user_id' => $user->id]);

        $this->actingAs($this->admin)->delete(route('settings.users.servant.unlink', $user))->assertForbidden();

        $this->assertDatabaseHas('servants', ['id' => $servant->id, 'user_id' => $user->id]);
    }

    public function test_un_administrateur_ne_delie_pas_un_super_admin_mais_un_super_admin_le_peut(): void
    {
        $super = $this->makeUser('super_admin');
        $autreSuper = $this->makeUser('super_admin');
        $servant = Servant::factory()->create(['organisation_id' => $this->organisation->id, 'user_id' => $autreSuper->id]);

        $this->actingAs($this->admin)->delete(route('settings.users.servant.unlink', $autreSuper))->assertForbidden();
        $this->assertDatabaseHas('servants', ['id' => $servant->id, 'user_id' => $autreSuper->id]);

        $this->actingAs($super)->delete(route('settings.users.servant.unlink', $autreSuper))->assertRedirect();
        $this->assertDatabaseHas('servants', ['id' => $servant->id, 'user_id' => null]);
        $this->assertDatabaseHas('users', ['id' => $autreSuper->id]);
    }

    // ---------------------------------------------------------------
    // Suppression d'un compte lié
    // ---------------------------------------------------------------

    public function test_supprimer_un_compte_lie_conserve_le_servant_et_journalise_la_deliaison(): void
    {
        $user = $this->makeUser('membre_test');
        $servant = Servant::factory()->create(['organisation_id' => $this->organisation->id, 'user_id' => $user->id]);

        $this->actingAs($this->admin)->delete(route('settings.users.destroy', $user))
            ->assertRedirect()
            ->assertSessionHas('success', 'Compte supprimé avec succès. La fiche du servant(e) est conservée.');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseHas('servants', ['id' => $servant->id, 'user_id' => null]);
        $this->assertSame(1, Activity::where('event', 'deliaison_compte_servant')->count());
    }

    public function test_les_protections_de_suppression_restent_en_place_pour_un_compte_lie(): void
    {
        // Son propre compte, même lié à un servant.
        $servantAdmin = Servant::factory()->create(['organisation_id' => $this->organisation->id, 'user_id' => $this->admin->id]);
        $this->actingAs($this->admin)->delete(route('settings.users.destroy', $this->admin))->assertStatus(422);
        $this->assertDatabaseHas('servants', ['id' => $servantAdmin->id, 'user_id' => $this->admin->id]);

        // Un administrateur ne supprime pas un compte Super Administrateur, même lié.
        $super = $this->makeUser('super_admin');
        $servantSuper = Servant::factory()->create(['organisation_id' => $this->organisation->id, 'user_id' => $super->id]);

        $this->actingAs($this->admin)->delete(route('settings.users.destroy', $super))->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $super->id]);
        $this->assertDatabaseHas('servants', ['id' => $servantSuper->id, 'user_id' => $super->id]);

        // Le dernier Super Administrateur ne peut supprimer son propre compte lié.
        $this->actingAs($super)->delete(route('settings.users.destroy', $super))->assertStatus(422);
        $this->assertDatabaseHas('servants', ['id' => $servantSuper->id, 'user_id' => $super->id]);
        $this->assertSame(0, Activity::where('event', 'deliaison_compte_servant')->count());
    }

    public function test_la_revocation_depuis_la_fiche_du_servant_fonctionne_toujours(): void
    {
        $user = $this->makeUser('membre_test');
        $servant = Servant::factory()->create(['organisation_id' => $this->organisation->id, 'user_id' => $user->id]);

        $this->actingAs($this->admin)->delete(route('servants.account.destroy', $servant))->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseHas('servants', ['id' => $servant->id, 'user_id' => null]);
    }

    // ---------------------------------------------------------------
    // Régression : liste des servants (props, ordre des colonnes)
    // ---------------------------------------------------------------

    public function test_liste_des_servants_props_et_ordre_des_colonnes(): void
    {
        Servant::factory()->create(['organisation_id' => $this->organisation->id, 'nom' => 'Zeta', 'prenom' => 'Ana', 'statut' => 'actif', 'date_debut' => now()->toDateString()]);
        Servant::factory()->create(['organisation_id' => $this->organisation->id, 'nom' => 'Alpha', 'prenom' => 'Bob', 'statut' => 'recommande']);

        $this->admin->forceFill(['preferences' => ['colonnes_servants' => ['pieu', 'statut', 'nom', 'prenom', 'voir']]])->save();

        $this->actingAs($this->admin)->get(route('servants.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Servants/Index')
                ->where('nouveaux', false)
                ->has('servants.data', 2)
                ->has('servants.data.0', fn (Assert $s) => $s->where('nom', 'Alpha')->hasAll(['id', 'prenom', 'statut', 'pieu']))
                ->has('compteurs')
                ->where('preferences.colonnesServants', ['pieu', 'statut', 'nom', 'prenom', 'voir']));

        $this->actingAs($this->admin)->get(route('servants.nouveaux'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('nouveaux', true)
                ->has('servants.data', 1)
                ->where('servants.data.0.nom', 'Alpha'));
    }
}
