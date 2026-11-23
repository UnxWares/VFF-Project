<?php

namespace Tests\Unit;

use App\Console\Commands\ImportStationSources;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * vff:import-station-sources, source Wikidata : lecture d'un élément (parseWikidata) sans réseau.
 *   - seules les gares et haltes de la Terre sont gardées (globe Q2), avec un nom ;
 *   - métro et tramway seuls sont écartés ;
 *   - ouverture, réouverture, fermeture (voyageurs, fret) et démolition, avec leur précision ;
 *   - cycle de vie tiré de P5817, sinon de la classe et des fermetures.
 */
class StationSourcesWikidataTest extends TestCase
{
    private const EARTH = 'http://www.wikidata.org/entity/Q2';

    private function command(): ImportStationSources
    {
        // Sans lire le fichier des corrections (pas d'application Laravel dans ce test).
        return (new ImportStationSources)->withLinkedWikidata([]);
    }

    /** @param  array<string, mixed>  $entity */
    private function parse(array $entity, string $qid = 'Q1'): ?array
    {
        $command = $this->command();

        return (new ReflectionMethod($command, 'parseWikidata'))->invoke($command, $qid, $entity);
    }

    private static function claim(mixed $value, array $qualifiers = [], string $rank = 'normal'): array
    {
        return ['rank' => $rank, 'mainsnak' => ['snaktype' => 'value', 'datavalue' => ['value' => $value]], 'qualifiers' => $qualifiers];
    }

    private static function time(string $date, int $precision = 11): array
    {
        return ['time' => '+'.$date.'T00:00:00Z', 'precision' => $precision];
    }

    private static function item(string $class = 'Q55488', array $claims = [], array $extra = []): array
    {
        return [
            'labels' => ['fr' => ['value' => 'Gare de Test']],
            'claims' => [
                'P625' => [self::claim(['longitude' => 2.5, 'latitude' => 47.5, 'globe' => self::EARTH])],
                'P31' => [self::claim(['id' => $class])],
            ] + $claims,
        ] + $extra;
    }

    public function test_a_station_is_read_with_its_position_and_name(): void
    {
        $r = $this->parse(self::item(), 'Q123');

        $this->assertSame('Q123', $r['source_ref']);
        $this->assertSame('Q123', $r['wikidata_id']);
        $this->assertSame('Gare de Test', $r['name']);
        $this->assertSame('gare', $r['kind']);
        $this->assertSame(2.5, $r['lon']);
        $this->assertSame(47.5, $r['lat']);
        $this->assertNull($r['opened_on']);
        $this->assertNull($r['closed_on']);
    }

    public function test_a_halt_is_a_halt(): void
    {
        $this->assertSame('halte', $this->parse(self::item('Q55678'))['kind']);
    }

    public function test_the_english_label_is_the_fallback_name_and_without_any_label_nothing_is_kept(): void
    {
        $item = self::item();
        $item['labels'] = ['en' => ['value' => 'Test station']];
        $this->assertSame('Test station', $this->parse($item)['name']);

        $item['labels'] = [];
        $this->assertNull($this->parse($item));
    }

    public function test_an_item_off_earth_or_without_coordinates_is_ignored(): void
    {
        $item = self::item();
        $item['claims']['P625'] = [self::claim(['longitude' => 2.5, 'latitude' => 47.5, 'globe' => 'http://www.wikidata.org/entity/Q405'])];
        $this->assertNull($this->parse($item));

        unset($item['claims']['P625']);
        $this->assertNull($this->parse($item));
    }

    public function test_an_item_that_is_not_a_station_is_ignored(): void
    {
        $this->assertNull($this->parse(self::item('Q5')));
    }

    public function test_metro_or_tram_stops_alone_are_excluded_but_not_a_railway_station_that_is_also_one(): void
    {
        $item = self::item('Q55493');
        $this->assertNull($this->parse($item), 'rien que du tramway');

        $item['claims']['P31'] = [self::claim(['id' => 'Q55488']), self::claim(['id' => 'Q55493'])];
        $this->assertNotNull($this->parse($item), 'gare ferroviaire et tramway');
    }

    public function test_opening_and_closure_dates_keep_their_precision(): void
    {
        $r = $this->parse(self::item('Q55488', [
            'P1619' => [self::claim(self::time('1869-12-06'))],
            'P3999' => [self::claim(self::time('1969-00-00', 9))],
        ]));

        $this->assertSame(['1869-12-06', 'day'], [$r['opened_on'], $r['opened_precision']]);
        $this->assertSame(['1969-01-01', 'year'], [$r['closed_on'], $r['closed_precision']]);
        $this->assertSame('all', $r['closed_scope']);
        $this->assertSame('disused', $r['lifecycle']);
    }

    public function test_a_passenger_only_closure_is_scoped_to_passengers_and_a_freight_one_is_typed(): void
    {
        $passenger = $this->parse(self::item('Q55488', [
            'P3999' => [self::claim(self::time('1969-05-04'), ['P518' => [['datavalue' => ['value' => ['id' => 'Q1433891']]]]])],
        ]));
        $this->assertSame('passenger', $passenger['closed_scope']);
        $this->assertSame('closure_passenger', $passenger['events'][0]['type']);

        $freight = $this->parse(self::item('Q55488', [
            'P3999' => [self::claim(self::time('1980-05-04'), ['P518' => [['datavalue' => ['value' => ['id' => 'Q2234623']]]]])],
        ]));
        $this->assertSame('closure_freight', $freight['events'][0]['type']);
    }

    public function test_a_reopening_after_a_closure_means_the_station_is_open(): void
    {
        $r = $this->parse(self::item('Q55488', [
            'P1619' => [
                self::claim(self::time('1869-12-06')),
                self::claim(self::time('2010-06-01'), ['P518' => [['datavalue' => ['value' => ['id' => 'Q16571590']]]]]),
            ],
            'P3999' => [self::claim(self::time('1969-05-04'))],
        ]));

        $this->assertSame(['opening', 'closure', 'reopening'], array_column($r['events'], 'type'));
        $this->assertNull($r['closed_on'], 'la dernière date est une réouverture');
        $this->assertSame('1869-12-06', $r['opened_on'], 'la première ouverture est gardée');
    }

    public function test_the_founding_date_stands_in_only_when_no_opening_date_exists(): void
    {
        $founded = $this->parse(self::item('Q55488', ['P571' => [self::claim(self::time('1850-01-01', 9))]]));
        $this->assertSame('1850-01-01', $founded['opened_on']);

        $both = $this->parse(self::item('Q55488', [
            'P571' => [self::claim(self::time('1850-01-01', 9))],
            'P1619' => [self::claim(self::time('1869-12-06'))],
        ]));
        $this->assertSame('1869-12-06', $both['opened_on']);
    }

    public function test_dates_less_precise_than_a_year_and_unreadable_times_are_ignored(): void
    {
        $r = $this->parse(self::item('Q55488', [
            'P1619' => [self::claim(self::time('1800-00-00', 8)), self::claim(['time' => 'inconnu', 'precision' => 11])],
        ]));

        $this->assertNull($r['opened_on']);
        $this->assertNull($r['events']);
    }

    public function test_deprecated_statements_are_ignored(): void
    {
        $r = $this->parse(self::item('Q55488', ['P1619' => [self::claim(self::time('1869-12-06'), [], 'deprecated')]]));

        $this->assertNull($r['opened_on']);
    }

    public function test_a_demolition_closes_the_station(): void
    {
        $r = $this->parse(self::item('Q55488', ['P576' => [self::claim(self::time('1975-01-01', 9))]]));

        $this->assertSame('demolition', $r['events'][0]['type']);
        $this->assertSame('1975-01-01', $r['closed_on']);
    }

    /** @return array<string, array{string, string}> */
    public static function lifecycles(): array
    {
        return [
            'en usage' => ['Q55654238', 'active'],
            'désaffectée' => ['Q11639308', 'disused'],
            'rasée' => ['Q56556915', 'razed'],
            'abandonnée' => ['Q63065035', 'abandoned'],
            'projetée' => ['Q811683', 'planned'],
        ];
    }

    #[DataProvider('lifecycles')]
    public function test_the_lifecycle_comes_from_the_usage_state(string $state, string $expected): void
    {
        $r = $this->parse(self::item('Q55488', ['P5817' => [self::claim(['id' => $state])]]));

        $this->assertSame($expected, $r['lifecycle']);
        $this->assertSame($state, $r['properties']['state_of_use']);
    }

    public function test_without_a_usage_state_a_former_station_class_means_disused_otherwise_unknown(): void
    {
        $this->assertSame('disused', $this->parse(self::item('Q4663385'))['lifecycle']);
        $this->assertSame('unknown', $this->parse(self::item('Q55488'))['lifecycle']);
    }

    public function test_uic_code_lines_aliases_and_frwiki_are_kept(): void
    {
        $r = $this->parse(self::item('Q55488', [
            'P722' => [self::claim('87 474 007')],
            'P81' => [self::claim(['id' => 'Q111']), self::claim(['id' => 'Q222'])],
        ], ['aliases' => ['fr' => [['value' => 'Brest']]], 'sitelinks' => ['frwiki' => ['title' => 'Gare de Brest']]]));

        $this->assertSame('87474007', $r['uic_code']);
        $this->assertSame(['Q111', 'Q222'], $r['properties']['lines']);
        $this->assertSame(['Brest'], $r['properties']['aliases']);
        $this->assertSame('Gare de Brest', $r['properties']['frwiki']);
    }

    /** Sans P5817 (cas le plus courant), la lecture du cycle de vie ne lève aucune dépréciation PHP 8.5. */
    public function test_a_missing_usage_state_raises_no_php_deprecation(): void
    {
        $deprecations = [];
        set_error_handler(function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, E_DEPRECATED);
        try {
            $this->parse(self::item('Q55488'));
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $deprecations);
    }

    public function test_a_freight_station_item_is_read_only_when_a_correction_links_it(): void
    {
        $item = self::item('Q55493', ['P1619' => [self::claim(self::time('1902-05-01', 11))]]);

        $this->assertNull($this->parse($item, 'Q55'), 'classe exclue : écarté');

        $command = $this->command()->withLinkedWikidata(['Q55']);
        $r = (new ReflectionMethod($command, 'parseWikidata'))->invoke($command, 'Q55', $item);
        $this->assertSame('Q55', $r['source_ref']);
        $this->assertSame('1902-05-01', $r['opened_on']);
        $this->assertSame(2.5, $r['lon']);

        $other = (new ReflectionMethod($command, 'parseWikidata'))->invoke($command, 'Q56', $item);
        $this->assertNull($other, 'un autre élément de marchandises reste écarté');
    }

    public function test_linked_items_are_added_to_the_list_without_duplicates(): void
    {
        $ids = ImportStationSources::wikidataIds(['Q1', 'Q2'], ['Q2', 'Q9'], ['Q9', 'Q7', 'Q1', 'Q5']);

        $this->assertSame(['Q1', 'Q2', 'Q9', 'Q5', 'Q7'], $ids);
        $this->assertSame(['Q1', 'Q2'], ImportStationSources::wikidataIds(['Q1', 'Q2'], [], []));
    }
}
