<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Suppression d'un compte par son titulaire uniquement. Le dernier administrateur ne peut pas
     * supprimer le sien : le site resterait sans personne pour attribuer les rôles.
     */
    public function delete(User $actor, User $account): Response
    {
        if (! $actor->is($account)) {
            return Response::denyAsNotFound();
        }

        if ($account->isLastAdmin()) {
            return Response::deny(__('account.errors.last_admin'));
        }

        return Response::allow();
    }
}
