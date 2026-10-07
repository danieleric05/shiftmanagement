<?php

namespace App\Http\Controllers;

use App\Support\ManuelFigures;
use Barryvdh\DomPDF\Facade\Pdf;

class ManualController extends Controller
{
    /**
     * Générer et télécharger le mode d'emploi de l'application au format PDF.
     */
    public function download()
    {
        $pdf = Pdf::loadView('manuel.index', [
            'genereLe' => now()->format('d/m/Y H:i'),
            'figures' => ManuelFigures::charger(),
        ]);

        return $pdf->download('mode-emploi-shift-management.pdf');
    }
}
