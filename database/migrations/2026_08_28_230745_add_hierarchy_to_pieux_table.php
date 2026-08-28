<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Un servant peut être rattaché soit à un Pieu, soit directement à un
     * District, soit directement à une Mission (organisation en trois
     * niveaux, sélection semi-automatique en cascade côté formulaire). Les
     * lignes existantes deviennent des Pieu de niveau racine (type="pieu",
     * parent_id=null).
     */
    public function up(): void
    {
        Schema::table('pieux', function (Blueprint $table) {
            $table->enum('type', ['mission', 'district', 'pieu'])->default('pieu')->after('nom');
            $table->foreignId('parent_id')->nullable()->after('type')->constrained('pieux')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pieux', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('type');
        });
    }
};
