<?php

namespace App\Console\Commands;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\Shift;
use App\Models\ShiftMember;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Crée (ou supprime) un compte de test par rôle pour les essais manuels sur
 * STAGING. Pensée pour Plesk (Laravel Toolkit > Artisan) : aucune invite
 * interactive, aucun guillemet imbriqué. Deux garde-fous empêchent tout
 * usage en production : le drapeau TEST_ACCOUNTS_ENABLED=true est obligatoire
 * et l'URL de production est toujours refusée. Les mots de passe sont
 * aléatoires, affichés une seule fois, jamais journalisés.
 */
class CreerComptesTest extends Command
{
    protected $signature = 'app:creer-comptes-test
        {--reinitialiser : Régénère le mot de passe des comptes de test déjà existants}
        {--supprimer : Supprime tous les comptes de test (et leurs liens avec les shifts)}';

    protected $description = 'STAGING UNIQUEMENT : crée un compte de test par rôle (mots de passe aléatoires affichés une fois) ou les supprime avec --supprimer';

    /** Hôte de production : cette commande refuse toujours de s'y exécuter. */
    private const HOTE_PRODUCTION = 'shifts.daertech.ci';

    /** Préfixe des noms de shift de test créés par cette commande. */
    private const PREFIXE_SHIFT_TEST = 'TEST ';

    /**
     * Comptes de test : slug du rôle => [e-mail, prénom, nom].
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const COMPTES = [
        'administrateur' => ['test.conseil@staging.daertech.ci', 'TEST', 'Conseil du Temple'],
        'secretaire' => ['test.secretaire@staging.daertech.ci', 'TEST', 'Secrétaire'],
        'coordonnateur_equipe' => ['test.coordonnateur@staging.daertech.ci', 'TEST', 'Coordonnateur'],
        'autres' => ['test.autres@staging.daertech.ci', 'TEST', 'Autres'],
        'super_admin' => ['test.superadmin@staging.daertech.ci', 'TEST', 'Super Administrateur'],
    ];

    /** Alphabet sans caractères ambigus (0/O, 1/l/I) ni guillemets. */
    private const MINUSCULES = 'abcdefghijkmnopqrstuvwxyz';

    private const MAJUSCULES = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const CHIFFRES = '23456789';

    public function handle(): int
    {
        if ($refus = $this->raisonDeRefus()) {
            $this->error($refus);

            return self::FAILURE;
        }

        return $this->option('supprimer') ? $this->supprimer() : $this->creer();
    }

    private function raisonDeRefus(): ?string
    {
        $url = strtolower((string) config('app.url'));

        if (str_contains($url, self::HOTE_PRODUCTION)) {
            return 'Refusé : APP_URL pointe vers la production ('.self::HOTE_PRODUCTION.'). Les comptes de test ne doivent jamais exister en production.';
        }

        if (config('services.test_accounts.enabled') !== true) {
            return 'Refusé : les comptes de test sont désactivés. Sur STAGING uniquement, ajouter TEST_ACCOUNTS_ENABLED=true dans le fichier .env puis lancer config:clear.';
        }

        return null;
    }

    private function creer(): int
    {
        $organisationId = Organisation::query()->orderBy('id')->value('id');
        $roles = Role::whereIn('slug', array_keys(self::COMPTES))->get()->keyBy('slug');
        $manquants = array_diff(array_keys(self::COMPTES), $roles->keys()->all());

        if (! $organisationId || $manquants !== []) {
            $this->error('Introuvable : '.($organisationId ? '' : 'une organisation ').($manquants ? 'rôle(s) '.implode(', ', $manquants) : '').'. Lancer d\'abord RoleSeeder et OrganisationSeeder.');

            return self::FAILURE;
        }

        $reinitialiser = (bool) $this->option('reinitialiser');
        $motsDePasse = [];

        foreach (self::COMPTES as $slug => [$email, $prenom, $nom]) {
            $existant = User::where('email', $email)->first();

            if ($existant && ! $reinitialiser) {
                $this->line("Déjà existant, conservé : {$email}");

                continue;
            }

            $motDePasse = $this->genererMotDePasse();

            if ($existant) {
                $existant->forceFill([
                    'password' => $motDePasse,
                    'must_change_password' => false,
                    'acces_suspendu' => false,
                    'organisation_id' => $organisationId,
                    'role_id' => $roles[$slug]->id,
                ])->save();
                $this->line("Mot de passe réinitialisé : {$email}");
            } else {
                User::forceCreate([
                    'name' => "{$prenom} {$nom}",
                    'prenom' => $prenom,
                    'nom' => $nom,
                    'email' => $email,
                    'password' => $motDePasse,
                    'organisation_id' => $organisationId,
                    'role_id' => $roles[$slug]->id,
                    'must_change_password' => false,
                    'statut' => 'actif',
                    'email_verified_at' => now(),
                ]);
                $this->line("Créé : {$email}");
            }

            $motsDePasse[$email] = $motDePasse;
        }

        $this->lierCoordonnateur($organisationId, $roles['coordonnateur_equipe']);

        if ($motsDePasse !== []) {
            $this->newLine();
            $this->warn('Mots de passe (affichés UNE SEULE FOIS, à noter maintenant) :');
            foreach ($motsDePasse as $email => $motDePasse) {
                $this->line("{$email}  {$motDePasse}");
            }
        } else {
            $this->newLine();
            $this->info('Aucun nouveau compte : tous existaient déjà. Utiliser --reinitialiser pour obtenir de nouveaux mots de passe.');
        }

        return self::SUCCESS;
    }

    /**
     * Le dashboard coordonnateur n'apparaît que si le compte gère au moins un
     * shift : User::shiftsGeres() = ShiftMember actif dont le rôle a
     * gere_shifts. On l'inscrit donc comme membre (rôle Coordonnateur) du
     * premier shift de l'organisation, ou d'un shift de test créé au besoin.
     */
    private function lierCoordonnateur(int $organisationId, Role $role): void
    {
        $coordonnateur = User::where('email', self::COMPTES['coordonnateur_equipe'][0])->first();

        if (! $coordonnateur) {
            return;
        }

        if (! $role->gere_shifts) {
            $this->warn('Attention : le rôle Coordonnateur n\'a pas le droit « gère des Shifts » (Paramètres > Rôles) : son dashboard restera vide.');
        }

        if ($coordonnateur->shiftMemberships()->where('statut', 'actif')->where('role_id', $role->id)->exists()) {
            return;
        }

        $shift = Shift::where('organisation_id', $organisationId)->where('statut', 'actif')->orderBy('id')->first();

        if (! $shift) {
            $shift = Shift::create([
                'organisation_id' => $organisationId,
                'nom' => self::PREFIXE_SHIFT_TEST.'Shift de démonstration',
                'jour' => 'mardi',
                'heure_debut' => '07:00',
                'heure_fin' => '11:00',
                'statut' => 'actif',
            ]);
            $this->line("Aucun shift existant : shift de test créé ({$shift->nom}).");
        }

        ShiftMember::create([
            'shift_id' => $shift->id,
            'user_id' => $coordonnateur->id,
            'role_id' => $role->id,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);
        $this->line("Coordonnateur de test lié au shift : {$shift->nom}");
    }

    private function supprimer(): int
    {
        $emails = array_column(self::COMPTES, 0);

        DB::transaction(function () use ($emails): void {
            $utilisateurs = User::whereIn('email', $emails)->get();

            foreach ($utilisateurs as $utilisateur) {
                // Liens explicites avant suppression (les clés étrangères en
                // cascade / SET NULL le feraient aussi, mais on journalise).
                ShiftMember::where('user_id', $utilisateur->id)->delete();
                $utilisateur->delete();
                $this->line("Supprimé : {$utilisateur->email}");
            }

            if ($utilisateurs->isEmpty()) {
                $this->line('Aucun compte de test à supprimer.');
            }

            // Shift de démonstration créé par cette commande, uniquement s'il est vide.
            Shift::where('nom', self::PREFIXE_SHIFT_TEST.'Shift de démonstration')
                ->whereDoesntHave('shiftMembers')
                ->whereDoesntHave('positions')
                ->get()
                ->each(function (Shift $shift): void {
                    $shift->forceDelete();
                    $this->line('Shift de test supprimé.');
                });
        });

        $this->info('Comptes de test supprimés.');

        return self::SUCCESS;
    }

    private function genererMotDePasse(int $longueur = 16): string
    {
        $tous = self::MINUSCULES.self::MAJUSCULES.self::CHIFFRES;

        $caracteres = [
            self::MINUSCULES[random_int(0, strlen(self::MINUSCULES) - 1)],
            self::MAJUSCULES[random_int(0, strlen(self::MAJUSCULES) - 1)],
            self::CHIFFRES[random_int(0, strlen(self::CHIFFRES) - 1)],
        ];

        while (count($caracteres) < $longueur) {
            $caracteres[] = $tous[random_int(0, strlen($tous) - 1)];
        }

        for ($i = count($caracteres) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$caracteres[$i], $caracteres[$j]] = [$caracteres[$j], $caracteres[$i]];
        }

        return implode('', $caracteres);
    }
}
