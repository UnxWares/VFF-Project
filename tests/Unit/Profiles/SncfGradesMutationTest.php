<?php

namespace Tests\Unit\Profiles;

use App\Services\Profiles\SncfGrades;
use PHPUnit\Framework\TestCase;

/** Bornes et formes exactes de l'agrégation, de la décision et du fichier écrit (cibles des tests de mutation). */
class SncfGradesMutationTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function rec(string $code, string $type, mixed $value, ?string $from, ?string $to, string $track = 'T', string $name = 'L'): array
    {
        return ['code_ligne' => $code, 'lib_ligne' => $name, 'code_voie' => $track, 'type' => $type, 'valeur' => $value, 'pkd' => $from, 'pkf' => $to];
    }

    /** @param  array{value: float, kind: string}|null  $measure */
    private static function verdict(float $value, ?float $wiki = null, ?array $measure = ['value' => 20.0, 'kind' => 'track'], int $covered = 500, ?int $length = 1000, float $min = 0.1): string
    {
        return SncfGrades::decide(['max_grade_permil' => $value, 'covered_m' => $covered], $length, $wiki, $measure, $min)['status'];
    }

    public function test_constants_are_the_published_rules(): void
    {
        $this->assertSame('caracteristique-des-voies-et-declivite', SncfGrades::DATASET);
        $this->assertSame('ODbL 1.0', SncfGrades::LICENSE);
        $this->assertSame('SNCF Réseau', SncfGrades::ATTRIBUTION);
        $this->assertSame('SNCF Réseau, jeu « Caractéristique des voies et déclivité »', SncfGrades::TITLE);
        $this->assertSame('https://data.sncf.com/explore/dataset/caracteristique-des-voies-et-declivite/table/?refine.code_ligne=', SncfGrades::PAGE);
        $this->assertSame(60.0, SncfGrades::MAX_PLAIN_PERMIL);
        $this->assertSame(40.0, SncfGrades::MOUNTAIN_PERMIL);
        $this->assertSame(3.0, SncfGrades::MAX_RATIO);
        $this->assertSame(0.1, SncfGrades::MIN_COVERAGE);
        $this->assertSame(0.9, SncfGrades::FULL_COVERAGE);
        $this->assertSame(500, SncfGrades::MIN_STEEP_M);
        $this->assertSame(2.0, SncfGrades::AGREE_PERMIL);
        $this->assertSame(0.2, SncfGrades::AGREE_SHARE);
    }

    public function test_aggregate_fields_of_the_steepest_segment_and_counts(): void
    {
        $lines = SncfGrades::aggregate([
            self::rec('000123', 'PENTE', '-8', '010+000', '011+000', 'V1', 'Ligne A'),
            self::rec('000123', 'RAMPE', 15, '020+000', '020+500', 'V2', 'Ligne A bis'),
            self::rec('000123', 'RAMPE', 15, '030+000', '030+500', 'V3', 'Ligne A ter'),
            self::rec('000123', 'RAMPE', 3, '040+000', '040+500', 'V4', 'Ligne A 4'),
        ]);

        $this->assertSame(['000123'], array_keys($lines));
        $this->assertSame([
            'max_grade_permil' => 15.0, 'type' => 'RAMPE', 'track' => 'V2', 'pk_from' => '020+000', 'pk_to' => '020+500',
            'segments' => 4, 'covered_m' => 2500, 'steep_m' => 1000, 'name' => 'Ligne A bis',
        ], $lines['000123']);
    }

    public function test_steepest_length_restarts_at_each_new_maximum_and_merges_overlaps(): void
    {
        $lines = SncfGrades::aggregate([
            self::rec('000800', 'RAMPE', 10, '001+000', '002+000'),
            self::rec('000800', 'RAMPE', 10, '001+500', '002+200'),
            self::rec('000800', 'RAMPE', 25, '010+000', '010+300'),
            self::rec('000800', 'RAMPE', 25, '010+200', '010+600'),
            self::rec('000800', 'PENTE', -25, '020+400', '020+000'),
            self::rec('000800', 'RAMPE', 25, 'H+100', '030+000'),
            self::rec('000800', 'RAMPE', 25, '040+000', '040+000'),
            self::rec('000800', 'RAMPE', 24.9, '050+000', '052+000'),
            self::rec('000801', 'RAMPE', 12, '001+000', '001+250'),
            self::rec('000802', 'RAMPE', 12, 'H+100', 'H+200'),
            self::rec('000803', 'RAMPE', 5, '001+000', '009+000'),
            self::rec('000803', 'RAMPE', 7, '004+000', '004+100'),
        ]);

        // 10+000 à 10+600 et 20+000 à 20+400 : 1000 m ; le maximum de 10 pour mille (1+000 à 2+200) a été oublié.
        $this->assertSame(1000, $lines['000800']['steep_m']);
        $this->assertSame(250, $lines['000801']['steep_m']);
        $this->assertSame(0, $lines['000802']['steep_m']);
        $this->assertSame(100, $lines['000803']['steep_m']);
        $this->assertSame(8000, $lines['000803']['covered_m']);
        $this->assertSame(7.0, $lines['000803']['max_grade_permil']);
    }

    public function test_a_short_steep_portion_does_not_replace_wikipedia_unless_the_line_is_almost_all_covered(): void
    {
        $measure = ['value' => 100.0, 'kind' => 'track'];
        $decide = fn (float $value, ?int $steep, int $covered, float $wiki = 10.0, int $min = 500): array => SncfGrades::decide(
            ['max_grade_permil' => $value, 'covered_m' => $covered] + ($steep === null ? [] : ['steep_m' => $steep]), 1000, $wiki, $measure, 0.1, $min);

        $short = $decide(20.0, 499, 500);
        $this->assertSame('kept_wikipedia_short', $short['status']);
        $this->assertFalse($short['retained']);
        $this->assertSame(499, $short['steep_m']);
        $this->assertSame(0.5, $short['covered_share']);
        $long = $decide(20.0, 500, 500);
        $this->assertSame('retained_over_wikipedia', $long['status']);
        $this->assertTrue($long['retained']);
        $this->assertSame(500, $long['steep_m']);
        // Sans longueur connue, pas de contrôle ; avec une longueur minimale de 0, pas de contrôle non plus.
        $unknown = $decide(20.0, null, 500);
        $this->assertSame('retained_over_wikipedia', $unknown['status']);
        $this->assertNull($unknown['steep_m']);
        $this->assertSame('retained_over_wikipedia', $decide(20.0, 0, 500, 10.0, 0)['status']);
        $this->assertSame('kept_wikipedia_short', $decide(20.0, 600, 500, 10.0, 601)['status']);
        $this->assertSame('retained_over_wikipedia', $decide(20.0, 600, 500, 10.0, 600)['status']);
        // Couverture quasi totale (90 %) : la valeur vaut pour toute la ligne, même sur une portion courte.
        $this->assertSame('retained_over_wikipedia', $decide(20.0, 50, 900)['status']);
        $this->assertSame('kept_wikipedia_short', $decide(20.0, 50, 899)['status']);
        // SNCF plus faible que Wikipédia : l'ancienne règle, sans le motif de portion courte.
        $this->assertSame('kept_wikipedia_partial', $decide(5.0, 50, 500)['status']);
        $this->assertSame('retained_over_wikipedia', $decide(5.0, 50, 900)['status']);
        // Les refus d'avant ne portent pas de portion courte.
        $this->assertSame('rejected_coverage', $decide(20.0, 50, 50)['status']);
        $this->assertSame(50, $decide(20.0, 50, 50)['steep_m']);
    }

    public function test_the_first_segment_alone_counts_one_and_a_negative_value_is_absolute(): void
    {
        $lines = SncfGrades::aggregate([self::rec(' 000999 ', 'PENTE', -12.5, null, null)]);

        $this->assertSame(12.5, $lines['000999']['max_grade_permil']);
        $this->assertSame(1, $lines['000999']['segments']);
        $this->assertSame(0, $lines['000999']['covered_m']);
        $this->assertSame('', $lines['000999']['pk_from']);
        $this->assertSame('', $lines['000999']['pk_to']);
    }

    public function test_aggregate_rejects_bad_codes_types_and_values_and_sorts_by_code(): void
    {
        $lines = SncfGrades::aggregate([
            self::rec('000300', 'RAMPE', 5, '001+000', '002+000'),
            self::rec('12345', 'RAMPE', 5, '001+000', '002+000'),
            self::rec('1234567', 'RAMPE', 5, '001+000', '002+000'),
            self::rec('12345A', 'RAMPE', 5, '001+000', '002+000'),
            self::rec("000400\n", 'RAMPE', 5, '001+000', '002+000'),
            self::rec('000500', 'ALIGNEMENT', 5, '001+000', '002+000'),
            self::rec('000500', 'rampe', 5, '001+000', '002+000'),
            self::rec('000600', 'PENTE', null, '001+000', '002+000'),
            self::rec('000600', 'PENTE', 'abc', '001+000', '002+000'),
            self::rec('000100', 'PENTE', '0', '001+000', '002+000'),
            ['type' => 'PENTE', 'valeur' => 3],
        ]);

        $this->assertSame(['000100', '000300', '000400'], array_map(fn ($k): string => str_pad((string) $k, 6, '0', STR_PAD_LEFT), array_keys($lines)));
    }

    public function test_covered_length_ignores_empty_reversed_overlapping_and_lettered_intervals(): void
    {
        $lines = SncfGrades::aggregate([
            self::rec('000700', 'RAMPE', 5, '002+000', '001+000'),
            self::rec('000700', 'RAMPE', 5, '001+500', '003+000'),
            self::rec('000700', 'RAMPE', 5, '005+000', '005+000'),
            self::rec('000700', 'RAMPE', 5, '006+000', '006+100'),
            self::rec('000700', 'RAMPE', 5, '006+100', '006+200'),
            self::rec('000700', 'RAMPE', 5, 'H+100', '007+000'),
            self::rec('000700', 'RAMPE', 5, '008+000', 'H+100'),
            self::rec('000701', 'RAMPE', 5, '010+000', '010+100'),
            self::rec('000701', 'RAMPE', 5, '020+000', '020+100'),
        ]);

        // 1+000 à 3+000 (2000), 6+000 à 6+200 (200) ; l'intervalle de longueur nulle et les PK à lettre ne comptent pas.
        $this->assertSame(2200, $lines['000700']['covered_m']);
        $this->assertSame(200, $lines['000701']['covered_m']);
    }

    public function test_covered_length_merges_intervals_given_out_of_order_and_the_steep_portion_may_have_no_numeric_pk(): void
    {
        $lines = SncfGrades::aggregate([
            self::rec('000900', 'RAMPE', 5, '005+000', '006+000'),
            self::rec('000900', 'RAMPE', 5, '001+000', '002+000'),
            self::rec('000900', 'RAMPE', 5, '001+500', '005+500'),
            self::rec('000901', 'RAMPE', 30, 'H+100', 'H+200'),
            self::rec('000901', 'RAMPE', 10, '001+000', '001+400'),
        ]);

        $this->assertSame(5000, $lines['000900']['covered_m']);
        $this->assertSame(5000, $lines['000900']['steep_m']);
        $this->assertSame(400, $lines['000901']['covered_m']);
        $this->assertSame(0, $lines['000901']['steep_m']);
    }

    public function test_share_in_the_quote_has_one_decimal(): void
    {
        $sncf = ['max_grade_permil' => 12.0, 'type' => 'RAMPE', 'track' => 'V1', 'pk_from' => '001+000', 'pk_to' => '002+000', 'segments' => 1];

        $this->assertStringEndsWith('33,3 % de la ligne', SncfGrades::entry('1', $sncf, 0.3333)['quote']);
        $this->assertStringEndsWith('66,7 % de la ligne', SncfGrades::entry('1', $sncf, 0.6667)['quote']);
        $this->assertStringEndsWith('90 % de la ligne', SncfGrades::entry('1', $sncf, 0.9)['quote']);
    }

    public function test_pk_forms(): void
    {
        $this->assertSame(0, SncfGrades::pk('000+000'));
        $this->assertSame(1, SncfGrades::pk('0+1'));
        $this->assertSame(12005, SncfGrades::pk(' 12+005 '));
        $this->assertSame(-1, SncfGrades::pk('0-1'));
        $this->assertSame(4000 - 250, SncfGrades::pk('4-250'));
        $this->assertSame(999999, SncfGrades::pk('999+999'));
        $this->assertNull(SncfGrades::pk('12+1234'));
        $this->assertNull(SncfGrades::pk('12+'));
        $this->assertNull(SncfGrades::pk('+123'));
        $this->assertNull(SncfGrades::pk('a12+123'));
        $this->assertNull(SncfGrades::pk('12+123b'));
        $this->assertNull(SncfGrades::pk('12*123'));
        $this->assertNull(SncfGrades::pk(12123));
        $this->assertNull(SncfGrades::pk(''));
    }

    public function test_limits_of_plain_lines_and_of_the_plausible_maximum(): void
    {
        // 60 pour mille exactement : toléré partout ; au-delà, seulement sur une ligne de montagne.
        $this->assertSame('retained', self::verdict(60.0, null, ['value' => 60.0, 'kind' => 'terrain']));
        $this->assertSame('rejected_above_limit', self::verdict(60.1, null, ['value' => 60.0, 'kind' => 'terrain']));
        // Montagne : Wikipédia à 40 pour mille exactement, ou mesure sur la voie à 40.
        $this->assertSame('retained_over_wikipedia', self::verdict(70.0, 40.0, ['value' => 70.0, 'kind' => 'terrain']));
        $this->assertSame('rejected_above_limit', self::verdict(70.0, 39.9, ['value' => 70.0, 'kind' => 'terrain']));
        $this->assertSame('retained', self::verdict(70.0, null, ['value' => 40.0, 'kind' => 'track']));
        $this->assertSame('rejected_above_limit', self::verdict(70.0, null, ['value' => 39.9, 'kind' => 'track']));
        $this->assertSame('rejected_above_limit', self::verdict(70.0, null, ['value' => 50.0, 'kind' => 'terrain']));
        $this->assertSame('rejected_above_limit', self::verdict(70.0, null, null));
        // Même en montagne, plus de 120 pour mille est invraisemblable (120 reste admis).
        $this->assertSame('retained', self::verdict(120.0, null, ['value' => 120.0, 'kind' => 'track']));
        $this->assertSame('rejected_above_limit', self::verdict(120.1, null, ['value' => 120.0, 'kind' => 'track']));
    }

    public function test_rejection_order_and_ratio_boundary(): void
    {
        // Rapport : égalité à 3 fois la mesure tolérée, au-delà écartée ; mesure nulle ne contrôle rien.
        $this->assertSame('retained', self::verdict(30.0, null, ['value' => 10.0, 'kind' => 'track']));
        $this->assertSame('rejected_ratio', self::verdict(30.1, null, ['value' => 10.0, 'kind' => 'track']));
        $this->assertSame('retained', self::verdict(30.0, null, ['value' => 0.0, 'kind' => 'track']));
        $this->assertSame('retained', self::verdict(30.0, null, ['value' => -5.0, 'kind' => 'track']));
        // Le rapport passe avant le plancher de 1 pour mille et avant la couverture.
        $this->assertSame('rejected_ratio', self::verdict(0.9, null, ['value' => 0.1, 'kind' => 'track']));
        $this->assertSame('rejected_ratio', self::verdict(30.1, null, ['value' => 10.0, 'kind' => 'track'], 0));
        // Limite haute avant le rapport.
        $this->assertSame('rejected_above_limit', self::verdict(70.0, null, ['value' => 1.0, 'kind' => 'terrain']));
    }

    public function test_floor_of_one_per_mille_and_coverage_boundaries(): void
    {
        $m = ['value' => 1.0, 'kind' => 'track'];
        $this->assertSame('retained', self::verdict(1.0, null, $m));
        $this->assertSame('rejected_below_one', self::verdict(0.99, null, $m));
        $this->assertSame('rejected_below_one', self::verdict(0.99, null, $m, 0, null));
        // Couverture : 10 % exactement suffit, 9,9 % non ; longueur nulle ou négative : pas de part.
        $m = ['value' => 10.0, 'kind' => 'track'];
        $this->assertSame('retained', self::verdict(10.0, null, $m, 100));
        $this->assertSame('rejected_coverage', self::verdict(10.0, null, $m, 99));
        $this->assertSame('rejected_coverage', self::verdict(10.0, null, $m, 500, 0));
        $this->assertSame('rejected_coverage', self::verdict(10.0, null, $m, 500, -1000));
        $this->assertSame('retained', self::verdict(10.0, null, $m, 99, 1000, 0.099));
        $this->assertSame('retained', self::verdict(10.0, null, $m, 100, 1000, 0.1));
        $this->assertSame('rejected_coverage', self::verdict(10.0, null, $m, 100, 1000, 0.101));
    }

    public function test_covered_share_is_rounded_to_three_decimals_capped_at_one_and_null_without_length(): void
    {
        $m = ['value' => 10.0, 'kind' => 'track'];
        $share = fn (int $covered, ?int $length): ?float => SncfGrades::decide(['max_grade_permil' => 10.0, 'covered_m' => $covered], $length, null, $m)['covered_share'];

        $this->assertSame(0.333, $share(333, 1000));
        $this->assertSame(0.667, $share(2, 3));
        $this->assertSame(0.123, $share(1234, 10000));
        $this->assertSame(1.0, $share(5000, 1000));
        $this->assertSame(1.0, $share(1000, 1000));
        $this->assertNull($share(500, null));
        $this->assertNull($share(500, 0));
        $this->assertNull($share(500, -1));
        $this->assertTrue(SncfGrades::decide(['max_grade_permil' => 10.0, 'covered_m' => 500], 1000, null, $m)['retained']);
        $this->assertFalse(SncfGrades::decide(['max_grade_permil' => 10.0, 'covered_m' => 5], 1000, null, $m)['retained']);
    }

    public function test_agreement_with_wikipedia_and_what_is_retained(): void
    {
        $m = ['value' => 100.0, 'kind' => 'track'];
        // Tolérance : 2 pour mille ou 20 % de Wikipédia, le plus grand des deux, bornes comprises.
        $this->assertSame('kept_wikipedia_agree', self::verdict(11.0, 9.0, $m));
        $this->assertSame('retained_over_wikipedia', self::verdict(11.1, 9.0, $m));
        $this->assertSame('kept_wikipedia_agree', self::verdict(30.0, 25.0, $m));
        $this->assertSame('kept_wikipedia_agree', self::verdict(20.0, 25.0, $m));
        $this->assertSame('kept_wikipedia_partial', self::verdict(19.9, 25.0, $m));
        $this->assertSame('retained_over_wikipedia', self::verdict(30.1, 25.0, $m));
        // SNCF plus faible : gardée seulement si elle couvre 90 % ou plus de la ligne.
        $this->assertSame('kept_wikipedia_partial', self::verdict(10.0, 20.0, $m, 899));
        $this->assertSame('retained_over_wikipedia', self::verdict(10.0, 20.0, $m, 900));
        $this->assertSame('retained_over_wikipedia', self::verdict(10.0, 20.0, $m, 2000));
        $retained = fn (float $v, float $w, int $c): bool => SncfGrades::decide(['max_grade_permil' => $v, 'covered_m' => $c], 1000, $w, ['value' => 100.0, 'kind' => 'track'])['retained'];
        $this->assertFalse($retained(10.0, 10.0, 500));
        $this->assertFalse($retained(10.0, 20.0, 500));
        $this->assertTrue($retained(25.0, 10.0, 500));
        $this->assertTrue($retained(10.0, 20.0, 900));
    }

    public function test_entry_fields_and_quote_forms(): void
    {
        $sncf = ['max_grade_permil' => 12.345, 'type' => 'PENTE', 'track' => '476000-1-V1', 'pk_from' => '001+000', 'pk_to' => '002+000', 'segments' => 2];

        $entry = SncfGrades::entry('476000', $sncf, 0.5);

        $this->assertSame([
            'max_grade_permil' => 12.35,
            'provider' => 'sncf',
            'license' => 'ODbL 1.0',
            'attribution' => 'SNCF Réseau',
            'source_url' => SncfGrades::PAGE.'476000',
            'source_title' => SncfGrades::TITLE,
            'quote' => 'PENTE 12,35 ‰ ; voie 476000-1-V1 ; PK 001+000 à 002+000 ; 2 segments, 50 % de la ligne',
            'covered_share' => 0.5,
        ], $entry);
        $this->assertStringContainsString('1 segment,', SncfGrades::entry('1', ['segments' => 1] + $sncf, 0.5)['quote']);
        $this->assertStringContainsString('10,5 % de la ligne', SncfGrades::entry('1', $sncf, 0.105)['quote']);
        $this->assertStringContainsString(', 100 % de la ligne', SncfGrades::entry('1', $sncf, 1.0)['quote']);
        $this->assertStringContainsString(', 0 % de la ligne', SncfGrades::entry('1', $sncf, 0.0)['quote']);
        $this->assertStringContainsString(', 7,3 % de la ligne', SncfGrades::entry('1', $sncf, 0.073)['quote']);
        $this->assertStringContainsString('RAMPE 20 ‰', SncfGrades::entry('1', ['max_grade_permil' => 20.0, 'type' => 'RAMPE'] + $sncf, 0.5)['quote']);
    }

    public function test_render_exact_layout(): void
    {
        $out = SncfGrades::render(['b' => ['x' => 1.5, 'y' => "l'a"], 'a' => ['x' => 2]], '2026-01-20');

        $this->assertStringStartsWith("<?php\n\n/**\n * Déclivités les plus fortes du jeu SNCF", $out);
        $this->assertStringContainsString('jeu traité par SNCF le 2026-01-20.', $out);
        $this->assertStringContainsString("return [\n    'a' => [\n        'x' => 2,\n    ],\n    'b' => [\n        'x' => 1.5,\n        'y' => 'l\\'a',\n    ],\n];\n", $out);
        $this->assertStringEndsWith("];\n", $out);
        $this->assertStringContainsString("\n */\nreturn [", $out);
        $this->assertStringNotContainsString("\u{2014}", $out);
    }

    public function test_render_of_no_entry_is_an_empty_array(): void
    {
        $this->assertStringEndsWith("return [\n];\n", SncfGrades::render([], '2026-01-20'));
    }
}
