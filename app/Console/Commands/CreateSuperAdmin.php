<?php

namespace App\Console\Commands;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Crée le premier compte d'administration sur un serveur sans shell ni Faker
 * (Plesk : seule une commande `php artisan <commande> <args simples>` est
 * possible, sans invite interactive). Le mot de passe temporaire est généré,
 * affiché une seule fois et doit être changé à la première connexion
 * (cf. RedirectIfMustChangePassword). Il n'est jamais journalisé : le modèle
 * User n'utilise pas LogsActivity.
 */
class CreateSuperAdmin extends Command
{
    protected $signature = 'app:create-super-admin
        {email : Adresse e-mail du compte à créer}
        {--name=Super Admin : Nom affiché du compte}
        {--platform-owner : Crée un propriétaire de plateforme (sans organisation ni rôle)}';

    protected $description = 'Crée un compte super administrateur (ou propriétaire de plateforme) avec un mot de passe temporaire à changer à la première connexion';

    /** Alphabet sans caractères ambigus (0/O, 1/l/I) ni guillemets. */
    private const MINUSCULES = 'abcdefghijkmnopqrstuvwxyz';

    private const MAJUSCULES = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const CHIFFRES = '23456789';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error("Adresse e-mail invalide : {$email}");

            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->error("Un utilisateur avec l'adresse {$email} existe déjà. Aucun compte créé.");

            return self::FAILURE;
        }

        $name = trim((string) $this->option('name')) ?: 'Super Admin';
        $platformOwner = (bool) $this->option('platform-owner');

        $organisationId = null;
        $roleId = null;

        if (! $platformOwner) {
            $roleId = Role::where('slug', 'super_admin')->value('id');
            $organisationId = Organisation::query()->orderBy('id')->value('id');

            if (! $roleId || ! $organisationId) {
                $manquants = array_filter([
                    $roleId ? null : 'le rôle super_admin',
                    $organisationId ? null : 'une organisation',
                ]);
                $this->error('Introuvable : '.implode(' et ', $manquants).'. Lancer d\'abord RoleSeeder et OrganisationSeeder.');

                return self::FAILURE;
            }
        }

        $password = $this->genererMotDePasse();
        $parties = preg_split('/\s+/', $name, 2);

        User::forceCreate([
            'name' => $name,
            'prenom' => $parties[0] ?? $name,
            'nom' => $parties[1] ?? '',
            'email' => $email,
            'password' => $password,
            'organisation_id' => $organisationId,
            'role_id' => $roleId,
            'is_platform_owner' => $platformOwner,
            'must_change_password' => true,
            'statut' => 'actif',
            'email_verified_at' => now(),
        ]);

        $this->info($platformOwner
            ? "Propriétaire de plateforme créé : {$email}"
            : "Super administrateur créé : {$email}");
        $this->newLine();
        $this->warn('Mot de passe temporaire (affiché une seule fois, changement obligatoire à la première connexion) :');
        $this->line($password);

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

        // Mélange cryptographiquement sûr (Fisher-Yates avec random_int).
        for ($i = count($caracteres) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$caracteres[$i], $caracteres[$j]] = [$caracteres[$j], $caracteres[$i]];
        }

        return implode('', $caracteres);
    }
}
