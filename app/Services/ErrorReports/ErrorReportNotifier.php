<?php

namespace App\Services\ErrorReports;

use App\Enums\UserRole;
use App\Models\ErrorReport;
use App\Models\User;
use App\Notifications\ErrorReportReceived;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Prévient les validateurs et les administrateurs d'un nouveau signalement, sur le site.
 * Comme pour les contributions (ContributionNotifier), l'envoi attend la validation de la
 * transaction et une erreur d'envoi est signalée sans faire échouer l'enregistrement.
 */
final readonly class ErrorReportNotifier
{
    public function received(ErrorReport $report): void
    {
        $reviewers = User::query()
            ->whereIn('role', [UserRole::Validator->value, UserRole::Admin->value])
            // Le membre qui signale ne reçoit pas l'annonce de son propre signalement.
            ->when($report->user_id !== null, fn ($query) => $query->whereKeyNot($report->user_id))
            ->orderBy('id')
            ->get();
        $notification = new ErrorReportReceived($report->id, $report->subject_label);

        DB::afterCommit(function () use ($reviewers, $notification): void {
            foreach ($reviewers as $reviewer) {
                try {
                    $reviewer->notify($notification);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        });
    }
}
