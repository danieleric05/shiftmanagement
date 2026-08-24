<?php

namespace App\Policies;

use App\Models\Interview;
use App\Models\Shift;
use App\Models\User;

class InterviewPolicy extends Policy
{
    public function create(User $user, Shift $shiftSouhaite): bool
    {
        if (! $this->memeOrganisation($user, $shiftSouhaite)) {
            return false;
        }

        return $user->estAdministrateurOuSecretaire() || $user->shiftsGeres()->contains($shiftSouhaite->id);
    }

    public function view(User $user, Interview $interview): bool
    {
        if (! $this->memeOrganisation($user, $interview)) {
            return false;
        }

        if ($user->estAdministrateurOuSecretaire()) {
            return true;
        }

        return $interview->shift_souhaite_id !== null
            && $user->shiftsGeres()->contains($interview->shift_souhaite_id);
    }

    /**
     * Saisir le résultat (et déclencher la conversion en Servant) est réservé
     * à l'administrateur de la même organisation.
     */
    public function resolve(User $user, Interview $interview): bool
    {
        return $user->estAdministrateur() && $this->memeOrganisation($user, $interview);
    }
}
