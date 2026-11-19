<?php

namespace App\Notifications;

use App\Enums\ErrorReportStatus;
use App\Http\Middleware\SetLocale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Courriel de suivi envoyé à la personne qui a signalé une erreur, quand l'équipe traite ou
 * rejette son signalement. Destinataire anonyme (l'adresse laissée dans le formulaire, avec ou
 * sans compte) : `Notification::route('mail', $adresse)`.
 *
 * Contenu sobre : le numéro du signalement, la fiche, la suite donnée, la note de l'équipe et un
 * lien vers la fiche si elle existe encore. Langue : celle de la page où le signalement a été
 * écrit, le français si elle est inconnue. Le courriel part dans la file d'attente (il faut un
 * `queue:work` en production), après la validation de la transaction qui a traité le signalement.
 */
class ErrorReportFollowUp extends Notification implements ShouldQueue
{
    use Queueable;

    /** Essais d'un courriel avant de le tenir pour échoué (table failed_jobs). */
    public int $tries = 3;

    /**
     * @param  ErrorReportStatus  $outcome  Traité ou rejeté (un signalement ouvert n'est jamais annoncé).
     * @param  string|null  $sheetPath  Adresse relative de la fiche, null si elle n'existe plus.
     * @param  string|null  $locale  Langue de la page du signalement.
     */
    public function __construct(
        public readonly int $reportId,
        public readonly string $subjectLabel,
        public readonly ErrorReportStatus $outcome,
        public readonly ?string $note = null,
        public readonly ?string $sheetPath = null,
        ?string $locale = null,
    ) {
        $this->afterCommit();
        $this->locale(in_array($locale, SetLocale::SUPPORTED, true) ? $locale : 'fr');
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Attente entre deux essais (s) : une messagerie momentanément indisponible a le temps de revenir.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $key = $this->outcome === ErrorReportStatus::Resolved ? 'resolved' : 'rejected';
        $message = (new MailMessage)
            ->subject(__("reports.mail.{$key}.subject", ['id' => $this->reportId]))
            ->greeting(__('reports.mail.greeting'))
            ->salutation(__('account.mail.salutation'))
            ->line(__('reports.mail.intro', ['id' => $this->reportId, 'subject' => $this->subjectLabel]))
            ->line(__("reports.mail.{$key}.outcome"));

        $note = trim((string) $this->note);
        if ($note !== '') {
            $message->line(__('reports.mail.note', ['note' => $note]));
        }
        if ($this->sheetPath !== null) {
            $message->action(__('reports.mail.action'), url($this->sheetPath));
        }

        return $message->line(__('reports.mail.why_you'));
    }
}
