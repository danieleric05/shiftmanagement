<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Paramètres → Licence : consultation en lecture seule de la licence de
 * l'organisation de l'utilisateur connecté (Conseil du Temple et Super
 * Administrateur). À ne pas confondre avec l'espace propriétaire
 * (/owner/licences) qui gère les licences de toutes les organisations.
 */
class LicenceOrganisationController extends Controller
{
    public function index(Request $request): Response
    {
        // Seule l'organisation de l'utilisateur est lisible : un compte sans
        // organisation (propriétaire de plateforme) n'a pas de licence à consulter.
        $organisation = $request->user()?->organisation;
        abort_if($organisation === null, 403, "Aucune organisation n'est rattachée à ce compte.");

        $expiresAt = $organisation->license_expires_at;
        $secondesRestantes = $expiresAt !== null ? now()->diffInSeconds($expiresAt, false) : null;

        return Inertia::render('Settings/Licence/Index', [
            'licence' => [
                'organisation' => $organisation->nom,
                'etat' => $organisation->etatLicence(),
                'niveau' => $organisation->niveauLicence(),
                'expiresAtIso' => $expiresAt?->toIso8601String(),
                'joursRestants' => $secondesRestantes !== null && $secondesRestantes > 0
                    ? (int) floor($secondesRestantes / 86400)
                    : null,
            ],
        ]);
    }
}
