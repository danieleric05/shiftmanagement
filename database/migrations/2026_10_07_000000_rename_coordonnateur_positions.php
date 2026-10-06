<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Les postes « Coordonnateur d'équipe » / « Coordonnatrice d'équipe »
     * deviennent simplement « Coordonnateur » / « Coordonnatrice » (modèles
     * de shift et postes de shift, y compris les postes supprimés en douceur :
     * DB::table n'applique aucun filtre sur deleted_at). Le RÔLE utilisateur
     * « Coordonnateur d'équipe » (slug coordonnateur_equipe) n'est pas concerné.
     *
     * Aucune contrainte d'unicité sur `nom` : un éventuel doublon (poste déjà
     * nommé « Coordonnateur ») ne provoque pas d'erreur. Idempotente : une
     * relance ne trouve plus rien à renommer. Les deux apostrophes (' et ’)
     * sont prises en charge.
     */
    private const RENOMMAGES = [
        "Coordonnateur d'équipe" => 'Coordonnateur',
        'Coordonnateur d’équipe' => 'Coordonnateur',
        "Coordonnatrice d'équipe" => 'Coordonnatrice',
        'Coordonnatrice d’équipe' => 'Coordonnatrice',
    ];

    private const TABLES = ['shift_template_positions', 'shift_positions'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            foreach (self::RENOMMAGES as $ancien => $nouveau) {
                DB::table($table)->where('nom', $ancien)->update(['nom' => $nouveau]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::table($table)->where('nom', 'Coordonnateur')->update(['nom' => "Coordonnateur d'équipe"]);
            DB::table($table)->where('nom', 'Coordonnatrice')->update(['nom' => "Coordonnatrice d'équipe"]);
        }
    }
};
