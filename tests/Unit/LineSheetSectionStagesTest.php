<?php

namespace Tests\Unit;

use App\Services\LineSheet;
use PHPUnit\Framework\TestCase;

/**
 * Étapes de mise en service des sections nommées (LineSheet::sectionStages) : les phases datées
 * des années d'ouverture de chaque section, d'après les vraies dates de line_events. Exemple de la
 * LGV Sud-Est (752000) : LGV Paris - Lyon ouverte en 1981 puis en 1983, LGV Rhône-Alpes en 1992
 * et 1994, LGV Méditerranée en 2001.
 */
class LineSheetSectionStagesTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private static function sections(): array
    {
        return [
            ['name' => 'LGV Sud-Est', 'opening_year_from' => 1981, 'opening_year_to' => 1983],
            ['name' => 'LGV Rhône-Alpes', 'opening_year_from' => 1992, 'opening_year_to' => 1994],
            ['name' => 'LGV Méditerranée', 'opening_year_from' => 2001, 'opening_year_to' => null],
        ];
    }

    /**
     * @param  list<array{0: string, 1: string, 2: int, 3: ?int, 4?: bool}>  $changes  [from, to, length_m, event_id, minor]
     * @return array<string, mixed>
     */
    private static function phase(string $date, string $precision, array $changes): array
    {
        return ['date' => $date, 'precision' => $precision, 'changes' => array_map(fn (array $c): array => [
            'from' => $c[0], 'to' => $c[1], 'length_m' => $c[2], 'event_id' => $c[3], 'minor' => $c[4] ?? false,
        ], $changes)];
    }

    public function test_each_dated_opening_goes_to_the_section_opened_that_year(): void
    {
        $phases = [
            self::phase('1981-09-27', 'day', [['none', 'lgv', 263_938, 55733], ['none', 'lgv', 9_599, 55733]]),
            self::phase('1983-09-25', 'day', [['none', 'lgv', 118_653, 13490]]),
            self::phase('1992-12-13', 'day', [['none', 'lgv', 37_180, 13552]]),
            self::phase('1994-07-03', 'day', [['none', 'lgv', 76_250, 55765], ['none', 'lgv', 2_095, 13556]]),
            // Raccord de 0,4 km posé en 2010, fondu dans la phase de 2001 (changement « minor ») : pas compté.
            self::phase('2001-06-10', 'day', [['none', 'lgv', 218_200, 78577], ['none', 'lgv', 400, 78546, true]]),
        ];

        $sections = LineSheet::sectionStages(self::sections(), $phases);

        $this->assertSame([[0, 1], [2, 3], [4]], array_map(fn (array $s): array => array_column($s['stages'], 'phase'), $sections));
        $this->assertSame(['1981-09-27', 'day', 273_537, [55733]], [
            $sections[0]['stages'][0]['date'], $sections[0]['stages'][0]['precision'],
            $sections[0]['stages'][0]['length_m'], $sections[0]['stages'][0]['event_ids'],
        ]);
        $this->assertSame([78_345, [55765, 13556]], [$sections[1]['stages'][1]['length_m'], $sections[1]['stages'][1]['event_ids']]);
        $this->assertSame([218_200, [78577]], [$sections[2]['stages'][0]['length_m'], $sections[2]['stages'][0]['event_ids']], 'année seule : de 2001 à 2001');
        $this->assertSame('LGV Sud-Est', $sections[0]['name'], 'les autres champs restent');
    }

    public function test_undated_phases_closures_and_traffic_changes_are_not_stages(): void
    {
        $phases = [
            self::phase('1990-12-31', 'range', [['none', 'lgv', 50_000, null]]),
            self::phase('1982-05-01', 'day', [['lgv', 'closed', 1_000, 7]]),
            self::phase('1983-01-01', 'year', [['mixed', 'lgv', 30_000, 8]]),
        ];

        $sections = LineSheet::sectionStages(self::sections(), $phases);

        $this->assertSame([[], [], []], array_column($sections, 'stages'));
    }

    public function test_a_phase_longer_than_its_section_is_not_attached(): void
    {
        // LGV Est européenne : une seule date de 2007 pour toute la ligne (409 km), plus longue que la
        // première phase (300 km) : elle date aussi la seconde, ouverte en 2016. Pas d'étape inventée.
        $sections = [
            ['name' => 'Première phase', 'pk_start_m' => 0, 'pk_end_m' => 300_000, 'opening_year_from' => 2007, 'opening_year_to' => 2007],
            ['name' => 'Seconde phase', 'pk_start_m' => 300_000, 'pk_end_m' => 406_000, 'opening_year_from' => 2016, 'opening_year_to' => 2016],
        ];
        $tooLong = LineSheet::sectionStages($sections, [self::phase('2007-01-01', 'year', [['none', 'lgv', 409_136, 2464]])]);
        $this->assertSame([[], []], array_column($tooLong, 'stages'));

        // À la tolérance près (tracé VFF contre PK des sources), l'étape reste.
        $close = LineSheet::sectionStages($sections, [self::phase('2007-06-10', 'day', [['none', 'lgv', 320_000, 1]])]);
        $this->assertSame([320_000], array_column($close[0]['stages'], 'length_m'));
    }

    public function test_a_reopening_counts_but_an_opening_shared_by_two_sections_is_not_guessed(): void
    {
        $sections = [
            ['name' => 'Tronc commun', 'opening_year_from' => 1989, 'opening_year_to' => 1990],
            ['name' => 'Branche', 'opening_year_from' => 1990, 'opening_year_to' => 1990],
            ['name' => 'Sans année', 'opening_year_from' => null, 'opening_year_to' => null],
        ];
        $phases = [
            self::phase('1989-09-24', 'day', [['closed', 'mixed', 12_000, 3]]),
            // 1990 : années des deux premières sections, aucun tracé pour trancher.
            self::phase('1990-09-30', 'day', [['none', 'lgv', 150_000, 4]]),
        ];

        $out = LineSheet::sectionStages($sections, $phases);

        $this->assertSame([[0], [], []], array_map(fn (array $s): array => array_column($s['stages'], 'phase'), $out));
        $this->assertSame(12_000, $out[0]['stages'][0]['length_m'], 'réouverture d’une voie fermée');
    }
}
