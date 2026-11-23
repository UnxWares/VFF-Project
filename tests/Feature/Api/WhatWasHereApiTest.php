<?php

namespace Tests\Feature\Api;

use App\Models\Accident;
use App\Models\Line;
use App\Models\LineSegment;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * `GET /api/v1/what-was-here` : panneau « Qu'y avait-il ici ? » de la carte. Même service que l'outil MCP
 * `what_was_here` ; on vérifie ici le point d'API : case de grille, cache, droits des tracés, validation.
 */
class WhatWasHereApiTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    private const LAT = 48.8530;

    private const LNG = 2.2400;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->buildRailNetwork();
        $now = now();

        $closed = Line::factory()->create(['slug' => 'archeo-ligne-voisine', 'name' => 'Ligne voisine', 'code' => null]);
        LineSegment::factory()->create([
            'line_id' => $closed->id, 'era_id' => $this->pastEra->id, 'status' => 'abandoned', 'traffic_type' => 'unknown',
            'electrified' => false, 'is_heritage' => false, 'source' => 'archeoferroviaire', 'pk_start_m' => 0, 'pk_end_m' => 5_000,
            'geom' => LineString::make([Point::makeGeodetic(48.8560, 2.2300), Point::makeGeodetic(48.8560, 2.2500)]),
        ]);
        DB::table('station_eras')->insert([
            ['station_id' => $this->origin->id, 'era_id' => $this->pastEra->id, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['station_id' => $this->destination->id, 'era_id' => $this->pastEra->id, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('station_lines')->insert([
            ['station_id' => $this->origin->id, 'line_id' => $this->line->id, 'method' => 'segment', 'created_at' => $now, 'updated_at' => $now],
            ['station_id' => $this->destination->id, 'line_id' => $this->line->id, 'method' => 'segment', 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('line_events')->insert([
            ['line_id' => $this->line->id, 'type' => 'electrification', 'event_date' => '1962-05-01', 'date_precision' => 'month',
                'description' => 'Électrification de la ligne.', 'source' => 'wikipedia', 'source_url' => 'https://fr.wikipedia.org/wiki/X',
                'created_at' => $now, 'updated_at' => $now],
            ['line_id' => $this->line->id, 'type' => 'modernization', 'event_date' => '1962-09-01', 'date_precision' => 'month',
                'description' => 'Fait relevé ailleurs.', 'source' => 'archeoferroviaire', 'source_url' => 'http://archeoferroviaire.free.fr/x',
                'created_at' => $now, 'updated_at' => $now],
        ]);
        Accident::factory()->on('1962-03-04')->at(48.8531, 2.2405)->create(['slug' => '1962-03-04-test', 'kind' => 'derailment']);
    }

    /** @return array<string, mixed> */
    private function ask(array $query): array
    {
        $response = $this->getJson('/api/v1/what-was-here?'.http_build_query($query));
        $response->assertOk();

        return $response->json();
    }

    public function test_it_answers_with_lines_stations_events_and_accidents_of_the_year(): void
    {
        $data = $this->ask(['lat' => self::LAT, 'lng' => self::LNG, 'year' => 1962]);

        $this->assertSame(1960, $data['era']['year']);
        $this->assertSame(['paris-saint-lazare-versailles-rd', 'archeo-ligne-voisine'], array_column($data['lines'], 'slug'));
        $line = $data['lines'][0];
        $this->assertSame('in_service', $line['status']);
        $this->assertSame(['electrification'], array_column($line['events'], 'type'));
        $this->assertSame('wikipedia', $line['events'][0]['source']);
        $this->assertNotNull($line['nearest_station']);
        $this->assertContains($line['nearest_station']['slug'], [$this->origin->slug, $this->destination->slug]);
        $this->assertSame(['1962-03-04-test'], array_column($data['accidents'], 'slug'));
        $this->assertArrayHasKey('attribution', $data);
        $this->assertStringNotContainsString('archeoferroviaire.free.fr', json_encode(array_diff_key($data, ['attribution' => 1]), JSON_UNESCAPED_SLASHES));
    }

    public function test_a_withheld_trace_gives_no_distance_and_a_rounded_pk(): void
    {
        $data = $this->ask(['lat' => self::LAT, 'lng' => self::LNG, 'year' => 1962]);

        $other = $data['lines'][1];
        $this->assertTrue($other['geometry_withheld']);
        $this->assertNull($other['distance_m']);
        $this->assertSame(0, $other['pk_m'] % 100);
    }

    public function test_the_point_is_snapped_to_its_grid_cell_so_neighbours_share_the_answer(): void
    {
        $a = $this->ask(['lat' => 48.85301, 'lng' => 2.24001, 'year' => 1962]);
        $b = $this->ask(['lat' => 48.85349, 'lng' => 2.24049, 'year' => 1962]);

        $this->assertSame($a, $b);
        $this->assertEqualsWithDelta(48.8535, $a['point']['lat'], 0.00001);
        $this->assertEqualsWithDelta(2.2405, $a['point']['lng'], 0.00001);
    }

    public function test_it_defaults_to_today_and_refuses_bad_points(): void
    {
        $this->assertSame(2030, $this->ask(['lat' => self::LAT, 'lng' => self::LNG])['era']['year']);
        $this->getJson('/api/v1/what-was-here?lat=95&lng=2')->assertStatus(422);
        $this->getJson('/api/v1/what-was-here?lat=48&lng=2&radius_m=50000')->assertStatus(422);
        $this->getJson('/api/v1/what-was-here')->assertStatus(422);
        $this->assertSame([], $this->ask(['lat' => 43.3, 'lng' => 5.4, 'year' => 1962])['lines']);
    }
}
