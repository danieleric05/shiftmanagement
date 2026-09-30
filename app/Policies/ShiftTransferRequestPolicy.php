<?php

namespace App\Policies;

use App\Models\Shift;
use App\Models\ShiftTransferRequest;
use App\Models\User;

class ShiftTransferRequestPolicy extends Policy
{
    /**
     * Le coordonnateur d'équipe ne gère que les permutations : relèves et
     * appels sont réservés à l'administrateur et au secrétaire.
     */
    public static function typeAccessible(User $user, ?string $type): bool
    {
        return $user->gereServantsEtPermutations() || $type === 'permutation';
    }

    public function create(User $user, Shift $shift, ?string $type = null): bool
    {
        if (! $this->memeOrganisation($user, $shift) || ! self::typeAccessible($user, $type)) {
            return false;
        }

        return $user->gereServantsEtPermutations() || $user->shiftsGeres()->contains($shift->id);
    }

    public function view(User $user, ShiftTransferRequest $shiftTransferRequest): bool
    {
        if (! $this->memeOrganisation($user, $shiftTransferRequest) || ! self::typeAccessible($user, $shiftTransferRequest->type)) {
            return false;
        }

        return $user->gereServantsEtPermutations() || $user->shiftsGeres()->contains($shiftTransferRequest->shift_id);
    }

    /**
     * Le coordinateur peut compléter discussion/notes/approbation tant que la
     * demande n'a pas encore de résultat saisi. L'administrateur peut toujours.
     */
    public function update(User $user, ShiftTransferRequest $shiftTransferRequest): bool
    {
        if ($user->gereServantsEtPermutations()) {
            return $this->memeOrganisation($user, $shiftTransferRequest);
        }

        return $shiftTransferRequest->statut === 'en_attente'
            && $this->view($user, $shiftTransferRequest);
    }

    /**
     * Saisir le RÉSULTAT/DATE est réservé à l'administrateur (ou au secrétaire) de la même organisation.
     */
    public function resolve(User $user, ShiftTransferRequest $shiftTransferRequest): bool
    {
        return $user->gereServantsEtPermutations() && $this->memeOrganisation($user, $shiftTransferRequest);
    }

    /**
     * Validation par le coordonnateur du shift d'ORIGINE, réservée aux permutations en attente.
     */
    public function validerOrigine(User $user, ShiftTransferRequest $shiftTransferRequest): bool
    {
        if ($shiftTransferRequest->type !== 'permutation' || $shiftTransferRequest->statut !== 'en_attente') {
            return false;
        }

        if (! $this->memeOrganisation($user, $shiftTransferRequest)) {
            return false;
        }

        return $user->gereServantsEtPermutations() || $user->shiftsGeres()->contains($shiftTransferRequest->shift_id);
    }

    /**
     * Validation par le coordonnateur du shift de DESTINATION, réservée aux permutations en attente.
     */
    public function validerDestination(User $user, ShiftTransferRequest $shiftTransferRequest): bool
    {
        if ($shiftTransferRequest->type !== 'permutation' || $shiftTransferRequest->statut !== 'en_attente') {
            return false;
        }

        if (! $this->memeOrganisation($user, $shiftTransferRequest)) {
            return false;
        }

        return $user->gereServantsEtPermutations() || $user->shiftsGeres()->contains($shiftTransferRequest->shift_destination_id);
    }

    public function delete(User $user, ShiftTransferRequest $shiftTransferRequest): bool
    {
        return $user->gereServantsEtPermutations() && $this->memeOrganisation($user, $shiftTransferRequest);
    }
}
