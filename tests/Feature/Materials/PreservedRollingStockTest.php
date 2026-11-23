<?php

namespace Tests\Feature\Materials;

use App\Models\Material;
use App\Services\Materials\PreservedRollingStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Matériel préservé : section de la fiche, page des lieux et API REST (lecture seule, aucune table). */
class PreservedRollingStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Material::factory()->create(['slug' => '241-p', 'name' => '241 P', 'type' => 'locomotive_vapeur']);
        Material::factory()->create(['slug' => 'bb-9200', 'name' => 'BB 9200', 'type' => 'locomotive_electrique']);
        Material::factory()->create(['slug' => 'cc-7100', 'name' => 'CC 7100', 'type' => 'locomotive_electrique']);
    }

    public function test_a_sheet_lists_its_preserved_units_and_stays_ok_without_any(): void
    {
        $this->get('/materials/241-p')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Materials/Show')
            ->has('preserved', 4)
            ->where('preserved.0.number', '241 P 9')
            ->where('preserved.2.number', '241 P 17')
            ->where('preserved.2.protection.kind', 'classified')
            ->where('preserved.2.protection.date', '1990-03-27')
            ->where('preserved.2.protection.url', 'https://www.pop.culture.gouv.fr/notice/palissy/PM71000825'));

        Material::factory()->create(['slug' => 'serie-sans-exemplaire', 'name' => 'Série sans exemplaire']);
        $this->get('/materials/serie-sans-exemplaire')->assertOk()->assertInertia(fn (Assert $page) => $page->where('preserved', []));
    }

    public function test_the_page_lists_venues_with_their_pieces_and_the_coverage(): void
    {
        $this->get('/materials/preserved')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Materials/Preserved')
            ->where('types', PreservedRollingStock::VENUE_TYPES)
            ->where('figures.units', fn ($n) => $n > 300)
            ->where('figures.protected', fn ($n) => $n > 20)
            ->has('venues')
            ->has('sources.palissy'));
    }

    public function test_the_route_is_not_taken_for_a_material_slug(): void
    {
        $this->get('/materials/preserved')->assertInertia(fn (Assert $page) => $page->component('Materials/Preserved'));
    }

    public function test_the_api_gives_the_units_of_a_series_with_their_sources(): void
    {
        $this->getJson('/api/v1/materials/241-p/preserved')->assertOk()
            ->assertJsonPath('meta.material', '241-p')
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('data.2.number', '241 P 17')
            ->assertJsonPath('data.2.protection.ref', 'PM71000825')
            ->assertJsonPath('data.2.sources.0.key', 'wikipedia')
            ->assertJsonStructure(['data' => [['number', 'venue', 'where', 'state', 'protection', 'sources']], 'meta']);
        $this->getJson('/api/v1/materials/inconnue/preserved')->assertNotFound();
    }

    public function test_the_api_lists_the_venues_with_the_coverage_figures(): void
    {
        $this->getJson('/api/v1/materials/preserved')->assertOk()
            ->assertJsonStructure(['data' => [['slug', 'name', 'type', 'commune', 'tourist_railway', 'count', 'pieces']], 'meta' => ['venues', 'units', 'series', 'protected', 'with_place', 'others']])
            ->assertJsonPath('meta.venues', fn ($n) => $n > 100);
    }

    public function test_a_piece_shared_by_two_series_is_counted_once_per_venue(): void
    {
        $service = new PreservedRollingStock(null, [
            'venues' => ['v' => ['name' => 'Lieu', 'type' => 'museum', 'commune' => 'Ville', 'tourist_railway' => null]],
            'units' => [
                'bb-9200' => [['number' => 'BB 9200', 'venue' => 'v', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikidata', 'Q1']]]],
                'cc-7100' => [['number' => 'BB 9200', 'venue' => 'v', 'where' => null, 'state' => null, 'mh' => null, 'src' => [['wikidata', 'Q1']]]],
            ],
            'others' => [['number' => null, 'title' => 'wagon couvert', 'venue' => 'v', 'mh' => ['kind' => 'listed', 'date' => '2001-02-03', 'year' => 2001, 'ref' => 'PM01000001'], 'src' => [['palissy', 'PM01000001']]]],
        ]);
        $venues = $service->venues();
        $this->assertCount(1, $venues);
        $this->assertSame(2, $venues[0]['count']);
        $this->assertSame(['BB 9200', 'CC 7100'], array_column($venues[0]['pieces'][0]['series'], 'name'));
        $this->assertSame('https://www.pop.culture.gouv.fr/notice/palissy/PM01000001', $venues[0]['pieces'][1]['protection']['url']);
        $this->assertSame(1, $service->figures()['units']);
    }
}
