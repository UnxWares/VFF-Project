<?php

namespace Tests\Feature\Api;

use App\Models\Era;
use App\Models\Line;
use App\Services\TrackLayout\OsmTrackLayoutSource;
use App\Services\TrackLayout\VffTrackLayoutSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Values the track layout contract promises, read from the real sources (end-of-work review of 25/11/2026):
 *   - an OpenStreetMap signal mapped without any `railway:signal:<category>` tag (5,332 of the 17,818 signals of the
 *     import of 24/11/2026, on 247 lines: Paris - Marseille PK 10+422) has a kind: GraphQL `TrackLayoutSignal.kind` is
 *     non-null and the whole `track_layout` field failed on such a line;
 *   - the `vff` source names what to credit (`attribution`), like the RINF and OpenStreetMap: SNCF Réseau (ODbL) for
 *     the speeds, level crossings and station PKs, IGN (Licence Ouverte 2.0) for the gradients of the long profile.
 */
class TrackLayoutContractValuesTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->line = Line::factory()->create(['slug' => 'rfn-830000', 'code' => '830000', 'name' => 'Ligne de Paris-Lyon à Marseille-St-Charles']);
    }

    private function seedOsmSignals(): void
    {
        DB::table('osm_track_imports')->insert(['file' => 'france-tracks.opl', 'data_at' => '2026-11-24 06:00:00', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('osm_tracks')->insert(['line_id' => $this->line->id, 'osm_way_id' => 734205901, 'part' => 0, 'kind' => 'running', 'ref' => '1',
            'pk_from_m' => 10000, 'pk_to_m' => 11000, 'pk_precision' => 'derived', 'length_m' => 1000, 'offset_m' => 2.1, 'attach' => 'code',
            'tags' => '{}', 'geom' => DB::raw('ST_GeomFromText(\'LINESTRING(2.40 48.80, 2.41 48.80)\', 4326)'), 'created_at' => now(), 'updated_at' => now()]);
        foreach ([[1313771392, null, null, 10422], [1313237979, 'main', 'light', 10443]] as [$node, $category, $form, $pk]) {
            DB::table('osm_track_nodes')->insert(['line_id' => $this->line->id, 'osm_node_id' => $node, 'kind' => 'signal', 'subtype' => $category,
                'detail' => $form, 'ref' => null, 'pk_m' => $pk, 'pk_precision' => 'derived', 'way_id' => 734205901, 'direction' => 'up',
                'tags' => '{}', 'geom' => DB::raw('ST_SetSRID(ST_MakePoint(2.405, 48.80), 4326)'), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function test_an_osm_signal_without_category_has_a_kind_in_rest_and_graphql(): void
    {
        $this->seedOsmSignals();

        $rest = $this->getJson('/api/v1/lines/rfn-830000/track-layout?kinds=signals')->assertOk()->json('signals');
        $this->assertSame([OsmTrackLayoutSource::UNKNOWN_SIGNAL_KIND, 'main'], array_column($rest, 'kind'));
        $this->assertSame(['osm:way:734205901', 'osm:way:734205901'], array_column($rest, 'track'));

        $gql = $this->postJson('/graphql', ['query' => '{ line(slug: "rfn-830000") { track_layout(kinds: [SIGNALS]) { signals { id kind form track direction } } } }'])
            ->assertOk()->assertJsonMissingPath('errors')->json('data.line.track_layout.signals');
        $this->assertSame(['unknown', 'main'], array_column($gql, 'kind'));
    }

    public function test_the_vff_source_names_what_to_credit(): void
    {
        $era = Era::factory()->current()->create(['year' => 2030]);
        DB::table('line_speed_ranges')->insert(['line_id' => $this->line->id, 'era_id' => $era->id, 'pk_start_m' => 0, 'pk_end_m' => 5000,
            'max_speed_kmh' => 160, 'source' => 'sncf', 'created_at' => now(), 'updated_at' => now()]);

        $sources = $this->getJson('/api/v1/lines/rfn-830000/track-layout')->assertOk()->json('sources');
        $vff = collect((array) $sources)->firstWhere('key', 'vff');
        $this->assertSame(VffTrackLayoutSource::ATTRIBUTION, $vff['attribution']);
        $this->assertStringContainsString('SNCF Réseau', (string) $vff['attribution']);
        $this->assertStringContainsString('IGN', (string) $vff['attribution']);

        $gql = $this->postJson('/graphql', ['query' => '{ line(slug: "rfn-830000") { track_layout { sources { key attribution } } } }'])
            ->assertOk()->assertJsonMissingPath('errors')->json('data.line.track_layout.sources');
        $this->assertSame([['key' => 'vff', 'attribution' => VffTrackLayoutSource::ATTRIBUTION]], $gql);
    }
}
