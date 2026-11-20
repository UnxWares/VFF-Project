<?php

namespace App\Services\Schematic;

use App\Models\Era;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Plan schématique d'une ère prêt à servir (GeoJSON), calculé une fois par construction du graphe de
 * l'ère et gardé en cache : `GET /api/v1/eras/{year}/schematic.geojson` et la « vue ferroviaire » de
 * /maps. Calcul : SchematicGraphLoader (lecture du graphe) puis SchematicLayout (de l'ordre d'une
 * seconde pour l'ère la plus dense) ; un verrou évite deux calculs simultanés de la même ère, et
 * `vff:build-schematic-maps` les prépare d'avance.
 *
 * Clé du cache : ère, date de construction de son graphe (vff:build-network-graph), FORMAT et
 * réglages du plan. Une reconstruction du graphe ou un changement de réglage donne une nouvelle clé.
 *
 * Droits : le plan ne contient aucune géométrie source (voir SchematicLayout) ; il peut sortir pour
 * toutes les lignes, y compris celles dont l'API retient le tracé (`api.withhold_unlicensed_geometry`).
 */
final class SchematicMap
{
    /** Version du contenu : à changer quand la forme de la réponse ou le calcul change. */
    public const FORMAT = 3;

    /** Seuils des niveaux d'étiquettes : 0 (France entière), 1, 2, puis 3 (toutes). */
    public const LABEL_TIERS = [24, 120, 600];

    /**
     * Écart minimal sur le plan (m Web Mercator) entre deux villes nommées au niveau 0, puis au
     * niveau 1 : la France entière nomme des villes éloignées les unes des autres, pas Paris et
     * trois communes de sa banlieue.
     */
    public const CITY_SPACING_M = [80000.0, 30000.0];

    /** Côtés du nom d'une gare envoyés, du meilleur au moins bon (SchematicLabels). */
    public const LABEL_ANCHORS = 4;

    private const TTL_SECONDS = 30 * 24 * 3600;

    public function __construct(
        private readonly SchematicGraphLoader $loader,
        private readonly SchematicLayout $layout,
        private readonly SchematicLabels $labels,
        private readonly SchematicOptions $options = new SchematicOptions,
    ) {}

    /** Date de construction du graphe de l'ère, ou null s'il n'a jamais été construit. */
    public function graphBuiltAt(Era $era): ?string
    {
        $built = DB::table('network_graph_builds')->where('era_id', $era->id)->value('built_at');

        return $built === null ? null : Carbon::parse((string) $built)->toIso8601String();
    }

    /**
     * Plan de l'ère en GeoJSON (texte), ou null si son graphe n'est pas construit.
     */
    public function json(Era $era): ?string
    {
        $built = $this->graphBuiltAt($era);
        if ($built === null) {
            return null;
        }
        $key = $this->cacheKey($era, $built);
        $cached = Cache::get($key);
        if (is_string($cached)) {
            return $cached;
        }

        return Cache::lock("schematic-map:lock:{$era->id}", 120)->block(110, function () use ($era, $built, $key): string {
            $cached = Cache::get($key);
            if (is_string($cached)) {
                return $cached;
            }
            $json = $this->encode($this->build($era, $built));
            Cache::put($key, $json, self::TTL_SECONDS);

            return $json;
        });
    }

    /**
     * Recalcule le plan de l'ère et le range dans le cache (vff:build-schematic-maps).
     *
     * @return array<string, mixed>|null contenu du plan, null si le graphe n'est pas construit
     */
    public function rebuild(Era $era): ?array
    {
        $built = $this->graphBuiltAt($era);
        if ($built === null) {
            return null;
        }
        $payload = $this->build($era, $built);
        Cache::put($this->cacheKey($era, $built), $this->encode($payload), self::TTL_SECONDS);

        return $payload;
    }

    /** @param  array<string, mixed>  $payload */
    private function encode(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    private function cacheKey(Era $era, string $built): string
    {
        $settings = md5(serialize($this->options));

        return sprintf('schematic-map:%d:%d:%s:%s', self::FORMAT, $era->id, preg_replace('/\D/', '', $built), substr($settings, 0, 12));
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Era $era, string $builtAt): array
    {
        $data = $this->loader->load($era->id);
        $plan = $this->layout->layout($data['graph']);

        $features = [];
        $usedLines = [];
        foreach ($plan->pieces as $piece) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => ['type' => 'LineString', 'coordinates' => array_map(self::lngLat(...), $piece['coords'])],
                'properties' => ['kind' => 'axis', 'line_id' => $piece['line'], 'category' => $piece['category']],
            ];
            if ($piece['line'] !== null) {
                $usedLines[$piece['line']] = true;
            }
        }
        $rows = array_values(array_filter(
            $this->rankStations($plan->stations, $data['stations']),
            fn (array $row): bool => isset($data['stations'][$row['station']]),
        ));
        $sides = $this->labels->rankings($plan->pieces, array_map(fn (array $row): array => [
            'x' => $row['x'],
            'y' => $row['y'],
            'tier' => $row['tier'],
            'name' => (string) $data['stations'][$row['station']]['name'],
            'city' => $row['city_label'],
        ], $rows));
        foreach ($rows as $k => $row) {
            $info = $data['stations'][$row['station']];
            $features[] = [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => self::lngLat([$row['x'], $row['y']])],
                'properties' => [
                    'kind' => 'station',
                    'id' => $row['station'],
                    'slug' => $info['slug'],
                    'name' => $info['name'],
                    'city' => $info['city'],
                    'department_code' => $info['department_code'],
                    'status' => $info['status'],
                    'role' => $row['kind'],
                    'main' => $row['main'],
                    'angle' => $row['angle'],
                    'rank' => $row['rank'],
                    'tier' => $row['tier'],
                    'city_label' => $row['city_label'],
                    'label_anchors' => array_slice($sides[$k], 0, self::LABEL_ANCHORS),
                ],
            ];
        }

        $km = [];
        foreach ($data['graph']->edges as $edge) {
            if ($edge['category'] !== 'connector') {
                $km[$edge['category']] = ($km[$edge['category']] ?? 0.0) + $edge['length'] / 1000;
            }
        }
        ksort($km);
        $lines = array_intersect_key($data['lines'], $usedLines);

        return [
            'type' => 'FeatureCollection',
            'era' => (int) $era->year,
            'is_current' => (bool) $era->is_current,
            'graph_built_at' => $builtAt,
            'grid_m' => (int) $plan->cellM,
            'summary' => [
                'km' => array_map(fn (float $v): int => (int) round($v), $km),
                'stations' => count($plan->stations),
                'axes' => $plan->stats['axes'],
                'lines' => count($lines),
            ],
            // Objet JSON même vide (identifiant de ligne → fiche courte).
            'lines' => (object) array_combine(array_map('strval', array_keys($lines)), array_values($lines)),
            'features' => $features,
        ];
    }

    /**
     * Rang (importance), niveau d'étiquette et nom de ville de chaque gare.
     *
     * Rang : poids de la gare (population de sa commune, puis gare plutôt que halte, gare
     * principale : voir SchematicGraphLoader) et rôle (nœud de plusieurs axes, terminus).
     *
     * Niveaux 0 et 1 (France entière, puis une région) : des villes. Les communes sont prises par
     * population décroissante, une gare chacune, à CITY_SPACING_M au moins des villes déjà nommées
     * (la France entière nomme des villes éloignées, pas Paris et trois communes de sa banlieue).
     * Chaque ville est représentée par sa gare principale (`representative`), et son nœud porte le
     * nom de la commune (`city_label` : « Nantes », « Marseille ») jusqu'à ce que le plan montre les
     * noms des gares (SchematicLabels::STATION_NAMES_ZOOM). Niveau 2 : les gares suivantes par rang ;
     * niveau 3 : toutes les autres.
     *
     * @param  list<array{station: int, x: float, y: float, angle: int, kind: string, main: bool, degree: int}>  $stations
     * @param  array<int, array<string, mixed>>  $info
     * @return list<array{station: int, x: float, y: float, angle: int, kind: string, main: bool, degree: int, rank: int, tier: int, city_label: ?string}>
     */
    private function rankStations(array $stations, array $info): array
    {
        foreach ($stations as $k => $row) {
            $weight = (float) ($info[$row['station']]['weight'] ?? 0.0);
            $stations[$k]['rank'] = (int) round(10 * $weight) + self::role($row) + ($row['main'] ? 0 : -20);
            $stations[$k]['tier'] = count(self::LABEL_TIERS);
            $stations[$k]['city_label'] = null;
        }

        // Villes : gares qui nomment un nœud, groupées par commune ; la gare représentante de chacune.
        $cities = [];
        foreach ($stations as $k => $row) {
            $commune = $info[$row['station']]['commune'] ?? null;
            if ($row['main'] && is_string($commune) && $commune !== '') {
                $cities[mb_strtolower($commune).'|'.($info[$row['station']]['department_code'] ?? '')][] = $k;
            }
        }
        $representatives = [];
        foreach ($cities as $list) {
            usort($list, fn (int $a, int $b): int => self::representative($stations[$b], $info) <=> self::representative($stations[$a], $info));
            $representatives[] = $list[0];
        }
        usort($representatives, fn (int $a, int $b): int => [
            (int) ($info[$stations[$b]['station']]['population'] ?? 0), $stations[$b]['rank'], $stations[$a]['station'],
        ] <=> [
            (int) ($info[$stations[$a]['station']]['population'] ?? 0), $stations[$a]['rank'], $stations[$b]['station'],
        ]);
        $named = [];
        $filled = [0, 0];
        foreach ($representatives as $k) {
            foreach ([0, 1] as $level) {
                $room = self::LABEL_TIERS[$level] - ($level > 0 ? self::LABEL_TIERS[$level - 1] : 0);
                if ($filled[$level] < $room && self::spaced($stations[$k], $named, $level)) {
                    $stations[$k]['tier'] = $level;
                    $stations[$k]['city_label'] = (string) $info[$stations[$k]['station']]['commune'];
                    $named[] = [$stations[$k]['x'], $stations[$k]['y'], $level];
                    $filled[$level]++;
                    break;
                }
            }
        }

        // Niveau 2 : les gares suivantes qui nomment un nœud, par rang.
        $order = array_keys($stations);
        usort($order, fn (int $a, int $b): int => [$stations[$b]['rank'], $stations[$a]['station']] <=> [$stations[$a]['rank'], $stations[$b]['station']]);
        $room = self::LABEL_TIERS[2] - self::LABEL_TIERS[1];
        foreach ($order as $k) {
            if ($room === 0) {
                break;
            }
            if ($stations[$k]['main'] && $stations[$k]['tier'] > 1) {
                $stations[$k]['tier'] = 2;
                $room--;
            }
        }

        return $stations;
    }

    /** Part du rôle dans le rang : nœud de plusieurs axes (jusqu'à 4), terminus (2), gare intermédiaire (0). */
    private static function role(array $row): int
    {
        return match ($row['kind']) {
            'hub' => min($row['degree'], 4),
            'end' => 2,
            default => 0,
        };
    }

    /**
     * Ordre des gares d'une ville pour choisir celle qui la représente : la gare qui porte le nom de
     * la ville seul ou suivi de « Ville » ou « Centre » (« Nantes », « Metz-Ville »), puis une gare
     * dont le nom commence par celui de la ville (« Marseille-St-Charles » avant « L'Estaque »), une
     * gare avant une halte, un nœud du plan avant une gare intermédiaire, le nœud où se rejoignent
     * le plus d'axes, le rang.
     *
     * @param  array{station: int, kind: string, degree: int, rank: int}  $row
     * @param  array<int, array<string, mixed>>  $info
     * @return list<int>
     */
    private static function representative(array $row, array $info): array
    {
        $station = $info[$row['station']];

        return [
            (int) ($station['principal'] ?? false),
            (int) ($station['named_after'] ?? false),
            (int) (($station['type'] ?? null) === 'gare'),
            (int) ($row['kind'] !== 'stop'),
            $row['degree'],
            $row['rank'],
            -$row['station'],
        ];
    }

    /**
     * La gare est-elle assez loin, sur le plan, des villes déjà nommées aux niveaux 0 à `$level` ?
     *
     * @param  array{x: float, y: float}  $row
     * @param  list<array{0: float, 1: float, 2: int}>  $named
     */
    private static function spaced(array $row, array $named, int $level): bool
    {
        $min = self::CITY_SPACING_M[$level];

        return ! array_any($named, fn (array $c): bool => $c[2] <= $level && hypot($c[0] - $row['x'], $c[1] - $row['y']) < $min);
    }

    /**
     * @param  array{0: float, 1: float}  $xy
     * @return array{0: float, 1: float}
     */
    private static function lngLat(array $xy): array
    {
        [$lng, $lat] = Mercator::unproject($xy[0], $xy[1]);

        return [round($lng, 5), round($lat, 5)];
    }
}
