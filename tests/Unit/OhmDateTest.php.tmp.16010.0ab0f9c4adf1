<?php

namespace Tests\Unit;

use App\Services\OhmDate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Dates OpenHistoricalMap (start_date / end_date et variantes :edtf) : valeur
 * ISO réduite, incertitude EDTF, texte libre des contributeurs français.
 * Règles de prudence pour des cartes par décennie (cf. docblock d'OhmDate).
 */
class OhmDateTest extends TestCase
{
    /** @return array<string, array{0: string, 1: ?string, 2: ?string, 3: ?bool}> valeur, date, précision, approx */
    public static function valueProvider(): array
    {
        return [
            'année seule' => ['1890', '1890-01-01', 'year', false],
            'année-mois' => ['1890-06', '1890-06-01', 'month', false],
            'date complète' => ['1890-06-15', '1890-06-15', 'day', false],
            'mois sur un chiffre' => ['1890-6-5', '1890-06-05', 'day', false],
            'heure ignorée' => ['1890-06-15T10:30:00Z', '1890-06-15', 'day', false],
            'jour impossible → mois' => ['1890-02-30', '1890-02-01', 'month', false],
            'mois impossible → année' => ['1890-13', '1890-01-01', 'year', false],
            'EDTF environ' => ['1890~', '1890-01-01', 'year', true],
            'EDTF incertain' => ['1890?', '1890-01-01', 'year', true],
            'décennie 199X' => ['199X', '1990-01-01', 'year', true],
            'siècle 19XX' => ['19XX', '1900-01-01', 'year', true],
            'intervalle d\'années : la plus tardive' => ['1890/1895', '1895-01-01', 'year', true],
            'intervalle dégénéré a/a = date exacte' => ['1890-05-01/1890-05-01', '1890-05-01', 'day', false],
            'intervalle de dates : la plus tardive, dégradée à l\'année' => ['1890-05-01/1891-07-14', '1891-01-01', 'year', true],
            'année seule plus tardive que la date complète' => ['1833-08-29/1864', '1864-01-01', 'year', true],
            'borne ouverte' => ['/1866', '1866-01-01', 'year', true],
            'borne ouverte EDTF « .. »' => ['..1890', '1890-01-01', 'year', true],
            'ensemble EDTF' => ['[..1890]', '1890-01-01', 'year', true],
            'texte libre « vers »' => ['vers 1890', '1890-01-01', 'year', true],
            'texte libre « hiver 1997/1998 »' => ['hiver 1997/1998', '1998-01-01', 'year', true],
            'texte libre saisons' => ['été/automne 1932', '1932-01-01', 'year', true],
            'texte libre « approx. »' => ['approx. 1890', '1890-01-01', 'year', true],
            'vide' => ['', null, null, null],
            'blancs' => ['   ', null, null, null],
            'avant notre ère' => ['-0050', null, null, null],
            'avant 1700' => ['1650', null, null, null],
            'futur lointain' => ['2999', null, null, null],
            'sans année' => ['inconnue', null, null, null],
        ];
    }

    #[DataProvider('valueProvider')]
    public function test_parse_reads_iso_edtf_and_free_text(string $value, ?string $date, ?string $precision, ?bool $approx): void
    {
        $parsed = OhmDate::parse($value);

        if ($date === null) {
            $this->assertNull($parsed);

            return;
        }
        $this->assertNotNull($parsed, $value);
        $this->assertSame($date, $parsed['date'], $value);
        $this->assertSame((int) substr($date, 0, 4), $parsed['year'], $value);
        $this->assertSame($precision, $parsed['precision'], $value);
        $this->assertSame($approx, $parsed['approx'], $value);
    }

    public function test_iso_value_wins_and_uncertain_edtf_degrades_it_to_the_year(): void
    {
        $d = OhmDate::fromTags(['start_date' => '1890-06-15', 'start_date:edtf' => '1890-06-15~'], 'start');

        $this->assertSame(['date' => '1890-01-01', 'year' => 1890, 'precision' => 'year', 'approx' => true,
            'raw' => '1890-06-15 (edtf : 1890-06-15~)'], $d);
    }

    public function test_plain_edtf_copy_of_the_iso_value_keeps_full_precision(): void
    {
        $d = OhmDate::fromTags(['end_date' => '1938-05-15', 'end_date:edtf' => '1938-05-15'], 'end');

        $this->assertSame('1938-05-15', $d['date']);
        $this->assertSame('day', $d['precision']);
        $this->assertFalse($d['approx']);
    }

    public function test_unreadable_iso_falls_back_on_the_latest_bound_of_the_edtf_interval(): void
    {
        $d = OhmDate::fromTags(['end_date' => 'hiver', 'end_date:edtf' => '1997/1998'], 'end');

        $this->assertSame(1998, $d['year']);
        $this->assertTrue($d['approx']);
        $this->assertStringContainsString('edtf : 1997/1998', $d['raw']);
    }

    public function test_opening_date_tag_is_a_start_fallback_only(): void
    {
        $this->assertSame('1882-07-01', OhmDate::fromTags(['opening_date' => '1882-07-01'], 'start')['date']);
        $this->assertNull(OhmDate::fromTags(['opening_date' => '1882-07-01'], 'end'));
        // start_date présent : opening_date ne sert pas.
        $this->assertSame(1880, OhmDate::fromTags(['start_date' => '1880', 'opening_date' => '1882'], 'start')['year']);
    }

    public function test_missing_or_unreadable_tags_give_no_date(): void
    {
        $this->assertNull(OhmDate::fromTags([], 'start'));
        $this->assertNull(OhmDate::fromTags(['start_date' => '', 'start_date:edtf' => ''], 'start'));
        $this->assertNull(OhmDate::fromTags(['end_date' => 'inconnue'], 'end'));
        $this->assertNull(OhmDate::fromTags(['end_date' => '-0050'], 'end'));
    }

    public function test_non_string_tag_values_are_tolerated(): void
    {
        $this->assertSame(1890, OhmDate::fromTags(['start_date' => 1890], 'start')['year']);
    }

    public function test_is_uncertain_distinguishes_a_plain_copy_from_real_uncertainty(): void
    {
        $this->assertFalse(OhmDate::isUncertain('1890'));
        $this->assertFalse(OhmDate::isUncertain('1890-06-15'));
        $this->assertTrue(OhmDate::isUncertain('1890~'));
        $this->assertTrue(OhmDate::isUncertain('1890/1895'));
        $this->assertTrue(OhmDate::isUncertain('19XX'));
        // Illisible : on ne peut pas s'y fier.
        $this->assertTrue(OhmDate::isUncertain('inconnue'));
    }
}
