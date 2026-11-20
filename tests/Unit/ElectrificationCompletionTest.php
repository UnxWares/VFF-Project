<?php

namespace Tests\Unit;

use App\Services\Traction\ElectrificationText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Achèvement d'une électrification « toute la ligne » (ElectrificationText::completionYear),
 * qui borne la date de mise sous tension de toutes les portions (vff:enrich-era-segments).
 */
class ElectrificationCompletionTest extends TestCase
{
    /** @return array<string, array{0: string, 1: ?int}> */
    public static function texts(): array
    {
        return [
            'entre deux années' => ['Électrifiée entre 1926 et 1935.', 1935],
            'entièrement' => ['Ligne entièrement électrifiée.', 1930],
            'achèvement' => ['Achèvement de l\'électrification.', 1930],
            'électrification achevée' => ['L\'électrification achevée en 1930 permet la traction électrique.', 1930],
            'électrification est achevée' => ['L\'électrification est achevée en 1930.', 1930],
            'électrification a été achevée' => ['L\'électrification a été achevée en 1930.', 1930],
            'complète' => ['Électrification complète en 1500 V.', 1930],
            'section achevée' => ['La section Paris - Juvisy est achevée en 1930.', null],
            'partielle' => ['Électrification partielle.', null],
        ];
    }

    #[DataProvider('texts')]
    public function test_completion_year(string $text, ?int $expected): void
    {
        $this->assertSame($expected, ElectrificationText::completionYear($text, 1930));
    }
}
