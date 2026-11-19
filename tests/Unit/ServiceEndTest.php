<?php

namespace Tests\Unit;

use App\Enums\ServiceEnd;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Nature de la dernière année de desserte (App\Enums\ServiceEnd) : une borne haute venue d'une
 * fermeture en intervalle n'est jamais présentée comme une année sûre.
 */
class ServiceEndTest extends TestCase
{
    /** @return array<string, array{0: ?int, 1: ?string, 2: ?string, 3: ?ServiceEnd}> */
    public static function cases(): array
    {
        return [
            // Jarménil : fermée « entre 1871 et 2026 », borne = date du calcul.
            'fin non datée' => [2026, '2026-11-19', 'range', ServiceEnd::Undated],
            // Frenelle-la-Grande - Puzieux : fermée « entre 1980 et 1989 ».
            'borne haute' => [1989, '1989-12-31', 'range', ServiceEnd::UpperBound],
            // Briouze : fermée le 31/05/1970.
            'fermeture datée' => [1970, '1970-05-31', 'day', ServiceEnd::Year],
            // Fin venue d'un événement de la ligne, antérieure à la borne de la gare.
            'événement de ligne avant la borne' => [1938, '1989-12-31', 'range', ServiceEnd::Year],
            'gare ouverte, ligne fermée' => [1954, null, null, ServiceEnd::Year],
            'pas de fin' => [null, '1989-12-31', 'range', null],
        ];
    }

    #[DataProvider('cases')]
    public function test_the_kind_of_the_last_year_of_service(?int $lastYear, ?string $closedAt, ?string $precision, ?ServiceEnd $expected): void
    {
        $this->assertSame($expected, ServiceEnd::of($lastYear, $closedAt, $precision, 2026));
    }

    public function test_a_bound_of_a_past_calculation_stays_an_upper_bound(): void
    {
        // Calcul de 2026 lu en 2027 : « au plus tard en 2026 » reste vrai.
        $this->assertSame(ServiceEnd::UpperBound, ServiceEnd::of(2026, '2026-11-19', 'range', 2027));
    }
}
