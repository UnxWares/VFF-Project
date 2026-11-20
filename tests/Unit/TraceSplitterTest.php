<?php

namespace Tests\Unit;

use App\Console\Commands\LinkTraces;
use App\Services\TraceSplitter;
use PHPUnit\Framework\TestCase;

/**
 * Règles de découpe des tracés sources (vff:link-traces, logique pure) :
 *   - un tracé « A B » qui met 2 ou 3 lignes connues bout à bout est coupé aux
 *     jonctions (« Auray Saint-Brieuc », « Bram Lavelanet ») ;
 *   - une portion longue sur la voie d'une autre ligne est découpée, le reste
 *     garde son rattachement ;
 *   - un nom qui désigne une autre ligne l'emporte sur le recouvrement, sauf
 *     précisions entre parenthèses incompatibles.
 */
class TraceSplitterTest extends TestCase
{
    /** Lignes de Bretagne et de l'Aude, d'après la base (noms, gares Wikipédia / lignes-oubliees). */
    private function splitter(): TraceSplitter
    {
        return new TraceSplitter([
            665 => ['ends' => ['Auray', 'Pontivy'], 'stations' => ['Baud', 'Saint-Nicolas-des-Eaux']],
            140 => ['ends' => ['St-Brieuc', 'Pontivy'], 'stations' => ['Loudéac', 'Mûr-de-Bretagne']],
            799 => ['ends' => ['Auray', 'Quiberon'], 'stations' => ['Plouharnel']],
            946 => ['ends' => ['Questembert', 'Ploërmel'], 'stations' => ['Malestroit']],
            53 => ['ends' => ['Ploërmel', 'La Brohinière'], 'stations' => ['Loyat', 'Mauron', 'Gaël']],
            981 => ['ends' => ['Bram', 'Belvèze'], 'stations' => ['Montréal']],
            979 => ['ends' => ['Pamiers', 'Limoux'], 'stations' => ['Mirepoix', 'Moulin-Neuf', 'Belvèze']],
            980 => ['ends' => ['Moulin-Neuf', 'Lavelanet'], 'stations' => ['Chalabre']],
            938 => ['ends' => ['La Hutte-Coulombiers', 'Mamers'], 'stations' => ['Les Mées']],
            937 => ['ends' => ['Mamers', 'Mortagne-au-Perche'], 'stations' => ['Bellême']],
        ]);
    }

    /** @return list<string> chaînes « id>id » */
    private function lineChains(string $a, string $b): array
    {
        return array_map(fn ($c) => implode('>', array_column($c, 'line')), $this->splitter()->chains($a, $b));
    }

    public function test_same_place_tolerates_suffixes_alternatives_and_abbreviations(): void
    {
        $this->assertTrue(TraceSplitter::samePlace('Mortagne', 'Mortagne-au-Perche'));
        $this->assertTrue(TraceSplitter::samePlace('La Hutte/Coulombiers', 'La Hutte - Coulombiers'));
        $this->assertTrue(TraceSplitter::samePlace('Saint-Brieuc', 'St-Brieuc'));
        $this->assertTrue(TraceSplitter::samePlace('Châlon-sur-Saône', 'Chalon-sur-Saône'));
        $this->assertTrue(TraceSplitter::samePlace('Moulin-Neuf', 'Moulin-Neuf'));
        // Préfixe sans mot significatif, ou autre lieu : non.
        $this->assertFalse(TraceSplitter::samePlace('Saint', 'Saint-Germain-du-Plain'));
        $this->assertFalse(TraceSplitter::samePlace('Pontivy', 'Ponthierry'));
        $this->assertFalse(TraceSplitter::samePlace('Saint-Germain-des-Fossés', 'Saint-Germain-du-Plain'));
    }

    public function test_two_lines_end_to_end_are_found_at_their_common_end(): void
    {
        $chains = $this->splitter()->chains('Auray', 'Saint-Brieuc');

        $this->assertCount(1, $chains);
        $this->assertSame([665, 140], array_column($chains[0], 'line'));
        $this->assertSame('Pontivy', $chains[0][0]['to']);
        $this->assertTrue($chains[0][0]['from_end'] && $chains[0][0]['to_end'] && $chains[0][1]['to_end']);
    }

    public function test_label_ending_at_a_station_takes_a_section_of_the_last_line(): void
    {
        $chains = $this->splitter()->chains('Questembert', 'Mauron');

        $this->assertSame(['946>53'], array_map(fn ($c) => implode('>', array_column($c, 'line')), $chains));
        $this->assertFalse($chains[0][1]['to_end'], 'Mauron est une gare de Ploërmel - La Brohinière, pas une extrémité');
    }

    public function test_three_lines_with_a_middle_section_between_two_stations(): void
    {
        $chains = $this->splitter()->chains('Bram', 'Lavelanet');

        $this->assertSame(['981>979>980'], array_map(fn ($c) => implode('>', array_column($c, 'line')), $chains));
        $this->assertSame(['Belvèze', 'Moulin-Neuf'], [$chains[0][0]['to'], $chains[0][1]['to']]);
    }

    public function test_no_split_when_one_line_holds_both_places(): void
    {
        // « Auray Quiberon » est la ligne 799, « Ploërmel Mauron » une section de la ligne 53.
        $this->assertSame([], $this->lineChains('Auray', 'Quiberon'));
        $this->assertSame([], $this->lineChains('Ploërmel', 'Mauron'));
    }

    public function test_a_station_shared_by_two_lines_is_not_a_junction(): void
    {
        $s = new TraceSplitter([
            1 => ['ends' => ['Alpha', 'Bravo'], 'stations' => ['Juliett']],
            2 => ['ends' => ['Charlie', 'Delta'], 'stations' => ['Juliett']],
        ]);

        $this->assertSame([], $s->chains('Alpha', 'Delta'));
    }

    public function test_cuts_follow_the_trace_in_either_direction(): void
    {
        $ok = TraceSplitter::cuts([0.02, 0.41, 0.97], 120000);
        $this->assertTrue($ok['ok']);
        $this->assertSame([0.0, 0.41, 1.0], $ok['cuts']);
        $this->assertFalse($ok['reversed']);

        $rev = TraceSplitter::cuts([0.98, 0.6, 0.01], 120000);
        $this->assertTrue($rev['ok']);
        $this->assertTrue($rev['reversed']);
        $this->assertEqualsWithDelta(0.4, $rev['cuts'][1], 1e-9);
    }

    public function test_cuts_reject_disorder_far_ends_and_tiny_pieces(): void
    {
        $this->assertFalse(TraceSplitter::cuts([0.02, 0.7, 0.4, 0.98], 60000)['ok'], 'jonctions dans le désordre');
        $this->assertFalse(TraceSplitter::cuts([0.4, 0.6, 0.98], 60000)['ok'], 'A loin du bout du tracé');
        $this->assertFalse(TraceSplitter::cuts([0.0, 0.01, 1.0], 60000)['ok'], 'morceau de 600 m');
    }

    public function test_piece_length_is_checked_against_the_official_length(): void
    {
        $this->assertTrue(TraceSplitter::plausibleLength(50900, 55000, true));
        $this->assertFalse(TraceSplitter::plausibleLength(20000, 55000, true));
        $this->assertTrue(TraceSplitter::plausibleLength(20800, 41000, false), 'section de ligne');
        $this->assertFalse(TraceSplitter::plausibleLength(80000, 41000, false));
        $this->assertTrue(TraceSplitter::plausibleLength(80000, null, true), 'longueur officielle inconnue');
    }

    public function test_pieces_around_shared_runs(): void
    {
        // Tracé de 28,4 km dont 4,4 km sur la voie de la ligne 56 à la fin : 2 morceaux.
        $p = TraceSplitter::piecesAroundRuns([[0.847, 1.0, 56]], 28400, -12);
        $this->assertSame([[0.0, 0.847, -12, false], [0.847, 1.0, 56, true]], $p);

        // Reste de moins de 200 m en tête et en queue : absorbé par la portion commune.
        $p = TraceSplitter::piecesAroundRuns([[0.005, 0.5, 7], [0.6, 0.995, 8]], 20000, -3);
        $this->assertSame([[0.0, 0.5, 7, true], [0.5, 0.6, -3, false], [0.6, 1.0, 8, true]], $p);

        // Tout le tracé sur une seule ligne : relève de l'étape « même voie ».
        $this->assertSame([], TraceSplitter::piecesAroundRuns([[0.003, 0.998, 9]], 10000, -1));
    }

    public function test_name_rule_requires_compatible_qualifiers(): void
    {
        $this->assertFalse(LinkTraces::sameQualifiers('Raccordement de Badan (poste 3) à Chasse-sur-Rhône (Poste 3)',
            'Raccordement de Badan (poste 1) à Chasse-sur-Rhône (poste 3)'));
        $this->assertTrue(LinkTraces::sameQualifiers('Ligne de Montmédy à Écouviez', 'Ligne de Montmédy à Écouviez'));
        $this->assertTrue(LinkTraces::sameQualifiers('Ligne de Metz-Ville à la frontière', 'Ligne de Metz-Ville à la frontière (vers Ueberherrn)'));
    }
}
