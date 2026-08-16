<?php

namespace App\Policies;

use App\Models\Shift;
use App\Models\User;

class ShiftPolicy
{
    /**
     * Consultation en lecture seule du roster d'un shift par son coordinateur
     * (l'administrateur passe systématiquement via Gate::before).
     */
    public function view(User $user, Shift $shift): bool
    {
        return $user->organisation_id === $shift->organisation_id
            && $user->shiftsGeres()->contains($shift->id);
    }
}
