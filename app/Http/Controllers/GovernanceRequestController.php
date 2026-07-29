<?php

namespace App\Http\Controllers;

use App\Models\GovernanceRequest;
use App\Models\Servant;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GovernanceRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $organisationId = $request->user()->organisation_id;

        $demandes = GovernanceRequest::where('organisation_id', $organisationId)
            ->with(['servant', 'demandeur', 'decideur'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (GovernanceRequest $demande) => [
                'id' => $demande->id,
                'type' => $demande->type,
                'motif' => $demande->motif,
                'statut' => $demande->statut,
                'servant' => $demande->servant->nomComplet(),
                'servant_id' => $demande->servant_id,
                'demandeur' => $demande->demandeur->name,
                'decideur' => $demande->decideur?->name,
                'decision_commentaire' => $demande->decision_commentaire,
                'decided_at' => $demande->decided_at?->format('Y-m-d'),
                'created_at' => $demande->created_at->format('Y-m-d'),
            ]);

        $servants = Servant::where('organisation_id', $organisationId)
            ->orderBy('nom')
            ->get()
            ->map(fn (Servant $servant) => [
                'id' => $servant->id,
                'nom_complet' => $servant->nomComplet(),
            ]);

        return Inertia::render('Governance/Index', [
            'demandes' => $demandes,
            'servants' => $servants,
            'compteurs' => [
                'avis' => GovernanceRequest::where('organisation_id', $organisationId)->where('type', 'avis')->where('statut', 'en_attente')->count(),
                'retraits' => GovernanceRequest::where('organisation_id', $organisationId)->where('type', 'retrait')->where('statut', 'en_attente')->count(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'servant_id' => ['required', 'exists:servants,id'],
            'type' => ['required', 'in:avis,retrait,autre'],
            'motif' => ['required', 'string'],
        ]);

        $servant = Servant::findOrFail($validated['servant_id']);
        abort_if($servant->organisation_id !== $request->user()->organisation_id, 403);

        GovernanceRequest::create([
            ...$validated,
            'organisation_id' => $request->user()->organisation_id,
            'demandeur_id' => $request->user()->id,
            'statut' => 'en_attente',
        ]);

        return back()->with('success', 'Demande créée avec succès.');
    }

    /**
     * Valider une demande (avis favorable / retrait accepté).
     */
    public function validateRequest(Request $request, GovernanceRequest $governanceRequest)
    {
        $this->ensureSameOrganisation($request, $governanceRequest);

        $validated = $request->validate([
            'decision_commentaire' => ['nullable', 'string'],
        ]);

        $governanceRequest->update([
            ...$validated,
            'statut' => 'validee',
            'decideur_id' => $request->user()->id,
            'decided_at' => now(),
        ]);

        if ($governanceRequest->type === 'retrait') {
            $governanceRequest->servant->update(['statut' => 'retire']);
        }

        return back()->with('success', 'Demande validée avec succès.');
    }

    /**
     * Rejeter une demande.
     */
    public function rejectRequest(Request $request, GovernanceRequest $governanceRequest)
    {
        $this->ensureSameOrganisation($request, $governanceRequest);

        $validated = $request->validate([
            'decision_commentaire' => ['nullable', 'string'],
        ]);

        $governanceRequest->update([
            ...$validated,
            'statut' => 'rejetee',
            'decideur_id' => $request->user()->id,
            'decided_at' => now(),
        ]);

        return back()->with('success', 'Demande rejetée.');
    }

    private function ensureSameOrganisation(Request $request, GovernanceRequest $governanceRequest): void
    {
        abort_if($governanceRequest->organisation_id !== $request->user()->organisation_id, 403);
    }
}
