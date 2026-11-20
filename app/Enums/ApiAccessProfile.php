<?php

namespace App\Enums;

use App\Models\User;

/**
 * Profil d'un appel à l'API publique, qui fixe sa limite de débit (config/api.php, rate_limits) :
 * anonyme (sans jeton), membre (jeton personnel), partenaire (jeton d'un compte partenaire).
 */
enum ApiAccessProfile: string
{
    case Anonymous = 'anonymous';
    case Member = 'member';
    case Partner = 'partner';

    public static function for(?User $user): self
    {
        return match (true) {
            $user === null => self::Anonymous,
            $user->isApiPartner() => self::Partner,
            default => self::Member,
        };
    }

    /** Requêtes par minute. */
    public function perMinute(): int
    {
        return max(1, (int) config("api.rate_limits.{$this->value}"));
    }
}
