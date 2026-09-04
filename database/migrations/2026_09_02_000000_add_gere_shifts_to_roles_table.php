<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un rôle peut désormais être marqué « gère des shifts » indépendamment
     * de son slug : la capacité de coordination (User::shiftsGeres(),
     * ShiftPolicy, dashboard coordinateur) ne dépend plus uniquement du
     * rôle protégé "coordonnateur_equipe" mais de ce booléen, activable sur
     * n'importe quel rôle (y compris personnalisé) depuis Paramètres → Rôles.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('gere_shifts')->default(false)->after('description');
        });

        DB::table('roles')->where('slug', 'coordonnateur_equipe')->update(['gere_shifts' => true]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('gere_shifts');
        });
    }
};
