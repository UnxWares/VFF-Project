<?php

declare(strict_types=1);

namespace App\Services\Media;

/** Raison pour laquelle le relais ne sert pas une vignette de Commons (CommonsImageRelay). */
enum CommonsImageFailure
{
    /** Image absente de Commons (404, 410, 400). */
    case Missing;

    /** Réponse de Commons refusée : type, poids, dimensions ou redirection hors de la liste blanche. */
    case Refused;

    /** Commons injoignable ou en erreur, sans copie dans le cache. */
    case Unavailable;

    /** Plafond de lectures sur Commons atteint pour tout le serveur. */
    case Busy;

    /** Code HTTP de la réponse du relais. */
    public function status(): int
    {
        return match ($this) {
            self::Missing, self::Refused => 404,
            self::Unavailable => 502,
            self::Busy => 429,
        };
    }
}
