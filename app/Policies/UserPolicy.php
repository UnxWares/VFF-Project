<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /** Liste des comptes de l'espace de modération : administrateurs seulement. */
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin() && ! $actor->isSuspended();
    }

    /**
     * Suppression d'un compte par son titulaire uniquement. Le dernier administrateur ne peut pas
     * supprimer le sien : le site resterait sans personne pour attribuer les rôles. Un compte
     * suspendu peut être supprimé (droit à l'effacement).
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

    /**
     * Suspension d'un compte (CGU, « Validation et modération ») : un administrateur, jamais
     * contre lui-même ni contre le dernier administrateur, sinon plus personne ne pourrait lever
     * une suspension ni attribuer les rôles.
     */
    public function suspend(User $actor, User $account): Response
    {
        if (! $actor->isAdmin() || $actor->isSuspended()) {
            return Response::deny();
        }

        if ($actor->is($account)) {
            return Response::deny(__('moderation.errors.self'));
        }

        if ($account->isLastAdmin()) {
            return Response::deny(__('moderation.errors.last_admin'));
        }

        return Response::allow();
    }

    /** Levée d'une suspension avant son terme : un administrateur. */
    public function lift(User $actor, User $account): Response
    {
        return $actor->isAdmin() && ! $actor->isSuspended() ? Response::allow() : Response::deny();
    }
}
