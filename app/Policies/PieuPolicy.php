<?php

namespace App\Policies;

use App\Models\Pieu;
use App\Models\User;

class PieuPolicy extends Policy
{
    public function update(User $user, Pieu $pieu): bool
    {
        return $user->estAdministrateur() && $this->memeOrganisation($user, $pieu);
    }

    public function delete(User $user, Pieu $pieu): bool
    {
        return $user->estAdministrateur() && $this->memeOrganisation($user, $pieu);
    }
}
