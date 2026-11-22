<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use App\Services\Traction\LineElectrificationAggregate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * vff:aggregate-line-electrification : `lines.electrified` suit la part des tronçons en service
 * électrifiés de l'ère courante, pas la présence d'un seul tronçon (cas d'Andelot - La Cluse et de
 * Limoges - Angoulême : quelques centaines de mètres en gare sur plus de cent kilomètres).
 */
class LineElectrificationAggregateTest extends TestCase
{
    use RefreshDatabase;

    private Era $era;

    protected function setUp(): void
    {
        parent::setUp();
        $this->era = Era::factory()->current()->create(['year' => 2030]);
    }

    private function line(string $code, bool $flag, ?int $volt): Line
    {
        return Line::factory()->create(['code' => $code, 'name' => 'Ligne '.$code, 'electrified' => $flag, 'voltage_v' => $volt]);
    }

    private function segment(Line $line, float $lon1, float $lon2, bool $electrified, ?string $source, int $volt = 25000, string $status = 'in_service'): void
    {
        DB::table('line_segments')->insert([
            'line_id' => $line->id, 'era_id' => $this->era->id, 'status' => $status, 'traffic_type' => 'mixed',
            'electrified' => $electrified, 'voltage_v' => $electrified ? $volt : null, 'electrification_source' => $source,
            'geom' => DB::raw(sprintf("ST_GeomFromText('LINESTRING(%F 46, %F 46)', 4326)", $lon1, $lon2)),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_a_short_electrified_stretch_does_not_make_the_line_electrified(): void
    {
        $line = $this->line('878000', true, 25000);
        $this->segment($line, 5.00, 5.003, true, 'sncf');   // ~230 m électrifiés
        $this->segment($line, 5.003, 5.50, false, 'sncf');  // ~38 km non électrifiés

        $r = (new LineElectrificationAggregate)->apply($this->era->id);

        $this->assertSame(['878000 Ligne 878000'], $r['to_false']);
        $line->refresh();
        $this->assertFalse($line->electrified);
        $this->assertNull($line->voltage_v);
    }

    public function test_a_substantially_electrified_line_gets_the_dominant_voltage(): void
    {
        $line = $this->line('100000', false, null);
        $this->segment($line, 5.0, 5.4, true, 'sncf', 1500);
        $this->segment($line, 5.4, 5.45, false, 'sncf');

        $r = (new LineElectrificationAggregate)->apply($this->era->id);

        $this->assertSame(['100000 Ligne 100000'], $r['to_true']);
        $line->refresh();
        $this->assertTrue($line->electrified);
        $this->assertSame(1500, $line->voltage_v);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $line = $this->line('878000', true, 25000);
        $this->segment($line, 5.0, 5.5, false, 'sncf');

        $r = (new LineElectrificationAggregate)->apply($this->era->id, false);

        $this->assertCount(1, $r['to_false']);
        $this->assertTrue($line->fresh()->electrified);
    }

    public function test_silent_or_closed_lines_keep_their_value(): void
    {
        $silent = $this->line('200000', true, 1500);
        $this->segment($silent, 5.0, 5.5, false, 'none');
        $closed = $this->line('300000', true, 1500);
        $this->segment($closed, 5.0, 5.5, false, 'status', 25000, 'dismantled');

        (new LineElectrificationAggregate)->apply($this->era->id);

        $this->assertTrue($silent->fresh()->electrified);
        $this->assertTrue($closed->fresh()->electrified);
    }

    public function test_the_command_reports_and_writes(): void
    {
        $line = $this->line('878000', true, 25000);
        $this->segment($line, 5.0, 5.5, false, 'sncf');

        $this->artisan('vff:aggregate-line-electrification')->assertSuccessful();

        $this->assertFalse($line->fresh()->electrified);
    }
}
