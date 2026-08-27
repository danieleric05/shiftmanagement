<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('roles')
            ->where('slug', 'chef_equipe')
            ->update([
                'slug' => 'coordonnateur_equipe',
                'nom' => "Coordonnateur d'équipe",
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')
            ->where('slug', 'coordonnateur_equipe')
            ->update([
                'slug' => 'chef_equipe',
                'nom' => "Chef d'équipe",
            ]);
    }
};
