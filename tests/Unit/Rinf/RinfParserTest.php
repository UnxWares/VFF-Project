<?php

namespace Tests\Unit\Rinf;

use App\Services\Rinf\RinfDataset;
use App\Services\Rinf\RinfParser;
use App\Services\Rinf\RinfStationMatcher;
use App\Services\TrackLayout\LaneAssigner;
use App\Services\TrackLayout\TrackLayout;
use PHPUnit\Framework\TestCase;

/**
 * Lecture du RINF : profils de déclivités (valeurs réelles du graphe de l'ERA, lues le 24/11/2026), références de
 * voies, codes de ligne, sortes de voies, rattachement des gares, rangées et opérations sur les portions de PK.
 */
class RinfParserTest extends TestCase
{
    public function test_gradient_profiles_of_both_tracks_agree_once_read_in_their_running_direction(): void
    {
        // Paris - Bordeaux, section 570000-1_FR0000006885_FR0000004825 (PK 18+272 à 19+038).
        $v1 = RinfParser::gradientSegments('+02.2|+04.0(+18.300)|+00.0(+18.855)', 18272, 19038, true);
        $v2 = RinfParser::gradientSegments('+04.0|+02.9(+18.855)|+00.0(+18.300)', 18272, 19038, false);

        $this->assertSame([
            ['pk_from_m' => 18272, 'pk_to_m' => 18300, 'permille' => 4.0],
            ['pk_from_m' => 18300, 'pk_to_m' => 18855, 'permille' => 0.0],
            ['pk_from_m' => 18855, 'pk_to_m' => 19038, 'permille' => 2.2],
        ], $v1);
        $this->assertSame(array_column($v1, 'pk_from_m'), array_column($v2, 'pk_from_m'));
        $this->assertSame([4.0, 0.0, 2.9], array_column($v2, 'permille'));

        // Section 570000-1_FR0000003796_FR0000002717 (66+205 à 69+793), voie 1 et voie 2.
        $this->assertSame(
            RinfParser::gradientSegments('+01.5|-02.0(+67.791)', 66205, 69793, true),
            RinfParser::gradientSegments('-02.0|+01.5(+67.791)', 66205, 69793, false),
        );
        // Positions hors de la section : bornées ; valeur illisible : ignorée.
        $this->assertSame([['pk_from_m' => 100, 'pk_to_m' => 200, 'permille' => -5.1]], RinfParser::gradientSegments('-05.1|abc', 100, 200, true));
    }

    public function test_short_track_references_and_kinds(): void
    {
        $this->assertSame('V1', RinfDataset::shortTrackRef('Voie V1 de Paris-12 à Marseille-01'));
        $this->assertSame('V2', RinfDataset::shortTrackRef('Voie 2 de Paris-Gare-de-Lyon à Marseille-St-Charles'));
        $this->assertSame('V1', RinfDataset::shortTrackRef('1'));
        $this->assertSame('VU', RinfDataset::shortTrackRef('Voie UNIQUE de Beillant à St-Michel-sur-Charente'));
        $this->assertSame('VU', RinfDataset::shortTrackRef('UNIQUE'));
        $this->assertSame('V1BIS', RinfDataset::shortTrackRef('Voie V1BIS'));
        $this->assertSame('E', RinfDataset::shortTrackRef('Voie E de Sireuil'));
        $this->assertSame('5', RinfDataset::shortTrackRef('5 - COGNAC'));
        $this->assertSame('Liaison BS83/84', RinfDataset::shortTrackRef('Liaison BS83/84'));

        $this->assertSame('running', RinfDataset::opTrackKind('running', 'Voie V2', 10));
        $this->assertSame('loop', RinfDataset::opTrackKind('running', 'Voie E de Cognac', 10));
        $this->assertSame('crossover', RinfDataset::opTrackKind('siding', 'Liaison 216/210', 10));
        $this->assertSame('spur', RinfDataset::opTrackKind('siding', 'ITE Naviland', 130));
        $this->assertSame('yard', RinfDataset::opTrackKind('siding', 'Faisceau Pair V8', 10));
        $this->assertSame('siding', RinfDataset::opTrackKind('siding', '4 - COGNAC', 10));

        $this->assertSame('up', RinfDataset::direction('N', true));
        $this->assertSame('up', RinfDataset::direction('O', false));
        $this->assertSame('both', RinfDataset::direction('B', false));
        $this->assertSame(-31, RinfDataset::pkMeters(0.0, -31.0));
        $this->assertSame('579000', RinfParser::lineCode('579000-1'));
        $this->assertSame('837000', RinfParser::lineCode('837000'));
        $this->assertSame('ChannelTunnelFi', mb_substr(RinfParser::lineCode('ChannelTunnelFixedLink'), 0, 15));
        $this->assertSame('station', RinfDataset::opKind(10, 'Cognac'));
        $this->assertSame('junction', RinfDataset::opKind(80, 'Bif 579000/500306'));
        $this->assertSame('other', RinfDataset::opKind(50, 'Faisceau Pair'));
    }

    public function test_sections_keep_the_coordinate_of_their_own_line(): void
    {
        $uri = 'http://data.europa.eu/949/sectionOfLine/x';
        $row = fn (string $lrs, string $km): array => ['g' => ['type' => 'uri', 'value' => 'http://data.europa.eu/949/graph/0087'], 'uri' => ['type' => 'literal', 'value' => $uri],
            'lineId' => ['type' => 'literal', 'value' => '579000-1'], 'opS' => ['type' => 'literal', 'value' => 'A'], 'opE' => ['type' => 'literal', 'value' => 'B'],
            'len' => ['type' => 'literal', 'value' => '0.501'], 'nature' => ['type' => 'uri', 'value' => 'http://data.europa.eu/949/concepts/sol-natures/10'],
            'skm' => ['type' => 'literal', 'value' => $km], 'soff' => ['type' => 'literal', 'value' => '470.0'], 'slrs' => ['type' => 'literal', 'value' => $lrs]];

        $sections = RinfParser::sections([$row('500000-1', '120.0'), $row('579000-1', '0.0')]);

        $this->assertSame(470, $sections[$uri]['pk_start_m']);
        $this->assertSame(501, $sections[$uri]['length_m']);
        $this->assertSame('regular', $sections[$uri]['nature']);
        $this->assertSame('0087', $sections[$uri]['dataset']);
    }

    public function test_station_matching_by_name_and_distance(): void
    {
        $matcher = new RinfStationMatcher([
            (object) ['id' => 1, 'slug' => 'saint-michel-sur-charente', 'name' => 'Saint-Michel-sur-Charente', 'lat' => 45.6447, 'lon' => 0.1101],
            (object) ['id' => 2, 'slug' => 'jarnac', 'name' => 'Jarnac', 'lat' => 48.0, 'lon' => 2.0],
            (object) ['id' => 3, 'slug' => 'les-aubrais', 'name' => 'Les Aubrais', 'lat' => 47.9265, 'lon' => 1.9073],
        ]);

        $this->assertSame(['station_id' => 1, 'station_match' => 'name_distance'],
            array_slice((array) $matcher->match(['name' => 'St-Michel-sur-Charente', 'kind' => 'other', 'lat' => 45.6443445, 'lon' => 0.1106611]), 0, 2));
        // Homonyme à plus de 3 km : rien.
        $this->assertNull($matcher->match(['name' => 'Jarnac', 'kind' => 'station', 'lat' => 45.6711384, 'lon' => -0.177784]));
        // Sans position : nom égal à une seule gare.
        $this->assertSame('name', $matcher->match(['name' => 'Jarnac', 'kind' => 'station', 'lat' => null, 'lon' => null])['station_match'] ?? null);
        // Nom prolongé, mot pour mot.
        $this->assertSame(3, $matcher->match(['name' => 'Les Aubrais-Orléans', 'kind' => 'station', 'lat' => 47.927, 'lon' => 1.908])['station_id'] ?? null);
        // Bifurcation proche d'une gare : pas de rattachement par la seule distance.
        $this->assertNull($matcher->match(['name' => 'Bif 579000/579306', 'kind' => 'junction', 'lat' => 45.6448, 'lon' => 0.1102]));
        $this->assertSame('distance', $matcher->match(['name' => 'Autre nom', 'kind' => 'station', 'lat' => 45.6448, 'lon' => 0.1102])['station_match'] ?? null);
    }

    /**
     * A same name wins over a prolonged one, whatever the distance (RINF of 24/11/2026: "Châteaubriant" went to the VFF
     * station "Châteaubriant Tram-Train" 36 m away and "Châteaubriant Tram-Train" to "Châteaubriant" 11 m away;
     * "Paris-Nord-Surface" and "Paris-Nord-Souterraine" to "Paris-Nord", at the same place as the stations of their name).
     */
    public function test_a_same_name_wins_over_a_prolonged_name(): void
    {
        $matcher = new RinfStationMatcher([
            (object) ['id' => 16488, 'slug' => 'chateaubriant', 'name' => 'Châteaubriant', 'lat' => 47.7170119, 'lon' => -1.3713991],
            (object) ['id' => 16950, 'slug' => 'chateaubriant-tram-train', 'name' => 'Châteaubriant Tram-Train', 'lat' => 47.7174844, 'lon' => -1.3712729],
            (object) ['id' => 15457, 'slug' => 'paris-nord', 'name' => 'Paris-Nord', 'lat' => 48.8810532, 'lon' => 2.3566222],
            (object) ['id' => 15460, 'slug' => 'paris-nord-surface', 'name' => 'Paris-Nord-Surface', 'lat' => 48.8810532, 'lon' => 2.3566222],
        ]);

        $this->assertSame(16488, $matcher->match(['name' => 'Châteaubriant', 'kind' => 'station', 'lat' => 47.717219, 'lon' => -1.3710086])['station_id'] ?? null);
        $this->assertSame(16950, $matcher->match(['name' => 'Châteaubriant Tram-Train', 'kind' => 'station', 'lat' => 47.7171065, 'lon' => -1.3713739])['station_id'] ?? null);
        $this->assertSame(15460, $matcher->match(['name' => 'Paris-Nord-Surface', 'kind' => 'station', 'lat' => 48.8808863, 'lon' => 2.3574005])['station_id'] ?? null);
        $this->assertSame(15457, $matcher->match(['name' => 'Paris-Nord', 'kind' => 'station', 'lat' => 48.8808863, 'lon' => 2.3574005])['station_id'] ?? null);
        // Without a station of the same name, the prolonged name still matches.
        $this->assertSame(15457, $matcher->match(['name' => 'Paris-Nord-Souterraine', 'kind' => 'station', 'lat' => 48.8808863, 'lon' => 2.3574005])['station_id'] ?? null);
    }

    public function test_lanes_ranges_and_track_numbers(): void
    {
        $t = fn (string $id, string $ref, string $kind, int $from, int $to, ?string $op = null): array => ['id' => $id, 'ref' => $ref, 'kind' => $kind,
            'pk_from_m' => $from, 'pk_to_m' => $to, 'op_id' => $op];
        $lanes = array_column(LaneAssigner::assign([
            $t('a', 'V1', 'running', 0, 1000), $t('b', 'V2', 'running', 0, 1000), $t('c', 'V2', 'running', 400, 600, 'op'),
            $t('d', '3', 'siding', 100, 500), $t('e', '5', 'siding', 200, 600), $t('f', '7', 'siding', 700, 900), $t('g', '4', 'siding', 100, 300),
        ]), 'lane', 'id');

        $this->assertSame(['a' => 0, 'b' => 1, 'c' => 1, 'd' => -1, 'e' => -2, 'f' => -1, 'g' => 2], $lanes);
        $this->assertSame([[0, 100], [300, 400]], TrackLayout::subtract([0, 400], [[100, 200], [150, 300]]));
        $this->assertSame([[0, 300], [400, 500]], TrackLayout::union([[200, 300], [0, 250], [400, 500]]));
        $this->assertSame('12', TrackLayout::trackNumber('Voie 12 de Cognac'));
    }
}
