<?php

namespace App\Services\Media\Panoramax;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Lecture du catalogue fédéré Panoramax (`https://api.panoramax.xyz/api/search`, API STAC ouverte, sans clé) :
 * photos autour d'un point. robots.txt de api.panoramax.xyz : seuls deux robots nommés sont refusés.
 *
 * Politesse : requêtes séquentielles, au moins PAUSE_MS entre deux appels, User-Agent de VFF, réponse gardée
 * dans un cache disque (`storage/app/data/panoramax/`, `.nobackup`) : relancer une mesure ne refait aucune requête.
 */
class PanoramaxClient
{
    public const BASE = 'https://api.panoramax.xyz/api/search';

    public const USER_AGENT = 'VFF-Bot/1.0 (+https://vff-project.org)';

    public const PAUSE_MS = 2000;

    private float $lastCall = 0.0;

    public function __construct(
        private readonly ?string $cacheDir = null,
        private readonly int $pauseMs = self::PAUSE_MS,
    ) {}

    /**
     * Photos dont le point est dans la boîte (degrés WGS 84), 100 au plus. `$cacheOnly` : jamais de réseau (null si
     * absent du cache).
     *
     * @return ?list<array<string, mixed>>
     */
    public function search(float $minLng, float $minLat, float $maxLng, float $maxLat, bool $cacheOnly = false): ?array
    {
        $bbox = sprintf('%.6F,%.6F,%.6F,%.6F', $minLng, $minLat, $maxLng, $maxLat);
        $file = $this->dir().'/'.md5($bbox).'.json';
        if (is_file($file)) {
            $cached = json_decode((string) file_get_contents($file), true);

            return is_array($cached) ? array_values($cached) : [];
        }
        if ($cacheOnly) {
            return null;
        }

        $wait = $this->lastCall + $this->pauseMs / 1000 - microtime(true);
        if ($wait > 0) {
            usleep((int) ($wait * 1_000_000));
        }
        $this->lastCall = microtime(true);
        try {
            $res = Http::withHeaders(['User-Agent' => self::USER_AGENT, 'Accept' => 'application/geo+json'])
                ->timeout(30)->retry(2, 5000, throw: false)
                ->get(self::BASE, ['bbox' => $bbox, 'limit' => 100]);
        } catch (ConnectionException $e) {
            // Délai dépassé ou connexion perdue : la gare est comptée en erreur, la passe continue.
            throw new RuntimeException("Panoramax : pas de réponse pour {$bbox} ({$e->getMessage()})", 0, $e);
        }
        if (! $res->successful()) {
            throw new RuntimeException("Panoramax : réponse {$res->status()} pour {$bbox}");
        }
        $features = array_values(array_filter((array) ($res->json('features') ?? []), 'is_array'));
        file_put_contents($file.'.tmp', json_encode($features, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        rename($file.'.tmp', $file);

        return $features;
    }

    private function dir(): string
    {
        $dir = $this->cacheDir ?? storage_path('app/data/panoramax');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if (! is_file($dir.'/.nobackup')) {
            touch($dir.'/.nobackup');
        }

        return $dir;
    }
}
