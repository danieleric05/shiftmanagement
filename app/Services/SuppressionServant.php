<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Organisation;
use App\Models\Servant;
use App\Models\ServantWorkflowStep;
use App\Models\ShiftPosition;
use App\Models\ShiftTransferRequest;
use App\Models\User;
use App\Notifications\DemandeTransfertResolue;
use App\Notifications\NouvelleDemandeTransfert;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

/**
 * Suppression DÉFINITIVE d'un servant (correction d'une erreur de saisie par
 * le Conseil du Temple, ex. un membre du Conseil inscrit par erreur comme
 * servant). Contrairement à l'anonymisation (RGPD), rien n'est conservé :
 * fiche, affectations, parcours, demandes de changement (relèves,
 * permutations, appels), notifications et entrées de journal qui les
 * concernent. Le compte de connexion éventuellement lié n'est PAS supprimé
 * (il appartient au membre) : seul le lien disparaît avec la fiche.
 */
class SuppressionServant
{
    /**
     * Nombre d'entrées liées qui seront effacées (affiché dans la confirmation
     * puis consigné, sans donnée personnelle, dans le journal d'audit).
     *
     * @return array{affectations: int, etapes_parcours: int, releves: int, permutations: int, appels: int, demandes_changement: int, notifications: int, entrees_journal: int}
     */
    public function bilan(Servant $servant): array
    {
        $demandes = ShiftTransferRequest::withTrashed()->where('servant_id', $servant->id);
        $demandeIds = (clone $demandes)->pluck('id');

        return [
            'affectations' => Assignment::where('servant_id', $servant->id)->count(),
            'etapes_parcours' => ServantWorkflowStep::where('servant_id', $servant->id)->count(),
            'releves' => (clone $demandes)->where('type', 'releve')->count(),
            'permutations' => (clone $demandes)->where('type', 'permutation')->count(),
            'appels' => (clone $demandes)->where('type', 'appel')->count(),
            'demandes_changement' => $demandeIds->count(),
            'notifications' => $this->notifications($demandeIds)->count(),
            'entrees_journal' => $this->entreesJournal($servant->id, Assignment::where('servant_id', $servant->id)->pluck('id'), $demandeIds)->count(),
        ];
    }

    /**
     * Supprime le servant et tout ce qui le concerne, de façon atomique (la
     * fiche est verrouillée : deux suppressions concurrentes ne peuvent pas
     * toutes deux aboutir). La photo est effacée du stockage une fois la
     * transaction validée.
     *
     * @return array{compte_lie: bool, bilan: array<string, int>}
     */
    public function supprimer(Servant $servant, User $auteur): array
    {
        $resultat = DB::transaction(function () use ($servant, $auteur) {
            $verrou = Servant::withTrashed()->whereKey($servant->getKey())->lockForUpdate()->first();
            abort_if($verrou === null, 404, 'Ce servant(e) a déjà été supprimé(e).');

            $bilan = $this->bilan($verrou);

            $assignmentIds = Assignment::where('servant_id', $verrou->id)->pluck('id');
            $postesOccupes = Assignment::where('servant_id', $verrou->id)->where('statut', 'actif')->pluck('shift_position_id');
            $demandeIds = ShiftTransferRequest::withTrashed()->where('servant_id', $verrou->id)->pluck('id');

            // Les entrées de journal et notifications liées contiennent des
            // données personnelles (nom, téléphone…) : elles disparaissent avec
            // la fiche. Les suppressions passent par le query builder (aucun
            // événement de modèle) : aucune nouvelle entrée de journal ne
            // recopie les données effacées.
            $this->entreesJournal($verrou->id, $assignmentIds, $demandeIds)->delete();
            $this->notifications($demandeIds)->delete();

            Assignment::whereIn('id', $assignmentIds)->delete();
            // Un poste ne survit pas à son occupant (même règle que
            // ShiftController::endAssignment) : suppression douce.
            ShiftPosition::whereIn('id', $postesOccupes)->delete();
            ShiftTransferRequest::withTrashed()->whereIn('id', $demandeIds)->forceDelete();
            ServantWorkflowStep::where('servant_id', $verrou->id)->delete();

            $photo = $verrou->photo;
            $compteLie = $verrou->user_id !== null;

            Servant::withTrashed()->whereKey($verrou->id)->forceDelete();

            // Audit SANS donnée personnelle : identifiant technique,
            // organisation, auteur (causer), date (created_at) et volumes.
            activity()
                ->performedOn(Organisation::findOrFail($verrou->organisation_id))
                ->causedBy($auteur)
                ->event('suppression_definitive_servant')
                ->withProperties([
                    'servant_id' => $verrou->id,
                    'organisation_id' => $verrou->organisation_id,
                    'compte_utilisateur_conserve' => $compteLie,
                    'photo_supprimee' => $photo !== null,
                    'entrees_supprimees' => $bilan,
                ])
                ->log("Suppression définitive du servant #{$verrou->id}");

            return ['photo' => $photo, 'compte_lie' => $compteLie, 'bilan' => $bilan];
        });

        if ($resultat['photo']) {
            Storage::disk('local')->delete($resultat['photo']);
        }

        return ['compte_lie' => $resultat['compte_lie'], 'bilan' => $resultat['bilan']];
    }

    /**
     * @param  Collection<int, int>  $assignmentIds
     * @param  Collection<int, int>  $demandeIds
     */
    private function entreesJournal(int $servantId, Collection $assignmentIds, Collection $demandeIds): Builder
    {
        return Activity::query()->where(function ($query) use ($servantId, $assignmentIds, $demandeIds) {
            $query->where(fn ($q) => $q->where('subject_type', Servant::class)->where('subject_id', $servantId))
                ->orWhere(fn ($q) => $q->where('subject_type', Assignment::class)->whereIn('subject_id', $assignmentIds))
                ->orWhere(fn ($q) => $q->where('subject_type', ShiftTransferRequest::class)->whereIn('subject_id', $demandeIds));
        });
    }

    /**
     * @param  Collection<int, int>  $demandeIds
     */
    private function notifications(Collection $demandeIds): Builder
    {
        return DatabaseNotification::query()
            ->whereIn('type', [NouvelleDemandeTransfert::class, DemandeTransfertResolue::class])
            ->whereIn('data->shift_transfer_request_id', $demandeIds->all());
    }
}
