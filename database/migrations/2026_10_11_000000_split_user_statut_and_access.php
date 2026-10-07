<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sépare deux notions jusqu'ici confondues dans `users.statut` :
 * - le statut de la PERSONNE (Recommandé / Nouveau / Ancien, valeurs
 *   techniques `recommande`, `en_formation`, `actif`, comme pour les servants) ;
 * - le BLOCAGE d'accès au compte (fonction de sécurité), désormais porté par
 *   la colonne booléenne `acces_suspendu`.
 *
 * Tout compte « suspendu » reste bloqué (`acces_suspendu = true`) et prend le
 * statut `actif` (Ancien). Relançable sans effet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'acces_suspendu')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('acces_suspendu')->default(false)->after('statut');
            });
        }

        // L'ancienne colonne était un ENUM('actif', 'suspendu') : on l'élargit
        // en chaîne (liste blanche appliquée par la validation).
        Schema::table('users', function (Blueprint $table) {
            $table->string('statut', 20)->default('actif')->change();
        });

        // Ordre important : on bloque l'accès AVANT de changer le statut, dans
        // la même requête, pour qu'aucun compte suspendu ne retrouve l'accès.
        DB::table('users')
            ->where('statut', 'suspendu')
            ->update(['acces_suspendu' => true, 'statut' => 'actif']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'acces_suspendu')) {
            DB::table('users')->where('acces_suspendu', true)->update(['statut' => 'suspendu']);
        }

        // Les statuts Recommandé / Nouveau n'existaient pas pour les comptes.
        DB::table('users')->whereNotIn('statut', ['actif', 'suspendu'])->update(['statut' => 'actif']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('statut', ['actif', 'suspendu'])->default('actif')->change();
        });

        if (Schema::hasColumn('users', 'acces_suspendu')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('acces_suspendu');
            });
        }
    }
};
