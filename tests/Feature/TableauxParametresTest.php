<?php

namespace Tests\Feature;

use App\Models\Horaire;
use App\Models\Organisation;
use App\Models\Pieu;
use App\Models\Role;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftTemplate;
use App\Models\ShiftTemplatePosition;
use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Refonte des tableaux (phase 2) : tri serveur du journal d'activité (liste
 * blanche, conservation de la recherche et de la pagination) et routes
 * appelées par les nouvelles fenêtres modales (Rôles, Pieux, Horaires,
 * Étapes, poste de modèle, licence propriétaire), droits inchangés.
 */
class TableauxParametresTest extends TestCase
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

    private function makeUser(string $roleSlug, array $attributs = []): User
    {
        return User::factory()->create([
            'organisation_id' => $this->organisation->id,
            'role_id' => $this->role($roleSlug)->id,
            ...$attributs,
        ]);
    }

    private function creerActivite(string $sujetType, int $sujetId, string $evenement, ?User $auteur, string $date): Activity
    {
        $activite = new Activity([
            'log_name' => 'default',
            'description' => $evenement,
            'event' => $evenement,
            'subject_type' => $sujetType,
            'subject_id' => $sujetId,
            'causer_type' => $auteur ? User::class : null,
            'causer_id' => $auteur?->id,
            'properties' => ['attributes' => ['nom' => 'x']],
        ]);
        $activite->created_at = Carbon::parse($date);
        $activite->updated_at = Carbon::parse($date);
        $activite->save();

        return $activite;
    }

    /**
     * Trois activités dont l'ordre croissant diffère pour chaque clé :
     *   date   : A, B, C
     *   action : B (Création), C (Modification), A (Suppression)
     *   sujet  : C (Servant n° 1), B (Servant n° 2), A (Shift)
     *   auteur : A (Bernard), C (« Système »), B (Zoé)
     *
     * @return array{A: int, B: int, C: int}
     */
    private function jeuActivites(): array
    {
        $s1 = Servant::factory()->create(['organisation_id' => $this->organisation->id]);
        $s2 = Servant::factory()->create(['organisation_id' => $this->organisation->id]);
        $shift = Shift::create([
            'organisation_id' => $this->organisation->id, 'nom' => 'Shift du mardi',
            'jour' => 'mardi', 'heure_debut' => '07:00', 'heure_fin' => '11:00', 'statut' => 'actif',
        ]);
        $bernard = $this->makeUser('administrateur', ['name' => 'Bernard']);
        $zoe = $this->makeUser('administrateur', ['name' => 'Zoé']);

        // Seules les activités du jeu de données comptent.
        Activity::query()->delete();

        return [
            'A' => $this->creerActivite(Shift::class, $shift->id, 'deleted', $bernard, '2026-01-01 10:00:00')->id,
            'B' => $this->creerActivite(Servant::class, $s2->id, 'created', $zoe, '2026-01-02 10:00:00')->id,
            'C' => $this->creerActivite(Servant::class, $s1->id, 'updated', null, '2026-01-03 10:00:00')->id,
        ];
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function clesDeTri(): array
    {
        return [
            'date' => ['date', ['A', 'B', 'C']],
            'action' => ['action', ['B', 'C', 'A']],
            'sujet' => ['sujet', ['C', 'B', 'A']],
            'auteur' => ['auteur', ['A', 'C', 'B']],
        ];
    }

    #[DataProvider('clesDeTri')]
    public function test_le_journal_se_trie_sur_chaque_colonne_dans_les_deux_sens(string $cle, array $ordreCroissant): void
    {
        $ids = $this->jeuActivites();
        $attendu = array_map(fn (string $l) => $ids[$l], $ordreCroissant);

        foreach (['asc' => $attendu, 'desc' => array_reverse($attendu)] as $sens => $ordre) {
            $this->actingAs($this->admin)
                ->get(route('settings.activity-log.index', ['tri' => $cle, 'sens' => $sens]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Settings/ActivityLog/Index')
                    ->where('tri', ['cle' => $cle, 'sens' => $sens])
                    ->where('activites.data', fn ($data) => collect($data)->pluck('id')->all() === $ordre));
        }
    }

    public function test_le_journal_est_trie_par_date_decroissante_par_defaut(): void
    {
        $ids = $this->jeuActivites();

        $this->actingAs($this->admin)
            ->get(route('settings.activity-log.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tri', ['cle' => 'date', 'sens' => 'desc'])
                ->where('activites.data', fn ($data) => collect($data)->pluck('id')->all() === [$ids['C'], $ids['B'], $ids['A']]));
    }

    public function test_le_tri_du_journal_ignore_une_cle_ou_un_sens_hors_liste_blanche(): void
    {
        $ids = $this->jeuActivites();
        $parDefaut = [$ids['C'], $ids['B'], $ids['A']];

        foreach (['properties', 'id; DROP TABLE users', 'created_at', 'causer_id'] as $cle) {
            $this->actingAs($this->admin)
                ->get(route('settings.activity-log.index', ['tri' => $cle, 'sens' => 'asc']))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('tri', ['cle' => 'date', 'sens' => 'desc'])
                    ->where('activites.data', fn ($data) => collect($data)->pluck('id')->all() === $parDefaut));
        }

        // Sens invalide : croissant.
        $this->actingAs($this->admin)
            ->get(route('settings.activity-log.index', ['tri' => 'action', 'sens' => 'n_importe_quoi']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tri', ['cle' => 'action', 'sens' => 'asc'])
                ->where('activites.data', fn ($data) => collect($data)->pluck('id')->all() === [$ids['B'], $ids['C'], $ids['A']]));
    }

    public function test_le_tri_du_journal_conserve_la_recherche_et_la_pagination(): void
    {
        $servant = Servant::factory()->create(['organisation_id' => $this->organisation->id]);
        $cible = $this->makeUser('administrateur', ['name' => 'Auteur Cible']);
        $autre = $this->makeUser('administrateur', ['name' => 'Quelqu\'un d\'autre']);
        Activity::query()->delete();

        for ($i = 0; $i < 35; $i++) {
            $this->creerActivite(Servant::class, $servant->id, $i % 2 ? 'updated' : 'created', $cible, '2026-02-01 10:00:00');
        }
        $this->creerActivite(Servant::class, $servant->id, 'updated', $autre, '2026-02-02 10:00:00');

        $this->actingAs($this->admin)
            ->get(route('settings.activity-log.index', ['recherche' => 'Cible', 'tri' => 'action', 'sens' => 'desc', 'page' => 2]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filtreRecherche', 'Cible')
                ->where('tri', ['cle' => 'action', 'sens' => 'desc'])
                ->where('activites.total', 35)
                ->where('activites.current_page', 2)
                ->has('activites.data', 5)
                // Page 2 en tri « Action » décroissant : la fin des « Création ».
                ->where('activites.data', fn ($data) => collect($data)->every(fn ($a) => $a['evenement'] === 'created' && $a['causeur'] === 'Auteur Cible'))
                ->where('activites.links', fn ($liens) => collect($liens)->filter(fn ($l) => $l['url'] !== null)->every(
                    fn ($l) => str_contains($l['url'], 'recherche=Cible') && str_contains($l['url'], 'tri=action') && str_contains($l['url'], 'sens=desc'),
                )));
    }

    public function test_une_page_hors_limites_du_journal_redirige_en_conservant_le_tri(): void
    {
        $this->jeuActivites();

        $this->actingAs($this->admin)
            ->get(route('settings.activity-log.index', ['tri' => 'auteur', 'sens' => 'desc', 'page' => 9]))
            ->assertRedirect('/parametres/journal?tri=auteur&sens=desc&page=1');
    }

    public function test_le_journal_reste_reserve_et_cloisonne_par_organisation(): void
    {
        $this->jeuActivites();
        $autreOrganisation = Organisation::factory()->create();
        $etranger = Servant::factory()->create(['organisation_id' => $autreOrganisation->id]);
        $this->creerActivite(Servant::class, $etranger->id, 'created', null, '2026-03-01 10:00:00');

        $this->actingAs($this->admin)
            ->get(route('settings.activity-log.index', ['tri' => 'date', 'sens' => 'desc']))
            ->assertInertia(fn (Assert $page) => $page->has('activites.data', 3));

        $this->actingAs($this->makeUser('membre'))
            ->get(route('settings.activity-log.index', ['tri' => 'date']))
            ->assertForbidden();
    }

    // ---- Routes appelées par les fenêtres modales ----

    public function test_modale_role_modifie_un_role_personnalise_et_refuse_un_role_verrouille(): void
    {
        $superAdmin = $this->makeUser('super_admin');
        $role = Role::create(['slug' => 'equipe_bureau', 'nom' => 'Équipe du bureau', 'gere_shifts' => false]);

        $this->actingAs($superAdmin)
            ->put(route('settings.roles.update', $role), ['nom' => 'Équipe du bureau élargie', 'description' => 'Accueil', 'gere_shifts' => true])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'nom' => 'Équipe du bureau élargie', 'description' => 'Accueil', 'gere_shifts' => true]);

        $this->actingAs($superAdmin)
            ->put(route('settings.roles.update', $role), ['nom' => '', 'description' => '', 'gere_shifts' => false])
            ->assertSessionHasErrors('nom');

        $autres = $this->role('autres');
        $this->actingAs($superAdmin)
            ->put(route('settings.roles.update', $autres), ['nom' => 'Autres', 'gere_shifts' => true])
            ->assertStatus(422);

        // Droits inchangés : l'administrateur n'accède pas aux rôles.
        $this->actingAs($this->admin)
            ->put(route('settings.roles.update', $role), ['nom' => 'Piratage'])
            ->assertForbidden();
    }

    public function test_modale_pieu_modifie_nom_type_et_parent(): void
    {
        $mission = Pieu::create(['organisation_id' => $this->organisation->id, 'nom' => 'Mission', 'type' => 'mission']);
        $district = Pieu::create(['organisation_id' => $this->organisation->id, 'nom' => 'District', 'type' => 'district']);

        $this->actingAs($this->admin)
            ->put(route('settings.pieux.update', $district), ['nom' => 'District Nord', 'type' => 'district', 'parent_id' => $mission->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pieux', ['id' => $district->id, 'nom' => 'District Nord', 'parent_id' => $mission->id]);

        // La modale envoie parent_id null pour « Aucun ».
        $this->actingAs($this->admin)
            ->put(route('settings.pieux.update', $district), ['nom' => 'District Nord', 'type' => 'district', 'parent_id' => null])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pieux', ['id' => $district->id, 'parent_id' => null]);

        $this->actingAs($this->admin)
            ->put(route('settings.pieux.update', $district), ['nom' => '', 'type' => 'inconnu'])
            ->assertSessionHasErrors(['nom', 'type']);

        // Une autre organisation ne peut pas le modifier.
        $autreAdmin = User::factory()->create(['organisation_id' => Organisation::factory()->create()->id, 'role_id' => $this->role('administrateur')->id]);
        $this->actingAs($autreAdmin)
            ->put(route('settings.pieux.update', $district), ['nom' => 'Piratage', 'type' => 'district'])
            ->assertForbidden();
    }

    public function test_modale_horaire_modifie_un_horaire_avec_la_validation_existante(): void
    {
        $horaire = Horaire::create(['organisation_id' => $this->organisation->id, 'nom' => 'Matin', 'heure_debut' => '06:30', 'heure_fin' => '12:30']);

        $this->actingAs($this->admin)
            ->put(route('settings.horaires.update', $horaire), ['nom' => 'Matin tôt', 'heure_debut' => '06:00', 'heure_fin' => '12:00'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $horaire->refresh();
        $this->assertSame('Matin tôt', $horaire->nom);
        $this->assertSame('06:00', substr($horaire->heure_debut, 0, 5));

        $this->actingAs($this->admin)
            ->put(route('settings.horaires.update', $horaire), ['nom' => 'Matin', 'heure_debut' => '12:00', 'heure_fin' => '06:00'])
            ->assertSessionHasErrors('heure_fin');

        $this->actingAs($this->makeUser('membre'))
            ->put(route('settings.horaires.update', $horaire), ['nom' => 'X', 'heure_debut' => '06:00', 'heure_fin' => '07:00'])
            ->assertForbidden();
    }

    public function test_modale_etape_modifie_nom_et_ordre(): void
    {
        $etape = WorkflowStep::create(['cle' => 'entretien', 'nom' => 'Entretien', 'ordre' => 1]);

        $this->actingAs($this->admin)
            ->put(route('settings.workflow-steps.update', $etape), ['nom' => 'Entretien final', 'ordre' => 3])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('workflow_steps', ['id' => $etape->id, 'nom' => 'Entretien final', 'ordre' => 3]);

        $this->actingAs($this->admin)
            ->put(route('settings.workflow-steps.update', $etape), ['nom' => 'Entretien', 'ordre' => 0])
            ->assertSessionHasErrors('ordre');

        $this->actingAs($this->makeUser('membre'))
            ->put(route('settings.workflow-steps.update', $etape), ['nom' => 'X', 'ordre' => 1])
            ->assertForbidden();
    }

    public function test_modale_poste_de_modele_renomme_le_poste_sans_changer_l_ordre_metier(): void
    {
        $modele = ShiftTemplate::create(['organisation_id' => $this->organisation->id, 'nom' => 'Mardi']);
        $homme = ShiftTemplatePosition::create(['shift_template_id' => $modele->id, 'nom' => 'Coordonnateur', 'ordre' => 1]);
        $femme = ShiftTemplatePosition::create(['shift_template_id' => $modele->id, 'nom' => 'Coordonnatrice', 'ordre' => 2]);
        $scelleur = ShiftTemplatePosition::create(['shift_template_id' => $modele->id, 'nom' => 'Scelleur', 'ordre' => 3]);

        $this->actingAs($this->admin)
            ->put(route('shift-templates.positions.update', [$modele, $scelleur]), ['nom' => 'Scelleur'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->put(route('shift-templates.positions.update', [$modele, $homme]), ['nom' => ''])
            ->assertSessionHasErrors('nom');

        // L'ordre métier (femme sous l'homme, Scelleur seul) est inchangé.
        $this->actingAs($this->admin)
            ->get(route('shift-templates.show', $modele))
            ->assertInertia(fn (Assert $page) => $page
                ->component('ShiftTemplates/Show')
                ->where('positions.0.id', $homme->id)
                ->where('positions.1.id', $femme->id)
                ->where('positions.1.bloc', 0)
                ->where('positions.2.id', $scelleur->id)
                ->where('positions.2.bloc', 1));

        $this->actingAs($this->makeUser('membre'))
            ->put(route('shift-templates.positions.update', [$modele, $homme]), ['nom' => 'X'])
            ->assertForbidden();
    }

    public function test_modale_licence_proprietaire_modifie_nom_et_expiration(): void
    {
        $proprietaire = User::factory()->create(['organisation_id' => null, 'role_id' => null, 'is_platform_owner' => true]);
        $client = Organisation::factory()->create(['nom' => 'Temple A', 'license_expires_at' => null]);

        $this->actingAs($proprietaire)
            ->patch(route('owner.licenses.update', $client), ['nom' => 'Temple A renommé', 'license_expires_at' => '2027-06-30'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $client->refresh();
        $this->assertSame('Temple A renommé', $client->nom);
        $this->assertSame('2027-06-30', $client->license_expires_at->format('Y-m-d'));

        // Date vidée dans la modale : licence illimitée.
        $this->actingAs($proprietaire)
            ->patch(route('owner.licenses.update', $client), ['nom' => 'Temple A renommé', 'license_expires_at' => ''])
            ->assertSessionHasNoErrors();
        $this->assertNull($client->refresh()->license_expires_at);

        $this->actingAs($proprietaire)
            ->patch(route('owner.licenses.update', $client), ['nom' => 'Temple A', 'license_expires_at' => 'pas une date'])
            ->assertSessionHasErrors('license_expires_at');

        // La liste fournit toujours les champs utilisés par le tableau.
        $this->actingAs($proprietaire)
            ->get(route('owner.licenses.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/Licenses/Index')
                ->has('organisations.0', fn (Assert $o) => $o->hasAll(['id', 'nom', 'license_expires_at', 'users_count'])->etc()));

        // Droits inchangés : un administrateur d'organisation n'y a pas accès.
        $this->actingAs($this->admin)
            ->patch(route('owner.licenses.update', $client), ['nom' => 'Piratage'])
            ->assertForbidden();
    }
}
