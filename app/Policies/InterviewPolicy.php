<?php

namespace App\Policies;

use App\Models\Interview;
use App\Models\Shift;
use App\Models\User;

class InterviewPolicy
{
    public function create(User $user, Shift $shiftSouhaite): bool
    {
        return $user->organisation_id === $shiftSouhaite->organisation_id
            && $user->shiftsGeres()->contains($shiftSouhaite->id);
    }

    public function view(User $user, Interview $interview): bool
    {
        return $user->organisation_id === $interview->organisation_id
            && $interview->shift_souhaite_id !== null
            && $user->shiftsGeres()->contains($interview->shift_souhaite_id);
    }

    /**
     * Saisir le résultat (et déclencher la conversion en Servant) est réservé
     * à l'administrateur — géré uniquement par Gate::before.
     */
    public function resolve(User $user, Interview $interview): bool
    {
        return false;
    }
}
