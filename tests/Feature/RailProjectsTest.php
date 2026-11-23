<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Services\RailProjects;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * « Projets : réouvertures et lignes nouvelles » : catalogue versionné lu en base seulement pour les fiches de ligne,
 * page /projects et `GET /api/v1/projects`.
 */
class RailProjectsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_the_page_lists_the_projects_with_their_stages_and_sources(): void
    {
        $this->get('/projects')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Network/Projects')
            ->has('data.projects')
            ->has('data.regions')
            ->where('data.states', RailProjects::STATES)
            ->has('data.projects.0.stages.0.source.url')
            ->has('data.projects.0.stages.0.precision'));
    }

    public function test_a_project_links_to_its_line_sheet_only_when_the_sheet_exists(): void
    {
        $project = collect(RailProjects::catalogue())->first(fn (array $e): bool => ! empty($e['line_slugs']));
        $this->assertNotNull($project, 'au moins un projet doit désigner une fiche de ligne');
        $slug = $project['line_slugs'][0];

        $byId = fn (array $projects): array => array_column($projects, null, 'id')[$project['id']];
        $without = $byId((array) $this->getJson('/api/v1/projects')->assertOk()->json('data.projects'));
        $this->assertSame([], $without['lines']);

        Cache::flush();
        Line::factory()->create(['slug' => $slug, 'name' => 'Ligne de test']);
        $with = $byId((array) $this->getJson('/api/v1/projects')->json('data.projects'));
        $this->assertSame([['slug' => $slug, 'name' => 'Ligne de test']], $with['lines']);
    }

    public function test_planned_dates_are_never_presented_as_done(): void
    {
        $projects = $this->getJson('/api/v1/projects')->json('data.projects');
        $planned = 0;
        foreach ($projects as $p) {
            if ($p['planned_commissioning'] !== null) {
                $this->assertFalse($p['planned_commissioning']['done'], $p['id']);
                $this->assertSame('commissioning', $p['planned_commissioning']['kind'], $p['id']);
                $planned++;
            }
            if ($p['next_stage'] !== null) {
                $this->assertFalse($p['next_stage']['done'], $p['id']);
            }
        }
        $this->assertGreaterThan(0, $planned);
    }

    public function test_the_api_filters_by_state_kind_and_region(): void
    {
        $all = $this->getJson('/api/v1/projects')->assertOk();
        $this->assertSame(count($all->json('data.projects')), $all->json('meta.projects'));
        $this->assertNotEmpty($all->json('data.regions'));

        $region = $all->json('data.regions.0');
        $kind = $all->json('data.projects.0.kind');
        $filtered = $this->getJson('/api/v1/projects?kind='.$kind.'&region='.rawurlencode($region))->assertOk();
        foreach ($filtered->json('data.projects') as $p) {
            $this->assertSame($kind, $p['kind']);
            $this->assertContains($region, $p['regions']);
        }

        $state = $all->json('data.projects.0.state');
        foreach ($this->getJson('/api/v1/projects?state='.$state)->json('data.projects') as $p) {
            $this->assertSame($state, $p['state']);
        }
        $this->assertStringEndsWith('/projects', $filtered->json('links.web'));
    }

    public function test_the_api_rejects_an_unknown_state_or_kind(): void
    {
        $this->getJson('/api/v1/projects?state=zeppelin')->assertStatus(422)->assertJsonValidationErrors('state');
        $this->getJson('/api/v1/projects?kind=zeppelin')->assertStatus(422)->assertJsonValidationErrors('kind');
    }

    public function test_the_hand_checked_projects_are_listed(): void
    {
        $ids = array_column((array) $this->getJson('/api/v1/projects')->json('data.projects'), 'id');
        foreach (['angouleme-limoges', 'gpso-bordeaux-toulouse-dax', 'lyon-turin', 'lnpca', 'lnmp', 'laqueuille-le-mont-dore', 'serqueux-gisors'] as $id) {
            $this->assertContains($id, $ids);
        }
    }

    public function test_the_page_is_in_the_sitemap_and_linked_from_the_network_figures(): void
    {
        $this->get('/sitemap-pages-1.xml')->assertOk()->assertSee('/projects', false);
        $this->assertStringContainsString('/projects', (string) file_get_contents(base_path('resources/js/Pages/Network/Figures.svelte')));
    }
}
