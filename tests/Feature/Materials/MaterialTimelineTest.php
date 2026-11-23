<?php

namespace Tests\Feature\Materials;

use App\Models\Material;
use App\Services\Materials\MaterialTimeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Frise du matériel roulant : page, API REST, regroupement par traction et état d'une série selon l'année. */
class MaterialTimelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Material::factory()->create(['slug' => 'bb-9200', 'name' => 'BB 9200', 'type' => 'locomotive_electrique', 'in_service_from' => 1957, 'in_service_to' => 2015, 'units_built' => 92, 'builders' => [['name' => 'Jeumont-Schneider', 'wikidata_id' => null, 'units' => null]]]);
        Material::factory()->create(['slug' => 'tgv-sud-est', 'name' => 'TGV Sud-Est', 'type' => 'rame_tgv', 'in_service_from' => 1981, 'in_service_to' => null, 'service_status' => 'withdrawn']);
        Material::factory()->create(['slug' => 'bb-15000', 'name' => 'BB 15000', 'type' => 'locomotive_electrique', 'in_service_from' => 1971, 'in_service_to' => null, 'service_status' => 'in_service']);
        Material::factory()->create(['slug' => 'cc-72000', 'name' => 'CC 72000', 'type' => 'locomotive_diesel', 'in_service_from' => null, 'in_service_to' => null, 'service_status' => 'in_service']);
        Material::factory()->create(['slug' => 'z-5300', 'name' => 'Z 5300', 'type' => 'automotrice', 'in_service_from' => 1950, 'in_service_to' => 1990]);
        Material::factory()->create(['slug' => 'bimode-x', 'name' => 'Bimode X', 'type' => 'bimode', 'in_service_from' => 2010]);
    }

    public function test_the_page_lists_every_series_with_its_group_and_no_invented_dates(): void
    {
        $this->get('/materials/timeline')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Materials/Timeline')
            ->where('meta.total', 6)
            ->where('meta.dated', 5)
            ->where('meta.undated', 1)
            ->where('meta.first_year', 1950)
            ->where('focus', null)
            ->has('rows', 6)
            ->where('rows.0.slug', 'bb-15000')
            ->where('groups.0', 'vapeur'));
    }

    public function test_the_focus_series_is_kept_only_when_known(): void
    {
        $this->get('/materials/timeline?serie=bb-9200')->assertInertia(fn (Assert $page) => $page->where('focus', 'bb-9200'));
        $this->get('/materials/timeline?serie=inconnue')->assertInertia(fn (Assert $page) => $page->where('focus', null));
    }

    public function test_the_route_is_not_taken_for_a_material_slug(): void
    {
        $this->assertSame('/materials/timeline', route('materials.timeline', [], false));
        $this->getJson('/api/v1/materials/timeline')->assertOk()->assertJsonPath('meta.total', 6);
    }

    public function test_groups_merge_the_related_categories(): void
    {
        $this->assertSame('automoteur', MaterialTimeline::groupOf('bimode'));
        $this->assertSame('automoteur', MaterialTimeline::groupOf('automotrice'));
        $this->assertSame('voitures_wagons', MaterialTimeline::groupOf('wagon'));
        $this->assertSame('tgv', MaterialTimeline::groupOf('rame_tgv'));
        $this->assertSame('vapeur', MaterialTimeline::groupOf('locomotive_vapeur'));
    }

    public function test_the_api_filters_by_group_and_builder(): void
    {
        $this->getJson('/api/v1/materials/timeline?group=tgv')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'tgv-sud-est')->assertJsonPath('data.0.group', 'tgv');
        $this->getJson('/api/v1/materials/timeline?builder=JEUMONT')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'bb-9200');
        $this->getJson('/api/v1/materials/timeline?group=inconnu')->assertStatus(422)->assertJsonValidationErrors('group');
        $this->getJson('/api/v1/materials/timeline?in_service_at=1700')->assertStatus(422)->assertJsonValidationErrors('in_service_at');
    }

    public function test_in_service_at_separates_sure_from_possible(): void
    {
        /** @var list<array<string, mixed>> $data */
        $data = $this->getJson('/api/v1/materials/timeline?in_service_at=1990')->assertOk()->json('data');
        $states = array_column($data, 'in_service_state', 'slug');
        $this->assertSame(['bb-15000' => 'yes', 'bb-9200' => 'yes', 'tgv-sud-est' => 'maybe', 'z-5300' => 'yes'], $states);

        $this->assertSame('no', MaterialTimeline::stateIn(['in_service_from' => null, 'in_service_to' => null, 'service_status' => 'in_service'], 2000));
        $this->assertSame('no', MaterialTimeline::stateIn(['in_service_from' => 1957, 'in_service_to' => 2015, 'service_status' => null], 2016));
        $this->assertSame('no', MaterialTimeline::stateIn(['in_service_from' => 1957, 'in_service_to' => 2015, 'service_status' => null], 1956));
    }
}
