<?php

namespace Tests\Unit\Home;

use App\Services\HomeArchives;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * « Le rail en images » sans base de données : fenêtres de rotation (30 minutes réelles, changement de
 * jour et d'heure, fuseau de la machine), bornes des périodes, tirage indépendant de l'ordre de la réserve.
 * Le cache et la réserve en base sont testés par tests/Feature/HomeArchivesRotationTest.
 */
class HomeArchivesWindowTest extends TestCase
{
    private const HALF_HOUR = 1800;

    private static function at(string $local, string $zone = 'Europe/Paris'): int
    {
        return (new DateTimeImmutable($local, new DateTimeZone($zone)))->getTimestamp();
    }

    /** @return list<array{id: int, year: int}> */
    private static function pool(): array
    {
        $out = [];
        foreach ([1880, 1890, 1914, 1915, 1930, 1945, 1946, 1960, 1970, 1971, 1980, 1990, 1991, 2000, 2010, 2011, 2020] as $i => $year) {
            $out[] = ['id' => $i + 1, 'year' => $year];
        }

        return $out;
    }

    public function test_window_length_has_a_floor_of_one_minute(): void
    {
        $this->assertSame(60, (new HomeArchives(10, 7))->windowSeconds());
        $this->assertSame(60, (new HomeArchives(0, 7))->windowSeconds());
        $this->assertSame(self::HALF_HOUR, (new HomeArchives(self::HALF_HOUR, 7))->windowSeconds());
    }

    public function test_local_midnight_starts_a_window_and_the_day_change_changes_the_draw(): void
    {
        $archives = new HomeArchives(self::HALF_HOUR, 7);
        $midnight = self::at('2026-11-22 00:00:00');
        $this->assertSame($midnight, $archives->windowStart($midnight));
        $this->assertSame($midnight - self::HALF_HOUR, $archives->windowStart($midnight - 1));
        $this->assertSame($midnight, $archives->windowStart($midnight + self::HALF_HOUR - 1));
        $before = $archives->pick(self::pool(), $archives->windowStart($midnight - 1), []);
        $after = $archives->pick(self::pool(), $archives->windowStart($midnight), []);
        $this->assertNotSame(array_column($before, 'id'), array_column($after, 'id'));
    }

    public function test_windows_do_not_depend_on_the_machine_time_zone(): void
    {
        $archives = new HomeArchives(self::HALF_HOUR, 7);
        $now = self::at('2026-11-21 10:47:12');
        $zone = date_default_timezone_get();
        $starts = [];
        try {
            foreach (['UTC', 'Europe/Paris', 'America/Cayenne', 'Pacific/Noumea', 'Asia/Kolkata'] as $tz) {
                date_default_timezone_set($tz);
                $starts[] = $archives->windowStart($now);
            }
        } finally {
            date_default_timezone_set($zone);
        }
        $this->assertSame([self::at('2026-11-21 10:30:00')], array_values(array_unique($starts)));
        // Fuseaux décalés d'une demi-heure (Inde) : la fenêtre commence aussi à l'heure ou à la demie locale.
        $this->assertSame(self::at('2026-11-21 15:30:00', 'Asia/Kolkata'), $archives->windowStart(self::at('2026-11-21 15:59:59', 'Asia/Kolkata')));
    }

    public function test_daylight_saving_nights_keep_thirty_real_minutes_per_window(): void
    {
        $archives = new HomeArchives(self::HALF_HOUR, 7);
        // Passage à l'heure d'hiver (3 h → 2 h, 25/10/2026) et à l'heure d'été (2 h → 3 h, 29/03/2026) à Paris.
        foreach (['2026-10-25 00:00:00', '2026-03-29 00:00:00'] as $night) {
            $from = self::at($night);
            $starts = [];
            for ($t = $from; $t < $from + 6 * 3600; $t += 60) {
                $starts[$archives->windowStart($t)] = ($starts[$archives->windowStart($t)] ?? 0) + 1;
            }
            $keys = array_keys($starts);
            $this->assertSame(array_fill(0, count($keys), 30), array_values($starts), "{$night} : 30 minutes par fenêtre");
            for ($i = 1; $i < count($keys); $i++) {
                $this->assertSame(self::HALF_HOUR, $keys[$i] - $keys[$i - 1], "{$night} : aucune fenêtre sautée ni répétée");
            }
        }
    }

    public function test_period_bounds(): void
    {
        $archives = new HomeArchives(self::HALF_HOUR, 7);
        $this->assertSame([1, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 6], array_map($archives->period(...), [1830, 1914, 1915, 1945, 1946, 1970, 1971, 1990, 1991, 2010, 2011, 2030]));
    }

    public function test_draw_ignores_the_order_of_the_reserve(): void
    {
        $archives = new HomeArchives(self::HALF_HOUR, 7);
        $pool = self::pool();
        $reversed = array_reverse($pool);
        foreach ([0, 1800, 3600, 1_763_712_000] as $seed) {
            $this->assertSame($archives->pick($pool, $seed, [3]), $archives->pick($reversed, $seed, [3]));
        }
    }

    public function test_count_bounds_and_avoided_images_last(): void
    {
        $pool = self::pool();
        $this->assertSame([], (new HomeArchives(self::HALF_HOUR, 0))->pick($pool, 1800, []));
        // Plus d'images demandées que la réserve n'en a : toute la réserve, en ordre chronologique.
        $all = (new HomeArchives(self::HALF_HOUR, 50))->pick($pool, 1800, []);
        $this->assertSame(array_column($pool, 'year'), array_column($all, 'year'));
        // Six images, une par période : aucune image à éviter n'est prise tant qu'une autre reste dans sa période.
        $six = new HomeArchives(self::HALF_HOUR, 6);
        $avoid = [1, 2, 4, 5, 7, 8, 10, 11, 13, 14, 16];
        $picked = array_column($six->pick($pool, 1800, $avoid), 'id');
        $this->assertSame([3, 6, 9, 12, 15, 17], $picked);
        // Tout est à éviter : la rotation reprend plutôt que de laisser un vide.
        $this->assertCount(6, $six->pick($pool, 1800, array_column($pool, 'id')));
    }

    public function test_same_year_is_avoided_in_the_second_round_and_ties_sort_by_id(): void
    {
        $pool = [['id' => 9, 'year' => 1900], ['id' => 3, 'year' => 1900], ['id' => 5, 'year' => 1905]];
        $two = (new HomeArchives(self::HALF_HOUR, 2))->pick($pool, 1800, []);
        $this->assertSame([1900, 1905], array_column($two, 'year'));
        $three = (new HomeArchives(self::HALF_HOUR, 3))->pick($pool, 1800, []);
        $this->assertSame([3, 9, 5], array_column($three, 'id'));
    }
}
