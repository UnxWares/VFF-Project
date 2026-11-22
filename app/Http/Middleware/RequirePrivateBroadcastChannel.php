<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autorisation des canaux (/broadcasting/auth) : seuls les canaux privés (`private-…`) sont admis.
 * Laravel retire le préfixe avant de chercher le canal déclaré, donc `presence-review-queue` ou
 * `private-encrypted-review-queue` seraient autorisés comme `private-review-queue` alors qu'aucune page
 * ne s'y abonne. Tout autre type de canal est refusé (403), même pour un validateur.
 */
final class RequirePrivateBroadcastChannel
{
    public function handle(Request $request, Closure $next): Response
    {
        $channel = $request->input('channel_name');
        abort_unless(
            is_string($channel) && str_starts_with($channel, 'private-') && ! str_starts_with($channel, 'private-encrypted-'),
            403,
        );

        return $next($request);
    }
}
