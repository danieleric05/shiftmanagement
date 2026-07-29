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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organisation_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('role_id')->nullable()->after('organisation_id')->constrained()->nullOnDelete();
            $table->string('telephone')->nullable()->after('email');
            $table->string('photo')->nullable()->after('telephone');
            $table->enum('statut', ['actif', 'suspendu'])->default('actif')->after('photo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organisation_id');
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn(['telephone', 'photo', 'statut']);
        });
    }
};
