<?php

namespace Tests\Unit\Sncf;

use App\Services\Sncf\SncfPk;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SncfPkTest extends TestCase
{
    /** @return array<string, array{?string, ?int}> */
    public static function writings(): array
    {
        return [
            'kilomètre et mètres' => ['016+953', 16953],
            'moins de mètres' => ['001-250', 750],
            'avant le kilomètre 0' => ['000-168', -168],
            'PK négatif' => ['-001+200', -1200],
            'mètres sur quatre chiffres (quais)' => ['013+1089', 14089],
            'lettre D' => ['D+841', 3841],
            'lettre H' => ['H+566', 7566],
            'minuscule' => ['d+000', 3000],
            'espaces' => [' D+000 ', 3000],
            'vide' => ['', null],
            'null' => [null, null],
            'texte' => ['abc', null],
            'deux lettres' => ['DD+100', null],
        ];
    }

    #[DataProvider('writings')]
    public function test_reads_sncf_pk_writings(?string $raw, ?int $meters): void
    {
        $this->assertSame($meters, SncfPk::meters($raw));
    }

    /** Les lettres valent 1 000 m : D+822 à E+038 = les 216 m du quai B de Musée-d'Orsay. */
    public function test_letter_kilometres_are_consistent_with_platform_length(): void
    {
        $this->assertSame(216, SncfPk::meters('E+038') - SncfPk::meters('D+822'));
        $this->assertSame(3725, SncfPk::meters('H+566') - SncfPk::meters('D+841'));
    }
}
