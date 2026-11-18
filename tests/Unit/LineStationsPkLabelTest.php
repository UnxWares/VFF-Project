<?php

namespace Tests\Unit;

use App\Services\LineStations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PK des gares d'une ligne (LineStations::pkLabel) : « kilomètres+mètres ».
 * Une gare de bifurcation projetée en amont de l'origine de la ligne a un PK
 * négatif (Briouze, -250 m sur 434000) : « -0+250 », jamais « 0+-250 ».
 */
class LineStationsPkLabelTest extends TestCase
{
    /** @return array<string, array{0: ?int, 1: ?string}> */
    public static function labels(): array
    {
        return [
            'origine' => [0, '0+000'],
            'mètres' => [7, '0+007'],
            'kilomètres' => [7_735, '7+735'],
            'grand PK' => [657_700, '657+700'],
            'négatif < 1 km' => [-250, '-0+250'],
            'négatif' => [-1_250, '-1+250'],
            'négatif rond' => [-3_000, '-3+000'],
            'inconnu' => [null, null],
        ];
    }

    #[DataProvider('labels')]
    public function test_pk_label(?int $meters, ?string $expected): void
    {
        $this->assertSame($expected, LineStations::pkLabel($meters));
    }
}
