<?php

namespace Tests\Feature\Web;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ActsAsRoles;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Filtres et facettes de /lines non couverts ailleurs : patrimoine, lignes
 * sans réseau, lignes sans tracé (état inconnu), trafic dominant au km,
 * et partage des messages flash (props Inertia).
 */
class LinesIndexFiltersTest extends TestCase
{
    use ActsAsRoles;
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
        $this->line->update(['network' => 'principal']);
    }

    private function segment(Line $line, Era $era, array $points, array $attributes = []): void
    {
        LineSegment::factory()->create([
            'line_id' => $line->id,
            'era_id' => $era->id,
            'geom' => LineString::make(array_map(fn ($p) => Point::makeGeodetic($p[0], $p[1]), $points)),
            'status' => 'in_service',
            'traffic_type' => 'freight',
            'is_heritage' => false,
            ...$attributes,
        ]);
    }

    public function test_heritage_filter_and_counter(): void
    {
        $tourist = Line::factory()->create(['name' => 'Train touristique de la vallée', 'network' => 'particulier']);
        $this->segment($tourist, $this->currentEra, [[45.0, 3.0], [45.05, 3.05]], ['is_heritage' => true, 'traffic_type' => 'passenger']);

        $this->get('/lines?heritage=1')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.heritage', true)
            ->where('lines.total', 1)
            ->where('lines.data.0.slug', $tourist->slug)
            ->where('lines.data.0.state', 'open')
            ->where('stats.heritage', 1));

        $this->get('/lines')->assertInertia(fn (Assert $page) => $page->where('stats.heritage', 1)->where('stats.total', 2));
    }

    public function test_lines_without_network_or_geometry(): void
    {
        $orphan = Line::factory()->create(['name' => 'Ligne sans tracé', 'network' => null]);

        $this->get('/lines?network=none')->assertInertia(fn (Assert $page) => $page
            ->where('filters.network', 'none')
            ->where('lines.total', 1)
            ->where('lines.data.0.slug', $orphan->slug)
            // Sans tracé à l'ère courante : ni ouverte ni fermée.
            ->where('lines.data.0.state', 'unknown')
            ->where('lines.data.0.traffic', null)
            ->where('stats.unknown', 1)
            ->where('stats.open', 0)
            ->where('stats.closed', 0)
            ->where('stats.networks.none', 1)
            ->where('stats.networks.principal', 1));
    }

    public function test_dominant_traffic_is_weighted_by_length(): void
    {
        // Ligne mixte : 1 petit tronçon fret, 1 long tronçon voyageurs → voyageurs.
        $mixed = Line::factory()->create(['name' => 'Ligne pondérée', 'network' => 'secondaire']);
        $this->segment($mixed, $this->currentEra, [[46.0, 4.0], [46.001, 4.001]], ['traffic_type' => 'freight']);
        $this->segment($mixed, $this->currentEra, [[46.0, 4.0], [46.5, 4.5]], ['traffic_type' => 'passenger']);

        $this->get('/lines?network=secondaire')->assertInertia(fn (Assert $page) => $page
            ->where('lines.data.0.traffic', 'passenger')
            ->where('stats.traffics.passenger', 1)
            ->where('stats.traffics.freight', 1));

        $this->get('/lines?traffic=freight')->assertInertia(fn (Assert $page) => $page
            ->where('filters.traffic', 'freight')
            ->where('lines.total', 1)
            ->where('lines.data.0.slug', $mixed->slug));
    }

    public function test_flash_messages_are_shared_with_the_next_page_only(): void
    {
        $user = $this->member();

        $this->actingAs($user)->from('/profile')->put('/profile', ['name' => 'Nouveau', 'locale_preference' => 'fr']);
        $this->actingAs($user)->get('/profile')->assertInertia(fn (Assert $page) => $page
            ->where('flash.success', 'Profil mis à jour.')
            ->where('flash.error', null));
        $this->actingAs($user)->get('/profile')->assertInertia(fn (Assert $page) => $page->where('flash.success', null));
    }
}
