<?php

namespace Tests\Feature\GraphQL;

use App\Models\Line;
use App\Models\LineSegment;
use App\Models\Material;
use App\Models\Station;
use Clickbar\Magellan\Data\Geometries\Point;
use GraphQL\Type\Introspection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * POST /graphql (Lighthouse) : chaque requête du schéma, types des champs
 * (énumérations renvoyées en chaîne), pagination, filtres, erreurs.
 */
class GraphQLQueriesTest extends TestCase
{
    use BuildsRailNetwork;
    use MakesGraphQLRequests;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
    }

    // ─── Ères ────────────────────────────────────────────────────────────

    public function test_eras_and_era(): void
    {
        $this->graphQL('{ eras { id year label is_current description } }')
            ->assertGraphQLErrorFree()
            ->assertJsonCount(2, 'data.eras')
            ->assertJsonPath('data.eras.0.year', 1960)
            ->assertJsonPath('data.eras.0.is_current', false)
            ->assertJsonPath('data.eras.1.year', 2030)
            ->assertJsonPath('data.eras.1.is_current', true)
            ->assertJsonPath('data.eras.1.label', 'Aujourd\'hui')
            ->assertJsonPath('data.eras.1.id', (string) $this->currentEra->id);

        $this->graphQL('query ($y: Int!) { era(year: $y) { year label } }', ['y' => 1960])
            ->assertGraphQLErrorFree()
            ->assertExactJson(['data' => ['era' => ['year' => 1960, 'label' => 'Années 1960']]]);

        $this->graphQL('{ era(year: 1850) { year } }')
            ->assertGraphQLErrorFree()
            ->assertExactJson(['data' => ['era' => null]]);
    }

    // ─── Lignes ──────────────────────────────────────────────────────────

    public function test_segment_geometry_follows_the_redistribution_rule_of_the_sources(): void
    {
        $query = '{ line(slug: "'.$this->line->slug.'") { segments { geojson } } }';

        // Source non déclarée : pas de licence connue, géométrie non redistribuée.
        LineSegment::query()->update(['source' => null]);
        $this->graphQL($query)->assertGraphQLErrorFree()->assertJsonPath('data.line.segments.0.geojson', null);

        LineSegment::query()->update(['source' => 'archeoferroviaire']);
        $this->graphQL($query)->assertGraphQLErrorFree()->assertJsonPath('data.line.segments.0.geojson', null);

        LineSegment::query()->update(['source' => 'osm']);
        $this->assertNotNull($this->graphQL($query)->assertGraphQLErrorFree()->json('data.line.segments.0.geojson'));
    }

    public function test_line_with_every_relation_and_resolver(): void
    {
        // Seule une source à licence ouverte livre sa géométrie (registre des droits).
        LineSegment::query()->update(['source' => 'osm']);

        $response = $this->graphQL(<<<'GQL'
            query ($slug: String!) {
              line(slug: $slug) {
                id slug code name type network historical_operator alt_names created_at updated_at
                segments { id status traffic_type electrified voltage_v max_speed_kmh is_heritage geojson era { year } }
                events { type event_date date_precision line { slug } }
                materials { slug name type lines { slug } }
                stops { position pk_m effective_status is_terminal station { slug name type status lat lng } }
                signalling { pk_start_m pk_end_m length_m signalling train_protection kvb_equipped note }
              }
            }
            GQL, ['slug' => $this->line->slug])->assertGraphQLErrorFree();

        $line = $response->json('data.line');
        $this->assertSame('999 000', $line['code']);
        $this->assertSame('passenger', $line['type'], 'EnumValue : valeur de LineType');
        $this->assertSame('Compagnie de l\'Ouest', $line['historical_operator']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $line['created_at']);

        $this->assertCount(2, $line['segments']);
        $this->assertEqualsCanonicalizing([1960, 2030], array_column(array_column($line['segments'], 'era'), 'year'));
        $segment = $line['segments'][0];
        $this->assertSame('in_service', $segment['status'], 'EnumValue : valeur de SegmentStatus');
        $this->assertSame('passenger', $segment['traffic_type']);
        $this->assertTrue($segment['electrified']);
        $this->assertSame(1500, $segment['voltage_v']);
        $geojson = json_decode($segment['geojson'], true);
        $this->assertSame('LineString', $geojson['type']);
        $this->assertEqualsWithDelta([2.3253, 48.8763], $geojson['coordinates'][0], 1e-6);

        $this->assertSame([['type' => 'opening', 'event_date' => '1839-08-02', 'date_precision' => 'day',
            'line' => ['slug' => $this->line->slug]]], $line['events']);
        $this->assertSame([['slug' => 'z-6400-test', 'name' => 'Z 6400', 'type' => $this->material->type->value,
            'lines' => [['slug' => $this->line->slug]]]], $line['materials']);

        // stops / signalling : ère courante par défaut.
        $this->assertSame([1, 2], array_column($line['stops'], 'position'));
        $this->assertSame([0, 17_250], array_column($line['stops'], 'pk_m'));
        $this->assertSame(['slug' => 'paris-saint-lazare', 'name' => 'Paris-Saint-Lazare', 'type' => 'gare', 'status' => 'active',
            'lat' => 48.8763, 'lng' => 2.3253], $line['stops'][0]['station']);
        $this->assertSame([['pk_start_m' => 0, 'pk_end_m' => 17_250, 'length_m' => 17_250, 'signalling' => 'BAL',
            'train_protection' => ['KVB'], 'kvb_equipped' => true, 'note' => null]], $line['signalling']);
    }

    public function test_line_stops_and_signalling_for_a_given_era(): void
    {
        $this->graphQL('query ($s: String!) { line(slug: $s) { stops(era: 1960) { position } signalling(era: 1960) { signalling } } }',
            ['s' => $this->line->slug])
            ->assertGraphQLErrorFree()
            ->assertExactJson(['data' => ['line' => ['stops' => [], 'signalling' => []]]]);
    }

    public function test_unknown_era_in_stops_is_a_readable_client_error(): void
    {
        $this->graphQL('query ($s: String!) { line(slug: $s) { slug stops(era: 1850) { position } } }', ['s' => $this->line->slug])
            ->assertGraphQLErrorMessage('Aucune ère 1850.')
            ->assertJsonPath('data.line', null);

        $this->graphQL('query ($s: String!) { line(slug: $s) { signalling(era: 1850) { signalling } } }', ['s' => $this->line->slug])
            ->assertGraphQLErrorMessage('Aucune ère 1850.');
    }

    public function test_unknown_line_is_null(): void
    {
        $this->graphQL('{ line(slug: "inconnue") { slug } }')
            ->assertGraphQLErrorFree()
            ->assertExactJson(['data' => ['line' => null]]);
    }

    public function test_lines_pagination_and_filters(): void
    {
        foreach (range(1, 4) as $i) {
            Line::factory()->create(['name' => "Ligne de Rouen à Dieppe {$i}", 'code' => "330 00{$i}", 'type' => 'freight']);
        }

        $page = '{ paginatorInfo { total count currentPage lastPage perPage hasMorePages firstItem lastItem } data { slug name code type } }';

        $this->graphQL("{ lines(first: 2, page: 2) {$page} }")
            ->assertGraphQLErrorFree()
            ->assertJsonPath('data.lines.paginatorInfo', [
                'total' => 5, 'count' => 2, 'currentPage' => 2, 'lastPage' => 3, 'perPage' => 2,
                'hasMorePages' => true, 'firstItem' => 3, 'lastItem' => 4,
            ]);

        // Défaut : 20 par page.
        $this->graphQL("{ lines {$page} }")->assertJsonPath('data.lines.paginatorInfo.perPage', 20)
            ->assertJsonPath('data.lines.paginatorInfo.count', 5);

        // @where ilike : motif LIKE fourni par le client, insensible à la casse.
        $this->graphQL('{ lines(name: "%DIEPPE%") { paginatorInfo { total } } }')->assertJsonPath('data.lines.paginatorInfo.total', 4);
        $this->graphQL('{ lines(name: "ligne de rouen à dieppe 3") { data { code } } }')
            ->assertJsonPath('data.lines.data', [['code' => '330 003']]);
        $this->graphQL('{ lines(code: "999%") { data { slug } } }')->assertJsonPath('data.lines.data.0.slug', $this->line->slug);
        $this->graphQL('{ lines(type: "freight") { paginatorInfo { total } data { type } } }')
            ->assertJsonPath('data.lines.paginatorInfo.total', 4)
            ->assertJsonPath('data.lines.data.0.type', 'freight');
        $this->graphQL('{ lines(type: "freight", name: "%versailles%") { paginatorInfo { total } } }')
            ->assertJsonPath('data.lines.paginatorInfo.total', 0);
    }

    public function test_pagination_limits(): void
    {
        $this->graphQL('{ lines(first: 100) { paginatorInfo { perPage } } }')->assertGraphQLErrorFree();
        $this->graphQL('{ lines(first: 101) { paginatorInfo { total } } }')
            ->assertGraphQLErrorMessage('Maximum number of 100 requested items exceeded, got 101. Fetch smaller chunks.');
        $this->graphQL('{ stations(first: 500) { paginatorInfo { total } } }')->assertJsonPath('data.stations', null);
        $this->graphQL('{ materials(first: -1) { pageInfo { total } } }')->assertJsonPath('data.materials', null);
    }

    /** @return array<string, array{0: string}> */
    public static function hostilePatterns(): array
    {
        return [
            'apostrophe' => ["%l'Ouest%"],
            'injection' => ["x' OR '1'='1"],
            'commentaire' => ['%; DROP TABLE lines; --'],
            'antislash' => ['\\\\%'],
            'unicode' => ['%東京%'],
        ];
    }

    /** Les filtres ilike sont des paramètres liés (pas d'injection), jamais d'erreur. */
    #[DataProvider('hostilePatterns')]
    public function test_ilike_filters_are_bound_parameters(string $pattern): void
    {
        foreach (['lines(name: $p)', 'lines(code: $p)', 'stations(name: $p)'] as $field) {
            $root = strtok($field, '(');
            $this->graphQL("query (\$p: String) { {$field} { paginatorInfo { total } } }", ['p' => $pattern])
                ->assertGraphQLErrorFree()
                ->assertJsonPath("data.{$root}.paginatorInfo.total", 0);
        }
        // materials : connexion par curseur, compteur dans pageInfo.
        $this->graphQL('query ($p: String) { materials(builder: $p) { pageInfo { total } } }', ['p' => $pattern])
            ->assertGraphQLErrorFree()
            ->assertJsonPath('data.materials.pageInfo.total', 0);
        $this->assertSame(1, Line::count());
    }

    // ─── Gares ───────────────────────────────────────────────────────────

    public function test_stations_station_and_coordinates(): void
    {
        Station::factory()->create(['slug' => 'halte-x', 'name' => 'Halte X', 'type' => 'halte', 'status' => 'declassified',
            'geom' => Point::makeGeodetic(45.5, 4.25)]);

        $this->graphQL('{ stations(type: "gare", status: "active") { paginatorInfo { total } data { slug } } }')
            ->assertGraphQLErrorFree()
            ->assertJsonPath('data.stations.paginatorInfo.total', 2);
        $this->graphQL('{ stations(name: "%versailles%") { data { slug type status city } } }')
            ->assertJsonPath('data.stations.data', [['slug' => 'versailles-rive-droite', 'type' => 'gare', 'status' => 'active', 'city' => 'Versailles']]);

        $this->graphQL('{ station(slug: "halte-x") { slug type status lat lng opened_at closed_at department_code } }')
            ->assertGraphQLErrorFree()
            ->assertJsonPath('data.station.type', 'halte')
            ->assertJsonPath('data.station.status', 'declassified')
            ->assertJsonPath('data.station.lat', 45.5)
            ->assertJsonPath('data.station.lng', 4.25);
        $this->graphQL('{ station(slug: "paris-saint-lazare") { opened_at closed_at } }')
            ->assertExactJson(['data' => ['station' => ['opened_at' => '1837-08-26', 'closed_at' => null]]]);
        $this->graphQL('{ station(slug: "nulle-part") { slug } }')->assertExactJson(['data' => ['station' => null]]);
    }

    // ─── Matériels ───────────────────────────────────────────────────────

    public function test_materials_and_material(): void
    {
        Material::factory()->create(['slug' => 'bb-9200-x', 'name' => 'BB 9200', 'type' => 'locomotive_electrique',
            'builder' => 'Alsthom', 'in_service_from' => 1957, 'in_service_to' => 2014]);

        $this->graphQL('{ materials(type: "locomotive_electrique") { pageInfo { total } edges { node { slug type builder in_service_from in_service_to } } } }')
            ->assertGraphQLErrorFree()
            ->assertJsonPath('data.materials.edges', [['node' => ['slug' => 'bb-9200-x', 'type' => 'locomotive_electrique', 'builder' => 'Alsthom',
                'in_service_from' => 1957, 'in_service_to' => 2014]]]);
        $this->graphQL('{ materials(builder: "alsth%") { edges { node { slug } } } }')->assertJsonPath('data.materials.edges.0.node.slug', 'bb-9200-x');

        $this->graphQL('{ material(slug: "z-6400-test") { name lines { slug name } } }')
            ->assertGraphQLErrorFree()
            ->assertExactJson(['data' => ['material' => ['name' => 'Z 6400', 'lines' => [['slug' => $this->line->slug, 'name' => $this->line->name]]]]]);
        $this->graphQL('{ material(slug: "inconnu") { name } }')->assertExactJson(['data' => ['material' => null]]);
    }

    // ─── Itinéraire ──────────────────────────────────────────────────────

    public function test_route_is_null_until_the_graph_is_built_then_typed(): void
    {
        $query = '{ route(from: "paris-saint-lazare", to: "versailles-rive-droite") {
            era_year distance_m duration_h hops
            stations { slug name lat lng }
            lines { line_slug line_name line_code length_m from_slug to_slug }
            edges { from_slug to_slug line_id length_m skipped_count } } }';

        $this->graphQL($query)->assertGraphQLErrorFree()->assertExactJson(['data' => ['route' => null]]);

        $this->artisan('vff:build-network-graph')->assertSuccessful();

        $route = $this->graphQL($query)->assertGraphQLErrorFree()->json('data.route');
        $this->assertSame(2030, $route['era_year']);
        $this->assertSame(1, $route['hops']);
        $this->assertSame(['paris-saint-lazare', 'versailles-rive-droite'], array_column($route['stations'], 'slug'));
        $this->assertEqualsWithDelta(15_400, $route['distance_m'], 800);
        $this->assertEqualsWithDelta($route['distance_m'] / 100_000, $route['duration_h'], 0.01, '100 km/h indicatifs');
        $this->assertSame($this->line->slug, $route['lines'][0]['line_slug']);
        $this->assertSame('999 000', $route['lines'][0]['line_code']);
        $this->assertSame($this->line->id, $route['edges'][0]['line_id']);
        $this->assertSame(0, $route['edges'][0]['skipped_count']);

        // Ère passée sans graphe construit pour 1960 → null (pas d'erreur).
        $this->graphQL('{ route(from: "paris-saint-lazare", to: "versailles-rive-droite", era: 1965) { era_year } }')
            ->assertGraphQLErrorFree();
    }

    // ─── Erreurs de requête ──────────────────────────────────────────────

    public function test_query_errors_are_reported_without_data(): void
    {
        $this->graphQL('{ eras { inexistant } }')
            ->assertGraphQLErrorMessage('Cannot query field "inexistant" on type "Era".')
            ->assertJsonMissingPath('data');

        $this->graphQL('{ line { slug } }')
            ->assertGraphQLErrorMessage('Field "line" argument "slug" of type "String!" is required but not provided.');

        $this->graphQL('{ era(year: "deux mille") { year } }')
            ->assertJsonPath('errors.0.message', 'Int cannot represent non-integer value: "deux mille"');

        $this->postJson('/graphql', [])->assertStatus(400)
            ->assertJsonPath('message', 'GraphQL Server expects JSON object or array, but got: [].');
        $this->postJson('/graphql', ['query' => '{ eras { year '])->assertJsonPath('errors.0.message', fn ($m) => str_starts_with($m, 'Syntax Error'));
    }

    public function test_enum_value_resolver_returns_raw_values_unknown_to_php(): void
    {
        // Type d'événement inconnu de l'enum PHP : renvoyé brut au lieu de faire échouer la requête.
        DB::table('line_events')->insert(['line_id' => $this->line->id, 'type' => 'transfer', 'event_date' => '1909-01-01', 'description' => 'Rachat.',
            'created_at' => now(), 'updated_at' => now()]);

        $this->graphQL('query ($s: String!) { line(slug: $s) { events { type event_date } } }', ['s' => $this->line->slug])
            ->assertGraphQLErrorFree()
            ->assertJsonPath('data.line.events.*.type', fn ($types) => collect($types)->sort()->values()->all() === ['opening', 'transfer']);
    }

    public function test_get_requests_and_introspection_work(): void
    {
        $this->getJson('/graphql?query='.rawurlencode('{ era(year: 2030) { year } }'))
            ->assertOk()
            ->assertExactJson(['data' => ['era' => ['year' => 2030]]]);

        $this->graphQL('{ __schema { queryType { fields { name } } } }')
            ->assertGraphQLErrorFree()
            // Au moins ces requêtes (d'autres s'ajoutent : événements, médias…) ; aucune mutation n'existe.
            ->assertJsonPath('data.__schema.queryType.fields.*.name', fn ($names) => array_diff(
                ['era', 'eras', 'line', 'lines', 'material', 'materials', 'route', 'station', 'stations', 'lineEvents', 'lineEvent', 'media', 'mediaItem'],
                $names,
            ) === []);
    }

    public function test_graphql_endpoint_is_rate_limited_like_the_rest_api(): void
    {
        $this->graphQL('{ eras { year } }')
            ->assertHeader('X-RateLimit-Limit', '300')
            ->assertHeader('X-RateLimit-Remaining', '299');
    }

    public function test_abusively_deep_queries_are_refused(): void
    {
        $nested = fn (int $pairs) => '{ material(slug: "z-6400-test") { '.str_repeat('lines { materials { ', $pairs).'slug'.str_repeat(' } }', $pairs).' } }';

        // Profondeur comptée sous la racine : 2 × 7 = 14, sous la limite ; 2 × 8 = 16, refusé avant toute
        // requête SQL. Ces listes imbriquées dépassent aussi la limite de complexité (QueryLimitsTest) :
        // seule l'erreur de profondeur est regardée ici.
        $depthErrors = fn (int $pairs) => collect($this->graphQL($nested($pairs))->json('errors'))
            ->where('extensions.code', 'query_too_deep')->values();

        $this->assertCount(0, $depthErrors(7));
        $this->assertSame(
            [['code' => 'query_too_deep', 'depth' => 16, 'max_depth' => 15]],
            $depthErrors(8)->pluck('extensions')->map(fn (array $e) => array_diff_key($e, ['file' => true, 'line' => true]))->all(),
        );
    }

    public function test_full_graphiql_introspection_stays_within_the_depth_limit(): void
    {
        $this->graphQL(Introspection::getIntrospectionQuery())
            ->assertGraphQLErrorFree()
            ->assertJsonPath('data.__schema.queryType.name', 'Query');
    }
}
