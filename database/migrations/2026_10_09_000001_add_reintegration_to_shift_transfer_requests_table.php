<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Réintégration d'un servant relevé : la relève (historique) est conservée
     * et simplement marquée comme close par une réintégration. Une relève non
     * réintégrée (reintegre_le NULL) place le servant dans l'état « relevé ».
     */
    public function up(): void
    {
        Schema::table('shift_transfer_requests', function (Blueprint $table) {
            $table->timestamp('reintegre_le')->nullable()->after('decideur_id');
            $table->foreignId('reintegre_par_id')->nullable()->after('reintegre_le')->constrained('users')->nullOnDelete();
            $table->text('reintegration_commentaire')->nullable()->after('reintegre_par_id');
        });
    }

    public function down(): void
    {
        Schema::table('shift_transfer_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reintegre_par_id');
            $table->dropColumn(['reintegre_le', 'reintegration_commentaire']);
        });
    }
};
