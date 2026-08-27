<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['slug' => 'super_admin', 'nom' => 'Super Administrateur', 'description' => 'Responsable global de la plateforme.'],
            ['slug' => 'administrateur', 'nom' => 'Administrateur', 'description' => 'Gestion administrative des Shifts et des membres.'],
            ['slug' => 'coordonnateur_equipe', 'nom' => "Coordonnateur d'équipe", 'description' => 'Garant du Shift, autorité opérationnelle.'],
            ['slug' => 'secretaire', 'nom' => 'Secrétaire', 'description' => 'Prise de rendez-vous : gestion des candidats et des entretiens.'],
            ['slug' => 'servant', 'nom' => 'Servant', 'description' => "Membre de l'équipe opérationnelle."],
            ['slug' => 'membre', 'nom' => 'Membre', 'description' => 'Membre standard du Shift.'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
