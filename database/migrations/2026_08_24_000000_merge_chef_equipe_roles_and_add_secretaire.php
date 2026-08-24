<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ROLES_FUSIONNES = ['chef_adjoint', 'coordinateur', 'coordinateur_adjoint'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $chefEquipeId = DB::table('roles')->where('slug', 'chef_equipe')->value('id');

        $idsAFusionner = DB::table('roles')->whereIn('slug', self::ROLES_FUSIONNES)->pluck('id', 'slug');

        if ($chefEquipeId && $idsAFusionner->isNotEmpty()) {
            DB::table('users')->whereIn('role_id', $idsAFusionner)->update(['role_id' => $chefEquipeId]);
            DB::table('shift_members')->whereIn('role_id', $idsAFusionner)->update(['role_id' => $chefEquipeId]);
            DB::table('roles')->whereIn('id', $idsAFusionner)->delete();
        }

        // Le rôle "secretaire" lui-même est ajouté par RoleSeeder (comme tous les
        // autres rôles) et non ici : seule la fusion de données est du ressort
        // de cette migration.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Les rôles fusionnés (chef_adjoint/coordinateur/coordinateur_adjoint) et les
        // affectations d'origine ne sont pas restaurables : la fusion est irréversible.
    }
};
