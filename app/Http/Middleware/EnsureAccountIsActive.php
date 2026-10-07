<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    /**
     * Un compte dont l'accès est suspendu (Paramètres > Utilisateurs, colonne
     * `acces_suspendu`) ne doit plus pouvoir utiliser l'application : on le
     * déconnecte à la première requête suivant le blocage, plutôt que de
     * simplement masquer l'action côté UI. Le statut de la personne
     * (Recommandé / Nouveau / Ancien) n'a, lui, aucun effet sur l'accès.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->accesSuspendu()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Ce compte a été suspendu. Contactez votre administrateur.');
        }

        return $next($request);
    }
}
