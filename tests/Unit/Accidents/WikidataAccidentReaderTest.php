<?php

namespace Tests\Unit\Accidents;

use App\Enums\AccidentDatePrecision;
use App\Enums\AccidentKind;
use App\Enums\AccidentLocationPrecision;
use App\Services\Accidents\AccidentCandidate;
use App\Services\Accidents\AccidentKindClassifier;
use App\Services\Accidents\WikidataAccidentReader;
use PHPUnit\Framework\TestCase;

/**
 * Lecture d'éléments Wikidata d'accident (forme des réponses wbgetentities) : date et précision,
 * rangs, bilan ambigu jamais retenu, commune par le code INSEE de l'élément cité, lieu cité qui a
 * ses coordonnées, indices de lieu tirés des libellés.
 */
class WikidataAccidentReaderTest extends TestCase
{
    private WikidataAccidentReader $reader;

    protected function setUp(): void
    {
        $this->reader = new WikidataAccidentReader(new AccidentKindClassifier);
    }

    /** @param array<string, mixed> $value */
    private static function claim(string $type, array|string $value, string $rank = 'normal', array $references = []): array
    {
        return ['rank' => $rank, 'mainsnak' => ['datavalue' => ['type' => $type, 'value' => $value]], 'references' => $references];
    }

    private static function item(string $id, string $rank = 'normal'): array
    {
        return self::claim('wikibase-entityid', ['id' => $id], $rank);
    }

    private static function time(string $time, int $precision, string $rank = 'normal'): array
    {
        return self::claim('time', ['time' => $time, 'precision' => $precision], $rank);
    }

    private static function amount(int $n, string $rank = 'normal', ?string $url = null): array
    {
        $refs = $url !== null ? [['snaks' => ['P854' => [['datavalue' => ['value' => $url]]]]]] : [];

        return self::claim('quantity', ['amount' => '+'.$n, 'unit' => '1'], $rank, $refs);
    }

    private function millas(): array
    {
        return [
            'id' => 'Q45757847', 'lastrevid' => 2520152318,
            'labels' => ['fr' => ['value' => 'accident de Millas'], 'en' => ['value' => 'Millas train-bus collision']],
            'claims' => [
                'P31' => [self::item('Q19710423'), self::item('Q1078765')],
                'P17' => [self::item('Q142')],
                'P585' => [self::time('+2017-12-14T00:00:00Z', 11)],
                'P625' => [self::claim('globecoordinate', ['latitude' => 42.6852, 'longitude' => 2.7038])],
                'P131' => [self::item('Q190218')],
                'P1120' => [self::amount(6, url: 'https://example.org/bilan')],
                'P1339' => [self::amount(14)],
                'P137' => [self::item('Q13646')],
                'P121' => [self::item('Q3048911')],
            ],
            'sitelinks' => ['frwiki' => ['title' => 'Accident de Millas'], 'dewiki' => ['title' => 'Busunfall von Millas']],
        ];
    }

    private function related(): array
    {
        return [
            'Q190218' => ['labels' => ['fr' => ['value' => 'Millas']], 'claims' => ['P374' => [self::claim('string', '66108')]]],
            'Q13646' => ['labels' => ['fr' => ['value' => 'SNCF']], 'claims' => []],
            'Q3048911' => ['labels' => ['fr' => ['value' => 'SNCF Z 7300']], 'claims' => []],
        ];
    }

    public function test_reads_an_accident_element(): void
    {
        $c = $this->reader->read($this->millas(), $this->related());

        $this->assertInstanceOf(AccidentCandidate::class, $c);
        $this->assertSame('wikidata:Q45757847', $c->key);
        $this->assertSame('2017-12-14', $c->date);
        $this->assertSame(AccidentDatePrecision::Day, $c->precision);
        $this->assertSame(AccidentKind::LevelCrossing, $c->kind);
        $this->assertSame('Accident de Millas', $c->labels['fr']);
        $this->assertSame([42.6852, 2.7038], [$c->lat, $c->lng]);
        $this->assertTrue($c->declaredInFrance);
        $this->assertSame(['66108', 'Millas'], [$c->communeInsee, $c->communeName]);
        $this->assertSame(['wikidata' => 6], $c->deaths);
        $this->assertSame(['wikidata' => 14], $c->injuries);
        $this->assertSame('https://example.org/bilan', $c->tollUrls['wikidata']);
        $this->assertSame(['SNCF'], $c->operators);
        $this->assertSame('SNCF Z 7300', $c->trains[0]['material']);
        $this->assertSame('wikidata', $c->trains[0]['source']);
        $this->assertSame('https://fr.wikipedia.org/wiki/Accident_de_Millas', $c->wikipedia['fr']['url']);
        $this->assertArrayHasKey('de', $c->wikipedia);
        $this->assertSame('CC0 1.0', $c->sources[0]->licence);
        $this->assertSame(2520152318, $c->sources[0]->revision);
        $this->assertSame([], $c->notes);
    }

    public function test_ambiguous_toll_is_never_kept_and_is_flagged(): void
    {
        $entity = $this->millas();
        $entity['claims']['P1120'] = [self::amount(52), self::amount(200)];

        $c = $this->reader->read($entity, $this->related());

        $this->assertArrayNotHasKey('wikidata', $c->deaths);
        $this->assertTrue($c->hasNote('toll_ambiguous'));
    }

    public function test_preferred_rank_wins_and_deprecated_is_ignored(): void
    {
        $entity = $this->millas();
        $entity['claims']['P1120'] = [self::amount(9, 'deprecated'), self::amount(52), self::amount(55, 'preferred')];
        $entity['claims']['P585'] = [self::time('+1842-01-01T00:00:00Z', 9), self::time('+1842-05-08T00:00:00Z', 11, 'preferred')];

        $c = $this->reader->read($entity, $this->related());

        $this->assertSame(['wikidata' => 55], $c->deaths);
        $this->assertSame('1842-05-08', $c->date);
        $this->assertFalse($c->hasNote('date_ambiguous'));
    }

    public function test_conflicting_dates_at_the_same_rank_are_flagged(): void
    {
        $entity = $this->millas();
        $entity['claims']['P585'] = [self::time('+1911-11-23T00:00:00Z', 11), self::time('+1911-11-24T00:00:00Z', 11)];

        $this->assertTrue($this->reader->read($entity, $this->related())->hasNote('date_ambiguous'));
    }

    public function test_month_precision_is_kept_and_vague_date_rejected(): void
    {
        $entity = $this->millas();
        $entity['claims']['P585'] = [self::time('+1913-11-00T00:00:00Z', 10)];
        $c = $this->reader->read($entity, $this->related());
        $this->assertSame(['1913-11-01', AccidentDatePrecision::Month], [$c->date, $c->precision]);

        $entity['claims']['P585'] = [self::time('+1910-00-00T00:00:00Z', 8)];
        $this->assertSame('no_date', $this->reader->read($entity, $this->related()));
    }

    public function test_neighbour_country_and_urban_transit_are_flagged(): void
    {
        $entity = $this->millas();
        $entity['claims']['P17'] = [self::item('Q713750')];
        $entity['claims']['P31'] = [self::item('Q7833114')];

        $c = $this->reader->read($entity, $this->related());

        $this->assertFalse($c->declaredInFrance);
        $this->assertTrue($c->declaredElsewhere);
        $this->assertTrue($c->urbanTransit);
    }

    public function test_list_pages_are_not_accidents(): void
    {
        $entity = $this->millas();
        $entity['claims']['P31'] = [self::item('Q13406463')];

        $this->assertSame('not_event', $this->reader->read($entity, $this->related()));
    }

    public function test_cited_place_with_coordinates_locates_an_element_without_its_own(): void
    {
        $entity = [
            'id' => 'Q31836432',
            'labels' => ['de' => ['value' => 'Eisenbahnunfall von Revigny-sur-Ornain']],
            'claims' => [
                'P31' => [self::item('Q1078765')],
                'P17' => [self::item('Q142')],
                'P585' => [self::time('+1946-11-12T00:00:00Z', 11)],
                'P276' => [self::item('Q3097398')],
            ],
        ];
        $related = ['Q3097398' => ['labels' => ['fr' => ['value' => 'gare de Revigny']], 'claims' => [
            'P625' => [self::claim('globecoordinate', ['latitude' => 48.83, 'longitude' => 4.98])],
        ]]];

        $c = $this->reader->read($entity, $related);

        $this->assertSame([48.83, 4.98], [$c->lat, $c->lng]);
        $this->assertSame(AccidentLocationPrecision::Station, $c->locationPrecision);
        $this->assertSame([['Revigny-sur-Ornain', null]], $c->placeHints);
        $this->assertNull($c->kind);
    }

    public function test_places_are_read_from_labels_in_three_languages(): void
    {
        $this->assertSame(['Montreuil-Bellay'], $this->reader->placesFromLabels(['fr' => 'Accident ferroviaire de Montreuil-Bellay le 23 novembre 1911']));
        $this->assertSame(['Melun'], $this->reader->placesFromLabels(['fr' => 'Catastrophe ferroviaire de Melun du 17 octobre 1991']));
        $this->assertSame(['Port-d’Atelier'], $this->reader->placesFromLabels(['de' => 'Eisenbahnunfall von Port-d’Atelier']));
        $this->assertSame(['Müllheim'], $this->reader->placesFromLabels(['en' => '1911 Müllheim rail accident']));
        $this->assertSame([], $this->reader->placesFromLabels([]));
    }
}
