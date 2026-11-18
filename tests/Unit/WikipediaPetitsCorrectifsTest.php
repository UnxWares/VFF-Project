<?php

namespace Tests\Unit;

use App\Services\WikipediaLineParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * WikipediaLineParser, phrases réelles (line_events.source_quote, 2026-10-19) :
 *   - une sous-section d'une section écartée (« Matériel roulant ») l'est aussi (3905 Pithiviers :
 *     « mise en service sur la TPT 1929 » d'une locomotive n'est pas une ouverture) ;
 *   - un trafic qui survit jusqu'à une date (« perdura … jusqu'au ») est la FIN de ce trafic, pas
 *     une suite de l'événement précédent (876000 : fausse dépose 1969 Pontarlier - Hôpitaux-Neufs).
 */
class WikipediaPetitsCorrectifsTest extends TestCase
{
    private WikipediaLineParser $p;

    protected function setUp(): void
    {
        $this->p = new WikipediaLineParser;
    }

    /** @return list<string> « type date from - to » */
    private function events(string $wikitext, string $title): array
    {
        return array_map(fn ($e) => trim("{$e['type']} {$e['date']} ".($e['section_from'] ?? '').' - '.($e['section_to'] ?? ''), ' -'),
            $this->p->parseChronology($wikitext, $title));
    }

    private const PITHIVIERS = "== Histoire ==\nLa ligne est fermée aux voyageurs le 1er mars 1951.\n\n"
        ."== Matériel roulant ==\n=== Locomotives à simple expansion ===\n*3-5, Blanc Misseron, 030T, (282) 1902\n"
        ."=== Locomotives compound [[Locomotive articulée|système Mallet]] ===\n"
        ."*22-2, Decauville N°149 de 1892, baptisée \"Grandchamp\" puis devint la 22-2,(Ex Chemin de fer du Calvados)mise en service sur la TPT 1929 démolie en 1936.\n";

    public function test_3905_locomotive_put_in_service_in_a_rolling_stock_subsection_is_not_an_opening(): void
    {
        $events = $this->events(self::PITHIVIERS, 'Tramway de Pithiviers à Toury');

        $this->assertSame([], array_values(array_filter($events, fn ($e) => str_contains($e, '1929') || str_contains($e, '1936'))));
        $this->assertContains('closure_passenger 1951-03-01', $events, 'la section Histoire reste lue');
    }

    /** @return array<string, array{0: string}> */
    public static function rollingStockHeadingProvider(): array
    {
        return ['Matériel roulant' => ['Matériel roulant'], 'Matériel' => ['Matériel'], 'Matériel moteur' => ['Matériel moteur'],
            'Matériels utilisés' => ['Matériels utilisés']];
    }

    #[DataProvider('rollingStockHeadingProvider')]
    public function test_nothing_is_read_below_a_rolling_stock_section(string $heading): void
    {
        $wikitext = "== {$heading} ==\n=== Autorails ===\nLes autorails X 2100 sont mis en service sur la ligne de Alpha à Bravo en 1975.\n"
            ."==== Retrait ====\nIls sont retirés en 1990, date de la fermeture du dépôt.\n";

        $this->assertSame([], $this->events($wikitext, 'Ligne de Alpha à Bravo'));
    }

    public function test_a_section_following_a_skipped_one_is_read_again(): void
    {
        $wikitext = "== Matériel roulant ==\n=== Locomotives ===\n*030T mise en service en 1902.\n"
            ."== Histoire ==\nLa ligne de Alpha à Bravo est ouverte le 1er juin 1880.\n";

        $this->assertSame(['opening 1880-06-01'], array_map(fn ($e) => substr($e, 0, 18), $this->events($wikitext, 'Ligne de Alpha à Bravo')));
    }

    private const PONTARLIER = "== Histoire ==\n=== Déclin et fermeture ===\n"
        ."Le service voyageur, assuré en autorails depuis 1935, est suspendu le {{date-|19 avril 1939}} et dès 1940, le transport de marchandises cesse également du fait que l'armée française a fait sauter la voûte du tunnel des Hôpitaux. "
        ."Du côté suisse la ligne fut démontée entre 1942 et 1943, alors que le trafic marchandise perdura entre Pontarlier et la gare des [[Gare des Hôpitaux-Neufs - Jougne|Hôpitaux-Neufs-Jougne]] jusqu'au {{date-|3 novembre 1969}}.\n";

    public function test_876000_traffic_that_lasted_until_a_date_is_its_end_not_a_dismantling(): void
    {
        $events = $this->events(self::PONTARLIER, 'Ligne de Pontarlier à Vallorbe (frontière)');

        $this->assertSame([], array_values(array_filter($events, fn ($e) => str_starts_with($e, 'dismantling 1969'))));
        $this->assertContains('closure_freight 1969-11-03 Pontarlier - Hôpitaux-Neufs-Jougne', $events);
    }

    public function test_side_of_a_border_is_a_cited_portion(): void
    {
        foreach (['Du côté suisse la ligne fut démontée en 1942.', 'Sur le territoire belge, la ligne est fermée le 1er juin 1950.'] as $sentence) {
            $events = $this->p->parseChronology("== Histoire ==\n{$sentence}\n", 'Ligne de Alpha à Bravo');
            $this->assertCount(1, $events, $sentence);
            $this->assertTrue($events[0]['portion_unknown'], $sentence);
        }
        $whole = $this->p->parseChronology("== Histoire ==\nLa ligne de Alpha à Bravo fut démontée en 1942.\n", 'Ligne de Alpha à Bravo');
        $this->assertFalse($whole[0]['portion_unknown']);
    }

    /** @return array<string, array{0: string, 1: ?string}> */
    public static function survivingTrafficProvider(): array
    {
        return [
            'fret subsista' => ['Le trafic des marchandises subsista entre Alpha et Bravo jusqu\'en 1972.', 'closure_freight 1972'],
            'voyageurs se maintint' => ['Le service des voyageurs se maintint jusqu\'au 2 mai 1953 entre Alpha et Bravo.', 'closure_passenger 1953'],
            'sans trafic nommé' => ['La voie subsista jusqu\'en 1980 entre Alpha et Bravo.', null],
            'sans date de fin' => ['Le trafic des marchandises perdura longtemps entre Alpha et Bravo.', null],
        ];
    }

    #[DataProvider('survivingTrafficProvider')]
    public function test_surviving_traffic_rule(string $sentence, ?string $expected): void
    {
        $events = array_map(fn ($e) => substr($e, 0, strlen((string) $expected) ?: 0), array_filter(
            $this->events("== Histoire ==\n{$sentence}\n", 'Ligne de Alpha à Bravo'), fn ($e) => ! str_starts_with($e, 'closure_passenger 1938')));

        if ($expected === null) {
            $this->assertSame([], array_values(array_filter($this->events("== Histoire ==\n{$sentence}\n", 'Ligne de Alpha à Bravo'),
                fn ($e) => preg_match('/^(closure|dismantling)/', $e) && ! str_starts_with($e, 'closure_passenger 1938'))));
        } else {
            $this->assertContains($expected, $events);
        }
    }

    /** @return array<string, array{0: string}> */
    public static function equipmentProvider(): array
    {
        return [
            '752000 ETCS (bullet)' => ["== Chronologie ==\n* 27 septembre 1981 : première mise en service commerciale.\n* 12 novembre 2024 : mise en service technique de l'ETCS sur la ligne."],
            'ERTMS mis en service' => ["== Histoire ==\nLa ligne est ouverte le 27 septembre 1981. L'ERTMS est mis en service sur la ligne le 12 novembre 2024."],
            'TVM 430' => ["== Histoire ==\nLa ligne est ouverte le 27 septembre 1981. Mise en service de la TVM 430 sur la ligne le 5 juin 1996."],
            'KVB' => ["== Histoire ==\nLa ligne est ouverte le 27 septembre 1981. Le KVB est mis en service en 1993 sur la ligne."],
            'GSM-R' => ["== Histoire ==\nLa ligne est ouverte le 27 septembre 1981. Le GSM-R est mis en service sur la ligne en 2008."],
            'poste et commande centralisée' => ["== Histoire ==\nLa ligne est ouverte le 27 septembre 1981. La commande centralisée de la ligne est mise en service à Lyon en 2005."],
        ];
    }

    /** Une mise en service d'équipement n'est jamais une ouverture de la ligne. */
    #[DataProvider('equipmentProvider')]
    public function test_equipment_put_in_service_is_not_an_opening(string $wikitext): void
    {
        $openings = array_values(array_filter($this->events($wikitext, 'LGV Sud-Est'), fn ($e) => str_starts_with($e, 'opening')));

        $this->assertSame(['opening 1981-09-27'], array_map(fn ($e) => substr($e, 0, 18), $openings));
    }

    public function test_equipment_rx_lists_train_protection_systems(): void
    {
        foreach (['etcs', 'ertms', 'tvm', 'kvb', 'gsm-r', 'bal', 'signalisation', 'commande centralisée'] as $w) {
            $this->assertMatchesRegularExpression('/^(?:'.WikipediaLineParser::EQUIPMENT_RX.')$/u', $w);
        }
        $this->assertDoesNotMatchRegularExpression('/^(?:'.WikipediaLineParser::EQUIPMENT_RX.')$/u', 'ligne');
    }
}
