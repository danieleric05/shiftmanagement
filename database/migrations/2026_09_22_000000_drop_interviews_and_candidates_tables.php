<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Modules Candidats et Entretiens retirés : le cahier des charges
     * (feuilles "NOUVEAUX SERVANTS" / "ENTRETIENS") ne prévoit pas de fiche
     * candidat séparée, et la création d'un Servant se fait directement
     * depuis Servants/Create (cf. ServantController::store).
     */
    public function up(): void
    {
        Schema::dropIfExists('interviews');
        Schema::dropIfExists('candidates');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
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
            $table->softDeletes();
        });

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
};
