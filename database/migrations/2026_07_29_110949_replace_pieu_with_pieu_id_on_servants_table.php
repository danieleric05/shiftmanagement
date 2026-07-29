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
            $table->foreignId('pieu_id')->nullable()->after('pieu')->constrained('pieux')->nullOnDelete();
        });

        Schema::table('servants', function (Blueprint $table) {
            $table->dropColumn('pieu');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servants', function (Blueprint $table) {
            $table->string('pieu')->nullable();
        });

        Schema::table('servants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pieu_id');
        });
    }
};
