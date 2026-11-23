<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Models\Station;
use App\Services\JunctionRepair;
use App\Services\TraceConflation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Règle 11 de JunctionRepair : bout arrêté à côté de la voie traversante d'une autre ligne, dans une gare desservie par
 * les deux (ce que compte l'invariant lines.junction_gap_lateral), raccordé au dessin de cette ligne par les voies
 * précises réelles, sinon par une courbe estimée ; refusé pour les fausses jonctions.
 *
 * Extraits réels (tests/Fixtures/Lateral/*.json, lus en lecture seule sur vff le 23/11/2026) : carré de 700 m
 * (Sélestat : 1,2 km) autour du bout, tracés de référence de toutes les lignes, voies sources (Lambert-93, au
 * centimètre ; sans les traces archeoferroviaire ni les morceaux de référence qui en gardent la géométrie brute), gares
 * des deux lignes, tronçons 1930 et 2030 des deux lignes. Sources et licences : tests/Fixtures/SOURCES.md.
 * Réseaux fabriqués en Lambert-93 autour de (650 000, 6 860 000) pour les cas limites : voie traversante L d'ouest en
 * est sur y = 0, branche B parallèle à 10 m au nord qui s'arrête en x = 0, cap à l'est.
 */
class JunctionRepairLateralTest extends TestCase
{
    use RefreshDatabase;

    private const X0 = 650000;

    private const Y0 = 6860000;

    private Line $line;

    private Line $other;

    /** @var array{0: float, 1: float} */
    private array $end;

    /** @var array<int, int> */
    private array $eras = [];

    /** Décisions de la règle 11 au dernier passage (journal de diagnostic). */
    private array $decisions = [];

    private function wkt(array $pts): string
    {
        return 'LINESTRING('.implode(',', array_map(fn ($p) => $p[0].' '.$p[1], $pts)).')';
    }

    private function part(int $lineId, string $source, string $wkt, ?string $matched = null): void
    {
        DB::table('line_reference_parts')->insert([
            'line_id' => $lineId, 'source' => $source, 'source_trace_ids' => '[]', 'matched_source' => $matched,
            'length_m' => 0, 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw("ST_Multi(ST_Transform(ST_GeomFromText('".$wkt."', 2154), 4326))"),
        ]);
    }

    private function trace(?int $lineId, string $source, ?string $layer, string $wkt, array $props = [], ?float $score = null): void
    {
        DB::table('source_traces')->insert([
            'source' => $source, 'source_ref' => $source.'-'.uniqid('', true), 'layer' => $layer, 'line_id' => $lineId,
            'properties' => $props ? json_encode($props) : null, 'match_score' => $score,
            'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw("ST_Multi(ST_Transform(ST_GeomFromText('".$wkt."', 2154), 4326))"),
        ]);
    }

    private function station(array $lineIds, string $name, array $xy): void
    {
        $id = Station::factory()->create(['name' => $name])->id;
        DB::update('UPDATE stations SET geom = ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 2154), 4326) WHERE id = ?', [$xy[0], $xy[1], $id]);
        foreach ($lineIds as $l) {
            DB::table('station_lines')->insert(['station_id' => $id, 'line_id' => $l, 'method' => 'wikidata_p81', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function segment(int $lineId, int $year, string $status, string $wkt): void
    {
        $this->eras[$year] ??= DB::table('eras')->insertGetId(['year' => $year, 'label' => (string) $year, 'created_at' => now(), 'updated_at' => now()]);
        DB::insert('INSERT INTO line_segments (line_id, era_id, geom, status, created_at, updated_at) VALUES (?, ?, ST_Transform(ST_GeomFromText(?, 2154), 4326), ?, NOW(), NOW())',
            [$lineId, $this->eras[$year], $wkt, $status]);
    }

    /** Charge un extrait réel ; la ligne du bout devient $this->line, la voie traversante $this->other. */
    private function extract(string $name): void
    {
        $d = json_decode((string) file_get_contents(base_path("tests/Fixtures/Lateral/{$name}.json")), true);
        $ids = [];
        foreach ($d['lines'] as $l) {
            $ids[$l['id']] = Line::factory()->create(['code' => $l['code'] ?? 'x'.$l['id'], 'gauge_mm_default' => $l['gauge'], 'network' => $l['network']])->id;
        }
        $this->line = Line::findOrFail($ids[$d['line']]);
        $this->other = Line::findOrFail($ids[$d['oline']]);
        foreach ($d['parts'] as $p) {
            $this->part($ids[$p['line']], $p['source'], $p['wkt'], $p['matched']);
        }
        foreach ($d['traces'] ?? [] as $t) {
            $this->trace($t['line'] !== null && isset($ids[$t['line']]) ? $ids[$t['line']] : null, $t['source'], $t['layer'], $t['wkt'], $t['props'] ?: [], $t['score']);
        }
        foreach ($d['stations'] ?? [] as $s) {
            $this->station(array_map(fn ($l) => $ids[$l], $s['lines']), $s['name'], $s['xy']);
        }
        foreach ($d['segs'] ?? [] as $g) {
            if (str_starts_with($g['wkt'], 'LINESTRING')) {
                $this->segment($ids[$g['line']], $g['year'], $g['status'], $g['wkt']);
            }
        }
        $this->end = $d['end'];
    }

    private function repair(bool $lateral = true): array
    {
        (new TraceConflation)->preparePool();
        $r = new JunctionRepair;
        $r->joinLateral = $lateral;
        $r->debug = true;
        $out = $r->repair($this->line->id);
        $this->decisions = array_values(array_filter($r->log, fn ($e) => isset($e['lateral'])
            && hypot($e['at'][0] - $this->end[0], $e['at'][1] - $this->end[1]) < 3));
        if (getenv('JR_DEBUG')) {
            fwrite(STDERR, json_encode($r->log)."\n");
        }

        return $out;
    }

    /** Raisons des refus de la règle 11 pour le bout étudié. */
    private function refusals(): array
    {
        return array_values(array_filter(array_column($this->decisions, 'skip')));
    }

    /** Distance (m) du dessin de la ligne étudiée, près du bout (300 m), au dessin de la voie traversante. */
    private function gapToOther(): float
    {
        return (float) DB::selectOne(<<<'SQL'
            SELECT ST_Distance(a.g, b.g) AS d FROM
              (SELECT ST_Collect(ST_Intersection(ST_Transform(geom, 2154), ST_Expand(ST_SetSRID(ST_MakePoint(?, ?), 2154), 300))) AS g FROM line_reference_parts WHERE line_id = ?) a,
              (SELECT ST_Collect(ST_Transform(geom, 2154)) AS g FROM line_reference_parts WHERE line_id = ?) b
            SQL, [$this->end[0], $this->end[1], $this->line->id, $this->other->id])->d;
    }

    /** Dernier morceau ajouté à la ligne étudiée : source, provenance, polyligne Lambert-93. */
    private function added(): object
    {
        return DB::selectOne(<<<'SQL'
            SELECT source, matched_source, ST_AsText(ST_Transform(ST_GeometryN(geom, 1), 2154)) AS wkt, ST_Length(ST_Transform(geom, 2154)) AS len
            FROM line_reference_parts WHERE line_id = ? ORDER BY id DESC LIMIT 1
            SQL, [$this->line->id]);
    }

    // ─── Extraits réels ──────────────────────────────────────────────────

    public function test_vierzon_forges_branch_is_joined_along_the_real_track(): void
    {
        // 690000 s'arrête à Vierzon-Forges à 8,4 m de 590000, parallèle : la voie OSM sous le bout continue et rejoint la
        // voie de 590000 par l'aiguillage réel.
        $this->extract('vierzon-forges');
        $this->assertGreaterThan(5, $this->gapToOther());

        $stats = $this->repair();

        $this->assertSame(1, $stats['lateral_routed']);
        $this->assertSame(0, $stats['lateral_smoothed']);
        $this->assertLessThan(0.5, $this->gapToOther());
        $this->assertNotSame(JunctionRepair::SMOOTHED, $this->added()->matched_source);
    }

    public function test_selestat_branch_is_joined_by_an_estimated_tangent_curve(): void
    {
        // 111000 s'arrête à Sélestat à 11,7 m de 115000, parallèle ; aucune voie précise ne mène du bout à 115000 : courbe
        // estimée de rayon plausible, qui se rapproche toujours de 115000 et se pose sur son dessin.
        $this->extract('selestat');

        $stats = $this->repair();

        $this->assertSame(1, $stats['lateral_smoothed']);
        $this->assertLessThan(0.5, $this->gapToOther());
        $added = $this->added();
        $this->assertSame(JunctionRepair::SMOOTHED, $added->matched_source);
        $this->assertLessThan(250, (float) $added->len);
    }

    public function test_underground_rer_end_is_not_joined_to_the_surface_line_above(): void
    {
        // RER A à Paris-Gare-de-Lyon : dessiné sur une voie de surface rattachée par erreur, à 12,8 m de 830000 ; ses voies
        // sont en tunnel (BD TOPO, OSM), celles de 830000 au sol.
        $this->extract('paris-gare-de-lyon-rer-a');
        $before = $this->gapToOther();

        $stats = $this->repair();

        $this->assertSame(0, $stats['lateral_routed'] + $stats['lateral_smoothed']);
        $this->assertContains('niveaux différents (tunnel et surface)', $this->refusals());
        $this->assertEqualsWithDelta($before, $this->gapToOther(), 0.01);
    }

    // ─── Réseaux fabriqués ──────────────────────────────────────────────

    private function at(float $x, float $y): array
    {
        return [self::X0 + $x, self::Y0 + $y];
    }

    /** @param list<array{0: float, 1: float}> $pts */
    private function rel(array $pts): string
    {
        return $this->wkt(array_map(fn ($p) => $this->at($p[0], $p[1]), $pts));
    }

    /**
     * Voie traversante L (dessin BD TOPO de x = -800 à 800 sur y = 0), branche B arrêtée en (0, 10), cap à l'est, gare
     * commune en (0, 5), tronçons 2030 en service des deux lignes ; options : écartement de B, gare, statut de L en 2030.
     */
    private function lateralNetwork(?int $gauge = 1435, bool $station = true, string $otherStatus = 'in_service'): void
    {
        $this->line = Line::factory()->create(['code' => '420100', 'gauge_mm_default' => $gauge, 'network' => 'principal']);
        $this->other = Line::factory()->create(['code' => '420000', 'gauge_mm_default' => 1435, 'network' => 'principal']);
        $l = $this->rel([[-800, 0], [800, 0]]);
        $this->part($this->other->id, 'bdtopo', $l);
        $this->trace($this->other->id, 'bdtopo', 'Voie ferrée principale', $l);
        // Branche venue du nord-ouest, à 300 m de L à son autre bout (une seule extrémité à côté de L).
        $this->part($this->line->id, 'osm', $this->rel([[-700, 300], [-300, 10], [0, 10]]));
        $this->segment($this->line->id, 2030, 'in_service', $this->rel([[-700, 300], [-300, 10], [0, 10]]));
        $this->segment($this->other->id, 2030, $otherStatus, $l);
        if ($station) {
            $this->station([$this->line->id, $this->other->id], 'Gare commune', $this->at(0, 5));
        }
        $this->end = $this->at(0, 10);
    }

    public function test_lateral_end_without_precise_track_is_joined_by_a_curve_ahead_of_it(): void
    {
        $this->lateralNetwork();

        $stats = $this->repair();

        $this->assertSame(1, $stats['lateral_smoothed']);
        $this->assertLessThan(0.5, $this->gapToOther());
        $added = $this->added();
        $this->assertSame(JunctionRepair::SMOOTHED, $added->matched_source);
        $this->assertSame('osm', $added->source);
        // Devant le bout (vers l'est), tangente aux deux voies : rayon d'au moins 190 m, donc au moins 100 m de long pour 10 m d'écart.
        $pts = array_map(fn ($p) => array_map('floatval', explode(' ', $p)), explode(',', substr($added->wkt, 11, -1)));
        $this->assertGreaterThan(100, (float) $added->len);
        $this->assertGreaterThanOrEqual(self::X0 - 100, min(array_column($pts, 0)));
        $this->assertGreaterThan(self::X0 + 20, end($pts)[0]);
        $this->assertEqualsWithDelta(self::Y0, end($pts)[1], 0.5);
    }

    public function test_rule_is_on_by_default_and_can_be_turned_off(): void
    {
        $this->assertTrue((new JunctionRepair)->joinLateral);
        $this->lateralNetwork();

        $stats = $this->repair(false);

        $this->assertSame(0, $stats['lateral_smoothed'] + $stats['lateral_routed']);
        $this->assertEqualsWithDelta(10, $this->gapToOther(), 0.01);
    }

    public function test_lateral_end_on_a_track_that_converges_is_joined_along_it(): void
    {
        // Voie BD TOPO non rattachée sous le bout, qui continue 60 m puis rejoint L par un aiguillage (déviation de 4°).
        $this->lateralNetwork();
        $this->trace(null, 'bdtopo', 'Voie ferrée principale', $this->rel([[-300, 10], [60, 10], [203, 0], [400, 0]]));

        $stats = $this->repair();

        $this->assertSame(1, $stats['lateral_routed']);
        $this->assertLessThan(0.5, $this->gapToOther());
        $added = $this->added();
        $this->assertSame('bdtopo', $added->source);
        $this->assertNull($added->matched_source);
    }

    public function test_route_running_alongside_the_line_for_long_is_not_taken(): void
    {
        // La voie sous le bout longe L à 10 m sur 250 m avant de la rejoindre (autre voie d'un faisceau) : pas suivie.
        $this->lateralNetwork();
        $this->trace(null, 'bdtopo', 'Voie ferrée principale', $this->rel([[-300, 10], [250, 10], [393, 0], [600, 0]]));

        $stats = $this->repair();

        $this->assertSame(0, $stats['lateral_routed']);
        $this->assertSame(0, (int) DB::selectOne("SELECT COUNT(*) AS n FROM line_reference_parts WHERE line_id = ? AND source = 'bdtopo'", [$this->line->id])->n);
    }

    public function test_curve_through_another_track_between_them_is_refused(): void
    {
        // Voie précise (non rattachée) entre le bout et L, à 5 m de L : la courbe la traverserait sans aiguillage.
        $this->lateralNetwork();
        $this->trace(null, 'bdtopo', 'Voie de service', $this->rel([[-800, 5], [800, 5]]));

        $stats = $this->repair();

        $this->assertSame(0, $stats['lateral_smoothed'] + $stats['lateral_routed']);
        $this->assertEqualsWithDelta(10, $this->gapToOther(), 0.01);
    }

    public function test_end_on_a_dead_end_track_is_not_joined_ahead(): void
    {
        // Le bout est posé sur une voie de quai qui s'arrête 5 m plus loin (cul-de-sac) : la jonction n'est pas devant.
        $this->lateralNetwork();
        $this->trace(null, 'bdtopo', 'Voie de service', $this->rel([[-300, 10], [5, 10]]));

        $stats = $this->repair();

        $this->assertSame(0, $stats['lateral_smoothed'] + $stats['lateral_routed']);
        $this->assertContains('voie en impasse', $this->refusals());
    }

    public function test_lines_without_a_shared_station_are_not_joined(): void
    {
        $this->lateralNetwork(station: false);

        $this->assertSame(0, $this->repair()['lateral_smoothed']);
        $this->assertContains('pas de gare commune', $this->refusals());
    }

    public function test_lines_of_different_gauges_are_not_joined(): void
    {
        $this->lateralNetwork(gauge: 1000);

        $this->assertSame(0, $this->repair()['lateral_smoothed']);
        $this->assertContains('écartements incompatibles', $this->refusals());
    }

    public function test_line_never_in_service_at_the_same_era_is_not_joined(): void
    {
        $this->lateralNetwork(otherStatus: 'dismantled');

        $this->assertSame(0, $this->repair()['lateral_smoothed']);
        $this->assertContains('pas en service à une même ère', $this->refusals());
    }

    public function test_underground_branch_beside_a_surface_line_is_not_joined(): void
    {
        // Voies de B en tunnel (BD TOPO, position -1), voie de L au sol.
        $this->lateralNetwork();
        DB::table('source_traces')->update(['properties' => json_encode(['position_par_rapport_au_sol' => '0'])]);
        $this->trace($this->line->id, 'bdtopo', 'Voie ferrée principale', $this->rel([[-400, 10], [-1, 10]]), ['position_par_rapport_au_sol' => '-1']);

        $this->assertSame(0, $this->repair()['lateral_smoothed']);
        $this->assertContains('niveaux différents (tunnel et surface)', $this->refusals());
    }

    public function test_parallel_line_without_a_through_track_is_not_joined(): void
    {
        // L n'a que 40 m de dessin autour du bout (terminus voisin, pas une voie traversante).
        $this->lateralNetwork();
        DB::table('line_reference_parts')->where('line_id', $this->other->id)->delete();
        $this->part($this->other->id, 'bdtopo', $this->rel([[-20, 0], [20, 0]]));

        $this->assertSame(0, $this->repair()['lateral_smoothed']);
        $this->assertContains('voie non traversante', $this->refusals());
    }

    public function test_end_turned_towards_the_line_is_left_to_the_other_rules(): void
    {
        // Bout qui arrive en biais (45°) à 10 m de L : pas presque parallèle (règles 5 et 7 c).
        $this->lateralNetwork();
        DB::table('line_reference_parts')->where('line_id', $this->line->id)->delete();
        $this->part($this->line->id, 'osm', $this->rel([[-100, 110], [0, 10]]));

        $this->repair();

        $this->assertSame(['pas parallèle'], $this->refusals());
    }

    public function test_joined_end_is_left_alone_on_a_second_pass(): void
    {
        $this->lateralNetwork();
        $this->repair();
        $n = DB::table('line_reference_parts')->where('line_id', $this->line->id)->count();

        $stats = $this->repair();

        $this->assertSame(0, $stats['lateral_smoothed'] + $stats['lateral_routed']);
        $this->assertSame($n, DB::table('line_reference_parts')->where('line_id', $this->line->id)->count());
    }
}
