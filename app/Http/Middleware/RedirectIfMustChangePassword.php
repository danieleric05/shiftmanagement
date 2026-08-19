<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfMustChangePassword
{
    /**
     * Routes accessibles même quand un changement de mot de passe est requis
     * (la page de profil elle-même, l'enregistrement du nouveau mot de passe,
     * et la déconnexion).
     */
    private const ROUTES_AUTORISEES = ['profile.edit', 'profile.update', 'password.update', 'logout'];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->must_change_password && ! in_array($request->route()?->getName(), self::ROUTES_AUTORISEES, true)) {
            return redirect()->route('profile.edit')
                ->with('warning', 'Veuillez changer votre mot de passe temporaire avant de continuer.');
        }

        return $next($request);
    }
}
