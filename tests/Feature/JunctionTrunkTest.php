<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use App\Services\TraceConflation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tronc commun à une jonction (TraceConflation::junctionTrunk) : un tracé
 * archeo qui longe la voie d'une AUTRE ligne à 12-30 m, sans voie précise
 * propre, et qui converge vers elle (il la rejoint, ou s'arrête à la gare de
 * jonction) prend la voie de cette ligne ; une vraie voie distincte (OSM
 * propre), un croisement ou un long parallélisme sans convergence ne sont pas
 * touchés (le dernier est signalé).
 *
 * Réseau fabriqué en Lambert-93 (autour de x = 650 000, y = 6 860 000) : ligne
 * B = voie BD TOPO rattachée, est-ouest (y = 0) de x = 0 à 6 000 m.
 */
class JunctionTrunkTest extends TestCase
{
    use RefreshDatabase;

    private const X0 = 650000;

    private const Y0 = 6860000;

    private Line $a;

    private Line $b;

    private int $trunkTrace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->b = Line::factory()->create(['code' => '405000', 'name' => 'Ligne d\'Argentan à Granville']);
        $this->a = Line::factory()->create(['code' => '413000', 'name' => 'Ligne de Caen à Vire']);
        $this->trunkTrace = $this->trace($this->b, 'bdtopo', [[0, 0], [6000, 0]]);
    }

    private function wkt(array $pts): string
    {
        return 'LINESTRING('.implode(',', array_map(fn ($p) => (self::X0 + $p[0]).' '.(self::Y0 + $p[1]), $pts)).')';
    }

    private function trace(?Line $line, string $source, array $pts, array $extra = []): int
    {
        $layer = ['bdtopo' => 'Voie ferrée principale', 'osm' => 'rail', 'archeoferroviaire' => 'principal'][$source] ?? $source;

        return DB::table('source_traces')->insertGetId(array_merge([
            'source' => $source, 'source_ref' => $source.'-'.uniqid('', true), 'layer' => $layer,
            'line_id' => $line?->id, 'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw("ST_Multi(ST_Transform(ST_GeomFromText('".$this->wkt($pts)."', 2154), 4326))"),
        ], $extra));
    }

    /** Recale le tracé archeo de la ligne A (même entrée que vff:build-line-references sans géométrie déjà retenue). */
    private function conflate(array $archeo, string $profile = 'archeoferroviaire'): array
    {
        $this->trace($this->a, $profile, $archeo);
        $c = new TraceConflation;
        $c->preparePool();
        $g = DB::selectOne("SELECT encode(ST_AsEWKB(ST_UnaryUnion(ST_Collect(ST_Transform(geom, 2154)))), 'hex') AS ewkb FROM source_traces WHERE line_id = ? AND source = ?", [$this->a->id, $profile]);

        return $c->match($this->a->id, $g->ewkb, $profile);
    }

    private function length(array $res, ?string $family): float
    {
        return array_sum(array_map(fn ($p) => $p['len'], array_filter($res['pieces'], fn ($p) => $p['family'] === $family)));
    }

    /** Distance max (m) entre la voie précise retenue et la voie B (y = 0), sur la portion x ∈ [x0, x1]. */
    private function maxOffsetFromTrunk(array $res, string $family, float $x0, float $x1): float
    {
        $piece = array_values(array_filter($res['pieces'], fn ($p) => $p['family'] === $family))[0];

        return (float) DB::selectOne(<<<'SQL'
            SELECT MAX(abs(ST_Y(d.geom) - :y0)) AS m FROM ST_DumpPoints(ST_GeomFromEWKB(decode(:g, 'hex'))) d
            WHERE ST_X(d.geom) BETWEEN :x0 AND :x1
            SQL, ['g' => $piece['ewkb'], 'y0' => self::Y0, 'x0' => self::X0 + $x0, 'x1' => self::X0 + $x1])->m;
    }

    private function merged(array $res): array
    {
        return array_values(array_filter($res['junctions'], fn ($j) => $j['action'] === 'merged_trunk'));
    }

    // ─── Cas positifs ────────────────────────────────────────────────────

    public function test_branch_ending_at_the_junction_station_alongside_the_trunk_takes_the_trunk_track(): void
    {
        // Façon Vire : la branche arrive du nord-est, puis longe le tronc à 15 m jusqu'à la gare (x = 2 600).
        $res = $this->conflate([[4100, 900], [3800, 15], [2600, 15]]);

        $merged = $this->merged($res);
        $this->assertCount(1, $merged);
        $this->assertEqualsWithDelta(15, $merged[0]['gap'], 1);
        $this->assertSame([$this->b->id], $merged[0]['lines']);
        $this->assertSame('bdtopo', $merged[0]['family']);
        $this->assertContains($this->trunkTrace, $merged[0]['trace_ids']);
        $this->assertEqualsWithDelta(1200, $merged[0]['len'], 100);
        // La portion le long du tronc est dessinée SUR la voie de B (et n'est plus gardée telle quelle).
        $this->assertEqualsWithDelta(1150, $this->length($res, 'bdtopo'), 120);
        $this->assertLessThan(0.5, $this->maxOffsetFromTrunk($res, 'bdtopo', 2600, 3700));
        $this->assertGreaterThan(400, $this->length($res, null), 'l\'approche, loin du tronc, reste le tracé d\'origine');
        $this->assertEqualsWithDelta(1200, $res['stats']['junction_m'], 100);
    }

    public function test_branch_that_joins_the_trunk_and_continues_on_it_takes_the_trunk_track_before_the_junction(): void
    {
        // La branche longe le tronc à 20 m sur 1,5 km, puis roule dessus (2 m) jusqu'au bout.
        $res = $this->conflate([[2000, 900], [2500, 20], [4000, 20], [4100, 2], [6000, 2]]);

        $merged = $this->merged($res);
        $this->assertCount(1, $merged);
        $this->assertEqualsWithDelta(20, $merged[0]['gap'], 1);
        // Le bloc de validation (1 km) à cheval sur la jonction a été refusé en entier : il est repris aussi.
        $this->assertGreaterThan(1400, $merged[0]['len']);
        $this->assertLessThan(2100, $merged[0]['len']);
        $this->assertLessThan(0.5, $this->maxOffsetFromTrunk($res, 'bdtopo', 2600, 5900));
        $this->assertEqualsWithDelta(3500, $this->length($res, 'bdtopo'), 200);
    }

    public function test_station_track_of_the_branch_is_not_a_distinct_line_track(): void
    {
        // Voie de faisceau (BD TOPO « Voie de service », non rattachée) sous le tracé archeo, à 15 m du tronc.
        $service = $this->trace(null, 'bdtopo', [[2400, 15], [3900, 15]], ['layer' => 'Voie de service']);
        $res = $this->conflate([[4100, 900], [3800, 15], [2600, 15]]);

        $merged = $this->merged($res);
        $this->assertCount(1, $merged);
        $this->assertNotContains($service, $merged[0]['trace_ids']);
        $this->assertLessThan(0.5, $this->maxOffsetFromTrunk($res, 'bdtopo', 2600, 3700));
    }

    public function test_ohm_trace_follows_the_same_rule(): void
    {
        $res = $this->conflate([[4100, 900], [3800, 18], [2600, 18]], 'ohm');

        $this->assertCount(1, $this->merged($res));
        $this->assertLessThan(0.5, $this->maxOffsetFromTrunk($res, 'bdtopo', 2600, 3700));
    }

    /** Gare (Lambert-93 relatif). */
    private function station(float $x, float $y): void
    {
        $id = Station::factory()->create()->id;
        DB::update(sprintf('UPDATE stations SET geom = ST_Transform(ST_SetSRID(ST_MakePoint(%F, %F), 2154), 4326) WHERE id = ?', self::X0 + $x, self::Y0 + $y), [$id]);
    }

    public function test_branch_ending_at_the_station_building_beside_the_trunk_is_taken_up_to_the_station(): void
    {
        // La branche longe le tronc à 15 m, puis ses 100 derniers mètres s'écartent vers le bâtiment voyageurs (45 m).
        $this->station(2550, 30);
        $res = $this->conflate([[4100, 900], [3800, 15], [2700, 15], [2600, 45]]);

        $merged = $this->merged($res);
        $this->assertCount(1, $merged);
        $this->assertGreaterThan(1150, $merged[0]['len'], 'la fin de tracé jusqu\'à la gare est reprise');
        $this->assertLessThan(0.5, $this->maxOffsetFromTrunk($res, 'bdtopo', 2620, 3700));
    }

    public function test_same_end_without_a_station_of_the_trunk_is_not_a_junction(): void
    {
        $res = $this->conflate([[4100, 900], [3800, 15], [2700, 15], [2600, 45]]);

        $this->assertSame([], $this->merged($res));
        $this->assertCount(1, array_filter($res['junctions'], fn ($j) => $j['action'] === 'kept_parallel'));
    }

    public function test_station_too_far_from_the_trunk_does_not_count(): void
    {
        // Gare à 95 m du bout mais à 140 m du tronc (STATION_TRUNK_M = 60 m) : gare d'une autre compagnie, pas celle de B.
        $this->station(2600, 140);
        $res = $this->conflate([[4100, 900], [3800, 15], [2700, 15], [2600, 45]]);

        $this->assertSame([], $this->merged($res));
    }

    public function test_trunk_prefers_the_bd_topo_track_of_the_other_line(): void
    {
        // Même voie de B en OSM, 2 m plus près du tracé : la BD TOPO (dessin de B) reste préférée (+3 m pour OSM).
        $osm = $this->trace($this->b, 'osm', [[0, 2], [6000, 2]]);
        $res = $this->conflate([[4100, 900], [3800, 15], [2600, 15]]);

        $merged = $this->merged($res);
        $this->assertCount(1, $merged);
        $this->assertSame('bdtopo', $merged[0]['family']);
        $this->assertNotContains($osm, $merged[0]['trace_ids']);
    }

    public function test_osm_track_much_closer_than_the_bd_topo_one_is_taken(): void
    {
        // Voie OSM de B à 4 m du tracé, BD TOPO à 15 m : l'écart l'emporte sur la priorité (4 + 3 < 15).
        $osm = $this->trace($this->b, 'osm', [[0, 11], [6000, 11]]);
        $res = $this->conflate([[4100, 900], [3800, 15], [2600, 15]]);

        $this->assertSame([], $this->merged($res), 'à 4 m, la voie de B est acceptée par la validation (<= 12 m)');
        $this->assertGreaterThan(1000, $this->length($res, 'osm'));
        $this->assertContains($osm, array_merge(...array_column(array_filter($res['pieces'], fn ($p) => $p['family'] === 'osm'), 'trace_ids')));
    }

    public function test_lines_of_different_known_gauges_never_share_a_track(): void
    {
        // Voie métrique (CFIL) le long d'une voie normale jusqu'à la gare : deux voies distinctes.
        Line::whereKey($this->a->id)->update(['gauge_mm_default' => 1000]);
        Line::whereKey($this->b->id)->update(['gauge_mm_default' => 1435]);
        $res = $this->conflate([[4100, 900], [3800, 15], [2600, 15]]);

        $this->assertSame([], $this->merged($res));
        $this->assertEqualsWithDelta(0, $this->length($res, 'bdtopo'), 1);
        $this->assertSame(['écartement différent'], array_column($res['junctions'], 'why'));
    }

    /** Réseaux des lignes A (branche) et B (tronc, « principal »), écartement de A. */
    private function networks(string $a, ?int $gaugeA = null): void
    {
        Line::whereKey($this->a->id)->update(['network' => $a, 'gauge_mm_default' => $gaugeA]);
        Line::whereKey($this->b->id)->update(['network' => 'principal']);
    }

    public function test_secondary_line_of_unknown_gauge_never_shares_the_main_line_track(): void
    {
        $this->networks('secondaire');
        $res = $this->conflate([[4100, 900], [3800, 15], [2600, 15]]);

        $this->assertSame([], $this->merged($res));
        $kept = array_values(array_filter($res['junctions'], fn ($j) => $j['action'] === 'kept_parallel'));
        $this->assertCount(1, $kept);
        $this->assertSame('réseau différent', $kept[0]['why']);
        $this->assertEqualsWithDelta(0, $this->length($res, 'bdtopo'), 1);
    }

    public function test_standard_gauge_secondary_line_takes_the_trunk(): void
    {
        $this->networks('secondaire', 1435);
        Line::whereKey($this->b->id)->update(['gauge_mm_default' => 1435]);
        $res = $this->conflate([[4100, 900], [3800, 15], [2600, 15]]);

        $this->assertCount(1, $this->merged($res));
    }

    public function test_military_line_of_unknown_gauge_is_kept_distinct(): void
    {
        $this->networks('militaire');
        $res = $this->conflate([[4100, 900], [3800, 15], [2600, 15]]);

        $this->assertSame([], $this->merged($res));
        $this->assertSame(['réseau différent'], array_column($res['junctions'], 'why'));
    }

    public function test_private_siding_takes_the_trunk(): void
    {
        // Embranchement particulier (voie normale) : le tronc commun reste permis.
        $this->networks('particulier');
        $res = $this->conflate([[4100, 900], [3800, 15], [2600, 15]]);

        $this->assertCount(1, $this->merged($res));
    }

    public function test_different_network_reason_is_recorded_by_build_line_references(): void
    {
        Storage::fake('local');
        $this->networks('secondaire');
        $this->trace($this->a, 'archeoferroviaire', [[4100, 900], [3800, 15], [2600, 15]]);

        $this->artisan('vff:build-line-references', ['--line' => [(string) $this->a->id]])->assertSuccessful();

        $rec = DB::table('line_reference_duplicates')->where('line_id', $this->a->id)->where('action', 'kept_parallel')->sole();
        $this->assertSame('réseau différent : 405000', $rec->reason);
        $this->assertSame(0, DB::table('line_reference_duplicates')->where('line_id', $this->a->id)->where('action', 'merged_trunk')->count());
    }

    public function test_unknown_gauge_is_compatible(): void
    {
        Line::whereKey($this->b->id)->update(['gauge_mm_default' => 1435]);
        $res = $this->conflate([[4100, 900], [3800, 15], [2600, 15]]);

        $this->assertCount(1, $this->merged($res));
    }

    // ─── Cas négatifs ────────────────────────────────────────────────────

    public function test_real_parallel_track_with_its_own_osm_geometry_keeps_it(): void
    {
        // Sous une longue corde (sigma élargi), la voie BD TOPO de B gagnait au Viterbi puis était refusée
        // (autre ligne, 15 m > 12 m) : tout retombait sur le tracé d'origine. Repli : la voie OSM propre.
        $own = $this->trace(null, 'osm', [[2500, 15], [3900, 15]]);
        $res = $this->conflate([[4100, 900], [3800, 15], [2600, 15]]);

        $this->assertSame([], $this->merged($res));
        $osm = array_values(array_filter($res['pieces'], fn ($p) => $p['family'] === 'osm'));
        $this->assertCount(1, $osm);
        $this->assertContains($own, $osm[0]['trace_ids']);
        $this->assertEqualsWithDelta(1200, $osm[0]['len'], 120);
        $this->assertEqualsWithDelta(0, $this->length($res, 'bdtopo'), 1);
        $this->assertEqualsWithDelta(1200, $res['stats']['next_best_m'], 120);
    }

    public function test_fallback_skips_every_track_of_the_other_line_in_turn(): void
    {
        // La voie de B existe deux fois (BD TOPO et OSM, rattachées à B), refusées l'une après l'autre ; reste
        // la voie de service propre sous le tracé (parallèle sans convergence : rien n'est repris de B).
        $this->trace($this->b, 'osm', [[0, 1], [6000, 1]]);
        $own = $this->trace(null, 'bdtopo', [[900, 20], [4100, 20]], ['layer' => 'Voie de service']);
        $res = $this->conflate([[0, 900], [1000, 20], [4000, 20], [5000, 900]]);

        $this->assertSame([], $this->merged($res));
        $drawn = array_values(array_filter($res['pieces'], fn ($p) => in_array($own, $p['trace_ids'], true)));
        $this->assertCount(1, $drawn);
        $this->assertGreaterThan(2500, $drawn[0]['len']);
    }

    public function test_abandoned_osm_track_also_proves_a_distinct_track(): void
    {
        $this->trace(null, 'osm', [[2500, 16], [3900, 16]], ['layer' => 'abandoned']);
        $res = $this->conflate([[4100, 900], [3800, 15], [2600, 15]]);

        $this->assertSame([], $this->merged($res));
        $this->assertEqualsWithDelta(0, $this->length($res, 'bdtopo'), 1);
    }

    public function test_crossing_lines_are_not_merged(): void
    {
        // Croisement en biais (20°) : 10-30 m du tronc sur ~110 m de part et d'autre, puis de l'autre côté.
        $res = $this->conflate([[1000, 728], [3000, 0], [5000, -728]]);

        $this->assertSame([], $this->merged($res));
    }

    public function test_long_parallel_without_convergence_is_kept_and_reported(): void
    {
        // Parallèle à 20 m sur 3 km, mais la ligne s'écarte aux deux bouts : deux lignes distinctes.
        $res = $this->conflate([[0, 900], [1000, 20], [4000, 20], [5000, 900]]);

        $this->assertSame([], $this->merged($res));
        $kept = array_values(array_filter($res['junctions'], fn ($j) => $j['action'] === 'kept_parallel'));
        $this->assertCount(1, $kept);
        $this->assertEqualsWithDelta(20, $kept[0]['gap'], 1);
        $this->assertGreaterThan(2500, $kept[0]['len']);
        $this->assertEqualsWithDelta(0, $this->length($res, 'bdtopo'), 1);
        $this->assertGreaterThan(2500, $res['stats']['parallel_kept_m']);
    }

    public function test_end_of_trace_handing_over_to_the_line_own_precise_track_is_not_a_terminus(): void
    {
        // Le tracé archeo s'arrête là où commence la voie OSM rattachée à la ligne : simple relais.
        $this->trace($this->a, 'osm', [[2600, 20], [2000, 600]]);
        $res = $this->conflate([[4100, 900], [3800, 20], [2600, 20]]);

        $this->assertSame([], $this->merged($res));
    }

    public function test_track_of_the_same_line_is_never_a_trunk(): void
    {
        // Voie précise rattachée à la ligne A elle-même à 15 m : pas « une autre ligne ».
        DB::table('source_traces')->where('id', $this->trunkTrace)->update(['line_id' => $this->a->id]);
        $res = $this->conflate([[4100, 900], [3800, 15], [2600, 15]]);

        $this->assertSame([], $this->merged($res));
    }

    // ─── Tracés IGN historiques (ml/rail-extraction, source ign-historique) ───

    /** Voie extraite des orthophotos IGN 1950-1965, rattachée à la ligne A. */
    private function ignTrace(array $pts, float $score = 0.85): int
    {
        return $this->trace($this->a, 'ign-historique', $pts, ['layer' => 'ortho-1950', 'match_score' => $score]);
    }

    public function test_ign_historique_track_is_the_drawing_away_from_other_lines(): void
    {
        $ign = $this->ignTrace([[2550, 216], [3850, 216]]);
        $res = $this->conflate([[4100, 1100], [3800, 215], [2600, 215]]);

        $this->assertSame([], $res['junctions']);
        $pieces = array_values(array_filter($res['pieces'], fn ($p) => $p['family'] === 'ign-historique'));
        $this->assertCount(1, $pieces);
        $this->assertContains($ign, $pieces[0]['trace_ids']);
        $this->assertEqualsWithDelta(1200, $pieces[0]['len'], 120);
    }

    public function test_ign_historique_track_beside_the_trunk_does_not_prove_a_distinct_line(): void
    {
        // Comme à Vire : la voie extraite des photos de 1950 sous le tracé archeo est une voie du faisceau de la gare.
        $this->ignTrace([[2550, 16], [3850, 16]]);
        $res = $this->conflate([[4100, 900], [3800, 15], [2600, 15]]);

        $this->assertCount(1, $this->merged($res));
        $this->assertLessThan(0.5, $this->maxOffsetFromTrunk($res, 'bdtopo', 2600, 3700));
    }

    public function test_low_confidence_ign_historique_is_not_a_candidate(): void
    {
        $this->ignTrace([[2550, 16], [3850, 16]], 0.5);
        $res = $this->conflate([[4100, 900], [3800, 15], [2600, 15]]);

        $this->assertCount(1, $this->merged($res));
        $this->assertEqualsWithDelta(0, $this->length($res, 'ign-historique'), 1);
    }

    public function test_reference_drawn_on_ign_historique_is_precise_but_only_medium_confidence(): void
    {
        Storage::fake('local');
        $this->ignTrace([[2550, 216], [3850, 216]]);
        $this->trace($this->a, 'archeoferroviaire', [[4100, 1100], [3800, 215], [2600, 215]]);

        $this->artisan('vff:build-line-references', ['--line' => [(string) $this->a->id]])->assertSuccessful();

        $part = DB::table('line_reference_parts')->where('line_id', $this->a->id)->where('matched_source', 'ign-historique')->first();
        $this->assertNotNull($part);
        $this->assertSame('archeoferroviaire', $part->source);
        $conf = DB::table('line_reference_parts')->where('line_id', $this->a->id)->where('matched_source', 'ign-historique')->pluck('confidence')->unique()->all();
        $this->assertNotContains('high', $conf);
        $this->assertSame(0, DB::table('line_reference_duplicates')->where('line_id', $this->a->id)->where('action', 'merged_trunk')->count());
    }

    // ─── Seuils ──────────────────────────────────────────────────────────

    public function test_threshold_junction_distance(): void
    {
        $max = TraceConflation::PROFILES['archeoferroviaire']['junction_m'];
        $this->assertSame(30.0, $max);

        $in = $this->conflate([[4100, 900], [3800, $max - 1], [2600, $max - 1]]);
        $this->assertCount(1, $this->merged($in));
    }

    public function test_beyond_the_junction_distance_nothing_is_merged(): void
    {
        $max = TraceConflation::PROFILES['archeoferroviaire']['junction_m'];
        $out = $this->conflate([[4100, 900], [3800, $max + 2], [2600, $max + 2]]);

        $this->assertSame([], $out['junctions']);
        $this->assertEqualsWithDelta(0, $this->length($out, 'bdtopo'), 1);
    }

    public function test_close_trunk_is_accepted_by_the_validation_without_the_junction_rule(): void
    {
        // À 8 m (≤ other_line_median), la voie de l'autre ligne est déjà acceptée : pas d'enregistrement de jonction.
        $res = $this->conflate([[4100, 900], [3800, 8], [2600, 8]]);

        $this->assertSame([], $this->merged($res));
        $this->assertGreaterThan(1000, $this->length($res, 'bdtopo'));
    }

    public function test_own_track_just_inside_the_own_distance_blocks_the_rule(): void
    {
        // Voie OSM propre à 7 m du tracé (≤ JUNCTION_OWN_M) : voie distincte, recalée dessus, rien n'est repris de B.
        $this->trace(null, 'osm', [[2500, 22], [3900, 22]]);
        $res = $this->conflate([[4100, 900], [3800, 15], [2600, 15]]);

        $this->assertSame([], $this->merged($res));
        $this->assertGreaterThan(1000, $this->length($res, 'osm'));
    }

    public function test_short_run_below_the_minimum_is_ignored(): void
    {
        // 50 m le long du tronc seulement (2 observations) avant la gare.
        $res = $this->conflate([[2900, 700], [2650, 15], [2600, 15]]);

        $this->assertSame([], $this->merged($res));
    }

    public function test_sncf_profile_has_no_junction_rule(): void
    {
        $this->assertNull(TraceConflation::PROFILES['sncf']['junction_m']);
    }

    // ─── De bout en bout : tracé de référence, rapport, voie partagée ──────

    public function test_reference_takes_the_trunk_records_it_and_shared_track_hides_the_branch(): void
    {
        Storage::fake('local');
        $this->trace($this->a, 'archeoferroviaire', [[4100, 900], [3800, 15], [2600, 15]]);

        $this->artisan('vff:build-line-references', ['--line' => [(string) $this->a->id, (string) $this->b->id]])
            ->expectsOutputToContain('jonctions : 1,')
            ->assertSuccessful();

        $rec = DB::table('line_reference_duplicates')->where('line_id', $this->a->id)->where('action', 'merged_trunk')->sole();
        $this->assertSame('tronc commun 405000', $rec->reason);
        $this->assertSame('archeoferroviaire', $rec->source);
        $this->assertSame('bdtopo', $rec->kept_source);
        $this->assertContains($this->trunkTrace, json_decode($rec->kept_trace_ids, true));
        $this->assertEqualsWithDelta(15, $rec->gap_median_m, 1);
        $this->assertStringContainsString('tronc commun (voie de l\'autre ligne)', Storage::disk('local')->get('reports/trace-doublons-partiel.csv'));
        // Le tracé de référence de A longe exactement la voie de B entre la divergence et la gare.
        $near = (float) DB::selectOne(<<<'SQL'
            SELECT ST_Distance(ST_Transform(ST_Collect(geom), 2154), ST_SetSRID(ST_MakePoint(:x, :y), 2154)) AS m
            FROM line_reference_parts WHERE line_id = :line
            SQL, ['x' => self::X0 + 3000, 'y' => self::Y0, 'line' => $this->a->id])->m;
        $this->assertLessThan(0.5, $near);

        // Une ère : chaque ligne dessinée par son tracé de référence ; la branche (fermée) passe sous le tronc.
        $era = Era::factory()->create(['year' => 1980]);
        foreach ([[$this->b, 'in_service', 'mixed'], [$this->a, 'abandoned', 'unknown']] as [$line, $status, $traffic]) {
            DB::insert(<<<'SQL'
                INSERT INTO line_segments (line_id, era_id, status, traffic_type, geom, created_at, updated_at)
                SELECT ?, ?, ?, ?, d.geom, NOW(), NOW()
                FROM line_reference_geometries g, LATERAL ST_Dump(ST_LineMerge(g.geom)) d WHERE g.line_id = ?
                SQL, [$line->id, $era->id, $status, $traffic, $line->id]);
        }
        $this->artisan('vff:build-shared-track', ['--era' => 1980])->assertSuccessful();

        $covered = (float) DB::table('line_segment_covers as c')->join('line_segments as s', 's.id', '=', 'c.segment_id')
            ->where('s.line_id', $this->a->id)->sum('c.covered_m');
        $this->assertEqualsWithDelta(1150, $covered, 150);
    }

    // ─── Voie propre portant le nom de la ligne, à côté de la voie d'une autre ligne ─────

    /** Tracé archeo de A entre la voie de B (y = 0) et une voie OSM non rattachée à 14 m (y = 14) nommée $name. */
    private function besideNamedTrack(string $name): array
    {
        $this->trace(null, 'osm', [[500, 14], [5500, 14]], ['properties' => json_encode(['name' => $name, 'railway' => 'rail'])]);

        return $this->conflate([[0, 7], [6000, 7]]);
    }

    public function test_own_named_track_beside_another_line_is_preferred(): void
    {
        // Hombourg-Budange / Vire : la voie de la ligne (OSM, non rattachée, à son nom) longe la voie SNCF d'une autre ligne.
        $res = $this->besideNamedTrack('Ancienne ligne de Caen à Vire');

        $this->assertGreaterThan(3500, $this->length($res, 'osm'));
        // La voie de B ne reste qu'au-delà de la voie propre (2 × 500 m) et sur les bouts de blocs de validation peu couverts.
        $this->assertLessThan(2500, $this->length($res, 'bdtopo'));
        $this->assertGreaterThan(0, $res['stats']['own_track_preferred'] ?? 0);
    }

    public function test_unrelated_unattached_track_does_not_displace_the_other_line_track(): void
    {
        $res = $this->besideNamedTrack('Ligne de Paris à Brest');

        $this->assertGreaterThan(4000, $this->length($res, 'bdtopo'));
        $this->assertSame(0, $res['stats']['own_track_preferred'] ?? 0);
    }

    public function test_place_tokens_of_a_line_name(): void
    {
        $this->assertSame(['caen', 'vire'], TraceConflation::placeTokens('Ancienne ligne de Caen à Vire'));
        $this->assertSame(['bettelainville', 'hombourg'], TraceConflation::placeTokens('Ligne de Bettelainville à Hombourg'));
        $this->assertSame(['nantes', 'reze'], TraceConflation::placeTokens('Ligne de Nantes à Rezé'));
    }
}
