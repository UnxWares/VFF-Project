<?php

namespace Tests\Unit;

use App\Services\StationCorrections;
use App\Services\StationLineExclusions;
use App\Services\StationLineInclusions;
use PHPUnit\Framework\TestCase;

/** Liens gare - ligne ajoutés à la main, et décisions du 23/11/2026 sur Saignon, le Train des Mouettes et Saint-Martin-d'Étampes. */
class StationLineInclusionsTest extends TestCase
{
    private static function data(string $file): array
    {
        return require dirname(__DIR__, 2).'/database/data/'.$file.'.php';
    }

    public function test_the_versioned_inclusions_are_valid_and_unique(): void
    {
        $keys = [];
        foreach (self::data('station_line_inclusions') as $i => $e) {
            $this->assertSame([], StationLineInclusions::validate($e), "inclusion n° {$i}");
            $keys[] = $e['station'].'|'.$e['line'];
        }
        $this->assertSame($keys, array_values(array_unique($keys)));
    }

    public function test_saint_martin_d_etampes_is_linked_to_the_rer_c_line(): void
    {
        $found = array_values(array_filter(self::data('station_line_inclusions'), fn ($e) => $e['station'] === 'st-martin-detampes'));
        $this->assertCount(1, $found);
        $this->assertSame('rfn-570000', $found[0]['line']);
        $this->assertStringContainsString('oldid=240282342', $found[0]['source_url']);
    }

    public function test_the_train_des_mouettes_is_excluded_from_line_544000_only(): void
    {
        $found = array_values(array_filter(self::data('station_line_exclusions'), fn ($e) => $e['station'] === 'le-train-des-mouettes-saujon'));
        $this->assertCount(1, $found);
        $this->assertSame('rfn-544000', $found[0]['line']);
        $this->assertSame([], StationLineExclusions::validate($found[0]));
        $this->assertStringContainsString('oldid=', $found[0]['source_url']);
    }

    public function test_saignon_keeps_the_1934_service_end_and_cites_the_1938_closure(): void
    {
        $closures = array_values(array_filter(self::data('gares_corrections'),
            fn ($c) => ($c['station']['name'] ?? '') === 'Saignon' && ($c['type'] ?? '') === 'closure_passenger'));
        $this->assertCount(1, $closures);
        $c = $closures[0];
        $this->assertSame([], StationCorrections::validate($c));
        $this->assertSame('1934-01-15', $c['date']);
        $this->assertStringContainsString('oldid=222987396', $c['source_url']);
        $this->assertStringContainsString('240748405', $c['reason']);
        $this->assertStringContainsString('02/10/1938', $c['reason']);
    }
}
