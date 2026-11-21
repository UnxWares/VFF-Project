<?php

namespace Tests\Unit\StationAudit;

use App\Services\StationAudit\BdtopoStationZones;
use App\Services\StationAudit\WikidataStationDates;
use PHPUnit\Framework\TestCase;

class WikidataStationDatesTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function statement(string $id, string $time, int $precision, string $rank = 'normal', array $qualifiers = [], array $references = []): array
    {
        return [
            'id' => $id, 'rank' => $rank,
            'mainsnak' => ['datavalue' => ['value' => ['time' => $time, 'precision' => $precision]]],
            'qualifiers' => $qualifiers,
            'references' => $references,
        ];
    }

    public function test_parse_keeps_precision_qualifiers_and_references(): void
    {
        $claims = [
            'P1619' => [self::statement('Q1$a', '+1866-05-21T00:00:00Z', 11, references: [
                ['snaks' => ['P143' => [['datavalue' => ['value' => ['id' => 'Q8447']]]]]],
            ])],
            'P3999' => [
                self::statement('Q1$b', '+1938-00-00T00:00:00Z', 9, qualifiers: [
                    'P518' => [['datavalue' => ['value' => ['id' => 'Q1433891']]]],
                ]),
                self::statement('Q1$c', '+1990-00-00T00:00:00Z', 9, 'deprecated'),
                self::statement('Q1$d', '+1950-00-00T00:00:00Z', 8, qualifiers: [
                    'P518' => [['datavalue' => ['value' => ['id' => 'Q2234623']]]],
                    'P1326' => [['datavalue' => ['value' => ['time' => '+1959-00-00T00:00:00Z', 'precision' => 9]]]],
                ]),
            ],
        ];
        $c = WikidataStationDates::parse('Q1', $claims);
        $this->assertCount(3, $c);
        $this->assertSame(['opening', '1866-05-21', 'day'], [$c[0]['kind'], $c[0]['date'], $c[0]['precision']]);
        $this->assertSame([['imported_from' => 'Q8447']], $c[0]['references']);
        $this->assertSame('https://www.wikidata.org/wiki/Q1#P1619', $c[0]['source_url']);
        $this->assertSame(['closure_passenger', '1938-01-01', 'year'], [$c[1]['kind'], $c[1]['date'], $c[1]['precision']]);
        $this->assertSame(['closure_freight', '1950-01-01', 'decade'], [$c[2]['kind'], $c[2]['date'], $c[2]['precision']]);
        $this->assertSame('1959-01-01', $c[2]['qualifiers']['latest']);
    }

    public function test_time_rejects_coarse_or_impossible_dates(): void
    {
        $this->assertNull(WikidataStationDates::time(['time' => '+1866-00-00T00:00:00Z', 'precision' => 6]));
        $this->assertNull(WikidataStationDates::time(['time' => '+1866-02-31T00:00:00Z', 'precision' => 11]));
        $this->assertNull(WikidataStationDates::time(['time' => '+1500-01-01T00:00:00Z', 'precision' => 9]));
        $this->assertSame(['1860-01-01', 'decade'], WikidataStationDates::time(['time' => '+1866-00-00T00:00:00Z', 'precision' => 8]));
        $this->assertSame(['1866-03-01', 'month'], WikidataStationDates::time(['time' => '+1866-03-00T00:00:00Z', 'precision' => 10]));
    }

    public function test_bdtopo_keeps_railway_stations_only(): void
    {
        $f = fn (string $nature, ?string $name) => ['properties' => ['cleabs' => 'EQ1', 'nature' => $nature, 'toponyme' => $name], 'geometry' => ['type' => 'MultiPolygon', 'coordinates' => []]];
        $this->assertNotNull(BdtopoStationZones::zone($f('Gare voyageurs et fret', "Gare d'Argentan")));
        $this->assertNotNull(BdtopoStationZones::zone($f('Arrêt voyageurs', 'Halte Ferroviaire de Lison')));
        $this->assertNull(BdtopoStationZones::zone($f('Arrêt voyageurs', "Arrêt d'Écouché")));
        $this->assertNull(BdtopoStationZones::zone($f('Gare routière', 'Gare Routière de Caen')));
        $this->assertNull(BdtopoStationZones::zone($f('Gare maritime', null)));
        $this->assertSame([[-1, 48], [0, 48]], BdtopoStationZones::tiles([[-0.5, 48.7], [0.2, 48.1], [-0.9, 48.2]]));
    }
}
