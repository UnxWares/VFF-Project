<?php

namespace Tests\Feature\Api;

use App\Models\Line;
use App\Models\LineSegment;
use App\Models\Station;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;
use ZipArchive;

/**
 * GET /api/v1/eras/{year}/gtfs.zip : flux GTFS Static d'une ère (fichiers,
 * en-têtes CSV, valeurs, cohérence des identifiants entre fichiers).
 */
class GtfsExportTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
    }

    /**
     * Télécharge le zip et renvoie chaque fichier sous forme de lignes CSV.
     *
     * @return array<string, list<list<string>>>
     */
    private function feed(int $year): array
    {
        $response = $this->get("/api/v1/eras/{$year}/gtfs.zip")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/zip')
            ->assertDownload("vff-gtfs-{$year}.zip");

        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $path = $response->baseResponse->getFile()->getPathname();

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'zip illisible');
        $files = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $lines = array_values(array_filter(explode("\n", (string) $zip->getFromName($name)), fn ($l) => $l !== ''));
            $files[$name] = array_map(fn ($l) => str_getcsv($l, ',', '"', '\\'), $lines);
        }
        $zip->close();
        @unlink($path);

        return $files;
    }

    public function test_feed_contains_every_required_file_with_gtfs_headers(): void
    {
        $feed = $this->feed(2030);

        $expected = [
            'agency.txt' => ['agency_id', 'agency_name', 'agency_url', 'agency_timezone', 'agency_lang'],
            'routes.txt' => ['route_id', 'agency_id', 'route_short_name', 'route_long_name', 'route_type'],
            'stops.txt' => ['stop_id', 'stop_name', 'stop_lat', 'stop_lon', 'location_type'],
            'trips.txt' => ['route_id', 'service_id', 'trip_id', 'shape_id', 'trip_headsign'],
            'stop_times.txt' => ['trip_id', 'arrival_time', 'departure_time', 'stop_id', 'stop_sequence', 'shape_dist_traveled'],
            'shapes.txt' => ['shape_id', 'shape_pt_lat', 'shape_pt_lon', 'shape_pt_sequence', 'shape_dist_traveled'],
            'calendar.txt' => ['service_id', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday', 'start_date', 'end_date'],
            'feed_info.txt' => ['feed_publisher_name', 'feed_publisher_url', 'feed_lang', 'feed_start_date', 'feed_end_date', 'feed_version'],
        ];
        $this->assertEqualsCanonicalizing(array_keys($expected), array_keys($feed));
        foreach ($expected as $file => $header) {
            $this->assertSame($header, $feed[$file][0], "en-tête de {$file}");
        }
    }

    public function test_feed_values_for_the_current_era(): void
    {
        $feed = $this->feed(2030);
        $l = $this->line->id;

        $this->assertSame([['vff', 'VFF - Voies Ferrées de France', 'https://vff-project.org', 'Europe/Paris', 'fr']],
            array_slice($feed['agency.txt'], 1));
        // Ligne voyageurs → route_type 2 (Rail).
        $this->assertSame([["L{$l}", 'vff', '999 000', 'Paris-Saint-Lazare – Versailles-Rive-Droite', '2']],
            array_slice($feed['routes.txt'], 1));

        $stops = array_slice($feed['stops.txt'], 1);
        usort($stops, fn ($a, $b) => strcmp($a[1], $b[1]));
        $this->assertSame(['S'.$this->origin->id, 'Paris-Saint-Lazare'], array_slice($stops[0], 0, 2));
        $this->assertEqualsWithDelta(48.8763, (float) $stops[0][2], 1e-6);
        $this->assertEqualsWithDelta(2.3253, (float) $stops[0][3], 1e-6);
        $this->assertSame('0', $stops[0][4]);

        $this->assertSame([["L{$l}", 'service-2030', "T{$l}", "shape-{$l}", 'Versailles-Rive-Droite']],
            array_slice($feed['trips.txt'], 1));

        // Horaires synthétiques : position N → 08:0N, séquence 1-based, PK = shape_dist_traveled.
        $this->assertSame([
            ["T{$l}", '08:01:00', '08:01:00', 'S'.$this->origin->id, '2', '0'],
            ["T{$l}", '08:02:00', '08:02:00', 'S'.$this->destination->id, '3', '17250'],
        ], array_slice($feed['stop_times.txt'], 1));

        $shape = array_slice($feed['shapes.txt'], 1);
        $this->assertCount(3, $shape, '3 sommets du tracé');
        $this->assertSame(['1', '2', '3'], array_column($shape, 3));
        $this->assertSame('0', $shape[0][4]);
        $this->assertGreaterThan((int) $shape[1][4], (int) $shape[2][4], 'distance cumulée croissante');
        $this->assertEqualsWithDelta(15_400, (int) $shape[2][4], 600);

        $this->assertSame([['service-2030', '1', '1', '1', '1', '1', '1', '1', '20300101', '20301231']],
            array_slice($feed['calendar.txt'], 1));
        $info = $feed['feed_info.txt'][1];
        $this->assertSame(['VFF - Voies Ferrées de France', 'https://vff-project.org', 'fr', '20300101', '20301231'], array_slice($info, 0, 5));
        $this->assertSame('vff-'.now()->format('Y-m-d'), $info[5]);
    }

    public function test_every_trip_references_an_existing_route_and_shape(): void
    {
        // Ligne dont toutes les gares sont fermées à l'ère, et ligne sans tracé en service.
        $closed = Line::factory()->create(['name' => 'Ligne fermée', 'type' => 'passenger']);
        $noTrace = Line::factory()->create(['name' => 'Ligne sans tracé', 'type' => 'freight']);
        LineSegment::factory()->create([
            'line_id' => $noTrace->id, 'era_id' => $this->currentEra->id, 'status' => 'abandoned',
            'geom' => LineString::make([Point::makeGeodetic(47.0, 1.0), Point::makeGeodetic(47.1, 1.1)]),
        ]);
        $a = Station::factory()->create(['geom' => Point::makeGeodetic(47.0, 1.0)]);
        $b = Station::factory()->create(['geom' => Point::makeGeodetic(47.1, 1.1)]);
        foreach ([[$closed, 'declassified'], [$noTrace, 'no_commercial']] as [$line, $status]) {
            foreach ([$a, $b] as $i => $station) {
                DB::table('line_stops')->insert([
                    'line_id' => $line->id, 'era_id' => $this->currentEra->id, 'station_id' => $station->id,
                    'position' => $i + 1, 'pk_m' => $i * 1000, 'effective_status' => $status, 'is_terminal' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        $feed = $this->feed(2030);

        $routes = array_column(array_slice($feed['routes.txt'], 1), 0);
        $trips = array_slice($feed['trips.txt'], 1);
        $shapes = array_unique(array_column(array_slice($feed['shapes.txt'], 1), 0));
        $tripIds = array_column($trips, 2);
        $stopIds = array_column(array_slice($feed['stops.txt'], 1), 0);

        $this->assertEqualsCanonicalizing(["L{$this->line->id}", "L{$noTrace->id}"], $routes, 'gares toutes fermées : pas de route');
        // Ligne fret seul → route_type 100.
        $this->assertContains(["L{$noTrace->id}", 'vff', $noTrace->code, 'Ligne sans tracé', '100'], array_slice($feed['routes.txt'], 1));

        $byTrip = array_column($trips, 3, 2);
        $this->assertSame("shape-{$this->line->id}", $byTrip["T{$this->line->id}"]);
        $this->assertSame('', $byTrip["T{$noTrace->id}"], 'pas de tracé en service : shape_id vide');
        $this->assertArrayNotHasKey("T{$closed->id}", $byTrip);

        foreach ($trips as $trip) {
            $this->assertContains($trip[0], $routes, "trip {$trip[2]} : route_id inconnue");
            if ($trip[3] !== '') {
                $this->assertContains($trip[3], $shapes, "trip {$trip[2]} : shape_id sans points");
            }
        }
        foreach (array_slice($feed['stop_times.txt'], 1) as $st) {
            $this->assertContains($st[0], $tripIds, 'stop_time vers un trip absent');
            $this->assertContains($st[3], $stopIds, 'stop_time vers un arrêt absent');
        }
    }

    public function test_era_without_stops_gives_header_only_files(): void
    {
        $feed = $this->feed(1960);

        foreach (['routes.txt', 'stops.txt', 'trips.txt', 'stop_times.txt', 'shapes.txt'] as $file) {
            $this->assertCount(1, $feed[$file], "{$file} : en-tête seul");
        }
        $this->assertSame('service-1960', $feed['calendar.txt'][1][0]);
    }

    public function test_unknown_era_is_404(): void
    {
        $this->get('/api/v1/eras/1850/gtfs.zip')->assertNotFound();
    }
}
