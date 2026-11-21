<?php

namespace App\Notifications;

use App\Models\User;
use App\Services\Api\ApiTokens;
use DateTimeInterface;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Rappel envoyé 30 jours avant l'échéance d'un ou de plusieurs jetons d'API du compte
 * (vff:remind-expiring-api-tokens) : nom et date d'échéance de chaque jeton, lien vers le profil
 * pour le renouveler d'un clic. Jamais le secret du jeton.
 */
class ApiTokensExpiring extends ServiceNotification
{
    /** @param  list<array{name: string, expires_at: DateTimeInterface}>  $tokens */
    public function __construct(public readonly array $tokens) {}

    public function toMail(User $notifiable): MailMessage
    {
        $form = count($this->tokens) === 1 ? 'one' : 'other';
        $message = $this->message($notifiable)
            ->subject(__("api_tokens.mail.subject_{$form}"))
            ->line(__("api_tokens.mail.intro_{$form}", ['days' => ApiTokens::reminderDays()]));
        foreach ($this->tokens as $token) {
            $message->line(__('api_tokens.mail.item', ['name' => $token['name'], 'date' => $this->longDate($token['expires_at'])]));
        }

        return $message
            ->line(__('api_tokens.mail.renew'))
            ->action(__('api_tokens.mail.action'), route('profile.show'))
            ->line(__('api_tokens.mail.ignore'));
    }
}
