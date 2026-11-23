<?php

namespace App\Services\Media\Panoramax;

use App\Services\StationAudit\BdtopoStationZones;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Abords d'une gare pour le tri des photos Panoramax (StationScene), tirés de sources ouvertes :
 * - bâtiments de la BD TOPO de l'IGN (Licence Ouverte 2.0), WFS de la Géoplateforme, une requête par gare (boîte de
 *   RADIUS_M), réponse gardée dans `storage/app/data/panoramax/bdtopo/` (`.nobackup`), une seconde entre deux appels ;
 * - emprise de la gare : zone « équipement de transport » de nature gare de la BD TOPO (cache de BdtopoStationZones,
 *   dalles de 1°) ;
 * - quais de SNCF Réseau (liste des quais, ODbL, fichier local `storage/app/sources/sncf-quais/liste-des-quais.json`) ;
 * - bâtiments voyageurs et quais d'OpenStreetMap (ODbL) : `building=train_station`, bâtiment marqué
 *   `railway=station`, `historic=railway_station` ou ancienne gare (`disused:`, `abandoned:`), `railway=platform` ;
 *   une seule requête Overpass pour toute la France, gardée dans `storage/app/data/panoramax/osm-gares.json`.
 *
 * Bâtiments de la gare (cibles) : d'abord les bâtiments voyageurs OSM à TARGET_MAX_M de la gare ; à défaut, les
 * bâtiments BD TOPO de ZONE_TARGET_M2 m² au moins qui touchent l'emprise BD TOPO de la gare (zone à ZONE_MAX_M de la
 * gare au plus) et dont un point est à TARGET_MAX_M de la gare. Sans l'un ni l'autre (gare fermée dont rien ne dit
 * où est le bâtiment), aucune cible : seuls les quais comptent. Les autres bâtiments BD TOPO font obstacle, sauf
 * ceux qui sont la cible elle-même (le centre de l'un dans l'autre : même bâtiment dans les deux sources).
 */
class PanoramaxSceneBuilder
{
    public const WFS_URL = 'https://data.geopf.fr/wfs/ows';

    public const RADIUS_M = 170;

    public const ZONE_MAX_M = 120;

    public const TARGET_MAX_M = 120;

    public const ZONE_TARGET_M2 = 15;

    public const PLATFORM_MAX_M = 250;

    public const OVERPASS_URL = 'https://overpass-api.de/api/interpreter';

    /** Requête unique : bâtiments voyageurs (en service ou anciens) et quais ferroviaires de France. */
    public const OVERPASS_QUERY = <<<'QL'
        [out:json][timeout:900];
        area["ISO3166-1"="FR"][admin_level=2]->.fr;
        (
          way["building"="train_station"](area.fr);
          way["building"]["railway"="station"](area.fr);
          way["building"]["historic"="railway_station"](area.fr);
          way["building"]["disused:railway"="station"](area.fr);
          way["building"]["abandoned:railway"="station"](area.fr);
          way["railway"="platform"](area.fr);
        );
        out geom qt;
        QL;

    /** @var ?list<array{0: float, 1: float, 2: float, 3: float}> quais [lng1, lat1, lng2, lat2] */
    private ?array $platforms = null;

    /** @var ?array<string, list<array{0: string, 1: list<array{0: float, 1: float}>}>> OSM par case de 0,01° : [b|p, [[lng, lat]…]] */
    private ?array $osm = null;

    private float $lastCall = 0.0;

    public int $networkCalls = 0;

    public function __construct(
        private readonly BdtopoStationZones $zones,
        private readonly ?string $cacheDir = null,
        private readonly int $pauseMs = 1000,
        private readonly ?string $platformsFile = null,
        private readonly ?string $osmFile = null,
    ) {}

    /**
     * Abords de la gare, null si les bâtiments ne sont pas en cache et que `$cacheOnly` interdit le réseau.
     */
    public function scene(float $lat, float $lng, bool $cacheOnly = false): ?StationScene
    {
        $buildings = $this->buildings($lat, $lng, $cacheOnly);
        if ($buildings === null) {
            return null;
        }
        $empty = new StationScene($lat, $lng);
        $zone = $this->zone($empty, $lat, $lng, $cacheOnly);

        $project = fn (array $ring): array => array_map(fn (array $c): array => $empty->project((float) $c[1], (float) $c[0]), $ring);
        $distance = fn (array $poly): float => (new StationScene($lat, $lng, targets: [$poly]))->targetDistance(0.0, 0.0) ?? INF;

        $platforms = [];
        $osmTargets = [];
        foreach ($this->osmNear($lat, $lng, $cacheOnly) as [$kind, $ring]) {
            $poly = $project($ring);
            if ($kind === 'b' && count($poly) >= 3 && $distance($poly) <= self::TARGET_MAX_M) {
                $osmTargets[] = $poly;
            } elseif ($kind === 'p') {
                for ($i = 0; $i + 1 < count($poly); $i++) {
                    $platforms[] = [$poly[$i], $poly[$i + 1]];
                }
            }
        }

        $targets = $osmTargets;
        $obstacles = [];
        foreach ($buildings as $ring) {
            $poly = $project($ring);
            if ($osmTargets !== []) {
                // Même bâtiment dans les deux sources : le centre de l'un dans l'autre (un immeuble mitoyen qui touche
                // seulement la gare reste un obstacle).
                $same = array_filter($osmTargets, fn (array $t): bool => self::sameBuilding($poly, $t));
                if ($same === []) {
                    $obstacles[] = $poly;
                }

                continue;
            }
            $isTarget = $zone !== null && StationScene::area($poly) >= self::ZONE_TARGET_M2
                && $distance($poly) <= self::TARGET_MAX_M && self::touches($poly, $zone);
            if ($isTarget) {
                $targets[] = $poly;
            } else {
                $obstacles[] = $poly;
            }
        }

        foreach ($this->platformsNear($lat, $lng) as [$x1, $y1, $x2, $y2]) {
            $platforms[] = [$empty->project($y1, $x1), $empty->project($y2, $x2)];
        }

        return new StationScene($lat, $lng, $targets, $obstacles, $platforms, $zone);
    }

    /**
     * Contours extérieurs des bâtiments BD TOPO dans la boîte de la gare, [lng, lat] (null : hors cache).
     *
     * @return ?list<list<array{0: float, 1: float}>>
     */
    public function buildings(float $lat, float $lng, bool $cacheOnly = false): ?array
    {
        $dLat = self::RADIUS_M / 111_320;
        $dLng = self::RADIUS_M / (111_320 * max(0.2, cos(deg2rad($lat))));
        $bbox = sprintf('%.5F,%.5F,%.5F,%.5F', $lng - $dLng, $lat - $dLat, $lng + $dLng, $lat + $dLat);
        $file = $this->dir().'/'.md5('batiment:'.$bbox).'.json';
        if (is_file($file)) {
            $rings = json_decode((string) file_get_contents($file), true);

            return is_array($rings) ? $rings : [];
        }
        if ($cacheOnly) {
            return null;
        }
        $rings = [];
        foreach ($this->fetch($bbox) as $f) {
            if (($f['properties']['etat_de_l_objet'] ?? 'En service') !== 'En service') {
                continue;
            }
            foreach (self::outerRings($f['geometry'] ?? null) as $ring) {
                $rings[] = array_map(fn (array $c): array => [round((float) $c[0], 7), round((float) $c[1], 7)], $ring);
            }
        }
        file_put_contents($file.'.tmp', json_encode($rings));
        rename($file.'.tmp', $file);

        return $rings;
    }

    /**
     * Emprise de la gare (zone BD TOPO qui contient le point de la gare, sinon la plus proche à ZONE_MAX_M), en mètres.
     *
     * @return ?list<array{0: float, 1: float}>
     */
    private function zone(StationScene $frame, float $lat, float $lng, bool $cacheOnly): ?array
    {
        try {
            $zones = $this->zones->tile((int) floor($lng), (int) floor($lat), $cacheOnly);
        } catch (RuntimeException) {
            return null;
        }
        $best = null;
        $bestDist = INF;
        foreach ($zones as $z) {
            foreach (self::outerRings($z['geometry']) as $ring) {
                $poly = array_map(fn (array $c): array => $frame->project((float) $c[1], (float) $c[0]), $ring);
                $d = (new StationScene($lat, $lng, targets: [$poly]))->targetDistance(0.0, 0.0) ?? INF;
                if ($d < $bestDist) {
                    [$best, $bestDist] = [$poly, $d];
                }
            }
        }

        return $bestDist <= self::ZONE_MAX_M ? $best : null;
    }

    /**
     * Vrai si le centre de l'un des deux contours est dans l'autre.
     *
     * @param  list<array{0: float, 1: float}>  $a
     * @param  list<array{0: float, 1: float}>  $b
     */
    public static function sameBuilding(array $a, array $b): bool
    {
        $centre = fn (array $p): array => [array_sum(array_column($p, 0)) / count($p), array_sum(array_column($p, 1)) / count($p)];
        [$ax, $ay] = $centre($a);
        [$bx, $by] = $centre($b);

        return StationScene::contains($b, $ax, $ay) || StationScene::contains($a, $bx, $by);
    }

    /**
     * Vrai si les deux polygones se touchent (un sommet de l'un dans l'autre, ou deux bords qui se coupent).
     *
     * @param  list<array{0: float, 1: float}>  $a
     * @param  list<array{0: float, 1: float}>  $b
     */
    public static function touches(array $a, array $b): bool
    {
        foreach ($a as [$x, $y]) {
            if (StationScene::contains($b, $x, $y)) {
                return true;
            }
        }
        foreach ($b as [$x, $y]) {
            if (StationScene::contains($a, $x, $y)) {
                return true;
            }
        }
        $na = count($a);
        $nb = count($b);
        for ($i = 0; $i < $na; $i++) {
            [$p, $q] = [$a[$i], $a[($i + 1) % $na]];
            $len = hypot($q[0] - $p[0], $q[1] - $p[1]);
            if ($len <= 0) {
                continue;
            }
            for ($j = 0; $j < $nb; $j++) {
                $r = $b[$j];
                $s = $b[($j + 1) % $nb];
                $d = StationScene::raySegment($p[0], $p[1], ($q[0] - $p[0]) / $len, ($q[1] - $p[1]) / $len, $r[0], $r[1], $s[0], $s[1]);
                if ($d !== null && $d <= $len) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Quais SNCF dont un bout est à PLATFORM_MAX_M de la gare : [lng1, lat1, lng2, lat2].
     *
     * @return list<array{0: float, 1: float, 2: float, 3: float}>
     */
    private function platformsNear(float $lat, float $lng): array
    {
        if ($this->platforms === null) {
            $this->platforms = [];
            $file = $this->platformsFile ?? storage_path('app/sources/sncf-quais/liste-des-quais.json');
            $json = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
            foreach ((array) ($json['records'] ?? []) as $r) {
                if (is_numeric($r['x_d_wgs84'] ?? null) && is_numeric($r['y_d_wgs84'] ?? null) && is_numeric($r['x_f_wgs84'] ?? null) && is_numeric($r['y_f_wgs84'] ?? null)) {
                    $this->platforms[] = [(float) $r['x_d_wgs84'], (float) $r['y_d_wgs84'], (float) $r['x_f_wgs84'], (float) $r['y_f_wgs84']];
                }
            }
        }

        return array_values(array_filter($this->platforms, fn (array $p): bool => PanoramaxSelection::distanceM($p[1], $p[0], $lat, $lng) <= self::PLATFORM_MAX_M
            || PanoramaxSelection::distanceM($p[3], $p[2], $lat, $lng) <= self::PLATFORM_MAX_M));
    }

    /**
     * Bâtiments voyageurs (« b ») et quais (« p ») OSM dont un point est à moins d'environ 0,01° de la gare.
     *
     * @return list<array{0: string, 1: list<array{0: float, 1: float}>}>
     */
    private function osmNear(float $lat, float $lng, bool $cacheOnly): array
    {
        if ($this->osm === null) {
            $this->osm = [];
            // Même dossier que les bâtiments BD TOPO : un test ne lit ni n'écrit jamais le vrai cache.
            $file = $this->osmFile ?? ($this->cacheDir ?? storage_path('app/data/panoramax')).'/osm-gares.json';
            if (! is_file($file)) {
                if ($cacheOnly) {
                    return [];
                }
                $this->dir();
                $this->fetchOsm($file);
            }
            foreach ((array) json_decode((string) file_get_contents($file), true) as $item) {
                if (! is_array($item) || ! in_array($item[0] ?? null, ['b', 'p'], true) || ! is_array($item[1] ?? null) || $item[1] === []) {
                    continue;
                }
                $keys = [];
                foreach ($item[1] as $c) {
                    $keys[(int) floor($c[0] * 100).':'.(int) floor($c[1] * 100)] = true;
                }
                foreach (array_keys($keys) as $k) {
                    $this->osm[$k][] = [$item[0], $item[1]];
                }
            }
        }
        $out = [];
        $seen = [];
        for ($i = -1; $i <= 1; $i++) {
            for ($j = -1; $j <= 1; $j++) {
                foreach ($this->osm[((int) floor($lng * 100) + $i).':'.((int) floor($lat * 100) + $j)] ?? [] as $item) {
                    $key = md5((string) json_encode($item));
                    if (! isset($seen[$key])) {
                        $seen[$key] = true;
                        $out[] = $item;
                    }
                }
            }
        }

        return $out;
    }

    /** Télécharge les bâtiments voyageurs et quais OSM de France (une requête) et les range en format compact. */
    private function fetchOsm(string $file): void
    {
        $this->networkCalls++;
        $res = Http::withUserAgent(PanoramaxClient::USER_AGENT)->timeout(1200)->asForm()
            ->post((string) config('services.osm.overpass_endpoint', self::OVERPASS_URL), ['data' => self::OVERPASS_QUERY]);
        if (! $res->successful() || ! is_array($res->json('elements'))) {
            throw new RuntimeException("Overpass : réponse {$res->status()}");
        }
        $out = [];
        foreach ((array) $res->json('elements') as $e) {
            $tags = (array) ($e['tags'] ?? []);
            $geom = array_values(array_filter((array) ($e['geometry'] ?? []), fn ($g): bool => is_array($g) && is_numeric($g['lon'] ?? null) && is_numeric($g['lat'] ?? null)));
            if (count($geom) < 2) {
                continue;
            }
            $ring = array_map(fn (array $g): array => [round((float) $g['lon'], 7), round((float) $g['lat'], 7)], $geom);
            if (count($ring) > 3 && $ring[0] === $ring[count($ring) - 1]) {
                array_pop($ring);
            }
            $out[] = [($tags['railway'] ?? null) === 'platform' && ! isset($tags['building']) ? 'p' : 'b', $ring];
        }
        file_put_contents($file.'.tmp', json_encode($out));
        rename($file.'.tmp', $file);
    }

    /**
     * Contours extérieurs d'un Polygon ou d'un MultiPolygon GeoJSON (sans le point de fermeture).
     *
     * @return list<list<array<int, float>>>
     */
    public static function outerRings(mixed $geometry): array
    {
        if (! is_array($geometry)) {
            return [];
        }
        $polys = match ($geometry['type'] ?? null) {
            'Polygon' => [$geometry['coordinates'] ?? []],
            'MultiPolygon' => $geometry['coordinates'] ?? [],
            default => [],
        };
        $out = [];
        foreach ((array) $polys as $poly) {
            $ring = array_values(array_filter((array) ($poly[0] ?? []), fn ($c): bool => is_array($c) && is_numeric($c[0] ?? null) && is_numeric($c[1] ?? null)));
            if (count($ring) > 3 && $ring[0][0] === $ring[count($ring) - 1][0] && $ring[0][1] === $ring[count($ring) - 1][1]) {
                array_pop($ring);
            }
            if (count($ring) >= 3) {
                $out[] = $ring;
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function fetch(string $bbox): array
    {
        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $wait = $this->lastCall + $this->pauseMs / 1000 - microtime(true);
            if ($wait > 0) {
                usleep((int) ($wait * 1_000_000));
            }
            $this->lastCall = microtime(true);
            $this->networkCalls++;
            try {
                $res = Http::withUserAgent(PanoramaxClient::USER_AGENT)->timeout(60)->get(self::WFS_URL, [
                    'SERVICE' => 'WFS',
                    'VERSION' => '2.0.0',
                    'REQUEST' => 'GetFeature',
                    'TYPENAMES' => 'BDTOPO_V3:batiment',
                    'OUTPUTFORMAT' => 'application/json',
                    'COUNT' => 2000,
                    'PROPERTYNAME' => 'cleabs,etat_de_l_objet,geometrie',
                    // Axe CRS84 (lon, lat) : avec EPSG:4326 le service attend lat, lon.
                    'CQL_FILTER' => "BBOX(geometrie,{$bbox},'urn:ogc:def:crs:OGC:1.3:CRS84')",
                ]);
            } catch (ConnectionException) {
                $res = null;
            }
            if ($res !== null && $res->successful() && is_array($res->json('features'))) {
                return array_values(array_filter((array) $res->json('features'), 'is_array'));
            }
            if ($res !== null && ! $res->successful() && ! in_array($res->status(), [429, 500, 502, 503, 504], true)) {
                throw new RuntimeException("WFS de l'IGN : HTTP {$res->status()}");
            }
            usleep(min(30, 5 * $attempt) * 1_000_000);
        }
        throw new RuntimeException("WFS de l'IGN : trop de reprises.");
    }

    private function dir(): string
    {
        $dir = ($this->cacheDir ?? storage_path('app/data/panoramax')).'/bdtopo';
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $root = dirname($dir);
        if (! is_file($root.'/.nobackup')) {
            touch($root.'/.nobackup');
        }

        return $dir;
    }
}
