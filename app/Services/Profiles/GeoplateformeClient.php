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
 * Cache par grille fixe (depuis le 21/11/2026), pour qu'il reste valable quand les tracés de
 * référence bougent de quelques mètres :
 * - BD TOPO : une tuile de TILE_DEG degré de côté (environ 22 km sur 15 km) par fichier
 *   `bdtopo-tuiles/<x>_<y>.json.gz`, tous les tronçons de la tuile, sous une forme compacte ;
 * - RGE ALTI : chaque point est ramené au nœud le plus proche d'une grille de NODE_DEG degré
 *   (6 m au plus d'écart en France, sans effet sensible sur l'altitude du terrain), et les
 *   altitudes des nœuds sont gardées par tuile dans `rge-alti-noeuds/<x>_<y>.json.gz`.
 *
 * Politesse : une requête à la fois, une pause après chaque appel réseau (une seconde par défaut,
 * bien sous la limite), User-Agent générique, nouvelles tentatives espacées sur 429 et 5xx.
 * Le dossier `storage/app/data/altimetrie/` est marqué `.nobackup` (régénérable).
 */
class GeoplateformeClient
{
    public const ELEVATION_URL = 'https://data.geopf.fr/altimetrie/1.0/calcul/alti/rest/elevation.json';

    public const WFS_URL = 'https://data.geopf.fr/wfs/ows';

    public const RESOURCE = 'ign_rge_alti_wld';

    public const USER_AGENT = 'VFF-Bot/1.0 (+https://vff-project.org)';

    /** Côté d'une tuile du cache, en degrés. */
    public const TILE_DEG = 0.2;

    /** Pas de la grille des nœuds d'altitude du terrain, en degrés (environ 7 m sur 11 m). */
    public const NODE_DEG = 0.0001;

    /** Points par requête d'altitude (limite du service : 5 000). */
    public const ELEVATION_BATCH = 2000;

    /** Valeur renvoyée par le service pour un point hors couverture. */
    private const NO_DATA = -99999.0;

    /** Altitude de sommet absente dans la BD TOPO (-1000) : tout ce qui est en dessous est ignoré. */
    private const NO_TRACK_Z = -500.0;

    private const WFS_PAGE = 1000;

    private int $networkCalls = 0;

    /** @var array<string, array<string, float|null>> altitudes des nœuds par tuile, chargées */
    private array $nodes = [];

    public function __construct(private string $cacheDir = '', private readonly int $pauseMs = 1000)
    {
        $this->cacheDir = $cacheDir !== '' ? $cacheDir : storage_path('app/data/altimetrie');
    }

    /** Nombre d'appels réseau faits (les réponses lues dans le cache ne comptent pas). */
    public function networkCalls(): int
    {
        return $this->networkCalls;
    }

    /** Dossier du cache (taille surveillée par la commande). */
    public function cacheDir(): string
    {
        return $this->cacheDir;
    }

    /**
     * Tuile qui contient le point.
     *
     * @return array{0: int, 1: int}
     */
    public static function tileOf(float $lon, float $lat): array
    {
        return [(int) floor($lon / self::TILE_DEG), (int) floor($lat / self::TILE_DEG)];
    }

    /** Vrai si les tronçons de la tuile sont déjà dans le cache. */
    public function hasTile(int $x, int $y): bool
    {
        return is_file($this->tileFile('bdtopo-tuiles', $x, $y));
    }

    /**
     * Altitudes RGE ALTI des points, dans l'ordre ; null hors couverture (étranger, mer). Chaque
     * point prend l'altitude du nœud de grille le plus proche (NODE_DEG) ; seuls les nœuds absents
     * du cache sont demandés.
     *
     * @param  list<array{0: float, 1: float}>  $points  [lon, lat]
     * @return list<float|null>
     */
    public function elevations(array $points): array
    {
        $keys = [];
        $missing = [];
        foreach ($points as $i => $p) {
            $node = [(int) round($p[0] / self::NODE_DEG), (int) round($p[1] / self::NODE_DEG)];
            $tile = implode('_', self::tileOf($node[0] * self::NODE_DEG, $node[1] * self::NODE_DEG));
            $key = $node[0].':'.$node[1];
            $keys[$i] = [$tile, $key];
            $this->loadNodes($tile);
            if (! array_key_exists($key, $this->nodes[$tile])) {
                $missing[$tile.'|'.$key] = $node;
            }
        }

        foreach (array_chunk($missing, self::ELEVATION_BATCH, true) as $chunk) {
            $payload = [
                'lon' => implode('|', array_map(fn (array $n): string => sprintf('%.4f', $n[0] * self::NODE_DEG), $chunk)),
                'lat' => implode('|', array_map(fn (array $n): string => sprintf('%.4f', $n[1] * self::NODE_DEG), $chunk)),
                'resource' => self::RESOURCE,
                'delimiter' => '|',
                'zonly' => 'true',
            ];
            $json = $this->fetchWithRetries(fn () => Http::withUserAgent(self::USER_AGENT)
                ->timeout(60)
                ->acceptJson()
                ->asJson()
                ->post(self::ELEVATION_URL, $payload));
            $values = $json['elevations'] ?? null;
            if (! is_array($values) || count($values) !== count($chunk)) {
                throw new RuntimeException('Réponse inattendue du service d\'altimétrie de l\'IGN.');
            }
            $touched = [];
            foreach (array_keys($chunk) as $k => $id) {
                [$tile, $key] = explode('|', $id);
                $z = $values[$k];
                $this->nodes[$tile][$key] = is_numeric($z) && (float) $z > self::NO_DATA + 1 ? round((float) $z, 2) : null;
                $touched[$tile] = true;
            }
            foreach (array_keys($touched) as $tile) {
                $this->writeGz('rge-alti-noeuds', $tile, $this->nodes[$tile]);
            }
        }

        return array_map(fn (array $k): ?float => $this->nodes[$k[0]][$k[1]], array_values($keys));
    }

    /**
     * Tronçons de voie ferrée de la BD TOPO (hors tramway et métro) d'une tuile du cache, avec
     * leur position (`ground` au sol, `tunnel`, `bridge`) et l'altitude de chaque sommet : la
     * géométrie de la BD TOPO est en 3D, altitude de la voie (photogrammétrie, ou « Z corrigé »
     * sur les ouvrages). Sommet sans altitude (-1000 dans la source, souvent en tunnel) : null.
     * Un tronçon qui déborde de la tuile est rendu en entier (et par ses voisines aussi).
     *
     * @return list<array{kind: string, coords: list<array{0: float, 1: float}>, z: list<float|null>}>
     */
    public function railTracksInTile(int $x, int $y): array
    {
        $cached = $this->readGz('bdtopo-tuiles', $x.'_'.$y);
        if ($cached === null) {
            $cached = $this->fetchTile($x, $y);
            $this->writeGz('bdtopo-tuiles', $x.'_'.$y, $cached);
        }

        // Forme compacte : [position, [[lon, lat, z|null], …]].
        return array_map(fn (array $t): array => [
            'kind' => (string) $t[0],
            'coords' => array_map(fn (array $c): array => [(float) $c[0], (float) $c[1]], $t[1]),
            'z' => array_map(fn (array $c): ?float => $c[2] === null ? null : (float) $c[2], $t[1]),
        ], $cached);
    }

    /**
     * Tous les tronçons de la tuile, page par page (tri stable sur l'identifiant `cleabs`).
     *
     * @return list<array{0: string, 1: list<array{0: float, 1: float, 2: float|null}>}>
     */
    private function fetchTile(int $x, int $y): array
    {
        $bbox = [$x * self::TILE_DEG, $y * self::TILE_DEG, ($x + 1) * self::TILE_DEG, ($y + 1) * self::TILE_DEG];
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
                'SORTBY' => 'cleabs',
                // Axe CRS84 (lon, lat) : avec EPSG:4326 le service attend lat, lon (cf. mémoire du projet).
                'CQL_FILTER' => sprintf(
                    "nature NOT IN ('Tramway','Métro') AND BBOX(geometrie,%.6f,%.6f,%.6f,%.6f,'urn:ogc:def:crs:OGC:1.3:CRS84')",
                    ...$bbox,
                ),
            ];
            $json = $this->fetchWithRetries(fn () => Http::withUserAgent(self::USER_AGENT)
                ->timeout(90)
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
                    $out[] = [$kind, array_map(
                        fn (array $c, ?float $z): array => [round($c[0], 7), round($c[1], 7), $z === null ? null : round($z, 2)],
                        $line['coords'],
                        $line['z'],
                    )];
                }
            }
            if (count($features) < self::WFS_PAGE) {
                break;
            }
        }

        return $out;
    }

    private function loadNodes(string $tile): void
    {
        if (! isset($this->nodes[$tile])) {
            $this->nodes[$tile] = $this->readGz('rge-alti-noeuds', $tile) ?? [];
        }
    }

    private function tileFile(string $kind, int|string $x, ?int $y = null): string
    {
        return $this->cacheDir.'/'.$kind.'/'.($y === null ? $x : $x.'_'.$y).'.json.gz';
    }

    /** @return array<array-key, mixed>|null */
    private function readGz(string $kind, string $name): ?array
    {
        $file = $this->tileFile($kind, $name);
        if (! is_file($file)) {
            return null;
        }
        $raw = gzdecode((string) file_get_contents($file));
        $json = $raw === false ? null : json_decode($raw, true);

        return is_array($json) ? $json : null;
    }

    /** @param array<array-key, mixed> $data */
    private function writeGz(string $kind, string $name, array $data): void
    {
        $dir = $this->cacheDir.'/'.$kind;
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $marker = $this->cacheDir.'/.nobackup';
        if (! is_file($marker)) {
            file_put_contents($marker, "Réponses des services de l'IGN gardées en cache (régénérables).\n");
        }
        // Écriture atomique : un calcul interrompu ne laisse pas de tuile tronquée.
        $file = $this->tileFile($kind, $name);
        $tmp = $file.'.'.getmypid().'.tmp';
        file_put_contents($tmp, gzencode((string) json_encode($data, JSON_PRESERVE_ZERO_FRACTION), 6));
        rename($tmp, $file);
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
