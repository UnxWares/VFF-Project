<?php

namespace App\Models;

use App\Enums\AuditAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entrée du journal d'audit, écrite une fois et jamais modifiée (pas de updated_at).
 * Une entrée qui désigne une personne (actor_id : le validateur ou l'administrateur qui a
 * décidé) est conservée 12 mois, comme les journaux techniques (politique de confidentialité),
 * puis purgée par `model:prune`, planifié en production (routes/console.php). Les entrées sans
 * personne désignée (suppressions de comptes, information des membres) restent : elles prouvent
 * que les engagements ont été tenus sans contenir de donnée personnelle.
 *
 * @property AuditAction $action
 * @property array<string, mixed> $context
 */
class AuditEvent extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    /** Durée de conservation d'une entrée qui désigne une personne, en mois. */
    public const RETENTION_MONTHS = 12;

    protected $fillable = ['action', 'actor_id', 'contribution_id', 'context'];

    protected $casts = [
        'action' => AuditAction::class,
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()
            ->whereNotNull('actor_id')
            ->where('created_at', '<', now()->subMonths(self::RETENTION_MONTHS));
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<Contribution, $this> */
    public function contribution(): BelongsTo
    {
        return $this->belongsTo(Contribution::class);
    }
}
