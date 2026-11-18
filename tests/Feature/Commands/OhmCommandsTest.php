<?php

namespace Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * OpenHistoricalMap : vff:import-ohm (ways ferroviaires datés → source_traces)
 * et vff:ohm-events (périodes d'exploitation des ways → line_events par portion).
 * Overpass et contours régionaux simulés, attentes sans délai (Sleep::fake).
 */
class OhmCommandsTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        $this->era(2030, current: true);
        Sleep::fake();
        // Contour : une seule région métropolitaine (Bretagne simplifiée) ; un DROM (code 03) ignoré.
        Storage::disk('local')->put('sources/ohm/regions-1000m.geojson', json_encode(['type' => 'FeatureCollection', 'features' => [
            ['type' => 'Feature', 'properties' => ['code' => '53'], 'geometry' => ['type' => 'Polygon',
                'coordinates' => [[[-5.0, 47.5], [-1.6, 47.5], [-1.6, 48.8], [-5.0, 48.8], [-5.0, 47.5]]]]],
            ['type' => 'Feature', 'properties' => ['code' => '03'], 'geometry' => ['type' => 'Polygon',
                'coordinates' => [[[-53.0, 4.0], [-52.0, 4.0], [-52.0, 5.0], [-53.0, 5.0], [-53.0, 4.0]]]]],
        ]]));
    }

    /** @return array<string, mixed> */
    private function overpassResponse(): array
    {
        $way = fn (int $id, array $tags, array $coords) => ['type' => 'way', 'id' => $id, 'tags' => $tags,
            'geometry' => array_map(fn ($c) => ['lat' => $c[1], 'lon' => $c[0]], $coords)];

        return ['elements' => [
            $way(101, ['railway' => 'disused', 'name' => 'Ligne Carhaix - Morlaix', 'start_date' => '1891-09-28', 'end_date' => '1967'],
                [[-3.57, 48.27], [-3.70, 48.40], [-3.83, 48.58]]),
            $way(102, ['disused:railway' => 'rail', 'start_date:edtf' => '1905~'], [[-2.0, 48.0], [-2.1, 48.1]]),
            $way(103, ['railway' => 'rail'], [[-2.5, 48.0], [-2.5, 48.0]]), // dégénéré
            $way(104, ['railway' => 'rail', 'name' => 'Hors métropole'], [[-30.0, 40.0], [-30.1, 40.1]]),
            ['type' => 'node', 'id' => 9, 'lat' => 48.0, 'lon' => -2.0],
        ]];
    }

    public function test_ohm_ways_are_imported_with_their_dates_and_layers(): void
    {
        Http::fake(['overpass-api.openhistoricalmap.org/*' => Http::response($this->overpassResponse())]);

        $this->artisan('vff:import-ohm', ['--tile' => 2, '--pause' => 0])
            ->expectsOutputToContain('→ 2 tuiles de 2° recouvrent la métropole.')
            ->expectsOutputToContain('Ways écrits : 2 · hors métropole ignorés : 1 · tuiles en échec : 0')
            ->expectsOutputToContain('✓ 2 ways OHM en base')
            ->assertSuccessful();

        $rows = DB::table('source_traces')->where('source', 'ohm')->orderBy('source_ref')->get()->keyBy('source_ref');
        $this->assertEquals(['101', '102'], $rows->keys()->map(fn ($k) => (string) $k)->all());
        $this->assertSame(['disused', 'Ligne Carhaix - Morlaix', 1891, 1967, 'ohm', 1.0],
            [$rows['101']->layer, $rows['101']->name, $rows['101']->opened_year, $rows['101']->closed_year, $rows['101']->dates_source, $rows['101']->dates_score]);
        $this->assertSame(['disused', 1905, null], [$rows['102']->layer, $rows['102']->opened_year, $rows['102']->closed_year],
            'préfixe de cycle de vie, date EDTF incertaine');
        $this->assertEqualsWithDelta(39577, $rows['101']->length_m, 5, 'longueur géodésique du way');
        $this->assertSame('1891-09-28', json_decode($rows['101']->properties, true)['start_date'], 'tous les tags gardés');

        // Requête : ways railway, préfixes de cycle de vie, historic=railway.
        Http::assertSent(fn (Request $r) => str_contains($r['data'], 'way["railway"~"^(rail|narrow_gauge')
            && str_contains($r['data'], 'way["historic"="railway"]') && $r->hasHeader('User-Agent'));
        Http::assertSentCount(2);
        // Réponses brutes gardées (gzip) pour rejouer l'import.
        $this->assertCount(2, Storage::disk('local')->files('sources/ohm/'.now()->format('Y-m-d')));
    }

    public function test_ohm_import_replays_from_cache_and_is_idempotent(): void
    {
        Http::fake(['overpass-api.openhistoricalmap.org/*' => Http::response($this->overpassResponse())]);
        $this->artisan('vff:import-ohm', ['--tile' => 2, '--pause' => 0])->assertSuccessful();
        $before = $this->snapshot(['source_traces'], ['id', 'seen_at']);

        $this->artisan('vff:import-ohm', ['--tile' => 2, '--pause' => 0, '--cache' => true])->assertSuccessful();

        Http::assertSentCount(2);
        $this->assertSame($before, $this->snapshot(['source_traces'], ['id', 'seen_at']));
    }

    public function test_vanished_ways_are_pruned_only_on_request(): void
    {
        Http::fake(['overpass-api.openhistoricalmap.org/*' => Http::response($this->overpassResponse())]);
        DB::statement("INSERT INTO source_traces (source, source_ref, layer, seen_at, geom)
            VALUES ('ohm', '999', 'rail', now() - interval '2 days', ST_Multi(ST_GeomFromText('LINESTRING(-2 48, -2.1 48.1)', 4326)))");

        $this->artisan('vff:import-ohm', ['--tile' => 2, '--pause' => 0])
            ->expectsOutputToContain('1 ways OHM non renvoyés par ce passage')
            ->assertSuccessful();
        $this->assertTrue(DB::table('source_traces')->where('source_ref', '999')->exists());

        $this->artisan('vff:import-ohm', ['--tile' => 2, '--pause' => 0, '--prune' => true])
            ->expectsOutputToContain('1 ways OHM disparus supprimés.')
            ->assertSuccessful();
        $this->assertFalse(DB::table('source_traces')->where('source_ref', '999')->exists());
    }

    public function test_failing_tiles_are_split_retried_then_reported_without_pruning(): void
    {
        Http::fake(['overpass-api.openhistoricalmap.org/*' => Http::response('erreur', 504)]);
        DB::statement("INSERT INTO source_traces (source, source_ref, layer, seen_at, geom)
            VALUES ('ohm', '999', 'rail', now() - interval '2 days', ST_Multi(ST_GeomFromText('LINESTRING(-2 48, -2.1 48.1)', 4326)))");

        $this->artisan('vff:import-ohm', ['--tile' => 2, '--pause' => 0, '--prune' => true])
            ->expectsOutputToContain('abandonnée après plusieurs essais.')
            ->assertFailed();

        // 2 tuiles, chacune redécoupée en 4 puis 16 : 42 requêtes × 4 essais, sans attente réelle.
        Http::assertSentCount(2 * (1 + 4 + 16) * 4);
        $this->assertTrue(DB::table('source_traces')->where('source_ref', '999')->exists(), 'tuile en échec : aucune suppression');
    }

    public function test_overpass_runtime_error_remark_splits_the_tile(): void
    {
        Http::fake(['overpass-api.openhistoricalmap.org/*' => Http::sequence()
            ->push('{"elements": [], "remark": "runtime error: Query timed out"}')
            ->whenEmpty(Http::response(['elements' => []]))]);

        $this->artisan('vff:import-ohm', ['--tile' => 2, '--pause' => 0])->assertSuccessful();

        Http::assertSentCount(1 + 4 + 1, 'première tuile redécoupée en 4, seconde tuile normale');
    }

    public function test_regional_boundaries_are_downloaded_once(): void
    {
        Storage::disk('local')->delete('sources/ohm/regions-1000m.geojson');
        $regions = ['type' => 'FeatureCollection', 'features' => [['type' => 'Feature', 'properties' => ['code' => '53'],
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[[-5.0, 47.5], [-1.6, 47.5], [-1.6, 48.8], [-5.0, 48.8], [-5.0, 47.5]]]]]]];
        Http::fake([
            'etalab-datasets.geo.data.gouv.fr/*' => Http::response(gzencode(json_encode($regions))),
            'overpass-api.openhistoricalmap.org/*' => Http::response(['elements' => []]),
        ]);

        $this->artisan('vff:import-ohm', ['--tile' => 2, '--pause' => 0])->assertSuccessful();

        $this->assertEquals($regions, json_decode(Storage::disk('local')->get('sources/ohm/regions-1000m.geojson'), true), 'stocké décompressé');
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'regions-1000m.geojson.gz'));
    }

    // ─── vff:ohm-events ──────────────────────────────────────────────────

    /** Ligne de 7,4 km et ses ways OHM : mixte puis fret seul sur la 1re moitié, disused daté sur la 2de, embranchement. */
    private function ohmLine(): int
    {
        $line = $this->rfnLine('482000', 'Ligne de Carhaix à Morlaix');
        DB::statement("INSERT INTO line_reference_geometries (line_id, length_m, sources, geom, created_at, updated_at)
            VALUES (?, 7400, '[\"test\"]', ST_GeomFromText('MULTILINESTRING((-3.6 48.3, -3.5 48.3))', 4326), now(), now())", [$line->id]);
        $ways = [
            [1, 'rail', ['railway' => 'rail', 'name' => 'Ligne Carhaix - Morlaix', 'start_date' => '1891-09-28', 'end_date' => '1938-05-15'], 'LINESTRING(-3.6 48.3, -3.55 48.3)'],
            [2, 'rail', ['railway' => 'rail', 'usage' => 'freight', 'start_date' => '1938-05-15', 'end_date' => '1967'], 'LINESTRING(-3.6 48.3, -3.55 48.3)'],
            [3, 'disused', ['railway' => 'disused', 'start_date' => '1891-09-28', 'end_date' => '1967'], 'LINESTRING(-3.55 48.3, -3.5 48.3)'],
            [4, 'rail', ['railway' => 'rail', 'service' => 'siding', 'start_date' => '1900'], 'LINESTRING(-3.6 48.3001, -3.5 48.3001)'],
        ];
        foreach ($ways as [$id, $layer, $tags, $wkt]) {
            DB::statement("INSERT INTO source_traces (id, source, source_ref, layer, name, properties, seen_at, geom, length_m)
                VALUES (?, 'ohm', ?, ?, ?, ?::jsonb, now(), ST_Multi(ST_GeomFromText(?, 4326)), ROUND(ST_Length(ST_GeomFromText(?, 4326)::geography)))",
                [$id, (string) $id, $layer, $tags['name'] ?? null, json_encode($tags), $wkt, $wkt]);
        }

        return $line->id;
    }

    /** @return list<array<string, mixed>> */
    private function ohmEvents(int $lineId): array
    {
        return array_map(fn ($e) => (array) $e, DB::table('line_events')->where('line_id', $lineId)->where('source', 'ohm')->orderBy('event_date')
            ->selectRaw('type, event_date::text AS date, date_precision, confidence, section_resolution, source_trace_id, source_url,
                         ROUND(ST_Length(section_geom::geography) / 100) * 100 AS len')->get()->all());
    }

    public function test_ohm_events_follow_the_periods_of_the_ways(): void
    {
        $lineId = $this->ohmLine();

        $this->artisan('vff:ohm-events', ['--line' => ['482000']])->assertSuccessful();

        $this->assertEquals([
            ['type' => 'opening', 'date' => '1891-09-28', 'date_precision' => 'day', 'confidence' => 'medium', 'section_resolution' => 'trace',
                'source_trace_id' => 1, 'source_url' => 'https://www.openhistoricalmap.org/way/1', 'len' => 7400],
            ['type' => 'closure_passenger', 'date' => '1938-05-15', 'date_precision' => 'day', 'confidence' => 'low', 'section_resolution' => 'trace',
                'source_trace_id' => 2, 'source_url' => 'https://www.openhistoricalmap.org/way/2', 'len' => 3700],
            ['type' => 'closure', 'date' => '1967-01-01', 'date_precision' => 'year', 'confidence' => 'medium', 'section_resolution' => 'trace',
                'source_trace_id' => 2, 'source_url' => 'https://www.openhistoricalmap.org/way/2', 'len' => 7400],
        ], $this->ohmEvents($lineId), 'disused daté = période de vie ; usage=freight au relais = fermeture aux voyageurs ; embranchement ignoré');

        $quote = DB::table('line_events')->where('line_id', $lineId)->where('type', 'closure_passenger')->value('source_quote');
        $this->assertSame('way 2 : usage=freight, railway=rail, end_date=1967, start_date=1938-05-15 - changement de trafic entre ways successifs', $quote);
        $this->assertSame('Fermeture au service voyageurs (fret maintenu) d\'une portion de 3,7 km, d\'après OpenHistoricalMap.',
            DB::table('line_events')->where('line_id', $lineId)->where('type', 'closure_passenger')->value('description'));
    }

    public function test_ohm_events_are_idempotent_and_follow_the_ways(): void
    {
        $lineId = $this->ohmLine();
        DB::table('line_events')->insert(['line_id' => $lineId, 'type' => 'opening', 'event_date' => '1891-09-28', 'source' => 'wikipedia',
            'description' => 'Ouverture (Wikipédia)', 'created_at' => now(), 'updated_at' => now()]);
        $this->artisan('vff:ohm-events', ['--line' => ['482000']])->assertSuccessful();
        $before = $this->snapshot(['line_events'], ['id']);

        $this->artisan('vff:ohm-events', ['--line' => ['482000']])->assertSuccessful();
        $this->assertSame($before, $this->snapshot(['line_events'], ['id']), 'second passage : rien ne change');

        // Way « fret seul » retiré d'OHM : la fermeture aux voyageurs disparaît, l'événement Wikipédia reste.
        DB::table('source_traces')->where('id', 2)->delete();
        $this->artisan('vff:ohm-events', ['--line' => ['482000']])->assertSuccessful();
        $this->assertNotContains('closure_passenger', array_column($this->ohmEvents($lineId), 'type'));
        $this->assertTrue(DB::table('line_events')->where('line_id', $lineId)->where('source', 'wikipedia')->exists());
    }

    public function test_ohm_events_dry_run_writes_nothing(): void
    {
        $this->ohmLine();
        $before = $this->snapshot(['line_events']);

        $this->artisan('vff:ohm-events', ['--line' => ['482000'], '--dry-run' => true])->assertSuccessful();

        $this->assertSame($before, $this->snapshot(['line_events']));
    }
}
