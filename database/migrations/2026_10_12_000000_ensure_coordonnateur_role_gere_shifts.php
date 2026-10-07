<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Le rôle Coordonnateur (slug `coordonnateur_equipe`) porte le droit « gère des Shifts »
     * (tableau de bord coordonnateur, validation des permutations de ses shifts…).
     *
     * La migration add_gere_shifts_to_roles_table l'activait par un UPDATE, mais sur une base
     * neuve les rôles sont créés APRÈS les migrations (RoleSeeder) : le rôle naissait donc
     * avec gere_shifts = false et les coordonnateurs n'avaient aucun droit. Cette migration
     * corrige les bases déjà installées ; RoleSeeder crée désormais le rôle avec le bon réglage.
     */
    public function up(): void
    {
        DB::table('roles')
            ->where('slug', 'coordonnateur_equipe')
            ->where('gere_shifts', false)
            ->update(['gere_shifts' => true]);
    }

    public function down(): void
    {
        // Volontairement vide : on ne désactive pas un droit métier au retour arrière.
    }
};
