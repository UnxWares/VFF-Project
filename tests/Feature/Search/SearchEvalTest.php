<?php

namespace Tests\Feature\Search;

use App\Models\Accident;
use App\Models\Line;
use App\Models\Material;
use App\Models\Station;
use App\Services\CatalogSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Jeu d'essai de la recherche (tests/Fixtures/Search/queries.php, 78 requêtes réelles) rejoué sur un petit corpus
 * tiré de la base (tests/Fixtures/Search/corpus.json : fiches attendues et celles qui leur faisaient concurrence
 * dans les cinq premiers résultats). Chaque fiche attendue sort dans les cinq premiers, et en premier sauf pour
 * les requêtes de MISSES_FIRST (mesures : storage/app/reports/agents/aprem-2111/recherche.md).
 */
class SearchEvalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Requêtes dont la fiche attendue n'est pas première, mais dans les cinq premiers : « chalons » est plus
     * proche de « Chalô » que de « Chaléons ».
     */
    private const MISSES_FIRST = ['st hilaire chalons'];

    private const BUCKETS = ['station' => 'stations', 'line' => 'lines', 'material' => 'materials', 'accident' => 'accidents'];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        /** @var array{stations: list<array<string, mixed>>, lines: list<array<string, mixed>>, materials: list<array<string, mixed>>, accidents: list<array<string, mixed>>} $corpus */
        $corpus = json_decode((string) file_get_contents(base_path('tests/Fixtures/Search/corpus.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($corpus['stations'] as $s) {
            Station::factory()->create([
                ...$s,
                'alt_names' => $s['alt_names'] === null ? null : json_decode((string) $s['alt_names'], true),
                'provenance' => null,
            ]);
        }
        foreach ($corpus['lines'] as $l) {
            Line::factory()->create($l);
        }
        foreach ($corpus['materials'] as $m) {
            Material::factory()->create([...$m, 'designations' => $m['designations'] === null ? null : json_decode((string) $m['designations'], true)]);
        }
        foreach ($corpus['accidents'] as $a) {
            Accident::factory()->create([
                'slug' => $a['slug'],
                'name' => ['fr' => $a['fr'], 'en' => null, 'de' => null],
                'commune_name' => $a['commune_name'],
                'occurred_on' => $a['occurred_on'],
                'kind' => $a['kind'],
                'context_checked_on' => '2026-11-22',
            ]);
        }
    }

    public function test_every_query_finds_its_record_and_almost_all_in_first_place(): void
    {
        /** @var list<array{q: string, type: string, slug: string}> $cases */
        $cases = require base_path('tests/Fixtures/Search/queries.php');
        $this->assertGreaterThanOrEqual(60, count($cases));
        $search = app(CatalogSearch::class);
        $misses = [];
        foreach ($cases as $case) {
            $slugs = array_column($search->search($case['q'], 5, 5, 5, 5)[self::BUCKETS[$case['type']]], 'slug');
            $rank = array_search($case['slug'], $slugs, true);
            $this->assertNotFalse($rank, "« {$case['q']} » ne trouve pas {$case['slug']} : ".implode(', ', $slugs));
            if ($rank !== 0) {
                $misses[] = $case['q'];
            }
        }
        $this->assertSame(self::MISSES_FIRST, $misses);
    }
}
