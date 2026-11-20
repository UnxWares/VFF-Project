<?php

namespace App\Services\ErrorReports;

use App\Models\ErrorReport;
use App\Notifications\ErrorReportFollowUp;
use App\Notifications\ErrorReportReceived;
use App\Services\Notifications\SiteNotifier;

/**
 * Prévient les validateurs et les administrateurs d'un nouveau signalement, sur le site. Envoi
 * commun avec les contributions (SiteNotifier) : après la validation de la transaction, une
 * erreur d'envoi est signalée sans faire échouer l'enregistrement.
 */
final readonly class ErrorReportNotifier
{
    public function __construct(private SiteNotifier $notifier) {}

    public function received(ErrorReport $report): void
    {
        // Le membre qui signale ne reçoit pas l'annonce de son propre signalement.
        $this->notifier->toReviewers(new ErrorReportReceived($report->id, $report->subject_label), $report->user_id);
    }

    /**
     * Prévient la personne qui a signalé l'erreur que l'équipe a traité ou rejeté son signalement,
     * par courriel, si elle a laissé son adresse (jamais sinon, ni pour un signalement ouvert).
     */
    public function handled(ErrorReport $report): void
    {
        if ($report->status->isOpen() || blank($report->contact_email)) {
            return;
        }

        // Slug actuel de la fiche (elle a pu être renommée), null si elle a disparu.
        $slug = $report->subject_type->modelClass()::query()->whereKey($report->subject_id)->value('slug');

        $this->notifier->toAddress($report->contact_email, new ErrorReportFollowUp(
            reportId: $report->id,
            subjectLabel: $report->subject_label,
            outcome: $report->status,
            note: $report->resolution_note,
            sheetPath: is_string($slug) ? $report->subject_type->path($slug) : null,
            locale: $report->locale,
        ));
    }
}
