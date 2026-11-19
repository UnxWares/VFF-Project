<?php

namespace App\Services\ErrorReports;

use App\Models\ErrorReport;
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
}
