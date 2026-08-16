<?php

namespace App\Policies;

use App\Models\Shift;
use App\Models\User;

class ShiftRecruitmentNeedPolicy extends Policy
{
    /**
     * Modifier le besoin de recrutement d'un shift : administrateur de
     * l'organisation, ou coordinateur qui gère ce shift précis.
     */
    public function update(User $user, Shift $shift): bool
    {
        if (! $this->memeOrganisation($user, $shift)) {
            return false;
        }

        return $user->estAdministrateur() || $user->shiftsGeres()->contains($shift->id);
    }
}
