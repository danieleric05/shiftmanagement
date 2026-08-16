<?php

namespace App\Policies;

use App\Models\GovernanceRequest;
use App\Models\User;

class GovernanceRequestPolicy extends Policy
{
    /**
     * Valider/rejeter une demande (les deux actions du contrôleur relèvent
     * de la même autorisation : administrateur de l'organisation concernée).
     */
    public function update(User $user, GovernanceRequest $governanceRequest): bool
    {
        return $user->estAdministrateur() && $this->memeOrganisation($user, $governanceRequest);
    }
}
