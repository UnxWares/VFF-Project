<?php

namespace App\GraphQL\Exceptions;

use App\Services\Contributions\PublicationFailure;
use Carbon\CarbonInterface;
use GraphQL\Error\ClientAware;
use GraphQL\Error\ProvidesExtensions;

/**
 * Erreur d'une mutation de contribution (submitContribution, validateContribution), renvoyée au
 * client dans `errors[]` avec un code stable dans `extensions.code`. Messages en anglais, comme les
 * autres erreurs de l'API (ApiLocale). Les erreurs de saisie sont, elles, des erreurs de validation
 * Laravel (`extensions.validation`, champ par champ).
 *
 * Codes (documentés dans graphql/schema.graphql, sur le type Mutation) :
 *   - post_required : mutation envoyée en GET (le site n'écrit qu'en POST) ;
 *   - unauthenticated : ni jeton ni session du site ;
 *   - insufficient_scope : jeton sans la capacité `contribute` ;
 *   - csrf_token_mismatch : session du site sans jeton CSRF valide ;
 *   - account_suspended : compte suspendu ;
 *   - too_many_requests : limite de débit atteinte, chaque mutation comptant pour une requête ;
 *   - forbidden : relecture demandée par un compte qui n'est ni validateur ni administrateur ;
 *   - not_found : contribution inconnue ou retirée ;
 *   - own_contribution : relecture de sa propre contribution ;
 *   - already_closed : contribution déjà refusée ou publiée ;
 *   - already_approved : deuxième approbation du même validateur ;
 *   - application_failed : la publication a échoué, rien n'est écrit ; `extensions.reason` donne la
 *     raison stable (PublicationFailure : no_geometry, line_not_found, internal_error), jamais le
 *     message brut de l'erreur.
 */
final class ContributionApiError extends \Exception implements ClientAware, ProvidesExtensions
{
    /** @param  array<string, mixed>  $details */
    private function __construct(string $message, public readonly string $errorCode, private readonly array $details = [])
    {
        parent::__construct($message);
    }

    public static function postRequired(): self
    {
        return new self('Mutations must be sent with POST.', 'post_required');
    }

    public static function unauthenticated(): self
    {
        return new self('Sign in to the site or send a personal API token with the "contribute" ability (Authorization: Bearer).', 'unauthenticated');
    }

    public static function insufficientScope(): self
    {
        return new self('This API token does not have the "contribute" ability. Create a token with this ability in your profile.', 'insufficient_scope');
    }

    public static function csrfMismatch(): self
    {
        return new self('CSRF token mismatch: send the X-XSRF-TOKEN or X-CSRF-TOKEN header from the site, or use a personal API token.', 'csrf_token_mismatch');
    }

    public static function suspended(?CarbonInterface $until): self
    {
        return new self('This account is suspended: it cannot contribute or review until the suspension ends.', 'account_suspended', [
            'suspended_until' => $until?->toIso8601String(),
        ]);
    }

    public static function tooManyRequests(int $retryAfter): self
    {
        return new self('Rate limit reached: each mutation counts as one request. Retry after the delay in "retry_after" (seconds).', 'too_many_requests', [
            'retry_after' => $retryAfter,
        ]);
    }

    /** Limite propre aux propositions de série (ContributionLimits::SERIES), commune avec le site. */
    public static function tooManySeries(int $retryAfter): self
    {
        return new self('Rate limit reached for rolling stock series proposals (10 per minute per account, shared with the website). Retry after the delay in "retry_after" (seconds).', 'too_many_requests', [
            'retry_after' => $retryAfter,
        ]);
    }

    /** Plafond quotidien des propositions (ContributionLimits::DAILY), commun avec le site. */
    public static function dailyLimit(int $max): self
    {
        return new self("Daily limit reached: an account can send {$max} proposals per day (website and API together). Validators and administrators are exempt. Try again tomorrow.", 'daily_limit_reached', [
            'limit' => $max,
        ]);
    }

    public static function forbidden(): self
    {
        return new self('Only validators and administrators can review contributions.', 'forbidden');
    }

    public static function notFound(): self
    {
        return new self('No contribution with this id (unknown or removed by an administrator).', 'not_found');
    }

    public static function own(): self
    {
        return new self('You cannot review your own contribution: another validator must review it.', 'own_contribution');
    }

    public static function closed(): self
    {
        return new self('This contribution has already been rejected or published.', 'already_closed');
    }

    public static function alreadyApproved(): self
    {
        return new self('You have already approved this contribution.', 'already_approved');
    }

    /**
     * Raison stable dans `extensions.reason`, précisions pour le validateur dans `extensions.details`
     * (champs changés depuis l'envoi, liens rendus contraires aux règles) ; le détail technique de
     * l'erreur reste dans le journal du site.
     *
     * @param  list<string>  $details
     */
    public static function applicationFailed(PublicationFailure $failure, array $details = []): self
    {
        return new self('The contribution could not be published; nothing was written and your approval was cancelled.', 'application_failed', [
            'reason' => $failure->value,
            'details' => $details,
        ]);
    }

    public function isClientSafe(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function getExtensions(): array
    {
        return ['code' => $this->errorCode, ...$this->details];
    }
}
