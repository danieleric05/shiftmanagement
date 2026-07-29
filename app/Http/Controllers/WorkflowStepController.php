<?php

namespace App\Http\Controllers;

use App\Models\WorkflowStep;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkflowStepController extends Controller
{
    public function index()
    {
        return Inertia::render('Settings/WorkflowSteps/Index', [
            'etapes' => WorkflowStep::orderBy('ordre')->get(['id', 'cle', 'nom', 'ordre']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cle' => ['required', 'string', 'max:255', 'unique:workflow_steps,cle'],
            'nom' => ['required', 'string', 'max:255'],
        ]);

        $ordre = WorkflowStep::max('ordre') + 1;

        WorkflowStep::create([
            ...$validated,
            'ordre' => $ordre,
        ]);

        return back()->with('success', 'Étape ajoutée avec succès.');
    }

    public function update(Request $request, WorkflowStep $workflowStep)
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'ordre' => ['required', 'integer', 'min:1'],
        ]);

        $workflowStep->update($validated);

        return back()->with('success', 'Étape mise à jour avec succès.');
    }

    public function destroy(WorkflowStep $workflowStep)
    {
        $workflowStep->delete();

        return back()->with('success', 'Étape supprimée avec succès.');
    }
}
