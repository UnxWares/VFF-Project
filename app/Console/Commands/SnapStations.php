<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recale les gares sur la voie de leurs lignes (station_lines), après
 * vff:consolidate-stations (qui choisit la position par ordre de confiance
 * des sources : SNCF > OSM > Wikidata > OHM) et vff:attach-stations.
 *
 *   1. Mauvaise source retenue : la position retenue est à plus de --far m
 *      de toute géométrie de ses lignes (tronçons de toutes les ères, tracé
 *      de référence) et à plus de --on-track m de toute voie, alors qu'une
 *      autre de ses sources est à moins de --source-max m d'une de ses
 *      lignes → on prend cette source (ex. Marseille-St-Charles : point SNCF
 *      à 320 m des voies, point OSM sur les quais).
 *   2. Gare décalée : position à plus de --on-track m de toute voie mais à
 *      moins de --far m d'une de ses lignes → projetée sur la voie la plus
 *      proche de ses lignes (point le plus proche).
 *
 * Lignes cibles : rattachements par PK (SNCF, listes historiques), Wikidata
 * P81 ou proximité ≤ 60 m. Une gare déjà sur une voie (même d'une ligne non
 * rattachée, ou sur un tracé source d'une de ses lignes) n'est jamais
 * déplacée. Position d'origine, source et distance gardées dans
 * stations.provenance (geom = « recalage:<source> », geom_recalage = détail).
 * Idempotent : vff:consolidate-stations remet la position des sources, cette
 * commande la recale de nouveau.
 *
 *   php artisan vff:snap-stations --dry-run
 */
class SnapStations extends Command
{
    protected $signature = 'vff:snap-stations
        {--on-track=40 : Distance (m) en deçà de laquelle une gare est déjà sur une voie}
        {--far=300 : Distance max (m) gare ↔ ligne pour un recalage sur la voie}
        {--source-max=100 : Distance max (m) entre une autre source et la ligne pour changer de source}
        {--dry-run : Affiche les recalages sans rien écrire}';

    protected $description = 'Recale les gares décalées sur la voie de leurs lignes (et corrige la source de position retenue)';

    public function handle(): int
    {
        $onTrack = (int) $this->option('on-track');
        $far = (int) $this->option('far');
        $sourceMax = (int) $this->option('source-max');

        DB::beginTransaction();
        try {
            // Voies connues : tronçons de toutes les ères + tracés de référence.
            DB::statement('DROP TABLE IF EXISTS tmp_snap_tracks');
            DB::statement(<<<'SQL'
                CREATE TEMP TABLE tmp_snap_tracks AS
                SELECT line_id, ST_Force2D(geom) AS geom FROM line_segments
                UNION ALL
                SELECT line_id, (ST_Dump(ST_Force2D(geom))).geom FROM line_reference_geometries
            SQL);
            DB::statement('CREATE INDEX ON tmp_snap_tracks USING GIST (geom)');
            DB::statement('CREATE INDEX ON tmp_snap_tracks (line_id)');
            DB::statement('ANALYZE tmp_snap_tracks');
            // Tracés sources des lignes rattachées (OHM, archives…) : une gare posée
            // sur l'un d'eux est déjà sur sa voie, même si le tronçon n'existe pas.
            DB::statement('DROP TABLE IF EXISTS tmp_snap_traces');
            DB::statement(<<<'SQL'
                CREATE TEMP TABLE tmp_snap_traces AS
                SELECT line_id, ST_Force2D(geom) AS geom FROM source_traces
                WHERE line_id IN (SELECT line_id FROM station_lines)
            SQL);
            DB::statement('CREATE INDEX ON tmp_snap_traces USING GIST (geom)');
            DB::statement('ANALYZE tmp_snap_traces');
            // Lignes cibles d'un recalage : rattachement par PK (SNCF, listes
            // historiques), Wikidata P81, ou proximité réelle (≤ 60 m). Un
            // rattachement par proximité lâche (position Wikidata à 150 m) ne
            // suffit pas : ex. gares du RER B rattachées à la LGV voisine alors
            // que leur vraie ligne (ligne de Sceaux) n'a pas de tracé.
            DB::statement('DROP TABLE IF EXISTS tmp_snap_lines');
            DB::statement(<<<'SQL'
                CREATE TEMP TABLE tmp_snap_lines AS
                SELECT station_id, line_id FROM station_lines
                WHERE method IN ('sncf_pk', 'source_pk', 'wikidata_p81') OR COALESCE(distance_m, 0) <= 60
            SQL);
            DB::statement('CREATE INDEX ON tmp_snap_lines (station_id, line_id)');

            // 0. Point SNCF démenti par deux sources concordantes (gares-relecture, 2026-10-20) : liste-des-gares
            //    place certaines gares par leur PK sur une forme de ligne décalée (Millau, Marvejols, Saint-Flour :
            //    1,4 km le long de la voie ; Sallaumines 550 m), donc « sur une voie » et jamais recalées par les
            //    étapes 1 et 2. Quand l'objet OSM de la gare (précis à quelques mètres, même état : en service ou
            //    désaffecté) et une autre source indépendante (Gares & Connexions, Wikidata) concordent à 60 m
            //    près et que le point SNCF est à plus de 100 m d'eux, la position OSM est retenue.
            $consensus = DB::select(<<<'SQL'
                SELECT DISTINCT ON (s.id) s.id, ST_X(o.geom) AS lon, ST_Y(o.geom) AS lat, ST_X(s.geom) AS old_lon, ST_Y(s.geom) AS old_lat,
                       o.source_ref, a.source AS agree, ROUND(ST_Distance(o.geom::geography, s.geom::geography))::int AS moved
                FROM stations s
                JOIN station_sources o ON o.station_id = s.id AND o.source = 'osm' AND o.kind IN ('gare', 'halte')
                 AND o.lifecycle = CASE WHEN COALESCE(s.source_status, s.status) IN ('active', 'tourist', 'no_commercial') THEN 'active' ELSE o.lifecycle END
                 AND o.lifecycle IN ('active', 'disused', 'abandoned')
                CROSS JOIN LATERAL (SELECT x.source, ST_Distance(x.geom::geography, o.geom::geography) AS d FROM station_sources x
                     WHERE x.station_id = s.id AND x.source IN ('sncf_voyageurs', 'wikidata') ORDER BY x.geom <-> o.geom LIMIT 1) a
                WHERE s.position_source = 'sncf' AND a.d <= 60 AND ST_Distance(o.geom::geography, s.geom::geography) > 100
                ORDER BY s.id, a.d
            SQL);
            foreach ($consensus as $r) {
                DB::update(<<<'SQL'
                    UPDATE stations SET geom = ST_SetSRID(ST_MakePoint(?, ?), 4326), position_source = 'osm',
                           provenance = COALESCE(provenance, '{}'::jsonb) || jsonb_build_object('geom', 'osm',
                               'geom_recalage', jsonb_build_object('mode', 'concordance', 'from', jsonb_build_array(?::float, ?::float),
                                   'from_source', 'sncf', 'osm', ?::text, 'agree', ?::text, 'moved_m', ?::int)),
                           updated_at = NOW()
                    WHERE id = ?
                SQL, [$r->lon, $r->lat, $r->old_lon, $r->old_lat, $r->source_ref, $r->agree, $r->moved, $r->id]);
            }

            // Gares hors voie : aucune voie (toutes lignes) à moins de --on-track m.
            DB::statement('DROP TABLE IF EXISTS tmp_snap_off');
            DB::statement(<<<'SQL'
                CREATE TEMP TABLE tmp_snap_off AS
                SELECT s.id, s.geom, s.position_source
                FROM stations s
                WHERE EXISTS (SELECT 1 FROM station_lines sl WHERE sl.station_id = s.id)
                  AND NOT EXISTS (SELECT 1 FROM tmp_snap_tracks t WHERE t.geom && ST_Expand(s.geom, 0.001)
                                  AND ST_DWithin(t.geom::geography, s.geom::geography, :on_track))
                  AND NOT EXISTS (SELECT 1 FROM tmp_snap_traces t JOIN station_lines sl ON sl.station_id = s.id AND sl.line_id = t.line_id
                                  WHERE t.geom && ST_Expand(s.geom, 0.001)
                                  AND ST_DWithin(t.geom::geography, s.geom::geography, :on_track2))
            SQL, ['on_track' => $onTrack, 'on_track2' => $onTrack]);

            // 1. Autre source sur la ligne, position retenue loin de toutes ses lignes.
            $switches = DB::select(<<<'SQL'
                SELECT DISTINCT ON (o.id) o.id, ss.source, ST_X(ss.geom) AS lon, ST_Y(ss.geom) AS lat,
                       ST_X(o.geom) AS old_lon, ST_Y(o.geom) AS old_lat, o.position_source AS old_source,
                       ROUND(d.dist)::int AS dist, d.line_id
                FROM tmp_snap_off o
                JOIN station_sources ss ON ss.station_id = o.id AND NOT ST_Equals(ss.geom, o.geom)
                CROSS JOIN LATERAL (
                    SELECT t.line_id, ST_Distance(t.geom::geography, ss.geom::geography) AS dist
                    FROM tmp_snap_tracks t JOIN tmp_snap_lines sl ON sl.station_id = o.id AND sl.line_id = t.line_id
                    WHERE t.geom && ST_Expand(ss.geom, 0.002)
                    ORDER BY t.geom <-> ss.geom LIMIT 1
                ) d
                WHERE d.dist <= :source_max
                  AND NOT EXISTS (SELECT 1 FROM tmp_snap_tracks t JOIN station_lines sl ON sl.station_id = o.id AND sl.line_id = t.line_id
                                  WHERE t.geom && ST_Expand(o.geom, 0.005) AND ST_DWithin(t.geom::geography, o.geom::geography, :far))
                ORDER BY o.id, d.dist
            SQL, ['source_max' => $sourceMax, 'far' => $far]);
            foreach ($switches as $r) {
                DB::update(<<<'SQL'
                    UPDATE stations SET geom = ST_SetSRID(ST_MakePoint(?, ?), 4326), position_source = ?,
                           provenance = COALESCE(provenance, '{}'::jsonb) || jsonb_build_object('geom', ?::text,
                               'geom_recalage', jsonb_build_object('mode', 'source', 'from', jsonb_build_array(?::float, ?::float),
                                   'from_source', ?::text, 'line_id', ?::bigint, 'distance_m', ?::int)),
                           updated_at = NOW()
                    WHERE id = ?
                SQL, [$r->lon, $r->lat, $r->source, $r->source, $r->old_lon, $r->old_lat, $r->old_source, $r->line_id, $r->dist, $r->id]);
                DB::update('UPDATE tmp_snap_off SET geom = ST_SetSRID(ST_MakePoint(?, ?), 4326), position_source = ? WHERE id = ?',
                    [$r->lon, $r->lat, $r->source, $r->id]);
            }

            // 2. Projection sur la voie la plus proche des lignes de la gare.
            $snaps = DB::select(<<<'SQL'
                SELECT o.id, o.position_source, ST_X(o.geom) AS old_lon, ST_Y(o.geom) AS old_lat,
                       ST_X(p.pt) AS lon, ST_Y(p.pt) AS lat, ROUND(p.dist)::int AS dist, p.line_id
                FROM tmp_snap_off o
                CROSS JOIN LATERAL (
                    SELECT t.line_id, ST_ClosestPoint(t.geom, o.geom) AS pt, ST_Distance(t.geom::geography, o.geom::geography) AS dist
                    FROM tmp_snap_tracks t JOIN tmp_snap_lines sl ON sl.station_id = o.id AND sl.line_id = t.line_id
                    WHERE t.geom && ST_Expand(o.geom, 0.005)
                    ORDER BY t.geom <-> o.geom LIMIT 1
                ) p
                WHERE p.dist > :on_track AND p.dist <= :far
                  AND NOT EXISTS (SELECT 1 FROM tmp_snap_tracks t WHERE t.geom && ST_Expand(o.geom, 0.001)
                                  AND ST_DWithin(t.geom::geography, o.geom::geography, :on_track2))
            SQL, ['on_track' => $onTrack, 'on_track2' => $onTrack, 'far' => $far]);
            $switched = array_flip(array_column($switches, 'id'));
            foreach ($snaps as $r) {
                // Source changée à l'étape 1 : on complète son détail, sinon détail neuf.
                $base = isset($switched[$r->id]) ? "COALESCE(provenance->'geom_recalage', '{}'::jsonb)" : "'{}'::jsonb";
                DB::update(<<<SQL
                    UPDATE stations SET geom = ST_SetSRID(ST_MakePoint(?, ?), 4326),
                           provenance = COALESCE(provenance, '{}'::jsonb) || jsonb_build_object('geom', 'recalage:'||?::text,
                               'geom_recalage', {$base} || jsonb_build_object('mode', ?::text,
                                   'snapped_from', jsonb_build_array(?::float, ?::float), 'line_id', ?::bigint, 'snap_m', ?::int)),
                           updated_at = NOW()
                    WHERE id = ?
                SQL, [$r->lon, $r->lat, $r->position_source, isset($switched[$r->id]) ? 'source+voie' : 'voie',
                    $r->old_lon, $r->old_lat, $r->line_id, $r->dist, $r->id]);
            }

            $this->line(sprintf('Point SNCF démenti par OSM et une autre source (> 100 m) : %d gare(s)', count($consensus)));
            $this->line(sprintf('Gares hors voie (> %d m de toute voie) : %d', $onTrack, (int) DB::selectOne('SELECT COUNT(*) AS n FROM tmp_snap_off')->n));
            $this->info(sprintf('Source de position corrigée : %d gare(s) ; recalées sur la voie : %d gare(s)%s',
                count($switches), count($snaps), $this->option('dry-run') ? ' (simulation)' : ''));
            if ($this->output->isVerbose() || $this->option('dry-run')) {
                $names = DB::table('stations')->whereIn('id', array_merge(array_column($switches, 'id'), array_column($snaps, 'id')))->pluck('name', 'id');
                foreach ($switches as $r) {
                    $this->line("  source  {$r->id} {$names[$r->id]} : {$r->old_source} → {$r->source} (à {$r->dist} m de la ligne {$r->line_id})");
                }
                foreach ($snaps as $r) {
                    $this->line("  voie    {$r->id} {$names[$r->id]} : décalée de {$r->dist} m (ligne {$r->line_id})");
                }
            }

            $this->option('dry-run') ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return self::SUCCESS;
    }
}
