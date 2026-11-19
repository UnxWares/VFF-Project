<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\Contribution;
use App\Models\User;

/**
 * Écriture du journal d'audit (table audit_events).
 *
 * Le contexte ne contient que des compteurs, des états, des valeurs d'énumération et des listes
 * d'identifiants d'objets du site (contributions supprimées, par exemple) : jamais de
 * nom, de courriel, d'adresse IP ni de texte libre saisi par un membre (motifs de refus compris,
 * déjà conservés avec la contribution).
 */
final class AuditLog
{
    /** @param  array<string, scalar|list<int>|null>  $context */
    public function record(
        AuditAction $action,
        ?User $actor = null,
        ?Contribution $contribution = null,
        array $context = [],
    ): AuditEvent {
        return AuditEvent::create([
            'action' => $action,
            'actor_id' => $actor?->id,
            'contribution_id' => $contribution?->exists ? $contribution->id : null,
            'context' => $context,
        ]);
    }
}
