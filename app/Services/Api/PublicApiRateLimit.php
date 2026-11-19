<?php

namespace App\Services\Api;

use App\Enums\ApiAccessProfile;
use App\Http\Middleware\AuthenticateApiToken;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Limiteur `public-api` (REST /api/v1, GraphQL /graphql, MCP /mcp) : un seul compteur pour les
 * trois interfaces, par minute. Sans jeton, il est tenu par adresse IP ; avec un jeton valide
 * (AuthenticateApiToken), par compte, tous les jetons du compte partageant le même compteur.
 * La limite dépend du profil (ApiAccessProfile, config/api.php). Le middleware ThrottleRequests
 * ajoute `X-RateLimit-Limit` et `X-RateLimit-Remaining` à chaque réponse, et `Retry-After` à la
 * réponse HTTP 429.
 */
final class PublicApiRateLimit
{
    public const NAME = 'public-api';

    public static function for(Request $request): Limit
    {
        // Seul un jeton lu par AuthenticateApiToken compte : une session du site (cookie) reste anonyme.
        $user = $request->attributes->get(AuthenticateApiToken::ACCOUNT);
        $user = $user instanceof User ? $user : null;
        $profile = ApiAccessProfile::for($user);

        return Limit::perMinute($profile->perMinute())
            ->by($user === null ? self::anonymousKey($request) : 'user:'.$user->id);
    }

    /**
     * Compte un appel refusé avant le limiteur (jeton invalide, AuthenticateApiToken) sur le compteur
     * anonyme de l'adresse IP, pour qu'on ne puisse pas essayer des jetons sans limite. Clé écrite
     * comme ThrottleRequests l'écrit pour un limiteur nommé (md5 du nom et de la clé de la limite).
     *
     * @return array{limit: int, remaining: int, retry_after: ?int} état du compteur après cet appel ;
     *                                                              `retry_after` non nul si la limite est dépassée
     */
    public static function hitAnonymous(Request $request): array
    {
        $limit = ApiAccessProfile::Anonymous->perMinute();
        $key = md5(self::NAME.self::anonymousKey($request));

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return ['limit' => $limit, 'remaining' => 0, 'retry_after' => RateLimiter::availableIn($key)];
        }
        RateLimiter::hit($key, 60);

        return ['limit' => $limit, 'remaining' => max(0, $limit - RateLimiter::attempts($key)), 'retry_after' => null];
    }

    private static function anonymousKey(Request $request): string
    {
        return 'ip:'.$request->ip();
    }
}
