<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Session\Middleware\StartSession;
use Symfony\Component\HttpFoundation\Response;

/**
 * Session du site sur /graphql, pour les mutations de contribution envoyées depuis le site par un
 * membre connecté (même origine, contrôle CSRF dans ContributionApiAccess).
 *
 * La session n'est ouverte que si la requête porte déjà le cookie de session du site et aucun jeton
 * `Authorization: Bearer` : un appel anonyme ou par jeton ne crée ni session ni cookie, et reste
 * compté comme avant par le limiteur `public-api` (une session ne relève pas la limite de débit).
 */
class StartGraphQLSession
{
    public function __construct(private readonly Pipeline $pipeline) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() || ! $request->cookies->has((string) config('session.cookie'))) {
            return $next($request);
        }

        return $this->pipeline
            ->send($request)
            ->through([EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class])
            ->then($next);
    }
}
