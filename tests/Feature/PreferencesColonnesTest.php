<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Ordre des colonnes de la liste des servants mémorisé par utilisateur
 * (Conseil du Temple : administrateur et super_admin uniquement).
 */
class PreferencesColonnesTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $organisation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = Organisation::factory()->create();
    }

    private function makeUser(string $roleSlug): User
    {
        $role = Role::firstOrCreate(['slug' => $roleSlug], ['nom' => $roleSlug, 'gere_shifts' => $roleSlug === 'coordonnateur_equipe']);

        return User::factory()->create([
            'organisation_id' => $this->organisation->id,
            'role_id' => $role->id,
        ]);
    }

    private function patchColonnes(User $user, mixed $colonnes)
    {
        return $this->actingAs($user)
            ->from(route('servants.index'))
            ->patch(route('preferences.colonnes-servants.update'), ['colonnes' => $colonnes]);
    }

    #[DataProvider('rolesConseil')]
    public function test_le_conseil_du_temple_enregistre_son_ordre(string $role): void
    {
        $user = $this->makeUser($role);
        $ordre = ['pieu', 'statut', 'nom', 'prenom', 'voir'];

        $this->patchColonnes($user, $ordre)
            ->assertRedirect(route('servants.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame($ordre, $user->fresh()->preferences['colonnes_servants']);

        $this->actingAs($user)->get(route('servants.index'))
            ->assertInertia(fn (Assert $page) => $page->where('preferences.colonnesServants', $ordre));
    }

    public static function rolesConseil(): array
    {
        return [['administrateur'], ['super_admin']];
    }

    #[DataProvider('rolesNonAutorises')]
    public function test_les_autres_roles_recoivent_403(string $role): void
    {
        $user = $this->makeUser($role);

        $this->patchColonnes($user, ['pieu', 'statut', 'nom', 'prenom', 'voir'])->assertForbidden();

        $this->assertNull($user->fresh()->preferences);
    }

    public static function rolesNonAutorises(): array
    {
        return [['secretaire'], ['coordonnateur_equipe'], ['autres']];
    }

    public function test_les_autres_roles_ne_recoivent_pas_d_ordre_personnalisable(): void
    {
        $secretaire = $this->makeUser('secretaire');

        $this->actingAs($secretaire)->get(route('servants.index'))
            ->assertInertia(fn (Assert $page) => $page->where('preferences.colonnesServants', null));
    }

    public function test_une_cle_inconnue_est_refusee(): void
    {
        $admin = $this->makeUser('administrateur');

        $this->patchColonnes($admin, ['nom', 'prenom', 'statut', 'voir', 'telephone'])
            ->assertSessionHasErrors('colonnes.4');

        $this->assertNull($admin->fresh()->preferences);
    }

    public function test_un_doublon_est_refuse(): void
    {
        $admin = $this->makeUser('administrateur');

        $this->patchColonnes($admin, ['nom', 'nom', 'statut', 'voir', 'pieu'])
            ->assertSessionHasErrors();

        $this->assertNull($admin->fresh()->preferences);
    }

    public function test_un_ordre_incomplet_est_refuse(): void
    {
        $admin = $this->makeUser('administrateur');

        $this->patchColonnes($admin, ['nom', 'prenom', 'statut', 'voir'])
            ->assertSessionHasErrors('colonnes');

        $this->assertNull($admin->fresh()->preferences);
    }

    public function test_une_valeur_non_liste_ou_absente_est_refusee(): void
    {
        $admin = $this->makeUser('administrateur');

        // Hors api/*, l'application rend les erreurs de validation à la façon
        // Inertia (redirection + erreurs en session) plutôt qu'en 422 JSON.
        $this->patchColonnes($admin, 'nom,prenom,statut,voir,pieu')->assertSessionHasErrors('colonnes');
        $this->patchColonnes($admin, ['nom' => 'nom', 'prenom' => 'prenom', 'statut' => 'statut', 'voir' => 'voir', 'pieu' => 'pieu'])
            ->assertSessionHasErrors('colonnes');

        $this->actingAs($admin)
            ->patch(route('preferences.colonnes-servants.update'), ['autre' => 'valeur'])
            ->assertSessionHasErrors('colonnes');

        $this->assertNull($admin->fresh()->preferences);
    }

    public function test_la_reinitialisation_revient_a_l_ordre_par_defaut(): void
    {
        $admin = $this->makeUser('administrateur');
        $this->patchColonnes($admin, ['pieu', 'statut', 'nom', 'prenom', 'voir']);

        $this->patchColonnes($admin, null)->assertSessionHasNoErrors();

        $this->assertNull($admin->fresh()->preferences);
        $this->assertSame(User::COLONNES_SERVANTS, $admin->fresh()->ordreColonnesServants());

        $this->actingAs($admin)->get(route('servants.nouveaux'))
            ->assertInertia(fn (Assert $page) => $page->where('preferences.colonnesServants', ['nom', 'prenom', 'statut', 'voir', 'pieu']));
    }

    public function test_l_ordre_est_isole_par_utilisateur(): void
    {
        $admin1 = $this->makeUser('administrateur');
        $admin2 = $this->makeUser('administrateur');

        $this->patchColonnes($admin1, ['voir', 'pieu', 'statut', 'prenom', 'nom']);

        $this->assertSame(['voir', 'pieu', 'statut', 'prenom', 'nom'], $admin1->fresh()->ordreColonnesServants());
        $this->assertNull($admin2->fresh()->preferences);

        $this->actingAs($admin2)->get(route('servants.index'))
            ->assertInertia(fn (Assert $page) => $page->where('preferences.colonnesServants', User::COLONNES_SERVANTS));
    }

    public function test_un_ordre_stocke_invalide_retombe_sur_l_ordre_par_defaut(): void
    {
        $admin = $this->makeUser('administrateur');
        $admin->forceFill(['preferences' => ['colonnes_servants' => ['nom', 'telephone']]])->save();

        $this->assertSame(User::COLONNES_SERVANTS, $admin->fresh()->ordreColonnesServants());
    }

    public function test_les_preferences_ne_sont_pas_exposees_dans_l_utilisateur_partage(): void
    {
        $admin = $this->makeUser('administrateur');
        $this->patchColonnes($admin, ['pieu', 'statut', 'nom', 'prenom', 'voir']);

        $this->actingAs($admin)->get(route('servants.index'))
            ->assertInertia(fn (Assert $page) => $page->missing('auth.user.preferences'));
    }
}
