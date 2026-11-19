<?php

namespace App\Http\Middleware;

use App\Support\LongDate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse (403, message traduit) à un compte suspendu les actions de contribution : proposer une
 * contribution, relire, signaler une erreur. Les routes qui donnent accès à ses propres données
 * (profil, export, suppression du compte, notifications) n'ont pas ce middleware : un compte
 * suspendu garde ses droits sur ses données personnelles.
 *
 * Un visiteur non connecté passe : l'authentification est vérifiée ailleurs (middleware `auth`),
 * et un signalement d'erreur reste ouvert aux visiteurs.
 */
class EnsureAccountNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isSuspended()) {
            abort(403, $user->suspended_until === null
                ? __('moderation.errors.suspended')
                : __('moderation.errors.suspended_until', ['date' => LongDate::format($user->suspended_until)]));
        }

        return $next($request);
    }
}
