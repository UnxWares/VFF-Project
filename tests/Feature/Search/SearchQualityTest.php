<?php

namespace Tests\Feature\Search;

use App\Models\Accident;
use App\Models\Line;
use App\Models\Material;
use App\Models\Station;
use App\Services\CatalogSearch;
use App\Services\GlossarySearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Banc « qualité » de la recherche (tests/Fixtures/Search/queries-qualite.php, 84 requêtes réelles par famille :
 * gares, anciens noms, codes, homonymes, matériel, accidents, glossaire, fautes de frappe, anglais et allemand)
 * rejoué sur un petit corpus tiré de la base (tests/Fixtures/Search/corpus-qualite.json : fiches attendues et
 * celles qui sortaient dans les huit premiers résultats, avant et après les correctifs du 23/11, avec la
 * fréquentation et le nombre de lignes des gares). Seuil de rang : chaque fiche attendue sort en premier. Mesures avant et après :
 * storage/app/reports/agents/aprem-2111/recherche-qualite.md.
 */
class SearchQualityTest extends TestCase
{
    use RefreshDatabase;

    private const BUCKETS = ['station' => 'stations', 'line' => 'lines', 'material' => 'materials', 'accident' => 'accidents', 'glossary' => 'glossary'];

    /** Rang le plus bas toléré pour la fiche attendue (1 = premier). */
    private const MAX_RANK = 1;

    /**
     * Requêtes SQL d'une recherche de l'en-tête (quatre catégories, sans les communes ni les PK) au plus. Mesures
     * du 23/11 sur ce corpus : 6 pour un code, 11 à 15 avec les noms approchés des catégories qui ont peu de
     * fiches, 16 pour un ancien slug (une lecture des gares fusionnées, une des lignes renommées).
     */
    private const QUERY_BUDGET = 16;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        /** @var array{stations: list<array<string, mixed>>, lines: list<array<string, mixed>>, materials: list<array<string, mixed>>, accidents: list<array<string, mixed>>} $corpus */
        $corpus = json_decode((string) file_get_contents(base_path('tests/Fixtures/Search/corpus-qualite.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($corpus['lines'] as $l) {
            Line::factory()->create([...$l, 'alt_names' => self::decoded($l['alt_names'])]);
        }
        $lineIds = Line::orderBy('id')->pluck('id')->all();
        foreach ($corpus['stations'] as $s) {
            ['passengers' => $passengers, 'line_count' => $lineCount] = $s;
            unset($s['passengers'], $s['line_count']);
            $station = Station::factory()->create([...$s, 'alt_names' => self::decoded($s['alt_names']), 'provenance' => null]);
            // Nombre de lignes de la gare (départage à pertinence égale), rattachées à des lignes quelconques du corpus.
            foreach (array_slice($lineIds, 0, $lineCount) as $lineId) {
                DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $lineId, 'method' => 'test', 'created_at' => now(), 'updated_at' => now()]);
            }
            if ($passengers !== null) {
                DB::table('station_attendances')->insert(['station_id' => $station->id, 'year' => 2024, 'passengers' => $passengers, 'footfall' => $passengers,
                    'series' => '2015-2024', 'uic_code' => (string) $station->uic_code, 'source_name' => $station->name, 'match_method' => 'uic', 'source' => 'sncf',
                    'dataset' => 'frequentation-gares', 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        foreach ($corpus['materials'] as $m) {
            Material::factory()->create([...$m, 'designations' => self::decoded($m['designations'])]);
        }
        foreach ($corpus['accidents'] as $a) {
            Accident::factory()->create([
                'slug' => $a['slug'],
                'name' => ['fr' => $a['fr'], 'en' => $a['en'], 'de' => $a['de']],
                'commune_name' => $a['commune_name'],
                'occurred_on' => $a['occurred_on'],
                'kind' => $a['kind'],
                'context_checked_on' => '2026-11-22',
            ]);
        }
    }

    public function test_every_query_of_the_quality_bench_finds_its_record_first(): void
    {
        /** @var list<array{q: string, type: string, slug: string, family: string}> $cases */
        $cases = require base_path('tests/Fixtures/Search/queries-qualite.php');
        $this->assertGreaterThanOrEqual(80, count($cases));
        $search = app(CatalogSearch::class);
        $glossary = app(GlossarySearch::class);
        $misses = [];
        foreach ($cases as $case) {
            $result = [...$search->search($case['q'], 5, 5, 5, 5), 'glossary' => $glossary->search($case['q'])];
            $slugs = array_column($result[self::BUCKETS[$case['type']]], 'slug');
            $rank = array_search($case['slug'], $slugs, true);
            if ($rank === false || $rank + 1 > self::MAX_RANK) {
                $misses[] = sprintf('[%s] « %s » : %s attendu, rang %s (%s)', $case['family'], $case['q'], $case['slug'],
                    $rank === false ? '-' : (string) ($rank + 1), implode(', ', $slugs) ?: 'rien');
            }
        }
        $this->assertSame([], $misses);
    }

    public function test_a_search_stays_within_its_query_budget(): void
    {
        $search = app(CatalogSearch::class);
        $counts = [];
        foreach (['strasbourg', 'Paris Nord station', 'Lagny 1933', 'ligne 830 000', 'strasbourd', 'uic 87686006', 'saint-etienne', 'archeo-bifurcation-de-mirambeau'] as $q) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $search->search($q);
            $counts[$q] = count(DB::getQueryLog());
            DB::disableQueryLog();
        }
        $this->assertLessThanOrEqual(self::QUERY_BUDGET, max($counts), json_encode($counts, JSON_UNESCAPED_UNICODE) ?: '');
    }

    public function test_the_old_slug_of_a_merged_station_finds_the_station_that_absorbed_it_first(): void
    {
        $kept = Station::where('slug', 'paris-nord')->firstOrFail();
        DB::table('station_redirects')->insert(['old_slug' => 'osm-1234567', 'station_id' => $kept->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame('paris-nord', app(CatalogSearch::class)->search('osm-1234567')['stations'][0]['slug'] ?? null);
    }

    public function test_the_glossary_answers_through_the_search_api(): void
    {
        $this->getJson('/api/v1/search?q=tvm%20430')
            ->assertOk()
            ->assertJsonPath('glossary.0.slug', 'tvm-430')
            ->assertJsonPath('glossary.0.names.de', 'TVM 430');
    }

    /** @return array<mixed>|null */
    private static function decoded(?string $text): ?array
    {
        return $text === null ? null : json_decode($text, true, flags: JSON_THROW_ON_ERROR);
    }
}
