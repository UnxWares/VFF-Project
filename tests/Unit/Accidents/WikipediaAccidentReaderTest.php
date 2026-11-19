<?php

namespace Tests\Unit\Accidents;

use App\Enums\AccidentDatePrecision;
use App\Services\Accidents\WikipediaAccidentReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Faits d'une introduction d'article de Wikipédia (infobox « Accident de transport ») : seuls des
 * faits sont lus, jamais le texte. Infobox reprise de l'article sur Brétigny-sur-Orge.
 */
class WikipediaAccidentReaderTest extends TestCase
{
    private const BRETIGNY = <<<'WIKI'
        {{Infobox Accident de transport
         | nom                   = Accident ferroviaire<br />de Brétigny-sur-Orge
         | date                  = {{date|12|juillet|2013}} 17h14
         | type                  = [[Déraillement]]
         | site                  = [[Gare de Brétigny]]
         | morts                 = 7<ref>Rapport du BEA-TT</ref>
         | blessés               = 70 dont 9 graves
         | appareil              = Train de voyageurs [[Intercités]]
         | compagnie             = [[Société nationale des chemins de fer français|SNCF]]
         | appareil2             = 
         | latitude              = 48.607316
         | longitude             = 2.302274
         | géolocalisation       = Essonne/Île-de-France/France
        }}
        L’'''accident ferroviaire de Brétigny-sur-Orge''' est un [[déraillement]] survenu sur la
        [[Ligne de Paris-Austerlitz à Bordeaux-Saint-Jean|ligne de Paris à Bordeaux]] et la [[LGV Atlantique]].
        WIKI;

    public function test_reads_facts_of_the_infobox_and_the_lines_cited(): void
    {
        $facts = (new WikipediaAccidentReader)->read(self::BRETIGNY);

        $this->assertSame('2013-07-12', $facts['date']);
        $this->assertSame(AccidentDatePrecision::Day, $facts['precision']);
        $this->assertSame('17:14', $facts['time']);
        $this->assertSame(7, $facts['deaths']);
        $this->assertFalse($facts['deaths_approx']);
        $this->assertSame(70, $facts['injuries']);
        $this->assertSame(48.607316, $facts['lat']);
        $this->assertSame(2.302274, $facts['lng']);
        $this->assertSame('Déraillement', $facts['type']);
        $this->assertSame(['Train de voyageurs Intercités'], $facts['trains']);
        $this->assertSame(['SNCF'], $facts['operators']);
        $this->assertSame(['Gare de Brétigny'], $facts['site_links']);
        $this->assertSame('91', $facts['department']);
        $this->assertSame(['Ligne de Paris-Austerlitz à Bordeaux-Saint-Jean', 'LGV Atlantique'], $facts['line_articles']);
    }

    /** @return iterable<string, array{0: string, 1: ?string, 2: ?AccidentDatePrecision}> */
    public static function dates(): iterable
    {
        yield 'modèle à trois paramètres' => ['{{date|12|juillet|2013}}', '2013-07-12', AccidentDatePrecision::Day];
        yield 'modèle à un paramètre' => ['{{date-|26 juillet 1891}}', '1891-07-26', AccidentDatePrecision::Day];
        yield 'premier du mois' => ['{{Date|1er|août|1922|dans les chemins de fer}}', '1922-08-01', AccidentDatePrecision::Day];
        yield 'mois' => ['juillet 1913', '1913-07-01', AccidentDatePrecision::Month];
        yield 'année' => ['[[1842]]', '1842-01-01', AccidentDatePrecision::Year];
        yield 'rien' => ['inconnue', null, null];
    }

    #[DataProvider('dates')]
    public function test_date_keeps_only_what_is_known(string $value, ?string $date, ?AccidentDatePrecision $precision): void
    {
        $this->assertSame([$date, $precision], (new WikipediaAccidentReader)->date($value));
    }

    public function test_counts_flag_approximate_values(): void
    {
        $reader = new WikipediaAccidentReader;

        $this->assertSame([70, false], $reader->count('70 dont 9 graves'));
        $this->assertSame([30, true], $reader->count('environ 30'));
        $this->assertSame([52, true], $reader->count('52 à 200'));
        $this->assertSame([1200, false], $reader->count("1\u{00A0}200"));
        $this->assertSame([0, false], $reader->count('aucun'));
        $this->assertSame([null, false], $reader->count('inconnu'));
        $this->assertSame([null, false], $reader->count(null));
        $this->assertSame([2, false], $reader->count('1 (+ 1 agent de chantier au sol)'));
    }

    public function test_toll_of_each_vehicle_is_added_up(): void
    {
        $reader = new WikipediaAccidentReader;
        $fields = ['morts' => '1 (+ 1 agent de chantier au sol)', 'blessés' => '0', 'morts2' => '4', 'blessés2' => '16 <small>(dont 2 graves)</small>'];

        $this->assertSame([6, false], $reader->total($fields, 'morts'));
        $this->assertSame([16, false], $reader->total($fields, 'blessés'));
        $this->assertSame([null, false], $reader->total([], 'morts'));
    }

    public function test_article_without_infobox_gives_only_the_lines(): void
    {
        $facts = (new WikipediaAccidentReader)->read("La '''collision''' a eu lieu sur la [[Ligne d'Auteuil|ligne d'Auteuil]].");

        $this->assertNull($facts['date']);
        $this->assertNull($facts['deaths']);
        $this->assertSame([], $facts['trains']);
        $this->assertSame(["Ligne d'Auteuil"], $facts['line_articles']);
    }
}
