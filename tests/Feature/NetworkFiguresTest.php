<?php

namespace Tests\Feature;

use App\Models\Accident;
use App\Services\NetworkFigures;
use App\Services\SiteStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * « Le réseau en chiffres » : agrégats par décennie (NetworkFigures), commande de calcul, page /network et
 * `GET /api/v1/stats/network`. Réseau de test : une ligne ouverte aux voyageurs et électrifiée en 1960 et 2030.
 */
class NetworkFiguresTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->buildRailNetwork();
    }

    /** @return array<string, mixed> la ligne de l'ère */
    private function row(array $figures, int $year): array
    {
        foreach ($figures['decades'] as $row) {
            if ($row['year'] === $year) {
                return $row;
            }
        }
        $this->fail("Pas de ligne pour {$year}");
    }

    public function test_one_row_per_era_with_open_length_by_traffic_and_electrified_share(): void
    {
        $figures = app(NetworkFigures::class)->compute();

        $this->assertSame([1960, 2030], array_column($figures['decades'], 'year'));
        $row = $this->row($figures, 2030);
        $this->assertTrue($row['current']);
        $this->assertGreaterThan(10, $row['open_km']);
        $this->assertSame($row['open_km'], $row['open_passenger_km']);
        $this->assertSame(0, $row['open_freight_km']);
        $this->assertSame(0, $row['closed_km']);
        $this->assertSame(1.0, $row['electrified_share']);
        $this->assertNull($this->row($figures, 1960)['from_year']);
        $this->assertSame(1961, $row['from_year']);
    }

    public function test_the_total_matches_the_home_page_profile(): void
    {
        $figures = app(NetworkFigures::class)->compute();
        $profile = collect(app(SiteStats::class)->profile())->keyBy('year');

        foreach ($figures['decades'] as $row) {
            $this->assertEqualsWithDelta($profile[$row['year']]['km'], $row['open_km'], 1, "même longueur que l'accueil en {$row['year']}");
        }
    }

    public function test_closed_and_dismantled_segments_are_counted_apart_and_not_as_open(): void
    {
        $this->portionSegment($this->line, $this->currentEra->id, 48.0, 2.0, 2.3, 'freight', 'abandoned');
        $this->portionSegment($this->line, $this->currentEra->id, 48.1, 2.0, 2.3, 'mixed', 'dismantled');
        $this->portionSegment($this->line, $this->currentEra->id, 48.2, 2.0, 2.3, 'freight', 'in_service');

        $row = $this->row(app(NetworkFigures::class)->compute(), 2030);

        $this->assertGreaterThan(20, $row['closed_km']);
        $this->assertGreaterThan(20, $row['gone_km']);
        $this->assertGreaterThan(20, $row['open_freight_km']);
        $this->assertSame($row['open_km'], $row['open_passenger_km'] + $row['open_freight_km'] + $row['open_unknown_km']);
    }

    public function test_events_fall_in_the_period_that_leads_to_the_era_and_year_only_trace_dates_are_ignored(): void
    {
        $this->portionEvent($this->line, 'closure', '1955-03-01', 48.0);
        $this->portionEvent($this->line, 'closure_passenger', '1960-12-31', 48.0);
        $this->portionEvent($this->line, 'closure', '2022-05-01', 48.0);
        $this->portionEvent($this->line, 'reopening', '2024-06-01', 48.0);
        DB::table('line_events')->insert(['line_id' => $this->line->id, 'type' => 'closure', 'event_date' => '1918-01-01',
            'description' => 'Fermeture d\'après le nom du tracé', 'source' => 'archeoferroviaire', 'date_precision' => 'year',
            'confidence' => 'low', 'created_at' => now(), 'updated_at' => now()]);

        $figures = app(NetworkFigures::class)->compute();

        $past = $this->row($figures, 1960);
        $this->assertSame(2, $past['events']['closed'], 'la fermeture de 2022 est dans la période suivante, la date du nom de tracé est écartée');
        $this->assertSame(1, $past['events']['closed_passenger']);
        $this->assertSame(1, $past['events']['opened'], 'ouverture de 1839 de la ligne de test');
        $now = $this->row($figures, 2030);
        $this->assertSame(1, $now['events']['closed']);
        $this->assertSame(1, $now['events']['reopened']);
        $this->assertSame(2030, $figures['waves']['reopenings'][0]['year']);
    }

    public function test_accidents_count_published_ones_only_and_sum_known_tolls(): void
    {
        Accident::factory()->create(['slug' => '1955-01-01-a', 'kind' => 'collision', 'occurred_on' => '1955-01-01', 'deaths' => 10]);
        Accident::factory()->create(['slug' => '1956-01-01-b', 'kind' => 'derailment', 'occurred_on' => '1956-01-01', 'deaths' => null]);
        Accident::factory()->create(['slug' => '1957-01-01-masque', 'kind' => 'person_struck', 'occurred_on' => '1957-01-01', 'deaths' => 3]);

        $row = $this->row(app(NetworkFigures::class)->compute(), 1960);

        $this->assertSame(['count' => 2, 'deaths' => 10, 'deaths_known' => 1], $row['accidents']);
    }

    public function test_tracks_share_comes_from_the_sncf_regime_when_imported(): void
    {
        $this->assertNull(app(NetworkFigures::class)->compute()['tracks']);

        $now = now();
        $base = ['line_id' => $this->line->id, 'code_ligne' => '999000', 'source' => 'sncf', 'created_at' => $now, 'updated_at' => $now];
        DB::table('line_track_portions')->insert([
            $base + ['pk_start_m' => 0, 'pk_end_m' => 30_000, 'regime' => 'Double voie', 'tracks' => 2],
            $base + ['pk_start_m' => 30_000, 'pk_end_m' => 40_000, 'regime' => 'Voie unique', 'tracks' => 1],
            $base + ['pk_start_m' => 40_000, 'pk_end_m' => 45_000, 'regime' => 'Voie banalisée', 'tracks' => null],
        ]);

        $tracks = app(NetworkFigures::class)->compute()['tracks'];

        $this->assertSame(['double_km' => 30, 'single_km' => 10, 'unspecified_km' => 5, 'double_share' => 0.75], $tracks);
    }

    public function test_the_command_stores_the_figures_in_the_cache_and_dry_run_does_not(): void
    {
        $this->artisan('vff:compute-network-figures', ['--dry-run' => true])->assertSuccessful();
        $this->assertFalse(Cache::has(NetworkFigures::CACHE_KEY));

        $this->artisan('vff:compute-network-figures')->assertSuccessful();
        $this->assertTrue(Cache::has(NetworkFigures::CACHE_KEY));
        $this->assertNotEmpty(Cache::get(NetworkFigures::CACHE_KEY)['computed_at']);
    }

    public function test_the_page_reads_the_cache_without_querying_the_segments(): void
    {
        app(NetworkFigures::class)->refresh();

        DB::enableQueryLog();
        $this->get('/network')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Network/Figures')
            ->has('figures.decades', 2)
            ->has('figures.computed_at')
            ->where('figures.decades.1.year', 2030));
        $segmentQueries = array_filter(DB::getQueryLog(), fn (array $q): bool => str_contains($q['query'], 'line_segments'));
        $this->assertSame([], array_values($segmentQueries), 'aucun agrégat recalculé à l\'affichage');
    }

    public function test_the_api_serves_the_same_figures_with_a_cache_header(): void
    {
        $this->getJson('/api/v1/stats/network')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=3600, public')
            ->assertJsonStructure(['data' => ['computed_at', 'decades' => [['year', 'current', 'from_year', 'open_km', 'open_passenger_km',
                'open_freight_km', 'open_unknown_km', 'closed_km', 'gone_km', 'electrified_km', 'electrified_share', 'removed_km', 'added_km',
                'stations' => ['active', 'tourist', 'no_commercial', 'declassified'],
                'events' => ['opened', 'reopened', 'closed', 'closed_passenger', 'closed_freight', 'dismantled'],
                'accidents' => ['count', 'deaths', 'deaths_known']]], 'tracks', 'waves' => ['closures', 'reopenings']],
                'links' => ['web', 'stats']])
            ->assertJsonPath('data.decades.1.year', 2030);
    }
}
