<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Renomme le rôle "administrateur" en "Conseil du Temple" (libellé
     * uniquement, le slug interne "administrateur" reste inchangé — il est
     * utilisé partout dans les policies/routes). Supprime les rôles "membre"
     * et "servant", devenus inutiles : les comptes liés à un servant sont
     * désormais créés avec role_id = null (cf. ServantController::storeAccount,
     * CreateLeaderAccounts), le tableau de bord retombe déjà sur la vue
     * "membre" par défaut pour tout utilisateur sans rôle.
     */
    public function up(): void
    {
        DB::table('roles')->where('slug', 'administrateur')->update(['nom' => 'Conseil du Temple']);

        DB::table('users')->whereIn('role_id', DB::table('roles')->whereIn('slug', ['membre', 'servant'])->pluck('id'))
            ->update(['role_id' => null]);

        DB::table('roles')->whereIn('slug', ['membre', 'servant'])->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')->where('slug', 'administrateur')->update(['nom' => 'Administrateur']);

        DB::table('roles')->insertOrIgnore([
            ['slug' => 'membre', 'nom' => 'Membre', 'description' => 'Membre standard du Shift.', 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'servant', 'nom' => 'Servant', 'description' => "Membre de l'équipe opérationnelle.", 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
};
