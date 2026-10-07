<?php

namespace App\Http\Controllers;

use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Applique les migrations après un déploiement Plesk (hébergement sans SSH ni
 * planificateur) : appelé par le pipeline GitHub avec l'en-tête X-Deploy-Token.
 */
class SystemMigrateController extends Controller
{
    public const LOCK_NAME = 'system:migrate';

    private const LOCK_SECONDS = 600;

    private const MAX_OUTPUT = 4000;

    public const MAX_ATTEMPTS = 6;

    private const DECAY_SECONDS = 60;

    public function __invoke(Request $request): JsonResponse
    {
        // Limite GLOBALE (pas par IP) : le seul appelant légitime est le pipeline de
        // déploiement, et l'IP peut être falsifiée via X-Forwarded-For (proxys de
        // confiance « * ») ; une clé par IP permettrait de contourner le plafond.
        $retryAfter = $this->throttle('system-migrate');

        if ($retryAfter !== null) {
            return response()->json(['message' => 'Trop de tentatives.'], 429, ['Retry-After' => (string) $retryAfter]);
        }

        $token = (string) config('services.deploy.token');

        if ($token === '' || ! hash_equals($token, (string) $request->header('X-Deploy-Token'))) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $lock = $this->acquireLock();

        if ($lock === null) {
            return response()->json(['message' => 'Une migration est déjà en cours.'], 409);
        }

        try {
            /** @var Migrator $migrator */
            $migrator = app('migrator');
            $before = $this->ranCount($migrator);

            if (Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
                throw new RuntimeException('migrate a renvoyé un code de sortie non nul : '.Artisan::output());
            }
            $output = Artisan::output();

            $migrated = $this->ranCount($migrator) > $before;

            // Toujours : inoffensif, et évite de servir une configuration ou des routes périmées.
            Artisan::call('optimize:clear');
            $output .= Artisan::output();
        } catch (Throwable $e) {
            Log::error('Échec de la migration déclenchée par le déploiement.', ['exception' => $e]);

            return response()->json(['message' => 'La migration a échoué. Consulter les logs.'], 500);
        } finally {
            $lock->release();
        }

        return response()->json([
            'migrated' => $migrated,
            'output' => Str::limit($this->clean($output), self::MAX_OUTPUT),
        ]);
    }

    /**
     * Limitation de débit par IP (6 appels par minute), y compris pour les jetons faux.
     * Pas de middleware `throttle` : il exige le cache par défaut (`database`), absent
     * lors de la toute première installation ; repli sur le cache fichier comme le verrou.
     *
     * @return int|null secondes à attendre si la limite est atteinte
     */
    private function throttle(string $key): ?int
    {
        try {
            return $this->hit(new RateLimiter(Cache::store()), $key);
        } catch (Throwable) {
            return $this->hit(new RateLimiter(Cache::store('file')), $key);
        }
    }

    private function hit(RateLimiter $limiter, string $key): ?int
    {
        if ($limiter->tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return max(1, $limiter->availableIn($key));
        }

        $limiter->hit($key, self::DECAY_SECONDS);

        return null;
    }

    /**
     * Verrou dans le cache de l'application ; si ce cache est inutilisable (table
     * `cache` absente lors de la toute première installation), repli sur le cache fichier.
     */
    private function acquireLock(): ?Lock
    {
        try {
            $lock = Cache::lock(self::LOCK_NAME, self::LOCK_SECONDS);

            return $lock->get() ? $lock : null;
        } catch (Throwable) {
            $store = Cache::store('file')->getStore();

            if (! $store instanceof LockProvider) {
                throw new RuntimeException('Le cache fichier ne gère pas les verrous.');
            }

            $lock = $store->lock(self::LOCK_NAME, self::LOCK_SECONDS);

            return $lock->get() ? $lock : null;
        }
    }

    private function ranCount(Migrator $migrator): int
    {
        return $migrator->repositoryExists() ? count($migrator->getRepository()->getRan()) : 0;
    }

    /** Retire les codes de couleur ANSI et les espaces superflus de la sortie Artisan. */
    private function clean(string $output): string
    {
        return trim((string) preg_replace('/\e\[[\d;]*[A-Za-z]/', '', $output));
    }
}
