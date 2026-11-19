<?php

namespace App\GraphQL\Exceptions;

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
 *   - forbidden : relecture demandée par un compte qui n'est ni validateur ni administrateur ;
 *   - not_found : contribution inconnue ou retirée ;
 *   - own_contribution : relecture de sa propre contribution ;
 *   - already_closed : contribution déjà refusée ou publiée ;
 *   - already_approved : deuxième approbation du même validateur ;
 *   - application_failed : la publication a échoué, rien n'est écrit.
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

    public static function applicationFailed(string $reason): self
    {
        return new self('The contribution could not be published; nothing was written and your approval was cancelled.', 'application_failed', [
            'reason' => $reason,
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
