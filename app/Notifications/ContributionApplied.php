<?php

namespace App\Notifications;

use App\Enums\NotificationKind;

/** Pour l'auteur : seconde approbation, la contribution est appliquée à l'encyclopédie. */
class ContributionApplied extends ContributionNotification
{
    public function kind(): NotificationKind
    {
        return NotificationKind::Applied;
    }

    protected function title(): string
    {
        return __('notifications.kinds.applied.title', ['id' => $this->contributionId]);
    }

    protected function body(): string
    {
        return __('notifications.kinds.applied.body');
    }

    protected function path(): string
    {
        return $this->minePath();
    }
}
