<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use App\Services\Heritage\HeritageIndex;
use App\Services\Heritage\StationHeritage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Page /heritage et GET /api/v1/heritage : bâtiments voyageurs, ouvrages de gare et ouvrages d'art classés, inscrits ou
 * labellisés (pas les gares seulement recensées), avec architecte, époque, style, région, image libre ; mêmes filtres
 * et mêmes tris en REST et dans la page.
 */
class HeritageIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Era::factory()->current()->create(['year' => 2030]);

        $limoges = Station::factory()->create(['slug' => 'limoges-benedictins', 'name' => 'Limoges-Bénédictins', 'city' => 'Limoges', 'department_code' => '87']);
        $metz = Station::factory()->create(['slug' => 'metz-ville', 'name' => 'Metz-Ville', 'city' => 'Metz', 'department_code' => '57']);
        $chartres = Station::factory()->create(['slug' => 'chartres', 'name' => 'Chartres', 'city' => 'Chartres', 'department_code' => '28']);
        $recensee = Station::factory()->create(['slug' => 'recensee', 'name' => 'Recensée', 'city' => 'Ailleurs', 'department_code' => '87']);

        $this->row($limoges, ['protection' => 'registered', 'protection_date' => '1975-01-15', 'ref' => 'PA00100349', 'built_from' => 1925, 'architects' => [['name' => 'Roger Gonthier', 'role' => 'architecte']],
            'image_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/Gare.jpg/500px-Gare.jpg', 'image_license' => 'CC BY-SA 4.0', 'image_author' => 'Exemple']);
        $this->row($metz, ['protection' => 'registered', 'protection_date' => '1975-02-03', 'scope' => 'partial', 'ref' => 'PA00106780', 'built_from' => 1904, 'style' => 'architecture Art nouveau',
            'architects' => [['name' => 'Jürgen Kröger', 'role' => 'architecte'], ['name' => 'JURGEN KROGER', 'role' => 'architecte']]]);
        $this->row($chartres, ['protection' => 'acr', 'ref' => 'Q1', 'ref_kind' => 'Q', 'ref_url' => 'https://www.wikidata.org/wiki/Q1', 'source' => 'wikidata']);
        $this->row($chartres, ['element' => 'works', 'protection' => 'classified', 'ref' => 'PA00000001', 'built_period' => '2e quart 19e siècle', 'architects' => [['name' => 'Roger GONTHIER', 'role' => 'architecte']]]);
        $this->row($recensee, ['protection' => 'inventory', 'ref' => 'IA87000001', 'ref_kind' => 'IA', 'source' => 'wikidata']);

        DB::statement("INSERT INTO communes (code_insee, type, nom, nom_norm, departement, population, geom, created_at, updated_at)
            VALUES ('15014', 'commune-actuelle', 'Anglards-de-Saint-Flour', 'anglards-de-saint-flour', '15', 100, ST_SetSRID(ST_MakePoint(3.17, 44.97), 4326), now(), now())");
        $line = Line::factory()->create(['slug' => 'rfn-722000', 'name' => 'Ligne de Béziers à Neussargues']);
        $garabit = DB::table('structures')->insertGetId(['kind' => 'viaduct', 'name' => 'viaduc de Garabit', 'heritage' => 'classified', 'merimee_ref' => 'PA00093681', 'built_on' => '1880-01-01',
            'lat' => 44.97, 'lon' => 3.17, 'sources' => '[]', 'match_key' => 'Q1333442', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            'image' => json_encode(['thumb_url' => 'https://thumb.wikimedia.org/g.jpg', 'source_url' => 'https://commons.wikimedia.org/wiki/File:G.jpg', 'license' => 'CC BY-SA 3.0', 'author' => null, 'rights_status' => 'open_license'])]);
        DB::table('line_structures')->insert(['line_id' => $line->id, 'structure_id' => $garabit, 'offset_m' => 3, 'era_years' => '[2030]', 'created_at' => now(), 'updated_at' => now()]);
        // Pont non protégé, image sous droits non libres : jamais montrés.
        DB::table('structures')->insert(['kind' => 'bridge', 'name' => 'pont ordinaire', 'lat' => 45.0, 'lon' => 3.0, 'sources' => '[]', 'match_key' => 'x', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('structures')->insert(['kind' => 'tunnel', 'name' => 'tunnel inscrit', 'heritage' => 'registered', 'lat' => 45.0, 'lon' => 3.0, 'sources' => '[]', 'match_key' => 'y', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            'image' => json_encode(['thumb_url' => 'https://thumb.wikimedia.org/t.jpg', 'license' => 'Tous droits réservés', 'rights_status' => 'restricted'])]);
    }

    /** @param  array<string, mixed>  $a */
    private function row(Station $station, array $a): void
    {
        $a['architects'] = json_encode($a['architects'] ?? []);
        DB::table('station_heritage')->insert($a + ['station_id' => $station->id, 'element' => 'building', 'ref_kind' => 'PA', 'source' => 'merimee', 'match_method' => 'wikidata']);
    }

    /** @return list<array<string, mixed>> */
    private function items(string $query = ''): array
    {
        return $this->getJson('/api/v1/heritage'.$query)->assertOk()->json('data.items');
    }

    public function test_only_protected_or_labelled_objects_are_listed_not_the_inventory_or_plain_structures(): void
    {
        $names = array_column($this->items(), 'name');

        $this->assertEqualsCanonicalizing(['Chartres', 'Chartres', 'Limoges-Bénédictins', 'Metz-Ville', 'tunnel inscrit', 'viaduc de Garabit'], $names);
        $this->assertNotContains('Recensée', $names);
        $this->assertNotContains('pont ordinaire', $names);
    }

    public function test_a_station_item_carries_its_facts_and_a_structure_its_line_and_nearest_commune(): void
    {
        $byName = array_column($this->items(), null, 'name');

        $limoges = $byName['Limoges-Bénédictins'];
        $this->assertSame(['registered', '1975-01-15', 'building'], [$limoges['protection'], $limoges['protection_date'], $limoges['object']]);
        $this->assertSame(['Roger Gonthier'], $limoges['architects']);
        $this->assertSame(['Limoges', '87', 'Nouvelle-Aquitaine', '1900_1944'], [$limoges['commune'], $limoges['department'], $limoges['region'], $limoges['era']]);
        $this->assertSame('CC BY-SA 4.0', $limoges['image']['license']);
        $this->assertSame('limoges-benedictins', $limoges['station']['slug']);
        $this->assertNull($limoges['line']);

        $garabit = $byName['viaduc de Garabit'];
        $this->assertSame(['viaduct', 'classified', 1880], [$garabit['object'], $garabit['protection'], $garabit['built']['year']]);
        $this->assertSame(['Anglards-de-Saint-Flour', 'Auvergne-Rhône-Alpes'], [$garabit['commune'], $garabit['region']]);
        $this->assertSame(['slug' => 'rfn-722000', 'name' => 'Ligne de Béziers à Neussargues'], $garabit['line']);
        $this->assertSame('https://www.pop.culture.gouv.fr/notice/merimee/PA00093681', $garabit['reference']['url']);
        $this->assertSame('CC BY-SA 3.0', $garabit['image']['license']);
        $this->assertNull($byName['tunnel inscrit']['image'], 'une image sous droits réservés n\'est jamais montrée');
    }

    public function test_the_style_prefix_is_dropped_and_spellings_of_an_architect_that_differ_by_case_are_merged(): void
    {
        $facets = $this->getJson('/api/v1/heritage')->assertOk()->json('data.facets');

        $this->assertSame([['name' => 'Art nouveau', 'count' => 1]], $facets['styles']);
        $this->assertSame(
            [['name' => 'Jürgen Kröger', 'count' => 1], ['name' => 'Roger Gonthier', 'count' => 2]],
            $facets['architects'],
            'GONTHIER et Gonthier : un seul architecte, et un monument cité deux fois pour lui ne compte qu\'une fois',
        );
        $this->assertSame(['classified', 'registered', 'acr'], array_column($facets['protections'], 'key'));
        $this->assertEquals(['building' => 3, 'works' => 1, 'viaduct' => 1, 'tunnel' => 1], array_column($facets['objects'], 'count', 'key'));
    }

    public function test_filters_combine_and_each_returns_the_expected_objects(): void
    {
        $this->assertSame(['Chartres', 'viaduc de Garabit'], array_column($this->items('?protection=classified'), 'name'));
        $this->assertSame(['viaduc de Garabit'], array_column($this->items('?object=viaduct'), 'name'));
        $this->assertSame(['Metz-Ville'], array_column($this->items('?style=Art%20nouveau'), 'name'));
        $this->assertSame(['Chartres', 'Limoges-Bénédictins'], array_column($this->items('?architect=Roger%20Gonthier'), 'name'));
        $this->assertSame(['Metz-Ville'], array_column($this->items('?region=Grand%20Est'), 'name'));
        $this->assertSame(['Limoges-Bénédictins', 'Metz-Ville'], array_column($this->items('?era=1900_1944'), 'name'));
        $this->assertSame(['Chartres'], array_column($this->items('?era=before_1850&protection=classified'), 'name'), '« 2e quart 19e siècle » : milieu estimé à 1837, donc avant 1850');
        $this->assertSame(['viaduc de Garabit'], array_column($this->items('?era=1850_1899'), 'name'));
    }

    public function test_the_period_text_dates_an_object_roughly_for_the_era_filter(): void
    {
        $this->assertSame(1837, HeritageIndex::periodYear('2e quart 19e siècle'));
        $this->assertSame(1875, HeritageIndex::periodYear('2e moitié 19e siècle'));
        $this->assertSame(1950, HeritageIndex::periodYear('20e siècle'));
        $this->assertNull(HeritageIndex::periodYear(null));
        $this->assertNull(HeritageIndex::periodYear('époque inconnue'));
        $this->assertSame(
            ['unknown', 'before_1850', '1850_1899', '1900_1944', '1945_1999', 'from_2000'],
            array_map([HeritageIndex::class, 'era'], [null, 1849, 1850, 1900, 1945, 2000]),
        );
    }

    public function test_sorts_put_unknown_dates_last(): void
    {
        $this->assertSame(['Metz-Ville', 'Limoges-Bénédictins'], array_slice(array_column($this->items('?sort=protection_date'), 'name'), 0, 2), 'la plus récente d\'abord');
        $built = array_column($this->items('?sort=built'), 'name');
        $this->assertSame(['Chartres', 'viaduc de Garabit', 'Metz-Ville', 'Limoges-Bénédictins'], array_slice($built, 0, 4), '1837 (Chartres, ouvrage), 1880, 1904, 1925');
    }

    public function test_unknown_filter_values_are_refused_with_422(): void
    {
        foreach (['protection=inventory', 'object=station', 'era=2024', 'sort=random'] as $query) {
            $this->getJson('/api/v1/heritage?'.$query)->assertStatus(422);
        }
    }

    public function test_the_page_gets_the_same_data_and_the_api_links_to_it(): void
    {
        $this->get('/heritage')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Heritage/Index')
            ->has('data.items', 6)
            ->has('data.facets.architects', 2)
            ->where('data.attribution', fn ($v): bool => str_contains((string) $v, 'Licence Ouverte 2.0')));

        $this->getJson('/api/v1/heritage')->assertJsonPath('meta.total', 6)->assertJsonPath('links.web', url('/heritage'));
    }

    public function test_the_page_is_empty_not_broken_without_the_heritage_table(): void
    {
        DB::statement('DROP TABLE station_heritage');
        Cache::flush();
        (new \ReflectionProperty(StationHeritage::class, 'ready'))->setValue(null, null);
        $this->get('/heritage')->assertOk()->assertInertia(fn (Assert $page) => $page->has('data.items', 2));
    }
}
