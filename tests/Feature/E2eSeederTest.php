<?php

namespace Tests\Feature;

use Database\Seeders\E2eSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Jeu de données de la suite de fumée Playwright (tests/e2e, job `e2e` de la CI) : les fiches visées existent,
 * le seeder est rejouable sans doublon et les pages répondent.
 */
class E2eSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_the_pages_targeted_by_the_smoke_suite(): void
    {
        $this->seed(E2eSeeder::class);

        foreach (['/', '/lines', '/lines/'.E2eSeeder::LINE, '/stations', '/stations/'.E2eSeeder::STATION, '/accidents', '/accidents/'.E2eSeeder::ACCIDENT, '/maps'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_seeder_can_be_replayed_without_duplicates(): void
    {
        $this->seed(E2eSeeder::class);
        $counts = fn () => collect(['lines', 'stations', 'accidents', 'accident_lines', 'line_segments', 'line_stops', 'station_eras', 'eras'])
            ->mapWithKeys(fn (string $t) => [$t => DB::table($t)->count()])->all();
        $first = $counts();

        $this->seed(E2eSeeder::class);

        $this->assertSame($first, $counts());
        $this->assertSame(1, DB::table('lines')->where('slug', E2eSeeder::LINE)->count());
    }
}
