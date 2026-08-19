<?php

namespace App\Http\Controllers;

use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftTransferRequest;
use App\Models\User;
use App\Notifications\DemandeTransfertResolue;
use App\Notifications\NouvelleDemandeTransfert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class ShiftTransferRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = ShiftTransferRequest::where('organisation_id', $user->organisation_id)
            ->with(['shift', 'shiftDestination', 'servant', 'demandeur', 'decideur']);

        if (! $user->estAdministrateur()) {
            $query->whereIn('shift_id', $user->shiftsGeres());
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        $demandes = $query->orderByDesc('date_demande')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (ShiftTransferRequest $d) => [
                'id' => $d->id,
                'type' => $d->type,
                'shift' => $d->shift->nom,
                'shift_destination' => $d->shiftDestination?->nom,
                'servant' => $d->servant->nomComplet(),
                'coordonnees' => $d->servant->telephone,
                'motif' => $d->motif,
                'date_demande' => $d->date_demande->format('Y-m-d'),
                'discussion_servant' => $d->discussion_servant,
                'approuve_deux_shifts' => $d->approuve_deux_shifts,
                'statut' => $d->statut,
                'resultat' => $d->resultat,
                'resultat_date' => $d->resultat_date?->format('Y-m-d'),
                'notes' => $d->notes,
                'demandeur' => $d->demandeur->name,
                'decideur' => $d->decideur?->name,
            ]);

        $shiftsDisponibles = $user->estAdministrateur()
            ? Shift::where('organisation_id', $user->organisation_id)->orderByJourCalendrier()->get(['id', 'nom'])
            : Shift::where('organisation_id', $user->organisation_id)->whereIn('id', $user->shiftsGeres())->orderByJourCalendrier()->get(['id', 'nom']);

        $compteursQuery = fn (string $type) => ShiftTransferRequest::where('organisation_id', $user->organisation_id)
            ->when(! $user->estAdministrateur(), fn ($q) => $q->whereIn('shift_id', $user->shiftsGeres()))
            ->where('type', $type)
            ->enAttente()
            ->count();

        return Inertia::render('ShiftTransfers/Index', [
            'demandes' => $demandes,
            'shifts' => $shiftsDisponibles,
            'servants' => Servant::where('organisation_id', $user->organisation_id)->orderBy('nom')->get(['id', 'nom', 'prenom']),
            'filtreType' => $request->string('type')->toString(),
            'estAdministrateur' => $user->estAdministrateur(),
            'compteurs' => [
                'releves' => $compteursQuery('releve'),
                'permutations' => $compteursQuery('permutation'),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $shift = Shift::findOrFail($request->input('shift_id'));
        $this->authorize('create', [ShiftTransferRequest::class, $shift]);

        $validated = $request->validate([
            'shift_id' => ['required', 'exists:shifts,id'],
            'type' => ['required', 'in:releve,permutation'],
            'servant_id' => ['required', 'exists:servants,id'],
            'shift_destination_id' => ['required_if:type,permutation', 'nullable', 'exists:shifts,id', 'different:shift_id'],
            'motif' => ['required', 'string'],
            'date_demande' => ['required', 'date'],
            'discussion_servant' => ['nullable', 'string'],
            'approuve_deux_shifts' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $servant = Servant::findOrFail($validated['servant_id']);
        abort_if($servant->organisation_id !== $request->user()->organisation_id, 403);

        $demande = ShiftTransferRequest::create([
            ...$validated,
            'organisation_id' => $request->user()->organisation_id,
            'demandeur_id' => $request->user()->id,
            'statut' => 'en_attente',
        ]);

        $admins = User::where('organisation_id', $demande->organisation_id)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['administrateur', 'super_admin']))
            ->get();
        Notification::send($admins, new NouvelleDemandeTransfert($demande));

        return back()->with('success', 'Demande créée avec succès.');
    }

    /**
     * Update the specified resource in storage (coordinateur, tant que non traitée).
     */
    public function update(Request $request, ShiftTransferRequest $shiftTransferRequest)
    {
        $this->authorize('update', $shiftTransferRequest);

        $validated = $request->validate([
            'discussion_servant' => ['nullable', 'string'],
            'approuve_deux_shifts' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $shiftTransferRequest->update($validated);

        return back()->with('success', 'Demande mise à jour avec succès.');
    }

    /**
     * Saisir le résultat de la demande (administrateur uniquement).
     */
    public function resolve(Request $request, ShiftTransferRequest $shiftTransferRequest)
    {
        $this->authorize('resolve', $shiftTransferRequest);

        $validated = $request->validate([
            'resultat' => ['required', 'string'],
            'resultat_date' => ['required', 'date'],
        ]);

        $shiftTransferRequest->update([
            ...$validated,
            'statut' => 'traitee',
            'decideur_id' => $request->user()->id,
        ]);

        $shiftTransferRequest->demandeur->notify(new DemandeTransfertResolue($shiftTransferRequest));

        return back()->with('success', 'Résultat enregistré avec succès.');
    }

    /**
     * Remove the specified resource from storage (administrateur uniquement).
     */
    public function destroy(Request $request, ShiftTransferRequest $shiftTransferRequest)
    {
        $this->authorize('delete', $shiftTransferRequest);

        $shiftTransferRequest->delete();

        return back()->with('success', 'Demande supprimée avec succès.');
    }
}
