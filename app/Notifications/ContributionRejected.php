<?php

namespace App\Notifications;

use App\Enums\NotificationKind;

/**
 * Pour l'auteur : contribution refusée par un validateur, avec le motif s'il en a donné un
 * (sinon, l'adresse à laquelle demander des précisions).
 */
class ContributionRejected extends ContributionNotification
{
    public function __construct(
        int $contributionId,
        ?string $subject = null,
        public readonly ?string $rejectionReason = null,
    ) {
        parent::__construct($contributionId, $subject);
    }

    public function kind(): NotificationKind
    {
        return NotificationKind::Rejected;
    }

    protected function title(): string
    {
        return __('notifications.kinds.rejected.title', ['id' => $this->contributionId]);
    }

    protected function body(): string
    {
        return __($this->reason() === null ? 'notifications.kinds.rejected.body_without_reason' : 'notifications.kinds.rejected.body');
    }

    protected function reason(): ?string
    {
        $reason = trim((string) $this->rejectionReason);

        return $reason === '' ? null : $reason;
    }

    protected function path(): string
    {
        return $this->minePath();
    }
}
