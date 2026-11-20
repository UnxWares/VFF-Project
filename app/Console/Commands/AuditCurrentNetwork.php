<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Contrôle qualité chiffré de la géométrie précise du réseau actuel.
 *
 * Les formes SNCF (`sncf_line_geometries`) sont échantillonnées tous les
 * 100 m ; pour chaque point on cherche le tracé BD TOPO / OSM RATTACHÉ à la
 * même ligne (par `vff:match-current-network`) le plus proche :
 *   - couvert         : un tracé rattaché à la ligne est à ≤ 150 m (la
 *                       tolérance du rattachement souple) ;
 *   - couvert (brut)  : couvert, ou n'importe quel tracé principal de la
 *                       source à ≤ 50 m - la donnée existe mais n'a pas été
 *                       rattachée (nœud, ambiguïté…) ;
 *   - écart           : distance forme SNCF → tracé rattaché, c.-à-d. l'erreur
 *                       de généralisation de la forme SNCF (médiane, p90, max).
 * Les longueurs sont pondérées (longueur de la forme / nombre de points).
 *
 * Résultats enregistrés dans `rfn_trace_coverage` (une ligne par code, statut
 * SNCF et source ; recalcul complet à chaque passage) puis résumés : couverture
 * par statut, écarts, lignes mal couvertes, détail des lignes demandées.
 *
 *   php artisan vff:audit-current-network
 *   php artisan vff:audit-current-network --line=420000 --line=878000
 */
class AuditCurrentNetwork extends Command
{
    protected $signature = 'vff:audit-current-network
        {--source=* : bdtopo, osm (défaut : les deux)}
        {--step=100 : pas d\'échantillonnage des formes SNCF (m)}
        {--tolerance=150 : distance max (m) forme SNCF ↔ tracé rattaché}
        {--any-tolerance=50 : distance max (m) pour la couverture brute}
        {--line=* : codes de ligne à détailler}
        {--worst=25 : nombre de lignes mal couvertes à lister}';

    protected $description = 'Mesure la couverture des formes SNCF par les tracés BD TOPO / OSM rattachés et leurs écarts.';

    public function handle(): int
    {
        $sources = $this->option('source') ?: ['bdtopo', 'osm'];
        // Les noms de source sont repris dans le SQL du récapitulatif : liste fermée.
        if ($unknown = array_diff($sources, ['bdtopo', 'osm'])) {
            $this->error('Source inconnue : '.implode(', ', $unknown).' (attendu : bdtopo, osm).');

            return self::FAILURE;
        }
        $step = max(10.0, (float) $this->option('step'));
        $tol = (float) $this->option('tolerance');
        $anyTol = (float) $this->option('any-tolerance');

        DB::statement('DROP TABLE IF EXISTS a_pts');
        DB::statement(<<<'SQL'
            CREATE TEMP TABLE a_pts AS
            SELECT s.code_ligne AS code, s.libelle AS statut, l.id AS line_id,
                   ST_LineInterpolatePoint(s.g, (k - 0.5) / c.n) AS p, s.len / c.n AS w
            FROM (SELECT code_ligne, libelle, ST_Transform(geom, 2154) AS g, ST_Length(ST_Transform(geom, 2154)) AS len
                  FROM sncf_line_geometries) s
            CROSS JOIN LATERAL (SELECT GREATEST(1, CEIL(s.len / ?))::int AS n) c
            CROSS JOIN LATERAL generate_series(1, c.n) k
            LEFT JOIN LATERAL (SELECT id FROM lines WHERE code = s.code_ligne ORDER BY id LIMIT 1) l ON true
            WHERE s.len > 0
            SQL, [$step]);

        foreach ($sources as $source) {
            $this->line("→ {$source}");
            $this->measure($source, $tol, $anyTol);
        }

        $this->summary($sources);
        foreach ($this->option('line') as $code) {
            $this->lineDetail((string) $code);
        }

        return self::SUCCESS;
    }

    private function measure(string $source, float $tol, float $anyTol): void
    {
        $main = $source === 'bdtopo'
            ? "layer <> 'Voie de service' AND COALESCE(properties->>'etat_de_l_objet', '') <> 'En projet'"
            : "NOT jsonb_exists(properties, 'service')";

        DB::statement('DROP TABLE IF EXISTS a_tr');
        DB::statement(<<<SQL
            CREATE TEMP TABLE a_tr AS
            SELECT id, line_id, ({$main}) AS main, ST_Transform(geom, 2154) AS g
            FROM source_traces
            WHERE source = ? AND geom && ST_MakeEnvelope(-5.6, 41.2, 10.0, 51.2, 4326)
            SQL, [$source]);
        DB::statement('CREATE INDEX ON a_tr USING gist (g)');
        DB::statement('ANALYZE a_tr');

        DB::statement('DROP TABLE IF EXISTS a_d');
        DB::statement(<<<'SQL'
            CREATE TEMP TABLE a_d AS
            SELECT pt.code, pt.statut, pt.w,
                   (SELECT pt.p <-> t.g FROM a_tr t
                    WHERE t.line_id = pt.line_id AND ST_DWithin(t.g, pt.p, :tol)
                    ORDER BY pt.p <-> t.g LIMIT 1) AS d_match,
                   EXISTS (SELECT 1 FROM a_tr t WHERE t.main AND ST_DWithin(t.g, pt.p, :anytol)) AS near_any
            FROM a_pts pt
            SQL, ['tol' => $tol, 'anytol' => $anyTol]);

        DB::transaction(function () use ($source): void {
            DB::delete('DELETE FROM rfn_trace_coverage WHERE source = ?', [$source]);
            DB::insert(<<<'SQL'
                INSERT INTO rfn_trace_coverage (code_ligne, statut, source, length_m, covered_m, covered_any_m,
                                                offset_median_m, offset_p90_m, offset_max_m, computed_at)
                SELECT code, statut, ?, ROUND(SUM(w)), ROUND(COALESCE(SUM(w) FILTER (WHERE d_match IS NOT NULL), 0)),
                       ROUND(COALESCE(SUM(w) FILTER (WHERE d_match IS NOT NULL OR near_any), 0)),
                       ROUND(percentile_cont(0.5) WITHIN GROUP (ORDER BY d_match)::numeric, 1),
                       ROUND(percentile_cont(0.9) WITHIN GROUP (ORDER BY d_match)::numeric, 1),
                       ROUND(MAX(d_match)::numeric, 1), NOW()
                FROM a_d GROUP BY code, statut
                SQL, [$source]);
        });

        $o = DB::selectOne(<<<'SQL'
            SELECT ROUND(percentile_cont(0.5) WITHIN GROUP (ORDER BY d_match)::numeric, 1) AS med,
                   ROUND(percentile_cont(0.9) WITHIN GROUP (ORDER BY d_match)::numeric, 1) AS p90,
                   ROUND(percentile_cont(0.99) WITHIN GROUP (ORDER BY d_match)::numeric, 1) AS p99,
                   ROUND(MAX(d_match)::numeric, 1) AS max,
                   ROUND((100.0 * SUM(w) FILTER (WHERE d_match > 50) / NULLIF(SUM(w) FILTER (WHERE d_match IS NOT NULL), 0))::numeric, 2) AS over50
            FROM a_d WHERE statut = 'Exploitée'
            SQL);
        $this->line("  Écart forme SNCF « Exploitée » → tracé {$source} rattaché : médiane {$o->med} m, p90 {$o->p90} m, p99 {$o->p99} m, max {$o->max} m ; {$o->over50} % de la longueur couverte à plus de 50 m.");
    }

    private function summary(array $sources): void
    {
        $cols = [];
        foreach ($sources as $s) {
            $cols[] = "ROUND(100.0 * SUM(covered_m) FILTER (WHERE source = '{$s}') / NULLIF(SUM(length_m) FILTER (WHERE source = '{$s}'), 0), 1) AS \"{$s}\"";
            $cols[] = "ROUND(100.0 * SUM(covered_any_m) FILTER (WHERE source = '{$s}') / NULLIF(SUM(length_m) FILTER (WHERE source = '{$s}'), 0), 1) AS \"{$s}_any\"";
        }
        $first = $sources[0];
        $rows = DB::select('SELECT statut, ROUND(SUM(length_m) FILTER (WHERE source = ?) / 1000.0) AS km, '.implode(', ', $cols).'
            FROM rfn_trace_coverage GROUP BY ROLLUP (statut) ORDER BY km DESC', [$first]);
        $headers = ['Statut SNCF', 'km'];
        foreach ($sources as $s) {
            $headers[] = "% {$s} rattaché";
            $headers[] = "% {$s} brut";
        }
        $this->table($headers, array_map(fn ($r) => array_values(array_merge(
            ['statut' => $r->statut ?? 'TOTAL'], array_diff_key((array) $r, ['statut' => 0]),
        )), $rows));

        // Lignes mal couvertes : partie exploitée ou neutralisée (la voie est en place).
        $s = $first;
        $other = $sources[1] ?? null;
        $worst = DB::select(<<<'SQL'
            SELECT c.code_ligne, l.name, ROUND(SUM(c.length_m) / 1000.0, 1) AS km,
                   ROUND((SUM(c.length_m) - SUM(c.covered_m)) / 1000.0, 1) AS manque_km,
                   ROUND(100.0 * SUM(c.covered_m) / NULLIF(SUM(c.length_m), 0), 1) AS pct,
                   ROUND(100.0 * SUM(c.covered_any_m) / NULLIF(SUM(c.length_m), 0), 1) AS pct_any,
                   (SELECT ROUND(100.0 * SUM(o.covered_m) / NULLIF(SUM(o.length_m), 0), 1) FROM rfn_trace_coverage o
                    WHERE o.code_ligne = c.code_ligne AND o.source = ? AND o.statut IN ('Exploitée', 'Neutralisée')) AS pct_other
            FROM rfn_trace_coverage c
            LEFT JOIN LATERAL (SELECT name FROM lines WHERE code = c.code_ligne ORDER BY id LIMIT 1) l ON true
            WHERE c.source = ? AND c.statut IN ('Exploitée', 'Neutralisée')
            GROUP BY c.code_ligne, l.name
            HAVING SUM(c.length_m) - SUM(c.covered_m) >= 1000 OR SUM(c.covered_m) < 0.9 * SUM(c.length_m)
            ORDER BY SUM(c.length_m) - SUM(c.covered_m) DESC
            SQL, [$other ?? $s, $s]);
        $this->line(sprintf('Lignes mal couvertes par %s (parties exploitées/neutralisées, < 90 %% ou ≥ 1 km manquant) : %d', $s, count($worst)));
        $this->table(['Code', 'Ligne', 'km', 'manque km', "% {$s}", "% {$s} brut", $other ? "% {$other}" : '-'],
            array_map(fn ($r) => [$r->code_ligne, mb_strimwidth((string) $r->name, 0, 55, '…'), $r->km, $r->manque_km, $r->pct, $r->pct_any, $r->pct_other],
                array_slice($worst, 0, (int) $this->option('worst'))));
    }

    private function lineDetail(string $code): void
    {
        $name = DB::table('lines')->where('code', $code)->value('name');
        $this->line("Détail {$code} - {$name}");
        $rows = DB::select(<<<'SQL'
            SELECT statut, source, ROUND(length_m / 1000.0, 1) AS km, ROUND(100.0 * covered_m / NULLIF(length_m, 0), 1) AS pct,
                   ROUND(100.0 * covered_any_m / NULLIF(length_m, 0), 1) AS pct_any, offset_median_m, offset_p90_m, offset_max_m
            FROM rfn_trace_coverage WHERE code_ligne = ? ORDER BY statut, source
            SQL, [$code]);
        $this->table(['Statut', 'Source', 'km', '% rattaché', '% brut', 'écart méd.', 'p90', 'max'],
            array_map(fn ($r) => array_values((array) $r), $rows));

        $traces = DB::select(<<<'SQL'
            SELECT source, layer, COUNT(*) AS n, ROUND(SUM(length_m) / 1000.0, 1) AS km, ROUND(AVG(match_score)::numeric, 3) AS score
            FROM source_traces t JOIN lines l ON l.id = t.line_id
            WHERE l.code = ? AND t.source IN ('bdtopo', 'osm')
            GROUP BY 1, 2 ORDER BY 1, 4 DESC
            SQL, [$code]);
        $this->table(['Source', 'Calque', 'Tracés rattachés', 'km', 'score moyen'],
            array_map(fn ($r) => array_values((array) $r), $traces));
    }
}
