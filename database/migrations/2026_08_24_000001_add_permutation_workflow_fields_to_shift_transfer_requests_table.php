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
        Schema::table('shift_transfer_requests', function (Blueprint $table) {
            $table->boolean('validation_chef_origine')->nullable()->after('approuve_deux_shifts');
            $table->foreignId('validation_chef_origine_par_id')->nullable()->after('validation_chef_origine')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('validation_chef_origine_le')->nullable()->after('validation_chef_origine_par_id');

            $table->boolean('validation_chef_destination')->nullable()->after('validation_chef_origine_le');
            // Nom de contrainte explicite : le nom auto-généré dépasse la limite de 64
            // caractères de MySQL pour cette colonne.
            $table->foreignId('validation_chef_destination_par_id')->nullable()->after('validation_chef_destination')
                ->constrained('users', 'id', 'str_valid_chef_destination_par_id_foreign')->nullOnDelete();
            $table->timestamp('validation_chef_destination_le')->nullable()->after('validation_chef_destination_par_id');

            $table->date('entretien_date')->nullable()->after('validation_chef_destination_le');
            $table->time('entretien_heure')->nullable()->after('entretien_date');

            $table->boolean('favorable')->nullable()->after('resultat_date');
            $table->foreignId('shift_position_destination_id')->nullable()->after('favorable')
                ->constrained('shift_positions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shift_transfer_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_position_destination_id');
            $table->dropForeign('str_valid_chef_destination_par_id_foreign');
            $table->dropConstrainedForeignId('validation_chef_origine_par_id');
            $table->dropColumn([
                'validation_chef_origine', 'validation_chef_origine_le',
                'validation_chef_destination', 'validation_chef_destination_par_id', 'validation_chef_destination_le',
                'entretien_date', 'entretien_heure', 'favorable',
            ]);
        });
    }
};
