<?php

namespace Tests\Unit\StationService;

use App\Enums\ServicePattern;
use App\Services\StationService\ServicePatternDetector;
use App\Services\StationService\Ymd;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Motif de desserte (ServicePatternDetector) sur une fenêtre de 12 semaines,
 * du lundi 07/12/2026 au dimanche 28/02/2027 : un seuil par cas.
 */
class ServicePatternDetectorTest extends TestCase
{
    private const FROM = 20261207;

    private const TO = 20270228;

    /**
     * Jours desservis : tous ceux de la fenêtre qui passent le filtre (jour ISO, rang du jour).
     *
     * @param  callable(int, int): bool  $keep
     * @return array<int, int>
     */
    private static function days(callable $keep): array
    {
        $out = [];
        for ($i = 0, $day = self::FROM; $day <= self::TO; $i++, $day = Ymd::addDays($day, 1)) {
            if ($keep((int) Ymd::toDate($day)->format('N'), $i)) {
                $out[$day] = 2;
            }
        }

        return $out;
    }

    /** @return array<string, array{callable(int, int): bool, ServicePattern}> */
    public static function cases(): array
    {
        return [
            'tous les jours' => [fn (int $dow, int $i) => true, ServicePattern::Daily],
            'tous les jours sauf quelques-uns' => [fn (int $dow, int $i) => $i % 9 !== 0, ServicePattern::Daily],
            'en semaine' => [fn (int $dow, int $i) => $dow <= 5, ServicePattern::Weekdays],
            'le week-end' => [fn (int $dow, int $i) => $dow >= 6, ServicePattern::Weekend],
            'mercredi et samedi' => [fn (int $dow, int $i) => $dow === 3 || $dow === 6, ServicePattern::SomeDays],
            'trois jours' => [fn (int $dow, int $i) => $i < 3, ServicePattern::Sporadic],
            'au milieu de la fenêtre' => [fn (int $dow, int $i) => $i >= 28 && $i < 56, ServicePattern::Seasonal],
            'à partir de la 7e semaine' => [fn (int $dow, int $i) => $i >= 42, ServicePattern::Starts],
            'jusqu\'à la 6e semaine' => [fn (int $dow, int $i) => $i < 42, ServicePattern::Ends],
            'une semaine sur trois' => [fn (int $dow, int $i) => intdiv($i, 7) % 3 === 0, ServicePattern::Intermittent],
        ];
    }

    /** @param  callable(int, int): bool  $keep */
    #[DataProvider('cases')]
    public function test_pattern(callable $keep, ServicePattern $expected): void
    {
        $this->assertSame($expected, (new ServicePatternDetector)->profile(self::days($keep), self::FROM, self::TO)?->pattern);
    }

    public function test_no_served_day_gives_no_profile(): void
    {
        $this->assertNull((new ServicePatternDetector)->profile([], self::FROM, self::TO));
        $this->assertNull((new ServicePatternDetector)->profile([20260101 => 4, self::FROM => 0], self::FROM, self::TO));
    }

    public function test_days_outside_the_window_are_ignored(): void
    {
        $profile = (new ServicePatternDetector)->profile([20261201 => 5, self::FROM => 2, self::TO => 3, 20270301 => 9], self::FROM, self::TO);

        $this->assertSame([5, 2, self::FROM, self::TO], [$profile?->calls, $profile?->servedDays, $profile?->first, $profile?->last]);
    }

    public function test_the_profile_counts_weeks_and_weekdays(): void
    {
        $profile = (new ServicePatternDetector)->profile(self::days(fn (int $dow, int $i) => $dow === 6), self::FROM, self::TO);

        $this->assertSame([12, 12, 12], [$profile?->servedDays, $profile?->servedWeeks, $profile?->weeks]);
        $this->assertSame(12, $profile->weekdays[6]);
        $this->assertSame(['2026-12-12', '2027-02-27'], [$profile->toArray()['first'], $profile->toArray()['last']]);
    }

    public function test_a_regular_profile_beats_a_busier_irregular_one(): void
    {
        $detector = new ServicePatternDetector;
        $regular = $detector->profile(self::days(fn (int $dow, int $i) => $dow === 6), self::FROM, self::TO);
        $busy = $detector->profile(self::days(fn (int $dow, int $i) => $i < 30), self::FROM, self::TO);

        $this->assertNotNull($regular);
        $this->assertTrue($regular->isBetterThan($busy));
        $this->assertFalse($busy?->isBetterThan($regular));
        $this->assertTrue($regular->isBetterThan(null));
    }
}
