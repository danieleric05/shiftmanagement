<?php

namespace App\Policies;

use App\Models\Candidate;
use App\Models\Shift;
use App\Models\User;

class CandidatePolicy extends Policy
{
    public function create(User $user, Shift $shiftSouhaite): bool
    {
        if (! $this->memeOrganisation($user, $shiftSouhaite)) {
            return false;
        }

        return $user->estAdministrateurOuSecretaire() || $user->shiftsGeres()->contains($shiftSouhaite->id);
    }

    public function update(User $user, Candidate $candidate): bool
    {
        if (! $this->memeOrganisation($user, $candidate)) {
            return false;
        }

        if ($candidate->shift_souhaite_id === null) {
            return true;
        }

        return $user->estAdministrateurOuSecretaire() || $user->shiftsGeres()->contains($candidate->shift_souhaite_id);
    }

    public function delete(User $user, Candidate $candidate): bool
    {
        return $user->estAdministrateur() && $this->memeOrganisation($user, $candidate);
    }
}
