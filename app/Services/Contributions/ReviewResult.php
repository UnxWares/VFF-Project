<?php

namespace App\Services\Contributions;

use App\Models\Contribution;

/**
 * Résultat d'une relecture (ContributionReview) : l'issue, la contribution relue (relue après la
 * décision, nulle si elle a disparu entre-temps), le nombre d'approbations reçues et celui requis,
 * et, pour un échec d'application, le message de l'erreur.
 */
final readonly class ReviewResult
{
    public function __construct(
        public ReviewOutcome $outcome,
        public ?Contribution $contribution,
        public int $approvals = 0,
        public int $threshold = ContributionReview::APPROVAL_THRESHOLD,
        public ?string $error = null,
    ) {}
}
