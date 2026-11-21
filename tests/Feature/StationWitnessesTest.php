<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Models\Station;
use App\Services\LineStations;
use App\Services\StationTimeline;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Gares témoins (database/data/gares_temoins.json) : une vingtaine de gares
 * aux dates de fermeture connues et sourcées (Wikipédia, lignes-oubliees,
 * Wikidata, OSM), réparties en gares de lignes fermées, gares fermées sur une
 * ligne restée en service, haltes supprimées, gares rouvertes et gares de
 * lignes déposées. Chaque témoin est reconstruit (sources, événements, état
 * de ses lignes à chaque ère, extraits de la base vff), puis
 * vff:build-station-eras est lancé.
 *
 * Les TROIS affichages doivent donner la même date et le même état :
 *   - la carte (GET /api/v1/eras/{ère}/stations.geojson, frise de la carte) ;
 *   - la fiche gare (GET /stations/{slug}) ;
 *   - la fiche ligne (App\Services\LineStations, vue « décennie par décennie »).
 * Et la date doit être la date sourcée attendue, compatible avec la frise.
 */
class StationWitnessesTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-10-19';

    /** @var array<string, array{station: Station, lines: list<Line>, witness: array<string, mixed>}> */
    private array $built = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (range(1830, 2030, 10) as $y) {
            $y === 2030
                ? Era::factory()->current()->create(['year' => $y, 'label' => 'Aujourd\'hui'])
                : Era::factory()->create(['year' => $y]);
        }
    }

    /** @return list<array<string, mixed>> */
    public static function witnesses(): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/../../database/data/gares_temoins.json'), true)['stations'];
    }

    private function build(): void
    {
        $eras = Era::all()->keyBy('year');
        foreach (self::witnesses() as $i => $w) {
            $lat = 43.0 + $i * 0.1;
            $station = Station::factory()->create([
                'name' => $w['name'], 'type' => $w['type'], 'geom' => Point::makeGeodetic($lat, 2.10),
                'status' => $w['source_status'] === 'gone' ? 'declassified' : $w['source_status'],
                'source_status' => $w['source_status'], 'position_source' => 'sncf',
                'provenance' => ['status' => $w['status_source']], 'opened_at' => null, 'closed_at' => null,
            ]);
            foreach ($w['sources'] as $k => $src) {
                DB::table('station_sources')->insert([
                    'source' => $src['source'], 'source_ref' => "temoin-{$i}-{$k}", 'station_id' => $station->id, 'name' => $w['name'],
                    'lifecycle' => $src['lifecycle'], 'passenger' => $src['passenger'], 'freight' => $src['freight'],
                    'seen_at' => '2026-10-15 17:08:34', 'created_at' => now(), 'updated_at' => now(),
                    'geom' => DB::raw("ST_SetSRID(ST_MakePoint(2.10, {$lat}), 4326)"),
                ]);
            }
            foreach ($w['events'] as $e) {
                DB::table('station_events')->insert([
                    'station_id' => $station->id, 'type' => $e['type'], 'event_date' => $e['date'], 'date_precision' => $e['precision'],
                    'source' => $e['source'], 'source_url' => $e['url'] ?? null, 'is_inferred' => false, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $lines = [];
            foreach ($w['lines'] as $j => $l) {
                // Une ligne par témoin (et par ligne réelle), décalée en latitude : pas de voie voisine partagée.
                $llat = $lat + $j * 0.0;
                $line = Line::factory()->create(['code' => $l['code'], 'name' => $l['name'] ?? "Ligne {$l['code']}"]);
                foreach ($l['eras'] ?? [] as $year => $state) {
                    [$status, $traffic] = explode('/', $state) + [1 => 'unknown'];
                    LineSegment::factory()->create([
                        'line_id' => $line->id, 'era_id' => $eras[(int) $year]->id, 'status' => $status, 'traffic_type' => $traffic,
                        'is_heritage' => str_ends_with($state, '/heritage'),
                        'geom' => LineString::make([Point::makeGeodetic($llat, 2.00), Point::makeGeodetic($llat, 2.20)]),
                    ]);
                }
                foreach ($l['events'] ?? [] as $e) {
                    DB::table('line_events')->insert([
                        'line_id' => $line->id, 'type' => $e['type'], 'event_date' => $e['date'], 'date_precision' => $e['precision'],
                        'description' => 'Témoin', 'source' => $e['source'] ?? null, 'confidence' => 'medium',
                        'section_pk_start_m' => $e['pk_start'] ?? null, 'section_pk_end_m' => $e['pk_end'] ?? null,
                        'section_resolution' => $e['resolution'] ?? null, 'section_from' => $e['from'] ?? null, 'section_to' => $e['to'] ?? null,
                        'section_geom' => ($e['near_geom'] ?? false)
                            ? DB::raw("ST_Multi(ST_SetSRID(ST_MakeLine(ST_MakePoint(2.09, {$llat}), ST_MakePoint(2.11, {$llat})), 4326))") : null,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                DB::table('station_lines')->insert([
                    'station_id' => $station->id, 'line_id' => $line->id, 'method' => 'sncf_pk', 'pk_m' => $l['pk_m'],
                    'pk_source' => 'sncf', 'distance_m' => 0, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $lines[] = $line;
            }
            $this->built[$w['name']] = ['station' => $station, 'lines' => $lines, 'witness' => $w];
        }
        $this->artisan('vff:build-station-eras', ['--today' => self::TODAY])->assertSuccessful();
    }

    public function test_witnesses_cover_every_category(): void
    {
        $categories = array_count_values(array_map(fn ($w) => $w['category'], self::witnesses()));
        $this->assertGreaterThanOrEqual(20, array_sum($categories));
        foreach (['gare sur ligne fermée', 'gare fermée, ligne en service', 'halte supprimée', 'gare rouverte', 'gare sur ligne déposée'] as $c) {
            $this->assertArrayHasKey($c, $categories, $c);
        }
        foreach (self::witnesses() as $w) {
            $this->assertNotEmpty($w['expected']['source_url'], $w['name']);
        }
    }

    public function test_the_three_displays_agree_on_every_witness(): void
    {
        $this->build();
        $years = Era::orderBy('year')->pluck('year')->map(fn ($y) => (int) $y)->all();
        $map = [];
        foreach ($years as $y) {
            foreach ($this->getJson("/api/v1/eras/{$y}/stations.geojson")->assertOk()->json('features') as $f) {
                $map[$f['properties']['name']][$y] = $f['properties'];
            }
        }
        $service = new LineStations;
        $errors = [];
        foreach ($this->built as $name => ['station' => $station, 'lines' => $lines, 'witness' => $w]) {
            $station->refresh();
            $closedAt = $station->closed_at?->format('Y-m-d');
            $expected = $w['expected'];
            if ($closedAt !== $expected['closed_at'] || ($expected['closed_at'] !== null && $station->closed_precision !== $expected['closed_precision'])) {
                $errors[] = "{$name} : date retenue {$closedAt} ({$station->closed_precision}), attendue {$expected['closed_at']} ({$expected['closed_precision']})";
            }

            // Fiche gare.
            $page = $this->get('/stations/'.$station->slug)->assertOk()->viewData('page')['props'];
            $sheet = collect((array) $page['eras'])->mapWithKeys(fn ($e) => [$e['year'] => $e['status']])->all();
            if ($page['station']['closed_at'] !== $closedAt) {
                $errors[] = "{$name} : fiche gare {$page['station']['closed_at']} ≠ {$closedAt}";
            }

            // Fiche ligne (chaque ligne de la gare).
            foreach ($lines as $line) {
                $row = collect($service->forLine($line->id)['stations'])->firstWhere('id', $station->id);
                if ($row['closed_at'] !== $closedAt) {
                    $errors[] = "{$name} : fiche ligne {$line->code} {$row['closed_at']} ≠ {$closedAt}";
                }
                foreach ($years as $y) {
                    if ($row['eras'][$y]['status'] !== $sheet[$y]) {
                        $errors[] = "{$name} {$y} : fiche ligne {$row['eras'][$y]['status']} ≠ fiche gare {$sheet[$y]}";
                    }
                }
            }

            // Carte : même statut (ou gare fermée masquée faute de voie), même date.
            foreach ($years as $y) {
                $feature = $map[$name][$y] ?? null;
                if ($feature === null) {
                    if (! in_array($sheet[$y], [null, StationTimeline::CLOSED], true)) {
                        $errors[] = "{$name} {$y} : absente de la carte, {$sheet[$y]} sur la fiche";
                    }

                    continue;
                }
                if ($feature['status'] !== $sheet[$y]) {
                    $errors[] = "{$name} {$y} : carte {$feature['status']} ≠ fiche {$sheet[$y]}";
                }
                if ($feature['closed_at'] !== $closedAt) {
                    $errors[] = "{$name} {$y} : date de la carte {$feature['closed_at']} ≠ {$closedAt}";
                }
            }

            // La date est compatible avec la frise : jamais montrée ouverte après sa fermeture.
            if ($closedAt !== null) {
                $closedYear = (int) substr($closedAt, 0, 4);
                foreach ($years as $y) {
                    $open = in_array($sheet[$y], StationTimeline::OPEN_STATUSES, true);
                    if ($open && ($y >= $closedYear || $y === 2030)) {
                        $errors[] = "{$name} {$y} : ouverte sur la frise après sa fermeture ({$closedAt})";
                    }
                }
            } elseif (! in_array($sheet[2030], [...StationTimeline::OPEN_STATUSES, LineStations::UNCERTAIN], true)) {
                $errors[] = "{$name} : fermée aujourd'hui sans date retenue";
            }
        }
        $this->assertSame([], $errors, implode("\n", $errors));
    }

    public function test_check_command_compares_the_real_displays(): void
    {
        $this->build();
        $this->artisan('vff:check-station-witnesses')->expectsOutputToContain('21 témoins, 0 en écart.')->assertSuccessful();

        // Une date qui ne correspond plus à la source attendue est signalée.
        $this->built['Ballersdorf']['station']->update(['closed_at' => '2013-12-30']);
        $this->artisan('vff:check-station-witnesses')->expectsOutputToContain('1 en écart')->assertFailed();
    }

    public function test_reopened_witnesses_show_the_gap_on_every_display(): void
    {
        $this->build();
        foreach (['Nort-sur-Erdre', 'Sucé-sur-Erdre'] as $name) {
            $id = $this->built[$name]['station']->id;
            $status = fn (int $y) => DB::table('station_eras as se')->join('eras as e', 'e.id', '=', 'se.era_id')
                ->where('se.station_id', $id)->where('e.year', $y)->value('se.status');
            // Fermée aux voyageurs en 1980, rouverte le 28/02/2014 (tram-train Nantes - Châteaubriant).
            $this->assertSame('active', $status(1970), $name);
            $this->assertNotSame('active', $status(2000), $name);
            $this->assertSame('active', $status(2020), $name);
            $this->assertSame('active', $status(2030), $name);
        }
    }

    public function test_line_decade_view_lists_each_closure_in_its_decade(): void
    {
        $this->build();
        $service = new LineStations;
        foreach ($this->built as $name => ['station' => $station, 'lines' => $lines]) {
            $closedAt = $station->fresh()->closed_at?->format('Y-m-d');
            if ($closedAt === null) {
                continue;
            }
            $decades = collect($service->byEra($lines[0]->id))
                ->filter(fn ($era) => collect($era['closures'])->contains('slug', $station->slug))->pluck('year')->all();
            $year = (int) substr($closedAt, 0, 4);
            $this->assertSame([(int) (ceil($year / 10) * 10)], $decades, $name);
        }
    }
}
