<?php

namespace Tests\Unit\Profiles;

use App\Services\Profiles\SourcedGrades;
use Tests\TestCase;

/** Déclivités sourcées : valeurs rendues, motifs admis, lecture du fichier versionné. */
class SourcedGradesMutationTest extends TestCase
{
    public function test_the_constants_are_the_documented_ones(): void
    {
        $this->assertSame(['terrain_only', 'tunnel', 'interpolated'], SourcedGrades::REPLACES);
        $this->assertSame('CC BY-SA 4.0', SourcedGrades::LICENSE);
    }

    public function test_all_is_the_versioned_file_and_stays_the_same_between_calls(): void
    {
        $file = array_replace(require database_path('data/sourced_grades.php'), require database_path('data/sourced_grades_sncf.php'));

        $this->assertSame($file, SourcedGrades::all());
        // Après flush(), les fichiers sont relus : mêmes lignes, même ordre.
        SourcedGrades::flush();
        $this->assertSame(array_keys($file), array_keys(SourcedGrades::all()));
        $this->assertSame(SourcedGrades::all(), SourcedGrades::all());
    }

    public function test_the_published_value_carries_the_entry_title_and_link(): void
    {
        $all = array_filter(SourcedGrades::all(), fn (array $e): bool => $e['provider'] === 'wikipedia');
        $slug = (string) array_key_first($all);
        $entry = $all[$slug];

        $this->assertSame([
            'max_grade_permil' => (float) $entry['max_grade_permil'],
            'covered_share' => null,
            'source' => [
                'provider' => 'wikipedia',
                'title' => $entry['source_title'],
                'url' => $entry['source_url'],
                'license' => 'CC BY-SA 4.0',
                'attribution' => 'Wikipédia',
            ],
        ], SourcedGrades::present($slug, 'tunnel'));
    }

    public function test_an_integer_entry_is_given_as_a_float(): void
    {
        $int = array_filter(SourcedGrades::all(), fn (array $e): bool => is_int($e['max_grade_permil']));
        $this->assertNotEmpty($int);
        $slug = (string) array_key_first($int);

        $grade = SourcedGrades::present($slug, 'interpolated');

        $this->assertIsFloat($grade['max_grade_permil'] ?? null);
        $this->assertSame((float) $int[$slug]['max_grade_permil'], $grade['max_grade_permil']);
    }

    public function test_every_other_reason_gives_nothing(): void
    {
        foreach ([null, '', 'rack', 'Tunnel', 'terrain', 'terrain_only ', 'short', 'none'] as $reason) {
            $this->assertNull(SourcedGrades::present('rfn-405000', $reason), var_export($reason, true));
        }
        $this->assertNull(SourcedGrades::present('', 'tunnel'));
        $this->assertNull(SourcedGrades::present('RFN-405000', 'tunnel'));
    }
}
