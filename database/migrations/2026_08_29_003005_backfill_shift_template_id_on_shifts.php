<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Les shifts créés par l'import du fichier maître (temple:import-roster)
     * n'ont jamais été rattachés à un modèle ("Temple Standard") : le
     * catalogue de postes propres au genre du shift (cf. ShiftController::
     * postesDisponiblesPourShift) n'avait donc jamais rien à proposer.
     */
    public function up(): void
    {
        DB::table('shifts')
            ->whereNull('shift_template_id')
            ->orderBy('organisation_id')
            ->get(['id', 'organisation_id'])
            ->groupBy('organisation_id')
            ->each(function ($shifts, $organisationId) {
                $templateId = DB::table('shift_templates')
                    ->where('organisation_id', $organisationId)
                    ->whereNull('deleted_at')
                    ->orderBy('id')
                    ->value('id');

                if ($templateId === null) {
                    return;
                }

                DB::table('shifts')
                    ->whereIn('id', $shifts->pluck('id'))
                    ->update(['shift_template_id' => $templateId]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non réversible avec exactitude (on ne sait plus lesquels étaient
        // déjà rattachés avant cette migration) : ne fait rien.
    }
};
