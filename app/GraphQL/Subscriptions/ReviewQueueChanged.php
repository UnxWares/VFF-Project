<?php

namespace App\GraphQL\Subscriptions;

use App\Models\User;
use Nuwave\Lighthouse\Subscriptions\Subscriber;

/**
 * `reviewQueueChanged` : tout changement de la file de validation (proposition reçue, approuvée,
 * publiée, refusée), réservé aux validateurs et aux administrateurs non suspendus, comme le canal
 * privé `review-queue`. Le droit est vérifié de nouveau à chaque diffusion, sur le compte relu dans
 * la base : un compte suspendu ou qui n'est plus validateur ne reçoit plus rien, sans attendre la fin
 * de vie de l'abonnement (un jour).
 */
final class ReviewQueueChanged extends ContributionSubscription
{
    protected function permits(Subscriber $subscriber): bool
    {
        return self::reviewer($this->account($subscriber));
    }

    public function filter(Subscriber $subscriber, mixed $root): bool
    {
        $user = $subscriber->context->user();

        return $user instanceof User && self::reviewer(User::query()->find($user->getKey()));
    }

    private static function reviewer(?User $account): bool
    {
        return $account !== null && $account->isReviewer() && ! $account->isSuspended();
    }
}
