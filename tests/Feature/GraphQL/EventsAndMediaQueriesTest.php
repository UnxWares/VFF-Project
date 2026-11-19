<?php

namespace Tests\Feature\GraphQL;

use App\Models\Line;
use App\Models\Station;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * GraphQL : événements de lignes (`lineEvents`, `lineEvent`), chronologie des gares (`Station.events`),
 * médias (`media`, `mediaItem`, `Line.media`, `Station.media`), avec provenance et licence.
 */
class EventsAndMediaQueriesTest extends TestCase
{
    use BuildsRailNetwork;
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private Line $other;

    /** @var array<string, int> */
    private array $eventIds = [];

    /** @var array<string, int> */
    private array $mediaIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();

        $this->other = Line::factory()->create(['slug' => 'morlaix-roscoff', 'name' => 'Morlaix - Roscoff']);
        DB::table('lines')->where('id', $this->other->id)->update(['departements' => json_encode(['Finistère'])]);

        $this->eventIds['closure'] = $this->event($this->other, 'closure_passenger', '1938-11-01', 'wikipedia', [
            'source_quote' => 'Le service des voyageurs est supprimé le 1er novembre 1938.', 'confidence' => 'high',
            'section_pk_start_m' => 0, 'section_pk_end_m' => 27300, 'section_resolution' => 'pk',
            'source_url' => 'https://fr.wikipedia.org/wiki/Ligne_de_Morlaix_a_Roscoff',
        ]);
        $this->eventIds['dismantling'] = $this->event($this->other, 'dismantling', '1960-01-01', 'lignes-oubliees', ['date_precision' => 'year']);
        $this->eventIds['greenway'] = $this->event($this->other, 'conversion', '2020-01-01', 'osm', ['reuse' => 'greenway', 'date_precision' => 'year']);

        $defaults = ['created_at' => now(), 'updated_at' => now(), 'source_ref' => null, 'source_url' => null, 'note' => null];
        DB::table('station_events')->insert([
            [...$defaults, ...['station_id' => $this->origin->id, 'type' => 'opening', 'event_date' => '1837-08-26', 'date_precision' => 'day', 'source' => 'wikidata',
                'source_ref' => 'Q1#P1619', 'source_url' => 'https://www.wikidata.org/wiki/Q1', 'is_inferred' => false]],
            [...$defaults, ...['station_id' => $this->origin->id, 'type' => 'closure', 'event_date' => '1990-01-01', 'date_precision' => 'year',
                'source' => 'inferred:line_event', 'is_inferred' => true, 'note' => 'Déduit']],
        ]);

        $now = ['created_at' => now(), 'updated_at' => now(), 'provider' => 'commons', 'mime_type' => 'image/jpeg'];
        $this->mediaIds['line'] = DB::table('media')->insertGetId($now + [
            'mediable_type' => Line::class, 'mediable_id' => $this->line->id, 'external_id' => 'a', 'type' => 'photo', 'title' => 'Gare en 2010',
            'depicted_year' => 2010, 'author' => 'Jean Photo', 'license' => 'CC BY-SA 4.0', 'rights_status' => 'open_license',
            'commercial_ok' => true, 'share_alike' => true, 'attribution' => 'Jean Photo, CC BY-SA 4.0, via Wikimedia Commons',
            'thumb_url' => 'https://upload.wikimedia.org/a.jpg', 'source_url' => 'https://commons.wikimedia.org/wiki/File:A.jpg',
            'provider_meta' => json_encode(['date_precision' => 'day', 'attribution_required' => true]),
            'location' => DB::raw('ST_SetSRID(ST_MakePoint(2.3253, 48.8763), 4326)'),
        ]);
        $this->mediaIds['station'] = DB::table('media')->insertGetId($now + [
            'mediable_type' => Station::class, 'mediable_id' => $this->origin->id, 'external_id' => 'b', 'type' => 'postcard', 'title' => 'Vers 1905',
            'depicted_year' => 1905, 'license' => 'Public domain', 'rights_status' => 'public_domain', 'commercial_ok' => true,
            'attribution' => 'Public domain, via Wikimedia Commons', 'thumb_url' => 'https://upload.wikimedia.org/b.jpg',
            'source_url' => 'https://commons.wikimedia.org/wiki/File:B.jpg',
        ]);
    }

    /** @param  array<string, mixed>  $extra */
    private function event(Line $line, string $type, string $date, string $source, array $extra = []): int
    {
        return DB::table('line_events')->insertGetId([
            'line_id' => $line->id, 'type' => $type, 'event_date' => $date, 'description' => "{$type} {$date}", 'source' => $source,
            'created_at' => now(), 'updated_at' => now(),
        ] + $extra);
    }

    // ─── lineEvents ──────────────────────────────────────────────────────

    public function test_line_events_connection_with_provenance_and_detail_fields(): void
    {
        $response = $this->graphQL(<<<'GQL'
            { lineEvents(first: 10, filter: { line: "morlaix-roscoff", from: "1900", to: "1999" }) {
                pageInfo { hasNextPage endCursor }
                edges { node {
                  id type event_date date_precision source source_url source_quote confidence
                  section_pk_start_m section_pk_end_m section_length_m section_resolution reuse heritage_kind
                  provenance { provider name license license_status }
                  line { slug }
                } }
            } }
            GQL)->assertGraphQLErrorFree();

        $edges = $response->json('data.lineEvents.edges');
        $this->assertCount(2, $edges);
        $closure = $edges[0]['node'];
        $this->assertSame((string) $this->eventIds['closure'], $closure['id']);
        $this->assertSame('closure_passenger', $closure['type']);
        $this->assertSame('1938-11-01', $closure['event_date']);
        $this->assertSame('Le service des voyageurs est supprimé le 1er novembre 1938.', $closure['source_quote']);
        $this->assertSame('high', $closure['confidence']);
        $this->assertSame(27300, $closure['section_pk_end_m']);
        $this->assertSame('pk', $closure['section_resolution']);
        $this->assertSame('wikipedia', $closure['provenance']['provider']);
        $this->assertSame('verified', $closure['provenance']['license_status']);
        $this->assertSame('morlaix-roscoff', $closure['line']['slug']);
        $this->assertSame('uncertain', $edges[1]['node']['provenance']['license_status']);
        $this->assertFalse($response->json('data.lineEvents.pageInfo.hasNextPage'));
    }

    public function test_line_events_default_to_chronological_order_and_can_be_reversed(): void
    {
        $dates = fn (string $query): array => array_column(array_column(
            $this->graphQL($query)->assertGraphQLErrorFree()->json('data.lineEvents.edges'),
            'node',
        ), 'event_date');

        $this->assertSame(['1839-08-02', '1938-11-01', '1960-01-01', '2020-01-01'], $dates('{ lineEvents(first: 10) { edges { node { event_date } } } }'));
        $this->assertSame(['2020-01-01', '1960-01-01', '1938-11-01', '1839-08-02'], $dates('{ lineEvents(first: 10, filter: { order: DESC }) { edges { node { event_date } } } }'));
    }

    public function test_line_events_cursor_pagination(): void
    {
        $first = $this->graphQL('{ lineEvents(first: 2) { pageInfo { hasNextPage endCursor } edges { node { event_date } } } }')->assertGraphQLErrorFree();
        $this->assertTrue($first->json('data.lineEvents.pageInfo.hasNextPage'));
        $cursor = $first->json('data.lineEvents.pageInfo.endCursor');

        $second = $this->graphQL('query ($after: String) { lineEvents(first: 2, after: $after) { pageInfo { hasNextPage } edges { node { event_date } } } }', ['after' => $cursor])
            ->assertGraphQLErrorFree();
        $this->assertSame(['1960-01-01', '2020-01-01'], array_column(array_column($second->json('data.lineEvents.edges'), 'node'), 'event_date'));
        $this->assertFalse($second->json('data.lineEvents.pageInfo.hasNextPage'));
    }

    public function test_line_events_filters_by_type_source_and_department(): void
    {
        $count = fn (string $filter): int => count($this->graphQL("{ lineEvents(first: 50, filter: {{$filter}}) { edges { node { id } } } }")
            ->assertGraphQLErrorFree()->json('data.lineEvents.edges'));

        $this->assertSame(1, $count('type: "dismantling"'));
        $this->assertSame(1, $count('source: "osm"'));
        $this->assertSame(3, $count('department: "29"'));
        $this->assertSame(0, $count('department: "75"'));
        $this->assertSame(1, $count('from: "2020-01-01"'));
    }

    public function test_line_events_filter_errors_are_validation_errors(): void
    {
        $response = $this->graphQL('{ lineEvents(first: 5, filter: { type: "teleportation", from: "hier", department: "99" }) { edges { node { id } } } }');

        $validation = $response->json('errors.0.extensions.validation');
        $this->assertEqualsCanonicalizing(['type', 'from', 'department'], array_keys($validation));
        $this->assertNull($response->json('data'));
    }

    public function test_line_event_by_id(): void
    {
        $this->graphQL('query ($id: ID!) { lineEvent(id: $id) { id type reuse source_quote provenance { name license } } }', ['id' => $this->eventIds['greenway']])
            ->assertGraphQLErrorFree()
            ->assertJsonPath('data.lineEvent.type', 'conversion')
            ->assertJsonPath('data.lineEvent.reuse', 'greenway')
            ->assertJsonPath('data.lineEvent.provenance.name', 'OpenStreetMap');

        $this->graphQL('{ lineEvent(id: 999999) { id } }')->assertGraphQLErrorFree()->assertExactJson(['data' => ['lineEvent' => null]]);
    }

    public function test_an_event_type_unknown_to_the_enum_is_returned_as_is(): void
    {
        $this->event($this->line, 'type_futur', '1950-01-01', 'manual');

        $this->graphQL('{ lineEvents(first: 10, filter: { from: "1950", to: "1950" }) { edges { node { type provenance { provider } } } } }')
            ->assertGraphQLErrorFree()
            ->assertJsonPath('data.lineEvents.edges.0.node.type', 'type_futur')
            ->assertJsonPath('data.lineEvents.edges.0.node.provenance.provider', 'vff');
    }

    // ─── Station.events ──────────────────────────────────────────────────

    public function test_station_events_are_chronological_with_their_provenance(): void
    {
        $events = $this->graphQL('query ($slug: String!) { station(slug: $slug) { events { type event_date date_precision source source_ref source_url is_inferred note provenance { provider license_status } station { slug } } } }', ['slug' => $this->origin->slug])
            ->assertGraphQLErrorFree()->json('data.station.events');

        $this->assertCount(2, $events);
        $this->assertSame('opening', $events[0]['type']);
        $this->assertSame('1837-08-26', $events[0]['event_date']);
        $this->assertSame('Q1#P1619', $events[0]['source_ref']);
        $this->assertSame('wikidata', $events[0]['provenance']['provider']);
        $this->assertFalse($events[0]['is_inferred']);
        $this->assertTrue($events[1]['is_inferred']);
        $this->assertSame('Déduit', $events[1]['note']);
        $this->assertSame('vff', $events[1]['provenance']['provider']);
        $this->assertSame($this->origin->slug, $events[0]['station']['slug']);
    }

    // ─── Médias ──────────────────────────────────────────────────────────

    public function test_media_connection_with_rights_source_and_subject(): void
    {
        $edges = $this->graphQL(<<<'GQL'
            { media(first: 10) { pageInfo { hasNextPage } edges { node {
                id provider provider_label type title thumb_url display_url source_url depicted_year date_precision attribution_required
                author attribution license rights_status commercial_ok share_alike featured lat lng
                line { slug } station { slug }
            } } } }
            GQL)->assertGraphQLErrorFree()->json('data.media.edges');

        $this->assertCount(2, $edges);
        $line = $edges[0]['node'];
        $this->assertSame((string) $this->mediaIds['line'], $line['id']);
        $this->assertSame('Wikimedia Commons', $line['provider_label']);
        $this->assertSame('photo', $line['type']);
        $this->assertSame('Jean Photo', $line['author']);
        $this->assertSame('CC BY-SA 4.0', $line['license']);
        $this->assertSame('Jean Photo, CC BY-SA 4.0, via Wikimedia Commons', $line['attribution']);
        $this->assertSame('https://commons.wikimedia.org/wiki/File:A.jpg', $line['source_url']);
        $this->assertSame('https://upload.wikimedia.org/a.jpg', $line['thumb_url']);
        $this->assertSame('https://upload.wikimedia.org/a.jpg', $line['display_url']);
        $this->assertSame('day', $line['date_precision']);
        $this->assertTrue($line['attribution_required']);
        $this->assertTrue($line['share_alike']);
        $this->assertFalse($line['featured']);
        $this->assertEqualsWithDelta(48.8763, $line['lat'], 1e-6);
        $this->assertEqualsWithDelta(2.3253, $line['lng'], 1e-6);
        $this->assertSame($this->line->slug, $line['line']['slug']);
        $this->assertNull($line['station']);
        $this->assertSame($this->origin->slug, $edges[1]['node']['station']['slug']);
        $this->assertNull($edges[1]['node']['lat']);
        $this->assertSame('public_domain', $edges[1]['node']['rights_status']);
    }

    public function test_media_filters(): void
    {
        $titles = fn (string $filter): array => array_column(array_column(
            $this->graphQL("{ media(first: 10, filter: {{$filter}}) { edges { node { title } } } }")->assertGraphQLErrorFree()->json('data.media.edges'),
            'node',
        ), 'title');

        $this->assertSame(['Vers 1905'], $titles('type: "postcard"'));
        $this->assertSame(['Vers 1905'], $titles('license: "public domain"'));
        $this->assertSame(['Gare en 2010'], $titles('rights_status: "open_license"'));
        $this->assertSame(['Vers 1905'], $titles('period: "1900_1944"'));
        $this->assertSame(['Gare en 2010'], $titles('from_year: 2000'));
        $this->assertSame(['Gare en 2010'], $titles('line: "'.$this->line->slug.'"'));
        $this->assertSame(['Vers 1905'], $titles('station: "'.$this->origin->slug.'"'));
        $this->assertSame(['Gare en 2010', 'Vers 1905'], $titles('commercial_ok: true'));
        $this->assertSame(['Vers 1905', 'Gare en 2010'], $titles('order: DESC'));
    }

    public function test_media_filter_errors_are_validation_errors(): void
    {
        $response = $this->graphQL('{ media(first: 5, filter: { line: "'.$this->line->slug.'", station: "'.$this->origin->slug.'", type: "video" }) { edges { node { id } } } }');

        $validation = $response->json('errors.0.extensions.validation');
        $this->assertArrayHasKey('line', $validation);
        $this->assertArrayHasKey('type', $validation);
    }

    public function test_media_item_and_media_of_a_line_and_a_station(): void
    {
        $this->graphQL('query ($id: ID!) { mediaItem(id: $id) { title license lat lng station { slug } } }', ['id' => $this->mediaIds['station']])
            ->assertGraphQLErrorFree()
            ->assertJsonPath('data.mediaItem.title', 'Vers 1905')
            ->assertJsonPath('data.mediaItem.lat', null)
            ->assertJsonPath('data.mediaItem.station.slug', $this->origin->slug);

        $this->graphQL('query ($id: ID!) { mediaItem(id: $id) { lat lng } }', ['id' => $this->mediaIds['line']])
            ->assertGraphQLErrorFree()
            ->assertJsonPath('data.mediaItem.lat', 48.8763);

        $this->graphQL('query ($slug: String!) { line(slug: $slug) { media(first: 5) { pageInfo { hasNextPage } edges { node { title license } } } } }', ['slug' => $this->line->slug])
            ->assertGraphQLErrorFree()
            ->assertJsonCount(1, 'data.line.media.edges')
            ->assertJsonPath('data.line.media.edges.0.node.license', 'CC BY-SA 4.0');

        $this->graphQL('query ($slug: String!) { station(slug: $slug) { media(first: 5) { edges { node { title lat } } } } }', ['slug' => $this->origin->slug])
            ->assertGraphQLErrorFree()
            ->assertJsonPath('data.station.media.edges.0.node.title', 'Vers 1905');

        $this->graphQL('{ mediaItem(id: 999999) { id } }')->assertGraphQLErrorFree()->assertExactJson(['data' => ['mediaItem' => null]]);
    }

    public function test_the_only_mutations_are_the_contribution_ones(): void
    {
        // Mutations de contribution réservées aux comptes connectés (ContributionMutationsTest) ; pas de subscription.
        $this->graphQL('{ __schema { mutationType { name } subscriptionType { name } } }')
            ->assertGraphQLErrorFree()
            ->assertExactJson(['data' => ['__schema' => ['mutationType' => ['name' => 'Mutation'], 'subscriptionType' => null]]]);
    }

    public function test_page_size_is_capped_at_100(): void
    {
        $this->graphQL('{ media(first: 101) { edges { node { id } } } }')->assertGraphQLErrorMessage('Maximum number of 100 requested items exceeded, got 101. Fetch smaller chunks.');
        $this->graphQL('{ lineEvents(first: 101) { edges { node { id } } } }')->assertGraphQLErrorMessage('Maximum number of 100 requested items exceeded, got 101. Fetch smaller chunks.');
    }

    // ─── Description du schéma ───────────────────────────────────────────

    public function test_every_new_type_field_argument_and_input_is_described(): void
    {
        $schema = app(SchemaBuilder::class)->schema();

        foreach (['LineEvent', 'StationEvent', 'Media', 'Provenance', 'LineEventFilter', 'MediaFilter', 'SortOrder'] as $name) {
            $type = $schema->getType($name);
            $this->assertNotNull($type, "Type {$name} absent");
            $this->assertTrue($type instanceof ObjectType || $type instanceof InputObjectType || $type instanceof EnumType, "{$name} : type inattendu");
            $this->assertGreaterThan(20, mb_strlen((string) $type->description), "Type {$name} sans description");
            if ($type instanceof ObjectType || $type instanceof InputObjectType) {
                foreach ($type->getFields() as $field) {
                    $this->assertNotEmpty($field->description, "Champ {$name}.{$field->name} sans description");
                }
            }
        }

        $query = $schema->getQueryType();
        foreach (['lineEvents', 'lineEvent', 'media', 'mediaItem'] as $name) {
            /** @var FieldDefinition $field */
            $field = $query->getField($name);
            $this->assertGreaterThan(20, mb_strlen((string) $field->description), "Query {$name} sans description");
            foreach ($field->args as $arg) {
                $this->assertNotEmpty($arg->description, "Argument {$name}({$arg->name}) sans description");
            }
        }
        foreach ([['Line', 'media'], ['Station', 'events'], ['Station', 'media']] as [$typeName, $name]) {
            $type = $schema->getType($typeName);
            assert($type instanceof ObjectType);
            $this->assertGreaterThan(20, mb_strlen((string) $type->getField($name)->description), "{$typeName}.{$name} sans description");
        }
        $this->assertStringNotContainsString('—', (string) file_get_contents(base_path('graphql/schema.graphql')));
    }
}
