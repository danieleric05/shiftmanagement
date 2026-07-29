<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * Le Super Administrateur a toujours accès, quel que soit le rôle demandé,
     * conformément au chapitre 2.2.1 (supervision globale de la plateforme).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->role) {
            abort(403, "Accès refusé. Vous n'avez pas les permissions nécessaires.");
        }

        if ($user->role->slug === 'super_admin' || in_array($user->role->slug, $roles, true)) {
            return $next($request);
        }

        abort(403, "Accès refusé. Vous n'avez pas les permissions nécessaires.");
    }
}
