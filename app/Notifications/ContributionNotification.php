<?php

namespace App\Notifications;

use App\Enums\NotificationKind;
use App\Models\Contribution;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Suite donnée à une contribution, envoyée sur le site (canal `database`, page /notifications) et
 * par courriel. Laravel applique la langue du destinataire (User::preferredLocale : celle de son
 * profil, sinon le français) pendant la construction des deux messages : le texte gardé en base
 * est donc déjà dans sa langue.
 *
 * Vie privée : le texte ne nomme jamais un autre membre (ni l'auteur pour les validateurs, ni le
 * validateur pour l'auteur) ; il désigne la contribution par son numéro et la ligne visée.
 *
 * Envoi : le courriel part dans la file d'attente (un par destinataire, après la validation de la
 * transaction), pour que la décision d'un validateur ne dépende pas de la messagerie ; il faut un
 * `queue:work` en production. La notification sur le site, elle, est écrite tout de suite
 * (connexion `sync`) : le membre la voit dès son prochain affichage.
 */
abstract class ContributionNotification extends ServiceNotification implements ShouldQueue
{
    use Queueable;

    /** Essais d'un courriel avant de le tenir pour échoué (table failed_jobs). */
    public int $tries = 3;

    public function __construct(
        public readonly int $contributionId,
        public readonly ?string $subject = null,
    ) {
        $this->afterCommit();
    }

    /**
     * Ligne visée par une contribution, pour le texte : « Paris - Le Havre (340000) », le nom
     * proposé pour une ligne nouvelle, ou null si la charge utile n'en donne aucun.
     */
    public static function subjectOf(Contribution $contribution): ?string
    {
        $payload = $contribution->payload ?? [];
        $name = trim((string) ($payload['line_name'] ?? $payload['new_line_name'] ?? ''));
        $code = trim((string) ($payload['line_code'] ?? ''));

        return match (true) {
            $name !== '' && $code !== '' => "{$name} ({$code})",
            $name !== '' => $name,
            $code !== '' => $code,
            default => null,
        };
    }

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        // Le site d'abord : une messagerie absente ou en panne n'empêche pas la notification sur le site.
        return ['database', 'mail'];
    }

    /**
     * Connexion de file par canal : la notification du site s'écrit pendant la requête, le courriel
     * suit la connexion par défaut (QUEUE_CONNECTION).
     *
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    /**
     * Attente entre deux essais d'un courriel (s) : une messagerie momentanément indisponible a le
     * temps de revenir.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    abstract public function kind(): NotificationKind;

    /** Titre (aussi objet du courriel). */
    abstract protected function title(): string;

    /** Phrase qui explique la décision et la suite. */
    abstract protected function body(): string;

    /** Motif donné par l'équipe (refus, retrait), sinon null. */
    protected function reason(): ?string
    {
        return null;
    }

    /** Lien relatif vers la contribution (ou la page utile), sinon null. */
    abstract protected function path(): ?string;

    /** Phrase complète : la ligne visée, puis l'explication. */
    protected function text(): string
    {
        $about = $this->subject === null ? null : __('notifications.about', ['subject' => $this->subject]);

        return trim(($about ?? '').' '.$this->body());
    }

    /**
     * Contenu gardé en base : texte déjà rédigé dans la langue du destinataire.
     *
     * @return array{kind: string, contribution_id: int, title: string, body: string, reason: string|null, url: string|null}
     */
    public function toDatabase(User $notifiable): array
    {
        return [
            'kind' => $this->kind()->value,
            'contribution_id' => $this->contributionId,
            'title' => $this->title(),
            'body' => $this->text(),
            'reason' => $this->reason(),
            'url' => $this->path(),
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $message = $this->message($notifiable)
            ->subject($this->title())
            ->line($this->text());

        $reason = $this->reason();
        if ($reason !== null) {
            $message->line(__('notifications.reason_line', ['reason' => $reason]));
        }

        $path = $this->path();

        return $path === null ? $message : $message->action(__('notifications.open'), url($path));
    }

    /** « /contribute/mine#contribution-12 » : la contribution dans la liste de son auteur. */
    protected function minePath(): string
    {
        return route('contribute.mine', absolute: false).'#contribution-'.$this->contributionId;
    }
}
