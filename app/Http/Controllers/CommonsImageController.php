<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Media\CommonsAllowlist;
use App\Services\Media\CommonsImageCache;
use App\Services\Media\CommonsImageRelay;
use App\Support\Media\CommonsImagePath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Lottery;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

use function Illuminate\Support\defer;

/**
 * Relais des vignettes de Wikimedia Commons (GET /media/commons/{chemin}, config commons_proxy) : VFF sert
 * l'image depuis son cache disque ou la lit sur upload.wikimedia.org (CommonsImageRelay), pour que le
 * navigateur du visiteur ne contacte pas Wikimedia (cookies tiers). Route hors du groupe web : ni session ni
 * cookie, la même réponse pour tous.
 *
 * Seuls passent les chemins de CommonsImagePath (liste blanche : vignettes aux largeurs de VFF, originaux
 * JPEG, PNG, GIF, WebP), lus dans l'adresse brute, sans requête (`?…`), et seulement les fichiers cités par VFF
 * (CommonsAllowlist, ensemble précalculé que reconstruit vff:commons-allowlist). Désactivé, le relais répond 404.
 * Les images servies portent `Content-Security-Policy: default-src 'none'; sandbox`, `nosniff` et
 * `Cross-Origin-Resource-Policy: same-origin` (pas de réutilisation directe depuis un autre site). En
 * production, avec `commons_proxy.accel_redirect`, nginx envoie le fichier (X-Accel-Redirect).
 */
final class CommonsImageController
{
    public const PREFIX = '/media/commons/';

    public const IMAGE_CSP = "default-src 'none'; sandbox";

    public function __invoke(Request $request): Response
    {
        if (! config('commons_proxy.enabled')) {
            return self::plain(404, 'Not Found');
        }
        // Limite par adresse IP, ici plutôt qu'en middleware : la réponse reste un texte court, sans la page
        // d'erreur du site.
        $limiter = 'commons-proxy:'.$request->ip();
        if (RateLimiter::tooManyAttempts($limiter, max(1, (int) config('commons_proxy.requests_per_minute')))) {
            $response = self::plain(429, 'Too Many Requests');
            $response->headers->set('Retry-After', (string) RateLimiter::availableIn($limiter));

            return $response;
        }
        RateLimiter::hit($limiter, 60);
        $pathInfo = $request->getPathInfo();
        $image = $request->getQueryString() === null && str_starts_with($pathInfo, self::PREFIX)
            ? CommonsImagePath::parse(substr($pathInfo, strlen(self::PREFIX)))
            : null;
        if ($image === null) {
            return self::plain(404, 'Not Found', 86_400);
        }

        // Seuls les fichiers cités par VFF : lecture en O(1) de la liste précalculée (vff:commons-allowlist), avant
        // toute lecture du cache ou de Commons. Un fichier absent : 404, sans requête sortante. Liste jamais
        // construite : 503 (réessayer), pas un 404 que le navigateur garderait.
        $allowlist = app(CommonsAllowlist::class);
        if (! $allowlist->contains($image->file)) {
            if (! $allowlist->isBuilt()) {
                $unready = self::plain(503, 'Service Unavailable');
                $unready->headers->set('Retry-After', '60');

                return $unready;
            }

            return self::plain(404, 'Not Found', 300);
        }

        $relay = CommonsImageRelay::fromConfig();
        $result = $relay->get($image, (string) $request->ip());
        if ($result->failure !== null || $result->file === null || $result->relativePath === null || $result->mimeType === null) {
            $status = $result->failure?->status() ?? 502;
            $response = self::plain($status, Response::$statusTexts[$status] ?? 'Error', $status === 404 ? 3600 : 0);
            if ($result->retryAfter > 0) {
                $response->headers->set('Retry-After', (string) $result->retryAfter);
            }

            return $response;
        }

        // Nettoyage du cache de temps en temps, après l'envoi de la réponse (la tâche planifiée
        // vff:commons-cache-prune le fait aussi chaque heure).
        $cache = $relay->cache();
        if (Lottery::odds(1, 500)->choose()) {
            defer(fn () => $cache->prune());
        }

        return $this->serve($request, $result->file, $result->relativePath, $result->mimeType, $cache);
    }

    private function serve(Request $request, string $file, string $relativePath, string $mimeType, CommonsImageCache $cache): Response
    {
        $headers = [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age='.$cache->ttlSeconds(),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => self::IMAGE_CSP,
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ];
        $accel = trim((string) config('commons_proxy.accel_redirect'));
        if ($accel !== '') {
            $mtime = (int) filemtime($file);

            return response('', 200, $headers + [
                'X-Accel-Redirect' => rtrim($accel, '/').'/'.$relativePath,
                'Last-Modified' => gmdate('D, d M Y H:i:s', $mtime).' GMT',
            ]);
        }

        $response = new BinaryFileResponse($file, 200, $headers, true, null, true, true);
        $response->isNotModified($request);

        return $response;
    }

    private static function plain(int $status, string $text, int $maxAge = 0): Response
    {
        return response($text, $status, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => $maxAge > 0 ? 'public, max-age='.$maxAge : 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
