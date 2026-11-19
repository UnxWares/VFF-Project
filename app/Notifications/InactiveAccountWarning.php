<?php

namespace App\Notifications;

use App\Models\User;
use DateTimeInterface;
use Illuminate\Notifications\Messages\MailMessage;

/** Avertissement envoyé un mois avant la suppression d'un compte inutilisé depuis bientôt 3 ans. */
class InactiveAccountWarning extends ServiceNotification
{
    public function __construct(public readonly DateTimeInterface $deletionDate) {}

    public function toMail(User $notifiable): MailMessage
    {
        $date = $this->longDate($this->deletionDate);

        return $this->message($notifiable)
            ->subject(__('account.mail.inactive.subject', ['date' => $date]))
            ->line(__('account.mail.inactive.intro'))
            ->line(__('account.mail.inactive.deletion', ['date' => $date]))
            ->line(__('account.mail.inactive.keep'))
            ->action(__('account.mail.inactive.action'), route('login'))
            ->line(__('account.mail.inactive.contributions'));
    }
}
