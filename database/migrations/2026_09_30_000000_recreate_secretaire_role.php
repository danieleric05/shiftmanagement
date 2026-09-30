<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Recrée le rôle "secretaire" (supprimé par remove_secretaire_role) avec
     * un nouveau périmètre : gestion des servants et des permutations. Le
     * déploiement ne lance que les migrations, pas le RoleSeeder : on
     * l'insère ici de façon idempotente.
     */
    public function up(): void
    {
        DB::table('roles')->updateOrInsert(
            ['slug' => 'secretaire'],
            [
                'nom' => 'Secrétaire',
                'description' => 'Gestion des servants et des permutations.',
                'gere_shifts' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')->where('slug', 'secretaire')->delete();
    }
};
