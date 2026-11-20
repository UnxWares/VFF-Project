<?php

namespace App\Services\StationPositioning;

use App\Services\WikipediaLineParser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Lieux nommés de la BD TOPO (service de géocodage de la Géoplateforme IGN, index POI,
 * Licence Ouverte 2.0) : hameaux, lieux-dits habités, quartiers, gares et haltes.
 *
 * Même requête et même clé de cache que SectionResolver::geocodeHamlet() (« vff:geo:poi: »,
 * nom normalisé, centre arrondi au dixième de degré, 30 jours ; échec : 1 h) : les deux
 * partagent leurs réponses. Pause de PAUSE_MS entre deux requêtes réseau (service public).
 */
final class IgnPlaceSearch
{
    public const URL = 'https://data.geopf.fr/geocodage/search';

    public const PAUSE_MS = 120;

    /** Catégories retenues : lieu habité ou gare, jamais un lieu-dit « non habité » (bois, champ). */
    private const KEEP = '/habité|habitation|quartier|hameau|gare|halte/u';

    /** @var array<string, ?list<array{nom: string, cat: list<string>, city: ?string, lon: ?float, lat: ?float}>> */
    private static array $memo = [];

    public function __construct(private readonly WikipediaLineParser $text) {}

    /** Vide le mémo de processus (tests). */
    public static function forget(): void
    {
        self::$memo = [];
    }

    /**
     * Lieux du nom exact (normalisé) $name autour de ($lon, $lat), parmi les lieux habités et
     * les gares.
     *
     * @return list<array{name: string, lon: float, lat: float}>
     */
    public function places(string $name, float $lon, float $lat): array
    {
        $q = $this->text->norm($name);
        if (mb_strlen($q) < 3) {
            return [];
        }
        $out = [];
        foreach ($this->search($name, $q, round($lon, 1), round($lat, 1)) ?? [] as $f) {
            $cats = implode(' ', $f['cat']);
            if ($f['lon'] === null || $f['lat'] === null || $this->text->norm($f['nom']) !== $q
                || ! preg_match(self::KEEP, $cats) || str_contains($cats, 'non habité')) {
                continue;
            }
            $out[] = ['name' => $f['nom'], 'lon' => (float) $f['lon'], 'lat' => (float) $f['lat']];
        }

        return $out;
    }

    /** @return ?list<array{nom: string, cat: list<string>, city: ?string, lon: ?float, lat: ?float}> */
    private function search(string $name, string $q, float $lon, float $lat): ?array
    {
        $key = 'vff:geo:poi:'.md5("{$q}|{$lon}|{$lat}");
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }
        $cached = Cache::get($key);
        if (! is_array($cached) || ! array_key_exists('ok', $cached)) {
            try {
                Sleep::usleep(self::PAUSE_MS * 1000);
                $res = Http::withUserAgent('VFF-Bot/1.0 (+https://vff-project.org)')->connectTimeout(3)->timeout(8)
                    ->get(self::URL, ['q' => $name, 'index' => 'poi', 'limit' => 20, 'lat' => $lat, 'lon' => $lon]);
                $cached = $res->ok()
                    ? ['ok' => true, 'list' => array_map(fn ($f) => [
                        'nom' => (string) ($f['properties']['toponym'] ?? ($f['properties']['name'][0] ?? '')),
                        'cat' => array_values(array_map('strval', (array) ($f['properties']['category'] ?? []))),
                        'city' => ((array) ($f['properties']['city'] ?? []))[0] ?? null,
                        'lon' => $f['geometry']['coordinates'][0] ?? null,
                        'lat' => $f['geometry']['coordinates'][1] ?? null,
                    ], (array) ($res->json('features') ?? []))]
                    : ['ok' => false, 'list' => null];
            } catch (\Throwable) {
                $cached = ['ok' => false, 'list' => null];
            }
            Cache::put($key, $cached, $cached['ok'] ? now()->addDays(30) : now()->addHour());
        }

        return self::$memo[$key] = $cached['list'];
    }
}
