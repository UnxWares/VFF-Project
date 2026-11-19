<?php

namespace App\Notifications;

use App\Enums\NotificationKind;

/**
 * Pour l'auteur : une approbation de plus, la contribution n'est pas encore publiée. Le texte donne le
 * nombre d'approbations reçues et le nombre requis (ContributionController::APPROVAL_THRESHOLD).
 */
class ContributionApproved extends ContributionNotification
{
    public function __construct(
        int $contributionId,
        public readonly int $approvals,
        public readonly int $threshold,
        ?string $subject = null,
    ) {
        parent::__construct($contributionId, $subject);
    }

    public function kind(): NotificationKind
    {
        return NotificationKind::Approved;
    }

    protected function title(): string
    {
        return trans_choice('notifications.kinds.approved.title', $this->approvals, [
            'id' => $this->contributionId,
            'threshold' => $this->threshold,
        ]);
    }

    protected function body(): string
    {
        return trans_choice('notifications.kinds.approved.body', max(1, $this->threshold - $this->approvals));
    }

    protected function path(): string
    {
        return $this->minePath();
    }
}
