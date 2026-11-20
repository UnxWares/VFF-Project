<?php

namespace App\Services\NetworkGraph;

use App\Support\Lgv;
use App\Support\MapTiles;
use App\Support\MvtBytes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tuiles vectorielles (MVT) du graphe d'une ère pour la carte d'administration (/validate/graph) :
 * deux calques par tuile, `edges` (arêtes) et `nodes` (nœuds), selon la couche choisie.
 *
 *   - topology : network_edges (type : raccord, associatif, LGV, sans voyageurs, voie) et
 *     network_nodes (extrémité, passage, jonction, gare, gare fermée, raccord) ; les nœuds de
 *     simple passage (`link`) n'apparaissent qu'à partir du zoom LINK_NODES_FROM ;
 *   - stations : station_edges (associatif, avec raccord de lacune, surtout sans voyageurs,
 *     commercial) et gares ouvertes : reliées, sans voisine, non rattachées aux voies ;
 *   - lines : network_edges par ligne (la couleur suit l'identifiant de la ligne ; raccords à part)
 *     et arrêts de line_stops (ordre, PK, statut).
 *
 * Chaque élément porte son identifiant : la fiche détaillée vient de GraphFeatureDetails au clic.
 * Tuiles gardées en cache sous la date de construction du graphe, jusqu'au zoom CACHED_MAX_ZOOM.
 */
final class GraphTiles
{
    public const LAYERS = ['topology', 'stations', 'lines'];

    public const FORMAT = 1;

    public const LINK_NODES_FROM = 11;

    private const CACHED_MAX_ZOOM = 12;

    private const TTL_SECONDS = 7 * 24 * 3600;

    public function tile(int $eraId, string $buildStamp, string $layer, int $z, int $x, int $y): string
    {
        if ($z > self::CACHED_MAX_ZOOM) {
            return $this->compute($eraId, $layer, $z, $x, $y);
        }
        $stamp = preg_replace('/\D/', '', $buildStamp);

        return Cache::remember(
            sprintf('graph-tiles:%d:%d:%s:%s:%d:%d:%d', self::FORMAT, $eraId, $stamp, $layer, $z, $x, $y),
            self::TTL_SECONDS,
            fn (): string => $this->compute($eraId, $layer, $z, $x, $y),
        );
    }

    private function compute(int $eraId, string $layer, int $z, int $x, int $y): string
    {
        [$edges, $nodes] = match ($layer) {
            'topology' => [$this->topologyEdges($z), $this->topologyNodes($z)],
            'stations' => [$this->stationEdges($z), $this->stationNodes()],
            'lines' => [$this->lineEdges($z), $this->lineStops()],
            default => throw new \InvalidArgumentException("Couche inconnue : {$layer}"),
        };

        $result = DB::selectOne(<<<SQL
            WITH bounds AS (
                SELECT ST_TileEnvelope(:z, :x, :y) AS env_3857,
                       ST_Transform(ST_TileEnvelope(:z, :x, :y), 4326) AS env_4326
            ),
            edges AS ({$edges}),
            nodes AS ({$nodes})
            SELECT COALESCE((SELECT ST_AsMVT(edges.*, 'edges') FROM edges WHERE geom IS NOT NULL), ''::bytea)
                || COALESCE((SELECT ST_AsMVT(nodes.*, 'nodes') FROM nodes WHERE geom IS NOT NULL), ''::bytea) AS tile
            SQL, ['z' => $z, 'x' => $x, 'y' => $y, 'era' => $eraId]);

        return MvtBytes::from($result->tile ?? '');
    }

    private function topologyEdges(int $z): string
    {
        $geom = MapTiles::displaySql('e.geom', $z);
        $lgv = Lgv::sql('l', null);

        return <<<SQL
            SELECT e.id, e.line_id, e.length_m,
                   CASE WHEN e.is_connector THEN 'connector'
                        WHEN e.is_heritage THEN 'heritage'
                        WHEN l.id IS NOT NULL AND {$lgv} THEN 'lgv'
                        WHEN e.traffic_type = 'freight' OR COALESCE(e.network, '') IN ('militaire', 'particulier') THEN 'non_passenger'
                        ELSE 'track' END AS kind,
                   ST_AsMVTGeom({$geom}, bounds.env_3857, 4096, 64, true) AS geom
            FROM network_edges e
            CROSS JOIN bounds
            LEFT JOIN lines l ON l.id = e.line_id
            WHERE e.era_id = :era AND e.geom && bounds.env_4326
            SQL;
    }

    private function topologyNodes(int $z): string
    {
        $links = $z >= self::LINK_NODES_FROM ? '' : "AND n.kind <> 'link'";

        return <<<SQL
            SELECT n.id, n.kind, n.station_id,
                   ST_AsMVTGeom(ST_Transform(n.geom, 3857), bounds.env_3857, 4096, 64, true) AS geom
            FROM network_nodes n
            CROSS JOIN bounds
            WHERE n.era_id = :era AND n.geom && bounds.env_4326 {$links}
            SQL;
    }

    private function stationEdges(int $z): string
    {
        $geom = MapTiles::displaySql('se.geom', $z);

        return <<<SQL
            SELECT se.id, se.line_id, se.length_m,
                   CASE WHEN se.heritage_m > 0 THEN 'heritage'
                        WHEN se.gap_joins > 0 THEN 'gap'
                        WHEN se.non_passenger_m * 2 > se.length_m THEN 'non_passenger'
                        ELSE 'commercial' END AS kind,
                   ST_AsMVTGeom({$geom}, bounds.env_3857, 4096, 64, true) AS geom
            FROM station_edges se
            CROSS JOIN bounds
            WHERE se.era_id = :era AND se.geom && bounds.env_4326
            SQL;
    }

    /** Gares ouvertes : rattachées au graphe (nœud « station ») ou non (station_eras ouverte sans nœud). */
    private function stationNodes(): string
    {
        return <<<'SQL'
            SELECT s.id, s.name,
                   CASE WHEN n.id IS NULL THEN 'unattached'
                        WHEN EXISTS (SELECT 1 FROM station_edges e WHERE e.era_id = :era
                                     AND (e.from_station_id = s.id OR e.to_station_id = s.id)) THEN 'linked'
                        ELSE 'no_neighbour' END AS state,
                   ST_AsMVTGeom(ST_Transform(s.geom, 3857), bounds.env_3857, 4096, 64, true) AS geom
            FROM station_eras se
            JOIN stations s ON s.id = se.station_id
            CROSS JOIN bounds
            LEFT JOIN LATERAL (
                SELECT n.id FROM network_nodes n WHERE n.era_id = :era AND n.station_id = s.id AND n.kind = 'station' LIMIT 1
            ) n ON true
            WHERE se.era_id = :era AND se.status IN ('active', 'tourist', 'no_commercial') AND s.geom && bounds.env_4326
            SQL;
    }

    private function lineEdges(int $z): string
    {
        $geom = MapTiles::displaySql('e.geom', $z);

        return <<<SQL
            SELECT e.id, e.line_id, e.length_m, CASE WHEN e.is_connector THEN 'connector' ELSE 'line' END AS kind,
                   ST_AsMVTGeom({$geom}, bounds.env_3857, 4096, 64, true) AS geom
            FROM network_edges e
            CROSS JOIN bounds
            WHERE e.era_id = :era AND e.geom && bounds.env_4326
            SQL;
    }

    private function lineStops(): string
    {
        return <<<'SQL'
            SELECT ls.id, ls.line_id, ls.station_id, ls.position, ls.pk_m, ls.effective_status AS status, ls.is_terminal,
                   ST_AsMVTGeom(ST_Transform(s.geom, 3857), bounds.env_3857, 4096, 64, true) AS geom
            FROM line_stops ls
            JOIN stations s ON s.id = ls.station_id
            CROSS JOIN bounds
            WHERE ls.era_id = :era AND s.geom && bounds.env_4326
            SQL;
    }
}
