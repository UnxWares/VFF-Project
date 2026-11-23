<?php

namespace Tests\Unit\Heritage;

use App\Services\Heritage\MerimeeSource;
use App\Services\Heritage\StationHeritageBuilder;
use PHPUnit\Framework\TestCase;

/** Rattachement des notices Mérimée et des éléments Wikidata aux gares, et composition des lignes. */
class StationHeritageBuilderTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../../Fixtures/Heritage/';

    /** @return list<object> */
    private static function stations(): array
    {
        $s = fn (int $id, string $slug, string $name, string $city, string $dep, string $status, ?string $qid, float $lat, float $lng): object => (object) [
            'id' => $id, 'slug' => $slug, 'name' => $name, 'city' => $city, 'department_code' => $dep, 'status' => $status, 'wikidata_id' => $qid, 'lat' => $lat, 'lng' => $lng];

        return [
            $s(1, 'alpha', 'Alpha', 'Alpha', '75', 'active', 'Q1001', 48.8000, 2.3000),
            $s(2, 'bravo-ville', 'Bravo-Ville', 'Bravo', '13', 'active', null, 43.3005, 5.4005),
            $s(3, 'bravo-nord', 'Bravo-Nord', 'Bravo', '13', 'active', null, 43.3500, 5.4500),
            $s(4, 'charlie', 'Charlie', 'Charlie', '33', 'declassified', null, 44.8010, -0.5600),
            $s(5, 'delta', 'Delta', 'Delta', '69', 'active', null, 45.7500, 4.8500),
            $s(6, 'sans-patrimoine', 'Sans patrimoine', 'Ailleurs', '11', 'active', 'Q1009', 44.0, 4.0),
        ];
    }

    /** @return array{rows: list<array<string, mixed>>, unmatched: list<array<string, mixed>>, methods: array<string, int>} */
    private static function build(): array
    {
        $records = (new MerimeeSource)->railwayRecords(self::FIXTURES.'merimee.csv');
        $wikidata = json_decode((string) file_get_contents(self::FIXTURES.'wikidata.json'), true)['items'];

        return (new StationHeritageBuilder(self::stations()))->build($records, $wikidata, '2026-11-19');
    }

    /** @return array<string, array<string, mixed>> lignes par référence */
    private static function rows(): array
    {
        return array_column(self::build()['rows'], null, 'ref');
    }

    public function test_wikidata_reference_gives_the_station_and_the_merimee_details(): void
    {
        $alpha = self::rows()['PA00000001'];

        $this->assertSame(1, $alpha['station_id']);
        $this->assertSame('wikidata', $alpha['match_method']);
        $this->assertSame('merimee', $alpha['source']);
        $this->assertSame('building', $alpha['element']);
        $this->assertSame(['registered', '1984-12-28', 'partial'], [$alpha['protection'], $alpha['protection_date'], $alpha['scope']]);
        // Nom de Wikidata préféré à « Laloux Victor » réordonné : mêmes mots.
        $this->assertSame([['name' => 'Victor Laloux', 'role' => 'architecte'], ['name' => 'Jean Dupont', 'role' => 'sculpteur']], $alpha['architects']);
        $this->assertSame([1896, 1898], [$alpha['built_from'], $alpha['built_to']]);
        $this->assertSame('architecture néo-classique', $alpha['style']);
        $this->assertSame('Gare Alpha.jpg', $alpha['image_file']);
        $this->assertSame('PA', $alpha['ref_kind']);
        $this->assertSame('https://www.pop.culture.gouv.fr/notice/merimee/PA00000001', $alpha['ref_url']);
        $this->assertSame('2026-11-19', $alpha['source_updated']);
    }

    public function test_a_notice_without_wikidata_is_found_by_its_name_in_its_commune(): void
    {
        $bravo = self::rows()['PA00000002'];

        $this->assertSame(2, $bravo['station_id']);
        $this->assertSame('name-commune', $bravo['match_method']);
        $this->assertSame('classified', $bravo['protection']);
        $this->assertNull($bravo['scope']);
        $this->assertNull($bravo['style']);
    }

    public function test_a_works_notice_is_found_by_proximity_and_has_no_image_or_style(): void
    {
        $charlie = self::rows()['PA00000003'];

        $this->assertSame(4, $charlie['station_id']);
        $this->assertSame('proximity', $charlie['match_method']);
        $this->assertSame('works', $charlie['element']);
        $this->assertNull($charlie['image_url']);
    }

    public function test_a_label_without_protection_is_kept_as_acr(): void
    {
        $delta = self::rows()['PA00000004'];

        $this->assertSame(5, $delta['station_id']);
        $this->assertSame(['acr', '2018-05-04'], [$delta['protection'], $delta['protection_date']]);
    }

    public function test_a_wikidata_only_inventory_entry_uses_the_inventory_reference(): void
    {
        $row = self::rows()['IA00000009'];

        $this->assertSame(6, $row['station_id']);
        $this->assertSame(['inventory', 'wikidata', 'IA'], [$row['protection'], $row['source'], $row['ref_kind']]);
        $this->assertNull($row['source_updated']);
    }

    public function test_a_notice_without_a_station_is_reported_not_written(): void
    {
        $result = self::build();

        $this->assertSame(['PA00000007'], array_column($result['unmatched'], 'ref'));
        $this->assertSame('unmatched', $result['unmatched'][0]['why']);
        $this->assertNotContains('PA00000007', array_column($result['rows'], 'ref'));
    }

    public function test_two_stations_of_a_commune_far_from_the_notice_are_not_guessed(): void
    {
        $builder = new StationHeritageBuilder(self::stations());
        $record = ['ref' => 'PA9', 'title' => 'Gare', 'denomination' => 'gare', 'element' => 'building', 'commune' => 'Bravo', 'lat' => 43.3250, 'lng' => 5.4250];

        // Ni nom, ni station à moins de 250 m : rien, même si la commune n'a que deux gares.
        $this->assertNull($builder->match($record)['station']);
    }

    public function test_a_notice_without_coordinates_goes_to_the_only_open_station_of_the_commune(): void
    {
        $builder = new StationHeritageBuilder(self::stations());
        $record = ['ref' => 'PA9', 'title' => 'Gare', 'denomination' => 'gare', 'element' => 'building', 'commune' => 'Delta', 'lat' => null, 'lng' => null];

        $this->assertSame(['delta', 'commune-unique'], [$builder->match($record)['station']->slug, $builder->match($record)['method']]);
        $record['commune'] = 'Bravo';
        $this->assertSame('unmatched', $builder->match($record)['method']);
    }

    public function test_every_row_has_the_columns_of_the_table(): void
    {
        $columns = ['station_id', 'element', 'protection', 'protection_date', 'scope', 'designations', 'title', 'denomination', 'architects', 'built_from', 'built_to', 'built_period',
            'style', 'ref', 'ref_kind', 'ref_url', 'wikidata_id', 'image_file', 'image_url', 'image_source_url', 'image_license', 'image_author', 'source', 'match_method',
            'match_distance_m', 'source_updated'];
        foreach (self::build()['rows'] as $row) {
            $this->assertEqualsCanonicalizing($columns, array_keys($row));
        }
    }

    /** @return array<string, mixed> */
    private static function works(string $title, string $commune, ?float $lat, ?float $lng, string $element = 'works'): array
    {
        return ['ref' => 'PA99999999', 'title' => $title, 'denomination' => '', 'element' => $element, 'commune' => $commune, 'department' => '00', 'lat' => $lat, 'lng' => $lng];
    }

    public function test_a_depot_works_far_from_the_station_is_attached_by_points_when_the_commune_has_one_open_station(): void
    {
        $builder = new StationHeritageBuilder(self::stations());

        // Delta : une seule gare ouverte dans la commune, rotonde à 700 m (30 + 20 + 10 + 15 = 75).
        $found = $builder->rescue(self::works('Rotonde SNCF', 'Delta', 45.7562, 4.8500));

        $this->assertSame('delta', $found['station']->slug);
        $this->assertSame(75, $found['score']);
        $this->assertContains('seule gare ouverte de la commune +15', $found['reasons']);
    }

    public function test_the_gare_saint_name_without_de_is_found_even_when_stations_write_st(): void
    {
        $stations = [...self::stations(), (object) ['id' => 7, 'slug' => 'bordeaux-st-jean', 'name' => 'Bordeaux-St-Jean', 'city' => 'Bordeaux', 'department_code' => '33', 'status' => 'active', 'wikidata_id' => null, 'lat' => 44.826, 'lng' => -0.556]];
        $found = (new StationHeritageBuilder($stations))->rescue(self::works('Ancien château d\'eau de la gare Saint-Jean', 'Bordeaux', 44.8211, -0.5594));

        $this->assertSame('bordeaux-st-jean', $found['station']->slug);
        $this->assertContains('nom cité +40', $found['reasons']);
    }

    public function test_two_stations_with_close_scores_leave_the_notice_unattached(): void
    {
        // Bravo-Ville (80 m) et Bravo-Nord (6 km) : l'écart de points suffit ; deux gares à 600 m et 650 m, non.
        $stations = [
            (object) ['id' => 1, 'slug' => 'a', 'name' => 'Aa', 'city' => 'Zeta', 'department_code' => '00', 'status' => 'active', 'wikidata_id' => null, 'lat' => 45.0, 'lng' => 4.0],
            (object) ['id' => 2, 'slug' => 'b', 'name' => 'Bb', 'city' => 'Zeta', 'department_code' => '00', 'status' => 'active', 'wikidata_id' => null, 'lat' => 45.0, 'lng' => 4.0009],
        ];

        $this->assertNull((new StationHeritageBuilder($stations))->rescue(self::works('Rotonde SNCF', 'Zeta', 45.0, 4.0004)));
    }

    public function test_a_station_building_needs_more_points_than_a_depot_works(): void
    {
        // Gare départementale à 800 m de la seule gare ouverte de la commune : 75 points, 85 exigés pour un bâtiment voyageurs.
        $builder = new StationHeritageBuilder(self::stations());

        $this->assertNull($builder->rescue(self::works('Ancienne gare départementale', 'Delta', 45.7572, 4.8500, 'building')));
        $this->assertNotNull($builder->rescue(self::works('Ancienne gare départementale', 'Delta', 45.7572, 4.8500, 'works')));
    }

    public function test_a_notice_without_coordinates_and_without_the_name_stays_unattached(): void
    {
        $this->assertNull((new StationHeritageBuilder(self::stations()))->rescue(self::works('Rotonde SNCF', 'Ailleurs', null, null)));
    }
}
