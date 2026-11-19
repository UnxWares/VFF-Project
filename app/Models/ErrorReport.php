<?php

namespace App\Models;

use App\Enums\ErrorReportStatus;
use App\Enums\ErrorReportSubject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Signalement d'une erreur sur une fiche, par un visiteur ou un membre.
 *
 * Conservation : un signalement traité ou rejeté est supprimé 12 mois après son traitement
 * (`model:prune`, planifié en production dans routes/console.php) ; un signalement ouvert reste
 * jusqu'à ce que l'équipe l'examine.
 *
 * @property int $id
 * @property ErrorReportSubject $subject_type
 * @property int $subject_id
 * @property string $subject_slug
 * @property string $subject_label
 * @property string $description
 * @property string|null $source_url
 * @property string|null $contact_email
 * @property string $locale
 * @property int|null $user_id
 * @property ErrorReportStatus $status
 * @property string|null $resolution_note
 * @property int|null $handled_by
 * @property Carbon|null $handled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ErrorReport extends Model
{
    use MassPrunable;

    /** Durée de conservation d'un signalement traité ou rejeté, en mois. */
    public const RETENTION_MONTHS = 12;

    protected $fillable = [
        'subject_type', 'subject_id', 'subject_slug', 'subject_label', 'description', 'source_url',
        'contact_email', 'locale', 'user_id', 'status', 'resolution_note', 'handled_by', 'handled_at',
    ];

    protected $attributes = [
        'status' => 'open',
    ];

    protected function casts(): array
    {
        return [
            'subject_type' => ErrorReportSubject::class,
            'status' => ErrorReportStatus::class,
            'handled_at' => 'datetime',
        ];
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()
            ->where('status', '!=', ErrorReportStatus::Open->value)
            ->where('handled_at', '<', now()->subMonths(self::RETENTION_MONTHS));
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /** Chemin de la fiche visée, avec le slug enregistré. */
    public function subjectPath(): string
    {
        return $this->subject_type->path($this->subject_slug);
    }
}
