<?php

namespace App\Policies;

use App\Models\Horaire;
use App\Models\User;

class HorairePolicy extends Policy
{
    public function update(User $user, Horaire $horaire): bool
    {
        return $user->estAdministrateur() && $this->memeOrganisation($user, $horaire);
    }

    public function delete(User $user, Horaire $horaire): bool
    {
        return $user->estAdministrateur() && $this->memeOrganisation($user, $horaire);
    }
}
