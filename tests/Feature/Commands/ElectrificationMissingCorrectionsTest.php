<?php

namespace Tests\Feature\Commands;

use App\Models\Line;
use App\Services\Materials\ElectrificationBound;
use App\Services\Traction\LineElectrificationHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * database/data/line_event_corrections_electrification_missing.php : électrifications plus anciennes absentes de la
 * base (conflits de vff:material-bound-conflicts), cas contradictoires tranchés et jours trouvés. Chaque ajout se lit
 * comme une mise sous tension avec la tension de sa source, porte un permalien et une citation, et la borne
 * d'électrification des trois lignes en conflit recule bien avant la série sourcée.
 */
class ElectrificationMissingCorrectionsTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private const FILE = 'line_event_corrections_electrification_missing.php';

    /** @return list<array<string, mixed>> */
    private function corrections(): array
    {
        return require database_path('data/'.self::FILE);
    }

    private function voltageOf(string $reason): int
    {
        return match (true) {
            str_contains($reason, '25 kV') => 25000,
            str_contains($reason, '12 kV') => 12000,
            str_contains($reason, '1 500 V') => 1500,
            str_contains($reason, '750 V') => 750,
            default => 0,
        };
    }

    public function test_every_addition_is_a_sourced_commissioning_with_a_voltage(): void
    {
        $all = $this->corrections();
        $this->assertGreaterThanOrEqual(25, count($all));
        $seen = [];
        foreach ($all as $i => $c) {
            $this->assertSame('add', $c['action'], "#{$i}");
            $this->assertSame('electrification', $c['type'], "#{$i}");
            $this->assertContains($c['precision'], ['day', 'month', 'year'], "#{$i}");
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $c['date']);
            $this->assertLessThanOrEqual(date('Y-m-d'), $c['date']);
            $this->assertStringContainsString('oldid=', $c['source_url'], "#{$i}");
            $this->assertNotSame('', trim($c['quote']), "#{$i}");
            $this->assertGreaterThan(0, $this->voltageOf($c['reason']), "#{$i} tension dans le motif");
            $this->assertNotEmpty($c['from'], "#{$i}");
            $this->assertNotEmpty($c['to'], "#{$i}");
            $key = implode('|', [$c['line'], $c['date'], $c['from'], $c['to']]);
            $this->assertArrayNotHasKey($key, $seen, "doublon {$key}");
            $seen[$key] = true;
        }
    }

    public function test_the_additions_do_not_duplicate_the_day_dates_of_the_first_file(): void
    {
        $first = require database_path('data/line_event_corrections_electrification_dates.php');
        $keys = [];
        foreach ($first as $c) {
            $keys[$c['line'].'|'.$c['date'].'|'.($c['from'] ?? '')] = true;
        }
        foreach ($this->corrections() as $c) {
            $this->assertArrayNotHasKey($c['line'].'|'.$c['date'].'|'.$c['from'], $keys, "{$c['line']} {$c['date']}");
        }
    }

    public function test_every_day_addition_reads_as_a_commissioning(): void
    {
        foreach ($this->corrections() as $i => $c) {
            $fact = LineElectrificationHistory::read([
                'event_date' => $c['date'], 'date_precision' => $c['precision'], 'source' => 'manual', 'confidence' => 'high',
                'source_quote' => $c['quote'], 'description' => 'Électrification (correction sourcée : '.$c['reason'].')',
                'section_from' => $c['from'], 'section_to' => $c['to'],
                'section_pk_start_m' => null, 'section_pk_end_m' => null, 'section_resolution' => 'pending',
            ], '2026-11-23');
            $label = "#{$i} {$c['line']} {$c['date']}";
            $this->assertNotNull($fact, $label);
            $this->assertContains($fact['kind'], ['electrification', 'conversion'], $label);
            $this->assertSame($this->voltageOf($c['reason']), $fact['voltage_v'], $label);
        }
    }

    /** Les trois conflits : sans le fichier, la borne reste à 1967, 1987 et 2012 ; avec lui, elle précède la série sourcée. */
    public function test_the_electrification_bound_of_the_three_conflicting_lines_moves_back(): void
    {
        $this->sandboxStorage();
        $this->era(2030, current: true);
        $existing = [
            ['340000', '1967-02-28', 'day', 'Sotteville', 'Motteville', 'high'],
            ['420000', '1987-04-23', 'day', 'Rennes', 'Saint-Brieuc', 'medium'],
            ['909000', '2012-01-01', 'year', 'Gières', 'Montmélian', 'medium'],
        ];
        foreach ($existing as [$code, $date, $precision, $from, $to, $confidence]) {
            $line = $this->rfnLine($code, 'Ligne '.$code);
            DB::table('line_events')->insert([
                'line_id' => $line->id, 'type' => 'electrification', 'event_date' => $date, 'date_precision' => $precision,
                'description' => 'Électrification', 'source' => 'wikipedia', 'confidence' => $confidence,
                'section_from' => $from, 'section_to' => $to, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $first = require database_path('data/line_event_corrections_electrification_dates.php');
        $merged = array_merge($first, $this->corrections());
        foreach (array_unique(array_column($merged, 'line')) as $code) {
            if (! Line::query()->where('code', $code)->exists()) {
                $this->rfnLine((string) $code, 'Ligne '.$code);
            }
        }
        // Le 1937 de Paris-Montparnasse - Le Mans est dans le premier fichier : les deux ensemble.
        $this->useDataFile(self::FILE, $merged);

        $this->artisan('vff:apply-event-corrections')->assertSuccessful();

        $bound = fn (string $code): ?array => ElectrificationBound::lineDate(Line::query()->where('code', $code)->firstOrFail());
        // BB 17000 : 1966 (année) ne précède plus la borne ; BB 22200 : 1983 (année) ; BB 25500 : conflit retiré côté période.
        $this->assertSame('1966-01-11', $bound('340000')['date']);
        $this->assertSame('1937-05-08', $bound('420000')['date']);
        $this->assertSame('1991-09-01', $bound('909000')['date']);
        $this->assertSame('month', $bound('909000')['precision']);
        $this->assertFalse(ElectrificationBound::startsBefore(['start_date' => '1966-01-01', 'start_precision' => 'year'], $bound('340000')));
        $this->assertFalse(ElectrificationBound::startsBefore(['start_date' => '1983-01-01', 'start_precision' => 'year'], $bound('420000')));
    }

    public function test_the_real_file_applies_once_and_replays_without_duplicates(): void
    {
        $this->sandboxStorage();
        $this->era(2030, current: true);
        foreach (array_unique(array_column($this->corrections(), 'line')) as $code) {
            $this->rfnLine((string) $code, 'Ligne '.$code);
        }
        $this->useDataFile(self::FILE, $this->corrections());

        $this->artisan('vff:apply-event-corrections')->assertSuccessful();
        $this->assertSame(count($this->corrections()), DB::table('line_events')->where('source', 'manual')->where('type', 'electrification')->count());
        $this->artisan('vff:apply-event-corrections')->assertSuccessful();
        $this->assertSame(count($this->corrections()), DB::table('line_events')->where('source', 'manual')->count());
    }

    /** BB 25500 : « depuis septembre 1985 » est antérieur à l'électrification de Grenoble à Gières (septembre 1991) : la période est retirée, pas déplacée. */
    public function test_the_bb_25500_period_before_the_electrification_of_gieres_is_dropped(): void
    {
        $supplements = require database_path('data/line_material_supplements.php');
        $dropped = array_values(array_filter($supplements['dropped_periods'], fn (array $d): bool => $d['line'] === '909000' && $d['material'] === 'Q842202'));
        $this->assertCount(1, $dropped);
        $this->assertSame('1985-09-01', $dropped[0]['start']);
        $gieres = array_values(array_filter($this->corrections(), fn (array $c): bool => $c['line'] === '909000' && $c['to'] === 'Gières'));
        $this->assertCount(1, $gieres);
        $this->assertGreaterThan($dropped[0]['start'], $gieres[0]['date']);
    }
}
