<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Servant;
use App\Models\ShiftPosition;
use App\Models\ShiftTransferRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Parcours complets Secrétaire, Coordonnateur et « Autres », plus la matrice
 * d'accès rôle par rôle (pages de consultation et pages d'administration).
 */
class ParcoursCompletsRolesTest extends ParcoursCompletsBase
{
    /** @return array<string, array{0: string, 1: array<string, int>}> */
    public static function matriceAcces(): array
    {
        $pages = [
            '/servants' => ['administrateur' => 200, 'super_admin' => 200, 'secretaire' => 200, 'coordonnateur_equipe' => 403, 'autres' => 200],
            '/servants/create' => ['administrateur' => 200, 'super_admin' => 200, 'secretaire' => 200, 'coordonnateur_equipe' => 403, 'autres' => 403],
            '/shifts' => ['administrateur' => 200, 'super_admin' => 200, 'secretaire' => 403, 'coordonnateur_equipe' => 403, 'autres' => 200],
            '/rapports' => ['administrateur' => 200, 'super_admin' => 200, 'secretaire' => 403, 'coordonnateur_equipe' => 403, 'autres' => 200],
            '/recrutement' => ['administrateur' => 200, 'super_admin' => 200, 'secretaire' => 403, 'coordonnateur_equipe' => 200, 'autres' => 200],
            '/transferts' => ['administrateur' => 200, 'super_admin' => 200, 'secretaire' => 200, 'coordonnateur_equipe' => 200, 'autres' => 200],
            '/transferts/releves' => ['administrateur' => 200, 'super_admin' => 200, 'secretaire' => 200, 'coordonnateur_equipe' => 403, 'autres' => 200],
            '/parametres' => ['administrateur' => 200, 'super_admin' => 200, 'secretaire' => 403, 'coordonnateur_equipe' => 403, 'autres' => 403],
            '/parametres/licence' => ['administrateur' => 200, 'super_admin' => 200, 'secretaire' => 403, 'coordonnateur_equipe' => 403, 'autres' => 403],
            '/parametres/utilisateurs' => ['administrateur' => 200, 'super_admin' => 200, 'secretaire' => 403, 'coordonnateur_equipe' => 403, 'autres' => 403],
            '/parametres/roles' => ['administrateur' => 403, 'super_admin' => 200, 'secretaire' => 403, 'coordonnateur_equipe' => 403, 'autres' => 403],
            '/owner/licences' => ['administrateur' => 403, 'super_admin' => 403, 'secretaire' => 403, 'coordonnateur_equipe' => 403, 'autres' => 403],
            '/profile' => ['administrateur' => 200, 'super_admin' => 200, 'secretaire' => 200, 'coordonnateur_equipe' => 200, 'autres' => 200],
        ];

        $cas = [];
        foreach ($pages as $url => $attendus) {
            $cas[$url] = [$url, $attendus];
        }

        return $cas;
    }

    /**
     * @param  array<string, int>  $attendus
     */
    #[DataProvider('matriceAcces')]
    public function test_matrice_d_acces_par_role(string $url, array $attendus): void
    {
        foreach ($attendus as $slug => $statut) {
            $utilisateur = $slug === 'coordonnateur_equipe' ? $this->makeCoordonnateur($this->shiftA) : $this->makeUser($slug);

            $this->actingAs($utilisateur)->get($url)->assertStatus($statut, "{$slug} sur {$url}");
        }
    }

    public function test_secretaire_de_la_creation_d_un_servant_a_la_validation_par_les_coordonnateurs(): void
    {
        $secretaire = $this->makeUser('secretaire');
        $coordA = $this->makeCoordonnateur($this->shiftA);
        $coordB = $this->makeCoordonnateur($this->shiftB);
        $this->actingAs($secretaire);

        // Créer puis modifier un servant ; il apparaît dans « Recommandés ».
        $this->post('/servants', ['nom' => 'Bamba', 'prenom' => 'Issa', 'genre' => 'homme', 'pieu_id' => $this->pieu->id])
            ->assertRedirect()->assertSessionHasNoErrors();
        $servant = Servant::where('nom', 'Bamba')->firstOrFail();
        $this->put("/servants/{$servant->id}", ['nom' => 'Bamba', 'prenom' => 'Issa-Paul', 'genre' => 'homme', 'pieu_id' => $this->pieu->id])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/servants/nouveaux')->assertOk();

        // Affecté à un poste du shift A, il peut permuter vers B : création par la secrétaire…
        $position = ShiftPosition::create(['shift_id' => $this->shiftA->id, 'nom' => 'Poste', 'ordre' => 1]);
        Assignment::create(['shift_position_id' => $position->id, 'servant_id' => $servant->id, 'date_debut' => now()->subWeek()->toDateString(), 'statut' => 'actif']);
        $this->post('/transferts', [
            'shift_id' => $this->shiftA->id, 'shift_destination_id' => $this->shiftB->id, 'type' => 'permutation',
            'servant_id' => $servant->id, 'motif' => 'Disponibilités', 'date_demande' => now()->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $demande = ShiftTransferRequest::where('servant_id', $servant->id)->firstOrFail();

        // …la secrétaire ne valide pas à la place des coordonnateurs ni ne décide sans leurs validations.
        $this->patch("/transferts/{$demande->id}/valider-origine", ['accepte' => true])->assertForbidden();
        $this->patch("/transferts/{$demande->id}/resoudre", ['resultat' => 'Trop tôt', 'resultat_date' => now()->toDateString(), 'favorable' => false])->assertStatus(422);

        // Les deux coordonnateurs voient la demande et valident chacun leur côté.
        foreach ([$coordA, $coordB] as $coord) {
            $this->actingAs($coord)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('permutations.en_attente', 1));
        }
        $this->actingAs($coordA)->patch("/transferts/{$demande->id}/valider-origine", ['accepte' => true])->assertRedirect();
        $this->actingAs($coordB)->patch("/transferts/{$demande->id}/valider-destination", ['accepte' => true])->assertRedirect();

        // Décision finale par la secrétaire : le servant change de shift.
        $posteB = ShiftPosition::create(['shift_id' => $this->shiftB->id, 'nom' => 'Poste B', 'ordre' => 1]);
        $this->actingAs($secretaire)->patch("/transferts/{$demande->id}/resoudre", [
            'resultat' => 'Accordée', 'resultat_date' => now()->toDateString(), 'favorable' => true, 'shift_position_destination_id' => $posteB->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('assignments', ['shift_position_id' => $posteB->id, 'servant_id' => $servant->id, 'statut' => 'actif']);
        $this->assertDatabaseHas('assignments', ['shift_position_id' => $position->id, 'servant_id' => $servant->id, 'statut' => 'termine']);

        // Interdits : statut, paramètres, suppression, réintégration.
        $this->patch("/servants/{$servant->id}/statut", ['statut' => 'actif'])->assertForbidden();
        $this->post("/servants/{$servant->id}/reintegrer")->assertForbidden();
        $this->delete("/servants/{$servant->id}", ['confirmation' => 'SUPPRIMER'])->assertForbidden();
        $this->post('/parametres/pieux', ['nom' => 'Pirate', 'type' => 'pieu'])->assertForbidden();
        $this->post('/parametres/utilisateurs', ['nom' => 'X', 'prenom' => 'Y', 'email' => 'x@example.com', 'password' => 'Password123!', 'role_id' => 1])->assertForbidden();
        $this->assertNotNull(Servant::find($servant->id));
    }

    public function test_coordonnateur_refuse_une_permutation_et_reste_confine_a_ses_shifts(): void
    {
        $coordA = $this->makeCoordonnateur($this->shiftA);
        $coordB = $this->makeCoordonnateur($this->shiftB);
        $servant = $this->makeServantAffecte($this->shiftA);
        $servantB = $this->makeServantAffecte($this->shiftB, 'Poste B');

        $this->actingAs($coordA)->post('/transferts', [
            'shift_id' => $this->shiftA->id, 'shift_destination_id' => $this->shiftB->id, 'type' => 'permutation',
            'servant_id' => $servant->id, 'motif' => 'Test', 'date_demande' => now()->toDateString(),
        ])->assertRedirect();
        $demande = ShiftTransferRequest::where('servant_id', $servant->id)->firstOrFail();

        // Le coordonnateur de destination refuse : la validation est enregistrée comme refus.
        $this->actingAs($coordB)->patch("/transferts/{$demande->id}/valider-destination", ['accepte' => false])->assertRedirect();
        $demande->refresh();
        $this->assertFalse((bool) $demande->validation_chef_destination);
        $this->assertSame($coordB->id, $demande->validation_chef_destination_par_id);
        $this->assertFalse($demande->validationsChefsCompletes());

        // Formulaire : origine limitée à ses shifts, destination = tous les shifts de l'organisation
        // (régression : un coordonnateur d'un seul shift ne pouvait pas créer de permutation).
        $this->actingAs($coordA)->get('/transferts')->assertInertia(fn (Assert $page) => $page
            ->has('shifts', 1)->where('shifts.0.id', $this->shiftA->id)
            ->has('shiftsDestination', 2));

        // Son périmètre : sa fiche servant, pas celle de l'autre shift ; pas de configuration.
        $this->actingAs($coordA)->get("/mon-shift/{$this->shiftA->id}")->assertOk();
        // Le shift de l'autre coordonnateur est consultable (lecture) mais pas « le sien ».
        $this->actingAs($coordA)->get("/mon-shift/{$this->shiftB->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('estMonShift', false));
        $this->actingAs($coordA)->get("/mes-servants/{$servant->id}")->assertOk();
        $this->actingAs($coordA)->get("/mes-servants/{$servantB->id}")->assertForbidden();
        foreach (['/parametres', '/parametres/utilisateurs', '/rapports', '/servants', '/servants/create'] as $url) {
            $this->actingAs($coordA)->get($url)->assertForbidden();
        }
        $this->actingAs($coordA)->patch("/servants/{$servant->id}/statut", ['statut' => 'actif'])->assertForbidden();
        $this->actingAs($coordA)->delete("/servants/{$servant->id}", ['confirmation' => 'SUPPRIMER'])->assertForbidden();
        $this->assertNotNull(Servant::find($servant->id));
    }

    public function test_autres_consulte_tout_et_ne_modifie_rien(): void
    {
        $autres = $this->makeUser('autres');
        $servant = $this->makeServantAffecte($this->shiftA);
        $this->actingAs($autres);

        // Consultation : dashboard (vue administrateur), servants, fiche, shifts, rapports.
        $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Dashboard/Admin'));
        foreach (['/servants', "/servants/{$servant->id}", '/shifts', "/shifts/{$this->shiftA->id}", '/rapports', '/transferts', '/transferts/releves', '/recrutement'] as $url) {
            $this->get($url)->assertOk();
        }

        // Aucune écriture, quel que soit le verbe.
        $this->post('/servants', ['nom' => 'X', 'prenom' => 'Y', 'genre' => 'homme'])->assertForbidden();
        $this->put("/servants/{$servant->id}", ['nom' => 'X', 'prenom' => 'Y', 'statut' => 'actif'])->assertForbidden();
        $this->patch("/servants/{$servant->id}/statut", ['statut' => 'recommande'])->assertForbidden();
        $this->delete("/servants/{$servant->id}", ['confirmation' => 'SUPPRIMER'])->assertForbidden();
        $this->post('/transferts', [
            'shift_id' => $this->shiftA->id, 'type' => 'releve', 'servant_id' => $servant->id, 'motif' => 'x', 'date_demande' => now()->toDateString(),
        ])->assertForbidden();
        $this->put("/recrutement/{$this->shiftA->id}", ['nombre_a_recruter' => 1])->assertForbidden();
        $this->patch('/preferences/colonnes-servants', ['colonnes' => ['nom', 'prenom', 'statut', 'voir', 'pieu']])->assertForbidden();
        $this->assertSame(0, ShiftTransferRequest::count());
        $this->assertSame('actif', $servant->fresh()->statut);

        // Profil : seul le mot de passe peut changer.
        $this->patch('/profile', ['name' => 'Pirate', 'email' => 'pirate@example.com'])->assertForbidden();
        $this->put('/password', [
            'current_password' => 'password', 'password' => 'NouveauMotDePasse123!', 'password_confirmation' => 'NouveauMotDePasse123!',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('NouveauMotDePasse123!', $autres->fresh()->password));
        $this->assertNotSame('Pirate', User::find($autres->id)->name);
    }
}
