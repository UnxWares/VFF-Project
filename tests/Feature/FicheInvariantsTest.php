<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ReadsInertiaProps;
use Tests\TestCase;

/**
 * Invariants de cohérence d'une fiche ligne, vérifiés sur TOUTES les fiches d'un réseau de test
 * (deux lignes, gares insérées dans le désordre) et non sur un cas écrit à la main :
 *   - une seule longueur : `length_km` est celle du plus grand développement des décennies, la
 *     part en service n'est jamais plus longue, plus de « second chiffre » ;
 *   - gares dans l'ordre des PK (sans PK à la fin), décomptes cohérents ;
 *   - dates ordonnées : ouverture avant fermeture, desserte de la ligne début avant fin, phases
 *     et décennies dans l'ordre du temps.
 */
class FicheInvariantsTest extends TestCase
{
    use ReadsInertiaProps;
    use RefreshDatabase;

    /** @var array<int, Era> */
    private array $eras = [];

    /** @var list<string> */
    private array $slugs = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([1900, 1930, 1960, 1990, 2030] as $year) {
            $this->eras[$year] = $year === 2030 ? Era::factory()->current()->create(['year' => $year]) : Era::factory()->create(['year' => $year]);
        }

        // Ligne 1 : ouverte en 1900, fermée en 1960, rouverte en 1990 ; gares insérées à l'envers.
        $this->line('ligne-a', 'LINESTRING(1.00 47.00, 1.30 47.00)', [
            1900 => 'in_service', 1930 => 'in_service', 1960 => 'abandoned', 1990 => 'in_service', 2030 => 'in_service',
        ], [
            ['gare-d', 1.30, 30_000, '1900-05-01', null, 1900, null],
            ['gare-b', 1.10, 10_000, '1900-05-01', '1960-01-01', 1900, 1959],
            ['gare-c', 1.20, 20_000, '1900-05-01', null, 1900, null],
            ['gare-a', 1.00, 0, '1900-05-01', null, 1900, null],
            ['gare-x', 1.25, null, '1920-01-01', null, 1920, null],
        ]);
        // Ligne 2 : jamais fermée, dont la dernière gare n'a pas de PK.
        $this->line('ligne-b', 'LINESTRING(2.00 46.00, 2.20 46.00)', [
            1930 => 'in_service', 1960 => 'in_service', 1990 => 'in_service', 2030 => 'in_service',
        ], [
            ['gare-f', 2.20, 20_000, '1930-06-15', null, 1930, null],
            ['gare-e', 2.00, 0, '1930-06-15', null, 1930, null],
            ['gare-g', 2.10, 10_000, '1930-06-15', '1990-03-01', 1930, 1989],
        ]);
    }

    /**
     * @param  array<int, string>  $statusByYear  année → statut du tracé
     * @param  list<array{0: string, 1: float, 2: ?int, 3: string, 4: ?string, 5: int, 6: ?int}>  $stations  slug, longitude, PK, ouverture, fermeture, desserte de, desserte jusqu'à
     */
    private function line(string $slug, string $wkt, array $statusByYear, array $stations): void
    {
        $this->slugs[] = $slug;
        $line = Line::factory()->create(['slug' => $slug, 'name' => 'Ligne '.$slug, 'code' => null, 'network' => 'principal', 'type' => null]);
        foreach ($statusByYear as $year => $status) {
            DB::table('line_segments')->insert(['line_id' => $line->id, 'era_id' => $this->eras[$year]->id,
                'geom' => DB::raw("ST_GeomFromText('{$wkt}', 4326)"), 'status' => $status, 'traffic_type' => 'mixed',
                'length_meters' => 30_000, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('line_events')->insert(['line_id' => $line->id, 'type' => 'opening', 'event_date' => array_key_first($statusByYear).'-01-01',
            'date_precision' => 'year', 'description' => 'Ouverture', 'source' => 'manual', 'confidence' => 'high',
            'created_at' => now(), 'updated_at' => now()]);

        $lat = $slug === 'ligne-a' ? 47.00 : 46.00;
        foreach ($stations as [$name, $lng, $pk, $opened, $closed, $from, $until]) {
            $station = Station::factory()->create(['slug' => $name, 'name' => ucfirst($name), 'geom' => Point::makeGeodetic($lat, $lng),
                'status' => $closed === null ? 'active' : 'declassified', 'opened_at' => $opened, 'opened_precision' => 'day',
                'closed_at' => $closed, 'closed_precision' => $closed === null ? null : 'day']);
            DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $line->id, 'method' => 'sncf_pk', 'pk_m' => $pk,
                'first_year' => $from, 'last_year' => $until, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($this->eras as $year => $era) {
                if ($year >= $from && ($until === null || $year <= $until) && ($closed === null || $year < (int) substr($closed, 0, 4))) {
                    DB::table('station_eras')->insert(['station_id' => $station->id, 'era_id' => $era->id, 'status' => 'active',
                        'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    /** Applique $check à la fiche de chaque ligne du réseau de test (props Inertia). */
    private function eachSheet(callable $check): void
    {
        foreach ($this->slugs as $slug) {
            $this->get('/lines/'.$slug)->assertOk()->assertInertia(function (Assert $page) use ($check, $slug) {
                $props = $page->toArray()['props'];
                // Garde-fou : des listes vides rendraient les invariants vrais sans rien vérifier.
                foreach (['stations', 'phases', 'decades'] as $key) {
                    $this->assertNotEmpty($props[$key], "{$slug} : {$key} vides");
                }
                $check($props, $slug);

                return $page;
            });
        }
    }

    public function test_each_sheet_has_a_single_length(): void
    {
        $this->eachSheet(function (array $props, string $slug) {
            $stats = $props['stats'];
            $this->assertNull($stats['length_greatest_km'], "{$slug} : plus de second chiffre « à son apogée »");
            $this->assertSame('trace', $stats['length_definition'], $slug);

            $greatest = max(array_map(fn ($d) => array_sum($d['length_by_category']), $props['decades']));
            $this->assertEqualsWithDelta($greatest / 1000, $stats['length_km'], 0.1, "{$slug} : la longueur est celle du plus grand développement");
            $this->assertLessThanOrEqual($stats['length_km'], $stats['active_length_km'], "{$slug} : la part en service n'est jamais plus longue que la ligne");
        });
    }

    public function test_stations_follow_the_chainage_with_the_unlocated_ones_last(): void
    {
        $this->eachSheet(function (array $props, string $slug) {
            $pks = array_column($props['stations'], 'pk_m');
            $located = array_values(array_filter($pks, fn ($pk) => $pk !== null));
            $sorted = $located;
            sort($sorted);

            $this->assertSame($sorted, $located, "{$slug} : gares dans l'ordre des PK");
            $this->assertSame($located, array_slice($pks, 0, count($located)), "{$slug} : les gares sans PK viennent après");
        });
    }

    public function test_station_counts_are_consistent(): void
    {
        $this->eachSheet(function (array $props, string $slug) {
            $this->assertSame(count($props['stations']), $props['stats']['station_count'], $slug);
            $this->assertLessThanOrEqual($props['stats']['station_count'], $props['stats']['station_open_count'], $slug);
            foreach ($props['decades'] as $decade) {
                $s = $decade['stations'];
                $this->assertLessThanOrEqual(count($props['stations']), $s['open'] + $s['closed'] + $s['uncertain'], "{$slug} {$decade['year']}");
            }
        });
    }

    public function test_station_dates_are_ordered(): void
    {
        $this->eachSheet(function (array $props, string $slug) {
            foreach ($props['stations'] as $s) {
                if ($s['opened_at'] !== null && $s['closed_at'] !== null) {
                    $this->assertLessThanOrEqual($s['closed_at'], $s['opened_at'], "{$slug} / {$s['slug']} : ouverture avant fermeture");
                }
                if ($s['served_from'] !== null && $s['served_until'] !== null) {
                    $this->assertLessThanOrEqual($s['served_until'], $s['served_from'], "{$slug} / {$s['slug']} : desserte début avant fin");
                }
            }
        });
    }

    public function test_phases_and_decades_follow_time(): void
    {
        $this->eachSheet(function (array $props, string $slug) {
            $dates = array_column($props['phases'], 'date');
            $sorted = $dates;
            sort($sorted);
            $this->assertSame($sorted, $dates, "{$slug} : phases dans l'ordre du temps");
            foreach ($props['phases'] as $phase) {
                $this->assertLessThanOrEqual($phase['to_year'], $phase['from_year'], "{$slug} : une phase ne finit pas avant de commencer");
            }

            $years = array_column($props['decades'], 'year');
            $sortedYears = $years;
            sort($sortedYears);
            $this->assertSame($sortedYears, $years, "{$slug} : décennies dans l'ordre");
            $this->assertCount(1, array_filter($props['decades'], fn ($d) => $d['is_current']), "{$slug} : une seule ère courante");
        });
    }

    public function test_the_closed_then_reopened_line_is_not_reported_dismantled_or_closed_today(): void
    {
        $this->get('/lines/ligne-a')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('line.current_state', 'open'));
    }
}
