<?php

namespace App\Notifications;

use App\Enums\NotificationKind;

/** Pour l'auteur : première approbation sur deux, la contribution n'est pas encore publiée. */
class ContributionApproved extends ContributionNotification
{
    public function kind(): NotificationKind
    {
        return NotificationKind::Approved;
    }

    protected function title(): string
    {
        return __('notifications.kinds.approved.title', ['id' => $this->contributionId]);
    }

    protected function body(): string
    {
        return __('notifications.kinds.approved.body');
    }

    protected function path(): string
    {
        return $this->minePath();
    }
}
