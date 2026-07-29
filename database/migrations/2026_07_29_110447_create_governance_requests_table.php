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
        Schema::create('governance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('servant_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['avis', 'retrait', 'autre']);
            $table->text('motif');
            $table->foreignId('demandeur_id')->constrained('users')->cascadeOnDelete();
            $table->enum('statut', ['en_attente', 'validee', 'rejetee'])->default('en_attente');
            $table->foreignId('decideur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_commentaire')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('governance_requests');
    }
};
