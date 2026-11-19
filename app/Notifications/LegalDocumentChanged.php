<?php

namespace App\Notifications;

use App\Enums\LegalDocument;
use App\Enums\NotificationKind;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Information des titulaires d'un compte avant l'entrée en vigueur d'une nouvelle version des CGU
 * (au moins 15 jours avant) ou de la politique de confidentialité : sur le site (page
 * /notifications) et par courriel, dans la langue du membre.
 *
 * La notification du site s'écrit avant le courriel : une messagerie en panne ne l'empêche pas.
 * Pour qu'un nouvel envoi après un échec ne la double pas, vff:notify-legal-change ne garde que le
 * courriel pour un membre déjà prévenu sur le site du même changement (isSameChange).
 */
class LegalDocumentChanged extends ServiceNotification
{
    public function __construct(
        public readonly LegalDocument $document,
        public readonly DateTimeInterface $effectiveDate,
    ) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Contenu gardé en base, déjà rédigé dans la langue du membre : mêmes phrases que le courriel.
     *
     * @return array{kind: string, document: string, effective_date: string, title: string, body: string, reason: null, url: string}
     */
    public function toDatabase(User $notifiable): array
    {
        $key = 'account.mail.terms.'.$this->document->value;

        return [
            'kind' => NotificationKind::LegalDocumentChanged->value,
            'document' => $this->document->value,
            'effective_date' => $this->effectiveDate->format('Y-m-d'),
            'title' => __($key.'.subject'),
            'body' => __($key.'.intro', ['date' => $this->longDate($this->effectiveDate)]).' '.__('account.mail.terms.read'),
            'reason' => null,
            'url' => route($this->document->routeName(), absolute: false),
        ];
    }

    /**
     * Données d'une notification du site qui annonce ce même changement (même texte, même date
     * d'entrée en vigueur).
     *
     * @param  array<string, mixed>  $data
     */
    public function isSameChange(array $data): bool
    {
        return ($data['kind'] ?? null) === NotificationKind::LegalDocumentChanged->value
            && ($data['document'] ?? null) === $this->document->value
            && ($data['effective_date'] ?? null) === $this->effectiveDate->format('Y-m-d');
    }

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
