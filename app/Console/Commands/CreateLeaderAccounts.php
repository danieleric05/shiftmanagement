<?php

namespace App\Console\Commands;

use App\Models\Organisation;
use App\Models\Servant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Crée un compte de connexion pour chaque servant ayant un titre de
 * leadership reconnu (colonne LEADER du fichier maître), avec un mot de
 * passe temporaire partagé que chacun devra changer à sa première connexion
 * (cf. RedirectIfMustChangePassword). L'email est dérivé du téléphone, car
 * aucun des servants importés n'a d'adresse email dans les fichiers source.
 */
class CreateLeaderAccounts extends Command
{
    protected $signature = 'temple:create-leader-accounts
        {--organisation= : ID de l\'organisation cible (par défaut : la première)}
        {--password= : Mot de passe temporaire partagé (par défaut : Servant2026!)}
        {--force : Applique réellement les changements (sinon la transaction est annulée)}';

    protected $description = 'Crée un compte de connexion (email dérivé du téléphone, mot de passe temporaire) pour chaque servant ayant un titre de leadership reconnu';

    /**
     * Titres de la colonne LEADER qui désignent un vrai rôle de leadership.
     * Les autres valeurs rencontrées dans le fichier source (CW-1..CW-6,
     * "2nd & 4th weeks", des adresses email mal saisies dans cette colonne)
     * ne sont pas des titres et sont volontairement exclues.
     */
    private const TITRES_LEADER = ['OP', 'CO', 'CA', 'TRAINER', 'SEALER', 'BAPT', 'AC'];

    /** @var array<int, string> */
    private array $crees = [];

    /** @var array<int, string> */
    private array $ignores = [];

    public function handle(): int
    {
        $organisationId = (int) ($this->option('organisation') ?: Organisation::query()->value('id'));
        if (! $organisationId) {
            $this->error('Aucune organisation trouvée. Précisez --organisation=ID.');

            return self::FAILURE;
        }

        $password = $this->option('password') ?: 'Servant2026!';

        $servants = Servant::where('organisation_id', $organisationId)
            ->whereNull('user_id')
            ->whereNotNull('titre_leadership')
            ->get()
            ->filter(fn (Servant $s) => in_array(strtoupper(rtrim(trim($s->titre_leadership), '.')), self::TITRES_LEADER, true));

        $applique = false;

        try {
            DB::transaction(function () use ($servants, $organisationId, $password, &$applique) {
                foreach ($servants as $servant) {
                    $this->creerCompte($servant, $organisationId, $password);
                }

                if ($this->option('force')) {
                    $applique = true;

                    return;
                }

                throw new \RuntimeException('__DRY_RUN__');
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== '__DRY_RUN__') {
                throw $e;
            }
        }

        $this->afficherResume($applique, $password);

        return self::SUCCESS;
    }

    private function creerCompte(Servant $servant, int $organisationId, string $password): void
    {
        $email = $this->emailDepuisTelephone($servant->telephone ?? $servant->telephone_appel);

        if ($email === null) {
            $this->ignores[] = "{$servant->nomComplet()} — aucun téléphone exploitable";

            return;
        }

        if (User::where('email', $email)->exists()) {
            $this->ignores[] = "{$servant->nomComplet()} — email {$email} déjà utilisé";

            return;
        }

        $user = User::create([
            'name' => $servant->nomComplet(),
            'email' => $email,
            'password' => Hash::make($password),
            'organisation_id' => $organisationId,
            'role_id' => null,
            'email_verified_at' => now(),
            'must_change_password' => true,
        ]);

        $servant->update(['user_id' => $user->id]);

        $this->crees[] = "{$servant->nomComplet()} ({$servant->titre_leadership}) — {$email}";
    }

    private function emailDepuisTelephone(?string $telephone): ?string
    {
        if (! $telephone) {
            return null;
        }

        $chiffres = preg_replace('/\D+/', '', $telephone);

        return $chiffres !== '' ? "{$chiffres}@shiftmanagement.local" : null;
    }

    private function afficherResume(bool $applique, string $password): void
    {
        $this->newLine();
        $this->info($applique ? 'Comptes créés (--force).' : 'Aperçu uniquement (transaction annulée — relancez avec --force pour appliquer).');
        $this->table(['Résultat', 'Total'], [
            ['Comptes créés', count($this->crees)],
            ['Ignorés', count($this->ignores)],
        ]);

        if ($this->crees !== []) {
            $this->line('Comptes créés :');
            foreach ($this->crees as $ligne) {
                $this->line("  - {$ligne}");
            }
        }

        if ($this->ignores !== []) {
            $this->warn('Ignorés :');
            foreach ($this->ignores as $ligne) {
                $this->line("  - {$ligne}");
            }
        }

        if ($applique) {
            $this->newLine();
            $this->info("Mot de passe temporaire pour tous les comptes créés : {$password}");
            $this->info('Chaque personne devra le changer à sa première connexion.');
        }
    }
}
