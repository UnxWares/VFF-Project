<?php

namespace Tests\Feature\Profiles;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * vff:profile-trace-holes : lignes dont le tracé de référence a de vrais trous sous le profil (trous
 * conventionnels des embranchements exclus), bilan et liste CSV, lecture seule.
 */
class ProfileTraceHolesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<array{from_m: int, to_m: int}>  $gaps
     * @param  list<array{from_m: int, to_m: int}>  $branches
     */
    private function profile(string $slug, array $gaps, array $branches, int $covered): void
    {
        $id = DB::table('lines')->insertGetId(['slug' => $slug, 'name' => 'Ligne '.$slug]);
        DB::table('line_profiles')->insert([
            'line_id' => $id, 'resource' => 'test', 'step_m' => 100, 'length_m' => 50_000, 'trace_length_m' => 50_000, 'covered_m' => $covered,
            'structures' => '[]', 'altitude_sources' => '{}', 'gaps' => json_encode($gaps), 'branches' => json_encode($branches), 'stations' => '[]',
            'min_altitude_m' => 100, 'max_altitude_m' => 200, 'ascent_m' => 100, 'descent_m' => 0, 'computed_at' => now(),
        ]);
    }

    public function test_only_real_holes_are_listed(): void
    {
        $this->profile('a-trous', [['from_m' => 1_000, 'to_m' => 7_000], ['from_m' => 20_000, 'to_m' => 21_000]], [], 40_000);
        $this->profile('b-embranchement', [['from_m' => 30_000, 'to_m' => 30_500]], [['from_m' => 30_500, 'to_m' => 35_000]], 34_500);
        $this->profile('c-continue', [], [], 50_000);
        DB::table('lines')->insert(['slug' => 'd-sans-profil', 'name' => 'Ligne sans profil']);
        $csv = tempnam(sys_get_temp_dir(), 'vff-trous');
        $this->assertIsString($csv);
        $before = DB::table('line_profiles')->count();

        try {
            $this->artisan('vff:profile-trace-holes', ['--csv' => $csv])
                ->expectsTable(['', 'Valeur'], [
                    ['Profils', 3], ['Lignes à trous', 1], ['Trous', 2], ['km de trous (à vol d\'oiseau)', '7'],
                    ['Trous de plus de 5 km', 1], ['Lignes dont les trous font 25 % du parcours ou plus', 0], ['Lignes sans profil', 1],
                ])
                ->assertSuccessful();
            $rows = array_map('str_getcsv', file($csv, FILE_IGNORE_NEW_LINES) ?: []);
        } finally {
            unlink($csv);
        }

        array_shift($rows);
        $this->assertSame([['a-trous', '1000', '7000'], ['a-trous', '20000', '21000']], array_map(fn (array $r): array => [$r[0], $r[6], $r[7]], $rows));
        // 7 km de trous pour 40 km connus : 14,9 % du parcours.
        $this->assertSame('0.149', $rows[0][4]);
        $this->assertSame($before, DB::table('line_profiles')->count());
    }
}
