<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Crée le rôle « Autres » : consultation en lecture seule de toute
     * l'organisation (hors configuration). Le déploiement ne lance que les
     * migrations, pas le RoleSeeder : on l'insère ici de façon idempotente.
     */
    public function up(): void
    {
        DB::table('roles')->insertOrIgnore([
            'slug' => 'autres',
            'nom' => 'Autres',
            'description' => 'Consultation en lecture seule (hors configuration).',
            'gere_shifts' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')->where('slug', 'autres')->delete();
    }
};
