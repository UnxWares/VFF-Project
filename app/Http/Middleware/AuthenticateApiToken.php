<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Api\ErrorExamples;
use App\Models\User;
use App\Services\Api\PublicApiRateLimit;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\Events\TokenAuthenticated;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Jeton personnel facultatif de l'API publique (REST /api/v1, GraphQL, MCP).
 *
 * Sans en-tête `Authorization: Bearer …`, l'appel reste anonyme et passe. Avec un jeton :
 *   - inconnu, révoqué ou expiré : HTTP 401 (`invalid_token`), sans repli silencieux sur l'accès
 *     anonyme, pour que le client sache que son jeton ne sert plus. L'appel est compté sur le
 *     compteur anonyme de l'adresse IP (HTTP 429 au-delà) : pas d'essais de jetons sans limite ;
 *   - d'un compte suspendu : HTTP 403 (`account_suspended`) ; les jetons refonctionnent quand la
 *     suspension est levée ou échue ;
 *   - sans la capacité `read` : HTTP 403 (`insufficient_scope`) ;
 *   - valide : le compte devient l'utilisateur de la requête ($request->user()), ce qui fixe sa
 *     limite de débit (limiteur `public-api`, ApiAccessProfile).
 *
 * Placé avant ThrottleRequests dans l'ordre de priorité (bootstrap/app.php). Messages en anglais,
 * comme les autres erreurs de l'API (ApiLocale), avec un code `error` stable.
 */
class AuthenticateApiToken
{
    /** Attribut de la requête : compte du jeton valide (lu par le limiteur PublicApiRateLimit). */
    public const ACCOUNT = 'api_token_account';

    public const INVALID = 'Invalid, revoked or expired API token. Remove the Authorization header to use the API anonymously.';

    public const SUSPENDED = 'This account is suspended: its API tokens are refused until the suspension ends.';

    public const SCOPE = 'This API token does not allow reading the public API.';

    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();
        if ($plain === null || $plain === '') {
            return $next($request);
        }

        $token = PersonalAccessToken::findToken($plain);
        $user = $token?->tokenable;
        if ($token === null || ! $user instanceof User || ($token->expires_at !== null && $token->expires_at->isPast())) {
            return self::refuseInvalid($request);
        }

        if ($user->isSuspended()) {
            return self::error(403, 'account_suspended', self::SUSPENDED)
                ->setData(['message' => self::SUSPENDED, 'error' => 'account_suspended', 'suspended_until' => $user->suspended_until?->toIso8601String()]);
        }

        if (! $token->can('read')) {
            return self::error(403, 'insufficient_scope', self::SCOPE)
                ->header('WWW-Authenticate', 'Bearer error="insufficient_scope"');
        }

        $user->withAccessToken($token);
        $request->setUserResolver(fn (): User => $user);
        $request->attributes->set(self::ACCOUNT, $user);
        // Dernière utilisation du jeton (RecordApiTokenUse) et visite du membre (MarkTokenOwnerSeen).
        event(new TokenAuthenticated($token));

        return $next($request);
    }

    private static function refuseInvalid(Request $request): JsonResponse
    {
        $counter = PublicApiRateLimit::hitAnonymous($request);
        $headers = ['X-RateLimit-Limit' => $counter['limit'], 'X-RateLimit-Remaining' => $counter['remaining']];

        if ($counter['retry_after'] !== null) {
            // Même corps que la réponse 429 de ThrottleRequests.
            return response()->json(ErrorExamples::TOO_MANY_REQUESTS_BODY, 429, ['Cache-Control' => 'no-store'])
                ->withHeaders($headers + ['Retry-After' => $counter['retry_after']]);
        }

        return self::error(401, 'invalid_token', self::INVALID)
            ->withHeaders($headers + ['WWW-Authenticate' => 'Bearer error="invalid_token"']);
    }

    private static function error(int $status, string $code, string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'error' => $code], $status, ['Cache-Control' => 'no-store']);
    }
}
