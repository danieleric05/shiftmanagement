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
use App\Services\AffectationServant;
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
            ->where('statut', 'en_attente')
            ->with(['shift', 'shiftDestination', 'servant', 'demandeur.role', 'decideur', 'validateurOrigine', 'validateurDestination']);

        if (! $user->consulteToutesLesDonnees()) {
            // Le coordonnateur d'équipe ne gère que les permutations de ses shifts.
            abort_if($request->filled('type') && $request->string('type')->toString() !== 'permutation', 403);

            $shiftsGeres = $user->shiftsGeres();
            $query->where('type', 'permutation')
                ->where(fn ($q) => $q->whereIn('shift_id', $shiftsGeres)
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

                if ($d->statut === 'en_attente' && $user->gereServantsEtPermutations()) {
                    $shiftPourPoste = match (true) {
                        $d->type === 'permutation' && $d->validationsChefsCompletes() => $d->shift_destination_id,
                        $d->type === 'appel' => $d->shift_id,
                        default => null,
                    };

                    if ($shiftPourPoste !== null) {
                        $postesDestinationVacants = ShiftPosition::where('shift_id', $shiftPourPoste)
                            ->whereDoesntHave('assignments', fn ($q) => $q->where('statut', 'actif'))
                            ->orderBy('ordre')
                            ->get(['id', 'nom'])
                            ->toArray();
                    }
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
                    'suivi' => $d->type === 'permutation' ? $d->suiviPermutation() : null,
                ];
            });

        if ($redirection = $this->redirigerSiPageHorsLimites($demandes, $request)) {
            return $redirection;
        }

        $shiftsDisponibles = match (true) {
            // Rôle « Autres » : pas de formulaire de création, donc aucune liste à proposer.
            $user->estEnLectureSeule() => collect(),
            $user->gereServantsEtPermutations() => Shift::where('organisation_id', $user->organisation_id)->orderByJourCalendrier()->get(['id', 'nom']),
            default => Shift::where('organisation_id', $user->organisation_id)->whereIn('id', $user->shiftsGeres())->orderByJourCalendrier()->get(['id', 'nom']),
        };

        $compteursQuery = fn (string $type) => ShiftTransferRequest::where('organisation_id', $user->organisation_id)
            ->when(! $user->consulteToutesLesDonnees(), fn ($q) => $q->where(fn ($sub) => $sub->whereIn('shift_id', $user->shiftsGeres())
                ->orWhereIn('shift_destination_id', $user->shiftsGeres())))
            ->where('type', $type)
            ->enAttente()
            ->count();

        return Inertia::render('ShiftTransfers/Index', [
            'demandes' => $demandes,
            'shifts' => $shiftsDisponibles,
            'servants' => $user->estEnLectureSeule()
                ? []
                : Servant::where('organisation_id', $user->organisation_id)->orderBy('nom')->get(['id', 'nom', 'prenom']),
            'filtreType' => $request->string('type')->toString(),
            'filtreRecherche' => $request->string('recherche')->toString(),
            'estAdministrateur' => $user->gereServantsEtPermutations(),
            'consulteTout' => $user->consulteToutesLesDonnees(),
            'compteurs' => $user->consulteToutesLesDonnees()
                ? [
                    'releves' => $compteursQuery('releve'),
                    'permutations' => $compteursQuery('permutation'),
                    'appels' => $compteursQuery('appel'),
                ]
                : ['permutations' => $compteursQuery('permutation')],
        ]);
    }

    /**
     * Historique des relèves traitées : une fois relevé, le servant sort de
     * la liste des demandes en attente (index()) et apparaît ici.
     */
    public function releves(Request $request, AffectationServant $affectation)
    {
        $user = $request->user();

        // Les relèves sont réservées à l'administrateur et au secrétaire
        // (consultables en lecture seule par le rôle « Autres »).
        abort_unless($user->consulteToutesLesDonnees(), 403);

        $query = ShiftTransferRequest::where('organisation_id', $user->organisation_id)
            ->where('type', 'releve')
            ->where('statut', 'traitee')
            ->whereHas('servant')
            ->with(['shift' => fn ($q) => $q->withTrashed(), 'servant', 'decideur', 'reintegrePar']);

        // Réintégration réservée au Conseil du Temple (administrateur).
        $peutReintegrer = $user->estAdministrateur();

        $releves = $query->orderByDesc('resultat_date')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (ShiftTransferRequest $d) => [
                'id' => $d->id,
                'servant_id' => $d->servant_id,
                'servant' => $d->servant->nomComplet(),
                'genre' => $d->servant->genre,
                'coordonnees' => $d->servant->telephone,
                'shift' => $d->shift?->nom,
                'motif' => $d->motif,
                'resultat' => $d->resultat,
                'resultat_date' => $d->resultat_date?->format('Y-m-d'),
                'decideur' => $d->decideur?->name,
                'reintegre_le' => $d->reintegre_le?->format('Y-m-d'),
                'reintegre_par' => $d->reintegrePar?->name,
                'reintegration_commentaire' => $d->reintegration_commentaire,
                'peut_reintegrer' => $peutReintegrer && $d->reintegre_le === null,
            ]);

        if ($redirection = $this->redirigerSiPageHorsLimites($releves, $request)) {
            return $redirection;
        }

        $avecReintegration = $peutReintegrer && collect($releves->items())->contains('peut_reintegrer', true);

        return Inertia::render('ShiftTransfers/Releves', [
            'releves' => $releves,
            'shiftsReintegration' => $avecReintegration
                ? $affectation->optionsPourOrganisation($user->organisation_id)
                : [],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $shift = Shift::findOrFail($request->input('shift_id'));
        $this->authorize('create', [ShiftTransferRequest::class, $shift, $request->input('type')]);

        $validated = $request->validate([
            'shift_id' => ['required', 'exists:shifts,id'],
            'type' => ['required', 'in:releve,permutation,appel'],
            'servant_id' => ['required', 'exists:servants,id'],
            'shift_destination_id' => ['required_if:type,permutation', 'nullable', Rule::exists('shifts', 'id')->where('organisation_id', $request->user()->organisation_id), 'different:shift_id'],
            'motif' => ['required', 'string'],
            'date_demande' => ['required', 'date'],
            'discussion_servant' => ['nullable', 'string'],
            'approuve_deux_shifts' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $servant = Servant::findOrFail($validated['servant_id']);
        abort_if($servant->organisation_id !== $request->user()->organisation_id, 403);

        if ($validated['type'] === 'permutation') {
            $shiftDestination = Shift::findOrFail($validated['shift_destination_id']);

            $shiftDestination->assurerGenreCompatible(
                $servant,
                'Un homme ne peut pas être permuté vers un Shift Sœurs.',
                'Une femme ne peut pas être permutée vers un Shift Frères.'
            );
        }

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

        // Une permutation doit être validée par les coordonnateurs des deux
        // shifts concernés : ils sont prévenus (hors auteur de la demande).
        if ($demande->type === 'permutation') {
            $coordonnateurs = User::where('organisation_id', $demande->organisation_id)
                ->whereKeyNot($request->user()->id)
                ->whereNotIn('id', $admins->pluck('id'))
                ->whereHas('shiftMemberships', fn ($q) => $q->where('statut', 'actif')
                    ->whereIn('shift_id', [$demande->shift_id, $demande->shift_destination_id])
                    ->whereHas('role', fn ($r) => $r->where('gere_shifts', true)))
                ->get();
            Notification::send($coordonnateurs, new NouvelleDemandeTransfert($demande));
        }

        return back()->with('success', $demande->type === 'permutation'
            ? "Demande créée avec succès. Elle doit maintenant être validée par les coordonnateurs d'équipe des shifts d'origine et de destination."
            : 'Demande créée avec succès.');
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
        return $this->enregistrerValidationChef(
            $request,
            $shiftTransferRequest,
            'validerOrigine',
            'validation_chef_origine',
            "Le coordonnateur d'équipe du shift d'origine s'est déjà prononcé sur cette permutation.",
            "du shift d'origine"
        );
    }

    /**
     * Validation par le coordonnateur du shift de destination (étape 2/2 avant l'entretien manager, permutation uniquement).
     */
    public function validerDestination(Request $request, ShiftTransferRequest $shiftTransferRequest)
    {
        return $this->enregistrerValidationChef(
            $request,
            $shiftTransferRequest,
            'validerDestination',
            'validation_chef_destination',
            "Le coordonnateur d'équipe du shift de destination s'est déjà prononcé sur cette permutation.",
            'du shift de destination'
        );
    }

    /**
     * Enregistre la décision d'un coordonnateur de façon atomique : la demande est
     * verrouillée (lockForUpdate) et son état relu avant l'écriture, afin que deux
     * requêtes concurrentes du même côté ne puissent pas toutes deux aboutir.
     */
    private function enregistrerValidationChef(
        Request $request,
        ShiftTransferRequest $shiftTransferRequest,
        string $ability,
        string $colonne,
        string $messageDejaValidee,
        string $origineLabel
    ) {
        $this->authorize($ability, $shiftTransferRequest);

        abort_if($shiftTransferRequest->{$colonne} !== null, 422, $messageDejaValidee);

        $validated = $request->validate(['accepte' => ['required', 'boolean']]);
        $accepte = (bool) $validated['accepte'];

        DB::transaction(function () use ($request, $shiftTransferRequest, $ability, $colonne, $messageDejaValidee, $origineLabel, $accepte) {
            $verrou = ShiftTransferRequest::whereKey($shiftTransferRequest->getKey())->lockForUpdate()->firstOrFail();

            abort_if($verrou->{$colonne} !== null, 422, $messageDejaValidee);
            $this->authorize($ability, $verrou);

            $verrou->update([
                $colonne => $accepte,
                "{$colonne}_par_id" => $request->user()->id,
                "{$colonne}_le" => now(),
            ]);

            $this->cloturerSiRefusee($request, $verrou, $accepte, $origineLabel);
        });

        $shiftTransferRequest->refresh();

        if (! $accepte) {
            $shiftTransferRequest->demandeur->notify(new DemandeTransfertResolue($shiftTransferRequest));
        }

        return back()->with('success', $this->messageValidation($shiftTransferRequest, $accepte));
    }

    private function messageValidation(ShiftTransferRequest $shiftTransferRequest, bool $accepte): string
    {
        if (! $accepte) {
            return 'Permutation refusée : la demande est clôturée.';
        }

        return $shiftTransferRequest->validationsChefsCompletes()
            ? 'Validation enregistrée. Les deux coordonnateurs ont validé : la décision finale revient désormais au Conseil.'
            : "Validation enregistrée. En attente de la validation de l'autre coordonnateur d'équipe.";
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

        $typesAvecDecision = ['permutation', 'appel'];
        $shiftPourPoste = $shiftTransferRequest->type === 'appel'
            ? $shiftTransferRequest->shift_id
            : $shiftTransferRequest->shift_destination_id;

        $validated = $request->validate([
            'resultat' => ['required', 'string'],
            'resultat_date' => ['required', 'date'],
            'favorable' => [Rule::requiredIf(in_array($shiftTransferRequest->type, $typesAvecDecision, true)), 'nullable', 'boolean'],
            'shift_position_destination_id' => [
                'nullable',
                Rule::requiredIf(fn () => in_array($shiftTransferRequest->type, $typesAvecDecision, true) && $request->boolean('favorable')),
                Rule::exists('shift_positions', 'id')->where('shift_id', $shiftPourPoste)->whereNull('deleted_at'),
            ],
        ]);

        if (in_array($shiftTransferRequest->type, $typesAvecDecision, true) && ($validated['favorable'] ?? false)) {
            $shiftDestination = ShiftPosition::findOrFail($validated['shift_position_destination_id'])->shift;
            $shiftDestination->assurerGenreCompatible($shiftTransferRequest->servant);
        }

        DB::transaction(function () use ($shiftTransferRequest, $validated, $request) {
            // Verrouille la demande et relit son état : deux décisions finales
            // concurrentes ne peuvent pas toutes deux aboutir.
            $verrou = ShiftTransferRequest::whereKey($shiftTransferRequest->getKey())->lockForUpdate()->firstOrFail();

            abort_if($verrou->statut !== 'en_attente', 422, 'Cette demande a déjà été traitée.');

            if ($verrou->type === 'permutation') {
                abort_unless(
                    $verrou->validationsChefsCompletes(),
                    422,
                    "Les deux coordonnateurs d'équipe (origine et destination) doivent valider la permutation avant la décision finale."
                );
            }

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

            if ($shiftTransferRequest->type === 'appel' && ($validated['favorable'] ?? false)) {
                $this->integrerServantAuPosteAppel($shiftTransferRequest, $validated['shift_position_destination_id']);
            }

            if ($shiftTransferRequest->type === 'releve') {
                $this->terminerAffectationRelevee($shiftTransferRequest);
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
        $this->terminerAffectationsActives($shiftTransferRequest->shift_id, $shiftTransferRequest->servant_id);

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
     * Place le servant appelé sur le poste choisi de son shift — même
     * mécanique que ShiftController::assignServant(), appliquée ici suite à
     * un appel favorable.
     */
    private function integrerServantAuPosteAppel(ShiftTransferRequest $shiftTransferRequest, int $shiftPositionId): void
    {
        $this->terminerAffectationsActives($shiftTransferRequest->shift_id, $shiftTransferRequest->servant_id);

        Assignment::where('shift_position_id', $shiftPositionId)
            ->where('statut', 'actif')
            ->update(['statut' => 'termine', 'date_fin' => now()->toDateString()]);

        Assignment::create([
            'shift_position_id' => $shiftPositionId,
            'servant_id' => $shiftTransferRequest->servant_id,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);
    }

    /**
     * Une relève termine l'affectation active du servant sur le shift
     * d'origine et le servant apparaît dans l'historique des relevés (page
     * dédiée). Le shift n'ayant pas de nombre de postes fixe, le poste
     * n'est pas laissé vacant.
     */
    private function terminerAffectationRelevee(ShiftTransferRequest $shiftTransferRequest): void
    {
        $this->terminerAffectationsActives($shiftTransferRequest->shift_id, $shiftTransferRequest->servant_id);
    }

    /**
     * Termine les affectations actives d'un servant sur un shift et
     * supprime (suppression douce) les postes ainsi libérés : un shift n'a
     * pas de nombre de postes fixe, un poste ne survit pas à son occupant
     * quand personne ne le remplace dans la même opération. L'historique
     * d'affectations du servant reste consultable (postes accessibles via
     * withTrashed()).
     */
    private function terminerAffectationsActives(int $shiftId, int $servantId): void
    {
        $affectations = Assignment::whereIn('shift_position_id', ShiftPosition::where('shift_id', $shiftId)->pluck('id'))
            ->where('servant_id', $servantId)
            ->where('statut', 'actif')
            ->get();

        foreach ($affectations as $affectation) {
            $affectation->update(['statut' => 'termine', 'date_fin' => now()->toDateString()]);
        }

        ShiftPosition::whereIn('id', $affectations->pluck('shift_position_id'))->delete();
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
