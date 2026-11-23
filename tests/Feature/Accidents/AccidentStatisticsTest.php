<?php

namespace Tests\Feature\Accidents;

use App\Enums\AccidentKind;
use App\Models\Accident;
use App\Services\Accidents\AccidentStatistics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * « Les accidents en chiffres » : agrégats (AccidentStatistics), page /accidents/statistics et
 * `GET /api/v1/accidents/statistics`. Les sommes ne portent que sur les bilans connus ; les fiches à vérifier
 * sont comptées à part ; la nature ne filtre ni la ventilation par nature ni la part des passages à niveau.
 */
class AccidentStatisticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Accident::factory()->on('1842-05-08')->create(['slug' => '1842-05-08-meudon', 'kind' => 'derailment', 'deaths' => 55, 'injuries' => 150]);
        Accident::factory()->on('1847-01-02')->toReview()->create(['kind' => 'collision', 'deaths' => null, 'injuries' => null]);
        Accident::factory()->on('1988-06-27')->create(['kind' => 'collision', 'deaths' => 56, 'injuries' => 57]);
        Accident::factory()->on('1989-01-01')->toReview()->create(['kind' => 'level_crossing', 'deaths' => 3, 'injuries' => null]);
        Accident::factory()->on('1985-03-03')->create(['kind' => 'level_crossing', 'deaths' => 0, 'injuries' => 2]);
        // Un alias n'est jamais compté.
        $kept = Accident::factory()->on('2013-07-12')->create(['slug' => '2013-07-12-bretigny', 'kind' => 'derailment', 'deaths' => 7, 'injuries' => 32]);
        Accident::factory()->on('2013-07-12')->create(['kind' => 'derailment', 'deaths' => 99, 'alias_of_id' => $kept->id]);
        // Un heurt de personne sans enquête officielle ni contexte relu n'est pas publié.
        Accident::factory()->on('1990-05-05')->create(['kind' => AccidentKind::PersonStruck->value, 'deaths' => 1]);
    }

    /** @return array<string, mixed> la ligne de la décennie */
    private function decade(array $stats, int $decade): array
    {
        foreach ($stats['decades'] as $row) {
            if ($row['decade'] === $decade) {
                return $row;
            }
        }
        $this->fail("Pas de ligne pour {$decade}");
    }

    public function test_totals_count_published_accidents_and_only_known_tolls(): void
    {
        $t = app(AccidentStatistics::class)->get()['totals'];

        $this->assertSame(6, $t['accidents']);
        $this->assertSame(2, $t['to_review']);
        $this->assertSame(55 + 56 + 3 + 0 + 7, $t['deaths']);
        $this->assertSame(5, $t['deaths_known']);
        $this->assertSame(150 + 57 + 2 + 32, $t['injuries']);
        $this->assertSame(4, $t['injuries_known']);
        $this->assertSame('1842-05-08', $t['first_date']);
        $this->assertSame('2013-07-12', $t['last_date']);
    }

    public function test_decades_are_continuous_and_split_review_status(): void
    {
        $stats = app(AccidentStatistics::class)->get();
        $decades = array_column($stats['decades'], 'decade');

        $this->assertSame(1840, $decades[0]);
        $this->assertSame(2010, end($decades));
        $this->assertSame(range(1840, 2010, 10), $decades);
        $this->assertSame(0, $this->decade($stats, 1900)['accidents']);
        $this->assertNull($this->decade($stats, 1900)['level_crossing_share']);
        $row = $this->decade($stats, 1840);
        $this->assertSame(2, $row['accidents']);
        $this->assertSame(1, $row['to_review']);
        $this->assertSame(1, $row['deaths_known']);
        $this->assertSame(55, $row['deaths']);
    }

    public function test_level_crossing_share_ignores_the_kind_filter(): void
    {
        $all = $this->decade(app(AccidentStatistics::class)->get(), 1980);
        $this->assertSame(3, $all['all_kinds']);
        $this->assertSame(2, $all['level_crossing']);
        $this->assertSame(0.667, $all['level_crossing_share']);

        $filtered = app(AccidentStatistics::class)->get(['kind' => 'collision']);
        $row = $this->decade($filtered, 1980);
        $this->assertSame(1, $row['accidents']);
        $this->assertSame(3, $row['all_kinds']);
        $this->assertSame(0.667, $row['level_crossing_share']);
        $this->assertSame(array_sum(array_column($filtered['kinds'], 'accidents')), 6);
    }

    public function test_review_filter_excludes_unconfirmed_records_and_period_filter_bounds_years(): void
    {
        $ok = app(AccidentStatistics::class)->get(['review' => 'ok']);
        $this->assertSame(4, $ok['totals']['accidents']);
        $this->assertSame(0, $ok['totals']['to_review']);

        $period = app(AccidentStatistics::class)->get(['from' => 1985, 'to' => 1988]);
        $this->assertSame(2, $period['totals']['accidents']);
        $this->assertSame('1985-03-03', $period['totals']['first_date']);
    }

    public function test_deadliest_lists_the_heaviest_known_tolls_with_their_slug(): void
    {
        $deadliest = app(AccidentStatistics::class)->get()['deadliest'];

        $this->assertSame([56, 55, 7, 3], array_column($deadliest, 'deaths'));
        $this->assertSame('1842-05-08-meudon', $deadliest[1]['slug']);
        $this->assertNotContains(0, array_column($deadliest, 'deaths'));
    }

    public function test_the_page_reads_filters_from_the_address_and_swaps_a_reversed_period_and_ignores_invalid_ones(): void
    {
        $this->get('/accidents/statistics?from=1900&to=1800&kind=nope&review=ok')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Accidents/Statistics')
                ->where('filters.kind', null)
                ->where('filters.review', 'ok')
                ->where('filters.from', 1800)
                ->where('filters.to', 1900)
                ->where('unfiltered_total', 6)
                ->has('statistics.decades'));
    }

    public function test_the_page_is_not_swallowed_by_the_accident_sheet_route(): void
    {
        $this->get('/accidents/statistics')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Accidents/Statistics'));
        $this->get('/accidents/1842-05-08-meudon')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Accidents/Show'));
    }

    public function test_the_rest_endpoint_serves_the_same_figures_and_validates_filters(): void
    {
        $this->getJson('/api/v1/accidents/statistics?kind=derailment&from=1800')
            ->assertOk()
            ->assertJsonPath('data.filters.kind', 'derailment')
            ->assertJsonPath('data.totals.accidents', 2)
            ->assertJsonStructure(['data' => ['computed_at', 'totals', 'decades', 'kinds', 'deadliest'], 'links' => ['web', 'accidents']]);
        $this->getJson('/api/v1/accidents/statistics?to=1900&from=1950')->assertStatus(422);
        $this->getJson('/api/v1/accidents/statistics?kind=nope')->assertStatus(422);
        $this->getJson('/api/v1/accidents/statistics?review=to_review')->assertStatus(422);
    }
}
