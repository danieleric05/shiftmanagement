<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Quand ?page=N dépasse la dernière page (ex. après un filtrage ou une
     * suppression), redirige vers la dernière page en conservant les autres
     * paramètres de la requête (recherche, filtres…). Retourne null sinon.
     */
    protected function redirigerSiPageHorsLimites(LengthAwarePaginator $paginateur, Request $request): ?RedirectResponse
    {
        if ($paginateur->total() === 0 || $paginateur->currentPage() <= $paginateur->lastPage()) {
            return null;
        }

        // Chemin relatif : évite de dépendre de l'en-tête Host de la requête.
        $query = array_merge($request->query(), [$paginateur->getPageName() => $paginateur->lastPage()]);

        return redirect()->to('/'.ltrim($request->path(), '/').'?'.http_build_query($query));
    }
}
