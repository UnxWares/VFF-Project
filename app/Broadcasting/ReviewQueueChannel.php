<?php

namespace App\Broadcasting;

use App\Models\User;

/**
 * Canal privé `review-queue` : la file de validation en direct. Réservé aux validateurs et aux
 * administrateurs dont le compte n'est pas suspendu, comme l'espace `/validate`.
 */
final class ReviewQueueChannel
{
    public const NAME = 'review-queue';

    public function join(User $user): bool
    {
        return $user->isReviewer() && ! $user->isSuspended();
    }
}
