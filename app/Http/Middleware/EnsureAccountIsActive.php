<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    /**
     * Un compte suspendu (chapitre Paramètres > Utilisateurs) ne doit plus
     * pouvoir utiliser l'application : on le déconnecte à la première requête
     * suivant sa suspension, plutôt que de simplement masquer l'action côté UI.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->statut === 'suspendu') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Ce compte a été suspendu. Contactez votre administrateur.');
        }

        return $next($request);
    }
}
