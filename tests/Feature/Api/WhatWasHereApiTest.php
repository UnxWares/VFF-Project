<?php

namespace Tests\Feature\Api;

use App\Models\Accident;
use App\Models\Line;
use App\Models\LineSegment;
use App\Support\Monitoring\PositionlessError;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use PDOException;
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

    public function test_the_summary_mode_gives_history_station_years_and_the_commune_without_the_heavy_parts(): void
    {
        $now = now();
        DB::table('line_events')->insert([
            ['line_id' => $this->line->id, 'type' => 'opening', 'event_date' => '1869-05-01', 'date_precision' => 'month', 'description' => 'Ouverture.', 'source' => 'wikipedia', 'source_url' => null, 'created_at' => $now, 'updated_at' => $now],
            ['line_id' => $this->line->id, 'type' => 'closure_passenger', 'event_date' => '1938-09-01', 'date_precision' => 'day', 'description' => 'Fermeture.', 'source' => 'wikipedia', 'source_url' => null, 'created_at' => $now, 'updated_at' => $now],
            ['line_id' => $this->line->id, 'type' => 'closure_freight', 'event_date' => '1950-01-01', 'date_precision' => 'year', 'description' => 'Fermeture.', 'source' => 'wikipedia', 'source_url' => null, 'created_at' => $now, 'updated_at' => $now],
            ['line_id' => $this->line->id, 'type' => 'reopening', 'event_date' => '1960-01-01', 'date_precision' => 'year', 'description' => 'Réouverture.', 'source' => 'wikipedia', 'source_url' => null, 'created_at' => $now, 'updated_at' => $now],
            ['line_id' => $this->line->id, 'type' => 'closure', 'event_date' => '1990-01-01', 'date_precision' => 'year', 'description' => 'Fermeture.', 'source' => 'archeoferroviaire', 'source_url' => 'http://archeoferroviaire.free.fr/x', 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('stations')->where('id', $this->origin->id)->update(['opened_at' => '1850-01-01', 'closed_at' => '1999-06-01']);

        $data = $this->ask(['lat' => self::LAT, 'lng' => self::LNG, 'radius_m' => 10_000, 'summary' => 1]);

        $line = $data['lines'][0];
        // La fermeture aux voyageurs et au fret est suivie d'une réouverture : écartée ; la source non commerciale n'est jamais lue.
        $this->assertSame(['opened' => 1839], $line['history']); // la plus ancienne ouverture, celle du jeu d'essai
        $this->assertSame([], $line['events']);
        $this->assertSame([], $data['accidents']);
        $this->assertArrayHasKey('commune', $data);
        $station = array_column($data['stations'], null, 'slug')[$this->origin->slug];
        $this->assertSame(1850, $station['opened_year']);
        $this->assertSame(1999, $station['closed_year']);
    }

    public function test_a_large_radius_needs_the_summary_mode_and_the_cache_keeps_the_modes_apart(): void
    {
        $this->getJson('/api/v1/what-was-here?lat=48.85&lng=2.24&radius_m=5000')->assertStatus(422);
        $this->getJson('/api/v1/what-was-here?lat=48.85&lng=2.24&radius_m=10001&summary=1')->assertStatus(422);
        $short = $this->ask(['lat' => self::LAT, 'lng' => self::LNG, 'radius_m' => 1000, 'summary' => 1]);
        $full = $this->ask(['lat' => self::LAT, 'lng' => self::LNG, 'radius_m' => 1000]);
        $this->assertArrayHasKey('commune', $short);
        $this->assertArrayNotHasKey('commune', $full);
    }

    public function test_the_summary_position_is_kept_neither_in_the_server_cache_nor_in_shared_caches(): void
    {
        $here = fn (): array => array_values(array_filter(array_keys($this->cacheEntries()), fn (string $k): bool => str_contains($k, ':here:')));

        // Panneau de la carte : un point choisi sur la carte, gardé en cache par case.
        $this->ask(['lat' => self::LAT, 'lng' => self::LNG, 'year' => 1962]);
        $this->assertCount(1, $here());

        // « Autour de moi » : la position de l'internaute ne laisse aucune trace dans le cache du serveur.
        $response = $this->getJson('/api/v1/what-was-here?'.http_build_query(['lat' => self::LAT, 'lng' => self::LNG, 'radius_m' => 5000, 'summary' => 1]));
        $response->assertOk()->assertJsonStructure(['lines', 'stations', 'commune']);
        $this->assertCount(1, $here(), 'aucune clé de cache ne contient la case de la position');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('public', (string) $response->headers->get('Cache-Control'));
    }

    public function test_a_summary_error_is_reported_without_the_position(): void
    {
        // Une requête annulée (délai dépassé) : Laravel écrit dans son journal le message de l'erreur, qui recopie la
        // requête SQL avec ses valeurs, donc la latitude et la longitude de l'internaute.
        DB::beforeExecuting(function (string $query, array $bindings): void {
            if (str_contains($query, 'ST_MakePoint')) {
                throw new QueryException('pgsql', $query, $bindings, new PDOException('SQLSTATE[57014]: Query canceled: canceling statement due to statement timeout'));
            }
        });
        Exceptions::fake();

        $this->getJson('/api/v1/what-was-here?'.http_build_query(['lat' => 48.8765, 'lng' => 2.3245, 'radius_m' => 5000, 'summary' => 1]))
            ->assertStatus(500);

        Exceptions::assertReported(function (PositionlessError $e): bool {
            $logged = (string) $e;
            $this->assertNull($e->getPrevious(), 'aucune erreur précédente (le journal l\'écrirait en entier)');
            $this->assertStringContainsString(QueryException::class, $logged);
            $this->assertStringContainsString('statement timeout', $logged);
            foreach (['48.876', '2.324', '48,876', '2,324'] as $coordinate) {
                $this->assertStringNotContainsString($coordinate, $logged);
            }

            return true;
        });
    }

    public function test_a_map_panel_error_keeps_its_query_for_the_diagnosis(): void
    {
        // Panneau de la carte : un point choisi sur la carte, pas la position de l'internaute.
        DB::beforeExecuting(function (string $query, array $bindings): void {
            if (str_contains($query, 'ST_MakePoint')) {
                throw new QueryException('pgsql', $query, $bindings, new PDOException('SQLSTATE[57014]: Query canceled'));
            }
        });
        Exceptions::fake();

        $this->getJson('/api/v1/what-was-here?'.http_build_query(['lat' => self::LAT, 'lng' => self::LNG, 'year' => 1962]))
            ->assertStatus(500);

        Exceptions::assertReported(QueryException::class);
    }

    public function test_the_withheld_error_message_has_neither_sql_nor_decimal_number(): void
    {
        $this->assertSame('SQLSTATE[57014]: Query canceled', PositionlessError::message('SQLSTATE[57014]: Query canceled (Connection: pgsql, SQL: select ST_MakePoint(2.3245, 48.8765))'));
        $this->assertSame('Point [n], -[n] hors de la France', PositionlessError::message('Point 48.8765, -4,49 hors de la France'));
    }

    /** @return array<string, mixed> entrées du cache de test (magasin `array`) */
    private function cacheEntries(): array
    {
        $store = Cache::getStore();
        $storage = (new \ReflectionProperty($store, 'storage'))->getValue($store);

        return is_array($storage) ? $storage : [];
    }

    public function test_a_section_event_is_never_a_whole_line_fact(): void
    {
        $now = now();
        $base = ['line_id' => $this->line->id, 'date_precision' => 'year', 'source' => 'wikipedia', 'source_url' => null, 'created_at' => $now, 'updated_at' => $now,
            'section_from' => null, 'section_to' => null, 'section_pk_start_m' => null, 'section_pk_end_m' => null];
        DB::table('line_events')->insert([
            ['type' => 'dismantling', 'event_date' => '1962-01-01', 'description' => 'Dépose.', 'section_from' => 'Nexon', 'section_to' => 'Périgueux'] + $base,
            ['type' => 'closure', 'event_date' => '2018-01-01', 'description' => 'Fermeture.', 'section_pk_start_m' => 1000, 'section_pk_end_m' => 5000] + $base,
            ['type' => 'closure_freight', 'event_date' => '1980-01-01', 'description' => 'Fermeture.'] + $base,
        ]);

        $history = $this->ask(['lat' => self::LAT, 'lng' => self::LNG, 'radius_m' => 5000, 'summary' => 1])['lines'][0]['history'];

        $this->assertArrayNotHasKey('dismantled', $history);
        $this->assertArrayNotHasKey('closed', $history);
        $this->assertSame(1980, $history['closed_freight']);
        // Ligne en service : une fermeture ou une dépose sans portion n'est pas celle de toute la ligne.
        DB::table('line_events')->insert([['type' => 'closure', 'event_date' => '1942-01-01', 'description' => 'Fermeture.'] + $base]);
        $other = $this->ask(['lat' => self::LAT + 0.001, 'lng' => self::LNG, 'radius_m' => 5000, 'summary' => 1])['lines'][0]['history'];
        $this->assertArrayNotHasKey('closed', $other);
        $this->assertContains(['key' => 'closed', 'year' => 1942, 'from' => null, 'to' => null], $other['sections']);
        $this->assertContains(['key' => 'dismantled', 'year' => 1962, 'from' => 'Nexon', 'to' => 'Périgueux'], $history['sections']);
        $this->assertContains(['key' => 'closed', 'year' => 2018, 'from' => null, 'to' => null], $history['sections']);
    }
}
