<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\GovernanceRequest;
use App\Models\Interview;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftMember;
use App\Models\ShiftTransferRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    /**
     * Modèles journalisés à scoper par organisation. `Shift::class` sert de
     * proxy pour `ShiftMember`, qui n'a pas de colonne organisation_id propre.
     */
    private const MODELES_SCOPES = [
        Servant::class,
        Shift::class,
        GovernanceRequest::class,
        ShiftTransferRequest::class,
        Candidate::class,
        Interview::class,
    ];

    public function index(Request $request)
    {
        $organisationId = $request->user()->organisation_id;

        $idsParModele = collect(self::MODELES_SCOPES)->mapWithKeys(function (string $modele) use ($organisationId) {
            $query = method_exists($modele, 'bootSoftDeletes') ? $modele::withTrashed() : $modele::query();

            return [$modele => $query->where('organisation_id', $organisationId)->pluck('id')];
        });

        $shiftIdsOrganisation = $idsParModele->get(Shift::class, collect());
        $idsShiftMember = ShiftMember::whereIn('shift_id', $shiftIdsOrganisation)->pluck('id');
        $idsParModele->put(ShiftMember::class, $idsShiftMember);

        $activites = Activity::query()
            ->where(function ($query) use ($idsParModele) {
                foreach ($idsParModele as $modele => $ids) {
                    $query->orWhere(fn ($q) => $q->where('subject_type', $modele)->whereIn('subject_id', $ids));
                }
            })
            ->when($request->filled('recherche'), fn ($query) => $query->whereHasMorph(
                'causer',
                [User::class],
                fn ($q) => $q->where('name', 'like', '%'.$request->string('recherche').'%'),
            ))
            ->with('causer')
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Activity $activite) => [
                'id' => $activite->id,
                'modele' => class_basename($activite->subject_type),
                'sujet_id' => $activite->subject_id,
                'evenement' => $activite->event,
                'description' => $activite->description,
                'causeur' => $activite->causer?->name ?? 'Système',
                'proprietes' => $activite->properties,
                'date' => $activite->created_at->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Settings/ActivityLog/Index', [
            'activites' => $activites,
            'filtreRecherche' => $request->string('recherche')->toString(),
        ]);
    }
}
