<?php

namespace App\Policies;

use App\Models\Shift;
use App\Models\ShiftTransferRequest;
use App\Models\User;

class ShiftTransferRequestPolicy
{
    /**
     * L'administrateur passe toujours via Gate::before ; ici on ne statue que
     * sur le cas coordinateur, restreint au(x) shift(s) qu'il gère.
     */
    public function create(User $user, Shift $shift): bool
    {
        return $user->organisation_id === $shift->organisation_id
            && $user->shiftsGeres()->contains($shift->id);
    }

    public function view(User $user, ShiftTransferRequest $shiftTransferRequest): bool
    {
        return $user->organisation_id === $shiftTransferRequest->organisation_id
            && $user->shiftsGeres()->contains($shiftTransferRequest->shift_id);
    }

    /**
     * Le coordinateur peut compléter discussion/notes/approbation tant que la
     * demande n'a pas encore de résultat saisi.
     */
    public function update(User $user, ShiftTransferRequest $shiftTransferRequest): bool
    {
        return $shiftTransferRequest->statut === 'en_attente'
            && $this->view($user, $shiftTransferRequest);
    }

    /**
     * Saisir le RÉSULTAT/DATE est réservé à l'administrateur (Gate::before) —
     * un coordinateur n'y accède jamais.
     */
    public function resolve(User $user, ShiftTransferRequest $shiftTransferRequest): bool
    {
        return false;
    }

    public function delete(User $user, ShiftTransferRequest $shiftTransferRequest): bool
    {
        return false;
    }
}
