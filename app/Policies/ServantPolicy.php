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

    /**
     * Édition complète (sauf gestion du compte de connexion, réservée à
     * l'administrateur) par l'administrateur ou par le chef d'équipe d'un
     * shift où ce servant a une affectation active.
     */
    public function update(User $user, Servant $servant): bool
    {
        return $this->viewMine($user, $servant);
    }

    public function delete(User $user, Servant $servant): bool
    {
        return $user->estAdministrateur() && $this->memeOrganisation($user, $servant);
    }

    public function anonymize(User $user, Servant $servant): bool
    {
        return $user->estAdministrateur() && $this->memeOrganisation($user, $servant);
    }

    public function export(User $user, Servant $servant): bool
    {
        return $user->estAdministrateur() && $this->memeOrganisation($user, $servant);
    }

    /**
     * Gestion du compte de connexion (création/révocation) — toujours réservée
     * à l'administrateur, même si le chef d'équipe peut désormais éditer le
     * reste de la fiche du servant.
     */
    public function manageAccount(User $user, Servant $servant): bool
    {
        return $user->estAdministrateur() && $this->memeOrganisation($user, $servant);
    }
}
