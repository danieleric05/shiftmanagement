<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganisationLicenseIsActive
{
    /**
     * Handle an incoming request.
     *
     * Une organisation dont la licence a expiré passe en lecture seule :
     * les requêtes de consultation (GET/HEAD) restent autorisées, toute
     * mutation est bloquée jusqu'au renouvellement par le propriétaire
     * de la plateforme.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $organisation = $request->user()?->organisation;

        if ($organisation?->isLicenseExpired() && ! $request->isMethodCacheable()) {
            abort(403, 'Licence expirée. Contactez votre administrateur pour la renouveler.');
        }

        return $next($request);
    }
}
