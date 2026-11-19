<?php

namespace App\GraphQL\Support;

use App\GraphQL\Exceptions\ContributionApiError;
use App\Http\Middleware\AuthenticateApiToken;
use App\Models\User;
use App\Services\Api\ApiTokens;
use App\Services\Api\PublicApiRateLimit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Qui agit dans une mutation GraphQL de contribution, et avec quel droit.
 *
 * Deux façons de s'authentifier :
 *   - jeton personnel (`Authorization: Bearer`, lu par AuthenticateApiToken) qui porte la capacité
 *     `contribute` (ApiTokens::CONTRIBUTE), cochée à sa création dans le profil ;
 *   - session du site (cookie, StartGraphQLSession), avec le contrôle CSRF du site (SessionCsrfCheck).
 *
 * Puis les règles du site : un compte suspendu ne contribue ni ne relit (comme le middleware
 * `not.suspended`), et seule une personne validatrice ou administratrice relit.
 *
 * Débit : chaque mutation compte pour une requête dans la limite `public-api`. Le middleware compte la
 * requête HTTP, donc sa première mutation ; chaque mutation suivante de la même requête (alias,
 * lot d'opérations) est comptée ici, avant tout contrôle, et refusée sans rien écrire au-delà de la
 * limite (`too_many_requests`). Plusieurs champs de mutation ne contournent donc plus la limite.
 */
final readonly class ContributionApiAccess
{
    /** Attribut de requête : nombre de mutations de contribution déjà reçues dans la requête HTTP. */
    public const MUTATIONS = 'vff.graphql.contribution_mutations';

    public function __construct(private SessionCsrfCheck $csrf) {}

    /**
     * Compte autorisé à proposer une contribution.
     *
     * @throws ContributionApiError
     */
    public function contributor(Request $request): User
    {
        $user = $this->authenticated($request);
        if ($user->isSuspended()) {
            throw ContributionApiError::suspended($user->suspended_until);
        }

        return $user;
    }

    /**
     * Compte autorisé à relire (approuver ou refuser) une contribution.
     *
     * @throws ContributionApiError
     */
    public function reviewer(Request $request): User
    {
        $user = $this->contributor($request);
        if (! $user->isReviewer()) {
            throw ContributionApiError::forbidden();
        }

        return $user;
    }

    /**
     * Compte la mutation dans la limite de débit : la première de la requête HTTP l'est déjà par le
     * middleware `throttle:public-api`, les suivantes le sont ici.
     *
     * @throws ContributionApiError
     */
    private function countMutation(Request $request): void
    {
        $count = (int) $request->attributes->get(self::MUTATIONS, 0) + 1;
        $request->attributes->set(self::MUTATIONS, $count);
        if ($count > 1 && ($retryAfter = PublicApiRateLimit::hitAgain($request)) !== null) {
            throw ContributionApiError::tooManyRequests($retryAfter);
        }
    }

    /** @throws ContributionApiError */
    private function authenticated(Request $request): User
    {
        $this->countMutation($request);

        // Lighthouse exécute aussi les opérations reçues en GET : une écriture n'en passe jamais par là.
        if (! $request->isMethod('POST')) {
            throw ContributionApiError::postRequired();
        }

        // Jeton valide : AuthenticateApiToken a déjà refusé les jetons inconnus, expirés, sans
        // capacité `read` ou d'un compte suspendu (HTTP 401 ou 403, avant GraphQL).
        $tokenUser = $request->attributes->get(AuthenticateApiToken::ACCOUNT);
        if ($tokenUser instanceof User) {
            if (! $tokenUser->tokenCan(ApiTokens::CONTRIBUTE)) {
                throw ContributionApiError::insufficientScope();
            }

            return $tokenUser;
        }

        $sessionUser = $request->hasSession() ? Auth::guard('web')->user() : null;
        if (! $sessionUser instanceof User) {
            throw ContributionApiError::unauthenticated();
        }
        if (! $this->csrf->passes($request)) {
            throw ContributionApiError::csrfMismatch();
        }

        return $sessionUser;
    }
}
