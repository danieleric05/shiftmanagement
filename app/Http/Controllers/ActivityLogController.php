<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftMember;
use App\Models\ShiftTransferRequest;
use App\Models\User;
use App\Support\TriServeur;
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
        ShiftTransferRequest::class,
    ];

    /**
     * Libellés affichés des évènements (repris de la page) : le tri par
     * « Action » suit l'ordre alphabétique de ces libellés, pas des codes.
     */
    private const LIBELLES_EVENEMENTS = [
        'created' => 'Création',
        'updated' => 'Modification',
        'deleted' => 'Suppression',
        'restored' => 'Restauration',
        'changement_statut_compte' => 'Statut du compte',
        'blocage_acces_compte' => 'Accès suspendu',
        'deblocage_acces_compte' => 'Accès rétabli',
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

        $tri = $this->triActivites($request);

        $activites = Activity::query()
            ->where(function ($query) use ($idsParModele, $organisationId) {
                foreach ($idsParModele as $modele => $ids) {
                    $query->orWhere(fn ($q) => $q->where('subject_type', $modele)->whereIn('subject_id', $ids));
                }

                // Audits rattachés à l'organisation elle-même (ex. suppression
                // définitive d'un servant, dont la fiche n'existe plus).
                $query->orWhere(fn ($q) => $q->where('subject_type', Organisation::class)->where('subject_id', $organisationId));
            })
            ->when($request->filled('recherche'), fn ($query) => $query->whereHasMorph(
                'causer',
                [User::class],
                fn ($q) => $q->where('name', 'like', '%'.$request->string('recherche').'%'),
            ))
            ->with('causer');

        $activites = $tri->appliquer($activites)
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

        if ($redirection = $this->redirigerSiPageHorsLimites($activites, $request)) {
            return $redirection;
        }

        return Inertia::render('Settings/ActivityLog/Index', [
            'activites' => $activites,
            'filtreRecherche' => $request->string('recherche')->toString(),
            // Sans tri demandé, l'ordre par défaut (date décroissante) est
            // présenté comme un tri « Date ↓ » pour l'en-tête (aria-sort).
            'tri' => $tri->cle === null ? ['cle' => 'date', 'sens' => 'desc'] : $tri->versProps(),
        ]);
    }

    /**
     * Tri serveur du journal (liste blanche) : Date, Action (libellé affiché),
     * Sur quoi (modèle puis identifiant), Par qui (nom de l'auteur, « Système »
     * à défaut). Ordre par défaut : date décroissante (la plus récente
     * d'abord, y compris à la seconde près), aussi utilisé comme départage.
     */
    private function triActivites(Request $request): TriServeur
    {
        $table = (new Activity)->getTable();

        return TriServeur::depuisRequete($request, [
            // Deux activités de la même seconde : la plus récemment créée
            // (identifiant le plus grand) suit le sens demandé.
            'date' => function ($q, string $sens) use ($table) {
                $q->orderBy("{$table}.created_at", $sens)->orderBy("{$table}.id", $sens);
            },
            'action' => function ($q, string $sens) use ($table) {
                $cas = collect(self::LIBELLES_EVENEMENTS)->map(fn () => 'WHEN ? THEN ?')->implode(' ');
                $liaisons = collect(self::LIBELLES_EVENEMENTS)->flatMap(fn (string $libelle, string $code) => [$code, $libelle])->all();

                // `$sens` provient de la liste blanche TriServeur::SENS.
                $q->orderByRaw("CASE {$table}.event {$cas} ELSE {$table}.event END {$sens}", $liaisons);
            },
            'sujet' => function ($q, string $sens) use ($table) {
                $q->orderBy("{$table}.subject_type", $sens)->orderBy("{$table}.subject_id", $sens);
            },
            'auteur' => function ($q, string $sens) use ($table) {
                $nom = User::select('name')
                    ->whereColumn('users.id', "{$table}.causer_id")
                    ->where("{$table}.causer_type", User::class)
                    ->limit(1);

                $q->orderByRaw("COALESCE(({$nom->toSql()}), ?) {$sens}", [...$nom->getBindings(), 'Système']);
            },
        ], parDefaut: [["{$table}.created_at", 'desc'], ["{$table}.id", 'desc']], clePrimaire: "{$table}.id");
    }
}
