<?php

namespace App\GraphQL\Subscriptions;

use Nuwave\Lighthouse\Subscriptions\Subscriber;

/**
 * `reviewQueueChanged` : tout changement de la file de validation (proposition reçue, approuvée,
 * publiée, refusée), réservé aux validateurs et aux administrateurs non suspendus, comme le canal
 * privé `review-queue`.
 */
final class ReviewQueueChanged extends ContributionSubscription
{
    public function can(Subscriber $subscriber): bool
    {
        $account = $this->account($subscriber);

        return parent::can($subscriber) && $account !== null && $account->isReviewer() && ! $account->isSuspended();
    }

    public function filter(Subscriber $subscriber, mixed $root): bool
    {
        return true;
    }
}
