<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Horaire;
use App\Models\Role;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftMember;
use App\Models\ShiftPosition;
use App\Models\ShiftTemplate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ShiftController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $shifts = Shift::where('organisation_id', $request->user()->organisation_id)
            ->withCount(['membresActifs as membres_count'])
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
                    'statut' => $shift->statut,
                    'membres_count' => $shift->membres_count,
                    'chef_equipe' => $shift->chefEquipe()?->name,
                ];
            });

        return Inertia::render('Shifts/Index', [
            'shifts' => $shifts,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $templates = ShiftTemplate::where('organisation_id', $request->user()->organisation_id)
            ->orderBy('nom')
            ->get(['id', 'nom']);

        $horaires = Horaire::where('organisation_id', $request->user()->organisation_id)
            ->orderBy('heure_debut')
            ->get()
            ->map(fn (Horaire $horaire) => [
                'id' => $horaire->id,
                'nom' => $horaire->nom,
                'heure_debut' => substr($horaire->heure_debut, 0, 5),
                'heure_fin' => substr($horaire->heure_fin, 0, 5),
            ]);

        return Inertia::render('Shifts/Create', [
            'templates' => $templates,
            'horaires' => $horaires,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'jour' => ['required', 'in:lundi,mardi,mercredi,jeudi,vendredi,samedi,dimanche'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i', 'after:heure_debut'],
            'shift_template_id' => [
                'nullable',
                Rule::exists('shift_templates', 'id')->where('organisation_id', $request->user()->organisation_id),
            ],
        ]);

        $validated['organisation_id'] = $request->user()->organisation_id;
        $validated['statut'] = 'actif';

        $shift = Shift::create($validated);

        if ($shift->shift_template_id) {
            $template = ShiftTemplate::findOrFail($shift->shift_template_id);

            foreach ($template->positions as $position) {
                $shift->positions()->create([
                    'shift_template_position_id' => $position->id,
                    'nom' => $position->nom,
                    'ordre' => $position->ordre,
                ]);
            }
        }

        return redirect()->route('shifts.show', $shift)->with('success', 'Shift créé avec succès.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Shift $shift)
    {
        $this->ensureSameOrganisation($request, $shift);

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
            ->whereNotIn('id', $membresActuelsIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $positions = $shift->positions()
            ->with(['assignments' => fn ($q) => $q->where('statut', 'actif')->with('servant')])
            ->get()
            ->map(function (ShiftPosition $position) {
                $assignment = $position->assignments->first();

                return [
                    'id' => $position->id,
                    'nom' => $position->nom,
                    'ordre' => $position->ordre,
                    'assignment_id' => $assignment?->id,
                    'titulaire' => $assignment ? [
                        'id' => $assignment->servant->id,
                        'nom_complet' => $assignment->servant->nomComplet(),
                        'depuis' => $assignment->date_debut->format('Y-m-d'),
                    ] : null,
                ];
            });

        $servantsDisponibles = Servant::where('organisation_id', $request->user()->organisation_id)
            ->where('statut', 'actif')
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
            'roles' => Role::orderBy('nom')->get(['id', 'nom', 'slug']),
            'positions' => $positions,
            'servantsDisponibles' => $servantsDisponibles,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Shift $shift)
    {
        $this->ensureSameOrganisation($request, $shift);

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
        $this->ensureSameOrganisation($request, $shift);

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
        $this->ensureSameOrganisation($request, $shift);

        $shift->delete();

        return redirect()->route('shifts.index')->with('success', 'Shift supprimé avec succès.');
    }

    /**
     * Affecter un membre au Shift (chapitre 3.4).
     */
    public function addMember(Request $request, Shift $shift)
    {
        $this->ensureSameOrganisation($request, $shift);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        ShiftMember::create([
            'shift_id' => $shift->id,
            'user_id' => $validated['user_id'],
            'role_id' => $validated['role_id'],
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
        $this->ensureSameOrganisation($request, $shift);

        $shiftMember->update([
            'date_fin' => now()->toDateString(),
            'statut' => 'termine',
        ]);

        return back()->with('success', 'Affectation terminée avec succès.');
    }

    /**
     * Ajouter un poste supplémentaire à ce Shift (au-delà de ceux du modèle).
     */
    public function storePosition(Request $request, Shift $shift)
    {
        $this->ensureSameOrganisation($request, $shift);

        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
        ]);

        $ordre = $shift->positions()->max('ordre') + 1;

        $shift->positions()->create([
            'nom' => $validated['nom'],
            'ordre' => $ordre,
        ]);

        return back()->with('success', 'Poste ajouté avec succès.');
    }

    /**
     * Retirer un poste ajouté manuellement à ce Shift.
     */
    public function destroyPosition(Request $request, Shift $shift, ShiftPosition $position)
    {
        $this->ensureSameOrganisation($request, $shift);

        abort_if($position->shift_id !== $shift->id, 404);

        $position->delete();

        return back()->with('success', 'Poste supprimé avec succès.');
    }

    /**
     * Affecter un servant à un poste du Shift.
     */
    public function assignServant(Request $request, Shift $shift, ShiftPosition $position)
    {
        $this->ensureSameOrganisation($request, $shift);

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
        $this->ensureSameOrganisation($request, $shift);

        abort_if($position->shift_id !== $shift->id, 404);
        abort_if($assignment->shift_position_id !== $position->id, 404);

        $assignment->update([
            'date_fin' => now()->toDateString(),
            'statut' => 'termine',
        ]);

        return back()->with('success', 'Affectation terminée avec succès.');
    }

    private function ensureSameOrganisation(Request $request, Shift $shift): void
    {
        abort_if($shift->organisation_id !== $request->user()->organisation_id, 403);
    }
}
