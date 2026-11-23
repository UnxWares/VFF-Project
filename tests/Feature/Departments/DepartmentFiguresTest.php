<?php

namespace Tests\Feature\Departments;

use App\Models\Accident;
use App\Models\Line;
use App\Models\LineSegment;
use App\Services\Departments\DepartmentFigures;
use App\Services\Seo\SitemapBuilder;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Fiches par département : calcul (longueurs par décennie, part fermée, gares, lignes, dates, accidents), lecture en
 * cache sans calcul, page, redirection vers l'adresse canonique, 404 et 503, API REST et plan du site.
 *
 * Un département « 75 » en rectangle autour du mini-réseau (la ligne de Saint-Lazare à Versailles, ouverte en 1960 et
 * aujourd'hui), une seconde ligne ouverte en 1960 et abandonnée aujourd'hui.
 */
class DepartmentFiguresTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->buildRailNetwork();
        DB::table('communes')->insert([
            'code_insee' => '99100', 'type' => 'commune-actuelle', 'nom' => 'Testville', 'nom_norm' => 'testville', 'departement' => '75',
            'population' => 12000, 'created_at' => now(), 'updated_at' => now(), 'geom' => DB::raw('ST_SetSRID(ST_MakePoint(2.25, 48.87), 4326)'),
        ]);
        DB::table('commune_contours')->insert([
            'code_insee' => '99100', 'nom' => 'Testville', 'departement' => '75', 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw("ST_Multi(ST_GeomFromText('POLYGON((2.00 48.70, 2.50 48.70, 2.50 48.95, 2.00 48.95, 2.00 48.70))', 4326))"),
        ]);
        DB::table('commune_rail')->insert(['code_insee' => '99100', 'stations_count' => 1, 'lines_count' => 2, 'built_at' => now()]);

        // Seconde ligne : ouverte en 1960, abandonnée aujourd'hui (voie en place).
        $closed = Line::factory()->create(['slug' => 'ligne-fermee', 'name' => 'Ligne fermée', 'code' => '888 000']);
        $geom = LineString::make([Point::makeGeodetic(48.90, 2.10), Point::makeGeodetic(48.90, 2.30)]);
        LineSegment::factory()->create(['line_id' => $closed->id, 'era_id' => $this->pastEra->id, 'geom' => $geom, 'status' => 'in_service', 'source' => 'osm']);
        LineSegment::factory()->create(['line_id' => $closed->id, 'era_id' => $this->currentEra->id, 'geom' => $geom, 'status' => 'abandoned', 'source' => 'osm']);
        $this->portionEvent($closed, 'closure', '1985-06-01', 48.90, 2.12, 2.28);
    }

    private function compute(): array
    {
        return (new DepartmentFigures)->referenceYear(1960)->refresh();
    }

    public function test_compute_gives_lengths_per_decade_and_the_closed_share(): void
    {
        $sheet = $this->compute()['sheets']['75'];

        $this->assertSame('75-paris', $sheet['department']['slug']);
        $this->assertSame([1960, 2030], array_column($sheet['decades'], 'year'));
        [$then, $now] = $sheet['decades'];
        // 1960 : les deux lignes en service ; aujourd'hui : seule la ligne de Saint-Lazare, l'autre est fermée avec sa voie.
        $this->assertGreaterThan(25, $then['open_km']);
        $this->assertGreaterThan(10, $now['open_km']);
        $this->assertLessThan($then['open_km'], $now['open_km']);
        $this->assertGreaterThan(10, $now['closed_km']);
        $this->assertSame(0, $then['closed_km']);
        $this->assertGreaterThan(10, $now['removed_km']);
        $this->assertSame($now['open_km'], $sheet['summary']['km_today']);
        $this->assertGreaterThan(0.3, $sheet['closed_share']);
        $this->assertLessThan(0.7, $sheet['closed_share']);
    }

    public function test_lines_carry_their_state_and_dates(): void
    {
        $lines = array_column($this->compute()['sheets']['75']['lines'], null, 'slug');

        $this->assertSame('open', $lines[$this->line->slug]['state']);
        $this->assertSame('closed', $lines['ligne-fermee']['state']);
        $this->assertSame('in_place', $lines['ligne-fermee']['track']);
        $this->assertSame('1985-06-01', $lines['ligne-fermee']['closed']['date']);
        $this->assertSame(1960, $lines['ligne-fermee']['km_year']);
        $this->assertSame('open', array_column($this->compute()['sheets']['75']['lines'], 'state')[0], 'les lignes en service d\'abord');
    }

    public function test_milestones_list_waves_and_the_first_opening(): void
    {
        $this->portionEvent($this->line, 'opening', '1839-08-02', 48.8763, 2.31, 2.34);
        $m = $this->compute()['sheets']['75']['milestones'];

        $this->assertSame('1839-08-02', $m['first_opening']['date']);
        $this->assertSame(1960, $m['peak']['year']);
        $this->assertSame(2030, $m['closure_waves'][0]['year']);
        $this->assertSame('ligne-fermee', $m['closure_waves'][0]['lines'][0]['slug']);
    }

    public function test_stations_and_communes_are_counted(): void
    {
        $sheet = $this->compute()['sheets']['75'];

        $this->assertSame(['99100-testville'], array_column($sheet['communes'], 'slug'));
        $this->assertSame('75', $sheet['department']['code']);
        $this->assertNotNull($sheet['map']['contour']);
        $this->assertContains('open', array_column($sheet['map']['lines'], 'state'));
        $this->assertContains('closed', array_column($sheet['map']['lines'], 'state'));
    }

    public function test_accidents_of_the_department_are_listed(): void
    {
        Accident::factory()->create(['slug' => '1950-01-01-testville', 'department_code' => '75', 'commune_name' => 'Testville', 'occurred_on' => '1950-01-01', 'deaths' => 3]);
        Accident::factory()->create(['slug' => '1951-01-01-ailleurs', 'department_code' => '78', 'occurred_on' => '1951-01-01']);
        $this->compute();

        $accidents = app(DepartmentFigures::class)->sheet('75', 'fr')['accidents'];
        $this->assertSame(1, $accidents['count']);
        $this->assertSame(3, $accidents['deaths']);
        $this->assertSame('1950-01-01-testville', $accidents['list'][0]['slug']);
    }

    public function test_without_a_computation_nothing_is_served_and_nothing_is_computed(): void
    {
        $this->assertNull(app(DepartmentFigures::class)->index());
        $this->get('/departments/75-paris')->assertNotFound();
        $this->get('/departments')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Departments/Index')->where('figures', null));
        $this->getJson('/api/v1/departments/75/history')->assertStatus(503);
        $this->getJson('/api/v1/stats/departments')->assertStatus(503);
        $this->assertNull(Cache::get(DepartmentFigures::INDEX_KEY), 'la lecture ne range rien');
    }

    public function test_the_page_redirects_to_the_canonical_address(): void
    {
        $this->compute();

        $this->get('/departments/75-paris')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Departments/Show')
            ->where('department.name', 'Paris')
            ->where('department.region', 'Île-de-France')
            ->has('decades', 2)
            ->has('map.contour')
            ->where('lines.0.state', 'open'));
        $this->get('/departments/75')->assertRedirect('/departments/75-paris')->assertStatus(301);
        $this->get('/departments/75-ancien-nom')->assertRedirect('/departments/75-paris');
        // Département sans rail calculé, code inexistant : 404.
        $this->get('/departments/48-lozere')->assertNotFound();
        $this->get('/departments/00-nulle-part')->assertNotFound();
    }

    public function test_the_index_page_and_the_rest_api(): void
    {
        $this->compute();

        $this->get('/departments')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Departments/Index')
            ->where('figures.departments.0.code', '75')
            ->where('figures.reference_year', 1960));

        $history = $this->getJson('/api/v1/departments/75/history')->assertOk();
        $history->assertJsonPath('data.department.slug', '75-paris')
            ->assertJsonPath('data.links.page', url('/departments/75-paris'))
            ->assertJsonMissingPath('data.map')
            ->assertJsonMissingPath('data.summary')
            ->assertJsonPath('data.decades.1.current', true);
        $this->getJson('/api/v1/departments/2a/history')->assertNotFound();
        $this->getJson('/api/v1/departments/00/history')->assertNotFound();

        $this->getJson('/api/v1/stats/departments')->assertOk()
            ->assertJsonPath('data.0.code', '75')
            ->assertJsonPath('data.0.links.history', url('/api/v1/departments/75/history'))
            ->assertJsonPath('meta.reference_year', 1960);
    }

    public function test_a_partial_computation_keeps_the_other_departments_in_the_index(): void
    {
        $this->compute();
        $before = app(DepartmentFigures::class)->index();
        // Un calcul pour un département sans rail ne retire rien.
        app(DepartmentFigures::class)->refresh(['48']);

        $this->assertSame($before['departments'], app(DepartmentFigures::class)->index()['departments']);
    }

    public function test_slug_and_code_helpers(): void
    {
        $this->assertSame('16-charente', DepartmentFigures::slug('16', 'Charente'));
        $this->assertSame('2A-corse-du-sud', DepartmentFigures::slug('2A', 'Corse-du-Sud'));
        $this->assertSame('16', DepartmentFigures::codeOf('16-charente'));
        $this->assertSame('2A', DepartmentFigures::codeOf('2a-corse-du-sud'));
        $this->assertSame('971', DepartmentFigures::codeOf('971'));
        $this->assertNull(DepartmentFigures::codeOf('charente'));
        $this->assertNull(DepartmentFigures::codeOf('1-un'));
    }

    public function test_the_sitemap_lists_only_computed_departments(): void
    {
        $this->assertContains('departments', SitemapBuilder::FAMILIES);
        $empty = (string) (new SitemapBuilder)->family('https://vff.test', 'departments', 1);
        $this->assertStringNotContainsString('/departments/', $empty);

        $this->compute();
        Cache::flush();
        $this->compute();
        $xml = (string) (new SitemapBuilder)->family('https://vff.test', 'departments', 1);
        $this->assertStringContainsString('https://vff.test/departments/75-paris', $xml);
    }
}
