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
        Schema::create('servant_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_step_id')->constrained()->cascadeOnDelete();
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('statut', ['en_attente', 'en_cours', 'termine', 'ignore'])->default('en_attente');
            $table->date('date')->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamps();

            $table->unique(['servant_id', 'workflow_step_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servant_workflow_steps');
    }
};
