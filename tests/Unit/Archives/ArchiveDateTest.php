<?php

namespace Tests\Unit\Archives;

use App\Services\Archives\ArchiveDate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Dates des notices UNIMARC (zone 100, positions 8 à 16) et texte imprimé (210 $d) : année, intervalle,
 * jour exact, date approchée ; jamais une année devinée quand la notice ne la donne pas.
 */
class ArchiveDateTest extends TestCase
{
    /** @return iterable<string, array{string|null, string|null, ?int, ?int, ?string, string, ?string}> */
    public static function cases(): iterable
    {
        yield 'une date' => ['19970701d1860    m  y0frey50      ba', '1860', 1860, null, null, 'year', '1860'];
        yield 'date entre crochets (déduite par le catalogueur)' => ['20111026d1838    u  y0frey50      ba', '[1838]', 1838, null, null, 'year', '[1838]'];
        yield 'date approchée' => ['20180720d1850    u  y0frey50      ba', '[ca 1850]', 1850, null, null, 'circa', '[ca 1850]'];
        yield 'date incertaine sans borne' => ['20130722f1846    u  y0frey50      ba', '[1846]', 1846, null, null, 'circa', '[1846]'];
        yield 'date incertaine entre deux années' => ['20130722f18601865 u  y0frey50      ba', '[1860-1865]', 1860, 1865, null, 'range', '[1860-1865]'];
        yield 'publication étalée' => ['20130722g18421844 u  y0frey50      ba', '1842-1844', 1842, 1844, null, 'range', '1842-1844'];
        yield 'décennie seule' => ['20130722d189.    u  y0frey50      ba', '189.', 1890, 1899, null, 'range', '189.'];
        yield 'siècle seul : inconnue' => ['20130722d18..    u  y0frey50      ba', '[ca 18..]', null, null, null, 'unknown', '[ca 18..]'];
        yield 'jour exact' => ['20080101j19210509 u  y0frey50      ba', '9 mai 1921', 1921, null, '1921-05-09', 'day', '9 mai 1921'];
        yield 'mois seul' => ['20080101j192105   u  y0frey50      ba', 'mai 1921', 1921, null, '1921-05-01', 'month', 'mai 1921'];
        yield 'reproduction : date de l\'original' => ['20080101e19951860 u  y0frey50      ba', null, 1860, null, null, 'year', '1860'];
        yield 'sans date (s.d.)' => ['20200928u        u  y0frey50         ', '[s.d.]', null, null, null, 'unknown', null];
        yield 'zone 100 absente, année dans le texte' => [null, 'janvier 1837', 1837, null, null, 'year', 'janvier 1837'];
        yield 'rien du tout' => [null, null, null, null, null, 'unknown', null];
        yield 'année hors du possible' => ['20200928d0000    u  y0frey50         ', null, null, null, null, 'unknown', null];
    }

    #[DataProvider('cases')]
    public function test_dates_are_read_at_their_precision(?string $field100, ?string $label, ?int $year, ?int $yearTo, ?string $exact, string $precision, ?string $expectedLabel): void
    {
        $date = ArchiveDate::fromUnimarc($field100, $label);

        $this->assertSame($year, $date->year);
        $this->assertSame($yearTo, $date->yearTo);
        $this->assertSame($exact, $date->exact);
        $this->assertSame($precision, $date->precision);
        $this->assertSame($expectedLabel, $date->label);
    }
}
