<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftMember;
use App\Models\ShiftPosition;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
                    'genre' => Str::contains(Str::lower($shift->nom), ['sœur', 'soeur']) ? 'soeurs' : 'freres',
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

        $membres = $shift->membresActifs()
            ->with(['user', 'role'])
            ->get()
            ->map(fn (ShiftMember $sm) => [
                'affectation_id' => $sm->id,
                'user_id' => $sm->user->id,
                'name' => $sm->user->name,
                'email' => $sm->user->email,
                'role' => $sm->role->nom,
                'date_debut' => $sm->date_debut->format('Y-m-d'),
            ]);

        $membresActuelsIds = $shift->membresActifs()->pluck('user_id');

        $membresDisponibles = User::where('organisation_id', $request->user()->organisation_id)
            ->whereNotNull('role_id')
            ->whereNotIn('id', $membresActuelsIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $positions = $shift->positions()
            ->with(['assignments' => fn ($q) => $q->where('statut', 'actif')
                ->with(['servant.workflowSteps.workflowStep']),
            ])
            ->get()
            ->map(fn (ShiftPosition $position) => $this->formatePosition($position));

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

        return Inertia::render('Shifts/Show', [
            'shift' => [
                'id' => $shift->id,
                'nom' => $shift->nom,
                'jour' => $shift->jour,
                'heure_debut' => substr($shift->heure_debut, 0, 5),
                'heure_fin' => substr($shift->heure_fin, 0, 5),
                'statut' => $shift->statut,
            ],
            'membres' => $membres,
            'membresDisponibles' => $membresDisponibles,
            'positions' => $positions,
            'servantsDisponibles' => $servantsDisponibles,
        ]);
    }

    /**
     * Formate un poste et son titulaire pour la fiche shift, avec les étapes
     * clés du parcours (protection de la jeunesse, badge, photo, orientation,
     * formation) attendues sur le roster (cahier des charges "TABLEAU DE BORD").
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
        $termine = fn (string $cle) => $parCle->get($cle)?->statut === 'termine';

        return [
            'protection_jeunesse' => $termine('youth_protection'),
            'badge' => $termine('badge'),
            'photo' => $termine('photo'),
            'orientation' => $termine('orientation'),
            'formation' => $termine('formation'),
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
     * Mettre fin à l'affectation d'un servant sur un poste.
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

        return back()->with('success', 'Affectation terminée avec succès.');
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

        $positions = $shift->positions()
            ->with(['assignments' => fn ($q) => $q->where('statut', 'actif')
                ->with(['servant.workflowSteps.workflowStep']),
            ])
            ->get()
            ->map(fn (ShiftPosition $position) => $this->formatePosition($position));

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
