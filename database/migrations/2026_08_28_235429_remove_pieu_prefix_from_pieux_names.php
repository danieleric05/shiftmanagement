<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Le type ("Pieu") est désormais affiché à côté du nom (badge) partout
     * dans l'app : le préfixe "Pieu de "/"Pieu d'" dans le nom lui-même
     * devient redondant (ex. "Pieu de Cocody" → "Cocody").
     */
    public function up(): void
    {
        foreach (DB::table('pieux')->where('type', 'pieu')->get(['id', 'nom']) as $pieu) {
            $nouveauNom = $this->sansPrefixe($pieu->nom);

            if ($nouveauNom !== $pieu->nom) {
                DB::table('pieux')->where('id', $pieu->id)->update(['nom' => $nouveauNom]);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * Non réversible avec exactitude (impossible de reconstituer le choix
     * "de"/"d'" d'origine) : ré-ajoute "Pieu de " par défaut.
     */
    public function down(): void
    {
        DB::table('pieux')->where('type', 'pieu')->update([
            'nom' => DB::raw("CONCAT('Pieu de ', nom)"),
        ]);
    }

    private function sansPrefixe(string $nom): string
    {
        foreach (['Pieu de ', "Pieu d'", 'Pieu '] as $prefixe) {
            if (str_starts_with($nom, $prefixe)) {
                return substr($nom, strlen($prefixe));
            }
        }

        return $nom;
    }
};
