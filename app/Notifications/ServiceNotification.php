<?php

namespace App\Notifications;

use App\Models\User;
use DateTimeInterface;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Courriel de service envoyé à un membre (la politique de confidentialité exclut toute lettre
 * d'information et toute prospection). Langue : celle du profil (User::preferredLocale, appliquée
 * par Laravel pendant la construction du message), formule d'appel et signature de VFF.
 */
abstract class ServiceNotification extends Notification
{
    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    abstract public function toMail(User $notifiable): MailMessage;

    protected function message(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->greeting(__('account.mail.greeting', ['name' => $notifiable->name]))
            ->salutation(__('account.mail.salutation'));
    }

    /**
     * Date longue dans la langue du message : « 19 décembre 2026 », « 19 December 2026 »
     * (anglais britannique), « 19. Dezember 2026 ».
     */
    protected function longDate(DateTimeInterface $date): string
    {
        $locale = app()->getLocale();

        return Carbon::instance($date)->locale($locale)->isoFormat($locale === 'de' ? 'D. MMMM YYYY' : 'D MMMM YYYY');
    }
}
