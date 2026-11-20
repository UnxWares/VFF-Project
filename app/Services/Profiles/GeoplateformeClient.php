<?php

namespace App\Services\Profiles;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client poli des deux services de la Géoplateforme de l'IGN dont se sert le profil en long :
 *
 * - calcul altimétrique (`elevation.json`, ressource RGE ALTI `ign_rge_alti_wld`) : 5 000 points
 *   au plus par requête, 5 requêtes par seconde au plus par adresse IP (page « Calcul
 *   altimétrique » de cartes.gouv.fr, lue le 2026-11-19) ; altitude -99999 hors couverture ;
 * - WFS de la BD TOPO (`BDTOPO_V3:troncon_de_voie_ferree`) : tronçons de voie d'une emprise,
 *   géométrie 3D (altitude de la voie elle-même) et position (au sol, en tunnel, sur pont).
 *
 * Politesse : une requête à la fois, une pause après chaque appel réseau (une seconde par défaut,
 * bien sous la limite), User-Agent générique, nouvelles tentatives espacées sur 429 et 5xx.
 * Chaque réponse est gardée dans `storage/app/data/altimetrie/` (dossier marqué `.nobackup`) :
 * un nouveau calcul de la même ligne ne rappelle pas l'IGN.
 */
class GeoplateformeClient
{
    public const ELEVATION_URL = 'https://data.geopf.fr/altimetrie/1.0/calcul/alti/rest/elevation.json';

    public const WFS_URL = 'https://data.geopf.fr/wfs/ows';

    public const RESOURCE = 'ign_rge_alti_wld';

    public const USER_AGENT = 'VFF-Bot/1.0 (+https://vff-project.org)';

    /** Points par requête d'altitude (limite du service : 5 000). */
    public const ELEVATION_BATCH = 2000;

    /** Valeur renvoyée par le service pour un point hors couverture. */
    private const NO_DATA = -99999.0;

    /** Altitude de sommet absente dans la BD TOPO (-1000) : tout ce qui est en dessous est ignoré. */
    private const NO_TRACK_Z = -500.0;

    private const WFS_PAGE = 1000;

    private int $networkCalls = 0;

    public function __construct(private string $cacheDir = '', private readonly int $pauseMs = 1000)
    {
        $this->cacheDir = $cacheDir !== '' ? $cacheDir : storage_path('app/data/altimetrie');
    }

    /** Nombre d'appels réseau faits (les réponses lues dans le cache ne comptent pas). */
    public function networkCalls(): int
    {
        return $this->networkCalls;
    }

    /**
     * Altitudes RGE ALTI des points, dans l'ordre ; null hors couverture (étranger, mer).
     *
     * @param  list<array{0: float, 1: float}>  $points  [lon, lat]
     * @return list<float|null>
     */
    public function elevations(array $points): array
    {
        $out = [];
        foreach (array_chunk($points, self::ELEVATION_BATCH) as $chunk) {
            $payload = [
                'lon' => implode('|', array_map(fn (array $p): string => sprintf('%.6f', $p[0]), $chunk)),
                'lat' => implode('|', array_map(fn (array $p): string => sprintf('%.6f', $p[1]), $chunk)),
                'resource' => self::RESOURCE,
                'delimiter' => '|',
                'zonly' => 'true',
            ];
            $json = $this->cached('rge-alti', $payload, fn () => Http::withUserAgent(self::USER_AGENT)
                ->timeout(60)
                ->acceptJson()
                ->asJson()
                ->post(self::ELEVATION_URL, $payload));
            $values = $json['elevations'] ?? null;
            if (! is_array($values) || count($values) !== count($chunk)) {
                throw new RuntimeException('Réponse inattendue du service d\'altimétrie de l\'IGN.');
            }
            foreach ($values as $z) {
                $out[] = is_numeric($z) && (float) $z > self::NO_DATA + 1 ? (float) $z : null;
            }
        }

        return $out;
    }

    /**
     * Tronçons de voie ferrée de la BD TOPO (hors tramway et métro) dans une emprise WGS 84, avec
     * leur position (`ground` au sol, `tunnel`, `bridge`) et l'altitude de chaque sommet : la
     * géométrie de la BD TOPO est en 3D, altitude de la voie (photogrammétrie, ou « Z corrigé »
     * sur les ouvrages). Sommet sans altitude (-1000 dans la source, souvent en tunnel) : null.
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}  $bbox  ouest, sud, est, nord
     * @return list<array{kind: string, coords: list<array{0: float, 1: float}>, z: list<float|null>}>
     */
    public function railTracks(array $bbox): array
    {
        $out = [];
        for ($start = 0; ; $start += self::WFS_PAGE) {
            $query = [
                'SERVICE' => 'WFS',
                'VERSION' => '2.0.0',
                'REQUEST' => 'GetFeature',
                'TYPENAMES' => 'BDTOPO_V3:troncon_de_voie_ferree',
                'OUTPUTFORMAT' => 'application/json',
                'PROPERTYNAME' => 'position_par_rapport_au_sol,geometrie',
                'COUNT' => self::WFS_PAGE,
                'STARTINDEX' => $start,
                // Axe CRS84 (lon, lat) : avec EPSG:4326 le service attend lat, lon (cf. mémoire du projet).
                'CQL_FILTER' => sprintf(
                    "nature NOT IN ('Tramway','Métro') AND BBOX(geometrie,%.6f,%.6f,%.6f,%.6f,'urn:ogc:def:crs:OGC:1.3:CRS84')",
                    ...$bbox,
                ),
            ];
            $json = $this->cached('bdtopo', $query, fn () => Http::withUserAgent(self::USER_AGENT)
                ->timeout(60)
                ->get(self::WFS_URL, $query));
            $features = $json['features'] ?? [];
            foreach ($features as $f) {
                $position = (int) ($f['properties']['position_par_rapport_au_sol'] ?? 0);
                $kind = match (true) {
                    $position < 0 => 'tunnel',
                    $position > 0 => 'bridge',
                    default => 'ground',
                };
                foreach (self::lineStrings($f['geometry'] ?? []) as $line) {
                    $out[] = ['kind' => $kind, ...$line];
                }
            }
            if (count($features) < self::WFS_PAGE) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $geometry  GeoJSON (LineString ou MultiLineString, sommets 3D)
     * @return list<array{coords: list<array{0: float, 1: float}>, z: list<float|null>}>
     */
    private static function lineStrings(array $geometry): array
    {
        $lines = match ($geometry['type'] ?? null) {
            'LineString' => [$geometry['coordinates']],
            'MultiLineString' => $geometry['coordinates'],
            default => [],
        };

        return array_map(fn (array $line): array => [
            'coords' => array_map(fn (array $c): array => [(float) $c[0], (float) $c[1]], $line),
            'z' => array_map(fn (array $c): ?float => isset($c[2]) && (float) $c[2] > self::NO_TRACK_Z ? (float) $c[2] : null, $line),
        ], $lines);
    }

    /**
     * Réponse JSON gardée sur disque, sinon demandée (avec pause et nouvelles tentatives).
     *
     * @param  array<string, mixed>  $key
     * @param  callable(): Response  $fetch
     * @return array<string, mixed>
     */
    private function cached(string $kind, array $key, callable $fetch): array
    {
        $dir = $this->cacheDir.'/'.$kind;
        $file = $dir.'/'.sha1((string) json_encode($key)).'.json';
        if (is_file($file)) {
            $json = json_decode((string) file_get_contents($file), true);
            if (is_array($json)) {
                return $json;
            }
        }

        $json = $this->fetchWithRetries($fetch);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $marker = $this->cacheDir.'/.nobackup';
        if (! is_file($marker)) {
            file_put_contents($marker, "Réponses des services de l'IGN gardées en cache (régénérables).\n");
        }
        file_put_contents($file, json_encode($json));

        return $json;
    }

    /**
     * @param  callable(): Response  $fetch
     * @return array<string, mixed>
     */
    private function fetchWithRetries(callable $fetch): array
    {
        for ($attempt = 1; ; $attempt++) {
            $this->networkCalls++;
            $response = $fetch();
            $this->pause($this->pauseMs);
            if ($response->successful() && is_array($response->json())) {
                return $response->json();
            }
            // Une réponse 200 qui n'est pas du JSON (page de métriques du serveur servie par erreur,
            // vue le 2026-11-19) est un incident passager : on retente comme sur 429 et 5xx.
            $retry = $response->successful() || $response->status() === 429 || $response->serverError();
            if (! $retry || $attempt >= 3) {
                throw new RuntimeException("Service de l'IGN : HTTP {$response->status()}, ".mb_substr(trim($response->body()), 0, 200));
            }
            $this->pause($this->pauseMs * 5 * $attempt);
        }
    }

    private function pause(int $ms): void
    {
        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }
}
