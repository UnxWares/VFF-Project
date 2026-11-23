<?php

namespace Tests\Feature;

use App\Console\Commands\BuildSharedTrack;
use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Support\JunctionCurve;
use App\Support\TrackPriority;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Continuité du masquage des voies partagées (vff:build-shared-track, BuildSharedTrack::continuity) :
 * un tracé imprécis qui longe la voie d'une ligne plus active n'est plus dessiné par morceaux
 * (tirets, bouts décalés) et un bout dessiné ne s'arrête plus à côté de la voie qu'il rejoint.
 *
 * Géométries en Lambert-93 (autour de x = 650 000, y = 6 860 000), coordonnées relatives en m.
 * Ligne « A » : principale en service (masque) le long de y = 0 ; ligne « B » : fermée (masquée).
 */
class SharedTrackContinuityTest extends TestCase
{
    use RefreshDatabase;

    private const X0 = 650000;

    private const Y0 = 6860000;

    private Era $era;

    protected function setUp(): void
    {
        parent::setUp();
        $this->era = Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd\'hui']);
    }

    private function wkt(array $pts): string
    {
        return 'LINESTRING('.implode(',', array_map(fn ($p) => (self::X0 + $p[0]).' '.(self::Y0 + $p[1]), $pts)).')';
    }

    private function segment(Line $line, array $pts, string $status): LineSegment
    {
        $id = DB::table('line_segments')->insertGetId([
            'line_id' => $line->id, 'era_id' => $this->era->id, 'status' => $status, 'traffic_type' => $status === 'in_service' ? 'mixed' : 'unknown',
            'is_heritage' => false, 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw("ST_Transform(ST_GeomFromText('".$this->wkt($pts)."', 2154), 4326)"),
        ]);

        return LineSegment::findOrFail($id);
    }

    private function lines(): array
    {
        return [
            Line::factory()->create(['slug' => 'a', 'name' => 'Ligne A', 'network' => 'principal']),
            Line::factory()->create(['slug' => 'b', 'name' => 'Ligne B', 'network' => 'principal']),
        ];
    }

    private function build(): void
    {
        $this->artisan('vff:build-shared-track', ['--era' => 2030])->assertSuccessful();
    }

    /** @return list<object{covered: bool, m: float, g: string}> parties de la portion telles que les tuiles les découpent (Lambert-93) */
    private function parts(LineSegment $s): array
    {
        return DB::select('SELECT part.covered, ST_Length(ST_Transform(part.g, 2154)) AS m, ST_AsText(ST_Transform(part.g, 2154)) AS g FROM line_segments ls '
            .TrackPriority::partsJoinSql('ls').' WHERE ls.id = ?', [$s->id]);
    }

    private function drawn(LineSegment $s): float
    {
        return array_sum(array_map(fn ($p) => $p->covered ? 0.0 : (float) $p->m, $this->parts($s)));
    }

    /** Extrémités dessinées de la ligne qui ne touchent aucune autre partie dessinée de la même ligne. */
    private function drawnFreeEnds(Line $line): array
    {
        return DB::select(<<<'SQL'
            WITH d AS (
                SELECT row_number() OVER () AS i, x.geom AS g FROM line_segments ls
                LEFT JOIN line_segment_covers c ON c.segment_id = ls.id,
                LATERAL ST_Dump(ST_Transform(CASE WHEN c.segment_id IS NULL THEN ls.geom ELSE c.visible_geom END, 2154)) x
                WHERE ls.line_id = ? AND x.geom IS NOT NULL
            ),
            e AS (SELECT i, ST_StartPoint(g) AS p FROM d UNION ALL SELECT i, ST_EndPoint(g) FROM d)
            SELECT ST_X(e.p) - 650000 AS x, ST_Y(e.p) - 6860000 AS y FROM e WHERE NOT EXISTS (SELECT 1 FROM d WHERE d.i <> e.i AND ST_DWithin(d.g, e.p, 0.5))
            ORDER BY 1
            SQL, [$line->id]);
    }

    private function distanceToLine(Line $line, float $x, float $y): float
    {
        return (float) DB::selectOne('SELECT MIN(ST_Distance(ST_Transform(geom, 2154), ST_SetSRID(ST_MakePoint(?, ?), 2154))) AS d FROM line_segments WHERE line_id = ?',
            [self::X0 + $x, self::Y0 + $y, $line->id])->d;
    }

    public function test_offset_fragment_between_two_masked_parts_is_masked_too(): void
    {
        [$a, $b] = $this->lines();
        $this->segment($a, [[0, 0], [3000, 0]], 'in_service');
        // B longe A : à 5 m (masqué), s'écarte à 18 m sur 200 m (tracé imprécis), revient à 5 m, puis part au nord.
        $bs = $this->segment($b, [[0, 5], [800, 5], [850, 18], [1050, 18], [1100, 5], [2000, 5], [2100, 60], [2300, 400]], 'abandoned');

        $this->build();

        // Le morceau à 18 m (coincé entre deux parties masquées, dans le couloir de 25 m) est masqué :
        // il reste seulement la branche nord (et son approche jusqu'au contact de A).
        $drawn = array_values(array_filter($this->parts($bs), fn ($p) => ! $p->covered));
        $this->assertCount(1, $drawn);
        // (raccord de dessin compris : il vise A 20 m plus loin vers l'ouest)
        $this->assertGreaterThan(1950, (float) DB::selectOne('SELECT ST_XMin(ST_GeomFromText(?)) - 650000 AS x', [$drawn[0]->g])->x);
    }

    public function test_drawn_end_reaches_the_covering_line_instead_of_stopping_beside_it(): void
    {
        [$a, $b] = $this->lines();
        $this->segment($a, [[0, 0], [3000, 0]], 'in_service');
        // B arrive du nord, puis longe A à 8 m (même voie, tracé imprécis) jusqu'au bout.
        $this->segment($b, [[500, 600], [1000, 8], [3000, 8]], 'abandoned');

        $this->build();

        // Avant : bout dessiné arrêté à la limite des 10 m. Après : il rejoint la voie de A (raccord de dessin), à 0 m.
        $ends = array_filter($this->drawnFreeEnds($b), fn ($e) => $e->y < 100);
        $this->assertCount(1, $ends);
        $end = array_values($ends)[0];
        $this->assertLessThan(0.01, $this->distanceToLine($a, (float) $end->x, (float) $end->y));
    }

    public function test_converging_line_stays_drawn_until_it_touches_the_covering_line(): void
    {
        [$a, $b] = $this->lines();
        $this->segment($a, [[0, 0], [3000, 0]], 'in_service');
        // B converge en biais : à 10 m à x = 1000, au contact à x = 1060, puis sur la voie de A.
        $bs = $this->segment($b, [[400, 300], [1000, 10], [1060, 0], [3000, 0]], 'abandoned');

        $this->build();

        $ends = array_values(array_filter($this->drawnFreeEnds($b), fn ($e) => $e->y < 100));
        $this->assertCount(1, $ends);
        // Approche dessinée jusqu'au contact (x ~ 1052, à <= JOIN_MIN_M), puis raccord en biseau (règle 3) :
        // le bout est SUR la voie de A, quelques mètres plus loin.
        $this->assertGreaterThan(1050, (float) $ends[0]->x);
        $this->assertLessThan(1065, (float) $ends[0]->x);
        $this->assertLessThan(0.01, $this->distanceToLine($a, (float) $ends[0]->x, (float) $ends[0]->y));
        $this->assertGreaterThan(1800, array_sum(array_map(fn ($p) => $p->covered ? (float) $p->m : 0.0, $this->parts($bs))));
    }

    public function test_fragment_attached_to_another_drawn_part_of_the_line_is_kept(): void
    {
        [$a, $b] = $this->lines();
        $this->segment($a, [[0, 0], [3000, 0]], 'in_service');
        // B masquée le long de A, puis un morceau à 18 m d'où part une branche dessinée (embranchement de B).
        $this->segment($b, [[0, 5], [800, 5], [850, 18], [1050, 18], [1100, 5], [2000, 5]], 'abandoned');
        $branch = $this->segment($b, [[950, 18], [950, 800]], 'abandoned');

        $this->build();

        $this->assertEqualsWithDelta(782, $this->drawn($branch), 1);
        // Le morceau à 18 m touche la branche dessinée : il reste dessiné (au moins 150 m).
        $seg = LineSegment::where('line_id', $b->id)->where('id', '<>', $branch->id)->firstOrFail();
        $this->assertGreaterThan(150, $this->drawn($seg));
    }

    public function test_distinct_parallel_track_beyond_the_corridor_stays_drawn(): void
    {
        [$a, $b] = $this->lines();
        $this->segment($a, [[0, 0], [3000, 0]], 'in_service');
        // B : 300 m à 5 m (masqués), puis 1 km à 40 m (voie distincte), puis 300 m à 5 m.
        $bs = $this->segment($b, [[0, 5], [300, 5], [350, 40], [1350, 40], [1400, 5], [1700, 5]], 'abandoned');

        $this->build();

        $this->assertGreaterThan(1000, $this->drawn($bs));
    }

    public function test_short_tail_beyond_the_shared_track_is_masked_but_a_long_one_is_not(): void
    {
        [$a, $b] = $this->lines();
        $this->segment($a, [[0, 0], [3000, 0]], 'in_service');
        // Bout de ligne de 100 m à 15 m de A au-delà du tronc commun : masqué (même voie, tracé imprécis).
        $short = $this->segment($b, [[0, 5], [1000, 5], [1030, 15], [1130, 15]], 'abandoned');
        $c = Line::factory()->create(['slug' => 'c', 'name' => 'Ligne C', 'network' => 'principal']);
        // Bout de 400 m à 15 m : au-delà de TAIL_MAX_M, gardé dessiné.
        $long = DB::table('line_segments')->insertGetId([
            'line_id' => $c->id, 'era_id' => $this->era->id, 'status' => 'abandoned', 'traffic_type' => 'unknown', 'is_heritage' => false,
            'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw("ST_Transform(ST_GeomFromText('".$this->wkt([[1500, -5], [2400, -5], [2430, -15], [2830, -15]])."', 2154), 4326)"),
        ]);

        $this->build();

        $this->assertLessThan(1, $this->drawn($short));
        $this->assertGreaterThan(350, $this->drawn(LineSegment::findOrFail($long)));
    }

    // ─── Règle 2 : suite masquée sous une chaîne (BuildSharedTrack::continuity) ───

    /** Portion en service d'un trafic donné (les autres aides la créent en trafic mixte). */
    private function serviceSegment(Line $line, array $pts, string $traffic): LineSegment
    {
        $s = $this->segment($line, $pts, 'in_service');
        DB::table('line_segments')->where('id', $s->id)->update(['traffic_type' => $traffic]);

        return $s->refresh();
    }

    public function test_end_masked_under_a_masked_junction_curve_is_drawn_up_to_the_line_that_draws_the_track(): void
    {
        // La Voulte (800000, 800316, 913000) : A fret ; R raccordement mixte dont la courbe finit sur A ; C ligne mixte
        // qui arrive sur la voie de A 27 m plus loin. A est masquée sous R, R tout entière sous C (reste de 27 m, moins
        // que MIN_REMAINDER_M). Avant : rien ne dessinait les 27 m de A, et son bout restait à 27 m de C.
        $a = Line::factory()->create(['slug' => 'a', 'name' => 'Ligne A', 'network' => 'principal']);
        $r = Line::factory()->create(['slug' => 'r', 'name' => 'Raccordement R', 'network' => 'principal']);
        $c = Line::factory()->create(['slug' => 'c', 'name' => 'Ligne C', 'network' => 'principal']);
        $as = $this->serviceSegment($a, [[0, 0], [2000, 0]], 'freight');
        $rs = $this->serviceSegment($r, [[1088, 0], [1115, 3], [1250, 3]], 'mixed');
        $this->serviceSegment($c, [[1115, 0], [1250, 0], [1250, 500]], 'mixed');

        $this->build();

        $this->assertLessThan(1, $this->drawn($rs));
        // Plus de bout de A à côté de la jonction : il est SUR le dessin de C, à 0 m, sans retour en arrière.
        $end = $this->endNear($a, 1110, 0, 30);
        $this->assertLessThan(0.01, $this->distanceToDrawn($c, (float) $end->x, (float) $end->y));
        $this->assertGreaterThan(1112, (float) $end->x);
        $this->assertLessThan(1125, (float) $end->x);
        // Les 27 m que rien ne dessinait le sont par A (avant : 1 088 + 740 m dessinés), le tronc commun reste masqué sous C.
        $this->assertGreaterThan(1850, $this->drawn($as));
        $this->assertLessThan(1880, $this->drawn($as));
    }

    public function test_end_masked_under_a_line_that_nothing_draws_keeps_its_whole_continuation_drawn(): void
    {
        // Rochy-Condé - Soissons (317000) en 1980 : A1 déposée, masquée sous la portion fermée de B, elle-même masquée
        // sous la portion en service A2 de la même ligne A, à 8 m. Aucune autre ligne ne dessine la voie de A1.
        [$a, $b] = $this->lines();
        $a1 = $this->segment($a, [[0, 0], [1000, 0]], 'dismantled');
        $this->segment($a, [[300, 8], [800, 8]], 'in_service');
        $bs = $this->segment($b, [[400, 1], [700, 1]], 'abandoned');

        $this->build();

        $this->assertLessThan(1, $this->drawn($bs));
        // Avant : 300 m de A1 masqués sous B, que rien ne dessinait (deux bouts à 1 m de B, sans ligne dessinée).
        $this->assertEqualsWithDelta(1000, $this->drawn($a1), 0.5);
        $this->assertSame([], array_values(array_filter($this->drawnFreeEnds($a), fn ($e) => (float) $e->x > 350 && (float) $e->x < 750 && abs((float) $e->y) < 2)));
        // Second passage : même résultat (idempotent).
        $this->build();
        $this->assertEqualsWithDelta(1000, $this->drawn($a1), 0.5);
    }

    // ─── Règle 3 : contact à 0 m (BuildSharedTrack::contact) ───────────────

    /** Bout libre dessiné de la ligne près de (x, y), à moins de $r m. */
    private function endNear(Line $line, float $x, float $y, float $r = 20): object
    {
        $ends = array_values(array_filter($this->drawnFreeEnds($line), fn ($e) => hypot((float) $e->x - $x, (float) $e->y - $y) <= $r));
        $this->assertCount(1, $ends, 'un seul bout libre attendu près de ('.$x.', '.$y.')');

        return $ends[0];
    }

    /** Distance (m) du point au DESSIN de la ligne (parties non masquées, telles que les tuiles les sortent). */
    private function distanceToDrawn(Line $line, float $x, float $y): float
    {
        return (float) DB::selectOne('SELECT MIN(ST_Distance(ST_Transform(part.g, 2154), ST_SetSRID(ST_MakePoint(?, ?), 2154))) AS d FROM line_segments ls '
            .TrackPriority::partsJoinSql('ls').' WHERE ls.line_id = ? AND NOT part.covered', [self::X0 + $x, self::Y0 + $y, $line->id])->d;
    }

    private function drawnOfLine(Line $line): float
    {
        return array_sum(array_map(fn ($s) => $this->drawn($s), LineSegment::where('line_id', $line->id)->get()->all()));
    }

    public function test_masked_end_one_metre_beside_the_covering_line_now_ends_on_it_with_a_shallow_arrival(): void
    {
        [$a, $b] = $this->lines();
        $this->segment($a, [[0, 0], [3000, 0]], 'in_service');
        // B arrive en biais du nord-ouest puis longe A à 1 m (masquée) : avant, le bout dessiné restait à ~1 m de A.
        $this->segment($b, [[700, 300], [1000, 1], [3000, 1]], 'abandoned');

        $this->build();

        $end = $this->endNear($b, 1000, 0, 30);
        $this->assertLessThan(0.01, $this->distanceToLine($a, (float) $end->x, (float) $end->y));
        // Raccord vers l'avant (le long de la partie masquée), pas de crochet en arrière.
        $this->assertGreaterThan(995, (float) $end->x);
        $this->assertLessThan(1012, (float) $end->x);
    }

    public function test_branch_stopping_short_of_the_line_it_heads_for_is_extended_straight_onto_it(): void
    {
        [$a, $b] = $this->lines();
        $this->segment($a, [[0, 0], [3000, 0]], 'in_service');
        // Embranchement en service, perpendiculaire, qui s'arrête 8 m avant la voie de A (pas de tronc commun).
        $bs = $this->segment($b, [[500, 300], [500, 8]], 'in_service');

        $this->build();

        $end = $this->endNear($b, 500, 4);
        $this->assertLessThan(0.01, $this->distanceToLine($a, (float) $end->x, (float) $end->y));
        $this->assertEqualsWithDelta(500, (float) $end->x, 0.01);
        // Les tuiles dessinent la portion prolongée (entrée sans partie masquée) : 292 + 8 m.
        $this->assertEqualsWithDelta(300, $this->drawn($bs), 0.05);
        $this->assertSame(0.0, array_sum(array_map(fn ($p) => $p->covered ? (float) $p->m : 0.0, $this->parts($bs))));
    }

    public function test_terminus_beside_another_line_is_not_a_junction_and_stays_as_drawn(): void
    {
        [$a, $b] = $this->lines();
        $this->segment($a, [[0, 0], [3000, 0]], 'in_service');
        // Terminus : B descend vers A puis finit parallèle à 6 m sur 40 m (moins que MIN_SHARED_M : pas masqué).
        $bs = $this->segment($b, [[300, 200], [300, 40], [340, 6], [380, 6]], 'in_service');

        $this->build();

        $end = $this->endNear($b, 380, 6, 1);
        $this->assertEqualsWithDelta(380, (float) $end->x, 0.01);
        $this->assertEqualsWithDelta(6, (float) $end->y, 0.01);
        $this->assertSame(0, DB::table('line_segment_covers')->where('segment_id', $bs->id)->count());
    }

    public function test_end_overshooting_the_line_it_joins_is_cut_at_the_crossing(): void
    {
        [$a, $b] = $this->lines();
        $this->segment($a, [[0, 0], [3000, 0]], 'in_service');
        // B arrive perpendiculairement, coupe A et la dépasse de 1 m.
        $bs = $this->segment($b, [[500, 300], [500, -1]], 'in_service');

        $this->build();

        $end = $this->endNear($b, 500, 0, 5);
        $this->assertLessThan(0.01, abs((float) $end->y));
        $this->assertEqualsWithDelta(300, $this->drawn($bs), 0.05);
    }

    public function test_two_lines_handing_over_are_joined_once_end_to_end(): void
    {
        [$a, $b] = $this->lines();
        // A s'arrête en x = 1000 ; B repart 0,8 m à côté, 0,5 m plus loin : un relais, pas deux raccords parallèles.
        $this->segment($a, [[0, 0], [1000, 0]], 'in_service');
        $this->segment($b, [[1000.5, 0.8], [2000, 0.8]], 'in_service');

        $this->build();

        $ea = $this->endNear($a, 1000, 0, 3);
        $eb = $this->endNear($b, 1000.5, 0.8, 3);
        // Chaque bout est SUR le dessin de l'autre ligne.
        $this->assertLessThan(0.01, $this->distanceToDrawn($b, (float) $ea->x, (float) $ea->y));
        $this->assertLessThan(0.01, $this->distanceToDrawn($a, (float) $eb->x, (float) $eb->y));
        // Un seul raccord d'environ 0,94 m au total.
        $this->assertEqualsWithDelta(1000 + 999.5 + 0.94, $this->drawnOfLine($a) + $this->drawnOfLine($b), 0.05);
    }

    public function test_one_metre_hole_between_two_portions_of_the_same_line_is_closed(): void
    {
        [, $b] = $this->lines();
        // Deux portions de B d'états différents séparées d'un trou de 1 m (au-delà des 0,5 m du raccord).
        $this->segment($b, [[0, 0], [500, 0]], 'in_service');
        $this->segment($b, [[501, 0], [1000, 0]], 'abandoned');

        $this->build();

        $ends = array_map(fn ($e) => round((float) $e->x), $this->drawnFreeEnds($b));
        sort($ends);
        $this->assertSame([0.0, 1000.0], $ends);
        $this->assertEqualsWithDelta(1000, $this->drawnOfLine($b), 0.05);
    }

    // ─── Règle 4 : lissage des arrivées (BuildSharedTrack::smooth, App\Support\JunctionCurve) ───

    /**
     * Dessin de la ligne fusionné, orienté du bout près de (x, y) vers l'amont (coordonnées relatives, m).
     *
     * @return list<array{0: float, 1: float}>
     */
    private function drawnApproach(Line $line, float $x, float $y): array
    {
        $wkt = DB::selectOne(<<<'SQL'
            WITH m AS (
                SELECT (ST_Dump(ST_LineMerge(ST_Collect(ST_Transform(part.g, 2154))))).geom AS g FROM line_segments ls
            SQL.' '.TrackPriority::partsJoinSql('ls').<<<'SQL'
                WHERE ls.line_id = ? AND NOT part.covered AND NOT ST_IsEmpty(part.g)
            ), p AS (SELECT ST_SetSRID(ST_MakePoint(?, ?), 2154) AS p)
            SELECT ST_AsText(CASE WHEN ST_Distance(ST_StartPoint(m.g), p.p) <= ST_Distance(ST_EndPoint(m.g), p.p) THEN m.g ELSE ST_Reverse(m.g) END) AS w
            FROM m, p ORDER BY LEAST(ST_Distance(ST_StartPoint(m.g), p.p), ST_Distance(ST_EndPoint(m.g), p.p)) LIMIT 1
            SQL, [$line->id, self::X0 + $x, self::Y0 + $y])->w;
        preg_match('/^LINESTRING\((.*)\)$/', $wkt, $m);

        return array_map(function ($t) {
            [$px, $py] = array_map('floatval', explode(' ', trim($t)));

            return [$px - self::X0, $py - self::Y0];
        }, explode(',', $m[1]));
    }

    /**
     * Arrivée lisse : bout exactement sur A (y = 0), aucun coude de plus de 35° (cordes ±10 m) sur les 120 derniers m,
     * angle d'arrivée d'aiguillage (au plus 15°).
     *
     * @param  list<array{0: float, 1: float}>  $pts
     */
    private function assertSmoothArrival(Line $a, array $pts): void
    {
        $this->assertLessThan(0.01, $this->distanceToDrawn($a, $pts[0][0], $pts[0][1]), 'bout sur le dessin de A (0 m)');
        $c = JunctionCurve::cumul($pts);
        for ($s = 10.0; $s <= min(120.0, $c[count($c) - 1] - 10); $s += 2.0) {
            $this->assertLessThan(35.0, JunctionCurve::turn(JunctionCurve::at($pts, $c, $s + 10), JunctionCurve::at($pts, $c, $s), JunctionCurve::at($pts, $c, $s - 10)), "coude à {$s} m du bout");
        }
        $this->assertLessThanOrEqual(15.5, JunctionCurve::lineAngle([$pts[1][0] - $pts[0][0], $pts[1][1] - $pts[0][1]], [1.0, 0.0]));
    }

    public function test_bevelled_arrival_beside_the_covering_line_is_replaced_by_a_tangent_curve(): void
    {
        [$a, $b] = $this->lines();
        $this->segment($a, [[-2000, 0], [3000, 0]], 'in_service');
        // Vire : B (fermée) arrive en convergeant, longe A à 11-12 m, puis passe sous A (masquée) : avant, raccord
        // droit de 30 m en biseau (coude au départ, arrivée à ~20°).
        $this->segment($b, [[2500, 260], [1200, 14], [1000, 11.5], [940, 4], [0, 4]], 'abandoned');

        $this->build();

        // Arrivée tangente (6,3°) depuis 11 m d'écart : point de jonction glissé vers l'ouest, dans le sens de B.
        $end = $this->endNear($b, 900, 0, 150);
        $this->assertLessThan(960, (float) $end->x);
        $pts = $this->drawnApproach($b, (float) $end->x, (float) $end->y);
        $this->assertSmoothArrival($a, $pts);
        // Un seul bout libre dessiné de B près de la jonction : pas d'ancien raccord qui reste à côté de la courbe.
        $this->assertCount(1, array_filter($this->drawnFreeEnds($b), fn ($e) => (float) $e->x > 700 && (float) $e->x < 1300));
    }

    public function test_right_angle_hook_follows_the_branch_heading_to_a_tangent_junction(): void
    {
        [$a, $b] = $this->lines();
        $this->segment($a, [[-2000, 0], [3000, 0]], 'in_service');
        // Le Mans - Mézidon : B vient de l'ouest, converge à 14° vers A, puis crochet perpendiculaire de 12 m.
        $this->segment($b, [[-400, 12 + 300 * tan(deg2rad(14))], [-100, 12], [-100, 0]], 'in_service');

        $this->build();

        $end = $this->endNear($b, 0, 0, 200);
        $pts = $this->drawnApproach($b, (float) $end->x, (float) $end->y);
        $this->assertSmoothArrival($a, $pts);
        // Jonction dans le sens de la branche (vers l'est), plus au pied du crochet.
        $this->assertGreaterThan(-40, (float) $end->x);
    }

    public function test_hook_is_kept_when_the_smooth_curve_would_cross_another_track(): void
    {
        [$a, $b] = $this->lines();
        $c = Line::factory()->create(['slug' => 'c', 'name' => 'Ligne C', 'network' => 'principal']);
        $this->segment($a, [[-2000, 0], [3000, 0]], 'in_service');
        $this->segment($b, [[-400, 12 + 300 * tan(deg2rad(14))], [-100, 12], [-100, 0]], 'in_service');
        // Ligne C qui coupe A juste après le crochet : la courbe la croiserait.
        $this->segment($c, [[-60, -300], [-60, 300]], 'in_service');

        $this->build();

        $end = $this->endNear($b, -100, 0, 5);
        $this->assertEqualsWithDelta(-100, (float) $end->x, 0.01);
        $this->assertLessThan(0.01, $this->distanceToDrawn($a, (float) $end->x, (float) $end->y));
    }

    public function test_hook_is_kept_when_a_third_line_ends_at_the_same_junction(): void
    {
        [$a, $b] = $this->lines();
        $c = Line::factory()->create(['slug' => 'c', 'name' => 'Ligne C', 'network' => 'principal']);
        $this->segment($a, [[-2000, 0], [3000, 0]], 'in_service');
        $this->segment($b, [[-400, 12 + 300 * tan(deg2rad(14))], [-100, 12], [-100, 0]], 'in_service');
        // C finit au même point que B, sur A (jonction à trois) : lisser B la laisserait à côté de la courbe.
        $this->segment($c, [[-100, -300], [-100, 0]], 'in_service');

        $this->build();

        $end = $this->endNear($b, -100, 0, 5);
        $this->assertEqualsWithDelta(-100, (float) $end->x, 0.01);
        $endC = $this->endNear($c, -100, 0, 5);
        $this->assertLessThan(0.01, $this->distanceToDrawn($b, (float) $endC->x, (float) $endC->y));
    }

    public function test_precise_branch_arriving_on_the_line_is_left_untouched(): void
    {
        [$a, $b] = $this->lines();
        $this->segment($a, [[-2000, 0], [3000, 0]], 'in_service');
        // Voie précise (BD TOPO) qui quitte A à 20° avec un coude modéré (30°) : géométrie réelle, pas de lissage.
        $bs = $this->segment($b, [[0, 0], [100 * cos(deg2rad(20)), 100 * sin(deg2rad(20))], [100 * cos(deg2rad(20)) + 300 * cos(deg2rad(50)), 100 * sin(deg2rad(20)) + 300 * sin(deg2rad(50))]], 'in_service');
        DB::table('line_segments')->where('id', $bs->id)->update(['source' => 'bdtopo']);

        $this->build();

        $this->assertNull(DB::table('line_segment_covers')->where('segment_id', $bs->id)->first());
        $this->assertEqualsWithDelta(400, $this->drawn($bs), 0.05);
    }
}
