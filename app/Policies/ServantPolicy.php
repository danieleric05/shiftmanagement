<?php

namespace App\Policies;

use App\Models\Servant;
use App\Models\User;

class ServantPolicy extends Policy
{
    public function view(User $user, Servant $servant): bool
    {
        return $user->estAdministrateur() && $this->memeOrganisation($user, $servant);
    }

    /**
     * Consultation en lecture seule du parcours par le chef d'équipe d'un
     * shift où ce servant a une affectation active (cf. shiftsGeres()).
     */
    public function viewMine(User $user, Servant $servant): bool
    {
        if (! $this->memeOrganisation($user, $servant)) {
            return false;
        }

        if ($user->estAdministrateur()) {
            return true;
        }

        $shiftIds = $servant->assignationsActives()->with('shiftPosition')->get()
            ->pluck('shiftPosition.shift_id');

        return $shiftIds->intersect($user->shiftsGeres())->isNotEmpty();
    }

    public function update(User $user, Servant $servant): bool
    {
        return $user->estAdministrateur() && $this->memeOrganisation($user, $servant);
    }

    public function delete(User $user, Servant $servant): bool
    {
        return $user->estAdministrateur() && $this->memeOrganisation($user, $servant);
    }
}
