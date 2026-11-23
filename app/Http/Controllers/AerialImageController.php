<?php

namespace App\Http\Controllers;

use App\Services\Media\Aerial\AerialImages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * `GET /media/aerial/{station}/{period}.jpg` : vue aérienne historique d'une gare (AerialImages), sans session ni
 * cookie. 404 pour une gare inconnue ou une période hors couverture (retenu une heure par le navigateur), 503 si la
 * lecture chez l'IGN est impossible pour l'instant. En-têtes du relais Commons : contenu image seul, bac à sable.
 */
final class AerialImageController
{
    private const IMAGE_CSP = "default-src 'none'; sandbox";

    public function __invoke(Request $request, string $station, string $period, AerialImages $images): Response
    {
        if (! (bool) config('vff.media.aerial.enabled', true) || ! isset(AerialImages::PERIODS[$period])) {
            return self::plain(404, 'Not Found', 3600);
        }
        $limiter = 'aerial-ip:'.$request->ip();
        if (RateLimiter::tooManyAttempts($limiter, 240)) {
            return self::plain(429, 'Too Many Requests', 0, ['Retry-After' => (string) RateLimiter::availableIn($limiter)]);
        }
        RateLimiter::hit($limiter, 60);

        $row = DB::table('stations')->where('slug', $station)->selectRaw('id, ST_Y(geom) AS lat, ST_X(geom) AS lng')->first();
        if ($row === null) {
            return self::plain(404, 'Not Found', 3600);
        }
        $file = $images->image((int) $row->id, (float) $row->lat, (float) $row->lng, $period);
        if ($file === false) {
            return self::plain(503, 'Service Unavailable', 0, ['Retry-After' => '60']);
        }
        if ($file === null) {
            return self::plain(404, 'Not Found', 3600);
        }

        $response = new BinaryFileResponse($file, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=2592000',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => self::IMAGE_CSP,
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ], true, null, true, true);
        $response->isNotModified($request);

        return $response;
    }

    /** @param  array<string, string>  $extra */
    private static function plain(int $status, string $text, int $maxAge = 0, array $extra = []): Response
    {
        return response($text, $status, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => $maxAge > 0 ? 'public, max-age='.$maxAge : 'no-store',
            'X-Content-Type-Options' => 'nosniff',
            ...$extra,
        ]);
    }
}
