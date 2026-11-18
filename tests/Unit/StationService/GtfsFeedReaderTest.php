<?php

namespace Tests\Unit\StationService;

use App\Enums\TransitMode;
use App\Services\StationService\FeedDefinition;
use App\Services\StationService\FeedSnapshot;
use App\Services\StationService\GtfsArchive;
use App\Services\StationService\GtfsFeedReader;
use App\Services\StationService\GtfsStop;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * Lecture d'un flux GTFS (tests/Fixtures/Gtfs/mini : trente jours, du 16/11 au 15/12/2026) :
 * une règle de GtfsFeedReader par test.
 */
class GtfsFeedReaderTest extends TestCase
{
    private const DIR = __DIR__.'/../../Fixtures/Gtfs/mini';

    /** @param  array<string, mixed>  $overrides */
    private static function feed(array $overrides = []): FeedDefinition
    {
        return FeedDefinition::fromArray($overrides + [
            'key' => 'test', 'name' => 'Test', 'provider' => 'sncf', 'licence' => 'ODbL', 'page' => 'https://example.org',
            'url' => 'https://example.org/gtfs.zip', 'modes' => ['rail', 'bus'], 'bus_stop_pattern' => '/^StopPoint:OCECar/',
            'bus_trip_pattern' => '#/\s*car\b#i', 'uic_fields' => ['parent_station', 'stop_id'], 'uic_pattern' => '/OCE(?:[^-]*-)?([0-9]{8})$/',
        ]);
    }

    private static function read(?FeedDefinition $feed = null, ?int $from = null, ?int $to = null, string $path = self::DIR): FeedSnapshot
    {
        return (new GtfsFeedReader)->read(new GtfsArchive($path), $feed ?? self::feed(), $from, $to);
    }

    private static function stop(FeedSnapshot $snapshot, string $key): ?GtfsStop
    {
        foreach ($snapshot->stops as $stop) {
            if ($stop->key === $key) {
                return $stop;
            }
        }

        return null;
    }

    public function test_the_horizon_comes_from_feed_info_and_is_the_default_window(): void
    {
        $snapshot = self::read();

        $this->assertSame([20261116, 20261215, 20261116, 20261215],
            [$snapshot->horizonStart, $snapshot->horizonEnd, $snapshot->windowStart, $snapshot->windowEnd]);
    }

    public function test_a_daily_train_counts_one_call_per_running_day_minus_removed_dates(): void
    {
        $alpha = self::stop(self::read(), 'StopArea:OCE87000018')?->service(TransitMode::Rail);

        $this->assertNotNull($alpha);
        $this->assertSame(29, $alpha->calls);
        $this->assertArrayNotHasKey(20261125, $alpha->days);
        $this->assertSame(['TER1 Alpha - Bravo'], $alpha->routes);
    }

    public function test_added_dates_are_counted_on_top_of_the_weekly_calendar(): void
    {
        $bravo = self::stop(self::read(), 'StopArea:OCE87000026')?->service(TransitMode::Rail);

        $this->assertSame(30, $bravo?->calls);
        $this->assertSame(2, $bravo->days[20261201]);
    }

    public function test_a_call_without_pickup_nor_drop_off_is_not_a_service(): void
    {
        $charlie = self::stop(self::read(), 'S3');

        $this->assertNull($charlie?->service(TransitMode::Rail));
        $this->assertSame(29, $charlie?->service(TransitMode::Bus)?->calls);
    }

    public function test_coach_stop_points_and_coach_headsigns_inside_train_lines_count_as_coaches(): void
    {
        $alpha = self::stop(self::read(), 'StopArea:OCE87000018');

        // t2 (« Alpha /car ») et t3 (arrêt « OCECar ») : deux cars par jour.
        $this->assertSame(58, $alpha?->service(TransitMode::Bus)?->calls);
        $this->assertSame(29, $alpha->service(TransitMode::Rail)?->calls);
    }

    public function test_without_coach_patterns_the_whole_train_line_is_rail(): void
    {
        $alpha = self::stop(self::read(self::feed(['bus_trip_pattern' => null, 'bus_stop_pattern' => null])), 'StopArea:OCE87000018');

        $this->assertSame(58, $alpha?->service(TransitMode::Rail)?->calls);
        $this->assertSame(29, $alpha->service(TransitMode::Bus)?->calls);
    }

    public function test_coach_trips_are_dropped_when_the_feed_keeps_trains_only(): void
    {
        $snapshot = self::read(self::feed(['modes' => ['rail']]));

        $this->assertNull(self::stop($snapshot, 'S3'), 'Charlie : cars seulement');
        $this->assertNull(self::stop($snapshot, 'StopArea:OCE87000018')?->service(TransitMode::Bus));
    }

    public function test_a_zone_takes_the_name_position_and_uic_of_its_parent_station(): void
    {
        $alpha = self::stop(self::read(), 'StopArea:OCE87000018');

        $this->assertSame(['Alpha', 48.0, 2.0, '87000018'], [$alpha?->name, $alpha?->lat, $alpha?->lon, $alpha?->uic]);
        $this->assertNull(self::stop(self::read(), 'S3')?->uic);
    }

    public function test_tram_routes_are_filtered_by_short_name_and_a_zero_position_is_unknown(): void
    {
        $snapshot = self::read(self::feed(['modes' => ['tram'], 'route_pattern' => '/^T4$/']));
        $delta = self::stop($snapshot, 'S4');

        $this->assertCount(1, $snapshot->stops, 'le tramway urbain T2 est écarté');
        $this->assertSame(8, $delta?->service(TransitMode::Tram)?->calls, 'huit samedis et dimanches');
        $this->assertNull($delta->lat);
    }

    public function test_tram_trains_count_as_trains_when_the_feed_says_so(): void
    {
        $snapshot = self::read(self::feed(['modes' => ['rail'], 'tram_is_rail' => true, 'route_pattern' => '/^T4$/']));

        $this->assertSame(8, self::stop($snapshot, 'S4')?->service(TransitMode::Rail)?->calls);
    }

    public function test_the_window_limits_the_counted_days(): void
    {
        $snapshot = self::read(null, 20261201, 20261207);

        $this->assertSame([20261201, 20261207], [$snapshot->windowStart, $snapshot->windowEnd]);
        $this->assertSame(7, self::stop($snapshot, 'StopArea:OCE87000018')?->service(TransitMode::Rail)?->calls);
    }

    public function test_a_feed_finished_before_the_requested_day_is_read_over_its_whole_horizon(): void
    {
        $snapshot = self::read(null, 20270115);

        $this->assertTrue($snapshot->isStaleOn(20270115));
        $this->assertSame([20261116, 20261215], [$snapshot->windowStart, $snapshot->windowEnd]);
    }

    public function test_a_zip_archive_reads_like_the_directory(): void
    {
        $zip = sys_get_temp_dir().'/vff-gtfs-'.bin2hex(random_bytes(4)).'.zip';
        $archive = new ZipArchive;
        $archive->open($zip, ZipArchive::CREATE);
        foreach (glob(self::DIR.'/*.txt') ?: [] as $file) {
            $archive->addFile($file, 'gtfs/'.basename($file));
        }
        $archive->close();

        try {
            $this->assertEquals(self::read(), self::read(path: $zip));
        } finally {
            unlink($zip);
        }
    }
}
