<?php

namespace App\Notifications;

use App\Enums\NotificationKind;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Pour chaque validateur et administrateur : un signalement d'erreur vient d'arriver. Sur le site
 * seulement (canal `database`, page /notifications), dans la langue du destinataire.
 *
 * Vie privée : la notification désigne la fiche et le numéro du signalement, jamais le texte ni
 * l'adresse de la personne qui signale (ils restent dans error_reports, purgés à 12 mois).
 */
class ErrorReportReceived extends Notification
{
    public function __construct(
        public readonly int $reportId,
        public readonly string $subjectLabel,
    ) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{kind: string, report_id: int, title: string, body: string, reason: null, url: string}
     */
    public function toDatabase(User $notifiable): array
    {
        return [
            'kind' => NotificationKind::ErrorReported->value,
            'report_id' => $this->reportId,
            'title' => __('reports.notification.title', ['id' => $this->reportId]),
            'body' => __('reports.notification.body', ['subject' => $this->subjectLabel]),
            'reason' => null,
            'url' => route('validate.reports.index', absolute: false).'#report-'.$this->reportId,
        ];
    }
}
