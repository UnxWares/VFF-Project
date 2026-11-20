<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Exceptions\ContributionApiError;
use App\GraphQL\Support\ContributionApiAccess;
use App\GraphQL\Support\ContributionPresenter;
use App\Services\Contributions\ContributionReview;
use App\Services\Contributions\PublicationFailure;
use App\Services\Contributions\ReviewOutcome;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Traits\Localizable;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Mutation `validateContribution` : approbation ou refus d'une contribution par un validateur, avec
 * les règles de l'espace de validation du site (ContributionReview) : deux approbations publient,
 * motif obligatoire pour un refus, pas de relecture de sa propre contribution ni d'une contribution
 * close, réputation recalculée, auteur prévenu, journal d'audit, webhooks `contribution.applied`.
 */
final readonly class ValidateContribution
{
    use Localizable;

    public function __construct(
        private ContributionApiAccess $access,
        private ContributionReview $review,
        private ContributionPresenter $presenter,
    ) {}

    /**
     * @param  array{id: string, decision: string, reason?: string|null}  $args
     * @return array{outcome: string, contribution: array<string, mixed>}
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): array
    {
        $reviewer = $this->access->reviewer($context->request());

        return $this->withLocale('en', function () use ($reviewer, $args): array {
            $id = filter_var($args['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                throw ContributionApiError::notFound();
            }

            try {
                if ($args['decision'] === 'reject') {
                    $reason = trim((string) ($args['reason'] ?? ''));
                    Validator::make(['reason' => $reason], ['reason' => ContributionReview::reasonRules()], ContributionReview::reasonMessages('reason'))->validate();
                    $result = $this->review->reject($reviewer, $id, $reason);
                } else {
                    $result = $this->review->approve($reviewer, $id);
                }
            } catch (ModelNotFoundException) {
                throw ContributionApiError::notFound();
            }

            if (! $result->outcome->isRecorded() || $result->contribution === null) {
                throw match ($result->outcome) {
                    ReviewOutcome::Own => ContributionApiError::own(),
                    ReviewOutcome::AlreadyApproved => ContributionApiError::alreadyApproved(),
                    ReviewOutcome::Failed => ContributionApiError::applicationFailed($result->failure ?? PublicationFailure::InternalError, $result->failureDetails),
                    default => ContributionApiError::closed(),
                };
            }

            return [
                'outcome' => $result->outcome->value,
                'contribution' => $this->presenter->present($result->contribution),
            ];
        });
    }
}
