<?php

namespace App\Console\Commands;

use App\Models\Era;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Construit `line_geometries` : UNE géométrie de ligne continue par ligne
 * et par ère, fusionnée depuis les segments actifs.
 *
 * Tâches #138 / #139.
 *
 * Méthode (prescrite) :
 *   1. Regroupe les segments ACTIFS (in_service / single_track) de la ligne.
 *   2. ST_SnapToGrid léger (~1e-5 ≈ 1,1 m) pour recoudre les micro-trous.
 *   3. ST_LineMerge(ST_Collect(...)) → géométrie continue (LineString si
 *      possible, sinon MultiLineString).
 *   4. length_m = ST_Length(geom::geography) (route-km dédupliqué).
 *
 * 100 % ADDITIF : `line_segments` reste la source de vérité intacte.
 *
 * Expose aussi la projection PK d'un point (`pkOfPoint`), testable via
 * la commande `vff:line-pk-of-point`.
 */
class BuildLineGeometry extends Command
{
    protected $signature = 'vff:build-line-geometry
                            {--era= : Année. Défaut: ère courante.}
                            {--snap=0.00001 : Tolérance ST_SnapToGrid (degrés).}';

    protected $description = 'Construit line_geometries (géométrie de ligne continue fusionnée par ère).';

    private const ACTIVE_STATUSES = ['in_service', 'single_track'];

    public function handle(): int
    {
        $year = $this->option('era') ? (int) $this->option('era') : Era::where('is_current', true)->value('year');
        $era = Era::where('year', $year)->firstOrFail();
        $snap = (float) $this->option('snap');

        $this->info("Build line_geometries pour ère {$year} (snap {$snap}°)");

        // Lignes ayant au moins un segment actif à cette ère.
        $lineIds = DB::table('line_segments')
            ->where('era_id', $era->id)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->distinct()
            ->pluck('line_id')
            ->filter()
            ->values();

        $this->info('  '.$lineIds->count().' lignes avec segments actifs');

        DB::table('line_geometries')->where('era_id', $era->id)->delete();

        $bar = $this->output->createProgressBar($lineIds->count());
        $built = 0;
        $skipped = 0;

        foreach ($lineIds as $lineId) {
            $ok = DB::affectingStatement(<<<'SQL'
                INSERT INTO line_geometries
                    (line_id, era_id, geom, length_m, segment_count, part_count, created_at, updated_at)
                WITH src AS (
                    SELECT geom
                    FROM line_segments
                    WHERE line_id = :line_id
                      AND era_id = :era_id
                      AND status IN ('in_service', 'single_track')
                      AND geom IS NOT NULL
                ),
                merged AS (
                    SELECT
                        ST_LineMerge(ST_Collect(ST_SnapToGrid(geom::geometry, :snap)))::geometry AS g,
                        count(*) AS seg_count
                    FROM src
                )
                SELECT
                    :line_id_b,
                    :era_id_b,
                    -- Toujours stocker en MultiLineString pour un type homogène.
                    ST_Multi(ST_SetSRID(g, 4326)),
                    ROUND(ST_Length(g::geography))::bigint,
                    seg_count,
                    CASE WHEN GeometryType(g) = 'MULTILINESTRING'
                         THEN ST_NumGeometries(g) ELSE 1 END,
                    NOW(), NOW()
                FROM merged
                WHERE g IS NOT NULL AND NOT ST_IsEmpty(g)
                SQL, [
                'line_id' => $lineId, 'era_id' => $era->id, 'snap' => $snap,
                'line_id_b' => $lineId, 'era_id_b' => $era->id,
            ]);

            if ($ok > 0) {
                $built++;
            } else {
                $skipped++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        // route_length_m : route-km DÉDUPLIQUÉ (voies parallèles comptées une
        // fois). Calculé en post-pass car O(parts²) par ligne.
        $this->info('  → Calcul du route-km dédupliqué (voies parallèles)…');
        $this->computeRouteLengths($era->id);

        $total = DB::table('line_geometries')->where('era_id', $era->id)->count();
        $this->info("  ✓ {$built} géométries construites ({$skipped} ignorées), {$total} en base pour l'ère {$year}");

        // Exemple : LGV Sud-Est (code 752000) si présent.
        $example = DB::selectOne(<<<'SQL'
            SELECT l.code, l.name, lg.length_m, lg.route_length_m, lg.segment_count, lg.part_count
            FROM line_geometries lg
            JOIN lines l ON l.id = lg.line_id
            WHERE lg.era_id = ? AND l.code = '752000'
            LIMIT 1
            SQL, [$era->id]);
        if ($example) {
            $this->line(sprintf(
                '  Exemple [%s] %s : merge=%s km, route-km dédupliqué=%s km (%d segments, %d partie(s))',
                $example->code, $example->name,
                number_format($example->length_m / 1000, 1, ',', ' '),
                number_format(($example->route_length_m ?? $example->length_m) / 1000, 1, ',', ' '),
                $example->segment_count, $example->part_count,
            ));
        }

        return self::SUCCESS;
    }

    /**
     * Renseigne `route_length_m` = longueur des sous-LineString APRÈS
     * suppression des voies parallèles quasi superposées.
     *
     * Une sous-partie est un doublon si une autre partie plus longue la
     * recouvre à >80 % (buffer 25 m). On ne somme que les parties non
     * doublons → route-km commercial.
     *
     * Garde-fou perf : on saute le dédoublonnage si une ligne a > 300 parties
     * (O(n²) ST_Difference) ; route_length_m = length_m dans ce cas.
     */
    private function computeRouteLengths(int $eraId, int $maxParts = 300): void
    {
        $rows = DB::table('line_geometries')->where('era_id', $eraId)
            ->select(['id', 'part_count', 'length_m'])->get();

        $bar = $this->output->createProgressBar($rows->count());
        foreach ($rows as $row) {
            if ($row->part_count <= 1) {
                // Mono-ligne : aucune voie parallèle à dédupliquer.
                DB::table('line_geometries')->where('id', $row->id)
                    ->update(['route_length_m' => $row->length_m]);
                $bar->advance();

                continue;
            }
            if ($row->part_count > $maxParts) {
                DB::table('line_geometries')->where('id', $row->id)
                    ->update(['route_length_m' => $row->length_m]);
                $bar->advance();

                continue;
            }

            $res = DB::selectOne(<<<'SQL'
                WITH dumped AS (
                    SELECT (ST_Dump(geom)).geom AS part FROM line_geometries WHERE id = :id
                ),
                measured AS (
                    -- pid déterministe (ORDER BY explicite : row_number() OVER ()
                    -- sans ORDER BY peut renvoyer la même valeur pour toutes
                    -- les lignes selon l'optimiseur).
                    SELECT row_number() OVER (ORDER BY ST_Length(part::geography) DESC, ST_AsBinary(part)) AS pid,
                           part,
                           ST_Length(part::geography) AS plen
                    FROM dumped
                ),
                dedup AS (
                    SELECT p.pid, p.plen,
                        COALESCE((
                            SELECT bool_or(
                                (q.plen > p.plen OR (q.plen = p.plen AND q.pid < p.pid))
                                AND ST_Length(
                                      ST_Difference(p.part, ST_Buffer(q.part::geography, 25)::geometry)::geography
                                    ) < p.plen * 0.2
                            )
                            FROM measured q WHERE q.pid <> p.pid
                        ), false) AS is_dup
                    FROM measured p
                )
                SELECT COALESCE(ROUND(SUM(plen) FILTER (WHERE NOT is_dup))::bigint, 0) AS route_m
                FROM dedup
                SQL, ['id' => $row->id]);

            DB::table('line_geometries')->where('id', $row->id)
                ->update(['route_length_m' => (int) ($res->route_m ?: $row->length_m)]);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
    }

    /**
     * PK (point kilométrique, en mètres) d'un point lon/lat projeté sur la
     * géométrie fusionnée d'une ligne à une ère donnée.
     *
     * PK = ST_LineLocatePoint(geom, point) × length_m.
     *
     * Note : ST_LineLocatePoint exige une LineString simple. Si la géométrie
     * est multi-parties, on projette sur la partie la plus proche du point et
     * on accumule la longueur des parties précédentes.
     *
     * @return float|null PK en mètres, ou null si la ligne n'a pas de géométrie.
     */
    public static function pkOfPoint(int $lineId, float $lon, float $lat, ?int $eraId = null): ?float
    {
        if ($eraId === null) {
            $eraId = (int) Era::where('is_current', true)->value('id');
        }

        $row = DB::selectOne(<<<'SQL'
            WITH g AS (
                SELECT geom FROM line_geometries WHERE line_id = :line_id AND era_id = :era_id
            ),
            pt AS (SELECT ST_SetSRID(ST_MakePoint(:lon, :lat), 4326) AS p),
            parts AS (
                SELECT (ST_Dump(geom)).path[1] AS idx,
                       (ST_Dump(geom)).geom AS part
                FROM g
            ),
            measured AS (
                SELECT idx, part,
                       ST_Length(part::geography) AS plen,
                       ST_Distance(part::geography, (SELECT p FROM pt)::geography) AS dist
                FROM parts
            ),
            nearest AS (SELECT * FROM measured ORDER BY dist ASC LIMIT 1)
            SELECT
                -- somme des longueurs des parties d'index inférieur
                COALESCE((SELECT SUM(plen) FROM measured WHERE idx < n.idx), 0)
                + ST_LineLocatePoint(n.part, (SELECT p FROM pt)) * n.plen AS pk_m
            FROM nearest n
            SQL, [
            'line_id' => $lineId, 'era_id' => $eraId,
            'lon' => $lon, 'lat' => $lat,
        ]);

        return $row && $row->pk_m !== null ? (float) $row->pk_m : null;
    }
}
