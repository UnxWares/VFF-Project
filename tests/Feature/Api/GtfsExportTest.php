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
            'feed_info.txt' => ['feed_publisher_name', 'feed_publisher_url', 'feed_lang', 'feed_start_date', 'feed_end_date', 'feed_version', 'feed_contact_email', 'feed_contact_url'],
            'attributions.txt' => ['attribution_id', 'organization_name', 'is_producer', 'is_operator', 'is_authority', 'attribution_url'],
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

        // Séquence 1-based ; distance des arrêts mesurée le long de la forme (pas le PK), horaires synthétiques à 60 km/h.
        $stopTimes = array_slice($feed['stop_times.txt'], 1);
        $this->assertCount(2, $stopTimes);
        $this->assertSame(["T{$l}", 'S'.$this->origin->id, '2'], [$stopTimes[0][0], $stopTimes[0][3], $stopTimes[0][4]]);
        $this->assertSame(["T{$l}", 'S'.$this->destination->id, '3'], [$stopTimes[1][0], $stopTimes[1][3], $stopTimes[1][4]]);
        $this->assertSame('08:00:00', $stopTimes[0][1]);
        $this->assertSame('0', $stopTimes[0][5]);
        $this->assertEqualsWithDelta((float) end($feed['shapes.txt'])[4], (float) $stopTimes[1][5], 700);
        $this->assertEqualsWithDelta((float) $stopTimes[1][5] / 1000 * 3600 / 60, $this->seconds($stopTimes[1][1]) - 8 * 3600, 1.0);

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
        $this->assertSame(['contact@vff-project.org', 'https://vff-project.org/legal'], array_slice($info, 6, 2), 'contact du flux');

        // Attributions : licence ODbL des sources.
        $attributions = implode("\n", array_column(array_slice($feed['attributions.txt'], 1), 1));
        $this->assertStringContainsString('OpenStreetMap', $attributions);
        $this->assertStringContainsString('ODbL', $attributions);
        $this->assertStringContainsString('SNCF', $attributions);
    }

    private function seconds(string $time): int
    {
        [$h, $m, $s] = array_map('intval', explode(':', $time));

        return $h * 3600 + $m * 60 + $s;
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

    public function test_feed_has_the_minimal_gtfs_structure_on_a_test_network(): void
    {
        // Réseau d'essai : la ligne de base, une ligne d'un seul arrêt (pas de trajet) et une ligne dont les arrêts
        // sont à l'envers du tracé.
        $single = Line::factory()->create(['name' => 'Ligne à un arrêt', 'type' => 'passenger']);
        $stray = Station::factory()->create(['geom' => Point::makeGeodetic(46.0, 1.0)]);
        DB::table('line_stops')->insert([
            'line_id' => $single->id, 'era_id' => $this->currentEra->id, 'station_id' => $stray->id,
            'position' => 1, 'pk_m' => 0, 'effective_status' => 'active', 'is_terminal' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $feed = $this->feed(2030);

        // Fichiers obligatoires (agency, routes, trips, stop_times, stops, calendar) et facultatifs déclarés.
        foreach (['agency.txt', 'routes.txt', 'trips.txt', 'stop_times.txt', 'stops.txt', 'calendar.txt', 'feed_info.txt', 'shapes.txt', 'attributions.txt'] as $file) {
            $this->assertArrayHasKey($file, $feed, "{$file} absent");
        }

        // Chaque ligne a autant de colonnes que l'en-tête, aucune colonne d'en-tête en double.
        $tables = [];
        foreach ($feed as $file => $rows) {
            $header = $rows[0];
            $this->assertSame(count($header), count(array_unique($header)), "{$file} : colonne en double");
            foreach (array_slice($rows, 1) as $i => $row) {
                $this->assertCount(count($header), $row, "{$file} ligne ".($i + 2));
            }
            $tables[$file] = array_map(fn ($row) => array_combine($header, $row), array_slice($rows, 1));
        }

        // Identifiants uniques.
        $unique = fn (string $file, array $keys) => $this->assertSame(
            count($tables[$file]),
            count(array_unique(array_map(fn ($r) => implode('|', array_map(fn ($k) => $r[$k], $keys)), $tables[$file]))),
            "{$file} : clé ".implode('+', $keys).' en double',
        );
        $unique('agency.txt', ['agency_id']);
        $unique('routes.txt', ['route_id']);
        $unique('stops.txt', ['stop_id']);
        $unique('trips.txt', ['trip_id']);
        $unique('calendar.txt', ['service_id']);
        $unique('stop_times.txt', ['trip_id', 'stop_sequence']);
        $unique('shapes.txt', ['shape_id', 'shape_pt_sequence']);
        $unique('attributions.txt', ['attribution_id']);

        // Références croisées.
        $agencies = array_column($tables['agency.txt'], 'agency_id');
        $routes = array_column($tables['routes.txt'], 'route_id');
        $stops = array_column($tables['stops.txt'], 'stop_id');
        $services = array_column($tables['calendar.txt'], 'service_id');
        $shapeIds = array_column($tables['shapes.txt'], 'shape_id');
        $trips = array_column($tables['trips.txt'], 'trip_id');
        foreach ($tables['routes.txt'] as $r) {
            $this->assertContains($r['agency_id'], $agencies);
            $this->assertNotSame('', $r['route_short_name'].$r['route_long_name'], 'route sans nom');
        }
        foreach ($tables['trips.txt'] as $t) {
            $this->assertContains($t['route_id'], $routes);
            $this->assertContains($t['service_id'], $services);
            if ($t['shape_id'] !== '') {
                $this->assertContains($t['shape_id'], $shapeIds);
            }
        }
        foreach ($tables['stop_times.txt'] as $st) {
            $this->assertContains($st['trip_id'], $trips);
            $this->assertContains($st['stop_id'], $stops);
        }
        // Un trajet a au moins deux arrêts ; l'arrêt, la route et le trajet d'une ligne à un seul arrêt n'existent pas.
        $perTrip = array_count_values(array_column($tables['stop_times.txt'], 'trip_id'));
        foreach ($trips as $trip) {
            $this->assertGreaterThanOrEqual(2, $perTrip[$trip] ?? 0, "trajet {$trip} : moins de deux arrêts");
        }
        $this->assertNotContains("L{$single->id}", $routes);
        $this->assertNotContains("S{$stray->id}", $stops);
        // Chaque arrêt exporté est utilisé par un trajet, avec des coordonnées valides.
        $this->assertEqualsCanonicalizing($stops, array_values(array_unique(array_column($tables['stop_times.txt'], 'stop_id'))));
        foreach ($tables['stops.txt'] as $s) {
            $this->assertNotSame('', $s['stop_name']);
            $this->assertGreaterThanOrEqual(-90, (float) $s['stop_lat']);
            $this->assertLessThanOrEqual(90, (float) $s['stop_lat']);
            $this->assertNotSame('', $s['stop_lon']);
        }

        // Distances : strictement croissantes le long d'une forme et d'un trajet ; jamais négatives.
        $previous = [];
        foreach ($tables['shapes.txt'] as $p) {
            $d = (float) $p['shape_dist_traveled'];
            $this->assertGreaterThanOrEqual(0, $d);
            $this->assertGreaterThan($previous[$p['shape_id']] ?? -1, $d, "forme {$p['shape_id']} : distance non croissante");
            $previous[$p['shape_id']] = $d;
        }
        $previous = [];
        foreach ($tables['stop_times.txt'] as $st) {
            if ($st['shape_dist_traveled'] === '') {
                continue;
            }
            $d = (float) $st['shape_dist_traveled'];
            $this->assertGreaterThan($previous[$st['trip_id']] ?? -1, $d, "trajet {$st['trip_id']} : distance non croissante");
            $previous[$st['trip_id']] = $d;
        }

        // Dates AAAAMMJJ, calendrier cohérent avec le flux.
        $info = $tables['feed_info.txt'][0];
        $this->assertMatchesRegularExpression('/^\d{8}$/', $info['feed_start_date']);
        $this->assertLessThanOrEqual($info['feed_end_date'], $tables['calendar.txt'][0]['end_date']);
        $this->assertSame('Europe/Paris', $tables['agency.txt'][0]['agency_timezone']);
    }

    public function test_stop_times_are_ordered_and_distances_are_all_or_nothing_per_trip(): void
    {
        // Arrêts dans l'ordre inverse du tracé : la forme est retournée pour suivre les positions.
        $reverse = Line::factory()->create(['name' => 'Ligne inversée', 'type' => 'passenger']);
        $from = Station::factory()->create(['geom' => Point::makeGeodetic(47.0, 1.1)]);
        $to = Station::factory()->create(['geom' => Point::makeGeodetic(47.0, 1.0)]);
        LineSegment::factory()->create([
            'line_id' => $reverse->id, 'era_id' => $this->currentEra->id, 'status' => 'in_service',
            'geom' => LineString::make([Point::makeGeodetic(47.0, 1.0), Point::makeGeodetic(47.0, 1.1)]),
            'source' => 'osm',
        ]);
        foreach ([$from, $to] as $i => $station) {
            DB::table('line_stops')->insert([
                'line_id' => $reverse->id, 'era_id' => $this->currentEra->id, 'station_id' => $station->id,
                'position' => $i + 1, 'pk_m' => $i * 7000, 'effective_status' => 'active', 'is_terminal' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $feed = $this->feed(2030);

        $times = array_values(array_filter(array_slice($feed['stop_times.txt'], 1), fn ($r) => $r[0] === "T{$reverse->id}"));
        $this->assertCount(2, $times);
        $this->assertSame('0', $times[0][5], 'premier arrêt au début de la forme retournée');
        $this->assertGreaterThan(7000, (float) $times[1][5]);
        $this->assertGreaterThan($this->seconds($times[0][1]), $this->seconds($times[1][1]), 'horaires croissants');
        $shape = array_values(array_filter(array_slice($feed['shapes.txt'], 1), fn ($r) => $r[0] === "shape-{$reverse->id}"));
        $this->assertEqualsWithDelta(1.1, (float) $shape[0][2], 1e-6, 'la forme commence au premier arrêt');
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
