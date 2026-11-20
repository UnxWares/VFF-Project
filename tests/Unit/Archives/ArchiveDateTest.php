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
        yield 'zone 100 absente, mois et année dans le texte' => [null, 'janvier 1837', 1837, null, '1837-01-01', 'month', 'janvier 1837'];
        yield 'zone 100 absente, année seule' => [null, '1837', 1837, null, null, 'year', '1837'];
        // Formes relevées dans le cache des réponses de la BnF (revue du commit bb27386, point 5).
        yield 'jour imprimé' => ['20071107d1926    m  y0frey50      ba', '21 juin 1926', 1926, null, '1926-06-21', 'day', '21 juin 1926'];
        yield 'jour en chiffres' => ['20071107d1874    m  y0frey50      ba', '10/05/1874', 1874, null, '1874-05-10', 'day', '10/05/1874'];
        yield 'premier du mois' => ['20071107d1862    m  y0frey50      ba', '1er novembre 1862', 1862, null, '1862-11-01', 'day', '1er novembre 1862'];
        yield 'jour entre crochets' => ['20071107d1904    m  y0frey50      ba', '[25 août 1904]', 1904, null, '1904-08-25', 'day', '[25 août 1904]'];
        yield 'jour en anglais' => ['20071107d1870    m  y0frey50      ba', 'August 6, 1870', 1870, null, '1870-08-06', 'day', 'August 6, 1870'];
        yield 'mois seul imprimé' => ['20071107d1923    m  y0frey50      ba', '[Mai 1923]', 1923, null, '1923-05-01', 'month', '[Mai 1923]'];
        yield 'jour illisible, mois imprimé' => ['20071107d1877    m  y0frey50      ba', '12.. décembre 1877', 1877, null, '1877-12-01', 'month', '12.. décembre 1877'];
        yield 'jour d\'une autre année : ignoré' => ['20071107d1927    m  y0frey50      ba', '21 juin 1926', 1927, null, null, 'year', '21 juin 1926'];
        yield 'date impossible : ignorée' => ['20071107d1926    m  y0frey50      ba', '31/02/1926', 1926, null, null, 'year', '31/02/1926'];
        yield 'circa' => ['20071107d1869    m  y0frey50      ba', 'circa 1869', 1869, null, null, 'circa', 'circa 1869'];
        yield 'circa sans zone 100' => [null, 'circa 1869', 1869, null, null, 'circa', 'circa 1869'];
        yield 'ca. avec point' => ['20071107d1880    m  y0frey50      ba', 'ca. 1880', 1880, null, null, 'circa', 'ca. 1880'];
        yield 'sans date entre parenthèses, année codée' => ['20071107d1890    m  y0frey50      ba', '(s. d.)', 1890, null, null, 'year', '1890'];
        yield 'sans date avec virgule' => ['20071107d1852    m  y0frey50      ba', '(s. d.,)', 1852, null, null, 'year', '1852'];
        yield 'sans date, rien de codé' => ['20200928u        u  y0frey50         ', 'S. d.', null, null, null, 'unknown', null];
        yield 'texte et code en désaccord : le texte, en date approchée' => ['20071107d1864    m  y0frey50      ba', '1854', 1854, null, null, 'circa', '1854'];
        yield 'copyright d\'une reproduction : date de l\'original' => ['20071107e19931790 m  y0frey50      ba', 'cop. 1993', 1790, null, null, 'year', 'cop. 1993'];
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
