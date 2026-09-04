<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftMember;
use App\Models\ShiftPosition;
use App\Models\ShiftTemplatePosition;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ShiftController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $shifts = Shift::where('organisation_id', $request->user()->organisation_id)
            ->withCount('positions as postes_total')
            ->with(['positions' => fn ($q) => $q->select('id', 'shift_id')
                ->withCount(['assignments' => fn ($a) => $a->where('statut', 'actif')]),
            ])
            ->orderByJourCalendrier()
            ->orderBy('heure_debut')
            ->get()
            ->map(function (Shift $shift) {
                return [
                    'id' => $shift->id,
                    'nom' => $shift->nom,
                    'jour' => $shift->jour,
                    'heure_debut' => substr($shift->heure_debut, 0, 5),
                    'heure_fin' => substr($shift->heure_fin, 0, 5),
                    'postes_total' => $shift->postes_total,
                    'postes_vacants' => $shift->positions->where('assignments_count', 0)->count(),
                    'genre' => $shift->estSoeurs() ? 'soeurs' : 'freres',
                ];
            });

        return Inertia::render('Shifts/Index', [
            'shifts' => $shifts,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Shift $shift)
    {
        $this->authorize('view', $shift);

        $positions = $this->triPositionsOccupeesPuisVacantes(
            $shift->positions()
                ->with(['assignments' => fn ($q) => $q->where('statut', 'actif')
                    ->with(['servant.workflowSteps.workflowStep']),
                ])
                ->get()
        )->map(fn (ShiftPosition $position) => $this->formatePosition($position));

        $servantsDejaAffectesIds = Assignment::whereIn('shift_position_id', $shift->positions()->pluck('id'))
            ->where('statut', 'actif')
            ->pluck('servant_id');

        $servantsDisponibles = Servant::where('organisation_id', $request->user()->organisation_id)
            ->where('statut', 'actif')
            ->whereNotIn('id', $servantsDejaAffectesIds)
            ->orderBy('nom')
            ->get()
            ->map(fn (Servant $servant) => [
                'id' => $servant->id,
                'nom_complet' => $servant->nomComplet(),
            ]);

        $postesDisponibles = $this->postesDisponiblesPourShift($shift);

        return Inertia::render('Shifts/Show', [
            'shift' => [
                'id' => $shift->id,
                'nom' => $shift->nom,
                'jour' => $shift->jour,
                'heure_debut' => substr($shift->heure_debut, 0, 5),
                'heure_fin' => substr($shift->heure_fin, 0, 5),
                'statut' => $shift->statut,
            ],
            'positions' => $positions,
            'servantsDisponibles' => $servantsDisponibles,
            'postesDisponibles' => $postesDisponibles,
        ]);
    }

    /**
     * Postes du modèle du shift, filtrés selon le genre du Shift (déduit de
     * son nom, ex. "Mardi Matin Sœurs") : un Shift Frères ne propose jamais
     * un poste Coordonnatrice, et inversement. Le Scelleur est une ordonnance
     * exclusivement masculine : jamais proposé sur un Shift Sœurs, même si
     * son nom ne porte pas de marqueur de genre explicite.
     *
     * Les postes uniques (toute la hiérarchie de coordination) sont en plus
     * retirés dès qu'ils existent déjà sur ce Shift (occupés ou vacants) : un
     * Shift n'a qu'un seul Coordonnateur. "Servant"/"Servante" restent
     * proposables sans limite, plusieurs personnes tenant ce rôle par Shift.
     */
    private function postesDisponiblesPourShift(Shift $shift): Collection
    {
        if (! $shift->shift_template_id) {
            return collect();
        }

        $estSoeurs = $shift->estSoeurs();

        $idsDejaPresents = $shift->positions()->pluck('shift_template_position_id')->filter();

        return ShiftTemplatePosition::where('shift_template_id', $shift->shift_template_id)
            ->orderBy('ordre')
            ->get(['id', 'nom', 'ordre'])
            ->filter(function (ShiftTemplatePosition $poste) use ($estSoeurs) {
                $genre = match (true) {
                    str_contains($poste->nom, 'Coordonnatrice') || $poste->nom === 'Servante' => 'soeurs',
                    str_contains($poste->nom, 'Coordonnateur') || $poste->nom === 'Servant' => 'freres',
                    $poste->nom === 'Scelleur' => 'freres',
                    default => null,
                };

                return $genre === null || $genre === ($estSoeurs ? 'soeurs' : 'freres');
            })
            ->reject(fn (ShiftTemplatePosition $poste) => ! in_array($poste->nom, ['Servant', 'Servante'], true)
                && $idsDejaPresents->contains($poste->id))
            ->values();
    }

    /**
     * Ajouter un poste au Shift et y affecter directement un servant — existant
     * ou créé à la volée — en une seule action (rôle + servant choisis
     * ensemble, plutôt qu'un poste vacant à pourvoir séparément ensuite).
     */
    public function storePosition(Request $request, Shift $shift)
    {
        $this->authorize('update', $shift);

        $validated = $request->validate([
            'shift_template_position_id' => ['required', 'exists:shift_template_positions,id'],
            'servant_id' => ['nullable', 'exists:servants,id'],
            'nouveau_servant' => ['nullable', 'array'],
            'nouveau_servant.nom' => ['required_with:nouveau_servant', 'string', 'max:255'],
            'nouveau_servant.prenom' => ['required_with:nouveau_servant', 'string', 'max:255'],
            'nouveau_servant.genre' => ['nullable', 'in:homme,femme'],
            'nouveau_servant.telephone' => ['nullable', 'string', 'max:50'],
        ]);

        abort_if(
            empty($validated['servant_id']) && empty($validated['nouveau_servant']),
            422,
            'Sélectionnez un servant existant ou renseignez les informations du nouveau servant.'
        );

        $templatePosition = $this->postesDisponiblesPourShift($shift)
            ->firstWhere('id', (int) $validated['shift_template_position_id']);

        abort_if($templatePosition === null, 422, "Ce poste n'est pas proposé pour ce Shift.");

        DB::transaction(function () use ($request, $shift, $validated, $templatePosition) {
            if (! empty($validated['nouveau_servant'])) {
                $servant = Servant::create([
                    'organisation_id' => $shift->organisation_id,
                    'nom' => $validated['nouveau_servant']['nom'],
                    'prenom' => $validated['nouveau_servant']['prenom'],
                    'genre' => $validated['nouveau_servant']['genre'] ?? null,
                    'telephone' => $validated['nouveau_servant']['telephone'] ?? null,
                    'statut' => 'recommande',
                ]);

                $servant->demarrerParcours();
            } else {
                $servant = Servant::findOrFail($validated['servant_id']);
                abort_if($servant->organisation_id !== $request->user()->organisation_id, 403);
            }

            $this->assurerGenreCompatible($shift, $servant);

            $position = $shift->positions()->create([
                'shift_template_position_id' => $templatePosition->id,
                'nom' => $templatePosition->nom,
                'ordre' => $templatePosition->ordre,
            ]);

            Assignment::create([
                'shift_position_id' => $position->id,
                'servant_id' => $servant->id,
                'date_debut' => now()->toDateString(),
                'statut' => 'actif',
            ]);
        });

        return back()->with('success', 'Servant affecté avec succès.');
    }

    /**
     * Bloque l'affectation d'un servant à un Shift dont le genre ne
     * correspond pas au sien (déduit du nom du Shift, ex. "Mardi Matin
     * Sœurs") — même règle des deux côtés : affectation directe (ici et
     * assignServant()) et permutation (ShiftTransferRequestController).
     */
    private function assurerGenreCompatible(Shift $shift, Servant $servant): void
    {
        $genreAttendu = $shift->genreAttendu();

        abort_if(
            $servant->genre !== null && $servant->genre !== $genreAttendu,
            422,
            $genreAttendu === 'femme'
                ? 'Un homme ne peut pas être affecté à un Shift Sœurs.'
                : 'Une femme ne peut pas être affectée à un Shift Frères.'
        );
    }

    /**
     * Supprimer un poste vacant du Shift.
     */
    public function destroyPosition(Request $request, Shift $shift, ShiftPosition $position)
    {
        $this->authorize('update', $shift);

        abort_if($position->shift_id !== $shift->id, 404);
        abort_if(
            $position->assignments()->where('statut', 'actif')->exists(),
            422,
            'Retirez le servant affecté avant de supprimer ce poste.'
        );

        $position->delete();

        return back()->with('success', 'Poste supprimé avec succès.');
    }

    /**
     * Les postes occupés d'abord (dans leur ordre habituel), les postes
     * vacants ensuite : sur un roster de 20+ Servants, les quelques postes à
     * pourvoir ne doivent pas se retrouver noyés au milieu de la liste.
     *
     * @param  Collection<int, ShiftPosition>  $positions
     * @return Collection<int, ShiftPosition>
     */
    private function triPositionsOccupeesPuisVacantes(Collection $positions): Collection
    {
        return $positions->partition(fn (ShiftPosition $position) => $position->assignments->isNotEmpty())
            ->pipe(fn ($groupes) => $groupes[0]->concat($groupes[1]))
            ->values();
    }

    /**
     * Formate un poste et son titulaire pour la fiche shift, avec les étapes
     * clés du parcours (protection de la jeunesse, badge, photo) attendues
     * sur le roster (cahier des charges "TABLEAU DE BORD").
     */
    private function formatePosition(ShiftPosition $position): array
    {
        $assignment = $position->assignments->first();
        $servant = $assignment?->servant;

        return [
            'id' => $position->id,
            'nom' => $position->nom,
            'ordre' => $position->ordre,
            'assignment_id' => $assignment?->id,
            'titulaire' => $servant ? [
                'id' => $servant->id,
                'nom_complet' => $servant->nomComplet(),
                'coordonnees' => $servant->telephone,
                'titre_leadership' => $servant->titre_leadership,
                'depuis' => $assignment->date_debut->format('Y-m-d'),
                'etapes' => $this->etapesRoster($servant),
            ] : null,
        ];
    }

    private function etapesRoster(Servant $servant): array
    {
        $parCle = $servant->workflowSteps->keyBy(fn ($e) => $e->workflowStep->cle);
        $etape = fn (string $cle) => [
            'workflow_step_id' => $parCle->get($cle)?->id,
            'termine' => $parCle->get($cle)?->statut === 'termine',
        ];

        return [
            'protection_jeunesse' => $etape('youth_protection'),
            'badge' => $etape('badge'),
            'photo' => $etape('photo'),
        ];
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Shift $shift)
    {
        $this->authorize('update', $shift);

        return Inertia::render('Shifts/Edit', [
            'shift' => [
                'id' => $shift->id,
                'nom' => $shift->nom,
                'jour' => $shift->jour,
                'heure_debut' => substr($shift->heure_debut, 0, 5),
                'heure_fin' => substr($shift->heure_fin, 0, 5),
                'statut' => $shift->statut,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Shift $shift)
    {
        $this->authorize('update', $shift);

        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'jour' => ['required', 'in:lundi,mardi,mercredi,jeudi,vendredi,samedi,dimanche'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i', 'after:heure_debut'],
            'statut' => ['required', 'in:actif,inactif'],
        ]);

        $shift->update($validated);

        return redirect()->route('shifts.show', $shift)->with('success', 'Shift mis à jour avec succès.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Shift $shift)
    {
        $this->authorize('delete', $shift);

        $shift->delete();

        return redirect()->route('shifts.index')->with('success', 'Shift supprimé avec succès.');
    }

    /**
     * Affecter un membre au Shift (chapitre 3.4). Le rôle attribué au sein du
     * Shift est celui de son compte utilisateur : ce n'est pas ici qu'on
     * gère les rôles (cf. gestion des membres), seulement l'appartenance au Shift.
     */
    public function addMember(Request $request, Shift $shift)
    {
        $this->authorize('update', $shift);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $user = User::findOrFail($validated['user_id']);
        abort_if($user->organisation_id !== $shift->organisation_id, 403);
        abort_if($user->role_id === null, 422, "Ce compte n'a pas de rôle : attribuez-lui un rôle avant de l'affecter à un Shift.");

        ShiftMember::create([
            'shift_id' => $shift->id,
            'user_id' => $user->id,
            'role_id' => $user->role_id,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        return back()->with('success', 'Membre affecté avec succès.');
    }

    /**
     * Mettre fin à l'affectation d'un membre (retrait / remplacement, chapitre 3.9).
     */
    public function removeMember(Request $request, Shift $shift, ShiftMember $shiftMember)
    {
        $this->authorize('update', $shift);

        $shiftMember->update([
            'date_fin' => now()->toDateString(),
            'statut' => 'termine',
        ]);

        return back()->with('success', 'Affectation terminée avec succès.');
    }

    /**
     * Affecter un servant à un poste du Shift.
     */
    public function assignServant(Request $request, Shift $shift, ShiftPosition $position)
    {
        $this->authorize('update', $shift);

        abort_if($position->shift_id !== $shift->id, 404);

        $validated = $request->validate([
            'servant_id' => ['required', 'exists:servants,id'],
        ]);

        $servant = Servant::findOrFail($validated['servant_id']);
        abort_if($servant->organisation_id !== $request->user()->organisation_id, 403);

        $this->assurerGenreCompatible($shift, $servant);

        $position->assignments()->where('statut', 'actif')->update([
            'statut' => 'termine',
            'date_fin' => now()->toDateString(),
        ]);

        // Empêche un même servant de se retrouver sur deux postes du même
        // shift (ex. affectations concurrentes) même si l'interface a
        // normalement déjà filtré ce servant de la liste des disponibles.
        Assignment::whereIn('shift_position_id', $shift->positions()->pluck('id'))
            ->where('servant_id', $servant->id)
            ->where('statut', 'actif')
            ->update(['statut' => 'termine', 'date_fin' => now()->toDateString()]);

        Assignment::create([
            'shift_position_id' => $position->id,
            'servant_id' => $servant->id,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        return back()->with('success', 'Servant affecté au poste avec succès.');
    }

    /**
     * Retirer un servant d'un poste. Un Shift n'a pas de nombre de postes
     * fixe : le poste n'est pas laissé vacant, il disparaît (suppression
     * douce, l'historique d'affectations du servant reste consultable).
     */
    public function endAssignment(Request $request, Shift $shift, ShiftPosition $position, Assignment $assignment)
    {
        $this->authorize('update', $shift);

        abort_if($position->shift_id !== $shift->id, 404);
        abort_if($assignment->shift_position_id !== $position->id, 404);

        $assignment->update([
            'date_fin' => now()->toDateString(),
            'statut' => 'termine',
        ]);

        $position->delete();

        return back()->with('success', 'Servant retiré du Shift.');
    }

    /**
     * Consultation en lecture seule du roster d'un shift par son coordinateur
     * (espace "Mon shift" — voir ShiftPolicy::view).
     */
    public function monShift(Request $request, Shift $shift)
    {
        $this->authorize('view', $shift);

        $user = $request->user();
        $estMonShift = $user->estAdministrateur() || $user->shiftsGeres()->contains($shift->id);

        $membres = $shift->membresActifs()
            ->with(['user', 'role'])
            ->get()
            ->map(fn (ShiftMember $sm) => [
                'user_id' => $sm->user->id,
                'name' => $sm->user->name,
                'email' => $sm->user->email,
                'role' => $sm->role->nom,
                'date_debut' => $sm->date_debut->format('Y-m-d'),
            ]);

        $positions = $this->triPositionsOccupeesPuisVacantes(
            $shift->positions()
                ->with(['assignments' => fn ($q) => $q->where('statut', 'actif')
                    ->with(['servant.workflowSteps.workflowStep']),
                ])
                ->get()
        )->map(fn (ShiftPosition $position) => $this->formatePosition($position));

        return Inertia::render('Shifts/MonShift', [
            'shift' => [
                'id' => $shift->id,
                'nom' => $shift->nom,
                'jour' => $shift->jour,
                'heure_debut' => substr($shift->heure_debut, 0, 5),
                'heure_fin' => substr($shift->heure_fin, 0, 5),
                'statut' => $shift->statut,
            ],
            'membres' => $membres,
            'positions' => $positions,
            'estMonShift' => $estMonShift,
        ]);
    }
}
