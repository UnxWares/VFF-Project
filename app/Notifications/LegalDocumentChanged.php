<?php

namespace App\Notifications;

use App\Enums\LegalDocument;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Information des titulaires d'un compte avant l'entrée en vigueur d'une nouvelle version des CGU
 * (au moins 15 jours avant) ou de la politique de confidentialité.
 */
class LegalDocumentChanged extends ServiceNotification
{
    public function __construct(
        public readonly LegalDocument $document,
        public readonly DateTimeInterface $effectiveDate,
    ) {}

    public function toMail(User $notifiable): MailMessage
    {
        $key = 'account.mail.terms.'.$this->document->value;
        $message = $this->message($notifiable)
            ->subject(__($key.'.subject'))
            ->line(__($key.'.intro', ['date' => $this->longDate($this->effectiveDate)]))
            ->line(__('account.mail.terms.read'))
            ->action(__('account.mail.terms.action'), route($this->document->routeName()));

        return $this->document === LegalDocument::UseConditions
            ? $message->line(__('account.mail.terms.acceptance'))
            : $message->line(__('account.mail.terms.rights'));
    }
}
