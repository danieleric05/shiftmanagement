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
        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_souhaite_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->foreignId('planifie_par')->constrained('users')->cascadeOnDelete();
            $table->date('date_entretien');
            $table->time('heure_entretien')->nullable();
            $table->boolean('engagement_vu')->default(false);
            $table->enum('statut', ['planifie', 'realise', 'annule'])->default('planifie');
            $table->text('resultat')->nullable();
            $table->foreignId('shift_affecte_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->foreignId('decideur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['organisation_id', 'statut']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};
