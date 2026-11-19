<?php

namespace App\Notifications;

use App\Models\User;
use DateTimeInterface;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Information de l'auteur d'une contribution retirée par l'équipe, avec le motif (CGU,
 * « Validation et modération » ; exposé des motifs du règlement sur les services numériques,
 * article 17) et la voie de contestation.
 */
class ContributionRemoved extends ServiceNotification
{
    public function __construct(
        public readonly int $contributionId,
        public readonly DateTimeInterface $submittedAt,
        public readonly string $reason,
    ) {}

    public function toMail(User $notifiable): MailMessage
    {
        return $this->message($notifiable)
            ->subject(__('account.mail.removed.subject', ['id' => $this->contributionId]))
            ->line(__('account.mail.removed.intro', ['id' => $this->contributionId, 'date' => $this->longDate($this->submittedAt)]))
            ->line(__('account.mail.removed.reason', ['reason' => $this->reason]))
            ->line(__('account.mail.removed.contest'))
            ->action(__('account.mail.removed.action'), route('use-conditions'));
    }
}
