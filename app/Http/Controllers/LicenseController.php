<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LicenseController extends Controller
{
    public function index()
    {
        $organisations = Organisation::select(['id', 'nom', 'license_expires_at'])
            ->withCount('users')
            ->orderBy('nom')
            ->get();

        $expirantBientot = $organisations->filter(function (Organisation $organisation) {
            return $organisation->license_expires_at !== null
                && ! $organisation->isLicenseExpired()
                && $organisation->license_expires_at->diffInDays(now()) <= 14;
        })->count();

        return Inertia::render('Owner/Licenses/Index', [
            'organisations' => $organisations,
            'stats' => [
                'total' => $organisations->count(),
                'expirantBientot' => $expirantBientot,
                'expirees' => $organisations->filter(fn (Organisation $organisation) => $organisation->isLicenseExpired())->count(),
            ],
        ]);
    }

    public function update(Request $request, Organisation $organisation)
    {
        $validated = $request->validate([
            'nom' => ['sometimes', 'string', 'max:255'],
            'license_expires_at' => ['nullable', 'date'],
        ]);

        $organisation->update($validated);

        return back()->with('success', 'Licence mise à jour avec succès.');
    }
}
