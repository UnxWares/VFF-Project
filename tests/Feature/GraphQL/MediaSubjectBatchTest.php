<?php

namespace Tests\Feature\GraphQL;

use App\Models\LineEvent;
use App\Models\Material;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * GraphQL `Media.material`, `Media.event`, `Media.line` et `Media.station` : l'entité des médias d'une même
 * liste est lue en une requête par type d'entité, jamais une par média (relecture du 20/11/2026).
 */
class MediaSubjectBatchTest extends TestCase
{
    use BuildsRailNetwork;
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
        $this->eventId = DB::table('line_events')->insertGetId([
            'line_id' => $this->line->id, 'type' => 'opening', 'event_date' => '1900-01-01', 'date_precision' => 'year',
            'source' => 'wikipedia', 'description' => 'Ouverture', 'created_at' => now(), 'updated_at' => now(),
        ]);
        for ($i = 1; $i <= 6; $i++) {
            $this->media(Material::class, $this->material->id, "m{$i}");
            $this->media(LineEvent::class, $this->eventId, "e{$i}");
        }
    }

    private function media(string $type, int $id, string $external): void
    {
        DB::table('media')->insert([
            'mediable_type' => $type, 'mediable_id' => $id, 'provider' => 'commons', 'external_id' => $external,
            'type' => 'photo', 'title' => $external, 'rights_status' => 'public_domain', 'mime_type' => 'image/jpeg',
            'thumb_url' => "https://upload.wikimedia.org/{$external}.jpg", 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Requêtes SQL lancées par la requête GraphQL sur une table.
     *
     * @return list<string>
     */
    private function queriesOn(string $table, callable $run): array
    {
        $sql = [];
        DB::listen(function (QueryExecuted $q) use (&$sql, $table): void {
            if (preg_match('/^select .* from "'.$table.'"/i', $q->sql) === 1) {
                $sql[] = $q->sql;
            }
        });
        $run();

        return $sql;
    }

    public function test_the_series_of_the_media_of_a_series_is_read_once(): void
    {
        $queries = $this->queriesOn('materials', function (): void {
            $nodes = $this->graphQL('query ($slug: String!) { material(slug: $slug) { media(first: 10) { edges { node {
                title material { slug } event { id } line { slug } station { slug }
            } } } } }', ['slug' => $this->material->slug])->assertGraphQLErrorFree()->json('data.material.media.edges.*.node');

            $this->assertCount(6, $nodes);
            foreach ($nodes as $node) {
                $this->assertSame($this->material->slug, $node['material']['slug']);
                $this->assertNull($node['event']);
                $this->assertNull($node['line']);
            }
        });

        // La série demandée, le compte de ses médias, puis une seule lecture pour l'entité des six médias
        // (au lieu de six, une par média).
        $this->assertCount(3, $queries, implode("\n", $queries));
    }

    public function test_the_event_of_the_media_of_an_event_is_read_once(): void
    {
        $queries = $this->queriesOn('line_events', function (): void {
            $nodes = $this->graphQL('query ($id: ID!) { lineEvent(id: $id) { media(first: 10) { edges { node {
                event { id } material { slug }
            } } } } }', ['id' => $this->eventId])->assertGraphQLErrorFree()->json('data.lineEvent.media.edges.*.node');

            $this->assertCount(6, $nodes);
            foreach ($nodes as $node) {
                $this->assertSame((string) $this->eventId, $node['event']['id']);
                $this->assertNull($node['material']);
            }
        });

        $this->assertCount(3, $queries, implode("\n", $queries));
    }

    public function test_a_mixed_list_reads_one_query_per_type_of_subject(): void
    {
        $count = 0;
        DB::listen(function (QueryExecuted $q) use (&$count): void {
            $count++;
        });
        $nodes = $this->graphQL('{ media(first: 20) { edges { node { material { slug } event { id } } } } }')
            ->assertGraphQLErrorFree()->json('data.media.edges.*.node');

        $this->assertCount(12, $nodes);
        $this->assertCount(6, array_filter(array_column($nodes, 'material')));
        $this->assertCount(6, array_filter(array_column($nodes, 'event')));
        // Liste et compte des médias, une requête par type d'entité : rien qui croisse avec le nombre de médias.
        $this->assertLessThanOrEqual(5, $count);
    }
}
