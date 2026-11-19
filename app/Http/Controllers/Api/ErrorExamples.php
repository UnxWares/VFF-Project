<?php

namespace App\Http\Controllers\Api;

/**
 * Exemples de corps d'erreur de l'API publique, pour la documentation OpenAPI (attributs `ApiResponse`).
 *
 * Toutes les erreurs sont en JSON avec un `message` ; un paramètre invalide (HTTP 422) détaille en plus
 * chaque champ fautif dans `errors`. Les messages de validation sont en anglais, quelle que soit la langue du site.
 */
final class ErrorExamples
{
    /**
     * 429 : limite de requêtes par minute du profil dépassée (300 par adresse IP sans jeton, plus avec un
     * jeton : config/api.php) ; l'en-tête `Retry-After` donne l'attente en secondes.
     */
    public const TOO_MANY_REQUESTS = 'Limite de requêtes par minute dépassée : 300 par adresse IP sans jeton, davantage avec un jeton personnel (voir « Authentification et quotas »). L\'en-tête `Retry-After` donne le délai d\'attente en secondes, `X-RateLimit-Limit` la limite et `X-RateLimit-Remaining` le nombre de requêtes restantes.';

    public const TOO_MANY_REQUESTS_BODY = ['message' => 'Too Many Attempts.'];

    public const EVENT_NOT_FOUND = ['message' => 'No query results for model [App\Models\LineEvent] 99999999'];

    public const MEDIA_NOT_FOUND = ['message' => 'No query results for model [App\Models\Media].'];

    public const STATION_NOT_FOUND = ['message' => 'No query results for model [App\Models\Station] brestt'];

    public const EVENTS_INVALID = [
        'message' => 'The selected type is invalid. (and 1 more error)',
        'errors' => [
            'type' => ['The selected type is invalid.'],
            'from' => ['The from must be a year (YYYY) or a date (YYYY-MM-DD).'],
        ],
    ];

    public const STATION_EVENTS_INVALID = [
        'message' => 'The selected inferred is invalid.',
        'errors' => ['inferred' => ['The selected inferred is invalid.']],
    ];

    public const MEDIA_INVALID = [
        'message' => 'The line field prohibits station from being present. (and 1 more error)',
        'errors' => [
            'line' => ['The line field prohibits station from being present.'],
            'station' => ['The station field prohibits line from being present.'],
        ],
    ];

    public const LINE_NOT_FOUND = ['message' => 'No query results for model [App\Models\Line] rfn-999999'];

    public const SOURCE_NOT_FOUND = ['message' => 'Unknown source provider.'];

    public const ERA_NOT_FOUND = ['message' => 'Unknown era 1935: eras are the decades 1830 to 2030.'];

    public const LINES_INVALID = [
        'message' => 'The selected network is invalid. (and 1 more error)',
        'errors' => [
            'network' => ['The selected network is invalid.'],
            'era' => ['The selected era is invalid.'],
        ],
    ];

    public const STATIONS_INVALID = [
        'message' => 'The selected status is invalid.',
        'errors' => ['status' => ['The selected status is invalid.']],
    ];

    public const ERA_INVALID = [
        'message' => 'The era field must be an integer.',
        'errors' => ['era' => ['The era field must be an integer.']],
    ];

    public const GEOMETRY_INVALID = [
        'message' => 'The geometry field must be true or false.',
        'errors' => ['geometry' => ['The geometry field must be true or false.']],
    ];

    /** 451 : le tracé existe, mais aucune de ses sources n'autorise la redistribution (exports GPX et KML). */
    public const GEOMETRY_WITHHELD = [
        'error' => 'geometry_withheld',
        'message' => 'The route exists but its sources do not allow redistribution, so nothing is exported.',
        'withheld' => [[
            'provider' => 'exemple',
            'name' => 'Source d\'exemple (usage non commercial)',
            'license' => 'Usage non commercial uniquement : redistribution non autorisée',
            'reason' => 'licence_not_open',
            'sections' => 3,
            'length_m' => 18420,
        ]],
    ];

    /**
     * Corps de l'erreur 404 d'une ère inconnue.
     *
     * @return array{message: string}
     */
    public static function eraNotFound(int $year): array
    {
        return ['message' => "Unknown era {$year}: eras are the decades 1830 to 2030."];
    }
}
