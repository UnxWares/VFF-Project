<?php

namespace Tests\Feature\Api;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Audits à l'échelle du réseau : les mêmes pages, filtres, tris, `total`, `limit` et `offset` dans l'API
 * REST (`/api/v1/audits/segments`, `/stations`), GraphQL (`segmentAudits`, `stationAudits`) et le
 * serveur MCP (`segment_audits`, `station_audits`).
 */
class NetworkAuditParityTest extends TestCase
{
    use RefreshDatabase;

    private Line $orne;

    private Line $finistere;

    protected function setUp(): void
    {
        parent::setUp();
        $now = Era::factory()->create(['year' => 2030, 'label' => 'Aujourd’hui', 'is_current' => true]);
        $past = Era::factory()->create(['year' => 1930, 'label' => '1930']);
        $this->orne = Line::factory()->create(['slug' => 'rfn-111111', 'code' => '111111', 'name' => 'Ligne de Flers à Orne', 'departements' => json_encode(['Orne'])]);
        $this->finistere = Line::factory()->create(['slug' => 'rfn-222222', 'code' => '222222', 'name' => 'Ligne de Brest à Quimper', 'departements' => json_encode(['Finistère'])]);

        // Orne : 101 faux (placement, sur la voie d'une autre ligne), 102 sûr. Finistère : 201 à revoir (déposé), 202 sûr.
        $this->segment($now->id, $this->orne, 101, 'in_service', ['placement' => 'poor'], 90, 3000, ['placement' => ['on_other_line_track']]);
        $this->segment($now->id, $this->orne, 102, 'in_service', [], 0, 1000, []);
        $this->segment($now->id, $this->finistere, 201, 'dismantled', ['accuracy' => 'fair'], 40, 5000, ['accuracy' => ['hairpin']]);
        $this->segment($now->id, $this->finistere, 202, 'in_service', [], 0, 2000, []);
        $this->segment($past->id, $this->orne, 301, 'abandoned', ['classification' => 'poor'], 70, 800, ['classification' => ['source_contradicts']]);

        $this->station('argentan', 'Argentan', '61', 'disagree', 400, ['far_from_track'], 'sourced', 'not_applicable', $this->orne);
        $this->station('flers', 'Flers', '61', 'agree', 10, [], 'estimated', 'not_applicable', $this->orne);
        $this->station('brest', 'Brest', '29', 'disagree', 150, ['wikidata_differs'], 'sourced', 'unknown', $this->finistere);
    }

    /** @param  array<string, string>  $grades @param  array<string, list<string>>  $reasons */
    private function segment(int $eraId, Line $line, int $id, string $status, array $grades, int $score, int $length, array $reasons): void
    {
        $grades += ['classification' => 'good', 'placement' => 'good', 'accuracy' => 'good', 'trace' => 'good'];
        $worst = in_array('poor', $grades, true) ? 'poor' : (in_array('fair', $grades, true) ? 'fair' : 'good');
        $evidence = [];
        foreach ($grades as $check => $grade) {
            $evidence[$check] = ['reasons' => $reasons[$check] ?? ['no_defect']];
        }
        DB::table('segment_audits')->insert([
            'era_id' => $eraId, 'line_id' => $line->id, 'segment_id' => $id, 'scope' => 'segment', 'status' => $status, 'traffic_type' => 'mixed',
            'trace_source' => 'bdtopo', 'length_m' => $length, 'classification_grade' => $grades['classification'], 'placement_grade' => $grades['placement'],
            'accuracy_grade' => $grades['accuracy'], 'trace_grade' => $grades['trace'], 'worst_grade' => $worst, 'score' => $score,
            'evidence' => json_encode($evidence), 'rule_version' => '2026-11-21.5', 'audited_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @param  list<string>  $checks */
    private function station(string $slug, string $name, string $department, string $placement, int $spread, array $checks, string $opening, string $closure, Line $line): void
    {
        $station = Station::factory()->create(['slug' => $slug, 'name' => $name, 'department_code' => $department, 'status' => 'active']);
        DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $line->id, 'method' => 'sncf_pk', 'first_year' => 1866,
            'last_year' => null, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('station_audits')->insert([
            'station_id' => $station->id, 'placement_grade' => $placement, 'families' => 3, 'nearest_agreeing_m' => $spread, 'farthest_precise_m' => $spread,
            'track_offset_m' => 5, 'positions' => '[]', 'opening_grade' => $opening, 'closure_grade' => $closure, 'services' => '{}',
            'checks' => json_encode(array_map(fn (string $c): array => ['code' => $c], $checks)), 'checks_count' => count($checks),
            'region_code' => $department === '61' ? '28' : '53', 'rule_version' => '2026-11-21', 'audited_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function rest(string $kind, array $query = []): array
    {
        return $this->getJson('/api/v1/audits/'.$kind.'?'.http_build_query($query))->assertOk()->json();
    }

    /** Arguments GraphQL : `grade` est une énumération (POOR), les autres des chaînes. */
    private function args(array $args): string
    {
        $parts = [];
        foreach ($args as $name => $value) {
            $parts[] = $name.': '.match (true) {
                $name === 'grade' && ! in_array($value, ['agree', 'close', 'isolated', 'disagree', 'none'], true) => strtoupper($value),
                is_int($value) => (string) $value,
                default => json_encode($value),
            };
        }

        return $parts === [] ? '' : '('.implode(', ', $parts).')';
    }

    /** @return array<string, mixed> */
    private function graphql(string $field, string $selection, array $args = []): array
    {
        return $this->postJson('/graphql', ['query' => '{ '.$field.$this->args($args).' { '.$selection.' } }'])
            ->assertOk()->assertJsonMissingPath('errors')->json('data.'.$field);
    }

    /** @return array<string, mixed> */
    private function mcp(string $tool, array $arguments): array
    {
        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => $arguments]])
            ->assertOk()->json('result');
        $this->assertFalse($result['isError'] ?? false, $result['content'][0]['text'] ?? '');

        return json_decode($result['content'][0]['text'], true);
    }

    /** @return list<int> */
    private function ids(array $page): array
    {
        return array_map('intval', array_column($page['items'], 'segment_id'));
    }

    private const SEGMENT_FIELDS = 'era_year total limit offset items { line_slug line_code line_name segment_id status traffic_type trace_source length_m grades { classification placement accuracy trace } worst_grade score reasons audited_at }';

    private const STATION_FIELDS = 'total limit offset items { station_id slug name department region status position_source placement_grade families nearest_agreeing_m farthest_precise_m track_offset_m opening_grade closure_grade passenger freight checks sources }';

    /** @return array<string, array{array<string, mixed>, list<int>, int}> filtre => tronçons attendus, total */
    public static function segmentCases(): array
    {
        return [
            'tous, le plus mal noté d’abord' => [[], [101, 201, 202, 102], 4],
            'note la plus mauvaise' => [['grade' => 'poor'], [101], 1],
            'note d’un contrôle' => [['grade' => 'fair', 'check' => 'accuracy'], [201], 1],
            'note d’un autre contrôle, aucun' => [['grade' => 'poor', 'check' => 'accuracy'], [], 0],
            'raison dans un contrôle' => [['reason' => 'hairpin', 'check' => 'accuracy'], [201], 1],
            'raison dans le mauvais contrôle' => [['reason' => 'hairpin', 'check' => 'placement'], [], 0],
            'raison dans tous les contrôles' => [['reason' => 'on_other_line_track'], [101], 1],
            'état' => [['status' => 'dismantled'], [201], 1],
            'département' => [['department' => '29'], [201, 202], 2],
            'région' => [['region' => '28'], [101, 102], 2],
            'ligne par code' => [['line' => '222222'], [201, 202], 2],
            'nom de ligne sans accents ni casse' => [['q' => 'flers'], [101, 102], 2],
            'tri par longueur' => [['sort' => 'length'], [201, 101, 202, 102], 4],
            'tri par ligne' => [['sort' => 'line'], [101, 102, 201, 202], 4],
            'autre ère' => [['era' => 1930], [301], 1],
            'ère sans audit' => [['era' => 1830], [], 0],
            'page' => [['limit' => 2, 'offset' => 1], [201, 202], 4],
        ];
    }

    #[DataProvider('segmentCases')]
    public function test_segment_audits_agree_in_the_three_apis(array $filters, array $expected, int $total): void
    {
        $rest = $this->rest('segments', $filters);
        $graphql = $this->graphql('segmentAudits', self::SEGMENT_FIELDS, $filters);
        $mcp = $this->mcp('segment_audits', $filters);

        foreach ([$rest, $graphql, $mcp] as $i => $page) {
            $this->assertSame($expected, $this->ids($page), "interface {$i}");
            $this->assertSame($total, $page['total'], "interface {$i}");
        }
        // Mêmes champs, mêmes valeurs, d'une interface à l'autre.
        $this->assertSame($rest['items'], array_map(fn (array $i): array => $i, $this->normalize($graphql['items'])));
        $this->assertSame($rest['items'], $mcp['items']);
        foreach (['era_year', 'limit', 'offset'] as $key) {
            $this->assertSame($rest[$key], $graphql[$key]);
            $this->assertSame($rest[$key], $mcp[$key]);
        }
    }

    /**
     * GraphQL rend les notes en énumérations (`POOR` pour `poor`) et `segment_id` en chaîne : on ramène
     * au format REST pour comparer les valeurs.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function normalize(array $items): array
    {
        return array_map(function (array $i): array {
            $i['segment_id'] = (int) $i['segment_id'];
            $i['grades'] = array_map('strtolower', $i['grades']);
            $i['worst_grade'] = strtolower($i['worst_grade']);

            return $i;
        }, $items);
    }

    public function test_segment_rows_carry_the_line_the_grades_and_the_reasons(): void
    {
        $first = $this->rest('segments')['items'][0];
        $this->assertSame('rfn-111111', $first['line_slug']);
        $this->assertSame('Ligne de Flers à Orne', $first['line_name']);
        $this->assertSame(101, $first['segment_id']);
        $this->assertSame('poor', $first['grades']['placement']);
        $this->assertSame(['on_other_line_track'], $first['reasons']['placement']);
        $this->assertSame(90, $first['score']);
    }

    /** @return array<string, array{array<string, mixed>, list<string>, int}> */
    public static function stationCases(): array
    {
        return [
            'toutes, les plus éloignées d’abord' => [[], ['argentan', 'brest', 'flers'], 3],
            'note de placement' => [['grade' => 'disagree'], ['argentan', 'brest'], 2],
            'contrôle en échec' => [['check' => 'wikidata_differs'], ['brest'], 1],
            'date d’ouverture' => [['opening' => 'estimated'], ['flers'], 1],
            'date de fermeture' => [['closure' => 'unknown'], ['brest'], 1],
            'département' => [['department' => '61'], ['argentan', 'flers'], 2],
            'région' => [['region' => '53'], ['brest'], 1],
            'ligne' => [['line' => 'rfn-111111'], ['argentan', 'flers'], 2],
            'nom sans accents' => [['q' => 'ARGEN'], ['argentan'], 1],
            'tri par nom' => [['sort' => 'name'], ['argentan', 'brest', 'flers'], 3],
            'page' => [['limit' => 1, 'offset' => 1], ['brest'], 3],
        ];
    }

    #[DataProvider('stationCases')]
    public function test_station_audits_agree_in_the_three_apis(array $filters, array $expected, int $total): void
    {
        $rest = $this->rest('stations', $filters);
        $graphql = $this->graphql('stationAudits', self::STATION_FIELDS, $filters);
        $mcp = $this->mcp('station_audits', $filters);

        foreach ([$rest, $graphql, $mcp] as $i => $page) {
            $this->assertSame($expected, array_column($page['items'], 'slug'), "interface {$i}");
            $this->assertSame($total, $page['total'], "interface {$i}");
        }
        $graphql['items'] = array_map(fn (array $i): array => ['station_id' => (int) $i['station_id']] + $i, $graphql['items']);
        $this->assertSame($rest['items'], $graphql['items']);
        $this->assertSame($rest['items'], $mcp['items']);
        $this->assertSame($rest['limit'], $mcp['limit']);
        $this->assertSame($rest['offset'], $graphql['offset']);
    }

    public function test_the_page_size_limits_are_the_same_everywhere(): void
    {
        $this->assertSame(50, $this->rest('segments')['limit']);
        $this->assertSame(50, $this->rest('stations')['limit']);
        $this->assertSame(50, $this->mcp('segment_audits', [])['limit']);
        $this->assertSame(200, $this->rest('segments', ['limit' => 200])['limit']);

        foreach (['segments', 'stations'] as $kind) {
            $this->getJson('/api/v1/audits/'.$kind.'?limit=201')->assertUnprocessable();
            $this->getJson('/api/v1/audits/'.$kind.'?limit=0')->assertUnprocessable();
            $this->getJson('/api/v1/audits/'.$kind.'?offset=-1')->assertUnprocessable();
            $this->getJson('/api/v1/audits/'.$kind.'?grade=excellent')->assertUnprocessable();
        }
        foreach (['segmentAudits' => 'era_year', 'stationAudits' => 'total'] as $field => $sub) {
            foreach (['limit: 201', 'limit: 0', 'offset: -1'] as $args) {
                $errors = $this->postJson('/graphql', ['query' => "{ {$field}({$args}) { {$sub} } }"])->assertOk()->json('errors');
                $this->assertNotEmpty($errors, "{$field} {$args}");
                $this->assertArrayHasKey('validation', $errors[0]['extensions'], "{$field} {$args}");
            }
        }
        foreach (['segment_audits', 'station_audits'] as $tool) {
            foreach ([['limit' => 201], ['offset' => -1], ['grade' => 'excellent']] as $arguments) {
                $refused = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
                    ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => $arguments]])
                    ->assertOk()->json('result');
                $this->assertTrue($refused['isError'] ?? false, $tool.' '.json_encode($arguments));
            }
        }
    }

    public function test_a_lowercase_department_is_accepted_by_the_rest_api(): void
    {
        $this->assertSame(0, $this->rest('segments', ['department' => '2a'])['total']);
        $this->assertSame(2, $this->rest('segments', ['department' => '29'])['total']);
    }
}
