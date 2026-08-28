<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftPosition;
use App\Models\ShiftTransferRequest;
use App\Models\User;
use App\Notifications\DemandeTransfertResolue;
use App\Notifications\NouvelleDemandeTransfert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
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
            $shiftsGeres = $user->shiftsGeres();
            $query->where(fn ($q) => $q->whereIn('shift_id', $shiftsGeres)
                ->orWhereIn('shift_destination_id', $shiftsGeres));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->filled('recherche')) {
            $recherche = $request->string('recherche')->toString();
            $query->whereHas('servant', fn ($q) => $q->where('nom', 'like', "%{$recherche}%")
                ->orWhere('prenom', 'like', "%{$recherche}%"));
        }

        $demandes = $query->orderByDesc('date_demande')
            ->paginate(30)
            ->withQueryString()
            ->through(function (ShiftTransferRequest $d) use ($user) {
                $postesDestinationVacants = [];

                if ($d->type === 'permutation' && $d->validationsChefsCompletes() && $d->statut === 'en_attente' && $user->estAdministrateur()) {
                    $postesDestinationVacants = ShiftPosition::where('shift_id', $d->shift_destination_id)
                        ->whereDoesntHave('assignments', fn ($q) => $q->where('statut', 'actif'))
                        ->orderBy('ordre')
                        ->get(['id', 'nom'])
                        ->toArray();
                }

                return [
                    'id' => $d->id,
                    'type' => $d->type,
                    'shift_id' => $d->shift_id,
                    'shift' => $d->shift->nom,
                    'shift_destination_id' => $d->shift_destination_id,
                    'shift_destination' => $d->shiftDestination?->nom,
                    'servant' => $d->servant->nomComplet(),
                    'coordonnees' => $d->servant->telephone,
                    'motif' => $d->motif,
                    'date_demande' => $d->date_demande->format('Y-m-d'),
                    'discussion_servant' => $d->discussion_servant,
                    'approuve_deux_shifts' => $d->approuve_deux_shifts,
                    'validation_chef_origine' => $d->validation_chef_origine,
                    'validation_chef_origine_par' => $d->validateurOrigine?->name,
                    'validation_chef_destination' => $d->validation_chef_destination,
                    'validation_chef_destination_par' => $d->validateurDestination?->name,
                    'entretien_date' => $d->entretien_date?->format('Y-m-d'),
                    'entretien_heure' => $d->entretien_heure,
                    'statut' => $d->statut,
                    'resultat' => $d->resultat,
                    'resultat_date' => $d->resultat_date?->format('Y-m-d'),
                    'favorable' => $d->favorable,
                    'notes' => $d->notes,
                    'demandeur' => $d->demandeur->name,
                    'decideur' => $d->decideur?->name,
                    'postes_destination_vacants' => $postesDestinationVacants,
                    'peut_valider_origine' => $user->can('validerOrigine', $d),
                    'peut_valider_destination' => $user->can('validerDestination', $d),
                ];
            });

        $shiftsDisponibles = $user->estAdministrateur()
            ? Shift::where('organisation_id', $user->organisation_id)->orderByJourCalendrier()->get(['id', 'nom'])
            : Shift::where('organisation_id', $user->organisation_id)->whereIn('id', $user->shiftsGeres())->orderByJourCalendrier()->get(['id', 'nom']);

        $compteursQuery = fn (string $type) => ShiftTransferRequest::where('organisation_id', $user->organisation_id)
            ->when(! $user->estAdministrateur(), fn ($q) => $q->where(fn ($sub) => $sub->whereIn('shift_id', $user->shiftsGeres())
                ->orWhereIn('shift_destination_id', $user->shiftsGeres())))
            ->where('type', $type)
            ->enAttente()
            ->count();

        return Inertia::render('ShiftTransfers/Index', [
            'demandes' => $demandes,
            'shifts' => $shiftsDisponibles,
            'servants' => Servant::where('organisation_id', $user->organisation_id)->orderBy('nom')->get(['id', 'nom', 'prenom']),
            'filtreType' => $request->string('type')->toString(),
            'filtreRecherche' => $request->string('recherche')->toString(),
            'estAdministrateur' => $user->estAdministrateur(),
            'compteurs' => [
                'releves' => $compteursQuery('releve'),
                'permutations' => $compteursQuery('permutation'),
                'appels' => $compteursQuery('appel'),
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
            'type' => ['required', 'in:releve,permutation,appel'],
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
            'entretien_date' => ['nullable', 'date'],
            'entretien_heure' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string'],
        ]);

        $shiftTransferRequest->update($validated);

        return back()->with('success', 'Demande mise à jour avec succès.');
    }

    /**
     * Validation par le coordonnateur du shift d'origine (étape 1/2 avant l'entretien manager, permutation uniquement).
     */
    public function validerOrigine(Request $request, ShiftTransferRequest $shiftTransferRequest)
    {
        $this->authorize('validerOrigine', $shiftTransferRequest);

        $validated = $request->validate(['accepte' => ['required', 'boolean']]);

        $shiftTransferRequest->update([
            'validation_chef_origine' => $validated['accepte'],
            'validation_chef_origine_par_id' => $request->user()->id,
            'validation_chef_origine_le' => now(),
        ]);

        $this->cloturerSiRefusee($request, $shiftTransferRequest, $validated['accepte'], "du shift d'origine");

        return back()->with('success', 'Validation enregistrée avec succès.');
    }

    /**
     * Validation par le coordonnateur du shift de destination (étape 2/2 avant l'entretien manager, permutation uniquement).
     */
    public function validerDestination(Request $request, ShiftTransferRequest $shiftTransferRequest)
    {
        $this->authorize('validerDestination', $shiftTransferRequest);

        $validated = $request->validate(['accepte' => ['required', 'boolean']]);

        $shiftTransferRequest->update([
            'validation_chef_destination' => $validated['accepte'],
            'validation_chef_destination_par_id' => $request->user()->id,
            'validation_chef_destination_le' => now(),
        ]);

        $this->cloturerSiRefusee($request, $shiftTransferRequest, $validated['accepte'], 'du shift de destination');

        return back()->with('success', 'Validation enregistrée avec succès.');
    }

    /**
     * Un refus de l'un des deux chefs clôt directement la demande, sans attendre
     * l'entretien manager ni l'autre validation.
     */
    private function cloturerSiRefusee(Request $request, ShiftTransferRequest $shiftTransferRequest, bool $accepte, string $origineLabel): void
    {
        if ($accepte) {
            return;
        }

        $shiftTransferRequest->update([
            'statut' => 'traitee',
            'resultat' => "Refusée par le coordonnateur d'équipe {$origineLabel}.",
            'resultat_date' => now()->toDateString(),
            'favorable' => false,
            'decideur_id' => $request->user()->id,
        ]);

        $shiftTransferRequest->demandeur->notify(new DemandeTransfertResolue($shiftTransferRequest));
    }

    /**
     * Saisir le résultat de la demande (administrateur uniquement).
     */
    public function resolve(Request $request, ShiftTransferRequest $shiftTransferRequest)
    {
        $this->authorize('resolve', $shiftTransferRequest);

        if ($shiftTransferRequest->type === 'permutation') {
            abort_unless(
                $shiftTransferRequest->validationsChefsCompletes(),
                422,
                "Les deux coordonnateurs d'équipe (origine et destination) doivent valider la permutation avant la décision finale."
            );
        }

        $validated = $request->validate([
            'resultat' => ['required', 'string'],
            'resultat_date' => ['required', 'date'],
            'favorable' => [Rule::requiredIf($shiftTransferRequest->type === 'permutation'), 'nullable', 'boolean'],
            'shift_position_destination_id' => [
                'nullable',
                Rule::requiredIf(fn () => $shiftTransferRequest->type === 'permutation' && $request->boolean('favorable')),
                Rule::exists('shift_positions', 'id')->where('shift_id', $shiftTransferRequest->shift_destination_id),
            ],
        ]);

        DB::transaction(function () use ($shiftTransferRequest, $validated, $request) {
            $shiftTransferRequest->update([
                'resultat' => $validated['resultat'],
                'resultat_date' => $validated['resultat_date'],
                'favorable' => $validated['favorable'] ?? null,
                'statut' => 'traitee',
                'decideur_id' => $request->user()->id,
            ]);

            if ($shiftTransferRequest->type === 'permutation' && ($validated['favorable'] ?? false)) {
                $this->integrerServantAuShiftDestination($shiftTransferRequest, $validated['shift_position_destination_id']);
            }
        });

        $shiftTransferRequest->demandeur->notify(new DemandeTransfertResolue($shiftTransferRequest));

        return back()->with('success', 'Résultat enregistré avec succès.');
    }

    /**
     * Termine les affectations actives du servant sur le shift d'origine et le
     * place sur le poste choisi du shift de destination — même mécanique que
     * ShiftController::assignServant()/endAssignment(), appliquée ici suite à
     * une permutation favorable.
     */
    private function integrerServantAuShiftDestination(ShiftTransferRequest $shiftTransferRequest, int $shiftPositionDestinationId): void
    {
        Assignment::whereIn('shift_position_id', ShiftPosition::where('shift_id', $shiftTransferRequest->shift_id)->pluck('id'))
            ->where('servant_id', $shiftTransferRequest->servant_id)
            ->where('statut', 'actif')
            ->update(['statut' => 'termine', 'date_fin' => now()->toDateString()]);

        Assignment::where('shift_position_id', $shiftPositionDestinationId)
            ->where('statut', 'actif')
            ->update(['statut' => 'termine', 'date_fin' => now()->toDateString()]);

        Assignment::create([
            'shift_position_id' => $shiftPositionDestinationId,
            'servant_id' => $shiftTransferRequest->servant_id,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);
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
