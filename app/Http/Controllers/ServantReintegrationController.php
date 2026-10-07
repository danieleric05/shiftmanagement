<?php

namespace App\Http\Controllers;

use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftTransferRequest;
use App\Services\AffectationServant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Réintégration (« retour ») d'un servant relevé, réservée au Conseil du
 * Temple (administrateur / super administrateur).
 *
 * Une relève (ShiftTransferRequest de type « releve », statut « traitee »)
 * termine les affectations actives du servant sur le shift d'origine et
 * supprime les postes ainsi libérés ; elle ne modifie pas le statut du
 * servant, que le Conseil peut en outre passer à « Relevé » (valeur technique
 * `suspendu`). Est « relevé » le servant qui a une relève non réintégrée OU
 * le statut « Relevé » (Servant::estReleve()). La réintégration en est
 * l'inverse, sans rien effacer :
 *  - chaque relève non réintégrée est marquée (date, auteur, commentaire) et
 *    reste dans l'historique (page « Servant(e)s relevé(e)s », fiche) ;
 *  - le servant peut, en option, être replacé sur un poste d'un shift (mêmes
 *    règles que l'ajout d'un poste depuis la fiche du shift : genre, postes
 *    uniques) ;
 *  - un statut « Relevé » (`suspendu`) ou « Permutant » (`retire`) repasse à
 *    « Ancien » (`actif`) si le parcours est terminé, sinon à « En
 *    formation » ; les autres statuts sont conservés ;
 *  - une note datée est ajoutée à la fiche et l'action est journalisée.
 */
class ServantReintegrationController extends Controller
{
    public function store(Request $request, Servant $servant, AffectationServant $affectation)
    {
        $this->authorize('reintegrate', $servant);

        $validated = $request->validate([
            'shift_id' => [
                'nullable',
                Rule::exists('shifts', 'id')->where('organisation_id', $servant->organisation_id)->whereNull('deleted_at'),
            ],
            'shift_template_position_id' => ['nullable', 'required_with:shift_id', 'integer'],
            'commentaire' => ['nullable', 'string', 'max:1000'],
        ], [
            'shift_id.exists' => 'Veuillez choisir un shift de votre organisation.',
            'shift_template_position_id.required_with' => 'Veuillez choisir le poste sur lequel replacer le servant(e).',
        ]);

        $user = $request->user();

        $message = DB::transaction(function () use ($servant, $validated, $user, $affectation) {
            // Verrou sur la fiche puis relecture de l'état : deux réintégrations
            // concurrentes ne peuvent pas toutes deux aboutir.
            $verrou = Servant::whereKey($servant->getKey())->lockForUpdate()->firstOrFail();

            $releves = ShiftTransferRequest::where('servant_id', $verrou->id)
                ->releveeNonReintegree()
                ->with('shift')
                ->lockForUpdate()
                ->get();

            abort_if(
                $releves->isEmpty() && $verrou->statut !== 'suspendu',
                422,
                "Ce servant(e) n'est pas relevé(e) : aucune relève en cours ni statut « Relevé » (il ou elle a peut-être déjà été réintégré(e))."
            );

            $commentaire = $validated['commentaire'] ?? null;

            foreach ($releves as $releve) {
                $releve->update([
                    'reintegre_le' => now(),
                    'reintegre_par_id' => $user->id,
                    'reintegration_commentaire' => $commentaire,
                ]);
            }

            $affectationCreee = null;
            $shift = null;

            if (! empty($validated['shift_id'])) {
                $shift = Shift::findOrFail($validated['shift_id']);
                $affectationCreee = $affectation->affecterANouveauPoste($shift, $verrou, (int) $validated['shift_template_position_id']);
            }

            $ancienStatut = $verrou->statut;
            $nouveauStatut = $this->statutApresReintegration($verrou);
            $poste = $affectationCreee?->shiftPosition?->nom;

            $origine = $releves->isEmpty()
                ? 'statut « Relevé »'
                : 'relève du shift '.$releves->map(fn (ShiftTransferRequest $r) => '« '.($r->shift?->nom ?? '—').' »')->unique()->implode(', ');

            $note = sprintf(
                '[%s] Réintégré(e) par %s (%s)%s.%s',
                now()->format('d/m/Y'),
                $user->name,
                $origine,
                $shift ? " — replacé(e) sur le poste « {$poste} » du shift « {$shift->nom} »" : '',
                $commentaire ? " Commentaire : {$commentaire}" : ''
            );

            $verrou->update([
                'statut' => $nouveauStatut,
                'notes' => trim(($verrou->notes ? $verrou->notes."\n" : '').$note),
            ]);

            activity()
                ->performedOn($verrou)
                ->causedBy($user)
                ->event('reintegration')
                ->withProperties([
                    'releves' => $releves->pluck('id')->all(),
                    'ancien_statut' => $ancienStatut,
                    'nouveau_statut' => $nouveauStatut,
                    'shift_id' => $shift?->id,
                    'assignment_id' => $affectationCreee?->id,
                ])
                ->log('Réintégration du servant relevé');

            return $shift
                ? "Servant(e) réintégré(e) et replacé(e) sur le poste « {$poste} » du shift « {$shift->nom} »."
                : 'Servant(e) réintégré(e). Vous pouvez maintenant l\'affecter à un poste depuis la fiche d\'un shift.';
        });

        return back()->with('success', $message);
    }

    /**
     * Statut cohérent après réintégration. La relève ne change pas le statut
     * du servant ; seul un servant mis à l'écart (« Relevé » = `suspendu`,
     * « Permutant » = `retire`) est remis en service : « Ancien » (valeur
     * technique `actif`) si son parcours est terminé — même condition que
     * ServantController::ensureWorkflowComplete() —, « En formation » sinon.
     */
    private function statutApresReintegration(Servant $servant): string
    {
        if (! in_array($servant->statut, ['retire', 'suspendu'], true)) {
            return $servant->statut;
        }

        $parcoursIncomplet = $servant->workflowSteps()
            ->whereIn('statut', ['en_attente', 'en_cours'])
            ->exists();

        return $parcoursIncomplet ? 'en_formation' : 'actif';
    }
}
