<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineEvent;
use App\Models\LineSegment;
use App\Services\LineSheet;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Non-régression des correctifs « app » (base vff_test, mini-réseau déterministe) :
 * stats de la home, toutes les décennies sur la carte, filtres /lines par réseau
 * et trafic réel, ère affichée de la fiche ligne, événements (portion +
 * précision de date), PK sur tracé discontinu, contrats JSON de l'API.
 */
class AppPagesTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
        $this->line->update(['network' => 'principal']);
    }

    private function segment(Line $line, Era $era, array $points, string $status = 'in_service', string $traffic = 'passenger'): void
    {
        LineSegment::factory()->create([
            'line_id' => $line->id,
            'era_id' => $era->id,
            'geom' => LineString::make(array_map(fn ($p) => Point::makeGeodetic($p[0], $p[1]), $points)),
            'status' => $status,
            'traffic_type' => $traffic,
            'is_heritage' => false,
        ]);
    }

    public function test_home_stats_count_real_data(): void
    {
        // Une ère seedée mais vide ne doit pas compter comme « cartographiée ».
        Era::factory()->create(['year' => 1900]);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('stats.lines_count', 1)
            ->where('stats.eras_count', 2));
    }

    public function test_map_exposes_every_decade_including_empty_ones(): void
    {
        Era::factory()->create(['year' => 1900]);

        foreach (['/maps' => 'Maps', '/embed/maps' => 'Embed/Maps'] as $url => $component) {
            $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component($component)
                ->has('eras', 3)
                ->where('eras.0.year', 1900)
                ->where('eras.0.has_data', false)
                ->where('eras.2.year', 2030)
                ->where('eras.2.has_data', true)
                ->where('currentEraYear', 2030));
        }
    }

    public function test_lines_index_filters_by_network_traffic_and_state(): void
    {
        $secondary = Line::factory()->create(['name' => 'Ligne secondaire', 'network' => 'secondaire']);
        $this->segment($secondary, $this->currentEra, [[48.0, 2.0], [48.1, 2.1]], 'abandoned', 'unknown');

        $this->get('/lines?network=secondaire')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Lines/Index')
            ->where('lines.total', 1)
            ->where('lines.data.0.network', 'secondaire')
            ->where('lines.data.0.state', 'closed')
            // Les facettes réseau ignorent leur propre filtre.
            ->where('stats.networks.principal', 1)
            ->where('stats.networks.secondaire', 1));

        $this->get('/lines?traffic=passenger')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('lines.total', 1)
            ->where('lines.data.0.traffic', 'passenger')
            ->where('lines.data.0.state', 'open'));

        $this->get('/lines?status=closed')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('lines.total', 1)
            ->where('stats.open', 0)
            ->where('stats.closed', 1));

        // Valeurs inconnues ignorées (pas d'erreur SQL) ; alias historique ?type=.
        $this->get('/lines?network=bogus&traffic=bogus')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.network', '')->where('filters.traffic', '')->where('lines.total', 2));
        $this->get('/lines?type=passengers')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.traffic', 'passenger'));
    }

    public function test_line_page_uses_current_era_and_exposes_events_with_precision(): void
    {
        // Ère créée APRÈS l'ère courante → id plus grand : l'ancien
        // max('era_id') affichait 1980 au lieu de 2030.
        $era1980 = Era::factory()->create(['year' => 1980]);
        $this->segment($this->line, $era1980, [[48.8763, 2.3253], [48.8091, 2.1347]], 'in_service', 'mixed');

        LineEvent::factory()->create([
            'line_id' => $this->line->id,
            'type' => 'closure_passenger',
            'event_date' => '1938-05-01',
            'date_precision' => 'month',
            'section_from' => 'Saint-Cloud',
            'section_to' => 'Versailles',
            'source_url' => 'https://example.org/source',
            'description' => 'Fermeture partielle.',
        ]);

        $this->get("/lines/{$this->line->slug}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Lines/Show')
            ->where('line.network', 'principal')
            ->where('line.display_era_year', 2030)
            ->where('line.current_traffic', 'passenger')
            // Géométrie de l'ère affichée, synthèse par ère et liste brute des événements : hors de la page
            // (LineSheet::PAGE_OMITTED), dans la fiche complète de l'API, vérifiée ci-dessous.
            ->missing('segments')
            ->missing('era_summary')
            ->missing('events'));

        $sheet = app(LineSheet::class)->build($this->line->fresh());
        $this->assertSame(2030, $sheet['segments'][0]['era_year']);
        $this->assertSame([1960, 1980, 2030], $sheet['era_summary']->pluck('year')->all());
        $this->assertSame('closure_passenger', $sheet['events'][1]['type']);
        $this->assertSame('month', $sheet['events'][1]['date_precision']);
        $this->assertSame('Saint-Cloud', $sheet['events'][1]['section_from']);
        $this->assertSame('Versailles', $sheet['events'][1]['section_to']);
        $this->assertSame('https://example.org/source', $sheet['events'][1]['source_url']);
        $this->getJson("/api/v1/lines/{$this->line->slug}")
            ->assertOk()
            ->assertJsonPath('data.era_summary.0.year', 1960)
            ->assertJsonPath('data.era_summary.2.year', 2030);

        $this->getJson("/api/v1/lines/{$this->line->slug}/history")
            ->assertOk()
            ->assertJsonPath('events.1.date_precision', 'month')
            ->assertJsonPath('events.1.section_from', 'Saint-Cloud')
            ->assertJsonPath('status_by_era.0.year', 1960)
            ->assertJsonPath('status_by_era.2.year', 2030);
    }

    public function test_line_pk_handles_discontinuous_routes(): void
    {
        // Tronçon disjoint → ST_LineMerge renvoie un MULTILINESTRING (avant :
        // HTTP 500 « line_locate_point: 1st arg isn't a line »).
        $this->segment($this->line, $this->currentEra, [[48.70, 2.00], [48.65, 1.95]]);

        $this->getJson("/api/v1/lines/{$this->line->slug}/pk?lat=48.85&lng=2.24")
            ->assertOk()
            ->assertJsonStructure(['pk_m', 'pk_label', 'total_m', 'snap' => ['lat', 'lng', 'distance_from_input_m']]);
    }

    public function test_search_always_returns_three_keys(): void
    {
        $this->getJson('/api/v1/search?q=a')
            ->assertOk()
            ->assertExactJson(['lines' => [], 'stations' => [], 'materials' => [], 'accidents' => [], 'communes' => [], 'pk_positions' => []]);
    }

    public function test_unknown_era_returns_clean_404_before_streaming(): void
    {
        // Avant : en-têtes 200 + début de JSON envoyés, puis exception en plein flux.
        $this->getJson('/api/v1/eras/1955/lines.geojson')->assertNotFound();
    }

    public function test_map_data_exposes_traffic_status_and_network(): void
    {
        $response = $this->get('/api/v1/eras/2030/lines.geojson')->assertOk();
        $json = json_decode($response->streamedContent(), true);

        $props = $json['features'][0]['properties'];
        $this->assertSame('passenger', $props['traffic_type']);
        $this->assertSame('in_service', $props['status']);
        $this->assertSame('principal', $props['network']);
        $this->assertArrayHasKey('source', $props);

        // Tuile MVT couvrant le tracé : contient les attributs de style.
        $tile = $this->get('/api/v1/eras/2030/tiles/8/129/88.mvt')->assertOk()->getContent();
        foreach (['traffic_type', 'status', 'network', 'source'] as $attr) {
            $this->assertStringContainsString($attr, $tile);
        }
    }
}
