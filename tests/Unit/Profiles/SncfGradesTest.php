<?php

namespace Tests\Unit\Profiles;

use App\Services\Profiles\SncfGrades;
use PHPUnit\Framework\TestCase;

/** Jeu SNCF « Caractéristique des voies et déclivité » : agrégation par ligne, règles de reprise, fichier écrit. */
class SncfGradesTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function record(string $code, string $type, float $value, string $from, string $to, string $track = 'V1'): array
    {
        return ['code_ligne' => $code, 'lib_ligne' => 'Ligne '.$code, 'code_voie' => $code.'-1-'.$track, 'type' => $type, 'valeur' => $value, 'pkd' => $from, 'pkf' => $to];
    }

    public function test_aggregate_keeps_the_steepest_segment_and_the_covered_length(): void
    {
        $lines = SncfGrades::aggregate([
            self::record('476000', 'RAMPE', 12.0, '001+000', '002+000'),
            self::record('476000', 'PENTE', 18.0, '001+500', '002+500', 'V2'),
            self::record('476000', 'ALIGNEMENT', 0.0, '003+000', '004+000'),
            self::record('476000', 'RAMPE', 5.0, 'H+391', 'H+435'),
            array_replace(self::record('476000', 'RAMPE', 99.0, '005+000', '005+100'), ['valeur' => 'n/a']),
            self::record('12', 'RAMPE', 10.0, '000+000', '001+000'),
        ]);

        $this->assertSame(['476000'], array_map('strval', array_keys($lines)));
        $line = $lines['476000'];
        $this->assertSame(18.0, $line['max_grade_permil']);
        $this->assertSame('PENTE', $line['type']);
        $this->assertSame('476000-1-V2', $line['track']);
        $this->assertSame(3, $line['segments']);
        // 1+000 à 2+500, voies réunies ; le PK à lettre ne compte pas dans la longueur.
        $this->assertSame(1500, $line['covered_m']);
    }

    public function test_steepest_portion_is_the_union_of_the_segments_at_the_maximum(): void
    {
        $line = SncfGrades::aggregate([
            self::record('476000', 'RAMPE', 20.0, '001+000', '001+300'),
            self::record('476000', 'RAMPE', 20.0, '001+200', '001+600', 'V2'),
            self::record('476000', 'RAMPE', 20.0, '009+000', '009+050'),
            self::record('476000', 'RAMPE', 8.0, '002+000', '004+000'),
        ])['476000'];
        $this->assertSame(650, $line['steep_m'], '1+000 à 1+600 et 9+000 à 9+050');

        // Un maximum plus fort repart de zéro : l'ancienne portion ne compte plus.
        $line = SncfGrades::aggregate([
            self::record('476000', 'RAMPE', 8.0, '002+000', '004+000'),
            self::record('476000', 'RAMPE', 30.0, '005+000', '005+050'),
        ])['476000'];
        $this->assertSame(50, $line['steep_m']);
    }

    public function test_a_short_steep_portion_does_not_replace_wikipedia(): void
    {
        $measure = ['value' => 28.7, 'kind' => 'terrain'];
        $decide = fn (int $steep, int $covered = 400, int $min = SncfGrades::MIN_STEEP_M): array => SncfGrades::decide(
            ['max_grade_permil' => 36.2, 'covered_m' => $covered, 'steep_m' => $steep], 1000, 25.0, $measure, 0.1, $min);

        $this->assertSame(500, SncfGrades::MIN_STEEP_M);
        $this->assertSame('kept_wikipedia_short', $decide(50)['status']);
        $this->assertFalse($decide(50)['retained']);
        $this->assertSame(50, $decide(50)['steep_m']);
        $this->assertSame('kept_wikipedia_short', $decide(499)['status']);
        $this->assertSame('retained_over_wikipedia', $decide(500)['status'], 'la longueur minimale est incluse');
        $this->assertSame('retained_over_wikipedia', $decide(50, 400, 0)['status'], 'minimum à 0 : pas de contrôle');
        $this->assertSame('retained_over_wikipedia', $decide(50, 950)['status'], 'ligne couverte presque en entier');
        // Sans longueur connue, rien à contrôler ; sans Wikipédia, rien à remplacer.
        $this->assertSame('retained_over_wikipedia', SncfGrades::decide(['max_grade_permil' => 36.2, 'covered_m' => 400], 1000, 25.0, $measure)['status']);
        $this->assertSame('retained', SncfGrades::decide(['max_grade_permil' => 36.2, 'covered_m' => 400, 'steep_m' => 50], 1000, null, $measure)['status']);
    }

    public function test_pk_parsing(): void
    {
        $this->assertSame(19663, SncfGrades::pk('019+663'));
        $this->assertSame(-771, SncfGrades::pk('000-771'));
        $this->assertNull(SncfGrades::pk('H+391'));
        $this->assertNull(SncfGrades::pk(null));
    }

    public function test_a_value_above_sixty_is_rejected_except_on_a_known_mountain_line(): void
    {
        $sncf = ['max_grade_permil' => 90.0, 'covered_m' => 10000];

        $this->assertSame('rejected_above_limit', SncfGrades::decide($sncf, 60000, 10.0, ['value' => 25.4, 'kind' => 'terrain'])['status']);
        $this->assertSame('retained', SncfGrades::decide($sncf, 60000, null, ['value' => 85.0, 'kind' => 'track'])['status']);
        $this->assertSame('retained_over_wikipedia', SncfGrades::decide($sncf, 60000, 70.0, ['value' => 80.0, 'kind' => 'track'])['status']);
        // La mesure sur le terrain ne fait pas une ligne de montagne.
        $this->assertSame('rejected_above_limit', SncfGrades::decide($sncf, 60000, null, ['value' => 85.0, 'kind' => 'terrain'])['status']);
    }

    public function test_a_value_three_times_the_measure_is_rejected(): void
    {
        $sncf = ['max_grade_permil' => 20.0, 'covered_m' => 300];

        $decision = SncfGrades::decide($sncf, 1000, null, ['value' => 6.0, 'kind' => 'track']);
        $this->assertSame('rejected_ratio', $decision['status']);
        $this->assertFalse($decision['retained']);
        $this->assertTrue(SncfGrades::decide($sncf, 1000, null, ['value' => 7.0, 'kind' => 'track'])['retained']);
    }

    public function test_coverage_measure_and_floor(): void
    {
        $measure = ['value' => 10.0, 'kind' => 'track'];

        $this->assertSame('rejected_coverage', SncfGrades::decide(['max_grade_permil' => 10.0, 'covered_m' => 99], 1000, null, $measure)['status']);
        $this->assertSame('retained', SncfGrades::decide(['max_grade_permil' => 10.0, 'covered_m' => 99], 1000, null, $measure, 0.05)['status']);
        $this->assertSame('rejected_coverage', SncfGrades::decide(['max_grade_permil' => 10.0, 'covered_m' => 500], null, null, $measure)['status']);
        $this->assertSame('rejected_unchecked', SncfGrades::decide(['max_grade_permil' => 10.0, 'covered_m' => 500], 1000, null, null)['status']);
        $this->assertSame('rejected_below_one', SncfGrades::decide(['max_grade_permil' => 0.4, 'covered_m' => 500], 1000, null, $measure)['status']);
        $this->assertSame(1.0, SncfGrades::decide(['max_grade_permil' => 10.0, 'covered_m' => 1500], 1000, null, $measure)['covered_share']);
    }

    public function test_wikipedia_is_kept_unless_sncf_is_steeper_or_complete(): void
    {
        $measure = ['value' => 12.0, 'kind' => 'track'];
        $decide = fn (float $sncf, float $wiki, int $covered = 300): string => SncfGrades::decide(['max_grade_permil' => $sncf, 'covered_m' => $covered], 1000, $wiki, $measure)['status'];

        $this->assertSame('kept_wikipedia_agree', $decide(11.0, 10.0));
        $this->assertSame('kept_wikipedia_agree', $decide(29.0, 25.0));
        $this->assertSame('retained_over_wikipedia', $decide(15.0, 10.0));
        $this->assertSame('kept_wikipedia_partial', $decide(5.0, 10.0));
        $this->assertSame('retained_over_wikipedia', $decide(5.0, 10.0, 950));
    }

    public function test_entry_and_rendered_file_are_stable(): void
    {
        $agg = SncfGrades::aggregate([self::record('476000', 'RAMPE', 18.0, '012+400', '012+600')]);
        $entry = SncfGrades::entry('476000', $agg['476000'], 0.327);

        $this->assertSame('sncf', $entry['provider']);
        $this->assertSame('ODbL 1.0', $entry['license']);
        $this->assertSame('SNCF Réseau', $entry['attribution']);
        $this->assertSame('RAMPE 18 ‰ ; voie 476000-1-V1 ; PK 012+400 à 012+600 ; 1 segment, 32,7 % de la ligne', $entry['quote']);
        $this->assertStringEndsWith('refine.code_ligne=476000', $entry['source_url']);

        $b = SncfGrades::render(['rfn-b' => $entry, 'rfn-a' => $entry], '2026-01-20');
        $this->assertSame($b, SncfGrades::render(['rfn-a' => $entry, 'rfn-b' => $entry], '2026-01-20'));
        $this->assertStringNotContainsString('—', $b);
        $this->assertLessThan(strpos($b, "'rfn-b'"), strpos($b, "'rfn-a'"));

        $path = tempnam(sys_get_temp_dir(), 'sncf-grades');
        try {
            file_put_contents($path, $b);
            $this->assertSame(['rfn-a' => $entry, 'rfn-b' => $entry], require $path);
        } finally {
            unlink($path);
        }
    }
}
