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
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->string('telephone', 30)->nullable();
            $table->foreignId('shift_souhaite_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->date('date_appel')->nullable();
            $table->enum('statut', [
                'nouveau', 'appele', 'entretien_planifie', 'entretien_realise', 'converti', 'abandonne',
            ])->default('nouveau');
            $table->foreignId('servant_id')->nullable()->constrained('servants')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
