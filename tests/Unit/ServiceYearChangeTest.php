<?php

namespace Tests\Unit;

use App\Enums\ServiceYearChange;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Classement d'un changement d'année de desserte (vff:attach-stations --dry-run). */
class ServiceYearChangeTest extends TestCase
{
    /** @return iterable<string, array{?int, ?int, ?ServiceYearChange}> */
    public static function changes(): iterable
    {
        yield 'inchangée' => [1881, 1881, null];
        yield 'toujours vide' => [null, null, null];
        yield 'ajoutée' => [null, 1938, ServiceYearChange::Added];
        yield 'retirée (desservie aujourd\'hui)' => [2014, null, ServiceYearChange::Removed];
        yield 'avancée' => [1995, 1931, ServiceYearChange::Earlier];
        yield 'reculée' => [1855, 2026, ServiceYearChange::Later];
    }

    #[DataProvider('changes')]
    public function test_change_between_two_years(?int $before, ?int $after, ?ServiceYearChange $expected): void
    {
        $this->assertSame($expected, ServiceYearChange::between($before, $after));
    }

    public function test_every_change_has_a_french_label(): void
    {
        $this->assertSame(['ajoutée', 'retirée', 'avancée', 'reculée'], array_map(fn (ServiceYearChange $c) => $c->label(), ServiceYearChange::cases()));
    }
}
