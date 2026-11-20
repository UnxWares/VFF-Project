<?php

namespace Tests\Unit\Archives;

use App\Services\Archives\ArchiveDate;
use App\Services\Archives\ArchiveMatch;
use App\Services\Archives\ArchiveMatcher;
use App\Services\Archives\ArchiveRecord;
use App\Services\Archives\CatalogueSruParser;
use App\Services\Archives\LinePlaceIndex;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Rattachement d'un document de Gallica à une ligne : score explicable, conditions obligatoires, seuil
 * et titres ambigus. Lignes construites sans base (LinePlaceIndex::linePlaces), notices réelles
 * (tests/Fixtures/Bnf) ou écrites pour le cas.
 */
class ArchiveMatcherTest extends TestCase
{
    private LinePlaceIndex $index;

    protected function setUp(): void
    {
        parent::setUp();
        $this->index = new LinePlaceIndex([
            1 => LinePlaceIndex::linePlaces([
                'id' => 1, 'slug' => 'rfn-340000', 'name' => 'Ligne de Paris-St-Lazare au Havre',
                'stations' => [
                    ['name' => 'Paris-St-Lazare', 'city' => 'Paris', 'pk_m' => 0],
                    ['name' => 'Poissy', 'city' => 'Poissy', 'pk_m' => 26000],
                    ['name' => 'Rouen-Rive-Droite', 'city' => 'Rouen', 'pk_m' => 140000],
                    ['name' => 'Le Havre', 'city' => 'Le Havre', 'pk_m' => 228000],
                ],
                'first_year' => 1840, 'open_today' => true,
            ]),
            2 => LinePlaceIndex::linePlaces([
                'id' => 2, 'slug' => 'rfn-610000', 'name' => 'Ligne de Limoges-Bénédictins à Angoulême',
                'stations' => [
                    ['name' => 'Limoges-Bénédictins', 'city' => 'Limoges', 'pk_m' => 400000],
                    ['name' => 'Saillat-Chassenon', 'city' => 'Saillat-sur-Vienne', 'pk_m' => 446000],
                    ['name' => 'Angoulême', 'city' => 'Angoulême', 'pk_m' => 518000],
                ],
                'first_year' => 1875, 'open_today' => false, 'last_year' => 2018,
            ]),
            // Une ligne plus récente qui passe aussi par Paris et Rouen.
            3 => LinePlaceIndex::linePlaces([
                'id' => 3, 'slug' => 'rfn-335000', 'name' => 'Ligne de Paris à Rouen par Gisors',
                'stations' => [['name' => 'Paris', 'city' => 'Paris', 'pk_m' => 0], ['name' => 'Gisors', 'city' => 'Gisors', 'pk_m' => 70000],
                    ['name' => 'Rouen', 'city' => 'Rouen', 'pk_m' => 130000]],
                'first_year' => 1868, 'open_today' => true,
            ]),
        ]);
    }

    private function record(string $title, ?int $year, ?string $type = 'monograph'): ArchiveRecord
    {
        return new ArchiveRecord('ark:/12148/btv1b'.substr(md5($title), 0, 9), null, $title, $title, $type, [],
            $year === null ? ArchiveDate::unknown() : new ArchiveDate((string) $year, $year, null, null, 'year'), null, null);
    }

    /** @return array<string, ArchiveMatch> par slug de ligne */
    private function judge(ArchiveRecord $record): array
    {
        $out = [];
        foreach ((new ArchiveMatcher)->candidates($record, $this->index) as $m) {
            $out[$this->index->line($m->lineId)->slug ?? '?'] = $m;
        }

        return $out;
    }

    /** @return list<string> */
    private static function codes(ArchiveMatch $m): array
    {
        return array_column($m->reasons, 'code');
    }

    public function test_the_angouleme_limoges_brochure_is_a_sure_match_with_its_reasons(): void
    {
        $record = (new CatalogueSruParser)->parse((string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/Bnf/sru-angouleme-limoges.xml'))['records'][0];
        $m = $this->judge($record)['rfn-610000'];

        $this->assertNull($m->rejected);
        $this->assertSame(['rail_route', 'ends', 'rail_word', 'date_project', 'doc_type'], self::codes($m));
        $this->assertSame(10, $m->score);
        $this->assertSame(['Limoges', 'Angoulême'], $m->places);
        $this->assertTrue($m->passes(ArchiveMatcher::THRESHOLD));
    }

    public function test_a_route_between_two_stations_goes_to_the_line_that_existed_at_that_date(): void
    {
        // « de Paris à Rouen » en 1843 : la ligne du Havre (concédée en 1840) l'emporte sur celle de Gisors (1868).
        $judged = $this->judge($this->record('Chemin de fer de Paris à Rouen', 1843, 'map'));

        $this->assertSame(9, $judged['rfn-340000']->score);
        $this->assertContains('date_in_life', self::codes($judged['rfn-340000']));
        // Pour Gisors, 1843 est un projet (25 ans avant) : meilleur score grâce aux extrémités, mais la ligne
        // qui existait à cette date passe avant.
        $this->assertSame(['rail_route', 'ends', 'rail_word', 'date_project', 'doc_type'], self::codes($judged['rfn-335000']));
        $this->assertSame(10, $judged['rfn-335000']->score);

        $winners = (new ArchiveMatcher)->winners(array_values($judged));
        $this->assertSame([1], array_map(fn (ArchiveMatch $m) => $m->lineId, $winners));
    }

    public function test_the_line_whose_places_are_all_named_wins_over_a_line_with_fewer(): void
    {
        $judged = $this->judge($this->record('Projet de chemin de fer de Paris à Rouen par Gisors', 1862));
        $winners = (new ArchiveMatcher)->winners(array_values($judged));

        $this->assertSame(['rfn-335000'], array_map(fn (ArchiveMatch $m) => $this->index->line($m->lineId)?->slug, $winners));
    }

    /** @return iterable<string, array{string, ?int, string}> */
    public static function rejections(): iterable
    {
        yield 'pas de mot du rail' => ['De Paris au Havre : guide itinéraire', 1855, 'no_rail_word'];
        yield 'autre transport' => ['Guide des bateaux à vapeur de Paris au Havre, avec les gares', 1841, 'other_transport'];
        yield 'avant tout projet' => ['Chemin de fer de Paris au Havre', 1790, 'date_incompatible'];
        yield 'type non retenu' => ['Chemin de fer de Paris au Havre', 1850, 'unsupported_type'];
    }

    #[DataProvider('rejections')]
    public function test_mandatory_conditions_reject_the_document_with_their_reason(string $title, ?int $year, string $reason): void
    {
        $m = $this->judge($this->record($title, $year, $reason === 'unsupported_type' ? null : 'monograph'))['rfn-340000'];

        $this->assertSame($reason, $m->rejected);
        $this->assertFalse($m->passes(1));
    }

    public function test_a_rail_route_overrides_another_transport_named_in_the_title(): void
    {
        $m = $this->judge($this->record('Chemin de fer de Paris au Havre et bateaux à vapeur', 1847))['rfn-340000'];

        $this->assertNull($m->rejected);
        $this->assertSame(11, $m->score);
    }

    public function test_two_places_without_a_route_pass_only_when_they_are_the_ends_and_the_date_fits(): void
    {
        $ends = $this->judge($this->record('Tours, catastrophe de chemin de fer, rapide de Paris, près du Havre', 1926, 'image'))['rfn-340000'];
        $this->assertSame(['two_places', 'ends', 'rail_word', 'date_in_life', 'doc_type'], self::codes($ends));
        $this->assertSame(9, $ends->score);

        $middle = $this->judge($this->record('Gares de Poissy et de Rouen', 1926, 'image'))['rfn-340000'];
        $this->assertSame(['two_places', 'rail_hint', 'date_in_life', 'doc_type'], self::codes($middle));
        $this->assertFalse($middle->passes(ArchiveMatcher::THRESHOLD));
    }

    public function test_a_place_list_is_not_a_route(): void
    {
        // « de Paris au Havre, de Bordeaux… » : Paris et Rouen ne se suivent pas, pas de trajet Paris - Rouen.
        $m = $this->judge($this->record('Chemins de fer de Paris à Orléans, de Rouen à Dieppe', 1840))['rfn-340000'];

        $this->assertContains('two_places', self::codes($m));
    }

    public function test_a_line_named_only_as_a_connection_is_a_mention_not_the_subject(): void
    {
        $m = $this->judge($this->record('Chemin de fer de Gisors : raccordement avec la ligne de Paris au Havre', 1870))['rfn-340000'];

        $this->assertSame(['two_places', 'secondary_mention', 'rail_word', 'date_in_life', 'doc_type'], self::codes($m));
        $this->assertSame(7, $m->score);
        $this->assertFalse($m->passes(ArchiveMatcher::THRESHOLD));
    }

    public function test_a_horse_tramway_is_not_the_railway(): void
    {
        $m = $this->judge($this->record('Chemin de fer américain de Paris à Rouen', 1861))['rfn-340000'];

        $this->assertSame('other_transport', $m->rejected);
    }

    public function test_an_undated_document_shared_by_two_lines_goes_to_the_oldest(): void
    {
        $matcher = new ArchiveMatcher;
        $undated = [['code' => 'date_unknown', 'points' => 0]];
        $old = new ArchiveMatch(1, 9, $undated, [], null, ['paris', 'rouen'], 1840);
        $recent = new ArchiveMatch(3, 9, $undated, [], null, ['paris', 'rouen'], 1868);

        $this->assertSame([1], array_map(fn (ArchiveMatch $m) => $m->lineId, $matcher->winners([$recent, $old])));
    }

    public function test_a_plain_route_between_two_stations_needs_the_ends(): void
    {
        // « chaîne des Vosges de Saverne à Sélestat » : un trajet, pas une voie ferrée ; Poissy et Rouen ne
        // sont pas les extrémités de la ligne du Havre, le document reste sous le seuil.
        $m = $this->judge($this->record('Chemin de fer : panorama des coteaux de la Seine, vue de Poissy à Rouen', 1850, 'image'))['rfn-340000'];

        $this->assertSame(['route', 'rail_word', 'date_in_life', 'doc_type'], self::codes($m));
        $this->assertSame(8, $m->score);
        $this->assertFalse($m->passes(ArchiveMatcher::THRESHOLD));
    }

    public function test_the_parts_of_a_title_are_not_read_as_one_compound_name(): void
    {
        $record = new ArchiveRecord('ark:/12148/btv1b1', null, 'Chemin de fer de Paris à Rouen : Partie', 'Chemin de fer de Paris à Rouen : Partie', 'map', [],
            new ArchiveDate('1843', 1843, null, null, 'year'), null, null);

        $this->assertSame(['Paris', 'Rouen'], $this->judge($record)['rfn-340000']->places);
    }

    public function test_the_metro_is_not_a_railway_line(): void
    {
        $m = $this->judge($this->record('Construction du chemin de fer métropolitain municipal de Paris. Viaduc de Poissy, ligne vers Rouen', 1903, 'image'))['rfn-340000'];

        $this->assertSame('other_transport', $m->rejected);
    }

    public function test_a_tie_between_more_than_two_lines_is_ambiguous(): void
    {
        $matcher = new ArchiveMatcher;
        $tie = fn (int $id) => new ArchiveMatch($id, 10, [], [], null, ['a', 'b']);

        $this->assertCount(2, $matcher->winners([$tie(1), $tie(2), new ArchiveMatch(3, 9, [], [], null, ['a', 'c'])]));
        $this->assertSame([], $matcher->winners([$tie(1), $tie(2), $tie(3)]));
        $this->assertSame([], $matcher->winners([new ArchiveMatch(1, 8, [], [])]));
        $this->assertSame([], $matcher->winners([new ArchiveMatch(1, 11, [], [], 'no_rail_word')]));
    }

    public function test_dates_after_closure_and_unknown_dates(): void
    {
        $after = $this->judge($this->record("Souvenirs du chemin de fer d'Angoulême à Limoges", 2020))['rfn-610000'];
        $this->assertContains('date_after_closure', self::codes($after));

        $undated = $this->judge($this->record("Chemin de fer d'Angoulême à Limoges", null))['rfn-610000'];
        $this->assertContains('date_unknown', self::codes($undated));
        $this->assertSame(9, $undated->score);
    }

    /** @return iterable<string, array{string, ?array{0: string, 1: string}}> */
    public static function names(): iterable
    {
        yield 'de X à Y' => ['Ligne de Limoges-Bénédictins à Angoulême', ['Limoges-Bénédictins', 'Angoulême']];
        yield 'au' => ['Ligne de Paris-St-Lazare au Havre', ['Paris-St-Lazare', 'Havre']];
        yield 'élision et par' => ["Ligne d'Épernay à Reims par Rilly", ['Épernay', 'Reims']];
        yield 'du' => ['Ligne du Pouzin à Privas', ['Pouzin', 'Privas']];
        yield 'chemin de fer' => ['Chemin de fer de Paris à Lyon', ['Paris', 'Lyon']];
        yield 'parenthèse' => ['Ligne militaire de Maricourt à Péronne (1914-1918)', ['Maricourt', 'Péronne']];
        yield 'tiret' => ["Ligne L'Aigle - Conches", ["L'Aigle", 'Conches']];
        yield 'un seul lieu' => ['Voie-mère de Calais', null];
    }

    /** @param ?array{0: string, 1: string} $expected */
    #[DataProvider('names')]
    public function test_route_ends_are_read_from_line_names(string $name, ?array $expected): void
    {
        $this->assertSame($expected, LinePlaceIndex::routeEnds($name));
    }

    public function test_line_places_use_terminus_cities_and_skip_connections(): void
    {
        $line = $this->index->line(2);
        $this->assertNotNull($line);
        $this->assertTrue($line->areEnds('limoges', 'angouleme'));
        $this->assertSame([['Limoges', 'Angoulême']], $line->queryPairs);
        $this->assertArrayHasKey('saillat sur vienne', $line->places);
        $this->assertArrayHasKey('saillat chassenon', $line->places);

        // Ligne de ceinture : ses deux gares terminus sont dans Paris, Paris n'est donc pas une extrémité.
        $belt = LinePlaceIndex::linePlaces(['id' => 8, 'slug' => 'rfn-980000', 'name' => 'Ligne de ceinture', 'stations' => [
            ['name' => 'Passy', 'city' => 'Paris', 'pk_m' => 0], ['name' => 'Paris-Bestiaux', 'city' => 'Paris', 'pk_m' => 9000],
        ]]);
        $this->assertSame([[['passy'], ['paris bestiaux']]], $belt->endPairs);
        $this->assertFalse($belt->areEnds('passy', 'paris'));

        $connection = LinePlaceIndex::linePlaces(['id' => 9, 'slug' => 'rfn-9', 'name' => 'Raccordement de Bourges à Pont-Vert']);
        $this->assertSame([], $connection->queryPairs);
    }
}
