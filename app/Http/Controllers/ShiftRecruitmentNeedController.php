<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\Shift;
use App\Models\ShiftRecruitmentNeed;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShiftRecruitmentNeedController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $shiftsQuery = Shift::where('organisation_id', $user->organisation_id)->orderByJourCalendrier();

        if (! $user->estAdministrateur()) {
            $shiftsQuery->whereIn('id', $user->shiftsGeres());
        }

        $besoins = ShiftRecruitmentNeed::where('organisation_id', $user->organisation_id)
            ->get()
            ->keyBy('shift_id');

        $candidatsActifsParShift = Candidate::where('organisation_id', $user->organisation_id)
            ->whereIn('statut', ['nouveau', 'appele', 'entretien_planifie'])
            ->get()
            ->groupBy('shift_souhaite_id')
            ->map->count();

        $shifts = $shiftsQuery->get()->map(function (Shift $shift) use ($besoins, $candidatsActifsParShift) {
            $besoin = $besoins->get($shift->id);

            return [
                'shift_id' => $shift->id,
                'shift_nom' => $shift->nom,
                'nombre_a_recruter' => $besoin?->nombre_a_recruter ?? 0,
                'echeance' => $besoin?->echeance?->format('Y-m-d'),
                'notes' => $besoin?->notes,
                'candidats_actifs' => $candidatsActifsParShift->get($shift->id, 0),
            ];
        });

        return Inertia::render('Recruitment/Index', [
            'shifts' => $shifts,
            'estAdministrateur' => $user->estAdministrateur(),
            'compteurs' => [
                'total_a_recruter' => (int) $shifts->sum('nombre_a_recruter'),
                'total_candidats_actifs' => (int) $shifts->sum('candidats_actifs'),
            ],
        ]);
    }

    /**
     * Créer ou mettre à jour le besoin de recrutement courant d'un shift.
     */
    public function upsert(Request $request, Shift $shift)
    {
        abort_if($shift->organisation_id !== $request->user()->organisation_id, 403);
        $this->authorize('update', [ShiftRecruitmentNeed::class, $shift]);

        $validated = $request->validate([
            'nombre_a_recruter' => ['required', 'integer', 'min:0'],
            'echeance' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        ShiftRecruitmentNeed::updateOrCreate(
            ['shift_id' => $shift->id],
            [
                ...$validated,
                'organisation_id' => $shift->organisation_id,
                'updated_by' => $request->user()->id,
            ]
        );

        return back()->with('success', 'Besoin de recrutement mis à jour avec succès.');
    }
}
