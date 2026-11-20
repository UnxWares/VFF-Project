<?php

namespace Tests\Unit;

use App\Services\OhmDate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Dates OpenHistoricalMap (OhmDate) : cas limites, en complément d'OhmDateTest.
 *   - texte libre SANS marque d'incertitude contenant des dates complètes :
 *     la plus tardive, à sa précision réelle (jour, mois) ;
 *   - marques d'incertitude en majuscules ou accentuées (« VERS », « après »,
 *     « début ») : précision dégradée à l'année ;
 *   - bornes d'années (1700, an courant + 30) et de mois (1, 12, 0) ;
 *   - blancs autour des valeurs, clé :edtf vide, raw lisible.
 */
class OhmDateEdgeCasesTest extends TestCase
{
    /** @return array<string, array{0: string, 1: ?string, 2: ?string, 3: ?bool}> */
    public static function parseProvider(): array
    {
        return [
            'deux dates complètes sans incertitude : la plus tardive' => ['1890-05-01 1891-07-14', '1891-07-14', 'day', false],
            'plus tardive écrite en premier' => ['1891-07-14 1890-05-01', '1891-07-14', 'day', false],
            'dates au mois sans incertitude' => ['1890-05 et 1891-07', '1891-07-01', 'month', false],
            'date complète et même année isolée' => ['1890-05-01 (1890)', '1890-05-01', 'day', false],
            'date complète et année isolée plus tardive' => ['1890-05-01 (1895)', '1895-01-01', 'year', true],
            'marque en majuscules' => ['VERS 1890-05-01', '1890-01-01', 'year', true],
            'marque accentuée « après »' => ['après 1890-05-01', '1890-01-01', 'year', true],
            'marque accentuée « début »' => ['début 1890-05-01', '1890-01-01', 'year', true],
            'intervalle dégénéré précédé d\'une marque' => ['vers 1890/1890', '1890-01-01', 'year', true],
            'intervalle dégénéré suivi d\'une marque' => ['1890/1890 environ', '1890-01-01', 'year', true],
            'blancs autour d\'une année ISO' => [' 1890 ', '1890-01-01', 'year', false],
            'année négative' => ['-1890', null, null, null],
            'année 1700 : acceptée' => ['1700', '1700-01-01', 'year', false],
            'année 1699 : refusée' => ['1699', null, null, null],
            'janvier' => ['1890-01', '1890-01-01', 'month', false],
            'décembre' => ['1890-12', '1890-12-01', 'month', false],
            'mois 0 : année seule' => ['1890-00', '1890-01-01', 'year', false],
            'mois 0 avec jour : année seule' => ['1890-00-15', '1890-01-01', 'year', false],
        ];
    }

    #[DataProvider('parseProvider')]
    public function test_parse(string $value, ?string $date, ?string $precision, ?bool $approx): void
    {
        $parsed = OhmDate::parse($value);
        if ($date === null) {
            $this->assertNull($parsed, $value);

            return;
        }
        $this->assertSame(['date' => $date, 'year' => (int) substr($date, 0, 4), 'precision' => $precision, 'approx' => $approx], $parsed, $value);
    }

    public function test_future_bound_is_the_current_year_plus_thirty(): void
    {
        $limit = (int) date('Y') + 30;

        $this->assertSame($limit, OhmDate::parse((string) $limit)['year']);
        $this->assertNull(OhmDate::parse((string) ($limit + 1)));
    }

    public function test_certain_edtf_does_not_override_the_iso_value(): void
    {
        $d = OhmDate::fromTags(['start_date' => '1890-06-15', 'start_date:edtf' => '1890-06'], 'start');

        $this->assertSame(['date' => '1890-06-15', 'year' => 1890, 'precision' => 'day', 'approx' => false,
            'raw' => '1890-06-15 (edtf : 1890-06)'], $d);
    }

    public function test_blank_values_are_treated_as_missing(): void
    {
        // start_date blanc : repli sur opening_date ; :edtf blanc : aucune incertitude.
        $this->assertSame('1882-07-01', OhmDate::fromTags(['start_date' => '   ', 'opening_date' => '1882-07-01'], 'start')['date']);

        $d = OhmDate::fromTags(['start_date' => '1890-06-15', 'start_date:edtf' => '  '], 'start');
        $this->assertSame('day', $d['precision']);
        $this->assertFalse($d['approx']);
        $this->assertSame('1890-06-15', $d['raw']);
    }

    public function test_raw_value_is_trimmed_and_readable(): void
    {
        $this->assertSame('(edtf : 1890~)', OhmDate::fromTags(['end_date:edtf' => ' 1890~ '], 'end')['raw']);
        $this->assertSame('1890 (edtf : 1890~)', OhmDate::fromTags(['end_date' => ' 1890 ', 'end_date:edtf' => '1890~'], 'end')['raw']);
        $this->assertSame('1882-07-01 (edtf : 1882-07-01)',
            OhmDate::fromTags(['opening_date' => ' 1882-07-01 ', 'start_date:edtf' => '1882-07-01'], 'start')['raw']);
    }
}
