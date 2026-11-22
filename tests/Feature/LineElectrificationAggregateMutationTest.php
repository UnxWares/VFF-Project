<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use App\Services\LineStats;
use App\Services\Traction\LineElectrificationAggregate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Compteurs, libellés et tension du rapport de l'agrégation d'électrification. */
class LineElectrificationAggregateMutationTest extends TestCase
{
    use RefreshDatabase;

    private Era $era;

    protected function setUp(): void
    {
        parent::setUp();
        $this->era = Era::factory()->current()->create(['year' => 2030]);
    }

    private function line(?string $code, string $name, bool $flag, ?int $volt): Line
    {
        return Line::factory()->create(['code' => $code, 'name' => $name, 'electrified' => $flag, 'voltage_v' => $volt]);
    }

    private function segment(Line $line, float $lon1, float $lon2, bool $electrified, ?string $source, int $volt = 25000): void
    {
        DB::table('line_segments')->insert([
            'line_id' => $line->id, 'era_id' => $this->era->id, 'status' => 'in_service', 'traffic_type' => 'mixed',
            'electrified' => $electrified, 'voltage_v' => $electrified ? $volt : null, 'electrification_source' => $source,
            'geom' => DB::raw(sprintf("ST_GeomFromText('LINESTRING(%F 46, %F 46)', 4326)", $lon1, $lon2)),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_the_report_counts_unchanged_lines_voltage_changes_and_skips_silent_lines_without_stopping(): void
    {
        // Rien ne change : déjà électrifiée à la même tension.
        for ($i = 1; $i <= 3; $i++) {
            $same = $this->line("10000$i", "Stable $i", true, 25000);
            $this->segment($same, 5.0, 5.4, true, 'sncf', 25000);
            // Ligne muette : valeur conservée, comptée « inchangée », sans arrêter la boucle.
            $silent = $this->line("20000$i", "Muette $i", true, 1500);
            $this->segment($silent, 5.0, 5.4, false, 'none');
            // Tension qui change seule.
            $volt = $this->line("30000$i", "Tension $i", true, 1500);
            $this->segment($volt, 5.0, 5.4, true, 'osm', 25000);
            // Devient électrifiée.
            $up = $this->line("40000$i", "Monte $i", false, null);
            $this->segment($up, 5.0, 5.4, true, 'sncf', 1500);
        }

        $r = (new LineElectrificationAggregate)->apply($this->era->id);

        $this->assertSame(12, $r['lines']);
        $this->assertSame(6, $r['unchanged']);
        $this->assertSame(3, $r['voltage_changed']);
        $this->assertCount(3, $r['to_true']);
        $this->assertSame([], $r['to_false']);
        $this->assertSame(25000, Line::where('code', '300001')->value('voltage_v'));
        $this->assertSame(1500, Line::where('code', '400003')->value('voltage_v'));
        $this->assertSame(1500, Line::where('code', '200002')->value('voltage_v'), 'ligne muette intacte');
    }

    public function test_a_line_without_a_code_is_labelled_by_its_name_alone(): void
    {
        $line = $this->line(null, 'Sans code', false, null);
        $this->segment($line, 5.0, 5.4, true, 'sncf');

        $r = (new LineElectrificationAggregate)->apply($this->era->id);

        $this->assertSame(['Sans code'], $r['to_true']);
    }

    public function test_the_threshold_is_met_at_equality_and_the_pure_decision_handles_its_bounds(): void
    {
        $this->assertNull(LineElectrificationAggregate::decide(0.0, 0.0, true));
        $this->assertNull(LineElectrificationAggregate::decide(100.0, 100.0, false));
        $this->assertTrue(LineElectrificationAggregate::decide(100.0, 100.0, true));
        $this->assertFalse(LineElectrificationAggregate::decide(100.0, 0.0, true));
        $pct = LineStats::MARGINAL_ELECTRIFIED_PCT;
        $this->assertTrue(LineElectrificationAggregate::decide(100.0, (float) $pct, true));
        $this->assertFalse(LineElectrificationAggregate::decide(100.0, $pct - 0.01, true));
    }
}
