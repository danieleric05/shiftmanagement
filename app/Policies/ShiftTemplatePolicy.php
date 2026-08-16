<?php

namespace App\Policies;

use App\Models\ShiftTemplate;
use App\Models\User;

class ShiftTemplatePolicy extends Policy
{
    public function view(User $user, ShiftTemplate $shiftTemplate): bool
    {
        return $user->estAdministrateur() && $this->memeOrganisation($user, $shiftTemplate);
    }

    public function update(User $user, ShiftTemplate $shiftTemplate): bool
    {
        return $user->estAdministrateur() && $this->memeOrganisation($user, $shiftTemplate);
    }

    public function delete(User $user, ShiftTemplate $shiftTemplate): bool
    {
        return $user->estAdministrateur() && $this->memeOrganisation($user, $shiftTemplate);
    }
}
