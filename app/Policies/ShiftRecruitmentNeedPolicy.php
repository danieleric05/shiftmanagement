<?php

namespace App\Policies;

use App\Models\Shift;
use App\Models\User;

class ShiftRecruitmentNeedPolicy
{
    /**
     * Modifier le besoin de recrutement d'un shift est réservé à son coordinateur
     * (l'administrateur passe toujours via Gate::before).
     */
    public function update(User $user, Shift $shift): bool
    {
        return $user->organisation_id === $shift->organisation_id
            && $user->shiftsGeres()->contains($shift->id);
    }
}
