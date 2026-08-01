<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsPlatformOwner
{
    /**
     * Handle an incoming request.
     *
     * Réserve l'accès au propriétaire de la plateforme (gestion des licences
     * de toutes les organisations clientes), indépendamment des rôles et
     * organisations métier.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_platform_owner) {
            abort(403, "Accès refusé. Vous n'avez pas les permissions nécessaires.");
        }

        return $next($request);
    }
}
