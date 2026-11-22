<?php

namespace Tests\Unit;

use App\Services\Traction\LineElectrificationAggregate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Décision du drapeau « électrifiée » d'une ligne (LineElectrificationAggregate::decide) :
 * part des tronçons en service électrifiés, au seuil de l'état « marginal » de la fiche.
 */
class LineElectrificationDecisionTest extends TestCase
{
    /** @return array<string, array{0: float, 1: float, 2: bool, 3: ?bool}> */
    public static function cases(): array
    {
        return [
            'Limoges - Angoulême : 609 m sur 125 km' => [49_800.0, 2_310.0, true, false],
            'Andelot - La Cluse : 279 m sur 87 km' => [87_200.0, 279.0, true, false],
            'juste sous le seuil' => [100_000.0, 4_999.0, true, false],
            'au seuil' => [100_000.0, 5_000.0, true, true],
            'entièrement électrifiée' => [30_000.0, 30_000.0, true, true],
            'rien d\'électrifié' => [30_000.0, 0.0, true, false],
            'tronçons muets : valeur conservée' => [30_000.0, 0.0, false, null],
            'ligne sans longueur en service : valeur conservée' => [0.0, 0.0, true, null],
        ];
    }

    #[DataProvider('cases')]
    public function test_decision(float $active, float $electrified, bool $informed, ?bool $expected): void
    {
        $this->assertSame($expected, LineElectrificationAggregate::decide($active, $electrified, $informed));
    }
}
