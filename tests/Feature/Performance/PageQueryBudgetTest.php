<?php

namespace Tests\Feature\Performance;

use App\Services\LineSheet;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Budget de requêtes SQL des pages les plus visitées (chantier « performances » du 19/11/2026) : un
 * N+1 ou une requête en double ajoutée par mégarde fait échouer le test. Les plafonds sont ceux mesurés
 * sur le petit réseau de BuildsRailNetwork ; ils ne dépendent pas du nombre de lignes ni de gares.
 * Mesures et corrections : storage/app/reports/agents/soir/performances.md.
 */
class PageQueryBudgetTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
        DB::table('station_lines')->insert(['station_id' => $this->origin->id, 'line_id' => $this->line->id,
            'method' => 'sncf_pk', 'pk_m' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Requêtes lancées par une visite (hors transaction de RefreshDatabase).
     *
     * @return list<string>
     */
    private function queriesOf(string $url): array
    {
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $this->get($url)->assertOk();

        return $queries;
    }

    /** @return array<string, array{0: string, 1: int}> */
    public static function pages(): array
    {
        return [
            'accueil' => ['/', 10],
            'carte' => ['/maps', 3],
            'annuaire des lignes' => ['/lines', 10],
            'annuaire des gares' => ['/stations', 7],
            'matériel' => ['/materials', 4],
            'accidents' => ['/accidents', 5],
            'API' => ['/api', 0],
        ];
    }

    #[DataProvider('pages')]
    public function test_page_stays_within_its_query_budget(string $url, int $budget): void
    {
        $queries = $this->queriesOf($url);

        $this->assertLessThanOrEqual($budget, count($queries), "{$url} : ".count($queries)." requêtes\n".implode("\n", $queries));
    }

    public function test_line_sheet_stays_within_its_query_budget(): void
    {
        $queries = $this->queriesOf('/lines/'.$this->line->slug);

        $this->assertLessThanOrEqual(52, count($queries), count($queries)." requêtes\n".implode("\n", $queries));
        // La page ne lit plus la géométrie de l'ère affichée (LineSheet::PAGE_OMITTED).
        $this->assertEmpty(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'ST_AsGeoJSON(ls.geom, 6)')));
    }

    public function test_line_sheet_reads_the_stations_and_their_terminals_once(): void
    {
        $queries = $this->queriesOf('/lines/'.$this->line->slug);
        $count = fn (string $needle): int => count(array_filter($queries, fn (string $sql): bool => str_contains($sql, $needle)));

        // LineStations::forLine (gares de la ligne puis terminus) : la liste, l'en-tête et la vue
        // « décennie par décennie » reprennent le même résultat (LineStations::byEraOf).
        $this->assertSame(1, $count("provenance->>'closed_after' AS closed_after"), 'requête des gares de la ligne');
        $this->assertSame(1, $count('ST_Dump(ST_LineMerge(ST_Collect(geom)))'), 'requête des terminus');
    }

    public function test_station_sheet_stays_within_its_query_budget(): void
    {
        $queries = $this->queriesOf('/stations/'.$this->origin->slug);

        $this->assertLessThanOrEqual(7, count($queries), count($queries)." requêtes\n".implode("\n", $queries));
    }

    public function test_the_last_segment_update_is_read_through_an_index(): void
    {
        $this->assertTrue(collect(DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'line_segments'"))
            ->pluck('indexname')->contains('line_segments_updated_at_index'));
    }

    public function test_map_segment_counts_are_cached_until_the_segments_change(): void
    {
        $this->get('/maps')->assertOk();
        $grouped = fn (array $queries): int => count(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'group by "era_id"')));

        $this->assertSame(0, $grouped($this->queriesOf('/maps')), 'deuxième visite : comptes lus dans le cache');

        DB::table('line_segments')->update(['updated_at' => now()->addMinute()]);
        $this->assertSame(1, $grouped($this->queriesOf('/maps')), 'segments modifiés : comptes recalculés');
    }

    public function test_translations_are_sent_once_then_kept_by_the_browser(): void
    {
        $this->get('/materials')->assertInertia(fn (Assert $page) => $page->has('translations'));

        $first = $this->get('/materials')->viewData('page');
        $version = $first['version'];
        $key = 'translations.'.$first['props']['locale'];
        $this->assertSame('translations', $first['onceProps'][$key]['prop']);
        $headers = ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $version, 'X-Requested-With' => 'XMLHttpRequest'];

        // Navigation Inertia : le navigateur annonce les chaînes qu'il a déjà, le serveur ne les renvoie pas.
        $page = $this->get('/materials', $headers + ['X-Inertia-Except-Once-Props' => $key])->assertOk()->json();
        $this->assertArrayNotHasKey('translations', $page['props']);
        $this->assertSame('translations', $page['onceProps'][$key]['prop']);

        // Sans elles (premier passage, autre langue), elles sont envoyées.
        $page = $this->get('/materials', $headers)->assertOk()->json();
        $this->assertArrayHasKey('translations', $page['props']);
    }

    public function test_the_api_keeps_the_complete_line_sheet(): void
    {
        $full = app(LineSheet::class)->build($this->line->fresh());
        $page = app(LineSheet::class)->build($this->line->fresh(), forPage: true);

        foreach (LineSheet::PAGE_OMITTED as $key) {
            $this->assertArrayHasKey($key, $full);
            $this->assertArrayNotHasKey($key, $page);
        }
        $this->assertSame('LineString', $full['segments'][0]['features'][0]['geometry']['type']);
        $this->assertSame(json_encode(array_diff_key($full, array_flip(LineSheet::PAGE_OMITTED))), json_encode($page));
        $this->getJson('/api/v1/lines/'.$this->line->slug)->assertOk()->assertJsonPath('data.era_summary.0.year', 1960);
    }
}
