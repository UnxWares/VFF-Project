<?php

namespace Tests\Unit\Mutation;

use App\Rules\PartialDate;
use Illuminate\Translation\PotentiallyTranslatedString;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Bornes de la règle PartialDate (filtres `from` et `to` de l'API) que la validation par
 * tests/Unit/Api/ApiSupportTest.php ne touchait pas : types refusés, première année admise.
 */
class PartialDateRuleTest extends TestCase
{
    private function fails(mixed $value): bool
    {
        $failed = false;
        (new PartialDate)->validate('from', $value, function (string $message) use (&$failed): PotentiallyTranslatedString {
            $failed = true;

            return new PotentiallyTranslatedString($message, app('translator'));
        });

        return $failed;
    }

    /** @return iterable<string, array{mixed, bool}> */
    public static function values(): iterable
    {
        yield 'an 1, première année admise' => ['0001', false];
        yield 'an 1 au jour' => ['0001-01-01', false];
        yield 'an 0' => ['0000', true];
        yield 'an 0 au jour' => ['0000-01-01', true];
        yield 'année entière en nombre' => [1930, false];
        yield 'année en nombre décimal' => [1930.0, true];
        yield 'tableau' => [['1930'], true];
        yield 'booléen' => [true, true];
        yield 'nul' => [null, true];
        yield '29 février bissextile' => ['1880-02-29', false];
        yield '29 février non bissextile' => ['1879-02-29', true];
        yield 'mois 13' => ['1879-13-01', true];
        yield 'jour 0' => ['1879-03-00', true];
    }

    #[DataProvider('values')]
    public function test_values(mixed $value, bool $fails): void
    {
        $this->assertSame($fails, $this->fails($value));
    }
}
