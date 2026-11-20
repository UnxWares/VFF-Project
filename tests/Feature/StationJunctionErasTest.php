<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Gare de bifurcation (vff:build-station-eras) : une gare est fermée à une ère
 * si TOUTES ses lignes y sont fermées ; une seule ligne encore ouverte la garde
 * ouverte, au niveau de la plus active de ses lignes.
 *
 * Chaque gare est au croisement d'une ligne est-ouest et d'une ligne nord-sud.
 */
class StationJunctionErasTest extends TestCase
{
    use RefreshDatabase;

    private Era $current;

    private Era $past;

    protected function setUp(): void
    {
        parent::setUp();
        $this->past = Era::factory()->create(['year' => 1960]);
        $this->current = Era::factory()->current()->create(['year' => 2030]);
    }

    /** @return array<string, array{0: array{0: string, 1: string}, 1: array{0: string, 1: string}, 2: string}> */
    public static function junctions(): array
    {
        return [
            'une ligne fermée, une ouverte : gare ouverte' => [['abandoned', 'unknown'], ['in_service', 'mixed'], 'active'],
            'deux lignes fermées : gare fermée' => [['abandoned', 'unknown'], ['dismantled', 'unknown'], 'declassified'],
            'une ligne fermée, une fret seul : fret seul' => [['abandoned', 'unknown'], ['in_service', 'freight'], 'no_commercial'],
            'une ligne fret seul, une mixte : ouverte' => [['in_service', 'freight'], ['in_service', 'mixed'], 'active'],
        ];
    }

    #[DataProvider('junctions')]
    public function test_station_is_closed_only_when_all_its_lines_are_closed(array $eastWest, array $northSouth, string $expected): void
    {
        $lat = 48.50;
        $a = $this->line('910000', [[2.00, $lat], [2.20, $lat]], ...$eastWest);
        $b = $this->line('911000', [[2.10, $lat - 0.1], [2.10, $lat + 0.1]], ...$northSouth);
        $station = $this->station('Gare de la Bifurcation', 2.10, $lat, 'active', [$a, $b]);

        $this->artisan('vff:build-station-eras', ['--no-stops' => true])->assertSuccessful();

        $this->assertSame($expected, $this->eraStatus($station, $this->current));
    }

    public function test_each_era_is_capped_by_the_lines_of_that_era(): void
    {
        $lat = 48.60;
        $a = $this->line('912000', [[2.00, $lat], [2.20, $lat]], 'abandoned', 'unknown', pastStatus: 'in_service');
        $b = $this->line('913000', [[2.10, $lat - 0.1], [2.10, $lat + 0.1]], 'abandoned', 'unknown', pastStatus: 'in_service');
        $station = $this->station('Gare de Charlie', 2.10, $lat, 'active', [$a, $b]);

        $this->artisan('vff:build-station-eras', ['--no-stops' => true])->assertSuccessful();

        $this->assertSame('active', $this->eraStatus($station, $this->past), '1960 : lignes en service');
        $this->assertSame('declassified', $this->eraStatus($station, $this->current), '2030 : toutes ses lignes sont fermées');
    }

    // ─── Fabrique ────────────────────────────────────────────────────────

    /** @param  list<array{0: float, 1: float}>  $points  [lon, lat] */
    private function line(string $code, array $points, string $status, string $traffic, ?string $pastStatus = null): Line
    {
        $line = Line::factory()->create(['code' => $code, 'name' => "Ligne {$code}", 'network' => 'principal']);
        $wkt = 'LINESTRING('.implode(', ', array_map(fn ($p) => sprintf('%F %F', $p[0], $p[1]), $points)).')';
        $eras = [[$this->current, $status, $traffic]];
        if ($pastStatus !== null) {
            $eras[] = [$this->past, $pastStatus, $pastStatus === 'in_service' ? 'mixed' : 'unknown'];
        }
        foreach ($eras as [$era, $s, $t]) {
            DB::table('line_segments')->insert(['line_id' => $line->id, 'era_id' => $era->id, 'status' => $s, 'traffic_type' => $t,
                'geom' => DB::raw("ST_GeomFromText('{$wkt}', 4326)"), 'created_at' => now(), 'updated_at' => now()]);
        }

        return $line;
    }

    /** @param  list<Line>  $lines */
    private function station(string $name, float $lon, float $lat, string $sourceStatus, array $lines): Station
    {
        $station = Station::factory()->create([
            'name' => $name, 'geom' => Point::makeGeodetic($lat, $lon), 'status' => $sourceStatus, 'source_status' => $sourceStatus,
            'position_source' => 'sncf', 'provenance' => ['status' => 'sncf'], 'opened_at' => '1880-01-01', 'closed_at' => null,
        ]);
        DB::table('station_events')->insert(['station_id' => $station->id, 'type' => 'opening', 'event_date' => '1880-01-01',
            'source' => 'wikidata', 'created_at' => now(), 'updated_at' => now()]);
        foreach ($lines as $line) {
            DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $line->id, 'method' => 'sncf_pk',
                'pk_m' => 1000, 'pk_source' => 'sncf', 'distance_m' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $station;
    }

    private function eraStatus(Station $station, Era $era): ?string
    {
        return DB::table('station_eras')->where('station_id', $station->id)->where('era_id', $era->id)->value('status');
    }
}
