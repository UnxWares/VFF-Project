<?php

namespace Tests\Feature\Api;

use App\Services\LineSheet;
use App\Services\NetworkChronology;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Aucune donnée d'une source à usage non commercial ne sort du site (App\Support\RedistributableDates) : ni fait,
 * ni date, ni extrait, ni lien, par aucun endpoint REST, champ GraphQL ou outil MCP. Seul le crédit reste : le lien
 * `archeo_url` de la fiche ligne (et l'entrée de `sources` de la fiche qui le reprend), la notice du fournisseur
 * (`/api/v1/sources`, `withheld`, `provenance`, mentions du MCP).
 *
 * Données marquées (réseau BuildsRailNetwork) : un nom de tracé de cette source (« Zarchlabel »), une fermeture de
 * cette source et une électrification d'une autre source qui ne cite qu'elle (lien `article998`, phrases « Zarchdesc »
 * et « Zarchquote », années 1901 et 1902), une fermeture de gare qui la cite (1903) et une autre déduite de la
 * fermeture écartée, un écartement de 762 mm lu dans son calque.
 */
class NonCommercialSourceLeaksTest extends TestCase
{
    use BuildsRailNetwork;
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private const ARTICLE = 'http://archeoferroviaire.free.fr/v31/spip.php?article998';

    private const CREDIT = 'http://archeoferroviaire.free.fr/v31/spip.php?article999';

    /** Ce qui ne doit sortir nulle part (le crédit `article999` est permis). */
    private const MARKERS = ['Zarch', 'article998', '1901-01-01', '1902-01-01', '1903-01-01', '"gauge_mm":762', '\"gauge_mm\":762'];

    private int $closure;

    private int $electrification;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ThrottleRequests::class, ThrottleRequestsWithRedis::class]);
        $this->buildRailNetwork();

        $this->line->update(['archeo_url' => self::CREDIT,
            'alt_names' => ['labels' => ['Zarchlabel Hessange'], 'source' => 'archeoferroviaire', 'name_source' => 'archeoferroviaire',
                'names' => [['name' => 'Ligne de Saint-Lazare à Versailles', 'kind' => 'name', 'source' => 'wikidata', 'source_url' => 'https://www.wikidata.org/wiki/Q1']]]]);
        $this->closure = $this->lineEvent('closure', '1901-01-01', 'archeoferroviaire');
        $this->electrification = $this->lineEvent('electrification', '1902-01-01', 'manual');
        DB::table('station_events')->insert([
            ['station_id' => $this->origin->id, 'type' => 'closure', 'event_date' => '1903-01-01', 'date_precision' => 'year', 'source' => 'manual',
                'source_ref' => null, 'source_url' => self::ARTICLE, 'note' => 'Zarchnote', 'is_inferred' => false, 'created_at' => now(), 'updated_at' => now()],
            ['station_id' => $this->origin->id, 'type' => 'closure_passenger', 'event_date' => '1901-01-01', 'date_precision' => 'year', 'source' => 'inferred:line_event',
                'source_ref' => 'line_event:'.$this->closure, 'source_url' => null, 'note' => null, 'is_inferred' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('line_segments')->where('line_id', $this->line->id)->update(['source' => 'archeoferroviaire', 'gauge_source' => 'archeo', 'gauge_mm' => 762, 'gauge_class' => 'narrow']);
    }

    private function lineEvent(string $type, string $date, string $source): int
    {
        return (int) DB::table('line_events')->insertGetId(['line_id' => $this->line->id, 'type' => $type, 'event_date' => $date,
            'date_precision' => 'year', 'description' => 'Zarchdesc '.$type, 'source_quote' => 'Zarchquote', 'source' => $source,
            'source_url' => self::ARTICLE, 'confidence' => 'medium', 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @return list<string> marqueurs trouvés, avec leur contexte */
    private function leaks(string $where, string $body): array
    {
        $found = [];
        foreach (self::MARKERS as $m) {
            if (($p = stripos($body, $m)) !== false) {
                $found[] = $where.' : '.$m.' … '.substr($body, max(0, $p - 80), 160);
            }
        }

        return $found;
    }

    public function test_the_line_history_leaves_out_such_events(): void
    {
        $res = $this->getJson('/api/v1/lines/'.$this->line->slug.'/history')->assertOk();
        $this->assertSame(['1839-08-02'], array_column($res->json('events'), 'date'));
    }

    public function test_the_electrification_history_leaves_out_an_event_that_only_cites_such_a_source(): void
    {
        $this->getJson('/api/v1/lines/'.$this->line->slug.'/electrification-history')->assertOk()->assertJsonPath('history', []);
        $this->graphQL('query($s: String!) { line(slug: $s) { electrification_history { history { date } } } }', ['s' => $this->line->slug])
            ->assertJsonPath('data.line.electrification_history.history', []);
    }

    public function test_other_names_of_such_a_source_stay_on_the_site(): void
    {
        $sheet = $this->getJson('/api/v1/lines/'.$this->line->slug)->assertOk();
        $this->assertSame([['Ligne de Saint-Lazare à Versailles', 'wikidata']],
            array_map(fn (array $n): array => [$n['name'], $n['source']], $sheet->json('data.names')));
        // Le crédit reste : lien vers l'article de la source.
        $this->assertSame(self::CREDIT, $sheet->json('data.archeo_url'));
        $this->assertSame(762, app(LineSheet::class)->build($this->line, true)['stats']['gauge_mm'], 'le site garde l\'écartement');
        $this->assertNull($sheet->json('data.key_figures.gauge_mm'));

        // GraphQL : la colonne est un objet de provenance, le champ en tire la même liste (il échouait).
        $this->graphQL('query($s: String!) { line(slug: $s) { alt_names } }', ['s' => $this->line->slug])
            ->assertJsonMissingPath('errors')->assertJsonPath('data.line.alt_names', ['Ligne de Saint-Lazare à Versailles']);

        $page = app(LineSheet::class)->build($this->line, true);
        $this->assertContains('Zarchlabel Hessange', $page['line']['alt_names'], 'le site garde le nom, avec son crédit');
    }

    public function test_a_gauge_read_in_such_a_source_is_null_off_site(): void
    {
        $slug = $this->line->slug;
        $this->assertSame([null], array_unique(array_column($this->getJson("/api/v1/lines/{$slug}/segments")->assertOk()->json('data'), 'gauge_mm')));
        $this->assertSame([null], array_unique(array_column($this->getJson("/api/v1/lines/{$slug}/segments")->json('data'), 'gauge_class')));
        $this->assertSame([null], array_unique(array_column(array_column($this->getJson("/api/v1/lines/{$slug}/geojson")->assertOk()->json('features'), 'properties'), 'gauge_mm')));
        $era = json_decode((string) $this->get('/api/v1/eras/1960/lines.geojson')->assertOk()->streamedContent(), true);
        $this->assertSame([null], array_unique(array_column(array_column($era['features'], 'properties'), 'gauge_mm')));
        $gql = $this->graphQL('query($s: String!) { line(slug: $s) { segments { gauge_mm gauge_class } } }', ['s' => $slug])->assertJsonMissingPath('errors');
        $this->assertSame([['gauge_mm' => null, 'gauge_class' => null]], array_values(array_unique($gql->json('data.line.segments'), SORT_REGULAR)));
        $this->assertNull($this->getJson('/api/v1/what-was-here?lat=48.8530&lng=2.2400&year=2030')->assertOk()->json('lines.0.gauge_mm'));

        // Un écartement d'une autre source reste servi.
        DB::table('line_segments')->where('line_id', $this->line->id)->update(['gauge_source' => 'osm']);
        $this->assertSame([762], array_unique(array_column($this->getJson("/api/v1/lines/{$slug}/segments")->json('data'), 'gauge_mm')));
    }

    public function test_the_chronology_does_not_even_count_such_a_fact(): void
    {
        Cache::forget(NetworkChronology::CACHE_KEY);
        $years = array_column($this->getJson('/api/v1/chronology/1900')->assertOk()->json('data.years'), 'year');
        $this->assertNotContains(1902, $years);
    }

    /**
     * Balayage : chaque endpoint REST (GET), chaque outil MCP et chaque champ racine de GraphQL (sélection récursive),
     * avec les identifiants du réseau de test ; aucun marqueur dans aucune réponse.
     */
    public function test_no_endpoint_tool_or_graphql_field_serves_such_a_source(): void
    {
        Cache::forget(NetworkChronology::CACHE_KEY);
        $leaks = [];
        $calls = 0;

        // REST.
        $params = ['line' => $this->line->slug, 'station' => $this->origin->slug, 'event' => (string) $this->closure, 'decade' => '1900',
            'year' => '1960', 'monthDay' => '01-01', 'department' => '75', 'material' => $this->material->slug, 'other' => '2030',
            'provider' => 'archeoferroviaire'];
        $query = '?line='.$this->line->slug.'&station='.$this->origin->slug.'&year=1900&q=Paris&lat=48.853&lng=2.24';
        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            /** @var Route $route */
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/') || ! in_array('GET', $route->methods(), true) || str_ends_with($uri, '.mvt') || str_contains($uri, 'gtfs')) {
                continue;
            }
            $path = '/'.preg_replace_callback('/\{(\w+)(?::\w+)?\??\}/', fn (array $m): string => $params[$m[1]] ?? 'x', $uri);
            foreach ([$path, $path.$query, str_replace((string) $this->closure, (string) $this->electrification, $path)] as $p) {
                $res = $this->get($p);
                $calls++;
                $body = $res->baseResponse instanceof StreamedResponse ? (string) $res->streamedContent() : (string) $res->getContent();
                // La notice du fournisseur (crédit) nomme la source et ses valeurs : seule exception.
                if (! str_starts_with($p, '/api/v1/sources')) {
                    array_push($leaks, ...$this->leaks('REST '.$p, $body));
                }
            }
        }

        // MCP.
        $rpc = fn (string $method, array $p = []) => $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $p]);
        $fill = ['line' => $this->line->slug, 'station' => $this->origin->slug, 'event' => $this->closure, 'year' => 1960, 'decade' => 1900,
            'q' => 'Paris', 'query' => 'Paris', 'lat' => 48.853, 'lng' => 2.24, 'lon' => 2.24, 'month_day' => '01-01', 'date' => '01-01',
            'material' => $this->material->slug, 'from' => 1900, 'to' => 2030, 'department' => '75', 'pk' => '1+000'];
        $tools = $rpc('tools/list')->assertOk()->json('result.tools');
        $this->assertNotEmpty($tools);
        foreach ($tools as $tool) {
            $args = [];
            foreach (array_keys($tool['inputSchema']['properties'] ?? []) as $name) {
                if (array_key_exists($name, $fill)) {
                    $args[$name] = $fill[$name];
                }
            }
            foreach ([$args, ['line' => $this->line->slug, 'event' => $this->electrification], array_intersect_key($args, ['line' => 1, 'station' => 1])] as $a) {
                $calls++;
                array_push($leaks, ...$this->leaks('MCP '.$tool['name'].' '.json_encode($a), (string) $rpc('tools/call', ['name' => $tool['name'], 'arguments' => (object) $a])->getContent()));
            }
        }

        // GraphQL : chaque champ racine, sélection récursive (profondeur 2), un champ à la fois si la requête est trop chère.
        $types = [];
        foreach ($this->graphQL('{ __schema { types { name kind fields { name args { name defaultValue type { kind name ofType { kind name ofType { kind name } } } }
            type { kind name ofType { kind name ofType { kind name ofType { kind name } } } } } } } }')->json('data.__schema.types') as $t) {
            $types[$t['name']] = $t;
        }
        $named = function (array $t): array {
            while (($t['ofType'] ?? null) !== null) {
                $t = $t['ofType'];
            }

            return $t;
        };
        $values = ['slug' => $this->line->slug, 'id' => $this->closure, 'year' => 1960, 'line' => $this->line->slug, 'station' => $this->origin->slug,
            'first' => 5, 'lang' => 'fr', 'q' => 'Paris', 'decade' => 1900];
        $args = function (array $field) use ($values, $named): ?string {
            $out = [];
            foreach ($field['args'] as $a) {
                $type = $named($a['type']);
                $v = $values[$a['name']] ?? null;
                $literal = match (true) {
                    $v === null => null,
                    $type['kind'] === 'ENUM' => is_string($v) ? $v : null,
                    in_array($type['name'], ['Int', 'Float'], true) => is_int($v) ? (string) $v : null,
                    in_array($type['name'], ['String', 'ID'], true) => json_encode((string) $v),
                    default => null,
                };
                if ($literal !== null) {
                    $out[] = $a['name'].': '.$literal;
                } elseif ($a['type']['kind'] === 'NON_NULL' && $a['defaultValue'] === null) {
                    return null;
                }
            }

            return $out === [] ? '' : '('.implode(', ', $out).')';
        };
        $select = function (array $field, int $depth) use (&$select, $types, $named, $args): string {
            $a = $args($field);
            $type = $named($field['type']);
            if ($a === null || ($field['name'] === 'story' && $depth < 2)) {
                return '';
            }
            if (in_array($type['kind'], ['SCALAR', 'ENUM'], true)) {
                return $field['name'].$a;
            }
            if ($depth === 0) {
                return '';
            }
            $inner = implode(' ', array_filter(array_map(fn (array $f): string => $select($f, $depth - 1), $types[$type['name']]['fields'] ?? [])));

            return $inner === '' ? '' : $field['name'].$a.' { '.$inner.' }';
        };
        foreach ($types['Query']['fields'] as $root) {
            $a = $args($root);
            if ($a === null) {
                continue;
            }
            $body = (string) $this->graphQL('{ '.$select($root, 3).' }')->getContent();
            $calls++;
            if (str_contains($body, 'query_too_complex')) {
                $body = '';
                $type = $named($root['type']);
                foreach ($types[$type['name']]['fields'] ?? [] as $f) {
                    $inner = $select($f, 2);
                    if ($inner !== '') {
                        $body .= (string) $this->graphQL('{ '.$root['name'].$a.' { '.$inner.' } }')->getContent();
                        $calls++;
                    }
                }
            }
            if (! in_array($root['name'], ['sources', 'source'], true)) {
                array_push($leaks, ...$this->leaks('GraphQL '.$root['name'], $body));
            }
        }

        $this->assertGreaterThan(300, $calls);
        $this->assertSame([], $leaks);
    }
}
