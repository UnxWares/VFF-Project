<?php

namespace App\Services\Map;

use App\Enums\SignallingFamily;
use App\Models\Era;
use App\Services\StationLineCoverage;
use App\Support\Lgv;
use App\Support\SpeedClass;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alternative textuelle à la carte /maps : les lignes et les gares d'une emprise à une ère, sous une forme
 * compacte (liste lue par un lecteur d'écran, au clavier, sur petit écran).
 *
 * Chaque ligne de la liste est une ligne VFF dans UN état de la légende (`state` : mêmes clés que
 * TOGGLE_KEYS de railStyle.js, `lgv`, `mixed`, `freight`, `unknown`, `heritage`, `velorail`,
 * `service_track`, `construction`, `abandoned`, `dismantled`) avec ses kilomètres dans l'emprise. Une ligne
 * dont les portions n'ont pas le même état y figure donc plusieurs fois. Les gares portent le statut de la
 * carte (`active`, `tourist`, `no_commercial`, `uncertain`, `declassified`).
 *
 * Avec une coloration des tracés (`$color` : COLORINGS, le choix « Colorer les tracés selon » de la carte),
 * chaque ligne porte aussi `classes` : ses classes dans l'emprise (vitesse, électrification, écartement,
 * famille de signalisation) avec leurs kilomètres, les plus longues d'abord, et une liste vide quand rien
 * n'est connu. La vitesse et l'électrification ne concernent que les portions en service ; la signalisation
 * vient des tranches placées sur la carte (SNCF Réseau aujourd'hui, mises en service datées aux ères passées).
 *
 * L'emprise est bornée (MAX_LON_SPAN × MAX_LAT_SPAN degrés) : au-delà, aucune liste n'est calculée
 * (`too_wide`), pour ne jamais découper tout le réseau. Les listes sont plafonnées à LIMIT entrées (les
 * lignes les plus longues dans l'emprise, les gares par nom) ; `total` donne le nombre complet.
 */
final class ViewListing
{
    /** Largeur maximale de l'emprise (degrés de longitude). */
    public const MAX_LON_SPAN = 4.0;

    /** Hauteur maximale de l'emprise (degrés de latitude). */
    public const MAX_LAT_SPAN = 3.0;

    /** Entrées gardées par liste. */
    public const LIMIT = 300;

    /** Colorations des tracés que la liste sait décrire (paramètre `color` de l'API). */
    public const COLORINGS = ['speed', 'electrification', 'gauge', 'signalling'];

    /** Statuts d'une portion en service (vitesse et électrification n'ont de sens que là). */
    private const IN_SERVICE = "('in_service', 'single_track', 'service_track')";

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}  $bbox  ouest, sud, est, nord (degrés WGS 84)
     * @param  string|null  $color  coloration des tracés (COLORINGS) : ajoute `classes` à chaque ligne
     * @return array{era: int, bbox: list<float>, too_wide: bool, limit: int, lines: array{total: int, items: list<array{slug: string, name: string, code: ?string, state: string, km: float, classes?: list<array{key: string, km: float}>}>}, stations: array{total: int, items: list<array{slug: string, name: string, city: ?string, state: string}>}}
     */
    public function list(int $eraYear, array $bbox, ?string $color = null): array
    {
        $era = Era::where('year', $eraYear)->firstOrFail();
        $empty = ['total' => 0, 'items' => []];
        $result = [
            'era' => $eraYear,
            'bbox' => $bbox,
            'too_wide' => self::tooWide($bbox),
            'limit' => self::LIMIT,
            'lines' => $empty,
            'stations' => $empty,
        ];
        if ($result['too_wide']) {
            return $result;
        }
        // Les gares d'abord : requête courte, et sa lecture ne dépend pas des tracés.
        $result['stations'] = $this->stations($era, $bbox);
        $result['lines'] = $this->lines($era, $bbox);
        if ($color !== null && in_array($color, self::COLORINGS, true)) {
            $result['lines']['items'] = $this->withClasses($result['lines']['items'], $era, $bbox, $color);
        }

        return $result;
    }

    /**
     * Ajoute à chaque ligne listée ses classes de la coloration dans l'emprise (kilomètres par classe).
     *
     * @param  list<array{slug: string, name: string, code: ?string, state: string, km: float}>  $items
     * @param  array{0: float, 1: float, 2: float, 3: float}  $bbox
     * @return list<array{slug: string, name: string, code: ?string, state: string, km: float, classes: list<array{key: string, km: float}>}>
     */
    private function withClasses(array $items, Era $era, array $bbox, string $color): array
    {
        $slugs = array_values(array_unique(array_column($items, 'slug')));
        $byLine = $slugs === [] ? [] : ($color === 'signalling'
            ? $this->signallingClasses($era, $bbox, $slugs)
            : $this->segmentClasses($era, $bbox, $slugs, $color));
        // Les classes décrivent la partie en service : une entrée fermée ou déposée de la même ligne n'en a pas.
        $inService = ['lgv', 'mixed', 'freight', 'unknown', 'heritage', 'service_track'];

        return array_map(fn (array $item): array => [
            ...$item,
            'classes' => in_array($item['state'], $inService, true) || $color === 'gauge'
                ? ($byLine[$item['slug']] ?? [])
                : [],
        ], $items);
    }

    /**
     * Classes lues sur les portions de la carte (`line_segments` de l'ère), par ligne.
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}  $bbox
     * @param  list<string>  $slugs
     * @return array<string, list<array{key: string, km: float}>>
     */
    private function segmentClasses(Era $era, array $bbox, array $slugs, string $color): array
    {
        $inService = 'ls.status IN '.self::IN_SERVICE;
        $speed = SpeedClass::sql('ls.max_speed_kmh');
        $speedKeys = '';
        foreach (SpeedClass::KEYS as $class => $key) {
            $speedKeys .= " WHEN {$class} THEN '{$key}'";
        }
        // Mêmes classes que la carte (electrificationColorExpr et gaugeColorExpr de lib/railStyle.js).
        $class = match ($color) {
            'speed' => "CASE WHEN {$inService} THEN COALESCE(CASE {$speed}{$speedKeys} END, 'unknown') END",
            'electrification' => "CASE WHEN NOT ({$inService}) THEN NULL
                WHEN ls.electrified THEN CASE
                    WHEN COALESCE(ls.voltage_v, 0) = 0 THEN 'unknown_voltage'
                    WHEN ls.voltage_v = 25000 THEN 'ac25'
                    WHEN ls.voltage_v = 1500 THEN 'dc1500'
                    WHEN ls.voltage_v = 3000 THEN 'dc3000'
                    WHEN ls.voltage_v = 15000 THEN 'ac15'
                    WHEN ls.voltage_v <= 1000 THEN 'low'
                    ELSE 'other' END
                WHEN ls.electrification_source = 'unknown' THEN 'unknown_date'
                ELSE 'none' END",
            default => "COALESCE(NULLIF(ls.gauge_class, ''), 'unknown')",
        };
        $placeholders = implode(', ', array_fill(0, count($slugs), '?'));
        $rows = DB::select(<<<SQL
            WITH box AS (SELECT ST_MakeEnvelope(?, ?, ?, ?, 4326) AS g)
            SELECT l.slug, {$class} AS class, SUM(ST_Length(ST_Intersection(ls.geom, box.g)::geography)) AS m
            FROM line_segments ls
            INNER JOIN lines l ON l.id = ls.line_id
            CROSS JOIN box
            WHERE ls.era_id = ? AND ls.geom && box.g AND l.slug IN ({$placeholders})
            GROUP BY 1, 2
        SQL, [...$bbox, (int) $era->id, ...$slugs]);

        return self::groupClasses($rows);
    }

    /**
     * Familles de signalisation des tranches placées sur la carte (calque « Signalisation »), par ligne.
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}  $bbox
     * @param  list<string>  $slugs
     * @return array<string, list<array{key: string, km: float}>>
     */
    private function signallingClasses(Era $era, array $bbox, array $slugs): array
    {
        $placeholders = implode(', ', array_fill(0, count($slugs), '?'));
        if ($era->is_current) {
            if (! DB::scalar("SELECT to_regclass('line_signalling_map') IS NOT NULL")) {
                return [];
            }
            $family = SignallingFamily::sql('m.signalling');
            $from = 'line_signalling_map m';
        } else {
            if (! Schema::hasTable('line_signalling_history_map')) {
                return [];
            }
            $family = 'm.family';
            $from = 'line_signalling_history_map m';
        }
        $rows = DB::select(<<<SQL
            WITH box AS (SELECT ST_MakeEnvelope(?, ?, ?, ?, 4326) AS g)
            SELECT l.slug, {$family} AS class, SUM(ST_Length(ST_Intersection(m.geom, box.g)::geography)) AS m
            FROM {$from}
            INNER JOIN lines l ON l.id = m.line_id
            CROSS JOIN box
            WHERE m.era_id = ? AND m.geom && box.g AND l.slug IN ({$placeholders})
            GROUP BY 1, 2
        SQL, [...$bbox, (int) $era->id, ...$slugs]);

        return self::groupClasses($rows);
    }

    /**
     * @param  array<int, object>  $rows  lignes `slug`, `class`, `m` (mètres)
     * @return array<string, list<array{key: string, km: float}>>
     */
    private static function groupClasses(array $rows): array
    {
        $byLine = [];
        foreach ($rows as $row) {
            $km = round(((float) $row->m) / 1000, 1);
            if ($row->class === null || $km <= 0) {
                continue;
            }
            $byLine[(string) $row->slug][] = ['key' => (string) $row->class, 'km' => $km];
        }
        foreach ($byLine as &$classes) {
            usort($classes, fn (array $a, array $b): int => $b['km'] <=> $a['km'] ?: strcmp($a['key'], $b['key']));
        }

        return $byLine;
    }

    /** @param  array{0: float, 1: float, 2: float, 3: float}  $bbox */
    public static function tooWide(array $bbox): bool
    {
        return ($bbox[2] - $bbox[0]) > self::MAX_LON_SPAN || ($bbox[3] - $bbox[1]) > self::MAX_LAT_SPAN;
    }

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}  $bbox
     * @return array{total: int, items: list<array{slug: string, name: string, code: ?string, state: string, km: float}>}
     */
    private function lines(Era $era, array $bbox): array
    {
        $lgv = Lgv::sql('l', 'ls');
        $rows = DB::select(<<<SQL
            WITH box AS (SELECT ST_MakeEnvelope(?, ?, ?, ?, 4326) AS g),
            parts AS (
                SELECT l.slug, l.name, l.code,
                    CASE
                        WHEN COALESCE(ls.is_heritage, false) AND ls.status IN ('in_service', 'single_track') THEN 'heritage'
                        WHEN ls.status IN ('dismantled', 'razed') THEN 'dismantled'
                        WHEN COALESCE(ls.is_heritage, false) THEN 'velorail'
                        WHEN ls.status IN ('in_service', 'single_track') THEN
                            CASE WHEN {$lgv} OR ls.traffic_type = 'passenger' THEN 'lgv'
                                 WHEN ls.traffic_type IN ('mixed', 'freight') THEN ls.traffic_type
                                 ELSE 'unknown' END
                        WHEN ls.status IN ('service_track', 'construction', 'abandoned') THEN ls.status
                        ELSE 'unknown'
                    END AS state,
                    ST_Length(ST_Intersection(ls.geom, box.g)::geography) AS m
                FROM line_segments ls
                INNER JOIN lines l ON l.id = ls.line_id
                CROSS JOIN box
                WHERE ls.era_id = ? AND ls.geom && box.g
            ),
            grouped AS (
                SELECT slug, name, code, state, SUM(m) AS m FROM parts GROUP BY slug, name, code, state
            )
            SELECT slug, name, code, state, m, COUNT(*) OVER () AS total
            FROM grouped
            ORDER BY m DESC, name
            LIMIT ?
        SQL, [...$bbox, (int) $era->id, self::LIMIT]);

        return [
            'total' => (int) ($rows[0]->total ?? 0),
            'items' => array_map(fn (object $r): array => [
                'slug' => (string) $r->slug,
                'name' => (string) $r->name,
                'code' => $r->code === null ? null : (string) $r->code,
                'state' => (string) $r->state,
                'km' => round(((float) $r->m) / 1000, 1),
            ], $rows),
        ];
    }

    /**
     * Gares de l'emprise, avec la règle de la carte (StationLineCoverage::ISOLATION_RADIUS_M : une gare fermée
     * n'est montrée que si une voie est encore en place à côté).
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}  $bbox
     * @return array{total: int, items: list<array{slug: string, name: string, city: ?string, state: string}>}
     */
    private function stations(Era $era, array $bbox): array
    {
        $eraId = (int) $era->id;
        if (! DB::table('station_eras')->where('era_id', $eraId)->exists()) {
            // Ère pas encore calculée : seule l'ère courante montre ses gares, d'après leur statut actuel.
            if (! $era->is_current) {
                return ['total' => 0, 'items' => []];
            }
            $join = "JOIN (SELECT id AS station_id, status, FALSE AS uncertain FROM stations WHERE status <> 'declassified') se ON se.station_id = s.id";
        } else {
            $join = "JOIN station_eras se ON se.station_id = s.id AND se.era_id = {$eraId}";
        }
        $radius = StationLineCoverage::ISOLATION_RADIUS_M;
        $rows = DB::select(<<<SQL
            WITH box AS (SELECT ST_MakeEnvelope(?, ?, ?, ?, 4326) AS g)
            SELECT s.slug, s.name, s.city, se.status, se.uncertain, COUNT(*) OVER () AS total
            FROM stations s
            {$join}
            CROSS JOIN box
            WHERE s.geom && box.g
              AND (se.status <> 'declassified'
                   OR EXISTS (
                       SELECT 1 FROM line_segments ls
                       WHERE ls.era_id = {$eraId} AND ls.status NOT IN ('dismantled', 'razed')
                         AND ls.geom && ST_Expand(s.geom, 0.005)
                         AND ST_DWithin(ls.geom::geography, s.geom::geography, {$radius})))
            ORDER BY s.name
            LIMIT ?
        SQL, [...$bbox, self::LIMIT]);

        return [
            'total' => (int) ($rows[0]->total ?? 0),
            'items' => array_map(fn (object $r): array => [
                'slug' => (string) $r->slug,
                'name' => (string) $r->name,
                'city' => $r->city === null ? null : (string) $r->city,
                'state' => $r->uncertain && $r->status !== 'declassified' ? 'uncertain' : (string) $r->status,
            ], $rows),
        ];
    }
}
