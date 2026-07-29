<?php

namespace App\Http\Controllers;

use App\Models\Servant;
use App\Models\Shift;
use App\Models\ServantWorkflowStep;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Display the reports dashboard.
     */
    public function index(Request $request)
    {
        $organisationId = $request->user()->organisation_id;

        return Inertia::render('Reports/Index', [
            'servantsParStatut' => [
                'recommande' => Servant::where('organisation_id', $organisationId)->where('statut', 'recommande')->count(),
                'en_formation' => Servant::where('organisation_id', $organisationId)->where('statut', 'en_formation')->count(),
                'actif' => Servant::where('organisation_id', $organisationId)->where('statut', 'actif')->count(),
                'suspendu' => Servant::where('organisation_id', $organisationId)->where('statut', 'suspendu')->count(),
                'retire' => Servant::where('organisation_id', $organisationId)->where('statut', 'retire')->count(),
            ],
            'remplissageShifts' => $this->remplissageShifts($organisationId),
            'avancementFormation' => $this->avancementFormation($organisationId),
        ]);
    }

    /**
     * Export CSV de la liste des servants (ouvrable dans Excel).
     */
    public function exportServantsCsv(Request $request): StreamedResponse
    {
        $organisationId = $request->user()->organisation_id;

        $servants = Servant::where('organisation_id', $organisationId)
            ->with('pieu')
            ->orderBy('nom')
            ->get();

        return response()->streamDownload(function () use ($servants) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Nom', 'Prénom', 'Statut', 'Téléphone', 'Pieu']);

            foreach ($servants as $servant) {
                fputcsv($handle, array_map($this->sanitizeCsvField(...), [
                    $servant->nom,
                    $servant->prenom,
                    $servant->statut,
                    $servant->telephone,
                    $servant->pieu?->nom,
                ]));
            }

            fclose($handle);
        }, 'servants.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Préfixe les valeurs pouvant être interprétées comme une formule par un tableur
     * (protection contre l'injection CSV / formula injection, CWE-1236).
     */
    private function sanitizeCsvField(?string $value): ?string
    {
        if ($value !== null && preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * Export PDF du taux de remplissage des Shifts.
     */
    public function exportShiftsFillingPdf(Request $request)
    {
        $organisationId = $request->user()->organisation_id;

        $pdf = Pdf::loadView('reports.shifts-filling', [
            'shifts' => $this->remplissageShifts($organisationId),
            'genereLe' => now()->format('d/m/Y H:i'),
        ]);

        return $pdf->download('remplissage-shifts.pdf');
    }

    private function remplissageShifts(int $organisationId): array
    {
        return Shift::where('organisation_id', $organisationId)
            ->with('positions.assignments')
            ->orderByJourCalendrier()
            ->orderBy('heure_debut')
            ->get()
            ->map(function (Shift $shift) {
                $total = $shift->positions->count();
                $vacants = $shift->positions->filter(
                    fn ($position) => $position->assignments->where('statut', 'actif')->isEmpty()
                )->count();

                return [
                    'nom' => $shift->nom,
                    'jour' => $shift->jour,
                    'postes_total' => $total,
                    'postes_vacants' => $vacants,
                    'taux_remplissage' => $total > 0 ? round((($total - $vacants) / $total) * 100) : null,
                ];
            })
            ->values()
            ->toArray();
    }

    private function avancementFormation(int $organisationId): array
    {
        $servantIds = Servant::where('organisation_id', $organisationId)->pluck('id');

        $total = ServantWorkflowStep::whereIn('servant_id', $servantIds)->count();
        $termines = ServantWorkflowStep::whereIn('servant_id', $servantIds)->where('statut', 'termine')->count();

        return [
            'total_etapes' => $total,
            'etapes_terminees' => $termines,
            'taux_avancement' => $total > 0 ? round(($termines / $total) * 100) : null,
        ];
    }
}
