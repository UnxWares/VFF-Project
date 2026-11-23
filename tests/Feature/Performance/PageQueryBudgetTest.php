<?php

namespace Tests\Feature\Performance;

use App\Services\LineSheet;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

        // 53 : une requête de plus le 20/11 pour les événements qui ont une histoire (EventStories::available).
        // 56 : trois petites lectures de signature le 21/11 (LineDataSignature : phases, extrémités, longueurs),
        // qui évitent les calculs géographiques à la visite suivante (voir plus bas).
        // 57 : une lecture de plus après les chantiers de la nuit du 21/11 (matériel, profil en long, contexte des
        // accidents) ; aucune requête par élément, la seconde visite reste plus courte.
        // 58 : les notices des Archives SNCF le 22/11 (LineArchives::recordsForSheet, une seule lecture).
        // 60 : le nombre de voies du régime SNCF le 22/11 (LineInfrastructure::trackFact : une lecture, et le constat
        // que la table existe, gardé en cache ensuite).
        // 64 : les vitesses maximales le 23/11 (LineSpeeds : ère courante, constat des deux tables en une lecture gardée
        // en cache ensuite, tranches de PK, changements datés). Le diagramme « Où et quand » n'ajoute aucune lecture.
        $this->assertLessThanOrEqual(64, count($queries), count($queries)." requêtes\n".implode("\n", $queries));
        $warm = $this->queriesOf('/lines/'.$this->line->slug);
        $this->assertLessThan(count($queries), count($warm), 'seconde visite : calculs lus dans le cache');
        $this->assertEmpty(array_filter($warm, fn (string $sql): bool => str_contains($sql, 'CREATE TEMP TABLE')));
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

        // 8 depuis le 20/11 : un comptage indexé des images publiées (la galerie n'est posée que s'il y en a).
        // 9 avec la chronologie de la fiche (accidents proches). 11 depuis le 23/11 : la fréquentation de la gare (existence
        // de la table, un constat positif est ensuite en cache, puis une lecture indexée par gare).
        $this->assertLessThanOrEqual(11, count($queries), count($queries)." requêtes\n".implode("\n", $queries));
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

    public function test_translations_are_not_copied_into_each_page(): void
    {
        $first = $this->get('/materials')->viewData('page');
        $this->assertArrayNotHasKey('translations', $first['props']);
        $this->assertArrayNotHasKey('translations.'.$first['props']['locale'], $first['onceProps'] ?? []);
        $this->assertMatchesRegularExpression('#^/i18n/[a-z]{2}/[0-9a-f]{12}\.json$#', $first['props']['translations_url']);

        // La page annonce le dictionnaire dans son en-tête : le navigateur le demande avec les scripts.
        $this->get('/materials')->assertSee('<link rel="preload" href="'.$first['props']['translations_url'].'" as="fetch" crossorigin="anonymous" />', false);

        // Navigation Inertia : seulement l'adresse, quelques dizaines d'octets.
        $headers = ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $first['version'], 'X-Requested-With' => 'XMLHttpRequest'];
        $page = $this->get('/materials', $headers)->assertOk()->json();
        $this->assertArrayNotHasKey('translations', $page['props']);
        $this->assertSame($first['props']['translations_url'], $page['props']['translations_url']);
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
        $this->assertSame('LineString', $full['portions'][0]['geometry']['type']);
        // La page reçoit, à la place des portions, leur nombre et leur version (LineSheet::PAGE_ADDED).
        // Et sans les champs de gare que la page ne lit pas (LineSheet::PAGE_STATION_OMITTED).
        $expected = array_diff_key($full, array_flip(LineSheet::PAGE_OMITTED));
        $expected['stations'] = $expected['stations']->map(
            fn (array $s): array => array_diff_key($s, array_flip(LineSheet::PAGE_STATION_OMITTED)),
        )->values();
        $this->assertSame(json_encode($expected), json_encode(array_diff_key($page, array_flip(LineSheet::PAGE_ADDED))));
        $this->assertSame(count($full['portions']), $page['portions_count']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $page['portions_version']);
        $this->getJson('/api/v1/lines/'.$this->line->slug)->assertOk()->assertJsonPath('data.era_summary.0.year', 1960);
    }
}
