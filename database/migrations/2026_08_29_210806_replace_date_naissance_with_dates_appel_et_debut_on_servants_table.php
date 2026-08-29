<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('servants', function (Blueprint $table) {
            $table->dropColumn('date_naissance');
            $table->date('date_appel')->nullable()->after('pieu_id');
            $table->date('date_debut')->nullable()->after('date_appel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servants', function (Blueprint $table) {
            $table->dropColumn(['date_appel', 'date_debut']);
            $table->date('date_naissance')->nullable();
        });
    }
};
