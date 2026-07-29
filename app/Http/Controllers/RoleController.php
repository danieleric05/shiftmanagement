<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RoleController extends Controller
{
    public function index()
    {
        return Inertia::render('Settings/Roles/Index', [
            'roles' => Role::orderBy('nom')->get(['id', 'slug', 'nom', 'description']),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $role->update($validated);

        return back()->with('success', 'Rôle mis à jour avec succès.');
    }
}
