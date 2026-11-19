<?php

namespace Tests\Feature\Api;

use App\Enums\SegmentStatus;
use App\Models\Line;
use App\Models\LineSegment;
use App\Models\Station;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Annuaires de l'API publique : GET /api/v1/lines et GET /api/v1/stations (filtres de /lines et
 * /stations, tri, pagination par curseur, compteurs, erreurs 422).
 */
class DirectoryApiTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    private Line $closed;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
        $this->line->update(['network' => 'principal', 'departements' => json_encode(['Yvelines'])]);
        $this->origin->update(['department_code' => '75']);
        $this->destination->update(['department_code' => '78']);

        // Ligne secondaire fermée aujourd'hui, ouverte en 1960, avec une portion associative en 1960.
        $this->closed = Line::factory()->create([
            'slug' => 'archeo-ligne-de-morlaix-a-primel', 'code' => null, 'name' => 'Ligne de Morlaix à Primel',
            'network' => 'secondaire', 'departements' => json_encode(['Finistère']),
        ]);
        $geom = LineString::make([Point::makeGeodetic(48.58, -3.83), Point::makeGeodetic(48.70, -3.82)]);
        LineSegment::factory()->create(['line_id' => $this->closed->id, 'era_id' => $this->pastEra->id, 'geom' => $geom,
            'status' => SegmentStatus::InService->value, 'traffic_type' => 'mixed', 'is_heritage' => true]);
        LineSegment::factory()->create(['line_id' => $this->closed->id, 'era_id' => $this->currentEra->id, 'geom' => $geom,
            'status' => SegmentStatus::Dismantled->value, 'traffic_type' => 'unknown']);
        // Ligne sans tracé : ni ouverte ni fermée.
        Line::factory()->create(['slug' => 'rfn-000001', 'code' => '000001', 'name' => 'Ancienne ligne sans tracé', 'network' => null]);
    }

    public function test_line_directory_lists_lines_with_their_state_today(): void
    {
        $response = $this->getJson('/api/v1/lines')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=300, public')
            ->assertJsonPath('meta.era_year', 2030)
            ->assertJsonMissingPath('meta.facets')
            ->assertJsonStructure(['data' => [['slug', 'code', 'name', 'network', 'historical_operator', 'regions', 'departements',
                'state', 'traffic', 'heritage', 'links' => ['web', 'self']]], 'links' => ['next'], 'meta' => ['per_page', 'next_cursor']]);

        // Ordre alphabétique français : « Ancienne », « Ligne de Morlaix », « Paris-Saint-Lazare ».
        $this->assertSame(['rfn-000001', 'archeo-ligne-de-morlaix-a-primel', $this->line->slug], $response->json('data.*.slug'));
        $this->assertSame(['unknown', 'closed', 'open'], $response->json('data.*.state'));
        $this->assertSame('passenger', $response->json('data.2.traffic'));
        $this->assertSame(['Yvelines'], $response->json('data.2.departements'));
    }

    public function test_line_directory_filters_like_the_page(): void
    {
        $slugs = fn (string $query): array => $this->getJson('/api/v1/lines?'.$query)->assertOk()->json('data.*.slug');

        $this->assertSame([$this->line->slug], $slugs('status=open'));
        $this->assertSame([$this->closed->slug], $slugs('status=closed'));
        $this->assertSame([$this->closed->slug], $slugs('network=secondaire'));
        $this->assertSame(['rfn-000001'], $slugs('network=none'));
        $this->assertSame([$this->line->slug], $slugs('traffic=passenger'));
        $this->assertSame([$this->closed->slug], $slugs('heritage=true'));
        $this->assertSame([$this->closed->slug], $slugs('department=29'));
        $this->assertSame([$this->line->slug], $slugs('q=saint-lazare'));
        // À l'ère 1960, la ligne de Primel était ouverte, en trafic mixte.
        $this->assertSame([$this->closed->slug, $this->line->slug], $slugs('era=1960&status=open'));
        $this->getJson('/api/v1/lines?era=1960&status=open&traffic=mixed')->assertOk()
            ->assertJsonPath('meta.era_year', 1960)->assertJsonPath('data.0.state', 'open')->assertJsonPath('data.0.traffic', 'mixed');
    }

    public function test_line_directory_sorts_and_paginates_by_cursor(): void
    {
        $this->assertSame(['rfn-000001', $this->line->slug, $this->closed->slug],
            $this->getJson('/api/v1/lines?sort=code')->json('data.*.slug'), 'lignes sans code en dernier');
        $this->assertSame([$this->line->slug, $this->closed->slug, 'rfn-000001'],
            $this->getJson('/api/v1/lines?order=desc')->json('data.*.slug'));

        $first = $this->getJson('/api/v1/lines?per_page=2&sort=code')->assertOk()->assertJsonCount(2, 'data');
        $next = $first->json('links.next');
        $this->assertStringContainsString('sort=code', $next);
        $second = $this->getJson($next)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('links.next', null);
        $this->assertSame($this->closed->slug, $second->json('data.0.slug'));
    }

    public function test_line_directory_facets_count_each_option_without_its_own_filter(): void
    {
        $this->getJson('/api/v1/lines?facets=true&network=principal')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.facets.total', 1)
            ->assertJsonPath('meta.facets.open', 1)
            ->assertJsonPath('meta.facets.networks.principal', 1)
            ->assertJsonPath('meta.facets.networks.secondaire', 1)
            ->assertJsonPath('meta.facets.networks.none', 1)
            ->assertJsonPath('meta.facets.traffics.passenger', 1);
    }

    public function test_line_directory_rejects_invalid_filters_and_foreign_cursors(): void
    {
        $this->getJson('/api/v1/lines?network=autre&era=1935&status=x&sort=length&per_page=500&heritage=peut-etre&department=99')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['network', 'era', 'status', 'sort', 'per_page', 'heritage', 'department']);

        $eventsCursor = 'eyJsaW5lX2V2ZW50cy5ldmVudF9kYXRlIjoiMTg4My0wMS0wMSAwMDowMDowMCIsImxpbmVfZXZlbnRzLmlkIjoyNzk0LCJfcG9pbnRzVG9OZXh0SXRlbXMiOnRydWV9';
        $this->getJson('/api/v1/lines?cursor='.$eventsCursor)->assertUnprocessable()->assertJsonValidationErrors(['cursor']);
        $this->getJson('/api/v1/lines?cursor=nimporte')->assertUnprocessable()->assertJsonValidationErrors(['cursor']);
    }

    public function test_station_directory_filters_sorts_and_counts(): void
    {
        $closedStation = Station::factory()->create([
            'slug' => 'primel', 'name' => 'Primel', 'city' => null, 'department_code' => '29', 'status' => 'declassified',
            'opened_at' => '1906-07-01', 'closed_at' => '1934-12-31', 'geom' => Point::makeGeodetic(48.70, -3.82),
        ]);
        Station::factory()->create(['slug' => 'poste-technique', 'name' => 'Poste 12', 'provenance' => ['kind' => 'technical'],
            'geom' => Point::makeGeodetic(48.80, 2.20)]);
        DB::table('station_lines')->insert(['station_id' => $closedStation->id, 'line_id' => $this->closed->id, 'method' => 'segment']);
        DB::table('station_eras')->insert(['station_id' => $closedStation->id, 'era_id' => $this->pastEra->id, 'status' => 'declassified']);

        $all = $this->getJson('/api/v1/stations')->assertOk();
        $this->assertSame(['paris-saint-lazare', 'primel', 'versailles-rive-droite'], $all->json('data.*.slug'), 'sans point technique');
        $this->assertSame([$this->closed->slug], $all->json('data.1.lines.*.slug'));
        $this->assertSame('1906-07-01', $all->json('data.1.opened_at'));

        $this->assertSame(['primel'], $this->getJson('/api/v1/stations?status=declassified')->json('data.*.slug'));
        $this->assertSame(['primel'], $this->getJson('/api/v1/stations?department=29')->json('data.*.slug'));
        $this->assertSame(['primel'], $this->getJson('/api/v1/stations?era=1960')->assertJsonPath('meta.era_year', 1960)->json('data.*.slug'));
        // Tri par commune : la gare sans commune vient en dernier, dans les deux sens.
        $this->assertSame(['paris-saint-lazare', 'versailles-rive-droite', 'primel'], $this->getJson('/api/v1/stations?sort=city')->json('data.*.slug'));
        $this->assertSame(['versailles-rive-droite', 'paris-saint-lazare', 'primel'], $this->getJson('/api/v1/stations?sort=city&order=desc')->json('data.*.slug'));
        $this->assertSame(['paris-saint-lazare', 'versailles-rive-droite', 'primel'], $this->getJson('/api/v1/stations?sort=opened')->json('data.*.slug'));

        $this->getJson('/api/v1/stations?facets=true')->assertJsonPath('meta.facets.active', 2)->assertJsonPath('meta.facets.declassified', 1);

        $page = $this->getJson('/api/v1/stations?per_page=2&sort=closed')->assertJsonCount(2, 'data');
        $this->getJson($page->json('links.next'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('links.next', null);
    }

    public function test_station_directory_rejects_invalid_filters(): void
    {
        $this->getJson('/api/v1/stations?status=ouverte&era=1961&sort=uic&flagged=souvent')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'era', 'sort', 'flagged']);
    }
}
