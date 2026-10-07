<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\Servant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Parcours du propriétaire de plateforme, de la licence d'organisation, d'un
 * compte suspendu et de l'isolation entre organisations.
 */
class ParcoursCompletsPlateformeTest extends ParcoursCompletsBase
{
    public function test_proprietaire_cree_une_organisation_et_gere_sa_licence(): void
    {
        Role::firstOrCreate(['slug' => 'administrateur'], ['nom' => 'Conseil du Temple']);
        $proprietaire = User::factory()->create(['organisation_id' => null, 'role_id' => null, 'is_platform_owner' => true]);
        $this->actingAs($proprietaire);

        $this->get('/dashboard')->assertRedirect('/owner/licences');
        $this->get('/owner/licences')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Owner/Licenses/Index')->where('stats.total', 1));

        // Création d'organisation : un administrateur est créé, mot de passe fourni une seule fois.
        $this->post('/owner/organisations', [
            'nom' => 'Temple Nord', 'admin_nom' => 'Chef Nord', 'admin_email' => 'nord@example.com',
            'license_expires_at' => now()->addDays(10)->toDateString(),
        ])->assertRedirect()->assertSessionHas('credentials');
        $nord = Organisation::where('nom', 'Temple Nord')->firstOrFail();
        $adminNord = User::where('email', 'nord@example.com')->firstOrFail();
        $this->assertSame($nord->id, $adminNord->organisation_id);
        $this->assertSame('administrateur', $adminNord->role->slug);
        $this->assertTrue($adminNord->must_change_password, 'Mot de passe temporaire : changement exigé à la première connexion.');
        $adminNord->forceFill(['must_change_password' => false])->save();

        // Licence : prolongation puis expiration.
        $this->patch("/owner/licences/{$nord->id}", ['license_expires_at' => now()->addYear()->toDateString()])->assertRedirect();
        $this->assertFalse($nord->fresh()->isLicenseExpired());
        $this->patch("/owner/licences/{$nord->id}", ['license_expires_at' => now()->subDay()->toDateString()])->assertRedirect();
        $this->assertTrue($nord->fresh()->isLicenseExpired());

        // Licence expirée : l'organisation reste consultable mais passe en lecture seule.
        Auth::logout();
        $this->actingAs($adminNord)->get('/servants')->assertOk()->assertInertia(fn (Assert $page) => $page->where('licence.expired', true));
        $this->post('/servants', ['nom' => 'Bloque', 'prenom' => 'Test', 'genre' => 'homme'])->assertForbidden();
        $this->assertSame(0, Servant::where('organisation_id', $nord->id)->count());

        // Renouvelée : l'écriture refonctionne.
        $this->actingAs($proprietaire)->patch("/owner/licences/{$nord->id}", ['license_expires_at' => now()->addYear()->toDateString()])->assertRedirect();
        $this->actingAs($adminNord)->post('/servants', ['nom' => 'Autorise', 'prenom' => 'Test', 'genre' => 'homme'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, Servant::where('organisation_id', $nord->id)->count());

        // Un propriétaire sans organisation n'accède pas aux écrans métier.
        $this->actingAs($proprietaire)->get('/servants')->assertForbidden();
        $this->actingAs($proprietaire)->get('/parametres/licence')->assertForbidden();
    }

    public function test_compte_suspendu_ne_peut_pas_se_connecter_et_perd_sa_session(): void
    {
        foreach (['administrateur', 'secretaire', 'coordonnateur_equipe', 'autres'] as $slug) {
            $utilisateur = $this->makeUser($slug, null, ['password' => 'MotDePasse2026!', 'acces_suspendu' => true]);

            $this->post('/login', ['email' => $utilisateur->email, 'password' => 'MotDePasse2026!'])->assertSessionHasErrors();
            $this->assertGuest();

            // Session déjà ouverte au moment de la suspension : coupée à la requête suivante.
            $utilisateur->forceFill(['acces_suspendu' => false])->save();
            $this->actingAs($utilisateur)->get('/profile')->assertOk();
            $utilisateur->forceFill(['acces_suspendu' => true])->save();
            $this->actingAs($utilisateur)->get('/profile')->assertRedirect('/login');
            Auth::logout();
        }
    }

    public function test_isolation_entre_organisations_sur_tous_les_ecrans(): void
    {
        $autreOrg = Organisation::factory()->create();
        $servantEtranger = Servant::factory()->create(['organisation_id' => $autreOrg->id]);
        $shiftEtranger = $this->makeShift('Shift étranger', $autreOrg);
        $compteEtranger = $this->makeUser('secretaire', $autreOrg);
        $admin = $this->makeUser('administrateur');
        $this->actingAs($admin);

        $this->get("/servants/{$servantEtranger->id}")->assertForbidden();
        $this->get("/servants/{$servantEtranger->id}/edit")->assertForbidden();
        $this->patch("/servants/{$servantEtranger->id}/statut", ['statut' => 'actif'])->assertForbidden();
        $this->delete("/servants/{$servantEtranger->id}", ['confirmation' => 'SUPPRIMER'])->assertForbidden();
        $this->get("/shifts/{$shiftEtranger->id}")->assertForbidden();
        $this->put("/parametres/utilisateurs/{$compteEtranger->id}", $this->donneesCompte($compteEtranger, ['acces_suspendu' => true]))->assertForbidden();
        $this->delete("/parametres/utilisateurs/{$compteEtranger->id}")->assertForbidden();

        $this->get('/parametres/utilisateurs')->assertInertia(fn (Assert $page) => $page
            ->where('users.data', fn ($lignes) => collect($lignes)->pluck('id')->doesntContain($compteEtranger->id)));
        $this->get('/servants')->assertInertia(fn (Assert $page) => $page
            ->where('servants', fn ($lignes) => collect($lignes)->pluck('id')->doesntContain($servantEtranger->id)));

        $this->assertNotNull(Servant::find($servantEtranger->id));
        $this->assertNotNull(User::find($compteEtranger->id));
        $this->assertFalse($compteEtranger->fresh()->accesSuspendu());
    }
}
