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
    public const FORMAT = 1;

    /** Seuils des niveaux d'étiquettes : 0 (France entière), 1, 2, puis 3 (toutes). */
    public const LABEL_TIERS = [24, 120, 600];

    private const TTL_SECONDS = 30 * 24 * 3600;

    public function __construct(
        private readonly SchematicGraphLoader $loader,
        private readonly SchematicLayout $layout,
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
        foreach ($this->rankStations($plan->stations, $data['stations']) as $row) {
            $info = $data['stations'][$row['station']] ?? null;
            if ($info === null) {
                continue;
            }
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
     * Rang (importance) et niveau d'étiquette de chaque gare. Rang : poids de la gare (population de
     * sa commune, gare plutôt que halte) puis rôle (nœud de plusieurs axes, terminus). Niveaux : les
     * LABEL_TIERS premières gares qui nomment un nœud, une seule par commune aux niveaux 0 et 1, pour
     * que la France entière montre des villes différentes ; les autres gares au dernier niveau.
     *
     * @param  list<array{station: int, x: float, y: float, angle: int, kind: string, main: bool, degree: int}>  $stations
     * @param  array<int, array<string, mixed>>  $info
     * @return list<array{station: int, x: float, y: float, angle: int, kind: string, main: bool, degree: int, rank: int, tier: int}>
     */
    private function rankStations(array $stations, array $info): array
    {
        foreach ($stations as $k => $row) {
            $weight = (float) ($info[$row['station']]['weight'] ?? 0.0);
            $role = match ($row['kind']) {
                'hub' => min($row['degree'], 4),
                'end' => 2,
                default => 0,
            };
            $stations[$k]['rank'] = (int) round(10 * $weight) + $role + ($row['main'] ? 0 : -20);
        }
        $order = array_keys($stations);
        usort($order, fn (int $a, int $b): int => [$stations[$b]['rank'], $stations[$a]['station']] <=> [$stations[$a]['rank'], $stations[$b]['station']]);
        $cities = [];
        $filled = array_fill(0, count(self::LABEL_TIERS), 0);
        foreach ($order as $k) {
            $row = $stations[$k];
            $tier = count(self::LABEL_TIERS);
            if ($row['main']) {
                $city = mb_strtolower((string) ($info[$row['station']]['commune'] ?? $row['station'])).'|'.($info[$row['station']]['department_code'] ?? '');
                foreach (self::LABEL_TIERS as $level => $limit) {
                    $room = $limit - ($level > 0 ? self::LABEL_TIERS[$level - 1] : 0);
                    if ($filled[$level] < $room && ($level >= 2 || ! isset($cities[$city]))) {
                        $tier = $level;
                        $filled[$level]++;
                        break;
                    }
                }
                if ($tier < 2) {
                    $cities[$city] = true;
                }
            }
            $stations[$k]['tier'] = $tier;
        }

        return $stations;
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
