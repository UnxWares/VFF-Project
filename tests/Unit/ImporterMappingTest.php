<?php

namespace Tests\Unit;

use App\Console\Commands\ImportOsmGreenways;
use App\Console\Commands\ImportOsmRailways;
use App\Console\Commands\LinkTraces;
use App\Enums\SegmentStatus;
use App\Enums\StationType;
use App\Enums\TrafficType;
use App\Services\LineMerger;
use App\Services\OsmImporter;
use App\Services\SncfOpenDataImporter;
use Illuminate\Http\Client\Factory as HttpFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Lecture des sources à l'import (fonctions pures) : tags OSM (statut,
 * calque, type de gare, tension, vitesse, ref), statuts et PK SNCF, années
 * des voies vertes, titres d'articles Wikipédia.
 */
class ImporterMappingTest extends TestCase
{
    private function osm(): object
    {
        return new class(new HttpFactory, 'http://overpass.invalid', 'VFF-Test') extends OsmImporter
        {
            public function call(string $method, mixed ...$args): mixed
            {
                return $this->{$method}(...$args);
            }
        };
    }

    private function sncf(): object
    {
        return new class(new HttpFactory) extends SncfOpenDataImporter
        {
            public function call(string $method, mixed ...$args): mixed
            {
                return $this->{$method}(...$args);
            }
        };
    }

    // ─── OSM ─────────────────────────────────────────────────────────────

    /** @return array<string, array{0: string, 1: SegmentStatus}> */
    public static function osmStatusProvider(): array
    {
        return [
            'rail' => ['rail', SegmentStatus::InService],
            'construction' => ['construction', SegmentStatus::Construction],
            'abandoned (rails souvent en place)' => ['abandoned', SegmentStatus::Abandoned],
            'disused' => ['disused', SegmentStatus::Abandoned],
            'razed' => ['razed', SegmentStatus::Razed],
            'dismantled' => ['dismantled', SegmentStatus::Dismantled],
            'preserved (voie touristique en service)' => ['preserved', SegmentStatus::InService],
        ];
    }

    #[DataProvider('osmStatusProvider')]
    public function test_osm_railway_tag_to_segment_status(string $tag, SegmentStatus $expected): void
    {
        $this->assertSame($expected, $this->osm()->call('mapStatus', $tag));
    }

    public function test_osm_station_types(): void
    {
        $osm = $this->osm();

        $this->assertSame(StationType::Gare, $osm->call('mapStationType', 'station'));
        $this->assertSame(StationType::Halte, $osm->call('mapStationType', 'halt'));
        $this->assertSame(StationType::PointArret, $osm->call('mapStationType', 'stop'));
        $this->assertSame(StationType::Embranchement, $osm->call('mapStationType', 'service_station'));
        $this->assertSame(StationType::Gare, $osm->call('mapStationType', 'tram_stop'), 'repli : gare');
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function refProvider(): array
    {
        return [
            'espaces retirés' => ['420 000', '420000'],
            'zéros de tête retirés (006000 = 6000)' => ['006000', '6000'],
            'que des zéros' => ['000', '0'],
            'alphanumérique en majuscules' => ['l2c', 'L2C'],
            'zéros gardés dans une ref alphanumérique' => ['0A1', '0A1'],
        ];
    }

    #[DataProvider('refProvider')]
    public function test_osm_ref_and_sncf_code_are_normalized_the_same_way(string $raw, string $expected): void
    {
        $this->assertSame($expected, $this->osm()->call('normalizeRef', $raw));
        $this->assertSame($expected, $this->sncf()->call('normalizeCode', $raw));
    }

    public function test_osm_line_name_replaces_only_an_anonymous_name(): void
    {
        $osm = $this->osm();

        $this->assertTrue($osm->call('isBetterName', 'Ligne de Paris à Brest', null));
        $this->assertTrue($osm->call('isBetterName', 'Ligne de Paris à Brest', '420 000'));
        $this->assertFalse($osm->call('isBetterName', 'Paris - Brest', '420000'), 'pas un nom « Ligne … »');
        $this->assertFalse($osm->call('isBetterName', 'Ligne de Paris à Brest', 'Ligne de Paris-Montparnasse à Brest'));
    }

    public function test_osm_voltage_and_speed_outside_plausible_range_are_dropped(): void
    {
        $osm = $this->osm();

        $this->assertSame(1500, $osm->call('sanitizeVoltage', '1500'));
        $this->assertSame(25000, $osm->call('sanitizeVoltage', '25000'));
        $this->assertSame(15000, $osm->call('sanitizeVoltage', '15000;25000'), 'bi-tension : la première');
        $this->assertNull($osm->call('sanitizeVoltage', '0'));
        $this->assertNull($osm->call('sanitizeVoltage', '60000'));
        $this->assertNull($osm->call('sanitizeVoltage', null));

        $this->assertSame(160, $osm->call('sanitizeSpeed', '160'));
        $this->assertSame(320, $osm->call('sanitizeSpeed', '320'));
        $this->assertNull($osm->call('sanitizeSpeed', '700'));
        $this->assertNull($osm->call('sanitizeSpeed', 'FR:urban'));
        $this->assertNull($osm->call('sanitizeSpeed', null));
    }

    /** @return array<string, array{0: array<string, string>, 1: ?string}> */
    public static function layerProvider(): array
    {
        return [
            'voie en service' => [['railway' => 'rail'], 'rail'],
            'voie étroite' => [['railway' => 'narrow_gauge'], 'narrow_gauge'],
            'voie abandonnée' => [['railway' => 'abandoned'], 'abandoned'],
            'voie conservée (touristique)' => [['railway' => 'preserved'], 'preserved'],
            'gare : pas une voie' => [['railway' => 'station'], null],
            'quai : pas une voie' => [['railway' => 'platform'], null],
            'métro exclu' => [['railway' => 'subway'], null],
            'tram actif exclu' => [['railway' => 'tram'], null],
            'préfixe de cycle de vie' => [['disused:railway' => 'rail'], 'disused:railway'],
            'ancien tram (préfixe)' => [['abandoned:railway' => 'tram'], 'abandoned:railway'],
            'ancienne gare (préfixe) : pas une voie' => [['was:railway' => 'station'], null],
            'historic=railway' => [['historic' => 'railway'], 'historic'],
            'railway prioritaire sur un préfixe' => [['railway' => 'abandoned', 'disused:railway' => 'rail'], 'abandoned'],
            'aucun tag ferroviaire' => [['highway' => 'cycleway'], null],
        ];
    }

    /** @param  array<string, string>  $tags */
    #[DataProvider('layerProvider')]
    public function test_osm_way_layer(array $tags, ?string $expected): void
    {
        $this->assertSame($expected, ImportOsmRailways::layer($tags));
        $this->assertSame($expected !== null, ImportOsmGreenways::isRailway($tags), 'voies vertes : même sélection');
    }

    public function test_greenway_query_selects_the_same_railway_ways_as_the_railway_import(): void
    {
        $railwayLines = fn (string $q) => array_values(array_filter(
            array_map('trim', explode("\n", $q)),
            fn ($l) => str_starts_with($l, 'way["railway"') || str_starts_with($l, 'way[~') || str_starts_with($l, 'way["historic"'),
        ));

        $railways = $railwayLines(ImportOsmRailways::query('FR-BRE'));
        $this->assertCount(3, $railways);
        $this->assertSame($railways, $railwayLines(ImportOsmGreenways::query('FR-BRE')));
    }

    public function test_overpass_queries_target_the_requested_region(): void
    {
        foreach ([ImportOsmRailways::query('FR-29'), ImportOsmGreenways::query('FR-29')] as $q) {
            $this->assertStringContainsString('area["ISO3166-2"="FR-29"]', $q);
            $this->assertStringContainsString('[out:json]', $q);
        }
        $this->assertStringContainsString('way["highway"="cycleway"]', ImportOsmGreenways::query('FR-29'));
    }

    /** @return array<string, array{0: ?string, 1: ?int}> */
    public static function yearProvider(): array
    {
        return [
            'année seule' => ['2012', 2012],
            'date ISO' => ['2012-06-30', 2012],
            'date française' => ['30/06/1995', 1995],
            'approximative' => ['~1995', 1995],
            'intervalle : première année' => ['2010..2012', 2010],
            'décennie' => ['1880s', 1880],
            'année future' => ['2099', null],
            'avant 1800' => ['1799', null],
            'texte' => ['unknown', null],
            'vide' => ['', null],
            'null' => [null, null],
        ];
    }

    #[DataProvider('yearProvider')]
    public function test_greenway_start_year(?string $date, ?int $expected): void
    {
        $this->assertSame($expected, ImportOsmGreenways::year($date));
    }

    // ─── SNCF (lignes-par-statut) ───────────────────────────────────────

    /** @return array<string, array{0: string, 1: TrafficType}> */
    public static function sncfTrafficProvider(): array
    {
        return [
            'Exploitée' => ['Exploitée', TrafficType::Mixed],
            // R1 : voyageurs ⇒ mixte, « voyageurs seuls » réservé aux LGV (vff:classify-lines).
            'Exploitée Voyageurs' => ['Exploitée Voyageurs', TrafficType::Mixed],
            'Exploitée VU Voyageurs' => ['Exploitée VU Voyageurs', TrafficType::Mixed],
            'Exploitée Fret seul' => ['Exploitée Fret seul', TrafficType::Freight],
            'Exploitée VU Fret seul' => ['Exploitée VU Fret seul', TrafficType::Freight],
            'Fermée' => ['Fermée', TrafficType::Unknown],
            'Neutralisée' => ['Neutralisée', TrafficType::Unknown],
            'Transférée en voie de service' => ['Transférée en voie de service', TrafficType::Unknown],
        ];
    }

    #[DataProvider('sncfTrafficProvider')]
    public function test_sncf_status_to_traffic(string $statut, TrafficType $expected): void
    {
        $this->assertSame($expected, $this->sncf()->call('mapTrafficType', $statut));
    }

    public function test_sncf_status_mapping_is_the_shared_one(): void
    {
        $sncf = $this->sncf();

        foreach (['Exploitée', 'Fermée et déposée (Plus utilisable)', 'Neutralisée', 'Transférée en voie de service'] as $statut) {
            $this->assertSame(SegmentStatus::fromSncfLibelle($statut), $sncf->call('mapStatus', $statut), $statut);
        }
        $this->assertTrue($sncf->call('isExploitee', 'Exploitée VU Fret seul'));
        $this->assertFalse($sncf->call('isExploitee', 'Fermée'));
    }

    /** @return array<string, array{0: ?string, 1: ?int}> */
    public static function voltageProvider(): array
    {
        return [
            'volts' => ['1500', 1500],
            'volts avec unité' => ['25000 V', 25000],
            'kilovolts' => ['25 kV', 25000],
            'kilovolts décimaux' => ['1,5 kV', 1500],
            'kilovolts collés' => ['15kV', 15000],
            'nulle' => ['0', null],
            'aberrante' => ['99999', null],
            'vide' => ['', null],
            'null' => [null, null],
        ];
    }

    #[DataProvider('voltageProvider')]
    public function test_sncf_voltage(?string $raw, ?int $expected): void
    {
        $this->assertSame($expected, $this->sncf()->call('parseVoltage', $raw));
    }

    /** @return array<string, array{0: ?string, 1: ?int}> */
    public static function pkProvider(): array
    {
        return [
            'km + m' => ['123+450', 123_450],
            'origine' => [' 0+000 ', 0],
            'sans +' => ['12', null],
            'm non numériques' => ['12+abc', null],
            'PK négatif' => ['-1+200', null],
            'vide' => ['', null],
            'null' => [null, null],
        ];
    }

    #[DataProvider('pkProvider')]
    public function test_sncf_pk(?string $raw, ?int $expected): void
    {
        $this->assertSame($expected, $this->sncf()->call('parsePk', $raw));
    }

    public function test_sncf_geometry_is_split_into_linestrings(): void
    {
        $sncf = $this->sncf();
        $a = [[2.0, 48.0], [2.1, 48.1]];
        $b = [[3.0, 49.0], [3.1, 49.1]];

        $this->assertSame([$a], $sncf->call('extractLineStrings', ['type' => 'LineString', 'coordinates' => $a]));
        $this->assertSame([$a, $b], $sncf->call('extractLineStrings', ['type' => 'MultiLineString', 'coordinates' => [$a, $b]]));
        $this->assertSame([], $sncf->call('extractLineStrings', ['type' => 'Point', 'coordinates' => [2.0, 48.0]]));
        $this->assertSame([], $sncf->call('extractLineStrings', []));
    }

    // ─── Wikipédia ───────────────────────────────────────────────────────

    public function test_wikipedia_title_of_a_permalink(): void
    {
        $this->assertSame('Ligne d\'Auray à Pontivy',
            LineMerger::wikipediaTitle('https://fr.wikipedia.org/w/index.php?title=Ligne_d%27Auray_%C3%A0_Pontivy&oldid=123'));
        $this->assertNull(LineMerger::wikipediaTitle(''));
        $this->assertNull(LineMerger::wikipediaTitle(null));
    }

    /** @return array<string, array{0: string, 1: ?string}> */
    public static function wikipediaUrlProvider(): array
    {
        return [
            'URL /wiki/' => ['https://fr.wikipedia.org/wiki/Ligne_de_Paris-Montparnasse_%C3%A0_Brest', 'Ligne de Paris-Montparnasse à Brest'],
            'ancre retirée' => ['https://fr.wikipedia.org/wiki/Gare_de_Brest#Histoire', 'Gare de Brest'],
            'paramètres retirés' => ['https://fr.wikipedia.org/wiki/Gare_de_Brest?action=history', 'Gare de Brest'],
            'autre site' => ['https://example.org/page', null],
        ];
    }

    /**
     * Régression : le motif « #/wiki/([^?#]+)# » utilisait « # » comme délimiteur ET
     * dans la classe de caractères (preg_match en échec, aucune URL /wiki/ ne donnait
     * de titre, et MergeSplitLines ratait des fusions). Corrigé avec le délimiteur « ~ ».
     */
    #[DataProvider('wikipediaUrlProvider')]
    public function test_wikipedia_title_of_a_wiki_url(string $url, ?string $expected): void
    {
        $warning = null;
        set_error_handler(function (int $no, string $msg) use (&$warning) {
            $warning = $msg;

            return true;
        });
        try {
            $title = LineMerger::wikipediaTitle($url);
        } finally {
            restore_error_handler();
        }

        $this->assertNull($warning, 'motif invalide : '.$warning);
        $this->assertSame($expected, $title);
    }

    public function test_trace_name_loses_the_export_suffix_of_the_source_map(): void
    {
        $this->assertSame('Le Cheylard Vals', LinkTraces::cleanSourceName('Le Cheylard Vals_ivPw8'));
        $this->assertSame('Ligne de Morez à Saint-Claude', LinkTraces::cleanSourceName('Ligne de Morez à Saint-Claude'));
        $this->assertSame('Mil 14-18 Beaurieux', LinkTraces::cleanSourceName('Mil 14-18 Beaurieux'));
        $this->assertNull(LinkTraces::cleanSourceName(null));
    }
}
