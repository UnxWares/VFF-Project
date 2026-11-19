<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Date de dernière visite des membres connectés (users.last_seen_at), base du délai de 3 ans
 * après lequel un compte inutilisé est supprimé (politique de confidentialité).
 *
 * Lue APRÈS le contrôleur : la requête de connexion ou d'inscription compte déjà comme une
 * visite. Une seule écriture par jour et par membre (User::markSeen).
 */
class TrackLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $request->user()?->markSeen();

        return $response;
    }
}
