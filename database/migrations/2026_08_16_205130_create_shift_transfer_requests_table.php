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
        Schema::create('shift_transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['releve', 'permutation']);
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->foreignId('shift_destination_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->foreignId('servant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('demandeur_id')->constrained('users')->cascadeOnDelete();
            $table->text('motif');
            $table->date('date_demande');
            $table->text('discussion_servant')->nullable();
            $table->boolean('approuve_deux_shifts')->nullable();
            $table->enum('statut', ['en_attente', 'traitee'])->default('en_attente');
            $table->text('resultat')->nullable();
            $table->date('resultat_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('decideur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organisation_id', 'statut']);
            $table->index(['type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_transfer_requests');
    }
};
