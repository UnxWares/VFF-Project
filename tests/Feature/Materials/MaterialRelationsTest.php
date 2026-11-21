<?php

namespace Tests\Feature\Materials;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineMaterial;
use App\Models\LineMaterialPeriod;
use App\Models\Material;
use App\Models\Station;
use App\Services\Materials\LineMaterialLinker;
use App\Services\Materials\MaterialRelations;
use App\Services\Materials\RelationRouter;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Relations et itinéraires cités par l'article d'une série (MaterialRelations, RelationRouter), sur un petit
 * graphe écrit à la main (époque actuelle, longueurs en kilomètres) :
 *
 *   Paris ─395 (80)─ Dreux ─395 (60)─ Surdon ─430 (15)─ Argentan ─405 (110)─ Folligny ─405 (15)─ Granville
 *   Caen ─L1 (100)─ Mézidon        Caen ─L2a (50)─ Lisieux ─L2b (55)─ Mézidon   (deux itinéraires proches)
 */
class MaterialRelationsTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int|string, Line> codes numériques rangés en clés entières */
    private array $lines = [];

    /** @var array<string, Station> */
    private array $stations = [];

    private Era $era;

    protected function setUp(): void
    {
        parent::setUp();
        $this->era = Era::factory()->create(['year' => 2030, 'is_current' => true]);
        foreach (['395000', '430000', '405000', 'L1', 'L2a', 'L2b'] as $code) {
            $this->lines[$code] = Line::factory()->create(['code' => $code, 'name' => "Ligne {$code}", 'is_lgv' => false]);
        }
        $places = ['Paris-Montparnasse' => [2.32, 48.84], 'Dreux' => [1.37, 48.73], 'Surdon' => [0.17, 48.65], 'Argentan' => [-0.01, 48.74],
            'Folligny' => [-1.40, 48.83], 'Granville' => [-1.59, 48.84], 'Caen' => [-0.35, 49.18], 'Lisieux' => [0.22, 49.14], 'Mézidon' => [-0.07, 49.07]];
        foreach ($places as $name => [$lng, $lat]) {
            $this->stations[$name] = Station::factory()->create(['name' => $name, 'geom' => Point::makeGeodetic($lat, $lng)]);
        }
        $this->edge('Paris-Montparnasse', 'Dreux', '395000', 80);
        $this->edge('Dreux', 'Surdon', '395000', 60);
        $this->edge('Surdon', 'Argentan', '430000', 15);
        $this->edge('Argentan', 'Folligny', '405000', 110);
        $this->edge('Folligny', 'Granville', '405000', 15);
        $this->edge('Caen', 'Mézidon', 'L1', 100);
        $this->edge('Caen', 'Lisieux', 'L2a', 50);
        $this->edge('Lisieux', 'Mézidon', 'L2b', 55);
    }

    private function edge(string $from, string $to, string $line, int $km): void
    {
        $id = $this->lines[$line]->id;
        DB::table('station_edges')->insert([
            'era_id' => $this->era->id, 'from_station_id' => $this->stations[$from]->id, 'to_station_id' => $this->stations[$to]->id,
            'line_id' => $id, 'length_m' => $km * 1000, 'line_ids' => "{{$id}}", 'line_lengths' => '{'.($km * 1000).'}',
        ]);
    }

    private function relations(): MaterialRelations
    {
        return MaterialRelations::fromDatabase([
            'Ligne de Paris à Granville' => [
                'stops' => ['Paris-Montparnasse', 'Dreux', 'Surdon', 'Argentan', 'Folligny', 'Granville'],
                'lines' => ['395000', '430000', '405000'],
                'source' => 'https://fr.wikipedia.org/wiki/Ligne_de_Paris_%C3%A0_Granville',
            ],
        ]);
    }

    /**
     * Codes des lignes rattachées par une section d'article.
     *
     * @return list<array{line_id: int, evidence: array<string, mixed>}>
     */
    private function links(MaterialRelations $relations, string $text, string $section = 'Services effectués'): array
    {
        return $relations->links(
            ['title' => 'X 2800', 'revid' => 42, 'sections' => [['title' => $section, 'level' => 2, 'text' => $text]]],
            ['fast' => false, 'from' => 1960, 'to' => 1990],
            new \DateTimeImmutable('2026-11-20'),
        );
    }

    /** @param list<array{line_id: int, evidence: array<string, mixed>}> $links */
    private function codes(array $links): array
    {
        $codes = array_values(array_unique(array_map(fn (array $l): string => (string) Line::query()->whereKey($l['line_id'])->value('code'), $links)));
        sort($codes);

        return $codes;
    }

    public function test_an_itinerary_article_links_every_line_it_chains(): void
    {
        $relations = $this->relations();
        $links = $this->links($relations, "* [[Ligne de Paris à Granville|Paris - Granville]]\n");

        $this->assertSame(['395000', '405000', '430000'], $this->codes($links));
        $route = $links[0]['evidence']['route'];
        $this->assertSame('itinerary', $route['kind']);
        $this->assertSame('Ligne de Paris à Granville', $route['title']);
        $this->assertSame(['id' => $this->stations['Granville']->id, 'name' => 'Granville'], $route['to']);
        $this->assertSame('wikipedia_material_article', $links[0]['evidence']['kind']);
    }

    public function test_an_itinerary_cited_with_an_intermediate_stop_links_nothing(): void
    {
        $relations = $this->relations();
        $links = $this->links($relations, "* Axe régional sur la [[Ligne de Paris à Granville]] avec la [[Gare de Dreux]]\n");

        $this->assertSame([], $links, 'la phrase ne parle que de Paris - Dreux');
        $this->assertSame(1, $relations->stats()['itineraries_partial']);
    }

    public function test_an_unambiguous_relation_links_the_lines_of_the_shortest_path(): void
    {
        $links = $this->links($this->relations(), "* Relation Argentan - Granville\n* [[Gare de Dreux|Dreux]] \u{2013} [[Gare de Granville|Granville]] (X4790)\n");

        $this->assertSame(['395000', '405000', '430000'], $this->codes($links));
        $argentan = array_values(array_filter($links, fn (array $l): bool => $l['evidence']['route']['label'] === 'Argentan - Granville'));
        $this->assertCount(1, $argentan, 'Argentan - Granville : la seule ligne 405000');
        $this->assertSame('medium', LineMaterialLinker::attributes([$argentan[0]['evidence']])['confidence']);
        $this->assertSame('Relation Argentan - Granville', $argentan[0]['evidence']['quote']);
        $this->assertSame($this->stations['Argentan']->id, $argentan[0]['evidence']['route']['from']['id']);
    }

    public function test_an_ambiguous_relation_is_refused_and_counted(): void
    {
        $relations = $this->relations();
        $links = $this->links($relations, "* Caen - Mézidon\n");

        $this->assertSame([], $links, 'Caen - Lisieux - Mézidon n\'est que 5 % plus long que la ligne directe');
        $this->assertSame(1, $relations->stats()['pairs_ambiguous']);
    }

    public function test_paris_dreux_never_reaches_argentan_granville(): void
    {
        $links = $this->links($this->relations(), "* Paris - Dreux\n", 'Services effectués > TER Basse-Normandie');

        $this->assertSame(['395000'], $this->codes($links));
        $this->assertSame('TER', $links[0]['evidence']['route']['service']);
        $this->assertSame('Paris - Dreux', $links[0]['evidence']['route']['label'], 'la ville telle que la phrase la nomme');
        $this->assertSame('Paris-Montparnasse', $links[0]['evidence']['route']['from']['name'], 'la gare retenue, telle qu\'en base');
    }

    public function test_a_future_relation_links_nothing(): void
    {
        $this->assertSame([], $this->links($this->relations(), "Les rames seront redéployées sur la relation Argentan - Granville.\n"));
    }

    public function test_the_period_of_the_sentence_is_kept_with_the_relation(): void
    {
        $links = $this->links($this->relations(), "La CC 80001 rejoint la ligne Paris \u{2014} Argentan \u{2014} Granville jusqu'en 1977.\n", 'Carrière');

        $this->assertSame(['395000', '405000', '430000'], $this->codes($links));
        $evidence = $links[0]['evidence'];
        $this->assertSame('1977-01-01', $evidence['period']['to']['date']);
        $this->assertSame('Paris - Argentan - Granville', $evidence['route']['label']);
        $this->assertSame(2030, $evidence['route']['era_year'], 'année de la phrase (1977) avant la première ère : époque actuelle');
    }

    public function test_a_narrative_future_in_an_old_story_still_links(): void
    {
        $links = $this->links($this->relations(), "Les X 2800 continueront de circuler sur la relation Argentan - Granville jusqu'en 1950, année où elles seront remplacées par des autorails plus récents.\n");

        $this->assertSame(['405000'], $this->codes($links), 'futur de récit : toutes les années citées ont plus de quinze ans');
    }

    public function test_a_relation_written_as_one_hyphenated_word_is_split_at_the_right_hyphen(): void
    {
        $links = $this->links($this->relations(), "Les autorails assurent la relation Argentan-Granville.\n");

        $this->assertSame(['405000'], $this->codes($links));
        $this->assertSame('Argentan - Granville', $links[0]['evidence']['route']['label']);
    }

    public function test_a_hyphenated_line_named_for_its_works_or_as_a_link_label_links_nothing(): void
    {
        $relations = $this->relations();

        $this->assertSame([], $this->links($relations, "Après l'électrification de la ligne Argentan-Granville, les rames changent.\n"));
        $this->assertSame([], $this->links($relations, "Elles roulent sur la ligne [[Ligne d'Argentan à Granville|Argentan-Granville]] et ailleurs.\n"), 'le lien direct est lu par LineMaterialLinker');
    }

    public function test_an_electric_series_is_not_routed_over_a_line_never_electrified(): void
    {
        foreach (['A1' => false, 'A2' => true, 'A3' => true] as $code => $electrified) {
            $this->lines[$code] = Line::factory()->create(['code' => $code, 'name' => "Ligne {$code}", 'is_lgv' => false, 'electrified' => $electrified]);
        }
        foreach (['Arras' => [2.78, 50.29], 'Hazebrouck' => [2.54, 50.72], 'Boulogne-Ville' => [1.61, 50.72]] as $name => [$lng, $lat]) {
            $this->stations[$name] = Station::factory()->create(['name' => $name, 'geom' => Point::makeGeodetic($lat, $lng)]);
        }
        // Par Saint-Pol (ligne jamais électrifiée) : 100 km ; par Hazebrouck (électrifiée) : 60 + 80 km.
        $this->edge('Arras', 'Boulogne-Ville', 'A1', 100);
        $this->edge('Arras', 'Hazebrouck', 'A2', 60);
        $this->edge('Hazebrouck', 'Boulogne-Ville', 'A3', 80);
        $relations = $this->relations();
        $page = ['title' => 'TGV POS', 'revid' => 7, 'sections' => [['title' => 'Service', 'level' => 2, 'text' => "* Arras - Boulogne-Ville\n"]]];

        $thermal = $relations->links($page, ['fast' => true, 'from' => 2007, 'to' => null], new \DateTimeImmutable('2026-11-20'));
        $electric = $relations->links($page, ['fast' => true, 'from' => 2007, 'to' => null, 'electric' => true], new \DateTimeImmutable('2026-11-20'));

        $this->assertSame(['A1'], $this->codes($thermal));
        $this->assertSame(['A2', 'A3'], $this->codes($electric));
        $this->assertTrue(LineMaterialLinker::isElectricOnly('rame_tgv', null));
        $this->assertTrue(LineMaterialLinker::isElectricOnly('automotrice', '[{"label": "25 kV 50 Hz"}]'));
        $this->assertFalse(LineMaterialLinker::isElectricOnly('automotrice', null), 'une « automotrice » sans système connu peut être thermique');
        $this->assertFalse(LineMaterialLinker::isElectricOnly('bimode', '[{"label": "25 kV 50 Hz"}]'));
    }

    public function test_a_city_leaves_from_its_passenger_station_and_labels_are_readable(): void
    {
        $technical = $this->stations['Argentan (IE)'] = Station::factory()->create(['name' => 'Argentan (IE)', 'geom' => Point::makeGeodetic(48.75, -0.02)]);
        $this->edge('Argentan', 'Argentan (IE)', '430000', 1);
        $router = new RelationRouter;

        $this->assertSame([$this->stations['Argentan']->id], $router->resolve('Argentan', 2030), 'l\'installation embranchée est laissée de côté');
        $this->assertNotContains($technical->id, $router->resolve('Argentan', 2030));
        $this->assertSame('Saint-Malo', RelationRouter::readable('St-Malo'));
        $this->assertSame('Pontorson-Mont-Saint-Michel', RelationRouter::readable('Pontorson-Mont-St-Michel'));
        $this->assertSame('Sainte-Pazanne', RelationRouter::readable('Ste-Pazanne'));
        $this->assertSame('Honfleur', RelationRouter::readable('Honfleur (IE)'));
        $this->assertSame('Lyon-Perrache', RelationRouter::readable('Lyon-Perrache (MIN)'));
        $this->assertSame('Metz', RelationRouter::readable('Metz-n° 2'));
        $this->assertSame('Bize (Aude)', RelationRouter::readable('Bize (Aude)'), 'une précision de lieu reste');
        $this->assertSame('Stade', RelationRouter::readable('Stade'));
    }

    public function test_relations_are_served_with_their_periods(): void
    {
        $material = Material::factory()->create(['slug' => 'x-2800', 'name' => 'X 2800']);
        $link = LineMaterial::query()->create([
            'line_id' => $this->lines['405000']->id, 'material_id' => $material->id, 'source' => 'wikipedia', 'confidence' => 'medium',
            'evidence' => [[
                'kind' => 'wikipedia_material_article', 'article' => 'X 2800', 'revid' => 42, 'section' => 'Services', 'quote' => 'Relation Argentan - Granville',
                'route' => ['kind' => 'relation', 'label' => 'Argentan - Granville', 'from' => ['id' => 1, 'name' => 'Argentan'], 'to' => ['id' => 2, 'name' => 'Granville'], 'service' => 'omnibus'],
            ]],
        ]);
        LineMaterialPeriod::query()->create([
            'line_material_id' => $link->id, 'start_date' => '1975-01-01', 'start_precision' => 'year', 'end_date' => '1985-01-01', 'end_precision' => 'year',
            'confidence' => 'medium', 'origin' => 'material_article', 'source' => 'wikipedia', 'fingerprint' => sha1('x'),
            'relation' => ['label' => 'Argentan - Granville', 'from' => ['id' => 1, 'name' => 'Argentan'], 'to' => ['id' => 2, 'name' => 'Granville'], 'service' => 'omnibus'],
        ]);

        $relations = $link->fresh()?->relations();
        $this->assertSame('Argentan - Granville', $relations[0]['label']);
        $this->assertSame('omnibus', $relations[0]['service']);
        $this->assertSame([['start_date' => '1975-01-01', 'start_precision' => 'year', 'end_date' => '1985-01-01', 'end_precision' => 'year']], $relations[0]['periods']);

        $response = $this->getJson('/api/v1/materials/x-2800')->assertOk();
        $response->assertJsonPath('data.lines.0.relations.0.label', 'Argentan - Granville');
        $response->assertJsonPath('data.lines.0.periods.0.relation.service', 'omnibus');
        $response->assertJsonPath('data.lines.0.evidence.0.route.kind', 'relation');

        // GraphQL : mêmes relations, et la relation de chaque période.
        $this->postJson('/graphql', ['query' => '{ line(slug: "'.$this->lines['405000']->slug.'") { material_links { relations { kind label service from { id name } periods { start_date end_date } } periods { relation { label } } evidence { route { label } } } } }'])
            ->assertOk()
            ->assertJsonPath('data.line.material_links.0.relations.0.label', 'Argentan - Granville')
            ->assertJsonPath('data.line.material_links.0.relations.0.from.name', 'Argentan')
            ->assertJsonPath('data.line.material_links.0.relations.0.periods.0.end_date', '1985-01-01')
            ->assertJsonPath('data.line.material_links.0.periods.0.relation.label', 'Argentan - Granville')
            ->assertJsonPath('data.line.material_links.0.evidence.0.route.label', 'Argentan - Granville');

        // Serveur MCP : `line_materials` donne les relations de chaque série.
        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'line_materials', 'arguments' => ['line' => $this->lines['405000']->slug]]])
            ->assertOk()->json('result');
        $data = json_decode($result['content'][0]['text'], true);
        $this->assertSame('Argentan - Granville', $data['materials'][0]['relations'][0]['label']);
        $this->assertSame('omnibus', $data['materials'][0]['periods'][0]['relation']['service']);

        // Fiche de la série sur le site : relations de chaque ligne parcourue.
        $this->withCookie('locale', 'fr')->get('/materials/x-2800')->assertOk()
            ->assertInertia(fn ($page) => $page->where('lines.0.relations.0.label', 'Argentan - Granville')->etc());
    }
}
