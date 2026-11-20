<?php

namespace App\Services\Contributions;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Limites de débit des contributions qui font appeler Wikidata par le serveur de VFF, communes au
 * site (middleware `throttle:<nom>`) et à la mutation GraphQL `submitContribution` (hit()) : une
 * proposition de série (SERIES, 10 par minute et par compte) et une vérification d'identifiant
 * (WIKIDATA_LOOKUP, 30 par minute et par compte). Le compteur est le même des deux côtés : un compte
 * ne double pas sa limite en passant par l'API, et Wikidata ne reçoit pas des milliers de requêtes
 * depuis l'adresse de VFF (ce qui bloquerait aussi les imports).
 */
final class ContributionLimits
{
    public const SERIES = 'contribute-series';

    public const WIKIDATA_LOOKUP = 'wikidata-lookup';

    /** Envois par minute et par compte, par limite. */
    public const PER_MINUTE = [self::SERIES => 10, self::WIKIDATA_LOOKUP => 30];

    /** Déclare les limites nommées (AppServiceProvider). */
    public static function register(): void
    {
        foreach (self::PER_MINUTE as $name => $max) {
            RateLimiter::for($name, fn (Request $request): Limit => Limit::perMinute($max)->by(self::by($request->user()?->id, $request->ip())));
        }
    }

    /**
     * Compte un envoi de ce compte hors du site (API GraphQL) dans la même limite que le site.
     * Rend le nombre de secondes à attendre si la limite est atteinte (rien n'est compté), sinon null.
     */
    public static function hit(string $name, User $user): ?int
    {
        // Même clé que le middleware `throttle:<nom>` (ThrottleRequests : md5 du nom et de la clé de la limite).
        $key = md5($name.self::by($user->id, null));
        if (RateLimiter::tooManyAttempts($key, self::PER_MINUTE[$name])) {
            return max(1, RateLimiter::availableIn($key));
        }
        RateLimiter::hit($key, 60);

        return null;
    }

    private static function by(int|string|null $userId, ?string $ip): string
    {
        return $userId !== null ? 'user:'.$userId : 'ip:'.$ip;
    }
}
