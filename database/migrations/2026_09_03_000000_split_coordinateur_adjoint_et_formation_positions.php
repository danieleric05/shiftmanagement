<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Le catalogue "Temple Standard" fusionnait "Coordonnateur Adjoint" et
     * "Coordonnateur de la formation" en un seul poste ("...de la formation").
     * La présidence du temple les veut comme deux postes distincts : on
     * renomme les postes existants (aucun Shift réel ne les utilise encore,
     * vérifié en base — rien à propager) et on ajoute les deux nouveaux
     * postes "de la formation" en fin de liste (l'admin peut les réordonner
     * depuis la fiche du modèle, glisser-déposer déjà en place).
     */
    public function up(): void
    {
        DB::table('shift_template_positions')
            ->where('nom', 'Coordonnateur Adjoint de la formation')
            ->update(['nom' => 'Coordonnateur Adjoint']);

        DB::table('shift_template_positions')
            ->where('nom', 'Coordonnatrice Adjointe de la formation')
            ->update(['nom' => 'Coordonnatrice Adjointe']);

        // Uniquement le catalogue standard "Temple Standard" (celui décrit par la
        // présidence) — pas les autres modèles (ex. gabarits de test) qui ont
        // leur propre liste de postes, volontairement différente.
        $templates = DB::table('shift_templates')->where('nom', 'Temple Standard')->pluck('id');

        foreach ($templates as $templateId) {
            $dejaPresent = DB::table('shift_template_positions')
                ->where('shift_template_id', $templateId)
                ->where('nom', 'Coordonnateur de la formation')
                ->exists();

            if ($dejaPresent) {
                continue;
            }

            $ordreMax = (int) DB::table('shift_template_positions')
                ->where('shift_template_id', $templateId)
                ->max('ordre');

            DB::table('shift_template_positions')->insert([
                [
                    'shift_template_id' => $templateId,
                    'nom' => 'Coordonnateur de la formation',
                    'ordre' => $ordreMax + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'shift_template_id' => $templateId,
                    'nom' => 'Coordonnatrice de la formation',
                    'ordre' => $ordreMax + 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }

    public function down(): void
    {
        DB::table('shift_template_positions')
            ->whereIn('nom', ['Coordonnateur de la formation', 'Coordonnatrice de la formation'])
            ->delete();

        DB::table('shift_template_positions')
            ->where('nom', 'Coordonnateur Adjoint')
            ->update(['nom' => 'Coordonnateur Adjoint de la formation']);

        DB::table('shift_template_positions')
            ->where('nom', 'Coordonnatrice Adjointe')
            ->update(['nom' => 'Coordonnatrice Adjointe de la formation']);
    }
};
