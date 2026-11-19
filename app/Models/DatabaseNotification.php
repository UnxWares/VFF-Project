<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Notifications\DatabaseNotification as BaseDatabaseNotification;

/**
 * Notification affichée sur le site (table `notifications`). Celle que le membre a lue est
 * supprimée 12 mois après sa lecture (`model:prune`, planifié en production) ; une notification
 * non lue reste jusqu'à ce qu'il la lise ou supprime son compte.
 *
 * `data` : `kind` (App\Enums\NotificationKind), `contribution_id`, `title`, `body`, `reason`
 * (motif d'un refus ou d'un retrait, sinon null) et `url` (lien relatif, ou null).
 */
class DatabaseNotification extends BaseDatabaseNotification
{
    use MassPrunable;

    /** Durée de conservation d'une notification lue, en mois. */
    public const RETENTION_MONTHS = 12;

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()
            ->whereNotNull('read_at')
            ->where('read_at', '<', now()->subMonths(self::RETENTION_MONTHS));
    }
}
