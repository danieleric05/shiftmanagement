<?php

namespace Tests\Feature;

use App\Models\Horaire;
use App\Models\Pieu;
use App\Models\Role;
use App\Models\Servant;
use App\Models\ShiftTemplate;
use App\Models\ShiftTransferRequest;
use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/**
 * Parcours complets du Conseil du Temple (administrateur) et du Super
 * Administrateur : comptes, statuts, accès, servants, réglages, licence.
 */
class ParcoursCompletsConseilTest extends ParcoursCompletsBase
{
    public function test_conseil_cycle_de_vie_d_un_compte_de_la_creation_a_la_suppression(): void
    {
        $admin = $this->makeUser('administrateur');
        $roleSecretaire = Role::firstOrCreate(['slug' => 'secretaire'], ['nom' => 'Secrétaire']);

        // Création : le compte doit changer son mot de passe à la première connexion.
        $this->actingAs($admin)->post('/parametres/utilisateurs', [
            'nom' => 'Yao', 'prenom' => 'Marie', 'email' => 'marie.yao@example.com',
            'password' => 'MotDePasse2026!', 'role_id' => $roleSecretaire->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $compte = User::where('email', 'marie.yao@example.com')->firstOrFail();
        $this->assertTrue($compte->must_change_password);
        $this->assertSame($this->organisation->id, $compte->organisation_id);

        // Statut du compte : Recommandé -> Nouveau -> Ancien, journalisé à chaque changement.
        foreach (['recommande', 'en_formation', 'actif'] as $statut) {
            $this->put("/parametres/utilisateurs/{$compte->id}", $this->donneesCompte($compte->fresh(), ['statut' => $statut]))
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame($statut, $compte->fresh()->statut);
            $this->assertFalse($compte->fresh()->accesSuspendu(), 'Le statut de la personne ne bloque jamais l\'accès.');
        }
        $this->assertSame(3, Activity::where('event', 'changement_statut_compte')->count());

        // Suspension : connexion refusée ; rétablissement : connexion possible.
        $this->put("/parametres/utilisateurs/{$compte->id}", $this->donneesCompte($compte->fresh(), ['acces_suspendu' => true]))
            ->assertRedirect();
        $this->assertTrue($compte->fresh()->accesSuspendu());
        Auth::logout();
        $this->post('/login', ['email' => $compte->email, 'password' => 'MotDePasse2026!'])->assertSessionHasErrors();
        $this->assertGuest();

        $this->actingAs($admin)->put("/parametres/utilisateurs/{$compte->id}", $this->donneesCompte($compte->fresh(), ['acces_suspendu' => false]))
            ->assertRedirect();
        $this->assertFalse($compte->fresh()->accesSuspendu());
        Auth::logout();
        $this->post('/login', ['email' => $compte->email, 'password' => 'MotDePasse2026!'])->assertRedirect();
        $this->assertAuthenticatedAs($compte);
        $this->assertSame(1, Activity::where('event', 'blocage_acces_compte')->count());
        $this->assertSame(1, Activity::where('event', 'deblocage_acces_compte')->count());
        Auth::logout();

        // Lien avec un servant : délier conserve le compte et la fiche.
        $servant = Servant::factory()->create(['organisation_id' => $this->organisation->id, 'user_id' => $compte->id]);
        $this->actingAs($admin)->delete("/parametres/utilisateurs/{$compte->id}/servant")->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($servant->fresh()->user_id);
        $this->assertNotNull(User::find($compte->id));
        $this->assertSame(1, Activity::where('event', 'deliaison_compte_servant')->count());

        // Supprimer un compte encore lié : la fiche du servant survit.
        $servant->update(['user_id' => $compte->id]);
        $this->delete("/parametres/utilisateurs/{$compte->id}")->assertRedirect()->assertSessionHas('success');
        $this->assertNull(User::find($compte->id));
        $this->assertNotNull(Servant::find($servant->id));
        $this->assertNull($servant->fresh()->user_id);

        // L'administrateur ne se supprime pas lui-même.
        $this->delete("/parametres/utilisateurs/{$admin->id}")->assertStatus(422);
    }

    public function test_conseil_cycle_de_vie_d_un_servant_statut_releve_reintegration_suppression_definitive(): void
    {
        $admin = $this->makeUser('administrateur');
        $this->actingAs($admin);

        $this->post('/servants', ['nom' => 'Kone', 'prenom' => 'Ali', 'genre' => 'homme', 'pieu_id' => $this->pieu->id])
            ->assertRedirect()->assertSessionHasNoErrors();
        $servant = Servant::where('nom', 'Kone')->firstOrFail();
        $this->assertSame('recommande', $servant->statut);

        // Changement de statut dans les deux sens.
        foreach (['en_formation', 'actif', 'recommande'] as $statut) {
            $this->patch("/servants/{$servant->id}/statut", ['statut' => $statut])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame($statut, $servant->fresh()->statut);
        }
        $this->patch("/servants/{$servant->id}/statut", ['statut' => 'inconnu'])->assertSessionHasErrors('statut');
        $this->patch("/servants/{$servant->id}/statut", ['statut' => 'actif'])->assertRedirect();

        // Affectation puis relève : le servant sort du shift et devient « relevé ».
        $servantAffecte = $this->makeServantAffecte($this->shiftA, 'Poste relève', ['nom' => 'Releve', 'prenom' => 'Test']);
        $this->post('/transferts', [
            'shift_id' => $this->shiftA->id, 'type' => 'releve', 'servant_id' => $servantAffecte->id,
            'motif' => 'Déménagement', 'date_demande' => now()->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $releve = ShiftTransferRequest::where('servant_id', $servantAffecte->id)->firstOrFail();
        $this->patch("/transferts/{$releve->id}/resoudre", ['resultat' => 'Relève accordée', 'resultat_date' => now()->toDateString()])
            ->assertRedirect()->assertSessionHasNoErrors();

        $servantAffecte->refresh();
        $this->assertTrue($servantAffecte->estReleve());
        $this->assertSame(0, $servantAffecte->assignationsActives()->count());

        // Un servant relevé n'est plus modifiable par le changement de statut…
        $this->patch("/servants/{$servantAffecte->id}/statut", ['statut' => 'actif'])->assertSessionHasErrors('statut');
        // …la page des relèves propose la réintégration au Conseil.
        $this->get('/transferts/releves')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('ShiftTransfers/Releves')
            ->where('releves.data.0.peut_reintegrer', true));

        // Réintégration : historique conservé, journalisé.
        $this->post("/servants/{$servantAffecte->id}/reintegrer", ['commentaire' => 'De retour'])->assertRedirect()->assertSessionHas('success');
        $this->assertFalse($servantAffecte->fresh()->estReleve());
        $this->assertNotNull($releve->fresh()->reintegre_le);
        $this->assertSame('traitee', $releve->fresh()->statut);
        $this->post("/servants/{$servantAffecte->id}/reintegrer")->assertStatus(422);

        // Suppression définitive : confirmation exacte requise.
        $this->delete("/servants/{$servantAffecte->id}")->assertSessionHasErrors('confirmation');
        $this->delete("/servants/{$servantAffecte->id}", ['confirmation' => 'pas bon'])->assertSessionHasErrors('confirmation');
        $this->assertNotNull(Servant::find($servantAffecte->id));
        $this->delete("/servants/{$servantAffecte->id}", ['confirmation' => 'SUPPRIMER'])->assertRedirect();
        $this->assertNull(Servant::withTrashed()->find($servantAffecte->id));
        $this->assertSame(0, ShiftTransferRequest::withTrashed()->where('servant_id', $servantAffecte->id)->count());
        $this->assertNotNull(Servant::find($servant->id), 'Les autres servants ne sont pas touchés.');
    }

    public function test_conseil_colonnes_deplacables_tri_serveur_et_licence(): void
    {
        $admin = $this->makeUser('administrateur', null, ['name' => 'Zed Admin']);
        $this->makeUser('secretaire', null, ['name' => 'Anne Secr', 'prenom' => 'Anne', 'nom' => 'Secr', 'email' => 'anne@example.com']);
        $this->actingAs($admin);

        // Colonnes déplaçables : enregistrement, puis relecture dans la liste des servants.
        $ordre = ['pieu', 'statut', 'nom', 'prenom', 'voir'];
        $this->patch('/preferences/colonnes-servants', ['colonnes' => $ordre])->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/servants')->assertOk()->assertInertia(fn (Assert $page) => $page->where('preferences.colonnesServants', $ordre));
        $this->patch('/preferences/colonnes-servants', ['colonnes' => null])->assertRedirect();
        $this->get('/servants')->assertInertia(fn (Assert $page) => $page->where('preferences.colonnesServants', User::COLONNES_SERVANTS));

        // Tri serveur de la liste des comptes (email) dans les deux sens.
        $emails = fn (string $sens) => $this->get("/parametres/utilisateurs?tri=email&sens={$sens}")->assertOk()->original->getData()['page']['props']['users']['data'];
        $asc = array_column($emails('asc'), 'email');
        $desc = array_column($emails('desc'), 'email');
        $this->assertSame($asc, array_reverse($desc));
        $this->assertSame($asc, collect($asc)->sort()->values()->all());

        // Section Licence : visible (carte dans Paramètres) et consultable.
        $this->get('/parametres')->assertOk()->assertInertia(fn (Assert $page) => $page->where('afficherLicence', true));
        $this->get('/parametres/licence')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Licence/Index')->where('licence.etat', 'valide'));
        $this->get('/servants')->assertInertia(fn (Assert $page) => $page->where('licence.compteARebours.niveau', 'info'));
    }

    public function test_conseil_modifie_pieu_horaire_etape_et_modele(): void
    {
        $admin = $this->makeUser('administrateur');
        $this->actingAs($admin);

        // Pieu
        $this->post('/parametres/pieux', ['nom' => 'Pieu de Cocody', 'type' => 'pieu'])->assertRedirect()->assertSessionHasNoErrors();
        $pieu = Pieu::where('nom', 'Pieu de Cocody')->firstOrFail();
        $this->put("/parametres/pieux/{$pieu->id}", ['nom' => 'Pieu de Yopougon', 'type' => 'pieu'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Pieu de Yopougon', $pieu->fresh()->nom);

        // Horaire
        $this->post('/parametres/horaires', ['nom' => 'Matin', 'heure_debut' => '07:00', 'heure_fin' => '11:00'])->assertRedirect();
        $horaire = Horaire::where('nom', 'Matin')->firstOrFail();
        $this->put("/parametres/horaires/{$horaire->id}", ['nom' => 'Matin tôt', 'heure_debut' => '06:00', 'heure_fin' => '10:00'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Matin tôt', $horaire->fresh()->nom);
        $this->put("/parametres/horaires/{$horaire->id}", ['nom' => 'X', 'heure_debut' => '10:00', 'heure_fin' => '09:00'])->assertSessionHasErrors('heure_fin');

        // Étape du parcours
        $this->post('/parametres/parcours', ['cle' => 'entretien_test', 'nom' => 'Entretien test'])->assertRedirect();
        $etape = WorkflowStep::where('cle', 'entretien_test')->firstOrFail();
        $this->put("/parametres/parcours/{$etape->id}", ['nom' => 'Entretien révisé', 'ordre' => 4])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Entretien révisé', $etape->fresh()->nom);

        // Modèle de shift et poste
        $this->post('/shift-templates', ['nom' => 'Modèle test'])->assertRedirect();
        $modele = ShiftTemplate::where('nom', 'Modèle test')->firstOrFail();
        $this->put("/shift-templates/{$modele->id}", ['nom' => 'Modèle révisé'])->assertRedirect();
        $this->assertSame('Modèle révisé', $modele->fresh()->nom);
        $this->post("/shift-templates/{$modele->id}/postes", ['nom' => 'Présidence'])->assertRedirect();
        $this->assertDatabaseHas('shift_template_positions', ['shift_template_id' => $modele->id, 'nom' => 'Présidence']);

        // Le rôle reste réservé au Super Administrateur.
        $this->get('/parametres/roles')->assertForbidden();
    }

    public function test_super_admin_gere_les_roles_et_les_comptes_super_admin(): void
    {
        $super = $this->makeUser('super_admin');
        $autreSuper = $this->makeUser('super_admin');
        $this->actingAs($super);

        // Création et modification d'un rôle (Super Administrateur seulement).
        $this->post('/parametres/roles', ['nom' => 'Équipe accueil', 'description' => 'Accueil'])->assertRedirect()->assertSessionHasNoErrors();
        $role = Role::where('slug', 'equipe_accueil')->firstOrFail();
        $this->put("/parametres/roles/{$role->id}", ['nom' => 'Équipe accueil 2', 'description' => 'Accueil 2'])->assertRedirect();
        $this->assertSame('Équipe accueil 2', $role->fresh()->nom);
        $this->delete("/parametres/roles/{$role->id}")->assertRedirect();

        // Voit les comptes Super Admin et peut en suspendre un autre, jamais soi-même.
        $this->get('/parametres/utilisateurs')->assertOk();
        $this->put("/parametres/utilisateurs/{$autreSuper->id}", $this->donneesCompte($autreSuper, ['acces_suspendu' => true]))->assertRedirect();
        $this->assertTrue($autreSuper->fresh()->accesSuspendu());
        $this->put("/parametres/utilisateurs/{$super->id}", $this->donneesCompte($super, ['acces_suspendu' => true]))->assertStatus(422);

        // Mêmes parcours servants que le Conseil.
        $this->post('/servants', ['nom' => 'Super', 'prenom' => 'Servant', 'genre' => 'homme'])->assertRedirect()->assertSessionHasNoErrors();
        $servant = Servant::where('nom', 'Super')->firstOrFail();
        $this->patch("/servants/{$servant->id}/statut", ['statut' => 'en_formation'])->assertRedirect();
        $this->delete("/servants/{$servant->id}", ['confirmation' => 'SUPPRIMER'])->assertRedirect();
        $this->assertNull(Servant::find($servant->id));
    }
}
