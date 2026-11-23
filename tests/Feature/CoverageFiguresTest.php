<?php

namespace Tests\Feature;

use App\Models\Accident;
use App\Models\Line;
use App\Services\CoverageFigures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * « Ce que sait l'encyclopédie » : mesures de couverture (CoverageFigures), commande de calcul, page
 * /network/coverage et `GET /api/v1/stats/coverage`. Réseau de test : une ligne ouverte en 1839 (date au jour,
 * source Wikipédia), deux gares.
 */
class CoverageFiguresTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->buildRailNetwork();
    }

    /** @return array<string, mixed> */
    private function metric(array $section, string $key): array
    {
        foreach ($section['metrics'] as $metric) {
            if ($metric['key'] === $key) {
                return $metric;
            }
        }
        $this->fail("Pas de mesure {$key}");
    }

    public function test_an_opening_dated_to_the_day_from_an_open_source_is_counted_as_such(): void
    {
        $metric = $this->metric(app(CoverageFigures::class)->compute()['lines'], 'opening_dated');

        $this->assertSame(1, $metric['total']);
        $this->assertSame(1, $metric['done']);
        $this->assertSame(1.0, $metric['share']);
        $levels = array_column($metric['levels'], 'count', 'level');
        $this->assertSame(['day' => 1, 'month' => 0, 'year' => 0, 'none' => 0], $levels);
    }

    public function test_a_line_without_dated_opening_lowers_the_share(): void
    {
        $second = Line::factory()->create(['slug' => 'sans-date', 'code' => '999 001']);
        DB::table('line_events')->insert(['line_id' => $second->id, 'type' => 'opening', 'event_date' => '1880-01-01', 'date_precision' => 'year',
            'source' => 'wikipedia', 'confidence' => 'low', 'description' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        Line::factory()->create(['slug' => 'sans-evenement', 'code' => '999 002']);

        $metric = $this->metric(app(CoverageFigures::class)->compute()['lines'], 'opening_dated');

        $this->assertSame(3, $metric['total']);
        $this->assertSame(2, $metric['done']);
        $this->assertEqualsWithDelta(0.6667, $metric['share'], 0.0001);
        $this->assertSame(['day' => 1, 'month' => 0, 'year' => 1, 'none' => 1], array_column($metric['levels'], 'count', 'level'));
        $this->assertNull(array_column($metric['levels'], 'open', 'level')['none'], 'une date absente n\'a pas de source');
    }

    public function test_a_source_that_is_not_declared_open_is_never_counted_open(): void
    {
        DB::table('line_events')->where('line_id', $this->line->id)->update(['source' => 'archeoferroviaire']);

        $metric = $this->metric(app(CoverageFigures::class)->compute()['lines'], 'opening_dated');

        $this->assertSame(1, $metric['done']);
        $this->assertSame(0, $metric['open']);
    }

    public function test_every_measure_has_a_total_a_done_count_and_a_share_between_zero_and_one(): void
    {
        $figures = app(CoverageFigures::class)->compute();

        foreach (['lines', 'stations', 'accidents', 'materials'] as $section) {
            $this->assertNotEmpty($figures[$section]['metrics']);
            foreach ($figures[$section]['metrics'] as $m) {
                $this->assertLessThanOrEqual($m['total'], $m['done'], "{$section}.{$m['key']} : plus de renseignés que d'éléments");
                $this->assertTrue($m['share'] === null || ($m['share'] >= 0 && $m['share'] <= 1), "{$section}.{$m['key']} : part hors de 0 à 1");
                $this->assertLessThanOrEqual($m['done'], $m['open'] ?? 0, "{$section}.{$m['key']} : plus d'ouvert que de renseigné");
            }
        }
    }

    public function test_optional_tables_missing_give_an_unavailable_measure_not_an_error(): void
    {
        Schema::drop('station_attendances');
        Schema::drop('station_accessibility');

        $stations = app(CoverageFigures::class)->compute()['stations'];

        $attendance = $this->metric($stations, 'attendance');
        $this->assertFalse($attendance['available']);
        $this->assertSame(0, $attendance['total']);
        $this->assertFalse($this->metric($stations, 'accessibility')['available']);
        $this->assertTrue($this->metric($stations, 'photo')['available']);
    }

    public function test_accidents_count_published_ones_and_reviewed_ones_by_decade(): void
    {
        Accident::factory()->create(['slug' => '1955-01-01-a', 'kind' => 'collision', 'occurred_on' => '1955-01-01', 'review_status' => 'ok']);
        Accident::factory()->create(['slug' => '1956-01-01-b', 'kind' => 'derailment', 'occurred_on' => '1956-01-01', 'review_status' => 'to_review']);
        Accident::factory()->create(['slug' => '1957-01-01-masque', 'kind' => 'person_struck', 'occurred_on' => '1957-01-01', 'review_status' => 'ok']);

        $accidents = app(CoverageFigures::class)->compute()['accidents'];

        $reviewed = $this->metric($accidents, 'reviewed');
        $this->assertSame(2, $reviewed['total'], 'un heurt de personne sans enquête n\'est pas publié');
        $this->assertSame(1, $reviewed['done']);
        $this->assertSame([['decade' => 1950, 'total' => 2, 'reviewed' => 1]], $accidents['by_decade']);
    }

    public function test_the_eras_list_follows_the_map_eras_with_precise_opening_counts(): void
    {
        $eras = app(CoverageFigures::class)->compute()['eras'];

        $this->assertSame([1960, 2030], array_column($eras, 'year'));
        $this->assertSame(1, $eras[1]['lines']);
        $this->assertSame(1, $eras[1]['lines_precise_opening']);
        $this->assertTrue($eras[1]['current']);
    }

    public function test_the_command_stores_the_figures_in_the_cache_and_dry_run_does_not(): void
    {
        $this->artisan('vff:compute-coverage-figures', ['--dry-run' => true])->assertSuccessful();
        $this->assertFalse(Cache::has(CoverageFigures::CACHE_KEY));

        $this->artisan('vff:compute-coverage-figures')->assertSuccessful();
        $this->assertTrue(Cache::has(CoverageFigures::CACHE_KEY));
        $this->assertNotEmpty(Cache::get(CoverageFigures::CACHE_KEY)['computed_at']);
    }

    public function test_the_page_reads_the_cache_without_querying_the_data(): void
    {
        app(CoverageFigures::class)->refresh();

        DB::enableQueryLog();
        $this->get('/network/coverage')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Network/Coverage')
            ->has('coverage.lines.metrics')
            ->has('coverage.eras', 2)
            ->has('coverage.computed_at'));
        $heavy = array_filter(DB::getQueryLog(), fn (array $q): bool => str_contains($q['query'], 'line_segments') || str_contains($q['query'], 'segment_audits'));
        $this->assertSame([], array_values($heavy), 'aucun agrégat recalculé à l\'affichage');
    }

    public function test_the_api_serves_the_same_figures_with_a_cache_header(): void
    {
        $this->getJson('/api/v1/stats/coverage')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=3600, public')
            ->assertJsonStructure(['data' => [
                'computed_at',
                'lines' => ['metrics' => [['key', 'unit', 'available', 'total', 'done', 'share', 'open']]],
                'stations' => ['metrics'],
                'accidents' => ['metrics', 'by_decade'],
                'materials' => ['metrics', 'by_decade'],
                'eras' => [['year', 'current', 'lines', 'lines_precise_opening', 'trace_km', 'trace_precise_km', 'stations', 'stations_precise_opening']],
            ], 'links' => ['web', 'stats']]);
    }
}
