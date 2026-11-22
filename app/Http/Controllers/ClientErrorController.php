<?php

namespace App\Http\Controllers;

use App\Support\Monitoring\RedactedUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Erreurs JavaScript du navigateur (`POST /api/client-errors`), envoyées par resources/js/lib/clientErrors.js
 * sans action du visiteur. Journal `client-errors` (JSON, 30 jours), sans donnée personnelle : ni adresse
 * IP, ni navigateur, ni compte, ni cookie ; adresse de la page et du script réduites à l'origine et au
 * chemin ; message et pile tronqués. Débit limité par adresse IP (limiteur `client-errors`), corps de
 * 8 Ko au plus, réponse toujours 204.
 */
class ClientErrorController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $raw = $request->getContent();
        if ($raw === '' || strlen($raw) > (int) config('monitoring.client_errors_max_bytes')) {
            return response()->noContent();
        }
        $data = json_decode($raw, true);
        if (! is_array($data) || array_is_list($data)) {
            return response()->noContent();
        }

        Log::channel('client-errors')->error('client-error', self::clean($data));

        return response()->noContent();
    }

    /**
     * @param  array<mixed>  $data
     * @return array{type: ?string, message: ?string, page: ?string, source: ?string, line: ?int, column: ?int, stack: ?string, release: ?string}
     */
    public static function clean(array $data): array
    {
        return [
            'type' => self::word($data['type'] ?? null),
            'message' => self::text($data['message'] ?? null, 300),
            'page' => self::url($data['page'] ?? null),
            'source' => self::url($data['source'] ?? null),
            'line' => self::position($data['line'] ?? null),
            'column' => self::position($data['column'] ?? null),
            'stack' => self::stack($data['stack'] ?? null),
            'release' => self::word($data['release'] ?? null),
        ];
    }

    /** Texte court sans adresse électronique ni longue suite de caractères (jeton). */
    private static function text(mixed $value, int $max): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        $value = preg_replace('/[\w.+-]+@[\w-]+(?:\.[\w-]+)+/', '[email]', $value) ?? '';
        $value = preg_replace('/[A-Za-z0-9_-]{32,}/', '[token]', $value) ?? '';
        $value = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/', '', $value) ?? '';

        return mb_substr($value, 0, $max);
    }

    /** Pile d'appels : texte nettoyé, adresses sans requête ni fragment, dix lignes au plus. */
    private static function stack(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = preg_replace('/([a-z][a-z0-9+.-]*:\/\/[^\s?#)]+)[?#][^\s)]*/i', '$1', $value) ?? '';
        }
        $text = self::text($value, 1500);

        return $text === null ? null : implode("\n", array_slice(explode("\n", $text), 0, 10));
    }

    /** Origine et chemin d'une adresse, sans requête ni fragment. */
    private static function url(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }
        $redacted = RedactedUrl::of($url);

        return $redacted === null ? null : mb_substr($redacted, 0, 300);
    }

    /**
     * Numéro de ligne ou de colonne : entier positif, sinon null. Un nombre que PHP ne peut pas représenter
     * en entier (`1e999`, `1e20`) levait un avertissement, donc une erreur 500, au lieu d'être ignoré.
     */
    private static function position(mixed $value): ?int
    {
        $int = is_int($value) || is_float($value) || is_string($value)
            ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])
            : false;

        return $int === false ? null : $int;
    }

    private static function word(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9._-]{1,40}$/', $value) ? $value : null;
    }
}
