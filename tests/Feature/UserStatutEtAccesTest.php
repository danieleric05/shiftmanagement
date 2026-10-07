<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Comptes utilisateurs : statut de la personne (Recommandé / Nouveau / Ancien)
 * distinct du blocage d'accès au compte (`acces_suspendu`).
 */
class UserStatutEtAccesTest extends TestCase
{
    use RefreshDatabase;

    private function role(string $slug, array $attributs = []): Role
    {
        return Role::where('slug', $slug)->first()
            ?? Role::factory()->create(['slug' => $slug, 'nom' => $slug, ...$attributs]);
    }

    private function compte(string $slug, ?Organisation $organisation = null, array $attributs = []): User
    {
        $organisation ??= Organisation::factory()->create();

        return User::factory()->create([
            'organisation_id' => $organisation->id,
            'role_id' => $this->role($slug)->id,
            ...$attributs,
        ]);
    }

    private function donnees(User $cible, array $surcharges = []): array
    {
        return [
            'nom' => 'Nom',
            'prenom' => 'Prénom',
            'role_id' => $cible->role_id,
            'statut' => $cible->statut ?? 'actif',
            ...$surcharges,
        ];
    }

    private function idsListes($response): array
    {
        return collect($response->viewData('page')['props']['users']['data'])->pluck('id')->all();
    }

    public function test_un_compte_cree_est_ancien_et_autorise_par_defaut(): void
    {
        $compte = $this->compte('administrateur');

        $this->assertSame('actif', $compte->fresh()->statut);
        $this->assertFalse($compte->fresh()->acces_suspendu);
    }

    public function test_administrateur_peut_donner_les_trois_statuts(): void
    {
        $admin = $this->compte('administrateur');
        $cible = $this->compte('secretaire', $admin->organisation);

        foreach (['recommande', 'en_formation', 'actif'] as $statut) {
            $this->actingAs($admin)
                ->put("/parametres/utilisateurs/{$cible->id}", $this->donnees($cible, ['statut' => $statut]))
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $this->assertSame($statut, $cible->fresh()->statut);
            $this->assertFalse($cible->fresh()->acces_suspendu, 'Le statut de la personne ne bloque jamais l\'accès.');
        }
    }

    public function test_super_admin_peut_changer_le_statut_d_un_super_admin(): void
    {
        $super = $this->compte('super_admin');
        $autreSuper = $this->compte('super_admin', $super->organisation);

        $this->actingAs($super)
            ->put("/parametres/utilisateurs/{$autreSuper->id}", $this->donnees($autreSuper, ['statut' => 'recommande']))
            ->assertRedirect();

        $this->assertSame('recommande', $autreSuper->fresh()->statut);
    }

    public function test_les_autres_roles_ne_peuvent_pas_changer_le_statut(): void
    {
        $organisation = Organisation::factory()->create();
        $cible = $this->compte('administrateur', $organisation);

        foreach (['secretaire', 'coordonnateur_equipe', 'autres'] as $slug) {
            $acteur = $this->compte($slug, $organisation);

            $this->actingAs($acteur)
                ->put("/parametres/utilisateurs/{$cible->id}", $this->donnees($cible, ['statut' => 'recommande', 'acces_suspendu' => true]))
                ->assertForbidden();
        }

        $this->assertSame('actif', $cible->fresh()->statut);
        $this->assertFalse($cible->fresh()->acces_suspendu);
    }

    public function test_valeur_de_statut_invalide_refusee(): void
    {
        $admin = $this->compte('administrateur');
        $cible = $this->compte('secretaire', $admin->organisation);

        // « suspendu » n'est plus un statut de personne : c'est acces_suspendu.
        // Erreur de validation (422) restituée par Inertia sous forme de
        // redirection avec erreurs en session.
        foreach (['suspendu', 'retire', 'nimporte'] as $valeur) {
            $this->actingAs($admin)
                ->from('/parametres/utilisateurs')
                ->put("/parametres/utilisateurs/{$cible->id}", $this->donnees($cible, ['statut' => $valeur]))
                ->assertRedirect('/parametres/utilisateurs')
                ->assertSessionHasErrors('statut');
        }

        $this->actingAs($admin)
            ->from('/parametres/utilisateurs')
            ->put("/parametres/utilisateurs/{$cible->id}", $this->donnees($cible, ['acces_suspendu' => 'peut-etre']))
            ->assertSessionHasErrors('acces_suspendu');

        $this->assertSame('actif', $cible->fresh()->statut);
    }

    public function test_creation_avec_statut_et_acces(): void
    {
        $admin = $this->compte('administrateur');
        $secretaire = $this->role('secretaire');

        $this->actingAs($admin)->post('/parametres/utilisateurs', [
            'nom' => 'Kouadio', 'prenom' => 'Awa', 'email' => 'awa@example.com',
            'password' => 'MotDePasse123!', 'role_id' => $secretaire->id,
            'statut' => 'en_formation', 'acces_suspendu' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'awa@example.com', 'statut' => 'en_formation', 'acces_suspendu' => true]);

        $this->actingAs($admin)->post('/parametres/utilisateurs', [
            'nom' => 'Yao', 'prenom' => 'Koffi', 'email' => 'koffi@example.com',
            'password' => 'MotDePasse123!', 'role_id' => $secretaire->id, 'statut' => 'suspendu',
        ])->assertSessionHasErrors('statut');
        $this->assertDatabaseMissing('users', ['email' => 'koffi@example.com']);
    }

    public function test_un_compte_bloque_ne_peut_pas_se_connecter(): void
    {
        $compte = $this->compte('administrateur', null, ['email' => 'bloque@example.com', 'acces_suspendu' => true]);

        $this->post('/login', ['email' => 'bloque@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertNotNull($compte->fresh());
    }

    public function test_un_compte_non_bloque_se_connecte_quel_que_soit_son_statut(): void
    {
        $this->compte('administrateur', null, ['email' => 'recommande@example.com', 'statut' => 'recommande']);

        $this->post('/login', ['email' => 'recommande@example.com', 'password' => 'password']);

        $this->assertAuthenticated();
    }

    public function test_une_session_ouverte_est_coupee_apres_blocage(): void
    {
        $admin = $this->compte('administrateur');
        $cible = $this->compte('administrateur', $admin->organisation);

        $this->actingAs($cible)->get('/dashboard')->assertOk();

        $this->actingAs($admin)
            ->put("/parametres/utilisateurs/{$cible->id}", $this->donnees($cible, ['acces_suspendu' => true]))
            ->assertRedirect();

        $this->actingAs($cible->fresh())->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_l_ancienne_valeur_suspendu_reste_bloquante_avant_migration(): void
    {
        $compte = $this->compte('administrateur', null, ['email' => 'ancien@example.com', 'statut' => 'suspendu']);

        $this->post('/login', ['email' => 'ancien@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($compte)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_omettre_acces_suspendu_ne_debloque_pas_le_compte(): void
    {
        $admin = $this->compte('administrateur');
        $cible = $this->compte('secretaire', $admin->organisation, ['acces_suspendu' => true]);

        $this->actingAs($admin)
            ->put("/parametres/utilisateurs/{$cible->id}", $this->donnees($cible, ['statut' => 'recommande']))
            ->assertRedirect();

        $this->assertTrue($cible->fresh()->acces_suspendu);
    }

    public function test_auto_blocage_refuse_pour_administrateur_et_super_admin(): void
    {
        foreach (['administrateur', 'super_admin'] as $slug) {
            $compte = $this->compte($slug);
            // Un second super_admin pour isoler la règle d'auto-blocage.
            $this->compte('super_admin', $compte->organisation);

            $this->actingAs($compte)
                ->put("/parametres/utilisateurs/{$compte->id}", $this->donnees($compte, ['acces_suspendu' => true]))
                ->assertStatus(422);

            $this->assertFalse($compte->fresh()->acces_suspendu);
        }
    }

    public function test_un_administrateur_ne_peut_ni_bloquer_ni_supprimer_un_super_admin(): void
    {
        $admin = $this->compte('administrateur');
        $super = $this->compte('super_admin', $admin->organisation);

        $this->actingAs($admin)
            ->put("/parametres/utilisateurs/{$super->id}", $this->donnees($super, ['acces_suspendu' => true]))
            ->assertForbidden();
        $this->actingAs($admin)->delete("/parametres/utilisateurs/{$super->id}")->assertForbidden();

        $this->assertFalse($super->fresh()->acces_suspendu);
    }

    public function test_le_dernier_super_admin_ne_peut_pas_perdre_son_role(): void
    {
        $super = $this->compte('super_admin');
        $admin = $this->role('administrateur');

        $this->actingAs($super)
            ->put("/parametres/utilisateurs/{$super->id}", $this->donnees($super, ['role_id' => $admin->id]))
            ->assertStatus(422);

        $this->assertSame('super_admin', $super->fresh()->role->slug);
    }

    public function test_un_super_admin_peut_bloquer_un_autre_super_admin_mais_pas_le_dernier_actif(): void
    {
        $super = $this->compte('super_admin');
        $autre = $this->compte('super_admin', $super->organisation);

        $this->actingAs($super)
            ->put("/parametres/utilisateurs/{$autre->id}", $this->donnees($autre, ['acces_suspendu' => true]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($autre->fresh()->acces_suspendu);

        // Le super administrateur restant est désormais le dernier en mesure
        // de se connecter : ni rétrogradation, ni suppression de l'autre
        // super administrateur ne le met en danger, mais lui-même est protégé.
        $this->actingAs($super)->delete("/parametres/utilisateurs/{$autre->id}")->assertRedirect();
        $this->assertNull($autre->fresh());

        $this->actingAs($super)
            ->put("/parametres/utilisateurs/{$super->id}", $this->donnees($super, ['acces_suspendu' => true]))
            ->assertStatus(422);
    }

    public function test_liste_libelles_recherche_et_filtres(): void
    {
        $admin = $this->compte('administrateur', null, ['name' => 'Admin Zeta', 'email' => 'zeta@example.com']);
        $recommande = $this->compte('secretaire', $admin->organisation, ['name' => 'Rita Recommandee', 'email' => 'rita@example.com', 'statut' => 'recommande']);
        $nouveau = $this->compte('secretaire', $admin->organisation, ['name' => 'Noel Nouveau', 'email' => 'noel@example.com', 'statut' => 'en_formation', 'acces_suspendu' => true]);

        $liste = $this->actingAs($admin)->get('/parametres/utilisateurs')->assertOk();
        $liste->assertInertia(fn ($page) => $page
            ->where('statuts', [
                ['value' => 'recommande', 'label' => 'Recommandé'],
                ['value' => 'en_formation', 'label' => 'Nouveau'],
                ['value' => 'actif', 'label' => 'Ancien'],
            ])
            ->where('users.data', fn ($users) => collect($users)->firstWhere('id', $nouveau->id)['acces_suspendu'] === true
                && collect($users)->firstWhere('id', $nouveau->id)['statut'] === 'en_formation'));

        $this->assertSame([$recommande->id], $this->idsListes($this->actingAs($admin)->get('/parametres/utilisateurs?statut=recommande')));
        $this->assertSame([$nouveau->id], $this->idsListes($this->actingAs($admin)->get('/parametres/utilisateurs?acces=suspendu')));
        $this->assertSame([], $this->idsListes($this->actingAs($admin)->get('/parametres/utilisateurs?statut=recommande&recherche=noel')));
        $this->assertSame([$nouveau->id], $this->idsListes($this->actingAs($admin)->get('/parametres/utilisateurs?statut=en_formation&recherche=noel')));

        // Valeur hors liste blanche : filtre ignoré.
        $ignore = $this->actingAs($admin)->get('/parametres/utilisateurs?statut=suspendu')->assertOk();
        $this->assertCount(3, $this->idsListes($ignore));
        $ignore->assertInertia(fn ($page) => $page->where('filtreStatut', null));
    }

    public function test_isolation_par_organisation(): void
    {
        $admin = $this->compte('administrateur');
        $etranger = $this->compte('secretaire', null, ['statut' => 'recommande']);

        $this->actingAs($admin)
            ->put("/parametres/utilisateurs/{$etranger->id}", $this->donnees($etranger, ['statut' => 'actif', 'acces_suspendu' => true]))
            ->assertForbidden();

        $this->assertSame('recommande', $etranger->fresh()->statut);
        $this->assertFalse($etranger->fresh()->acces_suspendu);
        $this->assertNotContains($etranger->id, $this->idsListes($this->actingAs($admin)->get('/parametres/utilisateurs?statut=recommande')));
    }

    public function test_changements_journalises_et_visibles_dans_le_journal_de_l_organisation(): void
    {
        $admin = $this->compte('administrateur');
        $cible = $this->compte('secretaire', $admin->organisation, ['name' => 'Cible Test']);
        $autreAdmin = $this->compte('administrateur');

        $this->actingAs($admin)
            ->put("/parametres/utilisateurs/{$cible->id}", $this->donnees($cible, ['statut' => 'en_formation', 'acces_suspendu' => true]))
            ->assertRedirect();
        $this->actingAs($admin)
            ->put("/parametres/utilisateurs/{$cible->id}", $this->donnees($cible->fresh(), ['acces_suspendu' => false]))
            ->assertRedirect();
        // Aucun changement : aucune entrée supplémentaire.
        $this->actingAs($admin)
            ->put("/parametres/utilisateurs/{$cible->id}", $this->donnees($cible->fresh()))
            ->assertRedirect();

        $statut = Activity::where('event', 'changement_statut_compte')->sole();
        $this->assertSame($admin->id, $statut->causer_id);
        $this->assertSame(['user_id' => $cible->id, 'ancien_statut' => 'actif', 'nouveau_statut' => 'en_formation'], $statut->properties->all());
        $this->assertStringContainsString('« Ancien » → « Nouveau »', $statut->description);
        $this->assertSame(1, Activity::where('event', 'blocage_acces_compte')->count());
        $this->assertSame(1, Activity::where('event', 'deblocage_acces_compte')->count());

        $journal = $this->actingAs($admin)->get('/parametres/journal')->assertOk();
        $evenements = collect($journal->viewData('page')['props']['activites']['data'])->pluck('evenement');
        $this->assertContains('changement_statut_compte', $evenements);
        $this->assertContains('blocage_acces_compte', $evenements);

        $journalEtranger = $this->actingAs($autreAdmin)->get('/parametres/journal')->assertOk();
        $this->assertSame([], $journalEtranger->viewData('page')['props']['activites']['data']);
    }
}
