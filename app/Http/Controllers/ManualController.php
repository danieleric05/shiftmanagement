<?php

namespace App\Http\Controllers;

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
        ]);

        return $pdf->download('mode-emploi-temple-servant-manager.pdf');
    }
}
