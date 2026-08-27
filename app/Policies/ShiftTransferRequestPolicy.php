<?php

namespace App\Policies;

use App\Models\Shift;
use App\Models\ShiftTransferRequest;
use App\Models\User;

class ShiftTransferRequestPolicy extends Policy
{
    public function create(User $user, Shift $shift): bool
    {
        if (! $this->memeOrganisation($user, $shift)) {
            return false;
        }

        return $user->estAdministrateur() || $user->shiftsGeres()->contains($shift->id);
    }

    public function view(User $user, ShiftTransferRequest $shiftTransferRequest): bool
    {
        if (! $this->memeOrganisation($user, $shiftTransferRequest)) {
            return false;
        }

        return $user->estAdministrateur() || $user->shiftsGeres()->contains($shiftTransferRequest->shift_id);
    }

    /**
     * Le coordinateur peut compléter discussion/notes/approbation tant que la
     * demande n'a pas encore de résultat saisi. L'administrateur peut toujours.
     */
    public function update(User $user, ShiftTransferRequest $shiftTransferRequest): bool
    {
        if ($user->estAdministrateur()) {
            return $this->memeOrganisation($user, $shiftTransferRequest);
        }

        return $shiftTransferRequest->statut === 'en_attente'
            && $this->view($user, $shiftTransferRequest);
    }

    /**
     * Saisir le RÉSULTAT/DATE est réservé à l'administrateur de la même organisation.
     */
    public function resolve(User $user, ShiftTransferRequest $shiftTransferRequest): bool
    {
        return $user->estAdministrateur() && $this->memeOrganisation($user, $shiftTransferRequest);
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

        return $user->estAdministrateur() || $user->shiftsGeres()->contains($shiftTransferRequest->shift_id);
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

        return $user->estAdministrateur() || $user->shiftsGeres()->contains($shiftTransferRequest->shift_destination_id);
    }

    public function delete(User $user, ShiftTransferRequest $shiftTransferRequest): bool
    {
        return $user->estAdministrateur() && $this->memeOrganisation($user, $shiftTransferRequest);
    }
}
