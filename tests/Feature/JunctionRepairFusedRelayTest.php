<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Services\JunctionRepair;
use App\Services\TraceConflation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Règle 0 de JunctionRepair : bout d'un morceau fusionné (source « fusion ») arrêté à 1 ou 2 m du bout de la voie
 * réelle qui le prolonge, recollé sur lui par une déformation douce, la voie ne bougeant pas.
 *
 * Extrait réel (tests/Fixtures/FusedRelay/caen-931.json, lu dans la copie du pilote Calvados le 23/11/2026, état
 * juste avant la continuité) : carré de 600 m à l'ouest de Caen, où Caen - Vire (931) et Caen - Cerisy (533) arrivent
 * sur Paris - Cherbourg (702) et Caen - Courseulles (3181). Le morceau fusionné de 931 finit à 1,53 m du bout de sa
 * portion BD TOPO, posée sur le dessin de 3181 et de 702 ; sans la règle, la règle 5 y ajoutait un raccord en
 * équerre, puis le nettoyage tirait le bout de la voie sur le morceau fusionné : 931 finissait à 1,03 m de 702 et
 * 3181 (essai V2 du pilote, fusion-calvados.md). Sources et licences : tests/Fixtures/SOURCES.md.
 */
class JunctionRepairFusedRelayTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, int> identifiants de l'extrait => identifiants créés */
    private array $ids = [];

    private Line $line;

    /** @var array{0: float, 1: float} */
    private array $end;

    private array $log = [];

    private function insertGeom(string $table, array $row, string $wkt): void
    {
        DB::table($table)->insert($row + ['geom' => DB::raw("ST_Multi(ST_Transform(ST_GeomFromText('".$wkt."', 2154), 4326))")]);
    }

    private function extract(): void
    {
        $d = json_decode((string) file_get_contents(base_path('tests/Fixtures/FusedRelay/caen-931.json')), true);
        foreach ($d['lines'] as $l) {
            $this->ids[$l['id']] = Line::factory()->create(['code' => $l['code'] ?? 'x'.$l['id'], 'gauge_mm_default' => $l['gauge'], 'network' => $l['network']])->id;
        }
        $this->line = Line::findOrFail($this->ids[$d['line']]);
        foreach ($d['parts'] as $p) {
            $this->insertGeom('line_reference_parts', ['line_id' => $this->ids[$p['line']], 'source' => $p['source'], 'source_trace_ids' => '[]',
                'matched_source' => $p['matched'], 'length_m' => 0, 'created_at' => now(), 'updated_at' => now()], $p['wkt']);
        }
        foreach ($d['traces'] as $t) {
            $this->insertGeom('source_traces', ['source' => $t['source'], 'source_ref' => $t['source'].'-'.uniqid('', true), 'layer' => $t['layer'],
                'line_id' => $t['line'] !== null ? ($this->ids[$t['line']] ?? null) : null, 'properties' => $t['props'] ? json_encode($t['props']) : null,
                'match_score' => $t['score'], 'seen_at' => now(), 'created_at' => now(), 'updated_at' => now()], $t['wkt']);
        }
        $this->end = $d['end'];
    }

    private function repair(): array
    {
        (new TraceConflation)->preparePool();
        $r = new JunctionRepair;
        $r->debug = true;
        $out = $r->repair($this->line->id);
        $this->log = $r->log;

        return $out;
    }

    /** Bouts des morceaux de la ligne $lineId de matched_source $matched à moins de 5 m de $this->end (Lambert-93). */
    private function endsNear(int $lineId, ?string $matched): array
    {
        return array_map(fn ($r) => [(float) $r->x, (float) $r->y], DB::select(<<<'SQL'
            SELECT ST_X(q.p) AS x, ST_Y(q.p) AS y
            FROM line_reference_parts p, LATERAL ST_Dump(ST_Transform(p.geom, 2154)) d,
                 LATERAL (VALUES (ST_StartPoint(d.geom)), (ST_EndPoint(d.geom))) q(p)
            WHERE p.line_id = ? AND p.matched_source IS NOT DISTINCT FROM ? AND ST_DWithin(q.p, ST_SetSRID(ST_MakePoint(?, ?), 2154), 5)
            SQL, [$lineId, $matched, $this->end[0], $this->end[1]]));
    }

    private function distanceToLine(int $extractId, array $p): float
    {
        return (float) DB::selectOne('SELECT MIN(ST_Distance(ST_Transform(geom, 2154), ST_SetSRID(ST_MakePoint(?, ?), 2154))) AS d FROM line_reference_parts WHERE line_id = ?',
            [$p[0], $p[1], $this->ids[$extractId]])->d;
    }

    public function test_caen_fused_end_is_glued_on_the_bd_topo_track_that_carries_702_and_3181(): void
    {
        $this->extract();
        $bdtopo = $this->endsNear($this->line->id, 'bdtopo');
        $this->assertCount(1, $bdtopo);
        $this->assertGreaterThan(1.5, hypot($bdtopo[0][0] - $this->end[0], $bdtopo[0][1] - $this->end[1]), 'extrait : relais ouvert de 1,53 m');

        $stats = $this->repair();

        $this->assertSame(1, $stats['fused_relay']);
        $fused = $this->endsNear($this->line->id, 'fusion');
        $this->assertCount(1, $fused);
        // Le bout de la voie n'a pas bougé : il est toujours sur le dessin de 3181 et de 702 ; le morceau fusionné y finit.
        $this->assertSame($bdtopo, $this->endsNear($this->line->id, 'bdtopo'));
        $this->assertEqualsWithDelta(0.0, hypot($fused[0][0] - $bdtopo[0][0], $fused[0][1] - $bdtopo[0][1]), 1e-6);
        $this->assertLessThan(0.01, $this->distanceToLine(3181, $fused[0]));
        $this->assertLessThan(0.01, $this->distanceToLine(702, $fused[0]));
        // Pas de raccord en équerre ajouté au bout (règle 5).
        $this->assertSame(0, $stats['extended']);
        $this->assertSame(0, (int) DB::selectOne('SELECT COUNT(*) AS n FROM line_reference_parts WHERE line_id = ? AND ST_Length(ST_Transform(geom, 2154)) < 5', [$this->line->id])->n);
    }

    public function test_relay_is_left_alone_when_another_line_ends_on_the_part_that_would_move(): void
    {
        $this->extract();
        // Une autre ligne qui finit sur le morceau fusionné à 12 m du bout (dans la partie déformée).
        $other = Line::factory()->create(['code' => 'x-embranchement', 'gauge_mm_default' => 1435, 'network' => 'principal']);
        $p = DB::selectOne(<<<'SQL'
            SELECT ST_X(q) AS x, ST_Y(q) AS y FROM (
                SELECT ST_LineInterpolatePoint(g, 1 - 12 / ST_Length(g)) AS q
                FROM (SELECT (ST_Dump(ST_Transform(geom, 2154))).geom AS g FROM line_reference_parts WHERE line_id = ? AND matched_source = 'fusion') d
                ORDER BY ST_Distance(ST_EndPoint(g), ST_SetSRID(ST_MakePoint(?, ?), 2154)) LIMIT 1) z
            SQL, [$this->line->id, $this->end[0], $this->end[1]]);
        $this->insertGeom('line_reference_parts', ['line_id' => $other->id, 'source' => 'osm', 'source_trace_ids' => '[]', 'matched_source' => null,
            'length_m' => 0, 'created_at' => now(), 'updated_at' => now()], 'LINESTRING('.($p->x - 80).' '.($p->y - 60).','.$p->x.' '.$p->y.')');

        $stats = $this->repair();

        $this->assertSame(0, $stats['fused_relay']);
        $this->assertContains('autre ligne sur la partie déformée', array_column(array_filter($this->log, fn ($e) => isset($e['fused_relay'])), 'skip'));
        $this->assertEqualsWithDelta(0.0, $this->distanceToLine(931, [(float) $p->x, (float) $p->y]), 0.01);
    }

    /** Réseau fabriqué autour de (650 000, 6 860 000) : morceau fusionné de la ligne d'ouest en est jusqu'à x = 0. */
    private function fabricated(string $nextMatched, array $nextStart): void
    {
        [$x0, $y0] = [650000, 6860000];
        $this->line = Line::factory()->create(['code' => 'x-relais', 'gauge_mm_default' => 1435, 'network' => 'principal']);
        $this->end = [$x0, $y0];
        $this->insertGeom('line_reference_parts', ['line_id' => $this->line->id, 'source' => 'ohm', 'source_trace_ids' => '[]', 'matched_source' => 'fusion',
            'length_m' => 0, 'created_at' => now(), 'updated_at' => now()], 'LINESTRING('.($x0 - 200).' '.$y0.','.($x0 - 100).' '.$y0.','.$x0.' '.$y0.')');
        [$bx, $by] = [$x0 + $nextStart[0], $y0 + $nextStart[1]];
        $this->insertGeom('line_reference_parts', ['line_id' => $this->line->id, 'source' => 'ohm', 'source_trace_ids' => '[]', 'matched_source' => $nextMatched,
            'length_m' => 0, 'created_at' => now(), 'updated_at' => now()], 'LINESTRING('.$bx.' '.$by.','.($bx + 100).' '.$by.','.($bx + 200).' '.$by.')');
    }

    public function test_another_line_ending_at_the_track_end_does_not_block_the_relay(): void
    {
        // Relais de 0,81 m : le bout de la voie est à moins de 1 m de la fin du morceau fusionné ; une autre ligne y finit.
        $this->fabricated('osm', [0.4, 0.7]);
        $other = Line::factory()->create(['code' => 'x-autre', 'gauge_mm_default' => 1435, 'network' => 'principal']);
        $this->insertGeom('line_reference_parts', ['line_id' => $other->id, 'source' => 'osm', 'source_trace_ids' => '[]', 'matched_source' => null,
            'length_m' => 0, 'created_at' => now(), 'updated_at' => now()], 'LINESTRING(650000.4 6859900.7,650000.4 6860000.7)');

        $stats = $this->repair();

        $this->assertSame(1, $stats['fused_relay']);
        $this->assertEqualsWithDelta(0.0, hypot($this->endsNear($this->line->id, 'fusion')[0][0] - 650000.4, $this->endsNear($this->line->id, 'fusion')[0][1] - 6860000.7), 1e-6);
    }

    public function test_a_shared_relay_of_two_lines_is_glued_whichever_line_is_rebuilt_first(): void
    {
        // Comme 931 et 533 à Caen : l'autre ligne finit au même point sur son propre morceau fusionné (même relais).
        $this->fabricated('osm', [0.4, 0.7]);
        $other = Line::factory()->create(['code' => 'x-autre', 'gauge_mm_default' => 1435, 'network' => 'principal']);
        $this->insertGeom('line_reference_parts', ['line_id' => $other->id, 'source' => 'ohm', 'source_trace_ids' => '[]', 'matched_source' => 'fusion',
            'length_m' => 0, 'created_at' => now(), 'updated_at' => now()], 'LINESTRING(649900 6859950,650000 6860000)');

        $stats = $this->repair();

        $this->assertSame(1, $stats['fused_relay']);
    }

    public function test_two_fused_pieces_are_not_glued_to_each_other(): void
    {
        $this->fabricated('fusion', [0.4, 0.7]);

        $stats = $this->repair();

        $this->assertSame(0, $stats['fused_relay']);
    }

    public function test_ease_end_onto_lands_exactly_and_bends_gently(): void
    {
        $c = [[0.0, 0.0], [100.0, 0.0], [200.0, 0.0]];
        $q = [200.0, 1.5];
        $out = (new JunctionRepair)->easeEndOnto($c, $q);

        $this->assertSame($q, end($out['coords']));
        $this->assertEqualsWithDelta(45.0, $out['s'], 1e-9);
        // Avant la partie déformée, rien ne bouge.
        foreach ($out['coords'] as $p) {
            if ($p[0] <= 155.0 - 1e-9) {
                $this->assertEqualsWithDelta(0.0, $p[1], 1e-9);
            }
        }
        // Pente au plus pi/2 * 1,5 / 45 (3°) ; sommets tous les 5 m environ sur la partie déformée.
        $maxSlope = 0.0;
        for ($i = 1; $i < count($out['coords']); $i++) {
            [$a, $b] = [$out['coords'][$i - 1], $out['coords'][$i]];
            $maxSlope = max($maxSlope, abs(atan2($b[1] - $a[1], $b[0] - $a[0])));
            if ($a[0] >= 155.0) {
                $this->assertLessThanOrEqual(5.01, hypot($b[0] - $a[0], $b[1] - $a[1]));
            }
        }
        $this->assertLessThan(deg2rad(3.1), $maxSlope);
        $this->assertGreaterThan(deg2rad(2.5), $maxSlope);
    }
}
