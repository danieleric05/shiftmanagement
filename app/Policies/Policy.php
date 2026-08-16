<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class Policy
{
    /**
     * Vérifie que le modèle appartient à la même organisation que l'utilisateur.
     * Doit être combiné à une vérification de rôle — ne garantit jamais l'accès à elle seule.
     */
    protected function memeOrganisation(User $user, Model $model): bool
    {
        return $user->organisation_id === $model->organisation_id;
    }
}
