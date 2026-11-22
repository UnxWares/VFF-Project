<?php

namespace Tests\Unit\Sncf;

use App\Services\Sncf\StationPlatforms;
use PHPUnit\Framework\TestCase;

class StationPlatformsTest extends TestCase
{
    /** @return array{label: string, code_ligne: string, length_m: ?int, pk_start_m: ?int, pk_end_m: ?int} */
    private static function row(string $label, ?int $length, ?int $from, ?int $to, string $line = '420000'): array
    {
        return ['label' => $label, 'code_ligne' => $line, 'length_m' => $length, 'pk_start_m' => $from, 'pk_end_m' => $to];
    }

    public function test_pk_is_read_in_metres(): void
    {
        $this->assertSame(622_096, StationPlatforms::pkMetres('622+096'));
        $this->assertSame(-252, StationPlatforms::pkMetres('000-252'));
        $this->assertNull(StationPlatforms::pkMetres('D+841'));
        $this->assertNull(StationPlatforms::pkMetres(null));
    }

    public function test_label_key_ignores_case_and_spaces(): void
    {
        $this->assertSame(StationPlatforms::labelKey('Quai 20/21'), StationPlatforms::labelKey(' quai 20/ 21 '));
    }

    public function test_a_central_platform_listed_once_per_track_is_one_platform(): void
    {
        // Brest : « Quai A/B » figure deux fois (voies A et B), avec deux longueurs.
        $platforms = StationPlatforms::group([
            self::row('Quai A/B', 219, 622_096, 622_315),
            self::row('Quai A/B', 312, 622_096, 622_408),
            self::row('Quai E/F', 387, 622_017, 622_404),
        ]);

        $this->assertCount(2, $platforms);
        $this->assertSame(['label' => 'Quai E/F', 'code_ligne' => '420000', 'length_m' => 387, 'pk_start_m' => 622_017, 'pk_end_m' => 622_404, 'records' => 1], $platforms[0]);
        $this->assertSame(['label' => 'Quai A/B', 'code_ligne' => '420000', 'length_m' => 312, 'pk_start_m' => 622_096, 'pk_end_m' => 622_408, 'records' => 2], $platforms[1]);
        $this->assertSame(['count' => 2, 'longest_m' => 387, 'total_length_m' => 699], StationPlatforms::summary($platforms));
    }

    public function test_same_label_far_apart_or_on_another_line_stays_separate(): void
    {
        $platforms = StationPlatforms::group([
            self::row('Quai', 120, 10_000, 10_120),
            self::row('Quai', 90, 10_500, 10_590),
            self::row('Quai', 100, 10_000, 10_100, '421000'),
        ]);

        $this->assertCount(3, $platforms);
    }

    public function test_reversed_or_missing_pk_and_missing_length(): void
    {
        $platforms = StationPlatforms::group([
            self::row('Quai 1', null, 5_300, 5_100),
            self::row('Quai 1', 210, null, null),
        ]);

        $this->assertCount(1, $platforms);
        $this->assertSame([5_100, 5_300, 210], [$platforms[0]['pk_start_m'], $platforms[0]['pk_end_m'], $platforms[0]['length_m']]);
        $this->assertSame(['count' => 0, 'longest_m' => null, 'total_length_m' => 0], StationPlatforms::summary([]));
    }

    public function test_an_extent_far_beyond_the_length_is_an_error_of_the_dataset(): void
    {
        $this->assertTrue(StationPlatforms::plausibleExtent(425, 316));
        $this->assertTrue(StationPlatforms::plausibleExtent(425, 1_475));
        $this->assertFalse(StationPlatforms::plausibleExtent(425, 10_425));
        $this->assertFalse(StationPlatforms::plausibleExtent(145, 6_774));
        $this->assertTrue(StationPlatforms::plausibleExtent(null, 10_000));
    }

    public function test_pk_extent_of_platforms_ignores_those_without_pk(): void
    {
        $this->assertSame([861_764, 862_112], StationPlatforms::pkExtent([
            self::row('J/K', 340, 861_764, 862_104), self::row('L/M', 290, null, null), self::row('N', 245, 861_822, 862_112),
        ]));
        $this->assertNull(StationPlatforms::pkExtent([self::row('1', 100, null, null)]));
    }

    public function test_two_groups_are_on_the_same_site_when_their_pk_overlap(): void
    {
        $this->assertTrue(StationPlatforms::sameSite([861_764, 862_112], [861_648, 862_104]));
        $this->assertTrue(StationPlatforms::sameSite([100, 200], [240, 300]), 'écart de 40 m : toléré');
        $this->assertFalse(StationPlatforms::sameSite([100, 200], [400, 500]));
        $this->assertFalse(StationPlatforms::sameSite([0, 256_026], [0, 125]), 'un site de 256 km est une erreur de PK');
        $this->assertFalse(StationPlatforms::sameSite([100, 200], null));
    }
}
