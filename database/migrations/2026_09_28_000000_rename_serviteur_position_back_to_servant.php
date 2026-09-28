<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Retour en arrière sur le renommage du poste "Serviteur" : le client
     * souhaite finalement conserver "Servant" (masculin). "Servante"
     * (féminin) reste inchangé.
     */
    public function up(): void
    {
        DB::table('shift_template_positions')->where('nom', 'Serviteur')->update(['nom' => 'Servant']);
        DB::table('shift_positions')->where('nom', 'Serviteur')->update(['nom' => 'Servant']);
    }

    public function down(): void
    {
        DB::table('shift_template_positions')->where('nom', 'Servant')->update(['nom' => 'Serviteur']);
        DB::table('shift_positions')->where('nom', 'Servant')->update(['nom' => 'Serviteur']);
    }
};
