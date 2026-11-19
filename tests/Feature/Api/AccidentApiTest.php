<?php

namespace Tests\Feature\Api;

use App\Enums\AccidentDatePrecision;
use App\Models\Accident;
use App\Models\AccidentLine;
use App\Models\AccidentSource;
use App\Models\Line;
use App\Models\Station;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * API REST des accidents : liste (filtres, tris, pagination, erreurs), fiche (lignes, portion,
 * sources et licences, points à vérifier) ; GraphQL (`accidents`, `accident`, `Line.accidents`) ;
 * documentation OpenAPI complète.
 */
class AccidentApiTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    private Accident $bretigny;

    protected function setUp(): void
    {
        parent::setUp();
        $this->line = Line::factory()->create(['slug' => 'rfn-570000', 'code' => '570000', 'name' => 'Ligne de Paris-Austerlitz à Bordeaux-St-Jean']);
        $station = Station::factory()->create(['slug' => 'bretigny', 'name' => 'Brétigny', 'geom' => Point::makeGeodetic(48.607, 2.302)]);

        $this->bretigny = Accident::factory()->on('2013-07-12')->at(48.6072, 2.3022)->toReview('toll_mismatch', 'blessés : Wikidata 192, Wikipédia 70')->create([
            'slug' => '2013-07-12-bretigny-sur-orge', 'wikidata_id' => 'Q13835935', 'kind' => 'derailment',
            'name' => ['fr' => 'Accident ferroviaire de Brétigny-sur-Orge', 'en' => 'Brétigny-sur-Orge train crash', 'de' => 'Eisenbahnunfall von Brétigny-sur-Orge'],
            'commune_name' => 'Brétigny-sur-Orge', 'commune_insee' => '91103', 'department_code' => '91',
            'deaths' => 7, 'injuries' => 192, 'toll_source' => 'bea-tt', 'nearest_station_id' => $station->id, 'nearest_station_distance_m' => 120,
            'trains' => [['label' => 'Intercités', 'category' => 'passenger', 'number' => null, 'material' => null, 'operator' => 'SNCF', 'source' => 'bea-tt']],
            'wikipedia' => ['fr' => ['title' => 'Accident ferroviaire de Brétigny-sur-Orge', 'url' => 'https://fr.wikipedia.org/wiki/Accident_ferroviaire_de_Br%C3%A9tigny-sur-Orge', 'revid' => 241000000]],
            'summary' => ['fr' => 'Le 12 juillet 2013, un train déraille.', 'en' => 'On 12 July 2013, a train derailed.', 'de' => 'Am 12. Juli 2013 entgleiste ein Zug.'],
        ]);
        AccidentLine::query()->forceCreate([
            'accident_id' => $this->bretigny->id, 'line_id' => $this->line->id, 'is_primary' => true, 'era_year' => 2010,
            'distance_m' => 8, 'pk_m' => 31336, 'section_from_station_id' => $station->id, 'method' => 'proximity+wikidata',
            'confidence' => 'high', 'score' => 0.99,
        ]);
        AccidentSource::query()->forceCreate([
            'accident_id' => $this->bretigny->id, 'provider' => 'bea-tt', 'external_id' => '2013-07-12-bretigny-sur-orge-f', 'role' => 'report',
            'title' => 'Déraillement d’un train Intercités à Brétigny-sur-Orge (91)', 'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/rapport.pdf',
            'licence' => 'Licence Ouverte 2.0 (Etalab)', 'facts' => ['deaths' => 7], 'retrieved_at' => now(),
        ]);
        AccidentSource::query()->forceCreate([
            'accident_id' => $this->bretigny->id, 'provider' => 'wikidata', 'external_id' => 'Q13835935', 'role' => 'data',
            'title' => 'Accident ferroviaire de Brétigny-sur-Orge', 'url' => 'https://www.wikidata.org/wiki/Q13835935',
            'licence' => 'CC0 1.0', 'revision' => 2555864454, 'facts' => ['deaths' => 7, 'injuries' => 192], 'retrieved_at' => now(),
        ]);

        Accident::factory()->on('1891-07-26')->create(['slug' => '1891-07-26-saint-mande', 'kind' => 'collision', 'deaths' => 43, 'injuries' => null,
            'department_code' => '94', 'commune_name' => 'Saint-Mandé', 'name' => ['fr' => 'Accident ferroviaire de Saint-Mandé', 'en' => 'Saint-Mandé rail accident', 'de' => 'Eisenbahnunfall von Saint-Mandé']]);
        Accident::factory()->on('1922-01-01', AccidentDatePrecision::Year)->create(['slug' => '1922-villecomtal', 'kind' => 'collision', 'deaths' => null, 'injuries' => null,
            'department_code' => '32', 'commune_name' => 'Villecomtal-sur-Arros', 'geom' => null, 'location_precision' => null]);
    }

    public function test_list_is_chronological_with_page_pagination(): void
    {
        $response = $this->getJson('/api/v1/accidents?per_page=2')->assertOk();

        $this->assertSame(['1891-07-26-saint-mande', '1922-villecomtal'], array_column($response->json('data'), 'slug'));
        $this->assertSame(3, $response->json('meta.total'));
        $this->assertSame(2, $response->json('meta.last_page'));
        $this->assertStringContainsString('per_page=2', (string) $response->json('links.next'));
        $this->assertSame('year', $response->json('data.1.date_precision'));
        $this->assertNull($response->json('data.1.place.lat'));
        $this->assertNull($response->json('data.1.deaths'));
    }

    public function test_list_item_shape(): void
    {
        $item = $this->getJson('/api/v1/accidents?q=bretigny')->assertOk()->json('data.0');

        $this->assertSame('2013-07-12-bretigny-sur-orge', $item['slug']);
        $this->assertSame('Déraillement', $item['kind_label']);
        $this->assertSame('Essonne', $item['place']['department']);
        $this->assertSame(48.6072, $item['place']['lat']);
        $this->assertSame(['slug' => 'rfn-570000', 'code' => '570000', 'name' => 'Ligne de Paris-Austerlitz à Bordeaux-St-Jean', 'confidence' => 'high'], $item['line']);
        $this->assertSame('to_review', $item['review_status']);
        $this->assertSame('On 12 July 2013, a train derailed.', $item['summary']['en']);
        $this->assertStringEndsWith('/accidents/2013-07-12-bretigny-sur-orge', $item['url']);
    }

    /** @return iterable<string, array{0: string, 1: list<string>}> */
    public static function filters(): iterable
    {
        yield 'période' => ['from=1880&to=1930', ['1891-07-26-saint-mande', '1922-villecomtal']];
        yield 'ligne' => ['line=rfn-570000', ['2013-07-12-bretigny-sur-orge']];
        yield 'département' => ['department=94', ['1891-07-26-saint-mande']];
        yield 'nature' => ['kind=derailment', ['2013-07-12-bretigny-sur-orge']];
        yield 'recherche sans accent' => ['q=MANDE', ['1891-07-26-saint-mande']];
        yield 'bilan minimal' => ['min_deaths=10', ['1891-07-26-saint-mande']];
        yield 'sans ligne' => ['has_line=0', ['1891-07-26-saint-mande', '1922-villecomtal']];
        yield 'avec ligne, booléen en toutes lettres' => ['has_line=true', ['2013-07-12-bretigny-sur-orge']];
        yield 'sans ligne, booléen en toutes lettres' => ['has_line=FALSE', ['1891-07-26-saint-mande', '1922-villecomtal']];
        yield 'année de fin seule' => ['to=1930', ['1891-07-26-saint-mande', '1922-villecomtal']];
        yield 'année de début seule' => ['from=2000', ['2013-07-12-bretigny-sur-orge']];
        yield 'à vérifier' => ['review=to_review', ['2013-07-12-bretigny-sur-orge']];
        yield 'récents d’abord' => ['sort=-date', ['2013-07-12-bretigny-sur-orge', '1922-villecomtal', '1891-07-26-saint-mande']];
        yield 'bilan inconnu en dernier' => ['sort=-deaths', ['1891-07-26-saint-mande', '2013-07-12-bretigny-sur-orge', '1922-villecomtal']];
    }

    #[DataProvider('filters')]
    public function test_filters_and_sorts(string $query, array $expected): void
    {
        $this->assertSame($expected, array_column($this->getJson("/api/v1/accidents?{$query}")->assertOk()->json('data'), 'slug'));
    }

    /** @return iterable<string, array{0: string, 1: string}> */
    public static function invalid(): iterable
    {
        yield 'nature inconnue' => ['kind=meteor', 'kind'];
        yield 'bornes inversées' => ['from=2000&to=1990', 'to'];
        yield 'année hors bornes' => ['from=1500', 'from'];
        yield 'ligne inconnue' => ['line=rfn-000000', 'line'];
        yield 'département mal formé' => ['department=999999', 'department'];
        yield 'tri inconnu' => ['sort=name', 'sort'];
        yield 'page trop grande' => ['per_page=500', 'per_page'];
        yield 'tableau' => ['q[]=x', 'q'];
        yield 'booléen inconnu' => ['has_line=oui', 'has_line'];
    }

    #[DataProvider('invalid')]
    public function test_invalid_parameters_give_422(string $query, string $field): void
    {
        $this->getJson("/api/v1/accidents?{$query}")->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_record_details(): void
    {
        $data = $this->getJson('/api/v1/accidents/2013-07-12-bretigny-sur-orge')->assertOk()->json('data');

        $this->assertSame('rfn-570000', $data['lines'][0]['line']['slug']);
        $this->assertSame('31+336', $data['lines'][0]['pk_label']);
        $this->assertSame(['proximity', 'wikidata'], $data['lines'][0]['method']);
        $this->assertSame(['slug' => 'bretigny', 'name' => 'Brétigny'], $data['lines'][0]['section']['from']);
        $this->assertNull($data['lines'][0]['section']['to']);
        $this->assertSame(['slug' => 'bretigny', 'name' => 'Brétigny', 'distance_m' => 120], $data['nearest_station']);
        $this->assertSame('Intercités', $data['trains'][0]['label']);
        // Rapport d'enquête d'abord ; licence et statut tirés du registre des droits.
        $this->assertSame(['bea-tt', 'wikidata'], array_column($data['sources'], 'provider'));
        $this->assertSame('verified', $data['sources'][0]['license_status']);
        $this->assertSame(2555864454, $data['sources'][1]['revision']);
        $this->assertSame('toll_mismatch', $data['review']['notes'][0]['code']);
        $this->assertSame('Les sources ne donnent pas le même bilan.', $data['review']['notes'][0]['label']);
        $this->assertArrayHasKey('fr', $data['wikipedia']);
    }

    public function test_unknown_record_is_404(): void
    {
        $this->getJson('/api/v1/accidents/1900-01-01-nulle-part')->assertNotFound();
        $this->getJson('/api/v1/accidents/'.str_repeat('a', 200))->assertNotFound();
    }

    public function test_graphql_list_record_and_line_link(): void
    {
        $response = $this->postJson('/graphql', ['query' => '{
            accidents(first: 10, filter: {sort: "-deaths"}) { paginatorInfo { total } data { slug } }
            accident(slug: "2013-07-12-bretigny-sur-orge") {
                name(lang: "en") kind date date_precision lat lng deaths toll_source summary(lang: "de")
                review_status review_notes { code label detail } trains { label category operator source }
                lines { is_primary pk_m pk_label method confidence line { slug } section_from { slug } section_to { slug } }
                nearest_station { slug } sources { provider license revision facts } wikipedia_url
            }
            line(slug: "rfn-570000") { accidents { slug } }
        }'])->assertOk();

        $this->assertNull($response->json('errors'));
        $this->assertSame(3, $response->json('data.accidents.paginatorInfo.total'));
        $this->assertSame('1891-07-26-saint-mande', $response->json('data.accidents.data.0.slug'));
        $accident = $response->json('data.accident');
        $this->assertSame('Brétigny-sur-Orge train crash', $accident['name']);
        $this->assertSame('derailment', $accident['kind']);
        $this->assertSame('2013-07-12', $accident['date']);
        $this->assertEqualsWithDelta(48.6072, $accident['lat'], 0.0001);
        $this->assertSame('Am 12. Juli 2013 entgleiste ein Zug.', $accident['summary']);
        $this->assertSame('31+336', $accident['lines'][0]['pk_label']);
        $this->assertSame(['proximity', 'wikidata'], $accident['lines'][0]['method']);
        $this->assertSame('2555864454', $accident['sources'][1]['revision']);
        $this->assertStringContainsString('wikipedia.org', $accident['wikipedia_url']);
        $this->assertSame([['slug' => '2013-07-12-bretigny-sur-orge']], $response->json('data.line.accidents'));
    }

    public function test_graphql_invalid_filter_is_a_validation_error(): void
    {
        $response = $this->postJson('/graphql', ['query' => '{ accidents(filter: {kind: "meteor"}) { data { slug } } }'])->assertOk();

        $this->assertNotEmpty($response->json('errors'));
    }

    public function test_endpoints_are_fully_documented(): void
    {
        $spec = $this->getJson('/docs/api.json')->assertOk()->json();

        foreach (['/accidents', '/accidents/{accident}'] as $path) {
            $operation = $spec['paths'][$path]['get'] ?? null;
            $this->assertNotNull($operation, "{$path} absent de la spécification");
            $this->assertSame(['Accidents'], $operation['tags']);
            $this->assertLessThanOrEqual(60, mb_strlen($operation['summary']));
            $this->assertGreaterThan(600, mb_strlen($operation['description'] ?? ''), "{$path} : description trop courte");
            foreach ($operation['parameters'] ?? [] as $parameter) {
                $this->assertNotEmpty($parameter['description'] ?? null, "{$path} : paramètre {$parameter['name']} sans description");
                $this->assertArrayHasKey('example', $parameter, "{$path} : paramètre {$parameter['name']} sans exemple");
            }
            $this->assertNotEmpty($operation['responses']['200']['content']['application/json']['schema']['examples'] ?? null, "{$path} : exemple manquant");
            $this->assertArrayHasKey('429', $operation['responses']);
        }
        $this->assertArrayHasKey('422', $spec['paths']['/accidents']['get']['responses']);
        $this->assertArrayHasKey('404', $spec['paths']['/accidents/{accident}']['get']['responses']);
        foreach ($spec['components']['schemas']['AccidentResource']['properties'] as $name => $property) {
            $this->assertNotEmpty($property['description'] ?? null, "AccidentResource.{$name} sans description");
        }
    }
}
