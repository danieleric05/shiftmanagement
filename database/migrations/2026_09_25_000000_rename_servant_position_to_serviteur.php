<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Le poste "Servant" (masculin) est renommé "Serviteur", conformément au
     * vocabulaire déjà utilisé partout ailleurs dans l'application. "Servante"
     * (féminin) est inchangé, c'est déjà le bon accord.
     */
    public function up(): void
    {
        DB::table('shift_template_positions')->where('nom', 'Servant')->update(['nom' => 'Serviteur']);
        DB::table('shift_positions')->where('nom', 'Servant')->update(['nom' => 'Serviteur']);
    }

    public function down(): void
    {
        DB::table('shift_template_positions')->where('nom', 'Serviteur')->update(['nom' => 'Servant']);
        DB::table('shift_positions')->where('nom', 'Serviteur')->update(['nom' => 'Servant']);
    }
};
