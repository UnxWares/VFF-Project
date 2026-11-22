<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Support\Media\CommonsImagePath;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * Lecture d'une vignette de Commons pour le relais de VFF (CommonsImageController) : depuis le cache disque
 * (CommonsImageCache) quand il en a une copie fraîche, sinon sur upload.wikimedia.org.
 *
 * Garde-fous de la lecture sur Commons :
 *   - adresse toujours construite par CommonsImagePath (hôte fixe, HTTPS, chemin de la liste blanche) ;
 *   - redirections jamais suivies d'elles-mêmes : une seule est admise, vers une adresse que CommonsImagePath
 *     accepte aussi, relue sur upload.wikimedia.org ;
 *   - poids limité (`max_bytes`, vérifié sur Content-Length puis pendant la lecture) ;
 *   - type vérifié deux fois : Content-Type annoncé et contenu réel (finfo), celui de l'extension ou WebP
 *     pour une vignette (conversion faite par Commons), plus les dimensions (une vignette ne dépasse pas la
 *     largeur demandée) ;
 *   - `upstream_per_minute` lectures par minute pour tout le serveur et `upstream_per_client_per_minute` par
 *     visiteur (une seule adresse ne prend pas tout le plafond), User-Agent du projet, et pause de
 *     tout le serveur quand Commons répond 429 (délai de Retry-After) ;
 *   - une image absente de Commons (404, 410) est retenue une heure, sans nouvelle lecture.
 * Si Commons ne répond pas, une copie périmée du cache est servie plutôt qu'une erreur.
 */
final readonly class CommonsImageRelay
{
    private const UPSTREAM_LIMITER = 'commons-proxy:upstream';

    private const MISSING_PREFIX = 'commons-proxy:missing:';

    /** Commons a répondu 429 : plus aucune lecture jusqu'à cette date (horodatage Unix). */
    private const BACKOFF_KEY = 'commons-proxy:backoff';

    /** Hauteur maximale d'une image servie (px) : une vignette de 1 280 px de large reste sous ce plafond. */
    private const MAX_HEIGHT = 12_000;

    public function __construct(private CommonsImageCache $cache) {}

    public static function fromConfig(): self
    {
        return new self(CommonsImageCache::fromConfig());
    }

    public function cache(): CommonsImageCache
    {
        return $this->cache;
    }

    /**
     * @param  string|null  $client  clé du visiteur (adresse IP) : limite ses propres lectures sur Commons
     *                               (`upstream_per_client_per_minute`), pour qu'une seule adresse ne prenne
     *                               pas tout le plafond du serveur
     */
    public function get(CommonsImagePath $image, ?string $client = null): CommonsImageResult
    {
        $cached = $this->cache->find($image);
        if ($cached !== null && $this->cache->isFresh($cached)) {
            return $this->found($cached);
        }
        if (Cache::has(self::MISSING_PREFIX.$image->cacheKey())) {
            return CommonsImageResult::failed(CommonsImageFailure::Missing);
        }

        $perMinute = max(1, (int) config('commons_proxy.upstream_per_minute'));
        $perClient = max(1, (int) config('commons_proxy.upstream_per_client_per_minute'));
        $clientLimiter = $client !== null ? self::UPSTREAM_LIMITER.':'.$client : null;
        $backoff = (int) Cache::get(self::BACKOFF_KEY, 0) - now()->getTimestamp();
        if ($backoff > 0 || RateLimiter::tooManyAttempts(self::UPSTREAM_LIMITER, $perMinute)
            || ($clientLimiter !== null && RateLimiter::tooManyAttempts($clientLimiter, $perClient))) {
            $wait = max($backoff, RateLimiter::availableIn(self::UPSTREAM_LIMITER), $clientLimiter !== null ? RateLimiter::availableIn($clientLimiter) : 0, 1);

            return $cached !== null
                ? $this->found($cached)
                : CommonsImageResult::failed(CommonsImageFailure::Busy, $wait);
        }
        RateLimiter::hit(self::UPSTREAM_LIMITER, 60);
        if ($clientLimiter !== null) {
            RateLimiter::hit($clientLimiter, 60);
        }

        $outcome = $this->download($image);
        if (is_array($outcome)) {
            try {
                return $this->found($this->cache->put($image, $outcome[0], $outcome[1]));
            } catch (RuntimeException) {
                return CommonsImageResult::failed(CommonsImageFailure::Unavailable);
            }
        }
        if ($outcome === CommonsImageFailure::Missing) {
            Cache::put(self::MISSING_PREFIX.$image->cacheKey(), true, 3600);
        }
        if ($cached !== null && in_array($outcome, [CommonsImageFailure::Unavailable, CommonsImageFailure::Busy], true)) {
            return $this->found($cached);
        }

        return CommonsImageResult::failed($outcome, $outcome === CommonsImageFailure::Busy ? max(1, (int) Cache::get(self::BACKOFF_KEY, 0) - now()->getTimestamp()) : 0);
    }

    /** @return array{string, string}|CommonsImageFailure octets et type de l'image, ou la raison du refus */
    private function download(CommonsImagePath $image): array|CommonsImageFailure
    {
        $accept = implode(',', self::acceptedTypes($image));
        $response = $this->request($image->upstreamUrl(), $accept);
        if ($response !== null && $response->redirect()) {
            // Seule redirection admise : vers la même image (même chemin canonique), relue sur l'hôte fixe.
            $target = CommonsImagePath::fromUrl((string) $response->header('Location'));
            if ($target === null || $target->path !== $image->path) {
                return CommonsImageFailure::Refused;
            }
            $response = $this->request($target->upstreamUrl(), $accept);
        }
        if ($response === null) {
            return CommonsImageFailure::Unavailable;
        }
        if ($response->status() === 429) {
            // Commons limite le rendu des vignettes absentes de son propre cache (mesure du 22/11/2026 : 16
            // refus sur 25 vignettes de 1 280 px lues à 2,5 par seconde). On attend le délai demandé
            // (30 s par défaut, 5 min au plus) avant toute nouvelle lecture, pour tout le serveur.
            $retry = (int) $response->header('Retry-After');
            $wait = min(300, $retry > 0 ? $retry : 30);
            Cache::put(self::BACKOFF_KEY, now()->getTimestamp() + $wait, $wait);

            return CommonsImageFailure::Busy;
        }

        return match (true) {
            $response->status() === 200 => $this->validate($image, $response),
            in_array($response->status(), [400, 404, 410], true) => CommonsImageFailure::Missing,
            $response->status() >= 300 && $response->status() < 400 => CommonsImageFailure::Refused,
            default => CommonsImageFailure::Unavailable,
        };
    }

    private function request(string $url, string $accept): ?Response
    {
        $max = max(1, (int) config('commons_proxy.max_bytes'));
        try {
            return Http::withHeaders([
                'User-Agent' => (string) config('commons_proxy.user_agent'),
                'Accept' => $accept,
            ])
                ->connectTimeout(5)
                ->timeout(20)
                ->withOptions([
                    'allow_redirects' => false,
                    // Lecture arrêtée dès que le poids annoncé, puis le poids lu, dépasse le plafond.
                    'on_headers' => function (ResponseInterface $response) use ($max): void {
                        if ((int) $response->getHeaderLine('Content-Length') > $max) {
                            throw new RuntimeException('Image trop lourde.');
                        }
                    },
                    'progress' => function (int $total, int $downloaded) use ($max): void {
                        if ($downloaded > $max) {
                            throw new RuntimeException('Image trop lourde.');
                        }
                    },
                ])
                ->get($url);
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array{string, string}|CommonsImageFailure octets et type vérifiés, ou Refused */
    private function validate(CommonsImagePath $image, Response $response): array|CommonsImageFailure
    {
        $bytes = $response->body();
        $declared = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        if ($bytes === '' || strlen($bytes) > (int) config('commons_proxy.max_bytes') || ! in_array($declared, self::acceptedTypes($image), true)) {
            return CommonsImageFailure::Refused;
        }
        $detected = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $size = @getimagesizefromstring($bytes);
        if ($detected !== $declared || $size === false || $size['mime'] !== $declared) {
            return CommonsImageFailure::Refused;
        }
        [$width, $height] = $size;
        $maxWidth = $image->width ?? max(CommonsImagePath::WIDTHS);
        if ($width < 1 || $height < 1 || $width > $maxWidth || $height > self::MAX_HEIGHT) {
            return CommonsImageFailure::Refused;
        }

        return [$bytes, $declared];
    }

    /**
     * Types admis en réponse : celui de l'extension, plus WebP pour une vignette (Commons convertit certaines
     * vignettes JPEG ou PNG en WebP quand le client l'accepte, comme tous les navigateurs actuels).
     *
     * @return list<string>
     */
    private static function acceptedTypes(CommonsImagePath $image): array
    {
        return array_values(array_unique($image->width !== null ? ['image/webp', $image->mimeType()] : [$image->mimeType()]));
    }

    private function found(string $file): CommonsImageResult
    {
        return CommonsImageResult::found($file, $this->cache->relativeOf($file), $this->cache->mimeTypeOf($file));
    }
}
