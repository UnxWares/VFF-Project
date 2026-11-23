<?php

namespace Tests\Feature\Search;

use App\Models\Accident;
use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Models\Material;
use App\Models\Station;
use App\Services\SearchResultsPage;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Page de résultats de la recherche (/search) : groupes et comptes, filtres par type, état et département,
 * pagination, passage trouvé, suggestion, adresse sans réglage inconnu et page non indexée.
 */
class SearchPageTest extends TestCase
{
    use RefreshDatabase;

    private Era $era;

    protected function setUp(): void
    {
        parent::setUp();
        $this->era = Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd’hui']);
    }

    private function line(string $name, string $code, string $status): Line
    {
        $line = Line::factory()->create(['name' => $name, 'code' => $code, 'alt_names' => null]);
        LineSegment::factory()->create([
            'line_id' => $line->id,
            'era_id' => $this->era->id,
            'geom' => LineString::make([Point::makeGeodetic(48.0, 2.0), Point::makeGeodetic(48.0, 2.1)]),
            'status' => $status,
            'traffic_type' => 'passenger',
            'is_heritage' => false,
        ]);

        return $line;
    }

    private function station(string $name, string $status = 'active', string $dept = '11'): Station
    {
        return Station::factory()->create([
            'name' => $name, 'city' => $name, 'status' => $status, 'source_status' => $status,
            'department_code' => $dept, 'provenance' => [],
        ]);
    }

    private function seedCatalog(): void
    {
        $this->line('Ligne de Saint-Hilaire à Landivy', '421000', 'in_service');
        $this->line('Ligne de Saint-Hilaire-au-Temple à Hagondange', '088000', 'abandoned');
        $this->station('Saint-Hilaire', 'active', '11');
        $this->station('St-Hilaire-de-Chaléons', 'declassified', '12');
        $this->station('Saint-Hilaire-du-Rosier', 'active', '12');
        Accident::factory()->create([
            'name' => ['fr' => 'Déraillement à Saint-Hilaire', 'en' => 'Saint-Hilaire derailment', 'de' => 'Entgleisung in Saint-Hilaire'],
            'commune_name' => 'Saint-Hilaire',
        ]);
        Material::factory()->create(['name' => 'BB 7600', 'slug' => 'bb-7600', 'designations' => null, 'fleet_numbers' => null]);
    }

    public function test_groups_counts_and_highlight(): void
    {
        $this->seedCatalog();

        $response = $this->get('/search?q=st+hilaire');

        $response->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Search/Index')
            ->where('query', 'st hilaire')
            ->where('searched', true)
            ->where('counts', ['line' => 2, 'station' => 3, 'accident' => 1, 'material' => 0, 'glossary' => 0, 'commune' => 0])
            ->where('total', 6)
            ->where('approximate', false)
            ->where('suggestion', null)
            ->has('groups', 3)
            ->where('groups.0.type', 'line')
            ->where('groups.1.type', 'station')
            ->where('groups.2.type', 'accident')
            ->where('groups.1.items.0.highlight.0', ['text' => 'Saint', 'mark' => true])
        );
    }

    public function test_type_view_paginates(): void
    {
        foreach (range(1, SearchResultsPage::PER_PAGE + 3) as $i) {
            $this->station("Villeneuve {$i}");
        }

        $this->get('/search?q=villeneuve&type=station&page=2')
            ->assertInertia(fn (Assert $page) => $page
                ->has('groups', 1)
                ->where('groups.0.total', SearchResultsPage::PER_PAGE + 3)
                ->where('groups.0.current_page', 2)
                ->where('groups.0.last_page', 2)
                ->where('groups.0.per_page', SearchResultsPage::PER_PAGE)
                ->has('groups.0.items', 3)
            );

        // Vue « tout » : un aperçu par groupe.
        $this->get('/search?q=villeneuve')
            ->assertInertia(fn (Assert $page) => $page->has('groups.0.items', SearchResultsPage::PREVIEW));

        // Page au-delà de la dernière : la dernière.
        $this->get('/search?q=villeneuve&type=station&page=99')
            ->assertInertia(fn (Assert $page) => $page->where('groups.0.current_page', 2));
    }

    public function test_state_and_department_filters_for_stations_and_lines(): void
    {
        $this->seedCatalog();

        $this->get('/search?q=hilaire&type=station&state=closed')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters', ['type' => 'station', 'state' => 'closed', 'dept' => ''])
                ->where('groups.0.total', 1)
                ->where('counts.station', 1)
                // Total de l'onglet « Tout », qui retire les filtres : sans eux.
                ->where('total', 6)
                ->where('groups.0.items.0.name', 'St-Hilaire-de-Chaléons')
                ->where('departments', [['code' => '12', 'name' => 'Aveyron', 'count' => 1]])
            );

        $this->get('/search?q=hilaire&type=station&dept=12')
            ->assertInertia(fn (Assert $page) => $page->where('groups.0.total', 2));

        $this->get('/search?q=hilaire&type=line&state=open')
            ->assertInertia(fn (Assert $page) => $page
                ->where('groups.0.total', 1)
                ->where('groups.0.items.0.state', 'open')
            );
    }

    public function test_filters_ignored_where_they_do_not_apply(): void
    {
        $this->seedCatalog();

        // État sur les accidents, département inconnu, type inconnu : sans effet.
        $this->get('/search?q=hilaire&type=accident&state=closed&dept=11')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters', ['type' => 'accident', 'state' => '', 'dept' => ''])
                ->where('groups.0.total', 1)
            );
        $this->get('/search?q=hilaire&type=nope&dept=ZZ')
            ->assertInertia(fn (Assert $page) => $page->where('filters', ['type' => '', 'state' => '', 'dept' => '']));
        $this->get('/search?q[]=x&page=abc')->assertOk();
    }

    public function test_line_code_and_suggestion(): void
    {
        $this->seedCatalog();

        $this->get('/search?q=421+000')
            ->assertInertia(fn (Assert $page) => $page
                ->where('counts.line', 1)
                ->where('groups.0.items.0.code_match', true)
            );

        $this->get('/search?q=hilaier')
            ->assertInertia(fn (Assert $page) => $page
                ->where('approximate', true)
                ->where('suggestion', 'Saint-Hilaire')
            );
    }

    public function test_short_or_empty_query_searches_nothing(): void
    {
        $this->get('/search')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex')
            ->assertInertia(fn (Assert $page) => $page->where('searched', false)->where('groups', []));
        $this->get('/search?q=a')->assertInertia(fn (Assert $page) => $page->where('searched', false));
    }
}
