<?php

namespace Tests\Unit;

use App\Support\CsvSafe;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * CsvSafe : cas limites (nombres écrits en texte, notation scientifique, objets, aller-retour fputcsv).
 */
class CsvSafeEdgesTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function cases(): array
    {
        return [
            'nombre négatif décimal' => ['-0.5', '-0.5'],
            'nombre positif signé' => ['+12', '+12'],
            'notation scientifique négative' => ['-1e5', "'-1e5"],
            'moins seul' => ['-', "'-"],
            'plus seul' => ['+', "'+"],
            'téléphone international' => ['+33 1 23 45', "'+33 1 23 45"],
            'décimal sans partie entière' => ['.5', '.5'],
            'décimal avec virgule' => ['1,5', '1,5'],
            'décimal terminé par un point' => ['5.', '5.'],
            'tiret au milieu' => ['Paris-Nord', 'Paris-Nord'],
            'égal au milieu' => ['a=b', 'a=b'],
            'arobase au milieu' => ['jean@example.org', 'jean@example.org'],
            'espace avant égal' => [' =1+1', ' =1+1'],
            'texte accentué' => ['Éléonore', 'Éléonore'],
        ];
    }

    #[DataProvider('cases')]
    public function test_cell_text_cases(string $input, string $expected): void
    {
        $this->assertSame($expected, CsvSafe::cell($input));
    }

    public function test_zero_and_false_like_values_keep_their_type(): void
    {
        $this->assertSame(0, CsvSafe::cell(0));
        $this->assertSame(0.0, CsvSafe::cell(0.0));
        $this->assertSame('0', CsvSafe::cell(false));
        $this->assertSame('0', CsvSafe::cell('0'));
    }

    public function test_stringable_objects_are_protected_like_text(): void
    {
        $formula = new class
        {
            public function __toString(): string
            {
                return '=1+1';
            }
        };

        $this->assertSame("'=1+1", CsvSafe::cell($formula));
    }

    public function test_row_of_an_empty_array_is_empty_and_keys_are_dropped(): void
    {
        $this->assertSame([], CsvSafe::row([]));
        $this->assertSame(['a', "'-b"], CsvSafe::row(['k1' => 'a', 'k2' => '-b']));
    }

    public function test_a_written_csv_reads_back_without_any_leading_formula_character(): void
    {
        $fh = fopen('php://memory', 'w+');
        fputcsv($fh, CsvSafe::row(['=HYPERLINK("x")', '+cmd', '@sum', "\tx", 'normal', 12, null]), escape: '');
        rewind($fh);
        $cells = str_getcsv((string) fgets($fh), escape: '');
        fclose($fh);

        foreach (array_slice($cells, 0, 4) as $cell) {
            $this->assertStringStartsWith("'", $cell);
        }
        $this->assertSame(['normal', '12', ''], array_slice($cells, 4));
    }
}
