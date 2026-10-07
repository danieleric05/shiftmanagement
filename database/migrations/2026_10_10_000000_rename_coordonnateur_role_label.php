<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Le RÔLE « Coordonnateur d'équipe » s'affiche désormais « Coordonnateur »
     * (nom affiché seulement : le slug coordonnateur_equipe ne change pas).
     * Les deux apostrophes (' et ’) sont prises en charge. Idempotente : une
     * relance ne trouve plus rien à renommer, et un nom personnalisé n'est
     * jamais écrasé. Les postes ont été renommés séparément
     * (2026_10_07_000000_rename_coordonnateur_positions).
     */
    public function up(): void
    {
        DB::table('roles')
            ->where('slug', 'coordonnateur_equipe')
            ->whereIn('nom', ["Coordonnateur d'équipe", 'Coordonnateur d’équipe'])
            ->update(['nom' => 'Coordonnateur']);
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('slug', 'coordonnateur_equipe')
            ->where('nom', 'Coordonnateur')
            ->update(['nom' => "Coordonnateur d'équipe"]);
    }
};
