<?php

namespace App\Policies;

use App\Models\Shift;
use App\Models\User;

class ShiftPolicy extends Policy
{
    /**
     * Consultation en lecture seule du roster d'un shift : l'administrateur de
     * l'organisation, ou le coordinateur qui gère ce shift précis.
     */
    public function view(User $user, Shift $shift): bool
    {
        if (! $this->memeOrganisation($user, $shift)) {
            return false;
        }

        return $user->estAdministrateur() || $user->shiftsGeres()->contains($shift->id);
    }

    public function update(User $user, Shift $shift): bool
    {
        return $user->estAdministrateur() && $this->memeOrganisation($user, $shift);
    }

    public function delete(User $user, Shift $shift): bool
    {
        return $user->estAdministrateur() && $this->memeOrganisation($user, $shift);
    }
}
