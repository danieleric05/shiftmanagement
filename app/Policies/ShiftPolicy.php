<?php

namespace App\Policies;

use App\Models\Shift;
use App\Models\User;

class ShiftPolicy extends Policy
{
    /**
     * Consultation du roster d'un shift : l'administrateur voit tout, et tout
     * chef d'équipe peut consulter n'importe quel shift de son organisation
     * — en lecture seule pour ceux qu'il ne gère pas (cf. shiftsGeres()), qui
     * reste le critère de modification.
     */
    public function view(User $user, Shift $shift): bool
    {
        if (! $this->memeOrganisation($user, $shift)) {
            return false;
        }

        if ($user->estAdministrateur()) {
            return true;
        }

        return $user->role?->slug === 'chef_equipe';
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
