<?php

namespace App\Notifications;

use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Pour l'équipe (adresse de contact, config mail.contact.address) : le seul administrateur n'a
 * plus utilisé son compte depuis 3 ans. Le compte n'est pas supprimé (InactiveAccounts) ; il faut
 * nommer un autre administrateur. Courriel mis en file d'attente : il part dès qu'une messagerie
 * et un `queue:work` sont en place.
 *
 * Vie privée : le compte est désigné par son numéro, jamais par son nom ni son adresse.
 */
class LastAdminInactive extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $userId,
        public readonly DateTimeInterface $lastUsedAt,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = app()->getLocale();
        $date = Carbon::instance($this->lastUsedAt)->locale($locale)->isoFormat($locale === 'de' ? 'D. MMMM YYYY' : 'D MMMM YYYY');

        return (new MailMessage)
            ->subject(__('account.mail.last_admin.subject'))
            ->greeting(__('account.mail.last_admin.greeting'))
            ->line(__('account.mail.last_admin.intro', ['id' => $this->userId, 'date' => $date]))
            ->line(__('account.mail.last_admin.kept'))
            ->line(__('account.mail.last_admin.next'))
            ->salutation(__('account.mail.salutation'));
    }
}
