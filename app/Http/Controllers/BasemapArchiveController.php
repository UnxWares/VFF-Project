<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Basemap\BasemapArchive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Archive PMTiles du fond de carte libre (GET /basemap/{archive}), lue par plages d'octets : la réponse à une
 * requête `Range: bytes=début-fin` est une réponse partielle 206 (BinaryFileResponse s'en charge). Route hors
 * du groupe web : ni session ni cookie, la même réponse pour tous.
 *
 * L'archive pèse plusieurs gigaoctets et les clients PMTiles n'en lisent jamais que des morceaux (en-tête de
 * 16 Kio, répertoires, tuiles). D'où trois garde-fous (config maps.basemap) :
 *   - 416 pour une requête GET sans plage, à plusieurs plages, mal formée ou plus grande que `max_range_bytes`
 *     (aucun téléchargement de l'archive entière) ; 412 pour un If-Range qui ne correspond plus à l'archive
 *     (la réponse normale serait le fichier entier) ;
 *   - par adresse IP, `requests_per_minute` requêtes par minute et `bytes_per_hour` octets par heure, puis 429
 *     avec Retry-After.
 * Une requête HEAD reste permise sans plage (aucun octet de l'archive n'est envoyé).
 *
 * L'adresse porte la date des données : la réponse se garde longtemps (config maps.basemap.max_age). L'ETag
 * est tiré du nom, de la taille et de la date du fichier, sans relire ses 6 Go. En production, avec
 * `maps.basemap.accel_redirect`, ces règles restent appliquées ici et nginx envoie les octets
 * (X-Accel-Redirect, docs/FOND-DE-CARTE.md).
 */
final class BasemapArchiveController
{
    public function __invoke(Request $request, string $archive): Response
    {
        $basemap = BasemapArchive::fromConfig();
        abort_unless($basemap->serves($archive), 404);

        $path = (string) $basemap->path();
        $size = (int) filesize($path);
        $etag = hash('xxh128', $archive.'|'.$size.'|'.filemtime($path));
        $maxRange = max(1, (int) config('maps.basemap.max_range_bytes'));
        $accel = trim((string) config('maps.basemap.accel_redirect'));

        if (! $request->isMethod('HEAD')) {
            $length = self::rangeLength($request->headers->get('Range'), $size);
            if ($length === null || $length > $maxRange) {
                return $this->refusal(416, sprintf(
                    'This archive is read in byte ranges, as PMTiles clients do: send one Range header (bytes=start-end) of at most %d bytes.',
                    $maxRange,
                ), ['Content-Range' => 'bytes */'.$size]);
            }

            // Avec X-Accel-Redirect, nginx compare If-Range à sa propre empreinte : tout If-Range est refusé.
            $ifRange = $request->headers->get('If-Range');
            if ($ifRange !== null && ($accel !== '' || trim($ifRange) !== '"'.$etag.'"')) {
                return $this->refusal(412, 'The archive has changed since the If-Range validator: read its header again.');
            }

            if (($limited = $this->throttle($request, $length)) !== null) {
                return $limited;
            }
        }

        if ($accel !== '') {
            // Production derrière nginx : les règles ci-dessus sont appliquées ici, nginx envoie les octets
            // (location interne de docs/FOND-DE-CARTE.md), plage comprise.
            return new Response('', 200, [
                'X-Accel-Redirect' => rtrim($accel, '/').'/'.$archive,
                'Content-Type' => 'application/octet-stream',
                'Cache-Control' => 'public, max-age='.$basemap->maxAge,
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        $response = new BinaryFileResponse($path, 200, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ], true, null, false, true);
        $response->setEtag($etag);
        $response->setPublic();
        $response->setMaxAge($basemap->maxAge);

        return $response;
    }

    /**
     * Nombre d'octets demandés par un en-tête Range à une seule plage (`bytes=a-b`, `bytes=a-` ou `bytes=-n`),
     * borné à la taille du fichier ; null sans en-tête, pour plusieurs plages, une autre unité, une plage mal
     * formée ou hors du fichier.
     */
    public static function rangeLength(?string $header, int $size): ?int
    {
        if ($header === null || preg_match('/\Abytes=(\d*)-(\d*)\z/', trim($header), $match) !== 1 || $match[1].$match[2] === '') {
            return null;
        }

        if ($match[1] === '') {
            $suffix = (int) $match[2];

            return $suffix > 0 && $size > 0 ? min($suffix, $size) : null;
        }

        $start = (int) $match[1];
        $end = $match[2] === '' ? $size - 1 : min((int) $match[2], $size - 1);

        return $start < $size && $end >= $start ? $end - $start + 1 : null;
    }

    /** Limites de débit par adresse IP (requêtes par minute, octets par heure) ; null quand la requête passe. */
    private function throttle(Request $request, int $length): ?Response
    {
        $ip = (string) $request->ip();
        $requests = 'basemap:requests:'.$ip;
        $bytes = 'basemap:bytes:'.$ip;
        $maxRequests = max(1, (int) config('maps.basemap.requests_per_minute'));
        $maxBytes = max(1, (int) config('maps.basemap.bytes_per_hour'));

        foreach ([[$requests, $maxRequests], [$bytes, $maxBytes - $length + 1]] as [$key, $max]) {
            if ($max < 1 || RateLimiter::tooManyAttempts($key, $max)) {
                $retry = max(1, RateLimiter::availableIn($key));

                return $this->refusal(429, sprintf(
                    'Too many basemap requests from this address (%d per minute and %d bytes per hour at most). Retry after %d seconds.',
                    $maxRequests,
                    $maxBytes,
                    $retry,
                ), ['Retry-After' => (string) $retry]);
            }
        }

        RateLimiter::increment($requests, 60);
        RateLimiter::increment($bytes, 3600, $length);

        return null;
    }

    /** @param  array<string, string>  $headers */
    private function refusal(int $status, string $message, array $headers = []): Response
    {
        return new Response($message."\n", $status, $headers + [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-store',
            'Accept-Ranges' => 'bytes',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
