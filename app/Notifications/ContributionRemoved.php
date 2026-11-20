<?php

namespace App\Notifications;

use App\Enums\NotificationKind;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Information de l'auteur d'une contribution retirée par l'équipe, avec le motif (CGU,
 * « Validation et modération » ; exposé des motifs du règlement sur les services numériques,
 * article 17) et la voie de contestation. La contribution n'existe plus : le lien mène aux CGU.
 */
class ContributionRemoved extends ContributionNotification
{
    public function __construct(
        int $contributionId,
        public readonly DateTimeInterface $submittedAt,
        public readonly string $reason,
        ?string $subject = null,
    ) {
        parent::__construct($contributionId, $subject);
    }

    public function kind(): NotificationKind
    {
        return NotificationKind::Removed;
    }

    protected function title(): string
    {
        return __('account.mail.removed.subject', ['id' => $this->contributionId]);
    }

    protected function body(): string
    {
        return __('account.mail.removed.intro', ['id' => $this->contributionId, 'date' => $this->longDate($this->submittedAt)])
            .' '.__('account.mail.removed.contest');
    }

    protected function reason(): string
    {
        return $this->reason;
    }

    protected function path(): string
    {
        return route('use-conditions', absolute: false);
    }

    public function toMail(User $notifiable): MailMessage
    {
        return $this->message($notifiable)
            ->subject($this->title())
            ->line(__('account.mail.removed.intro', ['id' => $this->contributionId, 'date' => $this->longDate($this->submittedAt)]))
            ->line(__('account.mail.removed.reason', ['reason' => $this->reason]))
            ->line(__('account.mail.removed.contest'))
            ->action(__('account.mail.removed.action'), route('use-conditions'));
    }
}
