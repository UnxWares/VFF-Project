<?php

namespace App\Notifications;

use App\Enums\NotificationKind;
use DateTimeInterface;

/** Le compte du membre est suspendu : motif, fin éventuelle, droits conservés et contestation. */
class AccountSuspended extends AccountModerationNotification
{
    public function __construct(
        public readonly string $reason,
        public readonly ?DateTimeInterface $until,
    ) {
        parent::__construct();
    }

    public function kind(): NotificationKind
    {
        return NotificationKind::AccountSuspended;
    }

    protected function title(): string
    {
        return __('moderation.notification.suspended.title');
    }

    protected function lines(): string
    {
        $scope = $this->until === null
            ? __('moderation.notification.suspended.indefinite')
            : __('moderation.notification.suspended.until', ['date' => $this->longDate($this->until)]);

        return $scope.' '.__('moderation.notification.suspended.effect').' '.__('moderation.notification.contest');
    }

    protected function reason(): ?string
    {
        return $this->reason;
    }
}
