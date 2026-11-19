<?php

namespace App\GraphQL\Support;

use App\GraphQL\Exceptions\ContributionApiError;
use App\Http\Middleware\AuthenticateApiToken;
use App\Models\User;
use App\Services\Api\ApiTokens;
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
 */
final readonly class ContributionApiAccess
{
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

    /** @throws ContributionApiError */
    private function authenticated(Request $request): User
    {
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
