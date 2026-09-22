<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Le rôle "secretaire" n'existait que pour la gestion des candidats et
     * des entretiens (cf. drop_interviews_and_candidates_tables) : sans ces
     * modules il n'a plus aucune fonction. Les comptes concernés retombent à
     * role_id = null (foreignId nullOnDelete sur users.role_id), comme pour
     * les rôles "membre"/"servant" retirés précédemment.
     */
    public function up(): void
    {
        DB::table('roles')->where('slug', 'secretaire')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')->insertOrIgnore([
            'slug' => 'secretaire',
            'nom' => 'Secrétaire',
            'description' => 'Prise de rendez-vous : gestion des candidats et des entretiens.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
