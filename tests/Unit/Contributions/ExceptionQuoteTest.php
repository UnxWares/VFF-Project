<?php

namespace Tests\Unit\Contributions;

use App\Models\Material;
use App\Services\Contributions\ExceptionQuote;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phrase citée qui lève une règle d'écartement ou d'électrification : elle nomme la série et la
 * valeur, sans négation autour, en français, en anglais ou en allemand.
 */
class ExceptionQuoteTest extends TestCase
{
    private const TRACTION = ['rule' => 'traction', 'details' => ['volts' => [750], 'share' => 0, 'partial' => false]];

    private const GAUGE = ['rule' => 'gauge', 'details' => ['series_mm' => 1000, 'line_mm' => 1435, 'share' => 0]];

    private function z1200(): Material
    {
        return new Material(['name' => 'Z 1200', 'designations' => ['Automotrices des Moulineaux']]);
    }

    /** @return iterable<string, array{string, array<string, mixed>, ?string}> */
    public static function quotes(): iterable
    {
        yield 'phrase de l’article' => ['La ligne est électrifiée par troisième rail en 750 V ; tous les mouvements sont assurés par les automotrices Z 1200.', self::TRACTION, null];
        yield 'nom collé' => ['Les Z1200 roulaient sous 750 V.', self::TRACTION, null];
        yield 'désignation' => ['Les automotrices des Moulineaux, en 750 V, assuraient le service.', self::TRACTION, null];
        yield 'anglais' => ['The Z 1200 units ran on the 750 V third rail.', self::TRACTION, null];
        yield 'série absente' => ['Jamais en 750 V ici.', self::TRACTION, 'series'];
        yield 'autre série' => ['Les Z 12000 roulaient en 750 V.', self::TRACTION, 'series'];
        yield 'jamais' => ['Les Z 1200 n’ont jamais roulé en 750 V ici.', self::TRACTION, 'negated'];
        yield 'pas' => ['La ligne n’était pas en 750 V pour les Z 1200.', self::TRACTION, 'negated'];
        yield 'anglais, contraction' => ['The Z 1200 didn’t run on 750 V here.', self::TRACTION, 'negated'];
        yield 'allemand' => ['Die Z 1200 fuhren nie mit 750 V.', self::TRACTION, 'negated'];
        yield 'négation dans une autre phrase' => ['Pas de vapeur. Les Z 1200 roulaient en 750 V.', self::TRACTION, null];
        yield 'écartement nié' => ['Aucune voie métrique pour les Z 1200.', self::GAUGE, 'negated'];
        yield 'écartement affirmé' => ['Une file de rails à voie métrique a été posée pour les Z 1200.', self::GAUGE, null];
        yield 'écartement en allemand' => ['Für die Z 1200 wurde ein Meterspurgleis verlegt.', self::GAUGE, null];
        yield 'sans' => ['Les Z 1200 circulaient sans 750 V sur cette section.', self::TRACTION, 'negated'];
    }

    /** @param array{rule: string, details: array<string, mixed>} $conflict */
    #[DataProvider('quotes')]
    public function test_quotes(string $quote, array $conflict, ?string $problem): void
    {
        $this->assertSame($problem, ExceptionQuote::problem($quote, $this->z1200(), $conflict));
    }
}
