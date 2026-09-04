<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `name` reste la colonne d'authentification (Breeze, notifications,
     * emails — inchangés). `nom`/`prenom` sont des champs additionnels pour
     * la gestion des comptes (Paramètres → Utilisateurs), qui n'imposait
     * jusqu'ici qu'un seul champ "Nom" mélangeant prénom et nom de famille.
     * Rétro-remplis au mieux depuis `name` (convention "Prénom Nom" déjà
     * utilisée pour les servants) pour que les comptes existants s'affichent
     * correctement sans ressaisie.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('prenom')->nullable()->after('name');
            $table->string('nom')->nullable()->after('prenom');
        });

        DB::table('users')->orderBy('id')->chunk(200, function ($users) {
            foreach ($users as $user) {
                $parties = preg_split('/\s+/', trim($user->name), 2);

                DB::table('users')->where('id', $user->id)->update([
                    'prenom' => $parties[0] ?? $user->name,
                    'nom' => $parties[1] ?? '',
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['prenom', 'nom']);
        });
    }
};
