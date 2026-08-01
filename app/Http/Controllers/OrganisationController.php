<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrganisationController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'admin_nom' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'license_expires_at' => ['nullable', 'date'],
        ]);

        $organisation = Organisation::create([
            'nom' => $validated['nom'],
            'license_expires_at' => $validated['license_expires_at'] ?? null,
        ]);

        $password = Str::password(12);

        User::forceCreate([
            'name' => $validated['admin_nom'],
            'email' => $validated['admin_email'],
            'password' => $password,
            'organisation_id' => $organisation->id,
            'role_id' => Role::where('slug', 'administrateur')->value('id'),
            'email_verified_at' => now(),
        ]);

        return back()
            ->with('success', "Organisation « {$organisation->nom} » créée avec succès.")
            ->with('credentials', [
                'organisation' => $organisation->nom,
                'email' => $validated['admin_email'],
                'password' => $password,
            ]);
    }
}
