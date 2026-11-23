<?php

namespace Tests\Feature\Signalling;

use App\Mcp\Resources\LineSheetResource;
use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Services\Signalling\SignallingDating;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

/**
 * Mention « aucun document libre consulté ne date la mise en service de la signalisation de cette ligne » :
 * calculée (ligne classique en service sans cantonnement daté), jamais écrite à la main, avec la date de la dernière
 * vérification, dans les trois API (REST, GraphQL, MCP) et la fiche Markdown.
 */
class SignallingDatingTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private Era $era;

    protected function setUp(): void
    {
        parent::setUp();
        $this->era = Era::factory()->current()->create(['year' => 2030]);
    }

    private function line(string $slug, array $attributes = [], string $status = 'in_service', bool $heritage = false): Line
    {
        $line = Line::factory()->create($attributes + ['slug' => $slug, 'code' => substr($slug, -6), 'name' => 'Ligne '.$slug]);
        LineSegment::factory()->create(['line_id' => $line->id, 'era_id' => $this->era->id, 'status' => $status, 'is_heritage' => $heritage]);

        return $line;
    }

    private function fact(Line $line, string $system, string $role, string $confidence = 'high'): void
    {
        DB::table('line_signalling_events')->insert([
            'line_id' => $line->id, 'system' => $system, 'role' => $role, 'event_date' => '1976-01-01', 'date_precision' => 'year', 'scope' => 'whole_line',
            'confidence' => $confidence, 'origin' => 'text', 'source' => 'wikipedia', 'source_title' => 'Article', 'source_url' => 'https://fr.wikipedia.org/w/index.php?oldid=1',
            'source_excerpt' => 'Extrait', 'fingerprint' => substr(md5($line->id.$system.$role.$confidence), 0, 40), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_the_status_is_computed_from_the_service_state_and_the_dated_block_facts(): void
    {
        $undated = $this->line('rfn-100001');
        $dated = $this->line('rfn-100002');
        $this->fact($dated, 'BAL', 'block');
        $cab = $this->line('rfn-100003');
        $this->fact($cab, 'ETCS_L1', 'cab', 'medium');
        // Le KVB et la commande centralisée ne datent pas un cantonnement ; un fait peu fiable n'est pas exposé donc ne compte pas.
        $kvbOnly = $this->line('rfn-100004');
        $this->fact($kvbOnly, 'KVB', 'speed_control');
        $this->fact($kvbOnly, 'CC', 'remote_control');
        $this->fact($kvbOnly, 'BAL', 'block', 'low');
        // Hors périmètre : LGV, ligne fermée, train touristique seul, LGV reconnue par son nom.
        $lgv = $this->line('rfn-100005', ['is_lgv' => true]);
        $closed = $this->line('rfn-100006', [], 'abandoned');
        $tourist = $this->line('rfn-100007', [], 'in_service', true);
        $named = $this->line('rfn-100008', ['name' => 'LGV Test']);

        $all = array_map(fn (Line $l): int => $l->id, [$undated, $dated, $cab, $kvbOnly, $lgv, $closed, $tourist, $named]);
        $status = array_map(fn (array $d): string => $d['status'], app(SignallingDating::class)->forLines($all));

        $this->assertSame([
            $undated->id => 'undated', $dated->id => 'dated', $cab->id => 'dated', $kvbOnly->id => 'undated',
            $lgv->id => 'not_applicable', $closed->id => 'not_applicable', $tourist->id => 'not_applicable', $named->id => 'not_applicable',
        ], $status);
    }

    public function test_a_new_dated_fact_removes_the_notice(): void
    {
        $line = $this->line('rfn-100010');
        $this->assertSame('undated', app(SignallingDating::class)->forLine($line)['status']);

        $this->fact($line, 'BAPR', 'block');

        $dating = app(SignallingDating::class)->forLine($line);
        $this->assertSame('dated', $dating['status']);
        $this->assertNull($dating['messages']);
    }

    public function test_the_notice_is_in_three_languages_with_the_check_date(): void
    {
        $dating = app(SignallingDating::class)->forLine($this->line('rfn-100011'));

        $this->assertSame(SignallingDating::checkedAt(), $dating['checked_at']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $dating['checked_at']);
        $this->assertSame('Aucun document libre consulté ne date la mise en service de la signalisation de cette ligne.', $dating['messages']['fr']);
        $this->assertSame('No free document consulted dates the commissioning of this line’s signalling.', $dating['messages']['en']);
        $this->assertStringStartsWith('Kein eingesehenes freies Dokument', $dating['messages']['de']);
    }

    public function test_rest_gives_the_dating_on_both_endpoints(): void
    {
        $this->line('rfn-100012');
        $dated = $this->line('rfn-100013');
        $this->fact($dated, 'BAL', 'block');

        $this->getJson('/api/v1/lines/rfn-100012/signalling-history')->assertOk()
            ->assertJsonPath('dating.status', 'undated')
            ->assertJsonPath('dating.checked_at', SignallingDating::checkedAt())
            ->assertJsonPath('dating.messages.fr', 'Aucun document libre consulté ne date la mise en service de la signalisation de cette ligne.');
        $this->getJson('/api/v1/lines/rfn-100012')->assertOk()
            ->assertJsonPath('data.signalling.dating.status', 'undated')
            ->assertJsonPath('data.signalling.dating.messages.de', fn ($m) => str_contains((string) $m, 'Signalisierung'));
        $this->getJson('/api/v1/lines/rfn-100013/signalling-history')->assertOk()
            ->assertJsonPath('dating.status', 'dated')
            ->assertJsonPath('dating.messages', null);
    }

    public function test_graphql_gives_the_dating_on_the_line_and_on_its_sheet(): void
    {
        $this->line('rfn-100014');
        $dated = $this->line('rfn-100015');
        $this->fact($dated, 'BM', 'block');

        $this->graphQL('{ lines(first: 50) { data { slug signalling_dating { status checked_at messages { fr en de } } } } }')
            ->assertJsonMissingPath('errors')
            ->assertJsonFragment(['slug' => 'rfn-100014', 'signalling_dating' => [
                'status' => 'undated', 'checked_at' => SignallingDating::checkedAt(),
                'messages' => app(SignallingDating::class)->forLines([Line::where('slug', 'rfn-100014')->value('id')])[Line::where('slug', 'rfn-100014')->value('id')]['messages'],
            ]])
            ->assertJsonFragment(['slug' => 'rfn-100015', 'signalling_dating' => ['status' => 'dated', 'checked_at' => SignallingDating::checkedAt(), 'messages' => null]]);
        $this->graphQL('{ line(slug: "rfn-100014") { sheet { signalling { dating { status checked_at } } } } }')
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.line.sheet.signalling.dating.status', 'undated');
    }

    public function test_mcp_gives_the_dating_in_the_history_tool_and_the_line_sheet(): void
    {
        $this->line('rfn-100016');
        $headers = ['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'];
        $call = fn (string $tool, array $arguments): array => json_decode($this->withHeaders($headers)->postJson('/mcp', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => (object) $arguments],
        ])->assertOk()->json('result.content.0.text'), true);

        $this->assertSame('undated', $call('signalling_history', ['line' => 'rfn-100016'])['dating']['status']);
        $sheet = $call('get_line', ['line' => 'rfn-100016', 'include_events' => false]);
        $this->assertSame('undated', $sheet['line']['signalling']['dating']['status']);
        $this->assertSame(SignallingDating::checkedAt(), $sheet['line']['signalling']['dating']['checked_at']);
    }

    public function test_the_markdown_sheet_says_it_in_the_language_of_the_reader_and_only_when_undated(): void
    {
        $sheet = fn (string $status): array => [
            'name' => 'Ligne de A à B', 'code' => '100000', 'max_speed_kmh' => null, 'electrification' => null,
            'length' => ['trace_km' => 10.0, 'in_service_today_km' => 10.0],
            'signalling' => ['block_systems_km' => [], 'history' => [], 'dating' => ['status' => $status, 'checked_at' => '2026-11-23', 'messages' => null]],
            'facts' => [], 'phases' => [], 'events' => [],
        ];
        $attribution = ['notice' => '', 'sources' => []];

        App::setLocale('de');
        $de = LineSheetResource::markdown($sheet('undated'), $attribution, 'https://vff.test/lines/x');
        $this->assertStringContainsString('Kein eingesehenes freies Dokument datiert die Inbetriebnahme der Signalisierung dieser Strecke. Zuletzt geprüft: 23. November 2026.', $de);

        App::setLocale('fr');
        $fr = LineSheetResource::markdown($sheet('undated'), $attribution, 'https://vff.test/lines/x');
        $this->assertStringContainsString('## Signalisation et protection au fil du temps', $fr);
        $this->assertStringContainsString('Aucun document libre consulté ne date la mise en service de la signalisation de cette ligne. Dernière vérification : 23 novembre 2026.', $fr);
        foreach (['dated', 'not_applicable'] as $status) {
            $this->assertStringNotContainsString('Aucun document libre', LineSheetResource::markdown($sheet($status), $attribution, 'https://vff.test/lines/x'));
        }
    }

    public function test_the_check_date_file_holds_a_valid_past_date(): void
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', SignallingDating::checkedAt());
        $this->assertNotFalse($date);
        $this->assertSame(SignallingDating::checkedAt(), $date->format('Y-m-d'));
        $this->assertLessThanOrEqual(new \DateTimeImmutable('tomorrow'), $date);
    }
}
