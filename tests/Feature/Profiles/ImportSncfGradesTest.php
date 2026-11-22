<?php

namespace Tests\Feature\Profiles;

use App\Services\Profiles\SourcedGrades;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * vff:import-sncf-grades : téléchargement du jeu SNCF (licence vérifiée), confrontation à Wikipédia
 * et au profil mesuré, valeurs aberrantes écartées, fichier écrit seulement hors passe à blanc,
 * import idempotent, base jamais modifiée.
 */
class ImportSncfGradesTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/vff-sncf-grades-'.getmypid().'-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            foreach (['out.php', 'report.csv', 'caracteristique-des-voies-et-declivite.json', '.nobackup'] as $file) {
                if (is_file($this->dir.'/'.$file)) {
                    unlink($this->dir.'/'.$file);
                }
            }
            rmdir($this->dir);
            SourcedGrades::flush();
        }
    }

    private function line(string $slug, string $code, int $length, ?float $measured): void
    {
        $id = DB::table('lines')->insertGetId(['slug' => $slug, 'name' => 'Ligne '.$code, 'code' => $code, 'length_official_m' => $length]);
        DB::table('line_profiles')->insert([
            'line_id' => $id, 'resource' => 'test', 'step_m' => 100, 'length_m' => $length, 'trace_length_m' => $length,
            'structures' => '[]', 'altitude_sources' => '{}', 'gaps' => '[]', 'stations' => '[]',
            'min_altitude_m' => 100, 'max_altitude_m' => 200, 'ascent_m' => 100, 'descent_m' => 0,
            'max_grade_permil' => $measured, 'max_grade_withheld' => $measured === null ? 'terrain_only' : null, 'computed_at' => now(),
        ]);
    }

    /** @param  array<string, mixed>  $meta */
    private function fakeSncf(array $meta = []): void
    {
        $segment = fn (string $code, string $type, float $value, string $from, string $to): array => [
            'code_ligne' => $code, 'lib_ligne' => 'Ligne '.$code, 'code_voie' => $code.'-1-V1', 'type' => $type, 'valeur' => $value, 'pkd' => $from, 'pkf' => $to,
        ];
        Http::fake([
            '*/datasets/caracteristique-des-voies-et-declivite/exports/json*' => Http::response([
                $segment('100000', 'RAMPE', 12.0, '001+000', '003+000'),   // cohérente : retenue
                $segment('100000', 'PENTE', 8.0, '004+000', '005+000'),
                $segment('200000', 'RAMPE', 90.0, '001+000', '003+000'),   // au-dessus de 60 ‰ en plaine
                $segment('300000', 'RAMPE', 30.0, '001+000', '003+000'),   // plus de 3 fois la mesure
                $segment('400000', 'RAMPE', 10.0, '001+000', '001+050'),   // couverture trop faible
                $segment('405000', 'RAMPE', 9.0, '001+000', '003+000'),    // Wikipédia (10 ‰) concordante
                $segment('999999', 'RAMPE', 9.0, '001+000', '003+000'),    // ligne inconnue
            ]),
            '*/datasets/caracteristique-des-voies-et-declivite*' => Http::response(['metas' => ['default' => array_replace([
                'license' => 'Open Database License (ODbL)', 'publisher' => 'SNCF Réseau', 'data_processed' => '2026-01-20T15:20:48+00:00', 'records_count' => 7,
            ], $meta)]]),
        ]);
        $this->line('rfn-100000', '100000', 10000, 10.0);
        $this->line('rfn-200000', '200000', 10000, null);
        $this->line('rfn-300000', '300000', 10000, 8.0);
        $this->line('rfn-400000', '400000', 10000, 10.0);
        $this->line('rfn-405000', '405000', 10000, 9.5);
    }

    /** @return array<string, string> */
    private function statuses(): array
    {
        $rows = array_map('str_getcsv', file($this->dir.'/report.csv', FILE_IGNORE_NEW_LINES) ?: []);
        array_shift($rows);

        return array_column($rows, 2, 0);
    }

    public function test_dry_run_reports_and_writes_nothing(): void
    {
        $this->fakeSncf();
        $before = DB::table('line_profiles')->orderBy('id')->get()->toJson();

        $this->artisan('vff:import-sncf-grades', ['--dry-run' => true, '--output' => $this->dir.'/out.php', '--csv' => $this->dir.'/report.csv'])
            ->expectsOutputToContain('Passe à blanc')
            ->assertSuccessful();

        $this->assertFileDoesNotExist($this->dir.'/out.php');
        $this->assertSame([
            '100000' => 'retained',
            '200000' => 'rejected_above_limit',
            '300000' => 'rejected_ratio',
            '400000' => 'rejected_coverage',
            '405000' => 'kept_wikipedia_agree',
            '999999' => 'line_unknown',
        ], $this->statuses());
        $this->assertSame($before, DB::table('line_profiles')->orderBy('id')->get()->toJson());
    }

    public function test_import_writes_only_the_retained_lines_and_is_idempotent(): void
    {
        $this->fakeSncf();
        $args = ['--output' => $this->dir.'/out.php', '--cache-dir' => $this->dir];

        $this->artisan('vff:import-sncf-grades', $args)->assertSuccessful();
        $first = (string) file_get_contents($this->dir.'/out.php');
        $entries = require $this->dir.'/out.php';

        $this->assertSame(['rfn-100000'], array_keys($entries));
        $this->assertSame(12.0, $entries['rfn-100000']['max_grade_permil']);
        $this->assertSame('sncf', $entries['rfn-100000']['provider']);
        $this->assertSame('ODbL 1.0', $entries['rfn-100000']['license']);
        $this->assertSame('SNCF Réseau', $entries['rfn-100000']['attribution']);
        $this->assertSame(0.3, $entries['rfn-100000']['covered_share']);
        $this->assertFileExists($this->dir.'/.nobackup');
        Http::assertSentCount(2);

        // Seconde passe, depuis le cache : même fichier, aucune requête de plus.
        $this->artisan('vff:import-sncf-grades', $args)->expectsOutputToContain('inchangé')->assertSuccessful();
        $this->assertSame($first, file_get_contents($this->dir.'/out.php'));
        Http::assertSentCount(2);
    }

    public function test_an_unexpected_licence_stops_the_import(): void
    {
        $this->fakeSncf(['license' => 'Licence Ouverte']);

        $this->artisan('vff:import-sncf-grades', ['--output' => $this->dir.'/out.php'])->assertFailed();
        $this->assertFileDoesNotExist($this->dir.'/out.php');
    }

    public function test_the_real_file_is_never_written_by_tests(): void
    {
        $this->fakeSncf();

        $this->artisan('vff:import-sncf-grades')->assertFailed();
    }

    public function test_an_sncf_grade_is_served_with_its_licence_by_rest_graphql_and_mcp(): void
    {
        // Ligne de Mézidon à Trouville-Deauville, retenue dans database/data/sourced_grades_sncf.php.
        $slug = 'rfn-379000';
        $this->line($slug, '379000', 50030, null);

        $this->getJson("/api/v1/lines/{$slug}/profile")->assertOk()
            ->assertJsonPath('summary.max_grade_permil', null)
            ->assertJsonPath('sourced_grade.source.provider', 'sncf')
            ->assertJsonPath('sourced_grade.source.license', 'ODbL 1.0')
            ->assertJsonPath('sourced_grade.source.attribution', 'SNCF Réseau')
            ->assertJsonPath('sourced_grade.covered_share', SourcedGrades::all()[$slug]['covered_share']);

        $data = $this->postJson('/graphql', ['query' => '{ line(slug: "'.$slug.'") { profile { sourced_grade { max_grade_permil covered_share source { provider license attribution url } } rack { source { attribution } } } } }'])
            ->assertOk()->json('data.line.profile.sourced_grade');
        $this->assertSame('sncf', $data['source']['provider']);
        $this->assertSame('ODbL 1.0', $data['source']['license']);
        $this->assertSame('SNCF Réseau', $data['source']['attribution']);
        $this->assertGreaterThan(0.1, $data['covered_share']);

        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => [
                'name' => 'line_profile', 'arguments' => ['line' => $slug, 'max_points' => 10],
            ]])->assertOk()->json('result');
        $payload = json_decode($result['content'][0]['text'], true);
        $this->assertSame('ODbL 1.0', $payload['profile']['sourced_grade']['source']['license']);
        $attribution = json_encode($payload['attribution'], JSON_UNESCAPED_UNICODE) ?: '';
        $this->assertStringContainsString('SNCF', $attribution);
        $this->assertStringNotContainsString('Wikip', $attribution);
    }
}
