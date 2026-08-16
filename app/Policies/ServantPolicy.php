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

    public function update(User $user, Servant $servant): bool
    {
        return $user->estAdministrateur() && $this->memeOrganisation($user, $servant);
    }

    public function delete(User $user, Servant $servant): bool
    {
        return $user->estAdministrateur() && $this->memeOrganisation($user, $servant);
    }
}
