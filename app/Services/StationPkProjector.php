<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PK des rattachements gare - ligne qui n'en ont pas (station_lines.pk_m nul) : projection de la gare sur le
 * tracé de la ligne, interpolée entre les gares de PK connu (les « ancres »).
 *
 * Règles (mesures du 23/11/2026 : 922 couples de gares à moins de 50 m de PK mais à plus de 500 m l'une de
 * l'autre, sur 181 lignes ; 5 173 couples dans le désordre sur 358 lignes) :
 *
 *   - Ancre : une gare dont le PK vient d'une source (SNCF, liste historique, schéma), à ≤ 500 m du tronçon sur
 *     lequel elle est posée. Un PK projeté n'en est jamais une. Un PK d'une source autre que SNCF identique (à 50 m)
 *     à celui d'une autre gare de la même ligne située à plus de 500 m (PK 0 posé sur Champigneulles et Burthécourt
 *     par un schéma) est annulé puis recalculé.
 *   - Tronçon : le tronçon SNCF (pk_debut / pk_fin) s'il est à ≤ 2,5 km de la gare, sinon le morceau le plus proche
 *     du tracé de référence. Avant, un tronçon SNCF partiel (553000 : 5 km de Paris sur 80 km de ligne) captait
 *     toutes les gares de la ligne, projetées en bout de tronçon et prenant toutes le PK de la dernière ancre.
 *   - Une gare à plus de 2 km de tout tronçon de sa ligne (rattachement douteux : P81 de Wikidata, tracé d'une
 *     autre ligne, homonyme) n'a pas de PK plutôt qu'un PK de bout de tronçon.
 *   - Sens du tronçon : déduit des ancres (le PK peut décroître le long du tracé) ; au-delà d'un bout, le PK
 *     est prolongé de la distance à ce bout.
 *   - Sans ancre sur son tronçon : projection sur la ligne brisée des ancres de la ligne (PK interpolé sur le
 *     segment le plus proche, à ≤ 15 km ; au-delà de la première ou de la dernière ancre, PK prolongé de la
 *     distance à elle), sinon abscisse brute (projected_raw) mise bout à bout sur les morceaux de la ligne dans
 *     l'ordre de leur axe principal, pas depuis le début de chaque morceau.
 *   - Tracés des sources (puis tronçons datés) : seulement pour les gares à plus de 2 km de la forme SNCF et du
 *     tracé de référence (Petite Ceinture, gares du sud).
 */
final class StationPkProjector
{
    /** Distance max (m) d'une ancre à son tronçon. */
    public const ANCHOR_MAX_M = 500;

    /** Distance (m) au-delà de laquelle un rattachement n'a pas de PK. */
    public const FAR_M = 2000;

    /** Distance (m) sous laquelle un tronçon SNCF passe avant le tracé de référence. */
    public const SNCF_PREFERRED_M = 2500;

    /** Distance max (m) d'une gare à la ligne brisée des ancres. */
    public const POLYLINE_MAX_M = 15000;

    /**
     * Calcule et écrit les PK manquants.
     *
     * @param  list<int>|null  $only  rattachements à traiter (--years-only : terminus relus ajoutés), sinon tous ceux sans PK
     * @return array{projected: int, raw: int, none: int, released: int}
     */
    public function project(?array $only = null): array
    {
        $released = $only === null ? $this->releaseCollidingPk() : 0;

        $this->buildTracks();
        $this->locate();
        if ($this->addTraceTracks()) {
            $this->locate();
        }
        $onlySql = $only === null ? '' : ' AND sl.id IN ('.implode(',', array_map('intval', $only ?: [0])).')';

        DB::statement('DROP TABLE IF EXISTS tmp_pk_res');
        DB::statement('CREATE TEMP TABLE tmp_pk_res (id bigint PRIMARY KEY, pk integer, src varchar(30))');
        $this->interpolate();
        $this->polyline();
        $this->chainRaw();

        $n = DB::affectingStatement(<<<SQL
            UPDATE station_lines sl SET pk_m = r.pk, pk_source = r.src
            FROM tmp_pk_res r WHERE r.id = sl.id AND r.pk IS NOT NULL{$onlySql}
        SQL);
        $raw = (int) DB::selectOne("SELECT COUNT(*) AS n FROM tmp_pk_res r JOIN station_lines sl ON sl.id = r.id WHERE r.src = 'projected_raw' AND r.pk IS NOT NULL{$onlySql}")->n;
        $none = (int) DB::selectOne('SELECT COUNT(*) AS n FROM tmp_loc WHERE target AND dist > '.self::FAR_M)->n;

        return ['projected' => $n - $raw, 'raw' => $raw, 'none' => $none, 'released' => $released];
    }

    /**
     * PK de source (hors SNCF) en collision avec celui d'une autre gare de la ligne à plus de 500 m : annulé, la gare
     * est projetée comme les autres. Les PK SNCF ne sont jamais touchés.
     */
    private function releaseCollidingPk(): int
    {
        return DB::affectingStatement(<<<'SQL'
            UPDATE station_lines sl SET pk_m = NULL, pk_source = NULL
            WHERE sl.pk_m IS NOT NULL AND COALESCE(sl.pk_source, '') <> 'sncf' AND COALESCE(sl.pk_source, '') NOT LIKE 'projected%'
              AND EXISTS (
                SELECT 1 FROM station_lines o
                JOIN stations so ON so.id = o.station_id
                JOIN stations ss ON ss.id = sl.station_id
                WHERE o.line_id = sl.line_id AND o.id <> sl.id AND o.pk_m IS NOT NULL AND ABS(o.pk_m - sl.pk_m) < 50
                  AND ST_Distance(so.geom::geography, ss.geom::geography) > 500)
        SQL);
    }

    /** Tronçons de chaque ligne ayant un rattachement sans PK : tronçons SNCF (avec PK) et morceaux du tracé. */
    private function buildTracks(): void
    {
        DB::statement('DROP TABLE IF EXISTS tmp_line_tr');
        DB::statement('CREATE TEMP TABLE tmp_line_tr (tid serial PRIMARY KEY, line_id bigint, geom geometry(LineString, 4326), pk_debut int, pk_fin int, is_sncf boolean NOT NULL DEFAULT false, extra boolean NOT NULL DEFAULT false)');
        DB::statement(<<<'SQL'
            INSERT INTO tmp_line_tr (line_id, geom, pk_debut, pk_fin, is_sncf)
            SELECT l.id, lg.geom, lg.pk_debut_m, lg.pk_fin_m, TRUE FROM sncf_line_geometries lg JOIN lines l ON l.code = lg.code_ligne
            WHERE l.id IN (SELECT line_id FROM station_lines WHERE pk_m IS NULL)
        SQL);
        $fallbacks = array_filter([
            Schema::hasTable('line_reference_geometries') ? 'SELECT line_id, geom FROM line_reference_geometries' : null,
            'SELECT line_id, geom FROM source_traces WHERE line_id IS NOT NULL',
            'SELECT line_id, geom FROM line_segments WHERE line_id IS NOT NULL',
        ]);
        // Morceaux d'un tracé séparés de quelques mètres (tracés de deux sources bout à bout : 433000
        // Couterne - La Ferté-Macé, 50 m) : extrémités recollées au début du morceau suivant (≈ 100 m).
        // Le tracé SNCF ne suffit pas à lui seul : il peut ne couvrir qu'une partie de la ligne (553000), le
        // tracé de référence complète donc toujours, la première source qui en donne.
        foreach ($fallbacks as $sql) {
            DB::statement(<<<SQL
                INSERT INTO tmp_line_tr (line_id, geom)
                SELECT line_id, (ST_Dump(CASE WHEN ST_NumGeometries(g) > 1
                    THEN ST_LineMerge(ST_Snap(g, (SELECT ST_Collect(ST_StartPoint(d.geom)) FROM ST_Dump(g) d), 0.0015)) ELSE g END)).geom
                FROM (SELECT line_id, ST_LineMerge(ST_Collect(ST_Force2D(geom))) AS g
                      FROM ({$sql}) x
                      WHERE line_id IN (SELECT line_id FROM station_lines WHERE pk_m IS NULL)
                        AND line_id NOT IN (SELECT line_id FROM tmp_line_tr WHERE NOT is_sncf)
                      GROUP BY line_id) y
            SQL);
        }
        DB::statement("DELETE FROM tmp_line_tr WHERE GeometryType(geom) <> 'LINESTRING'");
        DB::statement('CREATE INDEX ON tmp_line_tr USING GIST (geom)');
        DB::statement('CREATE INDEX ON tmp_line_tr (line_id)');
    }

    /**
     * Lignes dont des gares sont à plus de 2 km de tous leurs tronçons (la forme SNCF et le tracé de référence ne couvrent pas
     * tout : Petite Ceinture, 955000, dont les gares du sud sont sur des tracés de source rattachés à la ligne) : les tracés des
     * sources (puis les tronçons datés) s'ajoutent comme tronçons « extra », choisis seulement pour les gares que rien d'autre
     * ne couvre, jamais mis bout à bout.
     */
    private function addTraceTracks(): bool
    {
        $lines = DB::table('tmp_loc')->where('target', true)->where('dist', '>', self::FAR_M)->distinct()->pluck('line_id')->map(fn ($id) => (int) $id)->all();
        if ($lines === []) {
            return false;
        }
        $in = implode(',', $lines);
        foreach (['SELECT line_id, geom FROM source_traces', 'SELECT line_id, geom FROM line_segments'] as $sql) {
            DB::statement(<<<SQL
                INSERT INTO tmp_line_tr (line_id, geom, extra)
                SELECT line_id, (ST_Dump(CASE WHEN ST_NumGeometries(g) > 1
                    THEN ST_LineMerge(ST_Snap(g, (SELECT ST_Collect(ST_StartPoint(d.geom)) FROM ST_Dump(g) d), 0.0015)) ELSE g END)).geom, TRUE
                FROM (SELECT line_id, ST_LineMerge(ST_Collect(ST_Force2D(geom))) AS g
                      FROM ({$sql}) x
                      WHERE line_id IN ({$in}) AND line_id NOT IN (SELECT line_id FROM tmp_line_tr WHERE extra)
                      GROUP BY line_id) y
            SQL);
        }
        DB::statement("DELETE FROM tmp_line_tr WHERE GeometryType(geom) <> 'LINESTRING'");
        DB::statement('ANALYZE tmp_line_tr');

        return true;
    }

    /** Position de chaque rattachement sur son tronçon (SNCF à ≤ 2,5 km, sinon le plus proche), sens des tronçons. */
    private function locate(): void
    {
        $preferred = self::SNCF_PREFERRED_M;
        $far = self::FAR_M;
        DB::statement('DROP TABLE IF EXISTS tmp_loc');
        DB::statement(<<<SQL
            CREATE TEMP TABLE tmp_loc AS
            SELECT sl.id, sl.line_id, (sl.pk_m IS NULL) AS target,
                   CASE WHEN sl.pk_m IS NOT NULL AND COALESCE(sl.pk_source, '') NOT LIKE 'projected%' THEN sl.pk_m END AS pk_m,
                   tr.tid, ST_LineLocatePoint(tr.geom, s.geom) AS frac, ST_Length(tr.geom::geography) AS len,
                   tr.pk_debut, tr.pk_fin, tr.is_sncf, ST_Distance(tr.geom::geography, s.geom::geography) AS dist
            FROM station_lines sl
            JOIN stations s ON s.id = sl.station_id
            JOIN LATERAL (SELECT tid, geom, pk_debut, pk_fin, is_sncf FROM tmp_line_tr t WHERE t.line_id = sl.line_id
                          ORDER BY (t.is_sncf AND ST_DWithin(t.geom, s.geom, 0.03) AND ST_DWithin(t.geom::geography, s.geom::geography, {$preferred})) DESC,
                                   (t.extra AND EXISTS (SELECT 1 FROM tmp_line_tr n WHERE n.line_id = t.line_id AND NOT n.extra AND ST_DWithin(n.geom, s.geom, 0.03)
                                                        AND ST_DWithin(n.geom::geography, s.geom::geography, {$far}))) ASC,
                                   ST_Distance(t.geom::geography, s.geom::geography) LIMIT 1) tr ON TRUE
        SQL);
        DB::statement('CREATE INDEX ON tmp_loc (tid)');
        DB::statement('DROP TABLE IF EXISTS tmp_loc_dir');
        // Sens : le PK croît-il avec l'abscisse ? Selon les ancres les plus éloignées l'une de l'autre sur le tronçon.
        DB::statement(<<<'SQL'
            CREATE TEMP TABLE tmp_loc_dir AS
            SELECT tid, CASE WHEN MAX(pk_m) FILTER (WHERE frac = mxf) - MIN(pk_m) FILTER (WHERE frac = mnf) < 0 THEN -1 ELSE 1 END AS d
            FROM (SELECT tid, pk_m, frac, MAX(frac) OVER (PARTITION BY tid) AS mxf, MIN(frac) OVER (PARTITION BY tid) AS mnf
                  FROM tmp_loc WHERE pk_m IS NOT NULL AND dist <= 500) x
            GROUP BY tid
        SQL);
    }

    /** Interpolation entre les ancres voisines du même tronçon ; tronçon SNCF sans ancre : interpolation sur ses PK de début et de fin. */
    private function interpolate(): void
    {
        $far = self::FAR_M;
        $anchor = self::ANCHOR_MAX_M;
        DB::statement(<<<SQL
            INSERT INTO tmp_pk_res (id, pk, src)
            WITH t AS (
                SELECT a.id, a.frac, a.len, a.dist, a.pk_debut, a.pk_fin, COALESCE(dr.d, 1) AS d,
                       lo.pk_m AS lpk, lo.frac AS lfrac, hi.pk_m AS hpk, hi.frac AS hfrac
                FROM tmp_loc a
                LEFT JOIN tmp_loc_dir dr ON dr.tid = a.tid
                LEFT JOIN LATERAL (SELECT pk_m, frac FROM tmp_loc b WHERE b.tid = a.tid AND b.pk_m IS NOT NULL AND b.dist <= {$anchor} AND b.frac <= a.frac
                                   ORDER BY b.frac DESC LIMIT 1) lo ON TRUE
                LEFT JOIN LATERAL (SELECT pk_m, frac FROM tmp_loc b WHERE b.tid = a.tid AND b.pk_m IS NOT NULL AND b.dist <= {$anchor} AND b.frac >= a.frac
                                   ORDER BY b.frac ASC LIMIT 1) hi ON TRUE
                WHERE a.target AND a.dist <= {$far}
            )
            SELECT id, GREATEST(0, ROUND(CASE
                    WHEN lpk IS NOT NULL AND hpk IS NOT NULL AND hfrac > lfrac THEN lpk + (frac - lfrac) * (hpk - lpk) / (hfrac - lfrac)
                    WHEN lpk IS NOT NULL AND hpk IS NOT NULL THEN lpk
                    WHEN lpk IS NOT NULL THEN lpk + d * ((frac - lfrac) * len + CASE WHEN frac >= 1 THEN dist ELSE 0 END)
                    WHEN hpk IS NOT NULL THEN hpk - d * ((hfrac - frac) * len + CASE WHEN frac <= 0 THEN dist ELSE 0 END)
                    ELSE pk_debut + frac * (pk_fin - pk_debut) END))::int,
                   'projected'
            FROM t WHERE lpk IS NOT NULL OR hpk IS NOT NULL OR (pk_debut IS NOT NULL AND pk_fin IS NOT NULL)
        SQL);
    }

    /** Gares sans ancre sur leur tronçon : PK interpolé sur le segment le plus proche de la ligne brisée des ancres de la ligne. */
    private function polyline(): void
    {
        $max = self::POLYLINE_MAX_M;
        $far = self::FAR_M;
        DB::statement('DROP TABLE IF EXISTS tmp_anchor_seg');
        DB::statement(<<<'SQL'
            CREATE TEMP TABLE tmp_anchor_seg AS
            SELECT line_id, pk_m AS p0, nxt AS p1, ST_MakeLine(g0, g1) AS seg, pmin, pmax
            FROM (SELECT line_id, pk_m, geom AS g0, LEAD(pk_m) OVER w AS nxt, LEAD(geom) OVER w AS g1,
                         MIN(pk_m) OVER (PARTITION BY line_id) AS pmin, MAX(pk_m) OVER (PARTITION BY line_id) AS pmax
                  FROM (SELECT DISTINCT ON (l.line_id, l.pk_m) l.line_id, l.pk_m, s.geom
                        FROM tmp_loc l JOIN station_lines sl ON sl.id = l.id JOIN stations s ON s.id = sl.station_id
                        WHERE l.pk_m IS NOT NULL AND l.dist <= 500 ORDER BY l.line_id, l.pk_m) d
                  WINDOW w AS (PARTITION BY line_id ORDER BY pk_m)) z
            WHERE nxt IS NOT NULL AND nxt > pk_m
        SQL);
        DB::statement('CREATE INDEX ON tmp_anchor_seg USING GIST (seg)');
        DB::statement('CREATE INDEX ON tmp_anchor_seg (line_id)');
        DB::statement(<<<SQL
            INSERT INTO tmp_pk_res (id, pk, src)
            SELECT l.id, GREATEST(0, ROUND(CASE
                       WHEN g.t >= 1 AND g.p1 = g.pmax THEN g.p1 + g.dm
                       WHEN g.t <= 0 AND g.p0 = g.pmin THEN g.p0 - g.dm
                       ELSE g.p0 + g.t * (g.p1 - g.p0) END))::int, 'projected'
            FROM tmp_loc l
            JOIN station_lines sl ON sl.id = l.id
            JOIN stations s ON s.id = sl.station_id
            JOIN LATERAL (SELECT p0, p1, pmin, pmax, ST_LineLocatePoint(seg, s.geom) AS t, ST_Distance(seg::geography, s.geom::geography) AS dm
                          FROM tmp_anchor_seg a WHERE a.line_id = l.line_id ORDER BY a.seg <-> s.geom LIMIT 1) g ON TRUE
            WHERE l.target AND l.dist <= {$far} AND g.dm <= {$max}
              AND (g.t > 0 AND g.t < 1 OR (g.t >= 1 AND g.p1 = g.pmax) OR (g.t <= 0 AND g.p0 = g.pmin))
              AND NOT EXISTS (SELECT 1 FROM tmp_pk_res r WHERE r.id = l.id)
        SQL);
    }

    /** Reste : abscisse brute, morceaux de la ligne mis bout à bout dans l'ordre de leur axe principal. */
    private function chainRaw(): void
    {
        $far = self::FAR_M;
        DB::statement('DROP TABLE IF EXISTS tmp_raw_piece');
        DB::statement(<<<SQL
            CREATE TEMP TABLE tmp_raw_piece AS
            WITH pe AS (
                SELECT t.tid, t.line_id, t.extra, ST_Length(t.geom::geography) AS len, ST_StartPoint(t.geom) AS sp, ST_EndPoint(t.geom) AS ep
                FROM tmp_line_tr t
                WHERE NOT t.is_sncf AND t.line_id IN (SELECT line_id FROM tmp_loc WHERE target AND dist <= {$far} AND id NOT IN (SELECT id FROM tmp_pk_res))
                  AND (NOT t.extra OR t.tid IN (SELECT tid FROM tmp_loc WHERE target AND dist <= {$far} AND id NOT IN (SELECT id FROM tmp_pk_res)))
            ),
            ctr AS (SELECT line_id, ST_Centroid(ST_Collect(sp)) AS c FROM pe GROUP BY line_id),
            ax AS (
                SELECT DISTINCT ON (q.line_id) q.line_id, q.pt
                FROM (SELECT pe.line_id, pe.sp AS pt, ST_Distance(pe.sp, ctr.c) AS d FROM pe JOIN ctr USING (line_id)
                      UNION ALL SELECT pe.line_id, pe.ep, ST_Distance(pe.ep, ctr.c) FROM pe JOIN ctr USING (line_id)) q
                ORDER BY q.line_id, q.d DESC
            )
            SELECT pe.tid, ST_Distance(ax.pt, pe.sp) <= ST_Distance(ax.pt, pe.ep) AS fwd,
                   COALESCE(SUM(pe.len) OVER (PARTITION BY pe.line_id ORDER BY pe.extra, ST_Distance(ax.pt, pe.sp) + ST_Distance(ax.pt, pe.ep)
                                              ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING), 0) AS off
            FROM pe JOIN ax USING (line_id)
        SQL);
        DB::statement(<<<SQL
            INSERT INTO tmp_pk_res (id, pk, src)
            SELECT l.id, ROUND(p.off + (CASE WHEN p.fwd THEN l.frac ELSE 1 - l.frac END) * l.len)::int, 'projected_raw'
            FROM tmp_loc l JOIN tmp_raw_piece p ON p.tid = l.tid
            WHERE l.target AND l.dist <= {$far} AND NOT EXISTS (SELECT 1 FROM tmp_pk_res r WHERE r.id = l.id)
        SQL);
    }
}
