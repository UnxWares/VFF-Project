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

    /**
     * Chargement des props différées d'une fiche, tel que la page le demande après son premier affichage
     * (requête partielle Inertia du groupe « sheet ») : les requêtes lancées et les props reçues.
     *
     * @return array{0: list<string>, 1: array<string, mixed>}
     */
    private function deferredLoad(string $url, string $component): array
    {
        $first = $this->get($url)->assertOk()->viewData('page');
        $keys = $first['deferredProps']['sheet'] ?? [];
        $this->assertNotEmpty($keys, 'props différées annoncées dans le groupe « sheet »');
        foreach ($keys as $key) {
            $this->assertArrayNotHasKey($key, $first['props'], "{$key} : absente du premier affichage");
        }
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $props = $this->get($url, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $first['version'],
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia-Partial-Component' => $component,
            'X-Inertia-Partial-Data' => implode(',', $keys),
        ])->assertOk()->json('props');
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $props, "{$key} : reçue par le chargement différé");
        }

        return [$queries, $props];
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
        // 67 : l'histoire de l'électrification le 23/11 (LineElectrificationHistory : constat de la table des tranches SNCF,
        // tranches de la ligne par son code, événements d'électrification datés ; trois lectures indexées, aucune par élément).
        // 68 : les ouvrages d'art le 23/11 (LineStructures::forSheet : une lecture indexée des ouvrages de la ligne, et l'ère
        // courante seulement quand la ligne a des ouvrages).
        // 40 depuis le chantier « budget des fiches » du 23/11 (storage/app/reports/agents/aprem-2111/budget-fiches.md) : le
        // premier affichage ne porte que le haut de la fiche (LineSheet::PAGE_KEYS : en-tête, tracé, « Où et quand », « À propos »,
        // histoire, décennies, gares, chronologie) ; tout ce qui est plus bas arrive par les props différées (LineSheet::PAGE_DEFERRED
        // et les sections de LineController::show : un groupe, une requête, voir plus bas), et LineStats lit en deux requêtes ce
        // qu'elle lisait en cinq. Sur ces 40, 16 ne sont faites qu'une fois tant que les tronçons ne changent pas (phases et
        // portions, longueurs, terminus, départements, constat de la table des voies, gardés en cache) : la visite suivante en fait 24, sous l'objectif de 30.
        $this->assertLessThanOrEqual(40, count($queries), count($queries)." requêtes\n".implode("\n", $queries));
        $warm = $this->queriesOf('/lines/'.$this->line->slug);
        $this->assertLessThan(count($queries), count($warm), 'seconde visite : calculs lus dans le cache');
        $this->assertLessThanOrEqual(24, count($warm), count($warm)." requêtes à la seconde visite\n".implode("\n", $warm));
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
        // de la table, un constat positif est ensuite en cache, puis une lecture indexée par gare). 13 : le patrimoine de la gare (même schéma : existence de la table, constat
        // positif en cache, puis une lecture indexée par gare).
        // 8 depuis le chantier « budget des fiches » du 23/11 : gares voisines, nombre d'images et fréquentation arrivent par
        // les props différées (StationController::show : un groupe, une requête) ; la position de la gare est lue dans sa
        // géométrie, déjà chargée (une requête de moins, pour l'API aussi). Le patrimoine reste au premier affichage : sa
        // section suit « À propos ». Seconde visite : 7 (constat de la table du patrimoine en cache).
        $this->assertLessThanOrEqual(8, count($queries), count($queries)." requêtes\n".implode("\n", $queries));
    }

    public function test_line_sheet_loads_its_lower_sections_in_one_deferred_request(): void
    {
        [$queries, $props] = $this->deferredLoad('/lines/'.$this->line->slug, 'Lines/Show');

        // Le haut de la fiche n'est pas reconstruit : ni gares de la ligne, ni calcul des phases.
        $this->assertSame([], array_values(array_filter($queries, fn (string $sql): bool => str_contains($sql, "provenance->>'closed_after' AS closed_after")
            || str_contains($sql, 'tmp_line_portions'))));
        foreach (LineSheet::PAGE_KEYS as $key) {
            $this->assertArrayNotHasKey($key, $props, "{$key} : pas renvoyée par le chargement différé");
        }
        $this->assertLessThanOrEqual(26, count($queries), count($queries)." requêtes\n".implode("\n", $queries));
    }

    public function test_station_sheet_loads_its_lower_sections_in_one_deferred_request(): void
    {
        [$queries, $props] = $this->deferredLoad('/stations/'.$this->origin->slug, 'Stations/Show');

        $this->assertArrayHasKey('neighbors', $props);
        $this->assertArrayNotHasKey('chronology', $props);
        $this->assertLessThanOrEqual(6, count($queries), count($queries)." requêtes\n".implode("\n", $queries));
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
        $later = app(LineSheet::class)->pageDeferred($this->line->fresh());

        foreach (LineSheet::PAGE_OMITTED as $key) {
            $this->assertArrayHasKey($key, $full);
            $this->assertArrayNotHasKey($key, $page);
        }
        // Premier affichage : les clés annoncées, dans l'ordre ; le reste arrive par les props différées.
        $this->assertSame(LineSheet::PAGE_KEYS, array_keys($page));
        $this->assertSame(LineSheet::PAGE_DEFERRED, array_keys($later));
        $this->assertSame('LineString', $full['segments'][0]['features'][0]['geometry']['type']);
        $this->assertSame('LineString', $full['portions'][0]['geometry']['type']);
        // La page reçoit, à la place des portions, leur nombre et leur version (LineSheet::PAGE_ADDED).
        // Et sans les champs de gare que la page ne lit pas (LineSheet::PAGE_STATION_OMITTED).
        $expected = array_diff_key($full, array_flip(LineSheet::PAGE_OMITTED));
        $expected['stations'] = $expected['stations']->map(
            fn (array $s): array => array_diff_key($s, array_flip(LineSheet::PAGE_STATION_OMITTED)),
        )->values();
        // Premier affichage et props différées réunis : la fiche de l'API, valeur pour valeur.
        $shown = [...array_diff_key($page, array_flip(LineSheet::PAGE_ADDED)), ...$later];
        $this->assertEquals(json_decode((string) json_encode($expected), true), json_decode((string) json_encode($shown), true));
        $this->assertSame(count($full['portions']), $page['portions_count']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $page['portions_version']);
        // La fiche complète garde l'ordre de ses clés (contrat de l'API).
        $this->assertSame(['line', 'sources', 'stats', 'segments', 'era_summary', 'portions', 'phases', 'best_phase',
            'phases_unexplained', 'decades', 'chronicle', 'stations', 'signalling', 'sections', 'events', 'heritage', 'materials'], array_keys($full));
        $this->getJson('/api/v1/lines/'.$this->line->slug)->assertOk()->assertJsonPath('data.era_summary.0.year', 1960);
    }
}
