<?php

namespace App\Notifications;

use App\Enums\NotificationKind;

/** La suspension du compte est levée avant son terme : le membre peut de nouveau contribuer. */
class AccountSuspensionLifted extends AccountModerationNotification
{
    public function kind(): NotificationKind
    {
        return NotificationKind::AccountSuspensionLifted;
    }

    protected function title(): string
    {
        return __('moderation.notification.lifted.title');
    }

    protected function lines(): string
    {
        return __('moderation.notification.lifted.body');
    }
}
