<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Croisement systématique des sources le long du tracé de référence d'une
 * ligne (line_reference_parts), tous les STEP_M mètres.
 *
 * Pour chaque point, on mesure l'écart à la trace la plus proche de CHAQUE
 * famille de sources sur le même axe (direction à moins de 35°) : BD TOPO,
 * OSM (cycles de vie compris), contributions, archeoferroviaire, OHM, SNCF,
 * rattachées ou non à la ligne. Puis :
 *
 *   - confiance : high = la géométrie retenue est précise ET une autre source
 *     précise indépendante concorde à ≤ 5 m ; medium = géométrie précise seule ;
 *     low = archeo / OHM / SNCF seuls, ou raccord ;
 *   - chaque morceau est redécoupé par classe de confiance (portions d'au
 *     moins 100 m) et reçoit : nombre de sources concordantes, écart médian et
 *     maximal à la source indépendante la plus proche, détail par famille ;
 *   - divergences : portions d'au moins 100 m où deux sources précises du même
 *     axe s'écartent de plus de 8 m, ou bien où une source RATTACHÉE À LA
 *     LIGNE s'écarte de la géométrie retenue (déviation, ancien tracé, erreur
 *     de rattachement) puis la rejoint.
 *
 * Utilise la table temporaire cf_pool de TraceConflation::preparePool().
 */
class SourceAgreement
{
    public const STEP_M = 25.0;

    /** Familles, de la plus précise à la moins précise. */
    public const FAMILIES = ['contrib', 'bdtopo', 'osm', 'fusion', 'ign-historique', 'archeoferroviaire', 'ohm', 'sncf'];

    public const PRECISE = ['contrib', 'bdtopo', 'osm'];

    /** Rayon de recherche sur l'axe (m). */
    private const RADIUS = ['contrib' => 60, 'bdtopo' => 60, 'osm' => 60, 'fusion' => 60, 'ign-historique' => 60, 'archeoferroviaire' => 100, 'ohm' => 100, 'sncf' => 250];

    /** Concordance (m). */
    private const AGREE = ['contrib' => 5, 'bdtopo' => 5, 'osm' => 5, 'fusion' => 5, 'ign-historique' => 6, 'archeoferroviaire' => 10, 'ohm' => 10, 'sncf' => 50];

    /** Divergence d'une source rattachée à la ligne (m), recherchée jusqu'à 500 m. */
    private const DIVERGE = ['contrib' => 8, 'bdtopo' => 8, 'osm' => 8, 'fusion' => 8, 'ign-historique' => 12, 'archeoferroviaire' => 25, 'ohm' => 25, 'sncf' => 150];

    private const MIN_RUN_SAMPLES = 4;

    /**
     * Voies OSM abandonnées, déposées ou inutilisées : souvent dessinées d'après des cartes ou photos anciennes (à
     * Courseulles, 10 à 20 m à côté de la plateforme visible). Un morceau qui n'a qu'elles pour géométrie précise
     * n'est « précis » que si une source indépendante la confirme à LIFECYCLE_CONFIRM_M près : BD TOPO, LiDAR HD
     * (famille « lidar », quand elle existera) ou axe affiné sur photo recalée (ign-historique, classe ortho-precis).
     * archeo et OHM ne comptent pas : ils sont souvent tirés des mêmes documents. Sinon : « estimé » (low).
     * Mêmes états que JunctionRepair::DEAD_OSM_LAYERS (dismantled, was:railway… compris). Décision de Baptiste du
     * 22/11/2026.
     */
    public const OSM_LIFECYCLE_LAYERS = JunctionRepair::DEAD_OSM_LAYERS;

    private const LIFECYCLE_CONFIRM_M = 3.0;

    private bool $ready = false;

    /**
     * Analyse une ligne : redécoupe ses morceaux par confiance (en base) et
     * renvoie les divergences détectées.
     *
     * @return array{divergences: array<int, array<string, mixed>>, km: array<string, float>}
     */
    public function analyze(int $lineId): array
    {
        $this->prepare($lineId);
        $parts = [];
        $lifecycle = self::osmLifecycleSql();
        foreach (DB::select(<<<SQL
            SELECT p.id, p.line_id, p.source, p.matched_source, p.source_trace_ids::text AS sids, p.matched_trace_ids::text AS mids,
                   {$lifecycle} AS osm_lifecycle
            FROM line_reference_parts p WHERE p.line_id = ?
            SQL, [$lineId]) as $p) {
            $parts[$p->id] = $p;
        }
        if (! $parts) {
            return ['divergences' => [], 'km' => []];
        }

        $nearest = [];
        $mine = [];
        foreach (self::FAMILIES as $f) {
            $nearest[] = sprintf(<<<'SQL'
                LEFT JOIN LATERAL (SELECT ST_Distance(c.g, s.pt) AS d, c.id, c.line_id, c.cls FROM cf_pool c
                    WHERE c.family = '%1$s' AND ST_DWithin(c.g, s.pt, %2$d) AND pg_temp.cf_angle(c.g, s.pt, s.az) <= 35
                    ORDER BY c.g <-> s.pt LIMIT 1) n_%3$s ON TRUE
                SQL, $f, self::RADIUS[$f], $this->alias($f));
            $mine[] = sprintf(<<<'SQL'
                LEFT JOIN LATERAL (SELECT ST_Distance(c.g, s.pt) AS d, c.id FROM sa_mine c
                    WHERE c.family = '%1$s' AND ST_DWithin(c.g, s.pt, 500) AND pg_temp.cf_angle(c.g, s.pt, s.az) <= 35
                    ORDER BY c.g <-> s.pt LIMIT 1) m_%2$s ON TRUE
                SQL, $f, $this->alias($f));
        }
        $cols = implode(', ', array_map(fn ($f) => sprintf('n_%1$s.d AS nd_%1$s, n_%1$s.id AS ni_%1$s, n_%1$s.line_id AS nl_%1$s, n_%1$s.cls AS nc_%1$s, m_%1$s.d AS md_%1$s, m_%1$s.id AS mi_%1$s', $this->alias($f)), self::FAMILIES));
        $joins = implode("\n", array_merge($nearest, $mine));

        $samples = DB::select(<<<SQL
            WITH parts AS MATERIALIZED (
                SELECT p.id AS part_id, d.path[1] AS sub, d.geom AS g, ST_Length(d.geom) AS len
                FROM line_reference_parts p, LATERAL ST_Dump(ST_Transform(p.geom, 2154)) d
                WHERE p.line_id = :line AND ST_Length(d.geom) > 0.5
            ),
            s AS MATERIALIZED (
                SELECT part_id, sub, len, q.n, j, ST_LineInterpolatePoint(g, (j + 0.5) / q.n) AS pt,
                       ST_Azimuth(ST_LineInterpolatePoint(g, GREATEST(0, (j + 0.5) / q.n - 6 / len)),
                                  ST_LineInterpolatePoint(g, LEAST(1, (j + 0.5) / q.n + 6 / len))) AS az
                FROM parts, LATERAL (SELECT GREATEST(1, ROUND(len / :step))::int AS n) q, LATERAL generate_series(0, q.n - 1) j
            )
            SELECT s.part_id, s.sub, s.n, s.j, s.len,
                   ST_X(ST_Transform(s.pt, 4326)) AS lon, ST_Y(ST_Transform(s.pt, 4326)) AS lat, {$cols}
            FROM s
            {$joins}
            ORDER BY s.part_id, s.sub, s.j
            SQL, ['line' => $lineId, 'step' => self::STEP_M]);

        // Séquences d'échantillons par morceau / sous-géométrie.
        $seqs = [];
        foreach ($samples as $s) {
            $seqs[$s->part_id.':'.$s->sub][] = $s;
        }

        $groups = [];   // [part_id][class] => ['runs' => [[sub, f0, f1]], 'samples' => [...]]
        $divergences = [];
        $km = ['high' => 0.0, 'medium' => 0.0, 'low' => 0.0];
        foreach ($seqs as $seq) {
            $part = $parts[$seq[0]->part_id];
            $own = $this->ownFamily($part);
            $evals = array_map(fn ($s) => $this->evaluate($s, $own, $lineId, (bool) ($part->osm_lifecycle ?? false)), $seq);

            // Portions homogènes de confiance (lissées à MIN_RUN_SAMPLES).
            $classes = $this->smooth(array_column($evals, 'class'));
            $n = (int) $seq[0]->n;
            $len = (float) $seq[0]->len;
            $j0 = 0;
            for ($j = 1; $j <= $n; $j++) {
                if ($j === $n || $classes[$j] !== $classes[$j0]) {
                    $c = $classes[$j0];
                    $groups[$part->id][$c] ??= ['runs' => [], 'samples' => []];
                    $groups[$part->id][$c]['runs'][] = [(int) $seq[0]->sub, $j0 / $n, $j / $n];
                    array_push($groups[$part->id][$c]['samples'], ...array_slice($evals, $j0, $j - $j0));
                    $km[$c] += ($j - $j0) / $n * $len / 1000;
                    $j0 = $j;
                }
            }
            array_push($divergences, ...$this->divergences($seq, $evals, $part, $own));
        }

        $this->rewriteParts($lineId, $parts, $groups);

        // Trace(s) retenue(s) au droit de chaque divergence (plutôt que tous les ids du morceau).
        if ($divergences) {
            $near = DB::select(<<<'SQL'
                SELECT r.i, (SELECT string_agg(DISTINCT c.id::text, ' ') FROM cf_pool c
                             WHERE c.family = r.fam AND ST_DWithin(c.g, ST_Transform(ST_SetSRID(ST_MakePoint(r.lon, r.lat), 4326), 2154), 3)) AS ids
                FROM unnest(CAST(:i AS int[]), CAST(:fam AS text[]), CAST(:lon AS float8[]), CAST(:lat AS float8[])) AS r(i, fam, lon, lat)
                SQL, [
                'i' => '{'.implode(',', array_keys($divergences)).'}',
                'fam' => '{'.implode(',', array_column($divergences, 'retained')).'}',
                'lon' => '{'.implode(',', array_column($divergences, 'lon')).'}',
                'lat' => '{'.implode(',', array_column($divergences, 'lat')).'}',
            ]);
            foreach ($near as $n) {
                $divergences[$n->i]['retained_ids'] = $n->ids ?? '';
            }
        }
        $this->store($lineId, $divergences);

        return ['divergences' => $divergences, 'km' => $km];
    }

    private function prepare(int $lineId): void
    {
        if (! $this->ready) {
            DB::statement('DROP TABLE IF EXISTS sa_mine');
            DB::statement('CREATE TEMP TABLE sa_mine (id bigint, family text, g geometry(LineString, 2154))');
            DB::statement('CREATE INDEX ON sa_mine USING gist (g)');
            $this->ready = true;
        }
        DB::delete('DELETE FROM sa_mine');
        // Traces rattachées à la ligne (rattachement principal : les secondaires couvrent souvent une autre ligne).
        DB::insert('INSERT INTO sa_mine (id, family, g) SELECT c.id, c.family, c.g FROM cf_pool c WHERE c.line_id = ?', [$lineId]);
    }

    private function alias(string $family): string
    {
        // Alias SQL (n_<alias>, nd_<alias>…) : pas de tiret.
        return match ($family) {
            'archeoferroviaire' => 'archeo',
            'ign-historique' => 'ignh',
            default => $family,
        };
    }

    /** Famille de la géométrie retenue pour un morceau. */
    private function ownFamily(object $part): string
    {
        if ($part->matched_source !== null) {
            return $part->matched_source;
        }

        return $part->source;
    }

    /** Écarts par famille, classe de confiance et écart à la source indépendante la plus proche. */
    private function evaluate(object $s, string $own, int $lineId, bool $osmLifecycle = false): array
    {
        $d = [];
        $agree = [];
        $mine = [];
        foreach (self::FAMILIES as $f) {
            if ($f === $own) {
                continue;
            }
            $a = $this->alias($f);
            if ($s->{'nd_'.$a} !== null) {
                $d[$f] = ['d' => (float) $s->{'nd_'.$a}, 'id' => (int) $s->{'ni_'.$a}, 'line' => $s->{'nl_'.$a}, 'cls' => $s->{'nc_'.$a}];
                if ($d[$f]['d'] <= self::AGREE[$f]) {
                    $agree[] = $f;
                }
            }
            if ($s->{'md_'.$a} !== null) {
                $mine[$f] = ['d' => (float) $s->{'md_'.$a}, 'id' => (int) $s->{'mi_'.$a}];
            }
        }
        // Voie extraite des photos IGN (~2-5 m) : au mieux « medium » ; « high » seulement si BD TOPO / OSM concordent.
        $ownPrecise = in_array($own, self::PRECISE, true) || in_array($own, ['fusion', 'ign-historique'], true);
        if ($ownPrecise && $own === 'osm' && $osmLifecycle && ! $this->independentlyConfirmed($d)) {
            $ownPrecise = false;
        }
        $precAgree = array_intersect($agree, self::PRECISE);
        $class = $ownPrecise ? ($precAgree ? 'high' : 'medium') : 'low';

        $gap = null;
        foreach ([self::PRECISE, self::FAMILIES] as $set) {
            foreach ($d as $f => $v) {
                if (in_array($f, $set, true) && ($gap === null || $v['d'] < $gap)) {
                    $gap = $v['d'];
                }
            }
            if ($gap !== null) {
                break;
            }
        }

        return ['class' => $class, 'd' => $d, 'agree' => $agree, 'mine' => $mine, 'gap' => $gap,
            'lon' => (float) $s->lon, 'lat' => (float) $s->lat];
    }

    /**
     * Expression SQL (booléen) sur un morceau `p` de line_reference_parts : son dessin vient d'OSM, recalé sur des voies
     * OSM (matched_trace_ids) ou gardé tel quel depuis la trace source de la ligne (source_trace_ids), et toutes ces
     * voies sont abandonnées, déposées ou inutilisées (OSM_LIFECYCLE_LAYERS).
     */
    public static function osmLifecycleSql(): string
    {
        $layers = "'".implode("', '", self::OSM_LIFECYCLE_LAYERS)."'";
        $ids = 'CASE WHEN p.matched_source IS NULL THEN p.source_trace_ids ELSE p.matched_trace_ids END';

        return <<<SQL
            (COALESCE(p.matched_source, p.source) = 'osm'
             AND jsonb_array_length(COALESCE({$ids}, '[]'::jsonb)) > 0
             AND NOT EXISTS (
                SELECT 1 FROM source_traces s
                WHERE s.id IN (SELECT jsonb_array_elements_text({$ids})::bigint)
                  AND s.layer NOT IN ({$layers})))
            SQL;
    }

    /**
     * Confirmation indépendante d'une voie OSM abandonnée, à LIFECYCLE_CONFIRM_M près : BD TOPO, LiDAR HD, ou axe
     * affiné sur photo recalée (l'extraction au pixel, classe « ortho », ne suffit pas : 2 à 9 m).
     *
     * @param  array<string, array{d: float, id: int, line: mixed, cls: mixed}>  $d
     */
    private function independentlyConfirmed(array $d): bool
    {
        $near = fn (string $f): bool => isset($d[$f]) && $d[$f]['d'] <= self::LIFECYCLE_CONFIRM_M;

        return $near('bdtopo') || $near('lidar')
            || ($near('ign-historique') && $d['ign-historique']['cls'] === 'ortho-precis');
    }

    /** Portions de moins de MIN_RUN_SAMPLES échantillons absorbées par la voisine la plus longue. */
    private function smooth(array $classes): array
    {
        $n = count($classes);
        for ($guard = 0; $guard < $n; $guard++) {
            $runs = [];
            $j0 = 0;
            for ($j = 1; $j <= $n; $j++) {
                if ($j === $n || $classes[$j] !== $classes[$j0]) {
                    $runs[] = [$j0, $j - 1, $classes[$j0]];
                    $j0 = $j;
                }
            }
            $short = null;
            foreach ($runs as $r => [$a, $b]) {
                if (count($runs) > 1 && $b - $a + 1 < self::MIN_RUN_SAMPLES
                    && ($short === null || $b - $a < $runs[$short][1] - $runs[$short][0])) {
                    $short = $r;
                }
            }
            if ($short === null) {
                break;
            }
            $prev = $runs[$short - 1] ?? null;
            $next = $runs[$short + 1] ?? null;
            $target = ! $prev ? $next : (! $next ? $prev : (($prev[1] - $prev[0]) >= ($next[1] - $next[0]) ? $prev : $next));
            for ($j = $runs[$short][0]; $j <= $runs[$short][1]; $j++) {
                $classes[$j] = $target[2];
            }
        }

        return $classes;
    }

    /**
     * Divergences sur une séquence d'échantillons :
     *   precise : autre source précise du même axe (non rattachée à une autre ligne) à plus de 8 m ;
     *   line    : source moins précise rattachée à la ligne, écartée de plus de DIVERGE[f], qui rejoint
     *             la géométrie retenue aux deux bouts (déviation / ancien tracé) ou court jusqu'au bout.
     */
    private function divergences(array $seq, array $evals, object $part, string $own): array
    {
        $out = [];
        $n = count($evals);
        $ownRank = array_search($own, self::FAMILIES, true);
        $ownRank = $ownRank === false ? count(self::FAMILIES) : $ownRank;
        foreach (self::FAMILIES as $rank => $f) {
            if ($f === $own) {
                continue;
            }
            foreach (['precise', 'line'] as $type) {
                if ($type === 'precise' && (! in_array($own, self::PRECISE, true) || ! in_array($f, self::PRECISE, true))) {
                    continue;
                }
                if ($type === 'line' && $rank <= $ownRank) {
                    continue;
                }
                $flag = [];
                foreach ($evals as $j => $e) {
                    if ($type === 'precise') {
                        $v = $e['d'][$f] ?? null;
                        $flag[$j] = $v && $v['cls'] === 'main' && $v['d'] > self::DIVERGE[$f]
                            && ($v['line'] === null || (int) $v['line'] === (int) $part->line_id)
                            ? $v : null;
                    } else {
                        $v = $e['mine'][$f] ?? null;
                        $flag[$j] = $v && $v['d'] > self::DIVERGE[$f] ? $v : null;
                    }
                }
                for ($j = 0; $j < $n; $j++) {
                    if (! $flag[$j]) {
                        continue;
                    }
                    $j0 = $j;
                    while ($j + 1 < $n && $flag[$j + 1]) {
                        $j++;
                    }
                    $j1 = $j;
                    if ($j1 - $j0 + 1 < self::MIN_RUN_SAMPLES) {
                        continue;
                    }
                    if ($type === 'line') {
                        // Une branche qui s'en va n'est pas une divergence : la source doit rejoindre
                        // la géométrie retenue de part et d'autre (ou la portion court jusqu'au bout).
                        $close = fn ($k) => isset($evals[$k]['mine'][$f]) && $evals[$k]['mine'][$f]['d'] <= self::DIVERGE[$f];
                        $okStart = $j0 === 0 || $close($j0 - 1);
                        $okEnd = $j1 === $n - 1 || $close($j1 + 1);
                        if (! $okStart || ! $okEnd || ($j0 === 0 && $j1 === $n - 1 && $n < 20)) {
                            continue;
                        }
                    }
                    $ds = [];
                    $ids = [];
                    for ($k = $j0; $k <= $j1; $k++) {
                        $ds[] = $flag[$k]['d'];
                        $ids[$flag[$k]['id']] = true;
                    }
                    sort($ds);
                    $mid = $evals[intdiv($j0 + $j1, 2)];
                    $len = ($j1 - $j0 + 1) / (int) $seq[0]->n * (float) $seq[0]->len;
                    $med = $ds[intdiv(count($ds), 2)];
                    $coords = [];
                    for ($k = $j0; $k <= $j1; $k++) {
                        $coords[] = sprintf('%.7F %.7F', $evals[$k]['lon'], $evals[$k]['lat']);
                    }
                    $out[] = [
                        'wkt' => 'LINESTRING('.implode(',', $coords).')',
                        'line_id' => (int) $part->line_id, 'type' => $type, 'retained' => $own, 'other' => $f,
                        'length_m' => round($len), 'gap_median_m' => round($med, 1), 'gap_max_m' => round(end($ds), 1),
                        'score' => round($med * $len / 1000, 1),
                        'retained_ids' => '',
                        'other_ids' => implode(' ', array_keys($ids)),
                        'lat' => round($mid['lat'], 6), 'lon' => round($mid['lon'], 6),
                    ];
                }
            }
        }

        return $out;
    }

    /** Enregistre les divergences de la ligne (line_reference_divergences). */
    private function store(int $lineId, array $divergences): void
    {
        DB::delete('DELETE FROM line_reference_divergences WHERE line_id = ?', [$lineId]);
        $ids = fn (string $s) => json_encode(array_map('intval', array_values(array_filter(explode(' ', $s)))));
        foreach ($divergences as $d) {
            DB::insert(<<<'SQL'
                INSERT INTO line_reference_divergences (line_id, kind, retained_source, other_source, length_m, gap_median_m, gap_max_m, score,
                    retained_trace_ids, other_trace_ids, geom, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, CAST(? AS jsonb), CAST(? AS jsonb), ST_GeomFromText(?, 4326), NOW(), NOW())
                SQL, [$lineId, $d['type'] === 'precise' ? 'precise' : 'line_source', $d['retained'], $d['other'], $d['length_m'],
                $d['gap_median_m'], $d['gap_max_m'], $d['score'], $ids($d['retained_ids'] ?? ''), $ids($d['other_ids']), $d['wkt']]);
        }
    }

    /** Remplace chaque morceau par ses portions homogènes de confiance, avec leurs statistiques. */
    private function rewriteParts(int $lineId, array $parts, array $groups): void
    {
        foreach ($groups as $partId => $byClass) {
            $p = $parts[$partId];
            $own = $this->ownFamily($p);
            foreach ($byClass as $class => $g) {
                $stats = $this->stats($g['samples'], $own);
                DB::insert(<<<'SQL'
                    INSERT INTO line_reference_parts (line_id, source, source_trace_ids, matched_source, matched_trace_ids,
                        confidence, sources_agreeing, gap_median_m, gap_max_m, agreement, length_m, geom, created_at, updated_at)
                    SELECT p.line_id, p.source, p.source_trace_ids, p.matched_source, p.matched_trace_ids,
                           :class, :agreeing, :gmed, :gmax, CAST(:agreement AS jsonb),
                           ROUND(SUM(ST_Length(x.g)))::int,
                           ST_Multi(ST_Transform(ST_Collect(x.g), 4326)), NOW(), NOW()
                    FROM line_reference_parts p
                    CROSS JOIN LATERAL (
                        SELECT ST_LineSubstring(d.geom, r.f0, r.f1) AS g
                        FROM unnest(CAST(:subs AS int[]), CAST(:f0 AS float8[]), CAST(:f1 AS float8[])) AS r(sub, f0, f1)
                        JOIN LATERAL ST_Dump(ST_Transform(p.geom, 2154)) d ON d.path[1] = r.sub
                        WHERE r.f1 > r.f0
                    ) x
                    WHERE p.id = :id
                    GROUP BY p.line_id, p.source, p.source_trace_ids, p.matched_source, p.matched_trace_ids
                    SQL, [
                    'class' => $class, 'agreeing' => $stats['agreeing'], 'gmed' => $stats['gap_median'], 'gmax' => $stats['gap_max'],
                    'agreement' => json_encode($stats['detail']),
                    'subs' => '{'.implode(',', array_column($g['runs'], 0)).'}',
                    'f0' => '{'.implode(',', array_map(fn ($r) => sprintf('%.12F', $r[1]), $g['runs'])).'}',
                    'f1' => '{'.implode(',', array_map(fn ($r) => sprintf('%.12F', $r[2]), $g['runs'])).'}',
                    'id' => $partId,
                ]);
            }
        }
        DB::delete('DELETE FROM line_reference_parts WHERE id = ANY(CAST(? AS bigint[]))', ['{'.implode(',', array_keys($groups)).'}']);
    }

    private function stats(array $samples, string $own): array
    {
        $n = count($samples);
        $gaps = array_values(array_filter(array_column($samples, 'gap'), fn ($g) => $g !== null));
        sort($gaps);
        $detail = [];
        $agreeing = 1;
        foreach (self::FAMILIES as $f) {
            if ($f === $own) {
                continue;
            }
            $ds = [];
            $ok = 0;
            foreach ($samples as $s) {
                if (isset($s['d'][$f])) {
                    $ds[] = $s['d'][$f]['d'];
                    $ok += $s['d'][$f]['d'] <= self::AGREE[$f] ? 1 : 0;
                }
            }
            if (! $ds) {
                continue;
            }
            sort($ds);
            $detail[$f] = [
                'median' => round($ds[intdiv(count($ds), 2)], 1), 'max' => round(end($ds), 1),
                'coverage' => round(count($ds) / $n, 2), 'agree' => round($ok / $n, 2),
            ];
            if ($ok / $n >= 0.5) {
                $agreeing++;
            }
        }

        return [
            'agreeing' => $agreeing,
            'gap_median' => $gaps ? round($gaps[intdiv(count($gaps), 2)], 1) : null,
            'gap_max' => $gaps ? round(end($gaps), 1) : null,
            'detail' => $detail ?: new \stdClass,
        ];
    }
}
