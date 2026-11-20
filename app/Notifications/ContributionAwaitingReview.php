<?php

namespace App\Notifications;

use App\Enums\NotificationKind;

/** Pour chaque validateur (sauf l'auteur) : une contribution vient d'être envoyée. */
class ContributionAwaitingReview extends ContributionNotification
{
    public function kind(): NotificationKind
    {
        return NotificationKind::AwaitingReview;
    }

    protected function title(): string
    {
        return __('notifications.kinds.awaiting_review.title', ['id' => $this->contributionId]);
    }

    protected function body(): string
    {
        return __('notifications.kinds.awaiting_review.body');
    }

    protected function path(): string
    {
        return route('validate.index', absolute: false).'#contribution-'.$this->contributionId;
    }
}
