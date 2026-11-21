<?php

namespace App\Services\Contributions;

use App\Models\Contribution;
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
 *
 * S'y ajoute un plafond quotidien (DAILY, 30 propositions par jour et par compte, tous types
 * confondus, site et API ensemble), pour que la relecture à deux reste possible. Les validateurs et
 * les administrateurs en sont exemptés.
 */
final class ContributionLimits
{
    public const SERIES = 'contribute-series';

    public const WIKIDATA_LOOKUP = 'wikidata-lookup';

    /** Envois par minute et par compte, par limite. */
    public const PER_MINUTE = [self::SERIES => 10, self::WIKIDATA_LOOKUP => 30];

    /** Propositions par jour civil (fuseau du site) et par compte, toutes contributions confondues. */
    public const DAILY = 30;

    /**
     * Vrai si ce compte a déjà fait DAILY propositions aujourd'hui (contributions retirées ou refusées
     * comprises : elles ont été relues). Validateurs et administrateurs : jamais.
     */
    public static function dailyLimitReached(User $user): bool
    {
        return ! $user->isReviewer()
            && Contribution::query()->where('user_id', $user->id)->where('created_at', '>=', now()->startOfDay())->count() >= self::DAILY;
    }

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
