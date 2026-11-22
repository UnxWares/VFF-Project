<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Services\SingleAxis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Un seul axe par ligne (App\Services\SingleAxis, étape de
 * vff:build-line-references) : une section saisie sur deux axes parallèles
 * n'en garde qu'un, sans trou ; une branche qui longe la ligne puis s'en va
 * est raccordée au point de divergence ; un ancien tracé daté reste. Nettoyage
 * final (tidy) : bouts superposés retirés, un seul raccord par trou, raccords
 * courts fusionnés, extrémités recollées au bit près (sinon le moteur décennal
 * produit des tronçons en double ou de longueur nulle).
 *
 * Géométries synthétiques en Lambert-93 (autour de x = 650 000, y = 6 860 000).
 */
class SingleAxisTest extends TestCase
{
    use RefreshDatabase;

    private const X0 = 650000;

    private const Y0 = 6860000;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->line = Line::factory()->create();
    }

    /** WKT Lambert-93 d'une polyligne en coordonnées relatives (m). */
    private function wkt(array $pts): string
    {
        return 'LINESTRING('.implode(',', array_map(fn ($p) => (self::X0 + $p[0]).' '.(self::Y0 + $p[1]), $pts)).')';
    }

    private function trace(string $source, array $pts, array $extra = []): int
    {
        return DB::table('source_traces')->insertGetId(array_merge([
            'source' => $source, 'source_ref' => $source.'-'.uniqid(), 'layer' => $source === 'bdtopo' ? 'Voie ferrée principale' : 'principal',
            'line_id' => $this->line->id, 'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw("ST_Multi(ST_Transform(ST_GeomFromText('".$this->wkt($pts)."', 2154), 4326))"),
        ], $extra));
    }

    /** Morceau du tracé de référence (une ou plusieurs polylignes). */
    private function part(string $source, array $lines, array $ids, ?string $matched = null): void
    {
        $wkt = 'MULTILINESTRING('.implode(',', array_map(fn ($l) => substr($this->wkt($l), strlen('LINESTRING')), $lines)).')';
        DB::insert(<<<'SQL'
            INSERT INTO line_reference_parts (line_id, source, source_trace_ids, matched_source, length_m, geom, created_at, updated_at)
            SELECT ?, ?, CAST(? AS jsonb), ?, ROUND(ST_Length(g)), ST_Transform(g, 4326), NOW(), NOW()
            FROM (SELECT ST_GeomFromText(?, 2154) AS g) t
            SQL, [$this->line->id, $source, json_encode($ids), $matched, $wkt]);
    }

    private function totalLength(): float
    {
        return (float) DB::selectOne('SELECT COALESCE(SUM(ST_Length(ST_Transform(geom, 2154))), 0) AS m FROM line_reference_parts WHERE line_id = ?', [$this->line->id])->m;
    }

    /** Extrémités libres (ne touchant aucune autre polyligne de la ligne à 1 m près). */
    private function freeEnds(): int
    {
        return (int) DB::selectOne(<<<'SQL'
            WITH l AS (SELECT d.geom AS g FROM line_reference_parts p, LATERAL ST_Dump(ST_Transform(p.geom, 2154)) d WHERE p.line_id = ?),
            e AS (SELECT ST_StartPoint(g) AS p FROM l UNION ALL SELECT ST_EndPoint(g) FROM l)
            SELECT COUNT(*) FILTER (WHERE (SELECT COUNT(*) FROM l WHERE ST_DWithin(l.g, e.p, 1)) = 1) AS n FROM e
            SQL, [$this->line->id])->n;
    }

    public function test_four_track_section_drawn_on_two_axes_keeps_a_single_axis_without_gap(): void
    {
        $ids = [$this->trace('bdtopo', [[0, 0], [8000, 0]])];
        // Axe principal continu (8 km) et second axe à 10 m, raccordé aux deux bouts (2 → 6 km).
        $this->part('bdtopo', [
            [[0, 0], [2000, 0]],
            [[2000, 0], [6000, 0]],
            [[6000, 0], [8000, 0]],
            [[2000, 0], [2100, 10], [5900, 10], [6000, 0]],
        ], $ids);

        $stats = app(SingleAxis::class)->collapse($this->line->id);

        $this->assertEqualsWithDelta(8000, $this->totalLength(), 5);
        $this->assertSame(2, $this->freeEnds());
        $this->assertEqualsWithDelta(4001, $stats['removed:bdtopo/bdtopo'], 10);
        $dup = DB::table('line_reference_duplicates')->where('line_id', $this->line->id)->sole();
        $this->assertSame('removed', $dup->action);
        $this->assertEqualsWithDelta(10, $dup->gap_median_m, 0.5);
        $this->assertSame($ids, json_decode($dup->trace_ids, true));
    }

    public function test_continuous_track_keeps_the_whole_section_instead_of_jumping_between_axes(): void
    {
        // Axe sud (voie BD TOPO coupée aux aiguillages) : 3 chaînes bout à bout de 500 m, 1 500 m continus ;
        // axe nord à 12 m : une seule chaîne de 800 m, la plus longue chaîne prise isolément.
        $ids = [$this->trace('bdtopo', [[0, 0], [1500, 0]])];
        $this->part('bdtopo', [[[0, 0], [500, 0]], [[500, 0], [1000, 0]], [[1000, 0], [1500, 0]], [[350, 12], [1150, 12]]], $ids);

        app(SingleAxis::class)->collapse($this->line->id);

        // La voie continue est gardée d'un bout à l'autre ; l'axe nord, dans son ombre, est retiré en entier : aucun saut.
        $this->assertEqualsWithDelta(1500, $this->totalLength(), 1);
        $this->assertSame(2, $this->freeEnds());
        $this->assertSame(0, DB::table('line_reference_parts')->where('line_id', $this->line->id)->where('matched_source', 'connector')->count());
        $this->assertEqualsWithDelta(0, (float) DB::selectOne('SELECT MAX(ST_Y(d.geom)) - ? AS y FROM line_reference_parts p, LATERAL ST_DumpPoints(ST_Transform(p.geom, 2154)) d WHERE p.line_id = ?', [self::Y0, $this->line->id])->y, 0.01);
    }

    public function test_a_diverging_branch_at_a_switch_does_not_extend_the_through_track(): void
    {
        // Voie directe : 2 chaînes bout à bout (0 → 600, 600 → 1 200). Branche déviée de 20° au même nœud : elle
        // ne prolonge pas la voie directe (la plus droite seulement) ; voie parallèle au nord, 900 m d'un seul tenant.
        $ids = [$this->trace('bdtopo', [[0, 0], [1200, 0]])];
        $this->part('bdtopo', [[[0, 0], [600, 0]], [[600, 0], [1200, 0]], [[600, 0], [1070, 171]], [[150, 10], [1050, 10]]], $ids);

        app(SingleAxis::class)->collapse($this->line->id);

        // La voie directe (1 200 m continus) l'emporte sur la parallèle de 900 m, retirée en entier.
        $this->assertEqualsWithDelta(1200 + hypot(470, 171), $this->totalLength(), 2);
        $this->assertSame(0, DB::table('line_reference_parts')->where('line_id', $this->line->id)->where('matched_source', 'connector')->count());
    }

    public function test_short_branch_joining_a_track_does_not_outrank_the_track_it_joins(): void
    {
        // Y : la voie (1 080 m, en courbe) et une branche droite de 300 m rejoignent au même nœud la suite (4 km).
        // La suite se prolonge le plus droit par la branche, mais la voie aussi se prolonge par la suite :
        // la voie est la plus longue voie continue, la branche (à 4-15 m) est retirée en entier.
        $ids = [$this->trace('bdtopo', [[0, -60], [1000, 0], [5000, 0]])];
        $this->part('bdtopo', [[[0, -60], [600, -20], [900, -4], [1000, 0]], [[700, 0], [1000, 0]], [[1000, 0], [5000, 0]]], $ids);

        app(SingleAxis::class)->collapse($this->line->id);

        $voie = 4000 + hypot(600, 40) + hypot(300, 16) + hypot(100, 4);
        $this->assertEqualsWithDelta($voie, $this->totalLength(), 1);
        $this->assertSame(2, $this->freeEnds());
    }

    public function test_branch_running_alongside_then_diverging_is_cut_at_the_divergence_point_and_connected(): void
    {
        $main = $this->trace('bdtopo', [[0, 0], [5000, 0]]);
        $this->part('bdtopo', [[[0, 0], [5000, 0]]], [$main]);
        // Branche OSM : 1 km le long de la ligne à 8 m, puis s'en va vers le nord.
        $branch = $this->trace('osm', [[1000, 8], [2000, 8], [2400, 60], [3000, 600]], ['layer' => 'rail']);
        $this->part('osm', [[[1000, 8], [2000, 8], [2400, 60], [3000, 600]]], [$branch]);

        app(SingleAxis::class)->collapse($this->line->id);

        // La partie parallèle est retirée ; la branche part de la ligne par un raccord court.
        $osm = (float) DB::selectOne("SELECT SUM(ST_Length(ST_Transform(geom, 2154))) AS m FROM line_reference_parts WHERE line_id = ? AND source = 'osm' AND matched_source IS NULL", [$this->line->id])->m;
        // Reste la portion qui s'écarte : 403 m (8 → 60 m de la ligne) + 807 m, coupée au point de divergence.
        $this->assertEqualsWithDelta(1210, $osm, 25);
        $connector = DB::selectOne("SELECT SUM(ST_Length(ST_Transform(geom, 2154))) AS m FROM line_reference_parts WHERE line_id = ? AND matched_source = 'connector'", [$this->line->id]);
        $this->assertNotNull($connector->m);
        $this->assertLessThan(15, (float) $connector->m);
        // Bouts libres : les deux extrémités de la ligne et celle de la branche.
        $this->assertSame(3, $this->freeEnds());
    }

    public function test_a_gap_left_by_a_cut_is_stitched_by_a_single_connector(): void
    {
        $main = $this->trace('bdtopo', [[0, 0], [1000, 0]]);
        $this->part('bdtopo', [[[0, 0], [1000, 0]]], [$main]);
        // Tracé grossier qui reprend la voie sur 500 m puis la prolonge : la coupe (pas de 10 m)
        // laisse un trou de quelques mètres, vu depuis ses deux bords (coupe et extrémité de la voie).
        $copy = $this->trace('archeoferroviaire', [[503, 0.3], [2000, 0.3]]);
        $this->part('archeoferroviaire', [[[503, 0.3], [2000, 0.3]]], [$copy]);

        app(SingleAxis::class)->collapse($this->line->id);

        $connectors = DB::select("SELECT ST_Length(ST_Transform(d.geom, 2154)) AS m FROM line_reference_parts p, LATERAL ST_Dump(p.geom) d WHERE p.line_id = ? AND p.matched_source = 'connector'", [$this->line->id]);
        $this->assertCount(1, $connectors);
        $this->assertLessThan(10, $connectors[0]->m);
        $this->assertSame(2, $this->freeEnds());
    }

    /**
     * Comme le moteur décennal (vff:build-era-segments) : tracé de référence relu en Lambert-93,
     * noué (ST_UnaryUnion) puis fusionné (ST_LineMerge) ; longueurs des lignes obtenues.
     *
     * @return list<float>
     */
    private function engineLines(): array
    {
        return array_map(fn ($r) => (float) $r->m, DB::select(<<<'SQL'
            SELECT ST_Length(d.geom) AS m
            FROM (SELECT ST_LineMerge(ST_UnaryUnion(ST_Collect(ST_Transform(dd.geom, 2154)))) AS g
                  FROM line_reference_parts p, LATERAL ST_Dump(p.geom) dd WHERE p.line_id = ?) u,
                 LATERAL ST_Dump(u.g) d
            ORDER BY 1
            SQL, [$this->line->id]));
    }

    /** Longueur (m) des portions de la ligne superposées à une autre à moins de 0,5 m. */
    private function overlapLength(): float
    {
        return (float) DB::selectOne(<<<'SQL'
            WITH d AS (SELECT row_number() OVER () AS k, ST_Transform(d.geom, 2154) AS g FROM line_reference_parts p, LATERAL ST_Dump(p.geom) d WHERE p.line_id = ?)
            SELECT COALESCE(SUM(ST_Length(ST_Intersection(a.g, ST_Buffer(b.g, 0.5)))), 0) AS m
            FROM d a JOIN d b ON a.k < b.k AND ST_DWithin(a.g, b.g, 0.5)
            WHERE ST_Length(ST_Intersection(a.g, ST_Buffer(b.g, 0.5))) > 2
            SQL, [$this->line->id])->m;
    }

    public function test_tidy_trims_traces_overlapping_end_to_end_and_joins_them_exactly(): void
    {
        // Somain - Orchies : deux portions recalées sur la même voie qui se chevauchent sur 90 m (à 0,2 m).
        $ids = [$this->trace('archeoferroviaire', [[0, 0], [2000, 0]])];
        $this->part('archeoferroviaire', [[[0, 0], [1000, 0]], [[910, 0.2], [1500, 0.2], [2000, 0.2]]], $ids, 'bdtopo');
        $this->assertGreaterThan(80, $this->overlapLength());

        $stats = app(SingleAxis::class)->tidy($this->line->id);

        $this->assertEqualsWithDelta(90, $stats['superposed_m'], 2);
        $this->assertEqualsWithDelta(2000, $this->totalLength(), 2);
        $this->assertLessThan(1, $this->overlapLength());
        $this->assertSame(2, $this->freeEnds());
        // Bout à bout au bit près : le moteur n'en fait qu'une seule ligne.
        $this->assertCount(1, $this->engineLines());
        $dup = DB::table('line_reference_duplicates')->where('line_id', $this->line->id)->sole();
        $this->assertSame('superposition', $dup->reason);
        $this->assertEqualsWithDelta(90, $dup->length_m, 2);
    }

    public function test_tidy_keeps_a_single_connector_per_gap_and_folds_short_ones(): void
    {
        $ids = [$this->trace('bdtopo', [[0, 0], [1500, 0]])];
        $this->part('bdtopo', [[[0, 0], [500, 0]], [[520, 0], [1000, 0]], [[1003, 0], [1500, 0]]], $ids);
        // Trou de 20 m recousu depuis ses deux bords (deux raccords superposés), trou de 3 m recousu une fois.
        $this->part('bdtopo', [[[500, 0], [520, 0]], [[520, 0], [500.000001, 0.000001]], [[1000, 0], [1003, 0]]], $ids, 'connector');

        app(SingleAxis::class)->tidy($this->line->id);

        $connectors = DB::select("SELECT ST_Length(ST_Transform(d.geom, 2154)) AS m FROM line_reference_parts p, LATERAL ST_Dump(p.geom) d WHERE p.line_id = ? AND p.matched_source = 'connector'", [$this->line->id]);
        $this->assertCount(1, $connectors, 'un seul raccord de 20 m ; celui de 3 m est fusionné');
        $this->assertEqualsWithDelta(20, $connectors[0]->m, 0.1);
        $this->assertEqualsWithDelta(1500, $this->totalLength(), 0.1);
        $this->assertSame(2, $this->freeEnds());
        $this->assertGreaterThan(5, min($this->engineLines()));
    }

    public function test_tidy_puts_a_tee_connector_end_exactly_on_the_line_so_the_engine_leaves_no_zero_length_piece(): void
    {
        $ids = [$this->trace('bdtopo', [[0, 0], [1000, 0]])];
        $this->part('bdtopo', [[[0, 0], [1000, 0]], [[500, 400], [500, 10]]], $ids);
        // Raccord en T dont l'extrémité dépasse la voie de 2e-7 m (point le plus proche arrondi).
        $this->part('bdtopo', [[[500, 10], [500.0000001, -0.0000002]]], $ids, 'connector');
        $this->assertLessThan(0.001, min($this->engineLines()), 'avant : bout de longueur nulle');

        app(SingleAxis::class)->tidy($this->line->id);

        $this->assertGreaterThan(400, min($this->engineLines()));
        $this->assertCount(3, $this->engineLines());
        $this->assertEqualsWithDelta(1400, $this->totalLength(), 0.1);
        // L'extrémité du raccord est un sommet de la voie.
        $this->assertTrue((bool) DB::selectOne(<<<'SQL'
            WITH d AS (SELECT p.matched_source, d.geom FROM line_reference_parts p, LATERAL ST_Dump(p.geom) d WHERE p.line_id = ?)
            SELECT EXISTS (SELECT 1 FROM d c, d v, LATERAL ST_DumpPoints(v.geom) pt
                           WHERE c.matched_source = 'connector' AND v.matched_source IS NULL AND ST_Equals(pt.geom, ST_EndPoint(c.geom))) AS ok
            SQL, [$this->line->id])->ok);
    }

    public function test_tidy_leaves_a_diverging_branch_untouched(): void
    {
        $ids = [$this->trace('bdtopo', [[0, 0], [2000, 0]])];
        // Branche qui diverge à 3° (aiguillage) : à moins de 0,5 m de la voie sur ~10 m seulement.
        $this->part('bdtopo', [[[0, 0], [1000, 0], [2000, 0]], [[1000, 0], [2000, 52.4]]], $ids);
        $before = $this->totalLength();

        $stats = app(SingleAxis::class)->tidy($this->line->id);

        $this->assertSame(0.0, $stats['superposed_m']);
        $this->assertEqualsWithDelta($before, $this->totalLength(), 0.01);
        $this->assertSame(0, DB::table('line_reference_duplicates')->where('line_id', $this->line->id)->count());
    }

    public function test_tidy_removes_a_short_piece_lying_alongside_a_more_reliable_one(): void
    {
        // 60 m de voie OSM à 3 m de la voie BD TOPO : deux tronçons en double dans chaque ère sinon.
        $this->part('bdtopo', [[[0, 0], [1000, 0]]], []);
        $this->part('osm', [[[400, 3], [460, 3]]], []);

        $stats = app(SingleAxis::class)->tidy($this->line->id);

        $this->assertEqualsWithDelta(60, $stats['near_m'], 0.5);
        $this->assertEqualsWithDelta(1000, $this->totalLength(), 0.5);
        $this->assertSame(1, DB::table('line_reference_duplicates')->where('line_id', $this->line->id)->where('reason', 'superposition')->count());
    }

    public function test_tidy_removes_the_less_reliable_of_two_short_close_pieces(): void
    {
        // Deux bouts de 60 m à 3 m : le bout OSM part, le bout BD TOPO reste.
        $this->part('bdtopo', [[[400, 3], [460, 3]]], []);
        $this->part('osm', [[[400, 0], [460, 0]]], []);

        $stats = app(SingleAxis::class)->tidy($this->line->id);

        $this->assertEqualsWithDelta(60, $stats['near_m'], 0.5);
        $this->assertSame(['bdtopo'], DB::table('line_reference_parts')->where('line_id', $this->line->id)->pluck('source')->all());
    }

    public function test_tidy_removes_a_short_reliable_piece_alongside_a_long_one(): void
    {
        // 60 m de BD TOPO à 3 m d'une voie OSM de 1 km : la longue ne peut pas partir, le bout court part (pas de doublon).
        $this->part('osm', [[[0, 0], [1000, 0]]], []);
        $this->part('bdtopo', [[[400, 3], [460, 3]]], []);

        $stats = app(SingleAxis::class)->tidy($this->line->id);

        $this->assertEqualsWithDelta(60, $stats['near_m'], 0.5);
        $this->assertEqualsWithDelta(1000, $this->totalLength(), 0.5);
    }

    public function test_tidy_removes_a_tiny_link_and_rejoins_the_track_it_held(): void
    {
        // Bout de 3 m (autre source) entre deux portions : retiré, la suivante est reposée sur la première.
        $this->part('bdtopo', [[[0, 0], [500, 0]]], []);
        $this->part('osm', [[[500, 0], [503, 0]]], []);
        $this->part('bdtopo', [[[503, 0], [1000, 0]]], []);

        $stats = app(SingleAxis::class)->tidy($this->line->id);

        $this->assertEqualsWithDelta(3, $stats['near_m'], 0.1);
        $this->assertSame(2, $this->freeEnds());
        $this->assertEqualsWithDelta(1000, $this->totalLength(), 0.1);
    }

    public function test_tidy_keeps_a_short_piece_whose_removal_would_open_a_hole(): void
    {
        // Pont de 8 m entre deux portions bout à bout distantes de 8 m : rien pour reposer les bouts à 5,5 m près.
        $this->part('bdtopo', [[[0, 0], [500, 0]]], []);
        $this->part('osm', [[[500, 0], [508, 0]]], []);
        $this->part('bdtopo', [[[508, 0], [1000, 0]]], []);

        app(SingleAxis::class)->tidy($this->line->id);

        $this->assertSame(2, $this->freeEnds());
        $this->assertEqualsWithDelta(1000, $this->totalLength(), 0.1);
    }

    /** Toury (3110, état du 21/11/2026 relatif au point 1.9424, 48.1971) : tracé grossier A, voie BD TOPO B qui le croise en biais. */
    private function touryCrossing(): void
    {
        $this->part('archeoferroviaire', [[[-15.9, 85.2], [-18.5, 69.0], [-18.7, 51.4], [-16.7, 35.0], [-11.1, 19.3], [-3.9, 5.6], [6.4, -8.2],
            [39.7, -39.9], [49.9, -51.4], [57.1, -63.9], [60.5, -78.3], [61.3, -92.0], [59.9, -103.4]]], []);
        $this->part('archeoferroviaire', [[[37.9, -41.8], [44.3, -47.8], [53.8, -53.5], [89.7, -69.8], [95.2, -72.3]]], [], 'bdtopo');
    }

    /** Distance (m) du point (relatif) au tracé de la ligne. */
    private function distToTrace(float $x, float $y): float
    {
        return (float) DB::selectOne(
            'SELECT ST_Distance(ST_SetSRID(ST_MakePoint(?, ?), 2154), ST_Collect(ST_Transform(geom, 2154))) AS d FROM line_reference_parts WHERE line_id = ?',
            [self::X0 + $x, self::Y0 + $y, $this->line->id])->d;
    }

    public function test_tidy_cuts_a_free_end_overshooting_a_shallow_crossing_with_the_same_line(): void
    {
        // La voie B commence 14 m avant de croiser A, à 1-3 m de lui : deux tronçons en double à chaque ère.
        $this->touryCrossing();

        $stats = app(SingleAxis::class)->tidy($this->line->id);

        $this->assertEqualsWithDelta(14.5, $stats['crossed_m'], 0.5);
        // Le bout croisé n'est compté qu'une fois : pas aussi dans les bouts superposés (ligne « nettoyage » de la commande).
        $this->assertSame(0.0, $stats['superposed_m']);
        $this->assertGreaterThan(1, $this->distToTrace(37.9, -41.8));
        // B part du croisement, posé exactement sur A (raccord en T, sommet de A) : restent les deux bouts de A et celui de B.
        $this->assertSame(3, $this->freeEnds());
        $this->assertSame(1, DB::table('line_reference_duplicates')->where('line_id', $this->line->id)->where('reason', 'superposition')->count());
        $this->assertSame(3, count($this->engineLines()));
    }

    public function test_tidy_keeps_the_overshoot_of_a_crossing_where_another_line_ends(): void
    {
        $this->touryCrossing();
        $other = Line::factory()->create();
        DB::insert(<<<'SQL'
            INSERT INTO line_reference_parts (line_id, source, source_trace_ids, length_m, geom, created_at, updated_at)
            SELECT ?, 'bdtopo', '[]', 200, ST_Transform(ST_GeomFromText(?, 2154), 4326), NOW(), NOW()
            SQL, [$other->id, 'MULTILINESTRING(('.(self::X0 + 41.1).' '.(self::Y0 - 44.8).','.(self::X0 + 41.1).' '.(self::Y0 - 244.8).'))']);

        $stats = app(SingleAxis::class)->tidy($this->line->id);

        $this->assertArrayNotHasKey('crossed_m', $stats);
        $this->assertLessThan(0.01, $this->distToTrace(37.9, -41.8));
    }

    public function test_tidy_keeps_the_overshoot_of_a_square_crossing(): void
    {
        // Croisement à angle droit, bout de 20 m qui s'écarte de l'autre portion : une vraie fin de voie.
        $this->part('bdtopo', [[[0, 0], [1000, 0]]], []);
        $this->part('bdtopo', [[[500, -20], [500, 400]]], []);

        $stats = app(SingleAxis::class)->tidy($this->line->id);

        $this->assertArrayNotHasKey('crossed_m', $stats);
        $this->assertEqualsWithDelta(1420, $this->totalLength(), 0.1);
    }

    public function test_dated_old_alignment_is_kept_and_undated_coarse_duplicate_is_removed(): void
    {
        $main = $this->trace('bdtopo', [[0, 0], [6000, 0]]);
        $this->part('bdtopo', [[[0, 0], [6000, 0]]], [$main]);
        // Ancien tracé daté (fermé en 1876), à 15 m de la voie actuelle sur 1 km.
        $old = $this->trace('archeoferroviaire', [[1000, 0], [1100, 15], [2000, 15], [2100, 0]], ['name' => 'Tracé initial (jusqu’en 1876)']);
        $this->part('archeoferroviaire', [[[1000, 0], [1100, 15], [2000, 15], [2100, 0]]], [$old]);
        // Tracé archeo non daté, copie décalée de 12 m de la voie actuelle.
        $copy = $this->trace('archeoferroviaire', [[3000, -12], [4500, -12]]);
        $this->part('archeoferroviaire', [[[3000, -12], [4500, -12]]], [$copy]);

        app(SingleAxis::class)->collapse($this->line->id);

        $archeo = DB::select("SELECT d.geom FROM line_reference_parts p, LATERAL ST_Dump(ST_Transform(p.geom, 2154)) d WHERE p.line_id = ? AND p.source = 'archeoferroviaire' AND p.matched_source IS NULL", [$this->line->id]);
        $this->assertCount(1, $archeo, 'seul l\'ancien tracé daté reste');
        $this->assertEqualsWithDelta(1102, $this->totalLength() - 6000, 5);
        $this->assertSame(['kept_distinct', 'removed'], DB::table('line_reference_duplicates')->where('line_id', $this->line->id)->orderBy('action')->pluck('action')->all());
    }

    /** Morceau du tracé de référence en coordonnées Lambert-93 réelles (une polyligne WKT « x y, x y… » par élément). */
    private function realPart(int $lineId, string $source, array $lines, ?string $matched = null): void
    {
        DB::insert(<<<'SQL'
            INSERT INTO line_reference_parts (line_id, source, source_trace_ids, matched_source, length_m, geom, created_at, updated_at)
            SELECT ?, ?, '[]', ?, ROUND(ST_Length(g)), ST_Transform(g, 4326), NOW(), NOW()
            FROM (SELECT ST_GeomFromText(?, 2154) AS g) t
            SQL, [$lineId, $source, $matched, 'MULTILINESTRING(('.implode('),(', $lines).'))']);
    }

    /**
     * Ligne militaire de Marcq-Saint-Juvin à Autrecourt (2893), état du 21/11/2026 autour de
     * (4.933, 49.326) : tracé archeo non recalé, deux fragments de 35 et 34 m à 153 et 292 m de ses bouts
     * libres, posés à 1-7 m du dessin de la ligne 12 (Challerange - Apremont), sur une voie OSM déposée.
     * Coordonnées réelles, découpées à 400 m des fragments (ligne 12 et voie OSM à 120 m).
     */
    private function marcqAutrecourt(): void
    {
        $id = $this->line->id;
        $this->realPart($id, 'archeoferroviaire', [
            '840126.6 6915941.1,840157.6 6915958.6,840191.7 6915984,840210.4 6916000.6,840221.8 6916012.8,840239.2 6916035.9,840256.1 6916065.2,840270 6916106.3,840281.4 6916155.3,840301 6916283.1',
            '840126.6 6915770.6,840156 6915757.7,840245.6 6915725.4,840273.6 6915717.2,840344.6 6915700.8,840417.5 6915690.2,840708.2 6915664,840746.1 6915662,840798.9 6915663.7,840820.6 6915666.9,840836.2 6915671.7,840862 6915683.9,840879 6915694.1,840920.6 6915724.1,840975 6915769.2,841007.7 6915798.8,841039.1 6915819.9,841063 6915832.8,841081.3 6915841.8,841128.1 6915858.9,841152.7 6915863.2,841191.7 6915864.5,841218.4 6915860.6,841243.2 6915852.6,841267.6 6915839.3,841285 6915825.3,841303.8 6915801,841318.7 6915773.7,841325.9 6915754.1,841333.8 6915717.6',
            '840279.2 6916144.8,840273.4 6916066.6',
            '840296.8 6915948.7,840323.6 6915902.4,840353.9 6915857.2,840390.5 6915812.5',
            '840526.6 6915742.3,840561.2 6915741.7',
            '841334.7 6915724.3,841333.5 6915713.4,841335.1 6915685.1,841335 6915609.4,841328.1 6915540.6,841327.4 6915499.1,841330.1 6915451.8,841336 6915403.8,841348.1 6915341.5',
            '841612.4 6915874.1,841580 6915883.1',
        ]);
        $this->realPart($id, 'archeoferroviaire', ['840273.4 6916066.6,840258.6 6916071.6'], 'connector');
        $other = Line::factory()->create();
        $this->realPart($other->id, 'archeoferroviaire', [
            '840406.6 6915735.6,840463 6915733.6,840540 6915737.7,840640.1 6915750.5,840730.7 6915767.3,840742.2 6915769.4,840797.7 6915784.2,840999.8 6915838.2,841218.1 6915893.7,841245.7 6915900.8,841282.4 6915911.9,841340.3 6915922.2,841379.6 6915925.8,841482.9 6915921.6,841500.2 6915919.8,841525.7 6915913.6,841591.2 6915890.8,841613 6915881.3,841627.4 6915874.7,841679.4 6915845.5,841719.2 6915816.3,841732.4 6915804.7',
        ]);
        DB::table('source_traces')->insert([
            'source' => 'osm', 'source_ref' => 'osm-'.uniqid(), 'layer' => 'abandoned', 'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw("ST_Multi(ST_Transform(ST_GeomFromText('LINESTRING(840466.6 6915733.8,840540 6915737.7,840640.1 6915750.5,840730.7 6915767.3,840738.5 6915768.4)', 2154), 4326))"),
        ]);
    }

    /** Nombre de polylignes de la ligne qui passent à 1 m du point Lambert-93 réel. */
    private function piecesAt(float $x, float $y): int
    {
        return (int) DB::selectOne(<<<'SQL'
            SELECT COUNT(*) AS n FROM line_reference_parts p, LATERAL ST_Dump(ST_Transform(p.geom, 2154)) d
            WHERE p.line_id = ? AND ST_DWithin(d.geom, ST_SetSRID(ST_MakePoint(?, ?), 2154), 1)
            SQL, [$this->line->id, $x, $y])->n;
    }

    public function test_tidy_removes_isolated_fragments_left_from_a_source_trace(): void
    {
        $this->marcqAutrecourt();
        $before = $this->totalLength();

        $stats = app(SingleAxis::class)->tidy($this->line->id);

        // Les deux fragments (35 et 34 m) partent, rien d'autre : la voie OSM déposée sous le premier ne les retient pas.
        $this->assertEqualsWithDelta(68.2, $stats['isolated_m'], 0.2);
        $this->assertEqualsWithDelta($before - 68.2, $this->totalLength(), 1);
        $this->assertSame(0, $this->piecesAt(840543.9, 6915742.0));
        $this->assertSame(0, $this->piecesAt(841596.2, 6915878.6));
        $this->assertSame(1, $this->piecesAt(840390.5, 6915812.5), 'le morceau de 166 m qui finit à 153 m du premier fragment reste');
        $this->assertSame(['fragment isolé', 'fragment isolé'], DB::table('line_reference_duplicates')->where('line_id', $this->line->id)->pluck('reason')->all());
        // Idempotent.
        $this->assertArrayNotHasKey('isolated_m', app(SingleAxis::class)->tidy($this->line->id));
    }

    public function test_tidy_keeps_a_short_fragment_continuing_a_free_end_of_the_line(): void
    {
        // Vallon (3994) : morceau de 53 m du tracé, séparé du bout de la ligne par un trou de 33 m.
        $this->part('archeoferroviaire', [[[0, 0], [500, 0]], [[533, 0], [586, 0]]], []);

        $stats = app(SingleAxis::class)->tidy($this->line->id);

        $this->assertArrayNotHasKey('isolated_m', $stats);
        $this->assertEqualsWithDelta(553, $this->totalLength(), 0.5);
    }

    public function test_tidy_keeps_isolated_fragments_tied_to_another_line_a_station_or_a_track_in_service(): void
    {
        // Trois fragments de 50 m à 300 m du bout de la ligne : un qui finit sur une autre ligne, un près d'une gare, un sur une voie OSM en service.
        $this->part('archeoferroviaire', [[[0, 0], [1000, 0]], [[1300, 200], [1350, 200]], [[1300, 400], [1350, 400]], [[1300, 600], [1350, 600]]], []);
        $other = Line::factory()->create();
        $this->realPart($other->id, 'bdtopo', [(self::X0 + 1350).' '.(self::Y0 + 200).','.(self::X0 + 1350).' '.(self::Y0 - 500)]);
        DB::table('stations')->insert([
            'name' => 'Gare d\'essai', 'slug' => 'gare-d-essai-'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw('ST_Transform(ST_SetSRID(ST_MakePoint('.(self::X0 + 1325).', '.(self::Y0 + 430).'), 2154), 4326)'),
        ]);
        $this->trace('osm', [[1250, 600], [1400, 600]], ['layer' => 'rail', 'line_id' => null]);

        $stats = app(SingleAxis::class)->tidy($this->line->id);

        $this->assertArrayNotHasKey('isolated_m', $stats);
        $this->assertEqualsWithDelta(1150, $this->totalLength(), 0.5);
        // Témoin : sans ces liens, le même fragment part.
        $this->part('archeoferroviaire', [[[1300, 800], [1350, 800]]], []);
        $this->assertEqualsWithDelta(50, app(SingleAxis::class)->tidy($this->line->id)['isolated_m'] ?? 0, 0.5);
    }
}
