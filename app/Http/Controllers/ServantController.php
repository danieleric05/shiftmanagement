<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Pieu;
use App\Models\Servant;
use App\Models\ServantWorkflowStep;
use App\Models\ShiftTransferRequest;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Services\AffectationServant;
use App\Services\SuppressionServant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ServantController extends Controller
{
    private const MESSAGES_PIEU = [
        'pieu_id.exists' => 'Veuillez choisir un pieu de votre organisation (les districts et missions ne peuvent pas être sélectionnés).',
    ];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return $this->renderListe($request);
    }

    /**
     * Vue « Nouveaux » : servant(e)s au statut recommandé (en attente d'intégration).
     */
    public function nouveaux(Request $request)
    {
        return $this->renderListe($request, 'recommande');
    }

    private function renderListe(Request $request, ?string $statut = null)
    {
        $servants = Servant::where('organisation_id', $request->user()->organisation_id)
            ->when($statut !== null, fn ($q) => $q->where('statut', $statut))
            ->with('pieu')
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get()
            ->map(fn (Servant $servant) => [
                'id' => $servant->id,
                'nom' => $servant->nom,
                'prenom' => $servant->prenom,
                'statut' => $servant->statut,
                'pieu' => $servant->pieu?->nom,
            ]);

        return Inertia::render('Servants/Index', [
            'servants' => $servants,
            'nouveaux' => $statut === 'recommande',
            'compteurs' => [
                'actifs' => Servant::where('organisation_id', $request->user()->organisation_id)->where('statut', 'actif')->count(),
                'en_formation' => Servant::where('organisation_id', $request->user()->organisation_id)->where('statut', 'en_formation')->count(),
                'recommandes' => Servant::where('organisation_id', $request->user()->organisation_id)->where('statut', 'recommande')->count(),
                'suspendus' => Servant::where('organisation_id', $request->user()->organisation_id)->where('statut', 'suspendu')->count(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        return Inertia::render('Servants/Create', [
            'pieux' => $this->pieuxSelectionnables($request),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'genre' => ['nullable', 'in:homme,femme'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'telephone_appel' => ['nullable', 'string', 'max:50'],
            'pieu_id' => $this->reglePieu($request),
            'date_appel' => ['nullable', 'date'],
            'date_debut' => ['nullable', 'date'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'titre_leadership' => ['nullable', 'string', 'max:100'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ], self::MESSAGES_PIEU);

        $organisationId = $request->user()->organisation_id;

        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store("servants/{$organisationId}", 'local');
        } else {
            unset($validated['photo']);
        }

        $validated['organisation_id'] = $organisationId;
        $validated['statut'] = 'recommande';

        $servant = Servant::create($validated);
        $servant->demarrerParcours();

        return redirect()->route('servants.show', $servant)->with('success', 'Servant(e) créé(e) avec succès.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Servant $servant)
    {
        $this->authorize('view', $servant);

        $etapes = $servant->workflowSteps()
            ->with(['workflowStep', 'responsable'])
            ->get()
            ->sortBy(fn ($etape) => $etape->workflowStep->ordre)
            ->values()
            ->map(fn ($etape) => [
                'id' => $etape->id,
                'cle' => $etape->workflowStep->cle,
                'nom' => $etape->workflowStep->nom,
                'ordre' => $etape->workflowStep->ordre,
                'statut' => $etape->statut,
                'date' => $etape->date?->format('Y-m-d'),
                'commentaire' => $etape->commentaire,
                'responsable' => $etape->responsable?->name,
            ]);

        $etapesDisponibles = WorkflowStep::whereNotIn('id', $servant->workflowSteps()->pluck('workflow_step_id'))
            ->orderBy('ordre')
            ->get(['id', 'nom']);

        $historique = $servant->assignments()
            ->with([
                'shiftPosition' => fn ($q) => $q->withTrashed(),
                'shiftPosition.shift',
            ])
            ->orderByDesc('date_debut')
            ->get()
            ->map(fn ($assignment) => [
                'id' => $assignment->id,
                'poste' => $assignment->shiftPosition->nom,
                'shift' => $assignment->shiftPosition->shift->nom,
                'date_debut' => $assignment->date_debut->format('Y-m-d'),
                'date_fin' => $assignment->date_fin?->format('Y-m-d'),
                'statut' => $assignment->statut,
            ]);

        return Inertia::render('Servants/Show', [
            'servant' => [
                'id' => $servant->id,
                'nom' => $servant->nom,
                'prenom' => $servant->prenom,
                'genre' => $servant->genre,
                'telephone' => $servant->telephone,
                'telephone_appel' => $servant->telephone_appel,
                'pieu' => $servant->pieu?->nom,
                'date_appel' => $servant->date_appel?->format('Y-m-d'),
                'date_debut' => $servant->date_debut?->format('Y-m-d'),
                'adresse' => $servant->adresse,
                'statut' => $servant->statut,
                'titre_leadership' => $servant->titre_leadership,
                'a_photo' => $servant->photo !== null,
            ],
            'compte' => $request->user()->estAdministrateur() && $servant->user ? ['email' => $servant->user->email] : null,
            'etapes' => $etapes,
            'etapesDisponibles' => $etapesDisponibles,
            'historique' => $historique,
            ...$this->donneesConseil($request, $servant),
        ]);
    }

    /**
     * Historique des relèves/réintégrations et, pour le Conseil du Temple,
     * données des actions réintégration et suppression définitive.
     */
    private function donneesConseil(Request $request, Servant $servant): array
    {
        $user = $request->user();
        $estReleve = $servant->estReleve();

        $releves = $servant->demandesChangement()
            ->where('type', 'releve')
            ->where('statut', 'traitee')
            ->with(['shift' => fn ($q) => $q->withTrashed(), 'decideur', 'reintegrePar'])
            ->orderByDesc('resultat_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ShiftTransferRequest $r) => [
                'id' => $r->id,
                'shift' => $r->shift?->nom,
                'motif' => $r->motif,
                'resultat_date' => $r->resultat_date?->format('Y-m-d'),
                'decideur' => $r->decideur?->name,
                'reintegre_le' => $r->reintegre_le?->format('Y-m-d'),
                'reintegre_par' => $r->reintegrePar?->name,
                'reintegration_commentaire' => $r->reintegration_commentaire,
            ]);

        return [
            'releves' => $releves,
            'estReleve' => $estReleve,
            'peutReintegrer' => $estReleve && $user->can('reintegrate', $servant),
            'shiftsReintegration' => $estReleve && $user->can('reintegrate', $servant)
                ? app(AffectationServant::class)->optionsPourOrganisation($servant->organisation_id)
                : [],
            'suppression' => $user->can('delete', $servant)
                ? [
                    'bilan' => app(SuppressionServant::class)->bilan($servant),
                    'compte_lie' => $servant->user_id !== null,
                ]
                : null,
        ];
    }

    /**
     * Consultation en lecture seule du parcours d'un servant par le coordonnateur
     * d'équipe d'un shift où il est actuellement affecté (cf. ServantPolicy::viewMine()).
     */
    public function mine(Request $request, Servant $servant)
    {
        $this->authorize('viewMine', $servant);

        $etapes = $servant->workflowSteps()
            ->with(['workflowStep', 'responsable'])
            ->get()
            ->sortBy(fn ($etape) => $etape->workflowStep->ordre)
            ->values()
            ->map(fn ($etape) => [
                'id' => $etape->id,
                'cle' => $etape->workflowStep->cle,
                'nom' => $etape->workflowStep->nom,
                'ordre' => $etape->workflowStep->ordre,
                'statut' => $etape->statut,
                'date' => $etape->date?->format('Y-m-d'),
                'commentaire' => $etape->commentaire,
                'responsable' => $etape->responsable?->name,
            ]);

        $etapesDisponibles = WorkflowStep::whereNotIn('id', $servant->workflowSteps()->pluck('workflow_step_id'))
            ->orderBy('ordre')
            ->get(['id', 'nom']);

        return Inertia::render('Servants/MonServant', [
            'servant' => [
                'id' => $servant->id,
                'nom' => $servant->nom,
                'prenom' => $servant->prenom,
                'telephone' => $servant->telephone,
                'statut' => $servant->statut,
                'titre_leadership' => $servant->titre_leadership,
                'a_photo' => $servant->photo !== null,
            ],
            'etapes' => $etapes,
            'etapesDisponibles' => $etapesDisponibles,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Servant $servant)
    {
        $this->authorize('update', $servant);

        $estAdministrateur = $request->user()->gereServantsEtPermutations();

        return Inertia::render('Servants/Edit', [
            'servant' => [
                'id' => $servant->id,
                'nom' => $servant->nom,
                'prenom' => $servant->prenom,
                'genre' => $servant->genre,
                'telephone' => $servant->telephone,
                'telephone_appel' => $servant->telephone_appel,
                'pieu_id' => $servant->pieu_id,
                'date_appel' => $servant->date_appel?->format('Y-m-d'),
                'date_debut' => $servant->date_debut?->format('Y-m-d'),
                'adresse' => $servant->adresse,
                'statut' => $servant->statut,
                'titre_leadership' => $servant->titre_leadership,
                'a_photo' => $servant->photo !== null,
            ],
            'pieux' => $this->pieuxSelectionnables($request),
            'uniteActuelle' => $servant->pieu && $servant->pieu->type !== 'pieu'
                ? ['id' => $servant->pieu->id, 'nom' => $servant->pieu->nom, 'type' => $servant->pieu->type]
                : null,
            'retourRoute' => $estAdministrateur ? 'servants.show' : 'servants.mine.show',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Servant $servant)
    {
        $this->authorize('update', $servant);

        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'genre' => ['nullable', 'in:homme,femme'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'telephone_appel' => ['nullable', 'string', 'max:50'],
            'pieu_id' => $this->reglePieu($request, $servant),
            'date_appel' => ['nullable', 'date'],
            'date_debut' => ['nullable', 'date'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'statut' => ['required', 'in:recommande,en_formation,actif,suspendu,retire'],
            'titre_leadership' => ['nullable', 'string', 'max:100'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ], self::MESSAGES_PIEU);

        if ($validated['statut'] === 'actif') {
            $this->ensureWorkflowComplete($servant);
        }

        if (($validated['genre'] ?? null) !== null && $validated['genre'] !== $servant->genre) {
            $this->ensureGenreCompatibleAvecAffectationsActives($servant, $validated['genre']);
        }

        if ($request->hasFile('photo')) {
            if ($servant->photo) {
                Storage::disk('local')->delete($servant->photo);
            }
            $validated['photo'] = $request->file('photo')->store("servants/{$servant->organisation_id}", 'local');
        } else {
            unset($validated['photo']);
        }

        $devientRetire = $validated['statut'] === 'retire' && $servant->statut !== 'retire';

        DB::transaction(function () use ($servant, $validated, $devientRetire) {
            $servant->update($validated);

            if ($devientRetire) {
                $servant->assignationsActives()->update([
                    'statut' => 'termine',
                    'date_fin' => now()->toDateString(),
                ]);
            }
        });

        $retourRoute = $request->user()->gereServantsEtPermutations() ? 'servants.show' : 'servants.mine.show';

        return redirect()->route($retourRoute, $servant)->with('success', 'Servant(e) mis(e) à jour avec succès.');
    }

    /**
     * Seuls les pieux (type "pieu") de l'organisation sont proposés dans le
     * sélecteur « Pieu / District / Mission » du formulaire servant.
     */
    private function pieuxSelectionnables(Request $request)
    {
        return Pieu::where('organisation_id', $request->user()->organisation_id)
            ->where('type', 'pieu')
            ->orderBy('nom')
            ->get(['id', 'nom']);
    }

    /**
     * pieu_id doit désigner un pieu (type "pieu") de l'organisation. Exception :
     * un servant déjà rattaché à un district/une mission (import) peut être
     * modifié sans changer pieu_id — la valeur inchangée est alors acceptée.
     */
    private function reglePieu(Request $request, ?Servant $servant = null): array
    {
        $organisationId = $request->user()->organisation_id;

        if ($servant && $servant->pieu_id !== null && (string) $request->input('pieu_id') === (string) $servant->pieu_id) {
            return ['nullable', Rule::exists('pieux', 'id')->where('organisation_id', $organisationId)];
        }

        return ['nullable', Rule::exists('pieux', 'id')->where('organisation_id', $organisationId)->where('type', 'pieu')];
    }

    /**
     * Bloque le passage au statut "actif" tant que le parcours d'intégration
     * n'est pas termine (chapitre 3.2 : validation des etapes avant nomination).
     */
    private function ensureWorkflowComplete(Servant $servant): void
    {
        $incomplete = $servant->workflowSteps()
            ->whereIn('statut', ['en_attente', 'en_cours'])
            ->exists();

        if ($incomplete) {
            throw ValidationException::withMessages([
                'statut' => 'Ce servant(e) ne peut pas passer au statut « Ancien » tant que toutes les étapes de son parcours ne sont pas terminées.',
            ]);
        }
    }

    /**
     * Empêche de changer le genre d'un servant tant qu'il est activement
     * affecté à un poste d'un Shift qui n'accueille pas ce genre (même règle
     * que ShiftController::assignServant(), appliquée dans l'autre sens).
     */
    private function ensureGenreCompatibleAvecAffectationsActives(Servant $servant, string $nouveauGenre): void
    {
        $conflit = $servant->assignationsActives()
            ->with('shiftPosition.shift')
            ->get()
            ->first(fn (Assignment $a) => $a->shiftPosition->shift->genreAttendu() !== $nouveauGenre);

        if ($conflit) {
            throw ValidationException::withMessages([
                'genre' => "Ce servant(e) est actuellement affecté(e) au Shift « {$conflit->shiftPosition->shift->nom} », qui n'accueille pas ce genre. Retirez-le d'abord de ce Shift.",
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Servant $servant, SuppressionServant $suppression)
    {
        $this->authorize('delete', $servant);

        // Confirmation forte : nom complet du servant ou mot SUPPRIMER.
        $request->validate([
            'confirmation' => ['required', 'string'],
        ], [
            'confirmation.required' => 'Tapez le nom complet du servant(e) ou le mot SUPPRIMER pour confirmer la suppression définitive.',
        ]);

        $saisie = trim((string) $request->input('confirmation'));
        $normaliser = fn (string $valeur) => mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($valeur)));

        if ($saisie !== 'SUPPRIMER' && $normaliser($saisie) !== $normaliser($servant->nomComplet())) {
            throw ValidationException::withMessages([
                'confirmation' => 'La confirmation ne correspond pas : tapez exactement le nom complet du servant(e) ou le mot SUPPRIMER.',
            ]);
        }

        $resultat = $suppression->supprimer($servant, $request->user());

        $message = 'Servant(e) supprimé(e) définitivement, ainsi que ses affectations, son parcours et son historique de changements.';

        if ($resultat['compte_lie']) {
            $message .= ' Le compte de connexion du membre lié à cette fiche a été conservé : seul le lien avec la fiche a été supprimé.';
        }

        return redirect()->route('servants.index')->with('success', $message);
    }

    /**
     * Ajouter manuellement une étape du parcours d'intégration à un servant
     * (aucune étape n'est plus créée automatiquement à la création du
     * servant : l'administrateur/coordonnateur choisit dans le catalogue des
     * étapes celles qui s'appliquent à ce servant).
     */
    /**
     * Démarre le parcours d'intégration standard en une fois (onglet
     * Situation) — pour un servant qui n'en a encore aucun (créé avant que
     * la création ne le démarre automatiquement partout dans l'app, ou
     * exceptionnellement créé sans, ex. import). Sans effet s'il en a déjà un.
     */
    public function demarrerParcours(Request $request, Servant $servant)
    {
        $this->authorize('update', $servant);

        $servant->demarrerParcours();

        return back()->with('success', 'Parcours démarré avec succès.');
    }

    public function storeWorkflowStep(Request $request, Servant $servant)
    {
        $this->authorize('update', $servant);

        $validated = $request->validate([
            'workflow_step_id' => [
                'required',
                'exists:workflow_steps,id',
                Rule::unique('servant_workflow_steps', 'workflow_step_id')->where('servant_id', $servant->id),
            ],
        ]);

        $workflowStep = WorkflowStep::findOrFail($validated['workflow_step_id']);
        abort_if($workflowStep->cle === 'formation' && ! $request->user()->estAdministrateur(), 403, "Seul un administrateur peut ajouter l'étape Formation.");

        $servant->workflowSteps()->create([
            'workflow_step_id' => $validated['workflow_step_id'],
            'statut' => 'en_attente',
        ]);

        return back()->with('success', 'Étape ajoutée avec succès.');
    }

    /**
     * Mettre à jour une étape du parcours d'intégration d'un servant.
     */
    public function updateWorkflowStep(Request $request, Servant $servant, ServantWorkflowStep $workflowStep)
    {
        $this->authorize('update', $servant);

        abort_if($workflowStep->servant_id !== $servant->id, 404);
        abort_if(
            $workflowStep->workflowStep->cle === 'formation' && ! $request->user()->estAdministrateur(),
            403,
            "Seul un administrateur peut modifier l'étape Formation."
        );

        $validated = $request->validate([
            'statut' => ['required', 'in:en_attente,en_cours,termine,ignore'],
            'date' => ['nullable', 'date'],
            'commentaire' => ['nullable', 'string'],
        ]);

        $validated['responsable_id'] = $request->user()->id;

        $workflowStep->update($validated);

        return back()->with('success', 'Étape mise à jour avec succès.');
    }

    /**
     * Retirer une étape ajoutée par erreur au parcours d'un servant.
     */
    public function destroyWorkflowStep(Request $request, Servant $servant, ServantWorkflowStep $workflowStep)
    {
        $this->authorize('update', $servant);

        abort_if($workflowStep->servant_id !== $servant->id, 404);
        abort_if(
            $workflowStep->workflowStep->cle === 'formation' && ! $request->user()->estAdministrateur(),
            403,
            "Seul un administrateur peut retirer l'étape Formation."
        );

        $workflowStep->delete();

        return back()->with('success', 'Étape retirée avec succès.');
    }

    /**
     * Créer un compte de connexion pour ce servant.
     */
    public function storeAccount(Request $request, Servant $servant)
    {
        $this->authorize('manageAccount', $servant);

        abort_if($servant->user_id !== null, 422, 'Ce servant(e) a déjà un compte de connexion.');

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $servant->nomComplet(),
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'organisation_id' => $servant->organisation_id,
            'role_id' => null,
            'email_verified_at' => now(),
        ]);

        $servant->update(['user_id' => $user->id]);

        return back()->with('success', 'Compte de connexion créé avec succès.');
    }

    /**
     * Révoquer le compte de connexion de ce servant.
     */
    public function destroyAccount(Request $request, Servant $servant)
    {
        $this->authorize('manageAccount', $servant);

        $user = $servant->user;

        if ($user) {
            $servant->update(['user_id' => null]);
            $user->delete();
        }

        return back()->with('success', 'Compte de connexion révoqué avec succès.');
    }

    /**
     * Droit à l'effacement (RGPD) : anonymise les données personnelles du
     * servant plutôt que de supprimer son dossier, afin de préserver
     * l'intégrité de son historique d'affectations. Termine ses affectations
     * actives et révoque son éventuel compte de connexion.
     */
    public function anonymize(Request $request, Servant $servant)
    {
        $this->authorize('anonymize', $servant);

        DB::transaction(function () use ($servant) {
            if ($servant->photo) {
                Storage::disk('local')->delete($servant->photo);
            }

            $servant->assignationsActives()->update([
                'statut' => 'termine',
                'date_fin' => now()->toDateString(),
            ]);

            if ($servant->user_id) {
                $user = $servant->user;
                $servant->update(['user_id' => null]);
                $user?->delete();
            }

            $servant->update([
                'nom' => 'Anonymisé',
                'prenom' => "Servant #{$servant->id}",
                'genre' => null,
                'telephone' => null,
                'telephone_appel' => null,
                'adresse' => null,
                'photo' => null,
                'statut' => 'retire',
            ]);
        });

        return redirect()->route('servants.index')->with('success', 'Servant(e) anonymisé(e) avec succès.');
    }

    /**
     * Droit d'accès et de portabilité (RGPD) : export structuré de toutes les
     * données personnelles détenues sur ce servant.
     */
    public function export(Request $request, Servant $servant)
    {
        $this->authorize('export', $servant);

        $data = [
            'identite' => [
                'nom' => $servant->nom,
                'prenom' => $servant->prenom,
                'genre' => $servant->genre,
                'telephone' => $servant->telephone,
                'telephone_appel' => $servant->telephone_appel,
                'date_appel' => $servant->date_appel?->format('Y-m-d'),
                'date_debut' => $servant->date_debut?->format('Y-m-d'),
                'adresse' => $servant->adresse,
                'pieu' => $servant->pieu?->nom,
                'statut' => $servant->statut,
                'titre_leadership' => $servant->titre_leadership,
            ],
            'parcours' => $servant->workflowSteps()->with('workflowStep')->get()->map(fn ($etape) => [
                'etape' => $etape->workflowStep->nom,
                'statut' => $etape->statut,
                'date' => $etape->date?->format('Y-m-d'),
                'commentaire' => $etape->commentaire,
            ]),
            'historique_affectations' => $servant->assignments()->with([
                'shiftPosition' => fn ($q) => $q->withTrashed(),
                'shiftPosition.shift',
            ])->get()->map(fn ($assignment) => [
                'poste' => $assignment->shiftPosition->nom,
                'shift' => $assignment->shiftPosition->shift->nom,
                'date_debut' => $assignment->date_debut->format('Y-m-d'),
                'date_fin' => $assignment->date_fin?->format('Y-m-d'),
                'statut' => $assignment->statut,
            ]),
        ];

        return response()->json($data, 200, [
            'Content-Disposition' => "attachment; filename=\"servant-{$servant->id}-donnees.json\"",
        ]);
    }

    /**
     * Servir la photo du servant depuis le disque privé (jamais d'URL publique directe).
     */
    public function photo(Request $request, Servant $servant)
    {
        // Consultation (fiche, y compris rôle « Autres » en lecture seule) ou
        // coordonnateur d'un shift où le servant est affecté.
        abort_unless($request->user()->can('view', $servant) || $request->user()->can('viewMine', $servant), 403);

        abort_unless($servant->photo && Storage::disk('local')->exists($servant->photo), 404);

        return Storage::disk('local')->response($servant->photo);
    }
}
