<?php

namespace App\Models;

use App\Enums\ContributionAction;
use App\Enums\ContributionStatus;
use App\Services\Contributions\MediaContributions;
use Database\Factories\ContributionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int|null $user_id NULL quand le compte de l'auteur a été supprimé (contribution publiée gardée)
 */
class Contribution extends Model
{
    /** @use HasFactory<ContributionFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'action' => ContributionAction::class,
        'status' => ContributionStatus::class,
        'payload' => 'array',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Le fichier déposé d'une contribution « média » ne survit pas à son refus ni à sa suppression
     * (retrait par un administrateur, suppression du compte de son auteur) : il est effacé de la
     * réserve privée une fois la transaction validée (MediaContributions::discard).
     */
    protected static function booted(): void
    {
        static::deleted(fn (Contribution $contribution) => app(MediaContributions::class)->discard($contribution));
        static::updated(function (Contribution $contribution): void {
            if ($contribution->wasChanged('status') && $contribution->status === ContributionStatus::Rejected) {
                app(MediaContributions::class)->discard($contribution);
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return MorphTo<Model, $this> */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
