<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Models\Station;
use App\Services\StationCorrections;
use App\Services\StationTimeline;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * StationCorrections::apply et ses résolutions de gare : liens OSM et Wikidata, actions position, pk,
 * tourist, current, kind, unlink, et rapport des corrections écartées.
 */
class StationCorrectionsApplyTest extends TestCase
{
    use RefreshDatabase;

    private function station(string $name, ?string $uic = null, float $lon = 2.0, float $lat = 48.0): Station
    {
        return Station::factory()->create(['name' => $name, 'uic_code' => $uic, 'geom' => Point::makeGeodetic($lat, $lon)]);
    }

    private function source(Station $s, string $source, string $ref): void
    {
        DB::table('station_sources')->insert([
            'source' => $source, 'source_ref' => $ref, 'station_id' => $s->id, 'name' => $s->name, 'lifecycle' => 'active',
            'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(), 'geom' => DB::raw('ST_SetSRID(ST_MakePoint(2.0, 48.0), 4326)'),
        ]);
    }

    /** @param  array<string, mixed>  $extra @return array<string, mixed> */
    private function correction(array $station, string $action, array $extra = []): array
    {
        return ['station' => $station, 'action' => $action, 'source_url' => 'https://example.org/s', 'reason' => 'Relevé sur place.'] + $extra;
    }

    public function test_a_station_is_found_by_its_osm_or_wikidata_source_and_name(): void
    {
        $s = $this->station('Argentan');
        $this->source($s, 'osm', 'n42');
        $this->source($s, 'wikidata', 'Q77');

        $this->assertSame([$s->id], StationCorrections::resolveStation(['name' => 'Argentan', 'osm' => 'n42']));
        $this->assertSame([$s->id], StationCorrections::resolveStation(['name' => 'Argentan', 'wikidata' => 'Q77']));
        $this->assertSame([], StationCorrections::resolveStation(['name' => 'Autre', 'osm' => 'n42']));
        $this->assertSame([], StationCorrections::resolveStation(['name' => 'Argentan', 'osm' => 'n43']));
    }

    public function test_near_keeps_only_the_stations_within_the_radius(): void
    {
        $close = $this->station('Doublon', null, 2.0, 48.0);
        $this->station('Doublon', null, 2.5, 48.0);

        $this->assertSame([$close->id], StationCorrections::resolveStation(['name' => 'Doublon', 'near' => [2.0, 48.0, 1]]));
    }

    public function test_apply_runs_each_action_and_reports_the_skipped_ones(): void
    {
        $moved = $this->station('Deplacee', '87000001');
        $tourist = $this->station('Touristique', '87000002');
        $current = $this->station('Declassee', '87000003');
        $kind = $this->station('Technique', '87000004');
        $linked = $this->station('Liee', '87000005');
        $pk = $this->station('Pointee', '87000006');
        $line = Line::factory()->create(['code' => '123456']);
        DB::table('station_lines')->insert(['station_id' => $pk->id, 'line_id' => $line->id, 'method' => 'sncf_pk', 'pk_m' => 1000,
            'pk_source' => 'sncf', 'distance_m' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $this->station('Double', '87000007');
        $this->station('Double', '87000007');

        $result = (new StationCorrections([
            $this->correction(['name' => 'Deplacee', 'uic' => '87000001'], 'position', ['lng' => 3.5, 'lat' => 49.5]),
            $this->correction(['name' => 'Touristique', 'uic' => '87000002'], 'tourist'),
            $this->correction(['name' => 'Declassee', 'uic' => '87000003'], 'current', ['status' => StationTimeline::CLOSED]),
            $this->correction(['name' => 'Technique', 'uic' => '87000004'], 'kind', ['kind' => StationTimeline::KIND_EXCLUDED]),
            $this->correction(['name' => 'Liee', 'uic' => '87000005'], 'unlink', ['source' => 'wikidata', 'ref' => 'Q5']),
            $this->correction(['name' => 'Pointee', 'uic' => '87000006'], 'pk', ['line' => '123456', 'pk_m' => 4200]),
            $this->correction(['name' => 'Pointee', 'uic' => '87000006'], 'pk', ['line' => '999999', 'pk_m' => 1]),
            $this->correction(['name' => 'Inconnue', 'uic' => '99'], 'tourist'),
            $this->correction(['name' => 'Double', 'uic' => '87000007'], 'tourist'),
            $this->correction(['name' => 'Cassee', 'uic' => '1'], 'explode'),
            $this->correction(['name' => 'Fantome', 'uic' => '2'], 'neutralize', ['match' => ['source' => 'osm', 'type' => 'closure', 'date' => '1900-01-01']]),
        ]))->apply();

        $this->assertSame([$kind->id => StationTimeline::KIND_EXCLUDED], $result['kinds']);
        $this->assertSame([$tourist->id => true], $result['tourist']);
        $this->assertSame([$current->id => StationTimeline::CLOSED], $result['current']);
        // position, tourist, current, kind, unlink, pk : six corrections appliquées.
        $this->assertSame(6, $result['applied']);
        $this->assertCount(5, $result['skipped']);
        $joined = implode("\n", $result['skipped']);
        $this->assertStringContainsString('gare introuvable', $joined);
        $this->assertStringContainsString('2 fiches', $joined);
        $this->assertStringContainsString('action inconnue', $joined);
        $this->assertStringContainsString('sans effet', $joined);

        $row = DB::table('stations')->where('id', $moved->id)->selectRaw('ST_X(geom) as x, ST_Y(geom) as y, position_source')->first();
        $this->assertEqualsWithDelta(3.5, $row->x, 1e-6);
        $this->assertEqualsWithDelta(49.5, $row->y, 1e-6);
        $this->assertSame('correction', $row->position_source);
        $this->assertSame(4200, (int) DB::table('station_lines')->where('station_id', $pk->id)->value('pk_m'));
        $this->assertSame('correction', DB::table('station_lines')->where('station_id', $pk->id)->value('pk_source'));
        $this->assertSame('87000005', (string) DB::table('stations')->where('id', $linked->id)->value('uic_code'));
    }

    public function test_helpers_return_only_resolved_valid_corrections(): void
    {
        $t = $this->station('Touristique', '87000002');
        $c = $this->station('Declassee', '87000003');
        $this->station('Double', '87000007');
        $this->station('Double', '87000007');

        $corrections = new StationCorrections([
            $this->correction(['name' => 'Touristique', 'uic' => '87000002'], 'tourist'),
            $this->correction(['name' => 'Double', 'uic' => '87000007'], 'tourist'),
            $this->correction(['name' => 'Inconnue', 'uic' => '9'], 'tourist'),
            $this->correction(['name' => 'Declassee', 'uic' => '87000003'], 'current', ['status' => StationTimeline::CLOSED]),
            $this->correction(['name' => 'Declassee', 'uic' => '87000003'], 'current', ['status' => 'bizarre']),
        ]);

        $this->assertSame([$t->id => true], $corrections->touristStations());
        $this->assertSame([$c->id => StationTimeline::CLOSED], $corrections->currentStatuses());
    }

    public function test_a_second_apply_replaces_the_first_one_events(): void
    {
        $s = $this->station('Argentan', '87000008');
        $add = $this->correction(['name' => 'Argentan', 'uic' => '87000008'], 'add', ['type' => 'closure_passenger', 'date' => '1938-05-05']);

        $corrections = new StationCorrections([$add]);
        $first = $corrections->apply();
        $second = $corrections->apply();

        $this->assertSame(1, $first['applied']);
        $this->assertSame(1, $second['applied']);
        $this->assertSame(1, DB::table('station_events')->where('station_id', $s->id)->where('source', StationTimeline::CORRECTION)->count());
    }
}
