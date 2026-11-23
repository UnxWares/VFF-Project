<?php

namespace Tests\Feature\Commands;

use App\Services\Traction\LineElectrificationHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * database/data/line_event_corrections_electrification_dates.php : jour, section et tension des mises sous tension
 * restées à l'année ou au mois. Chaque ajout se lit comme une électrification (ni travaux ni décret), porte sa
 * tension et sa source, et s'applique sans erreur sur la base.
 */
class ElectrificationDatesCorrectionsTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private const FILE = 'line_event_corrections_electrification_dates.php';

    /** @return list<array<string, mixed>> */
    private function corrections(): array
    {
        return require database_path('data/'.self::FILE);
    }

    private function voltageOf(string $reason): int
    {
        return match (true) {
            str_contains($reason, '25 kV') => 25000,
            str_contains($reason, '20 kV') => 20000,
            str_contains($reason, '12 kV') => 12000,
            str_contains($reason, '1 500 V') => 1500,
            str_contains($reason, '750 V') => 750,
            str_contains($reason, '650 V') => 650,
            str_contains($reason, '600 V') => 600,
            default => 0,
        };
    }

    public function test_the_file_holds_at_least_eighty_sourced_day_dates(): void
    {
        $all = $this->corrections();
        $this->assertGreaterThanOrEqual(80, count($all));
        $seen = [];
        foreach ($all as $i => $c) {
            $this->assertSame('add', $c['action'], "#{$i}");
            $this->assertSame('electrification', $c['type'], "#{$i}");
            $this->assertSame('day', $c['precision'], "#{$i}");
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $c['date']);
            $this->assertLessThanOrEqual(date('Y-m-d'), $c['date']);
            $this->assertStringContainsString('oldid=', $c['source_url'], "#{$i}");
            $this->assertNotSame('', trim($c['quote']), "#{$i}");
            $this->assertGreaterThan(0, $this->voltageOf($c['reason']), "#{$i} tension dans le motif");
            $key = implode('|', [$c['line'], $c['date'], $c['from'] ?? '', $c['to'] ?? '']);
            $this->assertArrayNotHasKey($key, $seen, "doublon {$key}");
            $seen[$key] = true;
        }
    }

    public function test_every_addition_reads_as_a_commissioning_with_the_voltage_of_its_source(): void
    {
        foreach ($this->corrections() as $i => $c) {
            $fact = LineElectrificationHistory::read([
                'event_date' => $c['date'], 'date_precision' => 'day', 'source' => 'manual', 'confidence' => 'high',
                'source_quote' => $c['quote'], 'description' => 'Électrification (correction sourcée : '.$c['reason'].')',
                'section_from' => $c['from'] ?? null, 'section_to' => $c['to'] ?? null,
                'section_pk_start_m' => null, 'section_pk_end_m' => null, 'section_resolution' => 'pending',
            ], '2026-11-23');
            $label = "#{$i} {$c['line']} {$c['date']}";
            // Avant 1951 la tension de 20 kV 50 Hz n'entre pas dans la frise (FIRST_AC50_YEAR) : seul cas rejeté.
            if ($this->voltageOf($c['reason']) === 20000 && $c['date'] < '1951-01-01') {
                $this->assertNull($fact, $label);

                continue;
            }
            $this->assertNotNull($fact, $label);
            $this->assertContains($fact['kind'], ['electrification', 'conversion'], $label);
            $this->assertSame($this->voltageOf($c['reason']), $fact['voltage_v'], $label);
            $this->assertSame($c['date'], $fact['date'], $label);
        }
    }

    public function test_the_real_file_applies_and_adds_one_event_per_correction(): void
    {
        $this->sandboxStorage();
        $this->era(2030, current: true);
        $codes = array_unique(array_column($this->corrections(), 'line'));
        foreach ($codes as $code) {
            $this->rfnLine((string) $code, 'Ligne '.$code);
        }
        $this->useDataFile(self::FILE, $this->corrections());

        $this->artisan('vff:apply-event-corrections')->assertSuccessful();

        $this->assertSame(count($this->corrections()), DB::table('line_events')->where('source', 'manual')->where('type', 'electrification')->where('date_precision', 'day')->count());
        // Rejouable : une seconde application ne duplique rien.
        $this->artisan('vff:apply-event-corrections')->assertSuccessful();
        $this->assertSame(count($this->corrections()), DB::table('line_events')->where('source', 'manual')->count());
    }
}
