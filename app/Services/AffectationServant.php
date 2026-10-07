<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Servant;
use App\Models\Shift;
use App\Models\ShiftTemplatePosition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Règles d'affectation d'un servant à un nouveau poste d'un Shift, partagées
 * entre l'ajout d'un poste depuis la fiche du Shift (ShiftController::storePosition)
 * et la réintégration d'un servant relevé (ServantReintegrationController) :
 * genre du Shift, postes uniques (hiérarchie de coordination) et déplacement
 * d'un servant déjà présent sur ce Shift.
 */
class AffectationServant
{
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
     *
     * @return Collection<int, ShiftTemplatePosition>
     */
    public function postesDisponiblesPourShift(Shift $shift): Collection
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
                $genre = ShiftTemplatePosition::genreDuNom($poste->nom);

                return $genre === null || $genre === ($estSoeurs ? ShiftTemplatePosition::GENRE_SOEURS : ShiftTemplatePosition::GENRE_FRERES);
            })
            ->reject(fn (ShiftTemplatePosition $poste) => ! in_array($poste->nom, ['Servant', 'Servante'], true)
                && $idsDejaPresents->contains($poste->id))
            ->values();
    }

    /**
     * Shifts de l'organisation et postes proposables sur chacun (formulaire
     * de réintégration) : le genre du shift permet à l'interface de ne
     * proposer que les shifts compatibles avec le servant, le serveur
     * revérifiant tout dans affecterANouveauPoste().
     *
     * @return list<array{id: int, nom: string, genre: string, postes: list<array{id: int, nom: string}>}>
     */
    public function optionsPourOrganisation(int $organisationId): array
    {
        return Shift::where('organisation_id', $organisationId)
            ->orderByJourCalendrier()
            ->get()
            ->map(fn (Shift $shift) => [
                'id' => $shift->id,
                'nom' => $shift->nom,
                'genre' => $shift->genreAttendu(),
                'postes' => $this->postesDisponiblesPourShift($shift)
                    ->map(fn (ShiftTemplatePosition $poste) => ['id' => $poste->id, 'nom' => $poste->nom])
                    ->values()
                    ->all(),
            ])
            ->filter(fn (array $shift) => $shift['postes'] !== [])
            ->values()
            ->all();
    }

    /**
     * Crée le poste choisi (issu du modèle du Shift) et y affecte le servant.
     * Le servant puis le Shift sont verrouillés (toujours dans cet ordre) le
     * temps de l'opération : deux affectations concurrentes ne peuvent ni
     * créer deux fois un même poste unique, ni donner deux affectations
     * actives simultanées au même servant.
     * Refuse (422) un poste non proposé pour ce Shift ou un genre incompatible.
     */
    public function affecterANouveauPoste(Shift $shift, Servant $servant, int $shiftTemplatePositionId): Assignment
    {
        return DB::transaction(function () use ($shift, $servant, $shiftTemplatePositionId) {
            $servant = Servant::whereKey($servant->getKey())->lockForUpdate()->firstOrFail();
            $shift = Shift::whereKey($shift->getKey())->lockForUpdate()->firstOrFail();

            $templatePosition = $this->postesDisponiblesPourShift($shift)
                ->firstWhere('id', $shiftTemplatePositionId);

            abort_if($templatePosition === null, 422, "Ce poste n'est pas proposé pour ce Shift.");

            $shift->assurerGenreCompatible($servant);

            // Le servant occupe peut-être déjà un poste sur ce Shift : on le
            // déplace vers le nouveau rôle plutôt que de créer une seconde
            // affectation active. Le poste quitté n'est pas laissé vacant
            // (même règle que endAssignment), il disparaît.
            $ancienneAffectation = Assignment::whereIn('shift_position_id', $shift->positions()->pluck('id'))
                ->where('servant_id', $servant->id)
                ->where('statut', 'actif')
                ->first();

            if ($ancienneAffectation) {
                $ancienneAffectation->update(['statut' => 'termine', 'date_fin' => now()->toDateString()]);
                $ancienneAffectation->shiftPosition->delete();
            }

            $position = $shift->positions()->create([
                'shift_template_position_id' => $templatePosition->id,
                'nom' => $templatePosition->nom,
                'ordre' => $templatePosition->ordre,
            ]);

            return Assignment::create([
                'shift_position_id' => $position->id,
                'servant_id' => $servant->id,
                'date_debut' => now()->toDateString(),
                'statut' => 'actif',
            ]);
        });
    }
}
