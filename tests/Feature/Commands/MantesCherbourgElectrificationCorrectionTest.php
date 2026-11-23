<?php

namespace Tests\Feature\Commands;

use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * Cas témoin de database/data/line_event_corrections_electrification.php : l'article Wikipédia de Mantes - Cherbourg
 * annonce en 1989 le « début des travaux préparatoires à l'électrification » (Bernay - Lisieux), pas une mise sous
 * tension, et met la ligne en service le 1er juin 1996. L'événement de 1989 est neutralisé ; celui de 1996, juste
 * (jour), reste une électrification.
 */
class MantesCherbourgElectrificationCorrectionTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private const FILE = 'line_event_corrections_electrification.php';

    private const URL = 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Mantes-la-Jolie_%C3%A0_Cherbourg&oldid=241344631';

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        $this->era(2030, current: true);
    }

    private function event(Line $line, string $date, string $precision, ?string $from, ?string $to): int
    {
        return DB::table('line_events')->insertGetId([
            'line_id' => $line->id, 'type' => 'electrification', 'event_date' => $date, 'date_precision' => $precision,
            'description' => 'Import', 'source' => 'wikipedia', 'source_url' => self::URL, 'confidence' => 'medium',
            'section_from' => $from, 'section_to' => $to, 'section_resolution' => 'resolved', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_the_preparatory_works_of_1989_are_not_an_electrification(): void
    {
        $corrections = array_values(array_filter(require database_path('data/'.self::FILE), fn (array $c) => $c['line'] === '366000'));
        $this->assertCount(1, $corrections);
        $this->assertSame(self::URL, $corrections[0]['source_url'], 'même permalien que l\'événement importé');
        $line = $this->rfnLine('366000', 'Ligne de Mantes-la-Jolie à Cherbourg');
        $works = $this->event($line, '1989-01-01', 'year', 'Bernay', 'Lisieux');
        $service = $this->event($line, '1996-06-01', 'day', 'Paris', 'Cherbourg');
        $this->useDataFile(self::FILE, $corrections);

        $this->artisan('vff:apply-event-corrections', ['--line' => '366000'])->expectsOutputToContain('neutralisé (1)')->assertSuccessful();

        $this->assertSame('other', DB::table('line_events')->where('id', $works)->value('type'));
        $this->assertStringContainsString('travaux préparatoires', (string) DB::table('line_events')->where('id', $works)->value('description'));
        $this->assertSame('electrification', DB::table('line_events')->where('id', $service)->value('type'));
        $this->assertSame('day', DB::table('line_events')->where('id', $service)->value('date_precision'));
    }
}
