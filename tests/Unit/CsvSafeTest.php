<?php

namespace Tests\Unit;

use App\Support\CsvSafe;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CsvSafeTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function formulas(): array
    {
        return [
            'égal' => ['=HYPERLINK("http://x","y")'],
            'plus' => ['+cmd|calc'],
            'moins' => ['-2+3'],
            'arobase' => ['@SUM(A1)'],
            'tabulation' => ["\t=1+1"],
            'retour chariot' => ["\r=1+1"],
            'tabulation puis nombre' => ["\t1"],
        ];
    }

    #[DataProvider('formulas')]
    public function test_formula_like_text_gets_a_leading_apostrophe(string $text): void
    {
        $this->assertSame("'".$text, CsvSafe::cell($text));
    }

    public function test_plain_text_numbers_and_empty_values_are_untouched(): void
    {
        $this->assertSame('Gare de Lyon', CsvSafe::cell('Gare de Lyon'));
        $this->assertSame('a=b', CsvSafe::cell('a=b'));
        $this->assertSame('', CsvSafe::cell(''));
        $this->assertSame('', CsvSafe::cell(null));
        $this->assertSame(-5, CsvSafe::cell(-5));
        $this->assertSame(-1.5, CsvSafe::cell(-1.5));
        $this->assertSame('-12', CsvSafe::cell('-12'));
        $this->assertSame('+3.5', CsvSafe::cell('+3.5'));
        $this->assertSame('1', CsvSafe::cell(true));
    }

    public function test_row_keeps_order_and_protects_each_cell(): void
    {
        $this->assertSame(["'=A1", 3, 'ok', ''], CsvSafe::row(['x' => '=A1', 'y' => 3, 'z' => 'ok', 'w' => null]));
    }
}
