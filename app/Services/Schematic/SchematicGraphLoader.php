<?php

namespace App\Services\Schematic;

use App\Support\Lgv;
use Illuminate\Support\Facades\DB;

/**
 * Lecture du graphe d'une ère pour le plan schématique (lecture seule) : topologie construite par
 * vff:build-network-graph (network_nodes, network_edges), gares ouvertes posées sur ses nœuds, et
 * fiches des lignes et des gares que le plan affiche.
 *
 * Catégorie d'une arête, comme la couleur de la carte (lib/railStyle.js, segmentColorExpr) :
 * associative (`heritage`) d'abord, puis grande vitesse (`lgv` : règle App\Support\Lgv, ou trafic
 * « voyageurs seuls »), puis le trafic de la portion (`mixed`, `freight`, `unknown`) ; un raccord de
 * lacune est `connector`.
 */
final class SchematicGraphLoader
{
    /**
     * @return array{graph: SchematicGraph, lines: array<int, array<string, mixed>>, stations: array<int, array<string, mixed>>}
     */
    public function load(int $eraId): array
    {
        $nodes = [];
        $nodeStations = [];
        $rows = DB::select(<<<'SQL'
            SELECT n.id, ST_X(p.g) AS x, ST_Y(p.g) AS y,
                   CASE WHEN n.kind = 'station' THEN n.station_id END AS station_id
            FROM network_nodes n
            CROSS JOIN LATERAL (SELECT ST_Transform(n.geom, 3857) AS g) p
            WHERE n.era_id = ?
            SQL, [$eraId]);
        foreach ($rows as $row) {
            $nodes[(int) $row->id] = [(float) $row->x, (float) $row->y];
            if ($row->station_id !== null) {
                $nodeStations[(int) $row->id] = (int) $row->station_id;
            }
        }

        $lgv = Lgv::sql('l', null);
        $edges = [];
        $lineIds = [];
        $rows = DB::select(<<<SQL
            SELECT e.from_node_id AS u, e.to_node_id AS v, e.length_m, e.line_id,
                   CASE WHEN e.is_connector THEN 'connector'
                        WHEN e.is_heritage THEN 'heritage'
                        WHEN e.traffic_type = 'passenger' OR (l.id IS NOT NULL AND {$lgv}) THEN 'lgv'
                        WHEN e.traffic_type IN ('mixed', 'freight') THEN e.traffic_type
                        ELSE 'unknown' END AS category
            FROM network_edges e
            LEFT JOIN lines l ON l.id = e.line_id
            WHERE e.era_id = ?
            ORDER BY e.id
            SQL, [$eraId]);
        foreach ($rows as $row) {
            $line = $row->line_id === null ? null : (int) $row->line_id;
            $edges[] = [
                'u' => (int) $row->u,
                'v' => (int) $row->v,
                'length' => (float) $row->length_m,
                'line' => $line,
                'category' => (string) $row->category,
            ];
            if ($line !== null) {
                $lineIds[$line] = true;
            }
        }

        $stations = $this->stations($eraId, array_values(array_unique($nodeStations)));
        $weights = array_map(fn (array $s): float => (float) $s['weight'], $stations);

        return [
            'graph' => new SchematicGraph($nodes, $edges, $nodeStations, $weights),
            'lines' => $this->lines(array_keys($lineIds)),
            'stations' => $stations,
        ];
    }

    /**
     * Fiches courtes des lignes (mêmes champs que les tuiles de la carte, pour la fiche latérale).
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function lines(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $lgv = Lgv::sql('l', null);
        $out = [];
        foreach (array_chunk($ids, 5000) as $chunk) {
            $rows = DB::select(<<<SQL
                SELECT l.id, l.slug, l.name, l.code, l.network, {$lgv} AS is_lgv,
                       l.max_speed_kmh, l.electrified, l.voltage_v
                FROM lines l
                WHERE l.id = ANY(?::bigint[])
                SQL, ['{'.implode(',', $chunk).'}']);
            foreach ($rows as $row) {
                $out[(int) $row->id] = [
                    'slug' => $row->slug,
                    'name' => $row->name,
                    'code' => $row->code,
                    'network' => $row->network,
                    'is_lgv' => (bool) $row->is_lgv,
                    'max_speed_kmh' => $row->max_speed_kmh === null ? null : (int) $row->max_speed_kmh,
                    'electrified' => $row->electrified === null ? null : (bool) $row->electrified,
                    'voltage_v' => $row->voltage_v === null ? null : (int) $row->voltage_v,
                ];
            }
        }
        ksort($out);

        return $out;
    }

    /**
     * Gares ouvertes du plan, avec leur statut à l'ère et un poids qui choisit la gare qui nomme un
     * nœud et l'ordre des étiquettes : population de la commune (INSEE, dernier recensement importé),
     * puis, entre les gares d'une même ville, la gare (type « gare ») avant la halte, le nom qui
     * commence par celui de la ville (« Toulouse-Matabiau » avant « St-Cyprien-Arènes »), la gare qui
     * porte le nom de la ville seul ou suivi de « Ville » ou « Centre » (« Nantes », « Metz-Ville » :
     * la gare principale), le nombre de lignes desservies à l'ère, et une gare de voyageurs avant une
     * gare de fret seul.
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function stations(int $eraId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $out = [];
        foreach (array_chunk($ids, 5000) as $chunk) {
            $rows = DB::select(<<<'SQL'
                SELECT s.id, s.slug, s.name, s.city, s.department_code, s.type, sc.commune,
                       COALESCE(se.status, 'active') AS status, c.population, COALESCE(ls.lines, 0) AS lines
                FROM stations s
                -- Commune sans arrondissement (« Lyon 2e Arrondissement » : Lyon), pour sa population.
                CROSS JOIN LATERAL (
                    SELECT regexp_replace(s.city, '\s+\d+(er|e)\s+arrondissement$', '', 'i') AS commune
                ) sc
                LEFT JOIN station_eras se ON se.station_id = s.id AND se.era_id = ?
                LEFT JOIN (
                    SELECT station_id, count(DISTINCT line_id) AS lines FROM line_stops WHERE era_id = ? GROUP BY station_id
                ) ls ON ls.station_id = s.id
                LEFT JOIN (
                    SELECT nom, departement, max(population) AS population FROM communes GROUP BY nom, departement
                ) c ON c.nom = sc.commune AND c.departement = s.department_code
                WHERE s.id = ANY(?::bigint[])
                SQL, [$eraId, $eraId, '{'.implode(',', $chunk).'}']);
            foreach ($rows as $row) {
                $population = $row->population === null ? 0 : (int) $row->population;
                $namedAfter = self::namedAfter((string) $row->name, $row->commune);
                $principal = self::principal((string) $row->name, $row->commune);
                $out[(int) $row->id] = [
                    'slug' => $row->slug,
                    'name' => $row->name,
                    'city' => $row->city,
                    'commune' => $row->commune,
                    'department_code' => $row->department_code,
                    'status' => $row->status,
                    'type' => $row->type,
                    'population' => $population,
                    'named_after' => $namedAfter,
                    'principal' => $principal,
                    'weight' => log10($population + 10)
                        + ($row->type === 'gare' ? 0.5 : 0.0)
                        + ($namedAfter ? 0.4 : 0.0)
                        + ($principal ? 0.4 : 0.0)
                        + 0.08 * min((int) $row->lines, 8)
                        - ($row->status === 'no_commercial' ? 0.5 : 0.0),
                ];
            }
        }
        ksort($out);

        return $out;
    }

    /** Le nom de la gare commence-t-il par celui de sa commune (« Saint » et « St » confondus) ? */
    private static function namedAfter(string $name, ?string $city): bool
    {
        if ($city === null || $city === '') {
            return false;
        }

        return str_starts_with(self::norm($name), self::norm($city));
    }

    /**
     * La gare porte-t-elle le nom de sa commune seul, ou suivi de « Ville » ou « Centre » (« Nantes »,
     * « Strasbourg-Ville », « Avignon-Centre ») : le nom de la gare principale d'une ville ?
     */
    public static function principal(string $name, ?string $city): bool
    {
        if ($city === null || $city === '') {
            return false;
        }
        $rest = substr(self::norm($name), strlen(self::norm($city)));

        return self::namedAfter($name, $city) && in_array(trim($rest, ' -'), ['', 'ville', 'centre'], true);
    }

    private static function norm(string $s): string
    {
        return preg_replace('/\bsainte?(?=[\s-])/u', 'st', mb_strtolower($s)) ?? $s;
    }
}
