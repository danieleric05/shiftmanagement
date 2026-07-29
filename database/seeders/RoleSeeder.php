<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['slug' => 'super_admin', 'nom' => 'Super Administrateur', 'description' => "Responsable global de la plateforme."],
            ['slug' => 'administrateur', 'nom' => 'Administrateur', 'description' => "Gestion administrative des Shifts et des membres."],
            ['slug' => 'chef_equipe', 'nom' => "Chef d'équipe", 'description' => "Garant du Shift, autorité opérationnelle."],
            ['slug' => 'chef_adjoint', 'nom' => "Chef d'équipe adjoint", 'description' => "Second du Chef d'équipe."],
            ['slug' => 'coordinateur', 'nom' => 'Coordinateur', 'description' => "Assiste le Chef d'équipe dans l'organisation quotidienne."],
            ['slug' => 'coordinateur_adjoint', 'nom' => 'Coordinateur adjoint', 'description' => 'Second du Coordinateur.'],
            ['slug' => 'servant', 'nom' => 'Servant', 'description' => "Membre de l'équipe opérationnelle."],
            ['slug' => 'membre', 'nom' => 'Membre', 'description' => 'Membre standard du Shift.'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
