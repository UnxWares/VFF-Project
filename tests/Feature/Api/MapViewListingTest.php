<?php

namespace Tests\Feature\Api;

use App\Models\LineSegment;
use App\Services\Map\ViewListing;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Alternative textuelle à la carte : lignes et gares d'une emprise (`GET /api/v1/eras/{year}/in-view`).
 */
class MapViewListingTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    private const PARIS_WEST = '2.0,48.7,2.5,49.0';

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
    }

    public function test_lists_the_lines_and_stations_of_the_view(): void
    {
        DB::table('station_eras')->insert([
            ['station_id' => $this->origin->id, 'era_id' => $this->currentEra->id, 'status' => 'active', 'uncertain' => false, 'created_at' => now(), 'updated_at' => now()],
            ['station_id' => $this->destination->id, 'era_id' => $this->currentEra->id, 'status' => 'tourist', 'uncertain' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $json = $this->getJson('/api/v1/eras/2030/in-view?bbox='.self::PARIS_WEST)->assertOk()->json();

        $this->assertSame(2030, $json['era']);
        $this->assertFalse($json['too_wide']);
        $this->assertSame(1, $json['lines']['total']);
        $line = $json['lines']['items'][0];
        $this->assertSame($this->line->slug, $line['slug']);
        // Trafic « voyageurs seuls » : la couleur de la grande vitesse, comme sur la carte.
        $this->assertSame('lgv', $line['state']);
        // Environ 16 km de tracé entre Saint-Lazare et Versailles.
        $this->assertEqualsWithDelta(15.9, $line['km'], 1.0);
        $this->assertSame(['paris-saint-lazare', 'versailles-rive-droite'], array_column($json['stations']['items'], 'slug'));
        $this->assertSame(['active', 'tourist'], array_column($json['stations']['items'], 'state'));
    }

    public function test_km_are_counted_inside_the_view_only(): void
    {
        $full = $this->getJson('/api/v1/eras/2030/in-view?bbox='.self::PARIS_WEST)->json('lines.items.0.km');
        $half = $this->getJson('/api/v1/eras/2030/in-view?bbox=2.0,48.7,2.24,49.0')->json('lines.items.0.km');

        $this->assertGreaterThan(5, $half);
        $this->assertLessThan($full, $half);
    }

    public function test_a_line_with_several_states_appears_once_per_state(): void
    {
        LineSegment::factory()->create([
            'line_id' => $this->line->id,
            'era_id' => $this->currentEra->id,
            'geom' => LineString::make([Point::makeGeodetic(48.82, 2.10), Point::makeGeodetic(48.83, 2.20)]),
            'status' => 'abandoned',
            'is_heritage' => false,
            'source' => 'osm',
        ]);

        $states = array_column($this->getJson('/api/v1/eras/2030/in-view?bbox='.self::PARIS_WEST)->json('lines.items'), 'state');

        sort($states);
        $this->assertSame(['abandoned', 'lgv'], $states);
    }

    public function test_the_era_changes_the_answer(): void
    {
        $this->getJson('/api/v1/eras/1960/in-view?bbox='.self::PARIS_WEST)->assertOk()
            ->assertJsonPath('lines.total', 1)
            // Aucune gare calculée pour cette ère passée : liste vide.
            ->assertJsonPath('stations.total', 0);
    }

    public function test_a_view_outside_the_network_is_empty(): void
    {
        $this->getJson('/api/v1/eras/2030/in-view?bbox=5.0,43.0,5.5,43.4')->assertOk()
            ->assertJsonPath('lines.total', 0)->assertJsonPath('lines.items', []);
    }

    public function test_a_view_that_is_too_wide_is_not_computed(): void
    {
        $this->getJson('/api/v1/eras/2030/in-view?bbox=-5.0,41.0,9.0,51.0')->assertOk()
            ->assertJsonPath('too_wide', true)
            ->assertJsonPath('lines.items', [])
            ->assertJsonPath('stations.items', []);

        $this->assertTrue(ViewListing::tooWide([0.0, 0.0, 4.001, 1.0]));
        $this->assertFalse(ViewListing::tooWide([0.0, 0.0, 4.0, 3.0]));
    }

    public function test_the_answer_is_cached_and_versioned(): void
    {
        $response = $this->get('/api/v1/eras/2030/in-view?bbox='.self::PARIS_WEST)->assertOk();
        $this->assertNotNull($response->headers->get('ETag'));
        $this->assertStringContainsString('max-age=60', (string) $response->headers->get('Cache-Control'));
        $this->get('/api/v1/eras/2030/in-view?bbox='.self::PARIS_WEST.'&v=abc')
            ->assertHeader('Cache-Control', 'max-age=86400, public');
    }

    public function test_invalid_requests_are_refused(): void
    {
        $this->getJson('/api/v1/eras/2030/in-view')->assertUnprocessable()->assertJsonValidationErrors('bbox');
        $this->getJson('/api/v1/eras/2030/in-view?bbox=2.5,48.7,2.0,49.0')->assertUnprocessable();
        $this->getJson('/api/v1/eras/2030/in-view?bbox=abc')->assertUnprocessable();
        $this->getJson('/api/v1/eras/1234/in-view?bbox='.self::PARIS_WEST)->assertNotFound();
    }
}
