<?php

namespace Tests\Feature;

use App\Models\Accident;
use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use App\Services\NetworkFigures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cas limites de « Le réseau en chiffres » : arrondis au kilomètre sur la somme, statuts comptés, trafic,
 * kilomètres retirés et mis en service entre deux ères, frontières des périodes (31 décembre, 1er janvier),
 * dates hors des ères, accidents sans bilan, voies principales, vagues.
 */
class NetworkFiguresBoundaryTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, Era> */
    private array $eras = [];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->eras[1900] = Era::factory()->create(['year' => 1900]);
        $this->eras[1910] = Era::factory()->create(['year' => 1910]);
        $this->eras[2030] = Era::factory()->current()->create(['year' => 2030]);
    }

    private function segment(Line $line, int $eraYear, string $status, int $meters, string $traffic = 'passenger', bool $electrified = false): void
    {
        DB::table('line_segments')->insert([
            'line_id' => $line->id, 'era_id' => $this->eras[$eraYear]->id, 'status' => $status, 'traffic_type' => $traffic,
            'length_meters' => $meters, 'electrified' => $electrified, 'source' => 'osm',
            'geom' => DB::raw("ST_GeomFromText('LINESTRING(2.0 48.0, 2.1 48.0)', 4326)"), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function event(Line $line, string $type, string $date, string $source = 'wikipedia', string $precision = 'day'): void
    {
        DB::table('line_events')->insert(['line_id' => $line->id, 'type' => $type, 'event_date' => $date, 'description' => "{$type} {$date}",
            'source' => $source, 'confidence' => 'medium', 'date_precision' => $precision, 'section_resolution' => 'unresolved',
            'created_at' => now(), 'updated_at' => now()]);
    }

    /** @return array<string, mixed> */
    private function row(int $year): array
    {
        $figures = app(NetworkFigures::class)->compute();
        foreach ($figures['decades'] as $row) {
            if ($row['year'] === $year) {
                return $row;
            }
        }
        $this->fail("Pas de ligne pour {$year}");
    }

    public function test_without_any_era_everything_is_empty_and_the_figures_are_well_formed(): void
    {
        DB::table('eras')->delete();

        $figures = app(NetworkFigures::class)->compute();

        $this->assertSame([], $figures['decades']);
        $this->assertSame(['closures' => [], 'reopenings' => []], $figures['waves']);
    }

    public function test_the_eras_come_oldest_first_with_the_period_they_close(): void
    {
        $figures = app(NetworkFigures::class)->compute();

        $this->assertSame([1900, 1910, 2030], array_column($figures['decades'], 'year'));
        $this->assertSame([null, 1901, 1911], array_column($figures['decades'], 'from_year'));
        $this->assertSame([false, false, true], array_column($figures['decades'], 'current'));
    }

    public function test_an_empty_era_has_zeros_and_a_null_electrified_share(): void
    {
        $row = $this->row(1910);

        $this->assertSame(0, $row['open_km']);
        $this->assertNull($row['electrified_share']);
        $this->assertSame(['active' => 0, 'tourist' => 0, 'no_commercial' => 0, 'declassified' => 0], $row['stations']);
        $this->assertSame(['count' => 0, 'deaths' => 0, 'deaths_known' => 0], $row['accidents']);
        $this->assertSame(0, array_sum($row['events']));
    }

    public function test_lengths_are_summed_then_rounded_to_the_kilometre(): void
    {
        $line = Line::factory()->create();
        foreach ([400, 400, 400] as $m) {
            $this->segment($line, 1900, 'in_service', $m);
        }
        $this->segment($line, 1910, 'in_service', 1499);
        $this->segment($line, 2030, 'in_service', 1500);

        $this->assertSame(1, $this->row(1900)['open_km']);
        $this->assertSame(1, $this->row(1910)['open_km']);
        $this->assertSame(2, $this->row(2030)['open_km']);
    }

    public function test_every_status_goes_to_its_own_total(): void
    {
        $line = Line::factory()->create();
        foreach (['in_service' => 1000, 'single_track' => 2000, 'abandoned' => 4000, 'service_track' => 8000, 'dismantled' => 16000, 'razed' => 32000, 'construction' => 64000] as $status => $m) {
            $this->segment($line, 2030, $status, $m);
        }

        $row = $this->row(2030);

        $this->assertSame(3, $row['open_km']);
        $this->assertSame(12, $row['closed_km']);
        $this->assertSame(48, $row['gone_km']);
    }

    public function test_traffic_types_split_the_open_length_and_unknown_catches_the_rest(): void
    {
        $line = Line::factory()->create();
        $this->segment($line, 2030, 'in_service', 1000, 'passenger');
        $this->segment($line, 2030, 'single_track', 2000, 'mixed');
        $this->segment($line, 2030, 'in_service', 4000, 'freight');
        $this->segment($line, 2030, 'in_service', 8000, 'unknown');
        $this->segment($line, 2030, 'abandoned', 16000, 'freight');

        $row = $this->row(2030);

        $this->assertSame([15, 3, 4, 8], [$row['open_km'], $row['open_passenger_km'], $row['open_freight_km'], $row['open_unknown_km']]);
    }

    public function test_the_electrified_share_counts_open_electrified_segments_only(): void
    {
        $line = Line::factory()->create();
        $this->segment($line, 2030, 'in_service', 3000, 'passenger', true);
        $this->segment($line, 2030, 'in_service', 1000, 'passenger', false);
        $this->segment($line, 2030, 'abandoned', 5000, 'passenger', true);

        $row = $this->row(2030);

        $this->assertSame(3, $row['electrified_km']);
        $this->assertSame(0.75, $row['electrified_share']);

        $this->segment($line, 1910, 'in_service', 3000, 'passenger', false);
        $this->segment($line, 1910, 'in_service', 1000, 'passenger', true);
        $this->assertSame(0.25, $this->row(1910)['electrified_share']);
        $this->segment($line, 1900, 'in_service', 2000, 'passenger', true);
        $this->segment($line, 1900, 'in_service', 4000, 'passenger', false);
        $this->assertSame(0.3333, $this->row(1900)['electrified_share']);
    }

    public function test_a_segment_without_a_stored_length_uses_its_geographic_length(): void
    {
        $line = Line::factory()->create();
        DB::table('line_segments')->insert([
            'line_id' => $line->id, 'era_id' => $this->eras[2030]->id, 'status' => 'in_service', 'traffic_type' => 'passenger',
            'length_meters' => null, 'electrified' => false, 'source' => 'osm',
            'geom' => DB::raw("ST_GeomFromText('LINESTRING(2.0 48.0, 2.1 48.0)', 4326)"), 'created_at' => now(), 'updated_at' => now(),
        ]);

        // 0,1 degré de longitude à 48 degrés nord : environ 7,4 km.
        $this->assertEqualsWithDelta(7, $this->row(2030)['open_km'], 1);
    }

    public function test_removed_and_added_kilometres_are_per_line_between_eras(): void
    {
        $a = Line::factory()->create();
        $b = Line::factory()->create();
        $c = Line::factory()->create();
        $this->segment($a, 1900, 'in_service', 10000);
        $this->segment($a, 1910, 'in_service', 4000);
        $this->segment($a, 2030, 'in_service', 9000);
        $this->segment($b, 1910, 'in_service', 7000);
        $this->segment($b, 2030, 'abandoned', 7000);
        $this->segment($c, 2030, 'in_service', 2000);

        $first = $this->row(1900);
        $second = $this->row(1910);
        $third = $this->row(2030);

        // Première ère : tout ce qui est ouvert compte comme mis en service, rien n'est retiré.
        $this->assertSame([0, 10], [$first['removed_km'], $first['added_km']]);
        // A perd 6 km, B gagne 7 km : jamais compensés.
        $this->assertSame([6, 7], [$second['removed_km'], $second['added_km']]);
        // A regagne 5 km, B perd ses 7 km, C naît avec 2 km.
        $this->assertSame([7, 7], [$third['removed_km'], $third['added_km']]);
    }

    public function test_a_line_absent_from_an_era_counts_as_zero_open_there(): void
    {
        $a = Line::factory()->create();
        $this->segment($a, 1900, 'in_service', 5000);
        $this->segment($a, 2030, 'in_service', 5000);

        // Aucun segment en 1910 : la ligne n'a pas de ligne dans « per » pour cette ère, donc pas de mouvement en 1910.
        $this->assertSame([0, 0], [$this->row(1910)['removed_km'], $this->row(1910)['added_km']]);
    }

    public function test_events_on_the_31_december_and_the_1_january_fall_in_different_periods(): void
    {
        $line = Line::factory()->create();
        $this->event($line, 'closure', '1900-12-31');
        $this->event($line, 'closure', '1901-01-01');
        $this->event($line, 'closure', '1910-12-31');
        // 1911 appartient à la période de l'ère 1920, que ce jeu d'ères n'a pas : jamais rangé dans une autre.
        $this->event($line, 'closure', '1911-01-01');

        $this->assertSame(1, $this->row(1900)['events']['closed']);
        $this->assertSame(2, $this->row(1910)['events']['closed']);
        $this->assertSame(0, $this->row(2030)['events']['closed']);
    }

    public function test_dates_before_the_first_era_and_after_the_last_are_clamped(): void
    {
        $line = Line::factory()->create();
        $this->event($line, 'opening', '1832-05-01');
        $this->event($line, 'opening', '2025-05-01');
        $this->event($line, 'opening', '2045-05-01');

        $this->assertSame(1, $this->row(1900)['events']['opened']);
        $this->assertSame(2, $this->row(2030)['events']['opened']);
    }

    public function test_event_groups_and_the_dont_columns(): void
    {
        $line = Line::factory()->create();
        foreach (['opening', 'opening_passenger', 'opening_freight', 'reopening', 'reopening_passenger', 'reopening_freight', 'closure', 'closure_passenger',
            'closure_freight', 'dismantling', 'speed_change', 'electrification'] as $type) {
            $this->event($line, $type, '1905-06-01');
        }

        $events = $this->row(1910)['events'];

        $this->assertSame(['opened' => 3, 'reopened' => 3, 'closed' => 3, 'closed_passenger' => 1, 'closed_freight' => 1, 'dismantled' => 1], $events);
    }

    public function test_year_only_dates_of_the_trace_source_are_ignored_but_other_sources_and_precise_dates_are_counted(): void
    {
        $line = Line::factory()->create();
        $this->event($line, 'closure', '1905-01-01', 'archeoferroviaire', 'year');
        $this->event($line, 'closure', '1905-03-04', 'archeoferroviaire', 'day');
        $this->event($line, 'closure', '1905-01-01', 'archeoferroviaire', 'month');
        $this->event($line, 'closure', '1905-01-01', 'wikipedia', 'year');

        $this->assertSame(3, $this->row(1910)['events']['closed']);
    }

    public function test_accidents_in_the_boundary_years_and_with_unknown_deaths(): void
    {
        Accident::factory()->create(['slug' => '1900-12-31-a', 'occurred_on' => '1900-12-31', 'deaths' => 0]);
        Accident::factory()->create(['slug' => '1901-01-01-b', 'occurred_on' => '1901-01-01', 'deaths' => null]);
        Accident::factory()->create(['slug' => '1901-01-02-c', 'occurred_on' => '1901-01-02', 'deaths' => 12]);
        Accident::factory()->create(['slug' => '1850-01-02-d', 'occurred_on' => '1850-01-02', 'deaths' => 1]);
        Accident::factory()->create(['slug' => '2040-01-02-e', 'occurred_on' => '2040-01-02', 'deaths' => 5]);

        $this->assertSame(['count' => 2, 'deaths' => 1, 'deaths_known' => 2], $this->row(1900)['accidents']);
        $this->assertSame(['count' => 2, 'deaths' => 12, 'deaths_known' => 1], $this->row(1910)['accidents']);
        $this->assertSame(['count' => 1, 'deaths' => 5, 'deaths_known' => 1], $this->row(2030)['accidents']);
    }

    public function test_stations_are_counted_by_status_per_era_and_unknown_statuses_are_ignored(): void
    {
        foreach (['active', 'active', 'tourist', 'declassified', 'no_commercial'] as $i => $status) {
            $station = Station::factory()->create(['slug' => "s-{$i}"]);
            DB::table('station_eras')->insert(['station_id' => $station->id, 'era_id' => $this->eras[1910]->id, 'status' => $status, 'created_at' => now(), 'updated_at' => now()]);
        }
        $other = Station::factory()->create(['slug' => 's-x']);
        DB::table('station_eras')->insert(['station_id' => $other->id, 'era_id' => $this->eras[1910]->id, 'status' => 'closed', 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame(['active' => 2, 'tourist' => 1, 'no_commercial' => 1, 'declassified' => 1], $this->row(1910)['stations']);
        $this->assertSame(0, array_sum($this->row(1900)['stations']));
    }

    public function test_track_regime_is_null_without_double_or_single_and_ignores_empty_or_inverted_portions(): void
    {
        $line = Line::factory()->create();
        $base = ['line_id' => $line->id, 'code_ligne' => '999000', 'source' => 'sncf', 'created_at' => now(), 'updated_at' => now()];
        DB::table('line_track_portions')->insert([
            $base + ['pk_start_m' => 0, 'pk_end_m' => 5000, 'regime' => 'x', 'tracks' => null],
            $base + ['pk_start_m' => 5000, 'pk_end_m' => 5000, 'regime' => 'x', 'tracks' => 2],
            $base + ['pk_start_m' => 9000, 'pk_end_m' => 8000, 'regime' => 'x', 'tracks' => 2],
        ]);
        $this->assertNull(app(NetworkFigures::class)->compute()['tracks']);

        DB::table('line_track_portions')->insert($base + ['pk_start_m' => 0, 'pk_end_m' => 1000, 'regime' => 'x', 'tracks' => 2]);
        DB::table('line_track_portions')->insert($base + ['pk_start_m' => 1000, 'pk_end_m' => 3000, 'regime' => 'x', 'tracks' => 1]);

        $this->assertSame(['double_km' => 1, 'single_km' => 2, 'unspecified_km' => 5, 'double_share' => 0.3333], app(NetworkFigures::class)->compute()['tracks']);
    }

    public function test_the_waves_keep_the_largest_withdrawals_and_the_most_reopenings(): void
    {
        $line = Line::factory()->create();
        $this->segment($line, 1900, 'in_service', 20000);
        $this->segment($line, 1910, 'in_service', 15000);
        $this->segment($line, 2030, 'in_service', 14000);
        $this->event($line, 'reopening', '1905-01-01');
        $this->event($line, 'reopening', '1906-01-01');
        $this->event($line, 'reopening', '2021-01-01');
        $this->event($line, 'closure', '1905-02-02');

        $waves = app(NetworkFigures::class)->compute()['waves'];

        $this->assertSame([['year' => 1910, 'removed_km' => 5, 'closed_events' => 1], ['year' => 2030, 'removed_km' => 1, 'closed_events' => 0]], $waves['closures']);
        $this->assertSame([['year' => 1910, 'reopened_events' => 2, 'added_km' => 0], ['year' => 2030, 'reopened_events' => 1, 'added_km' => 0]], $waves['reopenings']);
    }

    public function test_at_most_three_waves_of_each_kind_in_decreasing_order(): void
    {
        $this->eras[1920] = Era::factory()->create(['year' => 1920]);
        $this->eras[1930] = Era::factory()->create(['year' => 1930]);
        $line = Line::factory()->create();
        foreach ([1900 => 9000, 1910 => 8000, 1920 => 6000, 1930 => 3000, 2030 => 1000] as $year => $meters) {
            $this->segment($line, $year, 'in_service', $meters);
        }
        foreach (['1905-01-01' => 1, '1915-01-01' => 2, '1925-01-01' => 3, '2025-01-01' => 4] as $date => $n) {
            for ($i = 0; $i < $n; $i++) {
                $this->event($line, 'reopening', $date);
            }
        }

        $waves = app(NetworkFigures::class)->compute()['waves'];

        // Retraits : 1910 perd 1 km, 1920 en perd 2, 1930 en perd 3, 2030 en perd 2 (à égalité, l'ère la plus ancienne d'abord).
        $this->assertSame([1930, 1920, 2030], array_column($waves['closures'], 'year'));
        $this->assertSame([2030, 1930, 1920], array_column($waves['reopenings'], 'year'));
        $this->assertSame([4, 3, 2], array_column($waves['reopenings'], 'reopened_events'));
    }

    public function test_get_serves_the_cache_and_refresh_replaces_it(): void
    {
        $figures = app(NetworkFigures::class);
        Cache::forever(NetworkFigures::CACHE_KEY, ['computed_at' => 'cached', 'decades' => [], 'tracks' => null, 'waves' => []]);

        $this->assertSame('cached', $figures->get()['computed_at']);
        $this->assertNotSame('cached', $figures->refresh()['computed_at']);
        $this->assertNotSame('cached', $figures->get()['computed_at']);
        Cache::forever(NetworkFigures::CACHE_KEY, 'broken');
        $this->assertIsArray($figures->get());
        $this->assertSame([1900, 1910, 2030], array_column($figures->get()['decades'], 'year'));
    }
}
