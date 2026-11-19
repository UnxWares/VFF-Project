<?php

namespace App\Policies;

use App\Models\ErrorReport;
use App\Models\User;

/** Signalements d'erreur : lus et traités par les validateurs et les administrateurs seulement. */
class ErrorReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isReviewer();
    }

    public function handle(User $user, ErrorReport $report): bool
    {
        return $user->isReviewer();
    }
}
