<?php

namespace Tests\Feature\Api;

use App\Models\Line;
use App\Models\Station;
use App\Services\GtfsExporter;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;
use ZipArchive;

/**
 * Forme GTFS prolongée sur la voie partagée : le tracé d'une ligne s'arrête au début du tronc
 * commun qu'elle partage avec une autre ligne, ses trains vont jusqu'à la gare. Extrait réel :
 * Gannat (fin de 707000 sur 790000, gare à 439 m du tracé de 707000 avant correctif), fixture
 * `tests/Fixtures/Gtfs/gannat-tronc-commun.json` (BD TOPO et OpenStreetMap seulement).
 */
class GtfsSharedTrackTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    private Line $branch;

    private Station $gannat;

    private Station $origin2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
    }

    /**
     * Charge l'extrait de Gannat : tronçons de 707000 et 790000, gare de Gannat, et un arrêt de départ
     * posé au bout de l'extrait de 707000 opposé à Gannat (le reste de la ligne n'est pas dans l'extrait).
     *
     * @param  array<int|string, string>  $sourceOverride  code de ligne => source imposée à tous ses tronçons
     */
    private function loadGannat(array $sourceOverride = []): void
    {
        $fixture = json_decode((string) file_get_contents(base_path('tests/Fixtures/Gtfs/gannat-tronc-commun.json')), true);
        $lines = [];
        foreach (['707000' => 'Ligne de Commentry à Gannat', '790000' => 'Ligne de Saint-Germain-des-Fossés à Gannat'] as $code => $name) {
            $lines[$code] = Line::factory()->create(['code' => $code, 'name' => $name, 'type' => 'mixed']);
        }
        $this->branch = $lines['707000'];
        foreach ($fixture['segments'] as $s) {
            $wkt = 'LINESTRING('.implode(',', array_map(fn (array $c): string => "{$c[0]} {$c[1]}", $s['coordinates'])).')';
            $this->auditSegment($lines[$s['line']], $this->currentEra, $wkt, $s['status'], 'mixed', $sourceOverride[$s['line']] ?? $s['source']);
        }

        $this->gannat = Station::factory()->create(['name' => 'Gannat', 'geom' => Point::makeGeodetic($fixture['station']['lat'], $fixture['station']['lng'])]);
        $far = DB::selectOne(<<<'SQL'
            WITH m AS (SELECT ST_LineMerge(ST_Collect(geom)) AS g FROM line_segments WHERE line_id = ? AND era_id = ?)
            SELECT CASE WHEN ST_Distance(ST_StartPoint(g), s.geom::geometry) > ST_Distance(ST_EndPoint(g), s.geom::geometry)
                        THEN ST_X(ST_StartPoint(g)) ELSE ST_X(ST_EndPoint(g)) END AS lng,
                   CASE WHEN ST_Distance(ST_StartPoint(g), s.geom::geometry) > ST_Distance(ST_EndPoint(g), s.geom::geometry)
                        THEN ST_Y(ST_StartPoint(g)) ELSE ST_Y(ST_EndPoint(g)) END AS lat,
                   GeometryType(g) AS kind
            FROM m, stations s WHERE s.id = ?
            SQL, [$this->branch->id, $this->currentEra->id, $this->gannat->id]);
        $this->assertSame('LINESTRING', $far->kind, "l'extrait de 707000 est d'un seul tenant");
        $this->origin2 = Station::factory()->create(['name' => 'Départ de l\'extrait', 'geom' => Point::makeGeodetic((float) $far->lat, (float) $far->lng)]);
        foreach ([$this->origin2, $this->gannat] as $i => $station) {
            DB::table('line_stops')->insert([
                'line_id' => $this->branch->id, 'era_id' => $this->currentEra->id, 'station_id' => $station->id,
                'position' => $i + 1, 'pk_m' => $i * 1000, 'effective_status' => 'active', 'is_terminal' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /**
     * Exporte l'ère 2030 et renvoie les fichiers sous forme de lignes associatives.
     *
     * @return array<string, list<array<string, string>>>
     */
    private function export(): array
    {
        $path = sys_get_temp_dir().'/vff-gtfs-partage-'.uniqid().'.zip';
        app(GtfsExporter::class)->exportEra(2030, $path);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $files = [];
        foreach (['shapes.txt', 'stop_times.txt', 'trips.txt'] as $name) {
            $rows = array_map(fn (string $l): array => str_getcsv($l, ',', '"', '\\'), array_values(array_filter(explode("\n", (string) $zip->getFromName($name)))));
            $files[$name] = array_map(fn (array $r): array => array_combine($rows[0], $r), array_slice($rows, 1));
        }
        $zip->close();
        @unlink($path);

        return $files;
    }

    /** @return list<array<string, string>> */
    private function shapeOf(array $feed, Line $line): array
    {
        return array_values(array_filter($feed['shapes.txt'], fn (array $p): bool => $p['shape_id'] === "shape-{$line->id}"));
    }

    /** Distance (m) d'une gare à une forme exportée. */
    private function stationToShape(Station $station, array $shape): float
    {
        $wkt = 'LINESTRING('.implode(',', array_map(fn (array $p): string => "{$p['shape_pt_lon']} {$p['shape_pt_lat']}", $shape)).')';

        return (float) DB::selectOne('SELECT ST_Distance(ST_GeomFromText(?, 4326)::geography, geom) AS d FROM stations WHERE id = ?', [$wkt, $station->id])->d;
    }

    /** Distance (m) d'une gare au tracé de la ligne seule (avant prolongement). */
    private function stationToOwnTrace(Station $station, Line $line): float
    {
        return (float) DB::selectOne(<<<'SQL'
            SELECT ST_Distance(ST_Collect(ls.geom)::geography, s.geom) AS d
            FROM line_segments ls, stations s
            WHERE ls.line_id = ? AND ls.era_id = ? AND ls.status IN ('in_service','single_track') AND s.id = ?
            GROUP BY s.geom
            SQL, [$line->id, $this->currentEra->id, $station->id])->d;
    }

    /** Plus grand changement de cap (°) entre deux pas successifs d'au moins 1 m de la forme. */
    private function sharpestTurn(array $shape): float
    {
        $pts = [];
        foreach ($shape as $p) {
            $pt = [(float) $p['shape_pt_lon'], (float) $p['shape_pt_lat']];
            if ($pts === [] || hypot(($pt[0] - end($pts)[0]) * 77_000, ($pt[1] - end($pts)[1]) * 111_320) >= 1.0) {
                $pts[] = $pt;
            }
        }
        $max = 0.0;
        for ($i = 1; $i < count($pts) - 1; $i++) {
            $a = [($pts[$i][0] - $pts[$i - 1][0]) * 77_000, ($pts[$i][1] - $pts[$i - 1][1]) * 111_320];
            $b = [($pts[$i + 1][0] - $pts[$i][0]) * 77_000, ($pts[$i + 1][1] - $pts[$i][1]) * 111_320];
            $cos = ($a[0] * $b[0] + $a[1] * $b[1]) / (hypot(...$a) * hypot(...$b));
            $max = max($max, rad2deg(acos(max(-1.0, min(1.0, $cos)))));
        }

        return $max;
    }

    public function test_gannat_shape_follows_the_shared_track_up_to_the_station(): void
    {
        $this->loadGannat();
        $this->assertGreaterThan(400, $this->stationToOwnTrace($this->gannat, $this->branch), 'avant : le tracé de 707000 s\'arrête 439 m avant Gannat');

        $feed = $this->export();
        $shape = $this->shapeOf($feed, $this->branch);
        $this->assertNotEmpty($shape);

        // La gare est sur la forme (moins de 100 m, seuil du validateur MobilityData) ; aucun arrêt ne s'éloigne.
        $this->assertLessThan(100, $this->stationToShape($this->gannat, $shape));
        $this->assertLessThan(30, $this->stationToShape($this->gannat, $shape), 'la voie de 790000 passe à 8 m de la gare');
        $this->assertLessThanOrEqual($this->stationToOwnTrace($this->origin2, $this->branch) + 0.5, $this->stationToShape($this->origin2, $shape));

        // Ni aller-retour ni rebroussement : aucun changement de cap brutal, même au raccord sur 790000.
        $this->assertLessThan(90, $this->sharpestTurn($shape));

        // Distances de la forme recalculées, strictement croissantes, et le prolongement compte.
        $d = array_map(fn (array $p): float => (float) $p['shape_dist_traveled'], $shape);
        $this->assertSame(0.0, $d[0]);
        for ($i = 1; $i < count($d); $i++) {
            $this->assertGreaterThan($d[$i - 1], $d[$i], "point {$i}");
        }
        $ownLength = (float) DB::selectOne('SELECT ST_Length(ST_LineMerge(ST_Collect(geom))::geography) AS m FROM line_segments WHERE line_id = ?', [$this->branch->id])->m;
        $this->assertGreaterThan($ownLength + 400, end($d), 'forme plus longue du tronc commun (environ 440 m)');
        $this->assertLessThan($ownLength + 900, end($d), 'pas de détour');

        // Arrêts : distance croissante le long de la forme, la gare de Gannat au bout du prolongement.
        $times = array_values(array_filter($feed['stop_times.txt'], fn (array $r): bool => $r['trip_id'] === "T{$this->branch->id}"));
        $this->assertCount(2, $times);
        $this->assertSame('S'.$this->gannat->id, $times[1]['stop_id']);
        $this->assertEqualsWithDelta(0.0, (float) $times[0]['shape_dist_traveled'], 1.0);
        $this->assertGreaterThan($ownLength + 380, (float) $times[1]['shape_dist_traveled']);
        $this->assertLessThanOrEqual(end($d) + 0.01, (float) $times[1]['shape_dist_traveled']);
    }

    public function test_shared_track_with_withheld_geometry_is_never_followed(): void
    {
        // La voie du tronc commun vient d'une source dont la licence ne permet pas la redistribution :
        // pas de prolongement (la forme reste celle de 707000, aucun point de 790000).
        $this->loadGannat(['790000' => 'archeoferroviaire']);

        $shape = $this->shapeOf($this->export(), $this->branch);

        $this->assertNotEmpty($shape);
        $this->assertGreaterThan(400, $this->stationToShape($this->gannat, $shape));
        $ownLength = (float) DB::selectOne('SELECT ST_Length(ST_LineMerge(ST_Collect(geom))::geography) AS m FROM line_segments WHERE line_id = ?', [$this->branch->id])->m;
        $this->assertEqualsWithDelta($ownLength, (float) end($shape)['shape_dist_traveled'], 1.0);
    }

    public function test_line_with_withheld_geometry_still_has_no_shape_at_all(): void
    {
        $this->loadGannat(['707000' => 'archeoferroviaire']);

        $feed = $this->export();

        $this->assertSame([], $this->shapeOf($feed, $this->branch));
        $trip = array_values(array_filter($feed['trips.txt'], fn (array $t): bool => $t['trip_id'] === "T{$this->branch->id}"));
        $this->assertSame('', $trip[0]['shape_id']);
    }

    public function test_track_leading_back_along_the_line_is_not_followed(): void
    {
        // La ligne A s'arrête en (1.0, 47.0) en venant de l'ouest ; la gare est à 400 m au nord-est,
        // au bout d'une ligne B qui revient 150 m sur A puis repart vers le nord-est : la suivre
        // ferait un aller-retour (assez court pour que seule la règle du cap le refuse).
        // La forme reste celle de A.
        $a = Line::factory()->create(['name' => 'A', 'type' => 'passenger']);
        $b = Line::factory()->create(['name' => 'B', 'type' => 'passenger']);
        $this->auditSegment($a, $this->currentEra, 'LINESTRING(0.98 47.0, 1.0 47.0)', 'in_service', 'mixed', 'osm');
        $this->auditSegment($b, $this->currentEra, 'LINESTRING(1.0 47.0, 0.998 47.0, 1.001 47.003)', 'in_service', 'mixed', 'osm');
        $from = Station::factory()->create(['geom' => Point::makeGeodetic(47.0, 0.98)]);
        $to = Station::factory()->create(['geom' => Point::makeGeodetic(47.003, 1.001)]);
        foreach ([$from, $to] as $i => $station) {
            DB::table('line_stops')->insert([
                'line_id' => $a->id, 'era_id' => $this->currentEra->id, 'station_id' => $station->id,
                'position' => $i + 1, 'pk_m' => $i * 1000, 'effective_status' => 'active', 'is_terminal' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $shape = $this->shapeOf($this->export(), $a);

        $this->assertEqualsWithDelta(1.0, (float) end($shape)['shape_pt_lon'], 1e-6, 'forme arrêtée au bout de A');
        $this->assertLessThan(90, $this->sharpestTurn($shape));
    }
}
