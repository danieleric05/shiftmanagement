<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Settings/Index', [
            // Section Licence : Conseil du Temple / Super Administrateur
            // rattachés à une organisation (pas le propriétaire de plateforme).
            'afficherLicence' => (bool) $user?->estAdministrateur() && $user->organisation_id !== null,
        ]);
    }
}
