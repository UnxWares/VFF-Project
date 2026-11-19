<?php

namespace App\Policies;

use App\Enums\UserRole;
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

        if ($account->isAdmin() && User::query()->where('role', UserRole::Admin->value)->count() === 1) {
            return Response::deny(__('account.errors.last_admin'));
        }

        return Response::allow();
    }
}
