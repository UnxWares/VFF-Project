<?php

namespace Tests\Unit;

use App\Services\StationCorrections;
use App\Services\StationTimeline;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Corrections de l'invariant stations.active_without_track (chantier « gares-sans-voie », 23/11/2026) : positions d'après
 * l'article ou le nœud OSM de la gare, gares aux marchandises seulement, fin des voyageurs de Doullens, dates du raccordement
 * de Boulogne-Maritime. Données seules, sans base : forme, sources et cohérence de chaque correction.
 */
class StationsWithoutTrackCorrectionsTest extends TestCase
{
    /** @return array<string, array<string, mixed>> correction « position » ou « add » de la gare, par nom et action */
    private static function corrections(): array
    {
        /** @var list<array<string, mixed>> $all */
        $all = require dirname(__DIR__, 2).'/database/data/gares_corrections.php';
        $out = [];
        foreach ($all as $c) {
            $mine = in_array($c['station']['name'], ['Grenade', 'Cornebarrieu', 'Hondeghem', 'Porte Maillot', 'Doullens', 'Gimouille', 'La Rapée'], true)
                && ($c['action'] === 'position' || ($c['action'] === 'add' && $c['type'] === 'closure_passenger'));
            if ($mine) {
                $out[$c['station']['name'].' '.$c['action']] = $c;
            }
        }

        return $out;
    }

    /** @return array<string, array{0: string}> */
    public static function stations(): array
    {
        return array_map(fn ($k) => [$k], array_combine(array_keys(self::corrections()), array_keys(self::corrections())));
    }

    #[DataProvider('stations')]
    public function test_each_correction_is_well_formed(string $key): void
    {
        $this->assertSame([], StationCorrections::validate(self::corrections()[$key]));
    }

    public function test_the_seven_stations_are_corrected_once(): void
    {
        $this->assertCount(7, self::corrections());
    }

    public function test_positions_are_in_metropolitan_france_and_sourced(): void
    {
        $positions = array_filter(self::corrections(), fn ($c) => $c['action'] === 'position');
        $this->assertCount(4, $positions);
        foreach ($positions as $key => $c) {
            $this->assertGreaterThan(41.0, $c['lat'], $key);
            $this->assertLessThan(51.5, $c['lat'], $key);
            $this->assertGreaterThan(-5.5, $c['lng'], $key);
            $this->assertLessThan(10.0, $c['lng'], $key);
            $this->assertNotSame('', trim((string) $c['quote']), $key);
            $this->assertMatchesRegularExpression('~^https://(fr\.wikipedia\.org/w/index\.php\?title=.+&oldid=\d+|www\.openstreetmap\.org/node/\d+)$~', $c['source_url'], $key);
        }
    }

    public function test_the_wikipedia_coordinates_replace_the_wikidata_ones_by_hundreds_of_metres(): void
    {
        // Wikidata : Grenade 43,7651 ; Cornebarrieu 43,6475 ; Hondeghem 50,7508 (écarts de 380 à 450 m).
        $wikidata = ['Grenade position' => 43.7651, 'Cornebarrieu position' => 43.6475, 'Hondeghem position' => 50.7508];
        foreach ($wikidata as $key => $lat) {
            $metres = abs(self::corrections()[$key]['lat'] - $lat) * 111_000;
            $this->assertGreaterThan(300, $metres, $key);
            $this->assertLessThan(500, $metres, $key);
        }
    }

    public function test_goods_only_stations_lose_their_passengers_at_the_opening(): void
    {
        foreach (['Gimouille add' => '1868-05-01', 'La Rapée add' => '1862-01-01'] as $key => $date) {
            $c = self::corrections()[$key];
            $this->assertSame('closure_passenger', $c['type'], $key);
            $this->assertSame($date, $c['date'], $key);
        }
    }

    public function test_doullens_ends_its_passengers_in_1944_after_the_second_war_resumption(): void
    {
        $c = self::corrections()['Doullens add'];
        $this->assertSame(['closure_passenger', '1944-01-01', 'year'], [$c['type'], $c['date'], $c['precision']]);
        $this->assertStringContainsString('1944', $c['quote']);
    }

    public function test_boulogne_maritime_link_is_dated_from_its_station_article(): void
    {
        /** @var list<array<string, mixed>> $lines */
        $lines = require dirname(__DIR__, 2).'/database/data/line_event_corrections_gares_sans_voie.php';
        $this->assertSame(['opening', 'closure'], array_column($lines, 'type'));
        $this->assertSame(['1875-01-01', '1995-01-21'], array_column($lines, 'date'));
        foreach ($lines as $l) {
            $this->assertSame('312311', $l['line']);
            $this->assertStringContainsString('Gare_de_Boulogne-Maritime', $l['source_url']);
            $this->assertStringNotContainsString("\u{2014}", $l['reason'].$l['quote']);
        }
    }

    public function test_the_timeline_shows_the_three_stations_as_freight_only_in_the_flagged_eras(): void
    {
        $doullens = [['type' => 'opening', 'date' => '1874-01-01'], ['type' => 'closure_passenger', 'date' => '1944-01-01'], ['type' => 'closure', 'date' => '1970-01-01']];
        $this->assertSame(StationTimeline::ACTIVE, StationTimeline::statusFromEvents($doullens, 1940, false));
        $this->assertSame(StationTimeline::FREIGHT, StationTimeline::statusFromEvents($doullens, 1950, false));
        $this->assertSame(StationTimeline::FREIGHT, StationTimeline::statusFromEvents($doullens, 1960, false));
        $this->assertSame(StationTimeline::CLOSED, StationTimeline::statusFromEvents($doullens, 1970, false));

        $gimouille = [['type' => 'opening', 'date' => '1868-05-01'], ['type' => 'closure_passenger', 'date' => '1868-05-01'], ['type' => 'closure', 'date' => '1980-01-01']];
        foreach ([1870, 1920, 1970] as $year) {
            $this->assertSame(StationTimeline::FREIGHT, StationTimeline::statusFromEvents($gimouille, $year, false), (string) $year);
        }
        $this->assertSame(StationTimeline::CLOSED, StationTimeline::statusFromEvents($gimouille, 1980, false));

        $rapee = [['type' => 'opening', 'date' => '1862-01-01'], ['type' => 'closure_passenger', 'date' => '1862-01-01'], ['type' => 'closure', 'date' => '1993-01-01']];
        foreach ([1870, 1940, 1990] as $year) {
            $this->assertSame(StationTimeline::FREIGHT, StationTimeline::statusFromEvents($rapee, $year, false), (string) $year);
        }
        $this->assertSame(StationTimeline::CLOSED, StationTimeline::statusFromEvents($rapee, 2000, false));
    }
}
