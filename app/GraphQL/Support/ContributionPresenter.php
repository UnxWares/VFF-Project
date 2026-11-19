<?php

namespace App\GraphQL\Support;

use App\Models\Contribution;
use App\Models\Line;
use App\Models\Station;
use App\Services\Contributions\ContributionReview;
use App\Services\Contributions\MediaContributions;

/**
 * Forme GraphQL d'une contribution (type `Contribution` de graphql/schema.graphql), rendue à son
 * auteur après `submitContribution` et au validateur après `validateContribution`.
 */
final readonly class ContributionPresenter
{
    public function __construct(private ContributionReview $review) {}

    /** @return array<string, mixed> */
    public function present(Contribution $contribution): array
    {
        $contribution->loadMissing('target');

        return [
            'id' => (string) $contribution->id,
            'type' => $contribution->type,
            'action' => $contribution->action->value,
            'status' => $contribution->status->value,
            'approvals' => $this->review->approvalCount($contribution->id),
            'approvals_required' => ContributionReview::APPROVAL_THRESHOLD,
            'line' => $contribution->target instanceof Line ? $contribution->target : null,
            'station' => $contribution->target instanceof Station ? $contribution->target : null,
            'payload' => MediaContributions::withoutPrivatePath($contribution->payload ?? []),
            'review_notes' => $contribution->review_notes,
            'created_at' => $contribution->created_at,
            'reviewed_at' => $contribution->reviewed_at,
        ];
    }
}
