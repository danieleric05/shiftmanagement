<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('horaires')->where('nom', 'Matin')
            ->where('heure_debut', '07:00:00')->where('heure_fin', '11:00:00')
            ->update(['heure_debut' => '06:30:00', 'heure_fin' => '12:30:00']);

        DB::table('horaires')->where('nom', 'Soir')
            ->where('heure_debut', '11:00:00')->where('heure_fin', '19:00:00')
            ->update(['heure_debut' => '12:30:00', 'heure_fin' => '17:30:00']);

        // Les heures des shifts déjà créés sont copiées depuis l'Horaire au
        // moment de leur création (pas de clé étrangère live) : on les met à
        // jour ici pour les shifts qui n'ont pas été personnalisés depuis.
        DB::table('shifts')
            ->where('heure_debut', '07:00:00')->where('heure_fin', '11:00:00')
            ->update(['heure_debut' => '06:30:00', 'heure_fin' => '12:30:00']);

        DB::table('shifts')
            ->where('heure_debut', '11:00:00')->where('heure_fin', '19:00:00')
            ->update(['heure_debut' => '12:30:00', 'heure_fin' => '17:30:00']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('horaires')->where('nom', 'Matin')
            ->where('heure_debut', '06:30:00')->where('heure_fin', '12:30:00')
            ->update(['heure_debut' => '07:00:00', 'heure_fin' => '11:00:00']);

        DB::table('horaires')->where('nom', 'Soir')
            ->where('heure_debut', '12:30:00')->where('heure_fin', '17:30:00')
            ->update(['heure_debut' => '11:00:00', 'heure_fin' => '19:00:00']);

        DB::table('shifts')
            ->where('heure_debut', '06:30:00')->where('heure_fin', '12:30:00')
            ->update(['heure_debut' => '07:00:00', 'heure_fin' => '11:00:00']);

        DB::table('shifts')
            ->where('heure_debut', '12:30:00')->where('heure_fin', '17:30:00')
            ->update(['heure_debut' => '11:00:00', 'heure_fin' => '19:00:00']);
    }
};
