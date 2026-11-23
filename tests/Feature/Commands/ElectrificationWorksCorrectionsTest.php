<?php

namespace Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * Corrections de database/data/line_event_corrections_electrification.php qui retirent des « électrifications »
 * les débuts de travaux, décrets et fermetures de gare (relus contre leur article le 23/11/2026) : l'événement
 * est neutralisé, la vraie mise sous tension de la même ligne reste une électrification.
 */
class ElectrificationWorksCorrectionsTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private const FILE = 'line_event_corrections_electrification.php';

    /**
     * Ligne => [date de l'événement de travaux, section de départ]. PHP range un code sans zéro de tête (884000)
     * sous une clé entière, d'où int|string et les conversions (string) ci-dessous.
     *
     * @var array<int|string, array{0: string, 1: ?string}>
     */
    private const WORKS = [
        '001000' => ['2019-01-03', 'Gretz-Armainvilliers'],
        '884000' => ['2005-01-31', null],
        '311000' => ['2009-01-01', 'Rang-du-Fliers - Verton - Berck'],
        '810000' => ['1943-01-01', 'Sète'],
        '834100' => ['2000-01-01', null],
        '886000' => ['1953-01-01', 'Lyon-Saint-Clair'],
        '905000' => ['1979-01-01', null],
        '908000' => ['2012-01-01', null],
        '974000' => ['1974-01-01', null],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        $this->era(2030, current: true);
    }

    private function event(int $lineId, string $date, ?string $from): int
    {
        return DB::table('line_events')->insertGetId([
            'line_id' => $lineId, 'type' => 'electrification', 'event_date' => $date, 'date_precision' => 'day',
            'description' => 'Import', 'source' => 'wikipedia', 'confidence' => 'medium',
            'section_from' => $from, 'section_resolution' => 'resolved', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_works_decrees_and_station_closures_are_not_electrifications(): void
    {
        $corrections = array_values(array_filter(require database_path('data/'.self::FILE), fn (array $c) => ($c['match']['date'] ?? null) === (self::WORKS[$c['line']][0] ?? '')));
        $this->assertCount(count(self::WORKS), $corrections);
        $works = $kept = [];
        foreach (self::WORKS as $code => [$date, $from]) {
            $line = $this->rfnLine((string) $code, 'Ligne '.$code);
            $works[$code] = $this->event($line->id, $date, $from);
            $kept[$code] = $this->event($line->id, '2022-07-03', null);
        }
        $this->useDataFile(self::FILE, $corrections);

        $this->artisan('vff:apply-event-corrections')->assertSuccessful();

        foreach (self::WORKS as $code => $_) {
            $this->assertSame('other', DB::table('line_events')->where('id', $works[$code])->value('type'), (string) $code);
            $this->assertSame('electrification', DB::table('line_events')->where('id', $kept[$code])->value('type'), (string) $code);
        }
    }

    public function test_every_correction_names_its_source_and_a_quote_about_the_event(): void
    {
        foreach (array_filter(require database_path('data/'.self::FILE), fn (array $c) => ($c['match']['date'] ?? null) === (self::WORKS[$c['line']][0] ?? '')) as $c) {
            $this->assertSame('neutralize', $c['action']);
            $this->assertStringContainsString('oldid=', $c['source_url']);
            $this->assertNotSame('', trim($c['quote']));
        }
    }
}
