<?php

namespace App\Http\Controllers;

use App\Models\Pieu;
use App\Models\Role;
use App\Models\Servant;
use App\Models\ServantWorkflowStep;
use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ServantController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $servants = Servant::where('organisation_id', $request->user()->organisation_id)
            ->with('pieu')
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get()
            ->map(fn (Servant $servant) => [
                'id' => $servant->id,
                'nom' => $servant->nom,
                'prenom' => $servant->prenom,
                'statut' => $servant->statut,
                'telephone' => $servant->telephone,
                'pieu' => $servant->pieu?->nom,
            ]);

        return Inertia::render('Servants/Index', [
            'servants' => $servants,
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
            'pieux' => Pieu::where('organisation_id', $request->user()->organisation_id)->orderBy('nom')->get(['id', 'nom']),
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
            'pieu_id' => ['nullable', 'exists:pieux,id'],
            'date_naissance' => ['nullable', 'date'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'titre_leadership' => ['nullable', 'string', 'max:100'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);

        $organisationId = $request->user()->organisation_id;

        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store("servants/{$organisationId}", 'local');
        } else {
            unset($validated['photo']);
        }

        $validated['organisation_id'] = $organisationId;
        $validated['statut'] = 'recommande';

        $servant = Servant::create($validated);

        foreach (WorkflowStep::orderBy('ordre')->get() as $index => $step) {
            $servant->workflowSteps()->create([
                'workflow_step_id' => $step->id,
                'statut' => $index === 0 ? 'en_cours' : 'en_attente',
            ]);
        }

        return redirect()->route('servants.show', $servant)->with('success', 'Servant créé avec succès.');
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

        $historique = $servant->assignments()
            ->with(['shiftPosition.shift'])
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
                'date_naissance' => $servant->date_naissance?->format('Y-m-d'),
                'adresse' => $servant->adresse,
                'statut' => $servant->statut,
                'titre_leadership' => $servant->titre_leadership,
                'a_photo' => $servant->photo !== null,
            ],
            'compte' => $servant->user ? ['email' => $servant->user->email] : null,
            'etapes' => $etapes,
            'historique' => $historique,
        ]);
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
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Servant $servant)
    {
        $this->authorize('update', $servant);

        $estAdministrateur = $request->user()->estAdministrateur();

        return Inertia::render('Servants/Edit', [
            'servant' => [
                'id' => $servant->id,
                'nom' => $servant->nom,
                'prenom' => $servant->prenom,
                'genre' => $servant->genre,
                'telephone' => $servant->telephone,
                'telephone_appel' => $servant->telephone_appel,
                'pieu_id' => $servant->pieu_id,
                'date_naissance' => $servant->date_naissance?->format('Y-m-d'),
                'adresse' => $servant->adresse,
                'statut' => $servant->statut,
                'titre_leadership' => $servant->titre_leadership,
                'a_photo' => $servant->photo !== null,
            ],
            'pieux' => Pieu::where('organisation_id', $request->user()->organisation_id)->orderBy('nom')->get(['id', 'nom']),
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
            'pieu_id' => ['nullable', 'exists:pieux,id'],
            'date_naissance' => ['nullable', 'date'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'statut' => ['required', 'in:recommande,en_formation,actif,suspendu,retire'],
            'titre_leadership' => ['nullable', 'string', 'max:100'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($validated['statut'] === 'actif') {
            $this->ensureWorkflowComplete($servant);
        }

        if ($request->hasFile('photo')) {
            if ($servant->photo) {
                Storage::disk('local')->delete($servant->photo);
            }
            $validated['photo'] = $request->file('photo')->store("servants/{$servant->organisation_id}", 'local');
        } else {
            unset($validated['photo']);
        }

        $servant->update($validated);

        $retourRoute = $request->user()->estAdministrateur() ? 'servants.show' : 'servants.mine.show';

        return redirect()->route($retourRoute, $servant)->with('success', 'Servant mis à jour avec succès.');
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
                'statut' => 'Ce servant ne peut pas devenir actif tant que toutes les étapes de son parcours ne sont pas terminées.',
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Servant $servant)
    {
        $this->authorize('delete', $servant);

        $servant->delete();

        return redirect()->route('servants.index')->with('success', 'Servant supprimé avec succès.');
    }

    /**
     * Mettre à jour une étape du parcours d'intégration d'un servant.
     */
    public function updateWorkflowStep(Request $request, Servant $servant, ServantWorkflowStep $workflowStep)
    {
        $this->authorize('update', $servant);

        abort_if($workflowStep->servant_id !== $servant->id, 404);

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
     * Créer un compte de connexion pour ce servant.
     */
    public function storeAccount(Request $request, Servant $servant)
    {
        $this->authorize('manageAccount', $servant);

        abort_if($servant->user_id !== null, 422, 'Ce servant a déjà un compte de connexion.');

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
        ]);

        $membreRole = Role::where('slug', 'membre')->first();

        $user = User::create([
            'name' => $servant->nomComplet(),
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'organisation_id' => $servant->organisation_id,
            'role_id' => $membreRole?->id,
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
                'date_naissance' => null,
                'adresse' => null,
                'photo' => null,
                'statut' => 'retire',
            ]);
        });

        return redirect()->route('servants.index')->with('success', 'Servant anonymisé avec succès.');
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
                'date_naissance' => $servant->date_naissance?->format('Y-m-d'),
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
            'historique_affectations' => $servant->assignments()->with('shiftPosition.shift')->get()->map(fn ($assignment) => [
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
        $this->authorize('viewMine', $servant);

        abort_unless($servant->photo && Storage::disk('local')->exists($servant->photo), 404);

        return Storage::disk('local')->response($servant->photo);
    }
}
