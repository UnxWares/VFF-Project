<?php

namespace Tests\Feature\Materials;

use App\Models\Line;
use App\Models\LineMaterial;
use App\Models\LineMaterialPeriod;
use App\Models\Material;
use App\Models\Station;
use App\Services\Materials\SourceCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\Support\InMemorySourceCache;
use Tests\Support\ReadsInertiaProps;
use Tests\TestCase;

/**
 * Périodes de circulation placées sur la portion de la ligne que la source nomme (MaterialPeriodPortion) :
 * relation citée par l'article de la série (arrêts et lignes connus, sortie de la ligne à la gare de jonction),
 * portion écrite dans la phrase, ligne entière, puis surfaces (fiches, REST, GraphQL, MCP) qui la servent.
 *
 * Réseau normand réduit, PK de SNCF Réseau (relevés le 23/11/2026) : Paris-Saint-Lazare - Le Havre (340000),
 * Mantes-la-Jolie - Cherbourg (366000), Lisieux - Trouville-Deauville, Le Havre-Graville - Tourville-les-Ifs.
 */
class MaterialPeriodPortionTest extends TestCase
{
    use MakesGraphQLRequests;
    use ReadsInertiaProps;
    use RefreshDatabase;

    private InMemorySourceCache $cache;

    private Line $paris;

    private Line $cherbourg;

    private Line $trouville;

    private Line $rolleville;

    /** @var array<string, Station> */
    private array $stations = [];

    private Material $z56600;

    private Material $x73500;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->paris = Line::factory()->create(['slug' => 'rfn-340000', 'code' => '340000', 'name' => 'Ligne de Paris-Saint-Lazare au Havre']);
        $this->cherbourg = Line::factory()->create(['slug' => 'rfn-366000', 'code' => '366000', 'name' => 'Ligne de Mantes-la-Jolie à Cherbourg', 'wikipedia_title' => 'Ligne de Mantes-la-Jolie à Cherbourg']);
        $this->trouville = Line::factory()->create(['slug' => 'rfn-379000', 'code' => '379000', 'name' => 'Ligne de Lisieux à Trouville - Deauville']);
        $this->rolleville = Line::factory()->create(['slug' => 'rfn-345000', 'code' => '345000', 'name' => 'Ligne du Havre-Graville à Tourville-les-Ifs', 'wikipedia_title' => 'Ligne du Havre-Graville à Tourville-les-Ifs']);
        $this->station('Paris-St-Lazare', [[$this->paris, 199]]);
        $this->station('Mantes-la-Jolie', [[$this->paris, 57275], [$this->cherbourg, 57275]]);
        $this->station('Rouen-Rive-Droite', [[$this->paris, 139468]]);
        $this->station('Le Havre-Graville', [[$this->paris, 225234], [$this->rolleville, 0]]);
        $this->station('Le Havre', [[$this->paris, 227900]]);
        $this->station('Évreux-Embranchement', [[$this->cherbourg, 107325]]);
        $this->station('Lisieux', [[$this->cherbourg, 190107], [$this->trouville, 0]]);
        $this->station('Caen', [[$this->cherbourg, 238908]]);
        $this->station('Cherbourg-en-Cotentin', [[$this->cherbourg, 370260]]);
        $this->station('Trouville-Deauville', [[$this->trouville, 29350]]);
        $this->station('Montivilliers', [[$this->rolleville, 6200]]);
        $this->station('Rolleville', [[$this->rolleville, 12400]]);
        $this->station('Tourville-les-Ifs', [[$this->rolleville, 28600]]);

        $this->z56600 = Material::factory()->create(['slug' => 'z-56600', 'name' => 'Z 56600', 'type' => 'automotrice', 'wikipedia_fr' => 'Z 56600', 'wikidata_id' => 'Q9000001', 'in_service_from' => 2019, 'in_service_to' => null]);
        $this->x73500 = Material::factory()->create(['slug' => 'x-73500', 'name' => 'X 73500', 'type' => 'autorail', 'wikipedia_fr' => 'X 73500', 'wikidata_id' => 'Q9000002', 'in_service_from' => 1999, 'in_service_to' => null]);

        // Regio 2N, révision 241123224 : relations lues par l'import du matériel (MaterialRelations), arrêts identifiés.
        $caen = $this->route('Paris-Saint-Lazare - Caen - Cherbourg', ['Paris-St-Lazare', 'Caen', 'Cherbourg-en-Cotentin'], 'Paris - Caen - Cherbourg', '2020-02-17');
        $deauville = $this->route('Paris-Saint-Lazare - Lisieux - Trouville-Deauville', ['Paris-St-Lazare', 'Lisieux', 'Trouville-Deauville'], 'Paris - Lisieux - Trouville - Deauville', '2020-02-17');
        $this->link($this->paris, $this->z56600, [$caen, $deauville]);
        $this->link($this->cherbourg, $this->z56600, [$caen, $deauville]);
        $this->link($this->trouville, $this->z56600, [$deauville]);
        $this->link($this->rolleville, $this->x73500, [['kind' => 'wikipedia_material_article', 'quote' => 'Le Havre – Rolleville', 'article' => 'X 73500', 'revid' => 240915381]]);
        $this->link($this->cherbourg, $this->x73500, [['kind' => 'wikipedia_line_article', 'quote' => 'X 73500', 'article' => 'Ligne de Mantes-la-Jolie à Cherbourg', 'revid' => 241344631]]);

        $this->cache = new InMemorySourceCache;
        $this->app->instance(SourceCache::class, $this->cache);
        // X 73500, révision 240915381, « Relations effectuées > TER > Normandie » : le libellé du lien nomme la portion.
        $this->cache->tables['wikipedia_materials'] = ['X 73500' => ['title' => 'X 73500', 'revid' => 240915381, 'sections' => [
            ['title' => 'Relations effectuées > TER > Normandie', 'level' => 3, 'text' => "* [[Ligne du Havre-Graville à Tourville-les-Ifs|Le Havre – Rolleville]] (jusqu'en {{Date|septembre 2024}})"],
        ]]];
        // Ligne de Mantes-la-Jolie à Cherbourg, révision 241344631 (phrase réduite) : une vitesse entre deux gares n'est pas une portion.
        $this->cache->tables['wikipedia_lines'] = ['Ligne de Mantes-la-Jolie à Cherbourg' => ['title' => 'Ligne de Mantes-la-Jolie à Cherbourg', 'revid' => 241344631, 'sections' => [
            ['title' => 'Matériel roulant', 'level' => 2, 'text' => 'Depuis 2001, des [[X 73500]] aptes à 140 km/h entre Lisieux et Caen assurent les omnibus.'],
        ]]];
        $this->cache->tables['vehicle_usage_periods'] = ['rows' => []];
    }

    /** @param list<array{0: Line, 1: int}> $onLines */
    private function station(string $name, array $onLines): void
    {
        $station = Station::factory()->create(['name' => $name, 'slug' => 'gare-'.str($name)->slug()]);
        foreach ($onLines as [$line, $pk]) {
            DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $line->id, 'method' => 'sncf_pk',
                'pk_m' => $pk, 'pk_source' => 'sncf', 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->stations[$name] = $station;
    }

    /**
     * Preuve d'un lien qui cite une relation datée (forme de MaterialRelations).
     *
     * @param  list<string>  $stops
     * @return array<string, mixed>
     */
    private function route(string $label, array $stops, string $quote, string $from): array
    {
        $ends = array_map(fn (string $s): array => ['id' => $this->stations[$s]->id, 'name' => $s], $stops);

        return [
            'kind' => 'wikipedia_material_article', 'quote' => $quote, 'article' => 'Regio 2N', 'revid' => 241123224,
            'section' => 'Dessertes prévues et commandes', 'url' => 'https://fr.wikipedia.org/w/index.php?title=Regio_2N&oldid=241123224',
            'route' => ['kind' => 'relation', 'label' => $label, 'from' => $ends[0], 'to' => $ends[count($ends) - 1], 'stops' => $ends, 'service' => null, 'era_year' => 2030],
            'period' => ['from' => ['date' => $from, 'precision' => 'day'], 'to' => null, 'confidence' => 'high', 'excerpt' => 'depuis le 17 février 2020, sur les lignes '.$quote],
        ];
    }

    /** @param list<array<string, mixed>> $evidence */
    private function link(Line $line, Material $material, array $evidence): void
    {
        LineMaterial::create(['line_id' => $line->id, 'material_id' => $material->id, 'source' => 'wikipedia', 'confidence' => 'medium',
            'source_url' => 'https://fr.wikipedia.org/w/index.php?oldid=1', 'evidence' => $evidence]);
    }

    /** @return list<array{0: ?string, 1: ?string, 2: ?string, 3: ?int, 4: ?int}> portion, relation, gares, PK */
    private function portions(Line $line, Material $material): array
    {
        return LineMaterialPeriod::query()->whereIn('line_material_id', LineMaterial::query()->where('line_id', $line->id)->where('material_id', $material->id)->select('id'))
            ->with(['fromStation', 'toStation'])->orderBy('id')->get()
            ->map(fn (LineMaterialPeriod $p): array => [$p->section_location, $p->relation['label'] ?? null, $p->fromStation?->name.' > '.$p->toStation?->name, $p->located_pk_start_m, $p->located_pk_end_m])->all();
    }

    public function test_a_cited_relation_is_placed_from_its_stops_and_the_junctions_where_it_leaves_the_line(): void
    {
        $this->artisan('vff:import-material-periods', ['--offline' => true])
            ->expectsOutputToContain('Portions des périodes sûres : 4 entre deux gares, 0 par un point remarquable, 2 sur la ligne entière, 0 nommées mais introuvables, 1 sans portion nommée.')
            ->assertSuccessful();

        // Paris - Caen - Cherbourg quitte la ligne de Paris au Havre à Mantes-la-Jolie ; Paris - Lisieux - Trouville aussi.
        $this->assertSame([
            ['stations', 'Paris-Saint-Lazare - Caen - Cherbourg', 'Paris-St-Lazare > Mantes-la-Jolie', 199, 57275],
            ['stations', 'Paris-Saint-Lazare - Lisieux - Trouville-Deauville', 'Paris-St-Lazare > Mantes-la-Jolie', 199, 57275],
        ], $this->portions($this->paris, $this->z56600));
        // Sur Mantes - Cherbourg : la ligne entière pour Cherbourg ; de Mantes-la-Jolie à Lisieux pour Trouville, qui
        // sort de la ligne aux deux bouts (une jonction par extrémité : Mantes-la-Jolie et Lisieux).
        $this->assertSame([
            ['line', 'Paris-Saint-Lazare - Caen - Cherbourg', 'Mantes-la-Jolie > Cherbourg-en-Cotentin', 57275, 370260],
            ['stations', 'Paris-Saint-Lazare - Lisieux - Trouville-Deauville', 'Mantes-la-Jolie > Lisieux', 57275, 190107],
        ], $this->portions($this->cherbourg, $this->z56600));
        $this->assertSame([['line', 'Paris-Saint-Lazare - Lisieux - Trouville-Deauville', 'Lisieux > Trouville-Deauville', 0, 29350]], $this->portions($this->trouville, $this->z56600));
    }

    public function test_a_portion_written_in_the_sentence_is_placed_on_its_stations_and_a_speed_is_not_a_portion(): void
    {
        $this->artisan('vff:import-material-periods', ['--offline' => true])->assertSuccessful();

        // « Le Havre – Rolleville » : Le Havre-Graville, origine de la ligne, à Rolleville (PK 0 à 12,4 km sur 28,6 km).
        $this->assertSame([['stations', null, 'Le Havre-Graville > Rolleville', 0, 12400]], $this->portions($this->rolleville, $this->x73500));
        $period = LineMaterialPeriod::query()->where('section_location', 'stations')->where('located_pk_end_m', 12400)->firstOrFail();
        $this->assertSame(['2024-09-01', 'month', 'high'], [$period->end_date?->toDateString(), $period->end_precision, $period->confidence]);
        // « aptes à 140 km/h entre Lisieux et Caen » : la période reste sur la ligne, sans portion.
        $this->assertSame([[null, null, ' > ', null, null]], $this->portions($this->cherbourg, $this->x73500));
    }

    public function test_the_portion_does_not_change_the_fingerprint_and_a_replay_keeps_it(): void
    {
        $this->artisan('vff:import-material-periods', ['--offline' => true])->assertSuccessful();
        $before = LineMaterialPeriod::query()->orderBy('id')->pluck('fingerprint', 'id')->all();
        LineMaterialPeriod::query()->update(['section_location' => null, 'from_station_id' => null, 'to_station_id' => null, 'located_pk_start_m' => null, 'located_pk_end_m' => null]);

        $this->artisan('vff:import-material-periods', ['--offline' => true])
            ->expectsOutputToContain('Périodes : 0 créée(s), 6 mise(s) à jour, 0 retirée(s).')
            ->assertSuccessful();

        $this->assertSame($before, LineMaterialPeriod::query()->orderBy('id')->pluck('fingerprint', 'id')->all());
        $this->assertSame(6, LineMaterialPeriod::query()->whereNotNull('section_location')->count());
    }

    public function test_sheets_rest_graphql_and_mcp_serve_the_portion(): void
    {
        $this->artisan('vff:import-material-periods', ['--offline' => true])->assertSuccessful();
        $portion = ['location' => 'stations', 'from' => ['id' => $this->stations['Le Havre-Graville']->id, 'name' => 'Le Havre-Graville'],
            'to' => ['id' => $this->stations['Rolleville']->id, 'name' => 'Rolleville'], 'pk_start_m' => 0, 'pk_end_m' => 12400];

        $this->getWithDeferredProps('/lines/rfn-345000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('materials.0.slug', 'x-73500')
            ->where('materials.0.periods.0.portion', $portion));
        // Nom lisible : « St-Lazare » s'écrit « Saint-Lazare » (RelationRouter::readable).
        $this->get('/materials/z-56600')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('lines', fn ($lines): bool => $this->rows($lines)->firstWhere('slug', 'rfn-340000')['periods'][0]['portion']['from']['name'] === 'Paris-Saint-Lazare'));

        $this->getJson('/api/v1/lines/rfn-345000/materials')->assertOk()->assertJsonPath('data.0.periods.0.portion', $portion);
        /** @var list<array{periods: list<array<string, mixed>>}> $restLines */
        $restLines = $this->getJson('/api/v1/materials/x-73500')->assertOk()->json('data.lines');
        $this->assertSame(['Rolleville'], collect($restLines)->flatMap(fn (array $l): array => $l['periods'])->pluck('portion.to.name')->filter()->values()->all());

        $this->graphQL('{ line(slug: "rfn-345000") { material_links { periods { portion { location from { id name } to { id name } pk_start_m pk_end_m } } } } }')
            ->assertGraphQLErrorFree()
            ->assertJsonPath('data.line.material_links.0.periods.0.portion', $portion);
        $this->graphQL('{ line(slug: "rfn-366000") { material_links { material { slug } periods { portion { location } } } } }')
            ->assertGraphQLErrorFree()
            ->assertJsonPath('data.line.material_links', fn (array $links): bool => collect($links)->firstWhere('material.slug', 'x-73500')['periods'] === [['portion' => null]]);

        $mcp = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'line_materials', 'arguments' => ['line' => '345000']]])
            ->assertOk()->json('result');
        $this->assertFalse($mcp['isError']);
        $this->assertSame($portion, json_decode($mcp['content'][0]['text'], true)['materials'][0]['periods'][0]['portion']);
    }
}
