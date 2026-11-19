<?php

namespace App\Notifications;

use App\Enums\NotificationKind;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Décision de modération qui touche le compte d'un membre (suspension, levée de la suspension) :
 * sur le site (canal `database`, page /notifications) et par courriel, dans la langue du membre.
 * Le motif donné par l'administrateur figure dans la notification (exposé des motifs du règlement
 * sur les services numériques, article 17), avec la voie de contestation des CGU.
 *
 * Comme pour les contributions (ContributionNotification), la notification du site s'écrit pendant
 * la requête et le courriel part dans la file d'attente : une messagerie en panne n'empêche pas la
 * suspension ni son annonce sur le site.
 */
abstract class AccountModerationNotification extends ServiceNotification implements ShouldQueue
{
    use Queueable;

    /** Essais d'un courriel avant de le tenir pour échoué (table failed_jobs). */
    public int $tries = 3;

    public function __construct()
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array<string, string> */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    abstract public function kind(): NotificationKind;

    /** Titre (aussi objet du courriel). */
    abstract protected function title(): string;

    /** Phrases d'explication, dans l'ordre. */
    abstract protected function lines(): string;

    /** Motif donné par l'administrateur, sinon null. */
    protected function reason(): ?string
    {
        return null;
    }

    /**
     * @return array{kind: string, title: string, body: string, reason: string|null, url: string}
     */
    public function toDatabase(User $notifiable): array
    {
        return [
            'kind' => $this->kind()->value,
            'title' => $this->title(),
            'body' => $this->lines(),
            'reason' => $this->reason(),
            'url' => route('profile.show', absolute: false),
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $message = $this->message($notifiable)
            ->subject($this->title())
            ->line($this->lines());

        $reason = $this->reason();
        if ($reason !== null) {
            $message->line(__('notifications.reason_line', ['reason' => $reason]));
        }

        return $message->action(__('moderation.mail.action'), route('profile.show'));
    }
}
