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
 * StationCorrections : bornes et libellés que les tests de forme et d'application laissent passer
 * (ancres des motifs, rayon de near, clé de gare, rapport exact des corrections écartées, note, source_ref).
 */
class StationCorrectionsMutationTest extends TestCase
{
    use RefreshDatabase;

    private function station(string $name, ?string $uic = null, float $lon = 2.0, float $lat = 48.0, ?string $wikidata = null): Station
    {
        return Station::factory()->create(['name' => $name, 'uic_code' => $uic, 'wikidata_id' => $wikidata, 'geom' => Point::makeGeodetic($lat, $lon)]);
    }

    /** @param  array<string, mixed>  $station  @param  array<string, mixed>  $extra  @return array<string, mixed> */
    private function correction(array $station, string $action, array $extra = []): array
    {
        return array_replace(['station' => $station, 'action' => $action, 'source_url' => 'https://example.org/s', 'reason' => 'Relevé sur place.'], $extra);
    }

    /** @return array<string, mixed> */
    private function base(array $override = []): array
    {
        return $override + $this->correction(['name' => 'X', 'uic' => '1'], 'add', ['type' => 'closure', 'date' => '1900-01-01']);
    }

    public function test_the_date_pattern_is_anchored_at_both_ends(): void
    {
        $this->assertSame([], StationCorrections::validate($this->base()));
        $this->assertNotSame([], StationCorrections::validate($this->base(['date' => 'x1900-01-01'])));
        $this->assertNotSame([], StationCorrections::validate($this->base(['date' => '1900-01-01x'])));
    }

    public function test_the_pk_line_code_is_six_digits_exactly(): void
    {
        $pk = fn (string $line) => StationCorrections::validate($this->base(['action' => 'pk', 'line' => $line, 'pk_m' => 10]));

        $this->assertSame([], $pk('123456'));
        $this->assertNotSame([], $pk('1234567'));
        $this->assertNotSame([], $pk('x123456'));
        $this->assertNotSame([], $pk('123456x'));
    }

    public function test_the_wikidata_ref_must_start_with_q(): void
    {
        $unlink = fn (string $ref) => StationCorrections::validate($this->base(['action' => 'unlink', 'source' => 'wikidata', 'ref' => $ref]));

        $this->assertSame([], $unlink('Q12'));
        $this->assertNotSame([], $unlink('xQ12'));
        $this->assertNotSame([], $unlink('Q12x'));
    }

    public function test_near_accepts_a_five_kilometre_radius_and_nothing_above(): void
    {
        $near = fn (float|int $km) => StationCorrections::validate($this->base(['station' => ['name' => 'X', 'near' => [2.0, 48.0, $km]]]));

        $this->assertSame([], $near(5));
        $this->assertSame([], $near(0.1));
        $this->assertNotSame([], $near(5.01));
    }

    public function test_every_error_is_reported_not_only_the_first(): void
    {
        $errors = StationCorrections::validate(['action' => 'explode', 'station' => [], 'reason' => '']);

        $this->assertSame(['action inconnue', 'gare : name + uic, wikidata, osm ou near', 'source_url manquante', 'reason manquante'], $errors);
    }

    public function test_a_station_is_resolved_by_each_key_alone_among_homonyms(): void
    {
        $a = $this->station('Doublon', '87000001', 2.0, 48.0, 'Q1');
        $b = $this->station('Doublon', '87000002', 2.0, 48.0, 'Q2');
        $c = $this->station('Doublon', '87000003');
        DB::table('station_sources')->insert([
            'source' => 'wikidata', 'source_ref' => 'Q9', 'station_id' => $c->id, 'name' => 'Doublon', 'lifecycle' => 'active',
            'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(), 'geom' => DB::raw('ST_SetSRID(ST_MakePoint(2.0, 48.0), 4326)'),
        ]);

        $this->assertSame([$a->id], StationCorrections::resolveStation(['name' => 'Doublon', 'uic' => '87000001']));
        $this->assertSame([$b->id], StationCorrections::resolveStation(['name' => 'Doublon', 'wikidata' => 'Q2']));
        $this->assertSame([$c->id], StationCorrections::resolveStation(['name' => 'Doublon', 'wikidata' => 'Q9']));
        $this->assertSame([], StationCorrections::resolveStation(['name' => 'Doublon', 'wikidata' => 'Q404']));
        $this->assertSame([], StationCorrections::resolveStation(['name' => 'Doublon', 'uic' => '404']));
    }

    public function test_near_radius_is_in_kilometres_to_the_metre(): void
    {
        $s = $this->station('Proche', null, 2.0, 48.0);
        $this->station('Proche', null, 3.0, 48.0);
        $d = (float) DB::selectOne('SELECT ST_Distance(ST_SetSRID(ST_MakePoint(2.0, 48.0), 4326)::geography, ST_SetSRID(ST_MakePoint(2.01, 48.0), 4326)::geography) AS d')->d;
        $near = $this->station('Proche', null, 2.01, 48.0);

        // Rayon de quelques centimètres au-dessus de la distance : la fiche est retenue ; en dessous : écartée.
        $this->assertContains($near->id, StationCorrections::resolveStation(['name' => 'Proche', 'near' => [2.0, 48.0, ($d + 0.4) / 1000]]));
        $this->assertNotContains($near->id, StationCorrections::resolveStation(['name' => 'Proche', 'near' => [2.0, 48.0, ($d - 0.4) / 1000]]));
        $this->assertSame([$s->id], StationCorrections::resolveStation(['name' => 'Proche', 'near' => [2.0, 48.0, ($d - 0.4) / 1000]]));
    }

    public function test_the_default_corrections_are_the_data_file(): void
    {
        $property = new \ReflectionProperty(StationCorrections::class, 'corrections');

        $this->assertSame(require database_path('data/gares_corrections.php'), $property->getValue(new StationCorrections));
    }

    public function test_unlinks_are_strings_and_all_listed(): void
    {
        $unlinks = (new StationCorrections([
            $this->correction(['name' => 'A', 'uic' => 87000001], 'unlink', ['source' => 'wikidata', 'ref' => 'Q1']),
            $this->correction(['name' => 'B', 'osm' => 'n42'], 'unlink', ['source' => 'wikidata', 'ref' => 'Q2']),
            $this->correction(['name' => 'C', 'uic' => '3'], 'add', ['type' => 'closure', 'date' => '1900-01-01']),
        ]))->unlinks();

        $this->assertSame([
            ['name' => 'A', 'uic' => '87000001', 'osm' => null, 'source' => 'wikidata', 'ref' => 'Q1'],
            ['name' => 'B', 'uic' => null, 'osm' => 'n42', 'source' => 'wikidata', 'ref' => 'Q2'],
        ], $unlinks);
    }

    public function test_tourist_stations_lists_every_resolved_station(): void
    {
        $a = $this->station('Une', '1');
        $b = $this->station('Deux', '2');

        $this->assertSame([$a->id => true, $b->id => true], (new StationCorrections([
            $this->correction(['name' => 'Une', 'uic' => '1'], 'tourist'),
            $this->correction(['name' => 'Deux', 'uic' => '2'], 'tourist'),
        ]))->touristStations());
    }

    public function test_skipped_corrections_are_reported_with_their_exact_label(): void
    {
        $this->station('Double', '7');
        $this->station('Double', '7');
        $pk = $this->station('Pointee', '8');
        $line = Line::factory()->create(['code' => '123456']);
        DB::table('station_lines')->insert(['station_id' => $pk->id, 'line_id' => $line->id, 'method' => 'sncf_pk', 'pk_m' => 1000,
            'pk_source' => 'sncf', 'distance_m' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $broken = ['station' => [], 'action' => 'explode', 'reason' => ''];

        $result = (new StationCorrections([
            $this->correction(['name' => 'Cassee', 'uic' => '1'], 'explode'),
            $broken,
            $this->correction(['name' => 'Inconnue', 'uic' => '99'], 'tourist'),
            $this->correction(['name' => 'Double', 'uic' => '7'], 'tourist'),
            $this->correction(['name' => 'Pointee', 'uic' => '8'], 'pk', ['line' => '999999', 'pk_m' => 1]),
            $this->correction(['name' => 'Pointee', 'uic' => '8'], 'pk', ['line' => '123456', 'pk_m' => 2000]),
        ]))->apply();

        $this->assertSame([
            '#0 Cassee (explode) : action inconnue',
            '#1 ? (explode) : '.implode(', ', StationCorrections::validate($broken)),
            '#2 Inconnue (tourist) : gare introuvable',
            '#3 Double (tourist) : 2 fiches',
            '#4 Pointee (pk) : sans effet (événement visé absent ?)',
        ], $result['skipped']);
        $this->assertSame(1, $result['applied']);
        $this->assertSame(2000, (int) DB::table('station_lines')->where('station_id', $pk->id)->value('pk_m'));
    }

    public function test_an_event_carries_its_note_precision_reference_and_clipped_url(): void
    {
        $s = $this->station('Argentan', '5');
        $long = 'https://example.org/'.str_repeat('a', 600);
        $add = $this->correction(['name' => 'Argentan', 'uic' => '5'], 'add', [
            'type' => 'closure', 'date' => '1938-05-01', 'precision' => 'month', 'reason' => ' Raison ', 'quote' => 'Citation', 'source_url' => $long,
        ]);
        $plain = $this->correction(['name' => 'Argentan', 'uic' => '5'], 'add', ['type' => 'opening', 'date' => '1900-01-01', 'reason' => 'Seule ']);
        $huge = $this->correction(['name' => 'Argentan', 'uic' => '5'], 'add', ['type' => 'demolition', 'date' => '1950-01-01', 'reason' => str_repeat('é', 1500)]);

        $result = (new StationCorrections([$add, $plain, $huge]))->apply();

        $this->assertSame([], $result['skipped']);
        $this->assertSame(3, $result['applied']);
        $rows = DB::table('station_events')->where('station_id', $s->id)->get()->keyBy('type');
        $this->assertSame('Raison  : « Citation »', $rows['closure']->note);
        $this->assertSame('month', $rows['closure']->date_precision);
        $this->assertSame(substr($long, 0, 500), $rows['closure']->source_url);
        $this->assertSame('gares_corrections:'.substr(sha1(json_encode($add)), 0, 12), $rows['closure']->source_ref);
        $this->assertSame('Seule', $rows['opening']->note);
        $this->assertSame('day', $rows['opening']->date_precision);
        $this->assertSame(1000, mb_strlen($rows['demolition']->note));
        $this->assertStringEndsWith('…', $rows['demolition']->note);
        $this->assertSame(StationTimeline::CORRECTION, $rows['opening']->source);
    }

    public function test_an_identical_event_listed_twice_is_reported_without_effect(): void
    {
        $this->station('Argentan', '5');
        $add = $this->correction(['name' => 'Argentan', 'uic' => '5'], 'add', ['type' => 'closure', 'date' => '1938-05-05']);

        $result = (new StationCorrections([$add, $add]))->apply();

        $this->assertSame(1, $result['applied']);
        $this->assertSame(['#1 Argentan (add) : sans effet (événement visé absent ?)'], $result['skipped']);
    }

    public function test_a_position_already_in_place_still_counts_as_applied(): void
    {
        $s = $this->station('Fixe', '6', 3.5, 49.5);

        $result = (new StationCorrections([
            $this->correction(['name' => 'Fixe', 'uic' => '6'], 'position', ['lng' => '3.5', 'lat' => '49.5']),
        ]))->apply();

        $this->assertSame(1, $result['applied']);
        $this->assertSame([], $result['skipped']);

        $moved = (new StationCorrections([
            $this->correction(['name' => 'Fixe', 'uic' => '6'], 'position', ['lng' => '4.25', 'lat' => '50.5']),
        ]))->apply();
        $row = DB::table('stations')->where('id', $s->id)->selectRaw('ST_X(geom) as x, ST_Y(geom) as y')->first();
        $this->assertSame(1, $moved['applied']);
        $this->assertEqualsWithDelta(4.25, $row->x, 1e-9);
        $this->assertEqualsWithDelta(50.5, $row->y, 1e-9);
    }
}
