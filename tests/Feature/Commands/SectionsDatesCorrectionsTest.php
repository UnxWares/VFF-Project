<?php

namespace Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * database/data/line_event_corrections_sections_dates.php : ouvertures de lignes voyageurs encore ouvertes dont
 * l'import ne connaissait que l'année, datées au jour d'après l'article de la ligne (révision citée).
 */
class SectionsDatesCorrectionsTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private const FILE = 'line_event_corrections_sections_dates.php';

    private const MONTHS = [1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin',
        7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre'];

    /** @return list<array<string, mixed>> */
    private function corrections(): array
    {
        return require database_path('data/'.self::FILE);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        $this->era(2030, current: true);
    }

    public function test_at_least_sixty_openings_are_dated_to_the_day_and_only_openings(): void
    {
        $corrections = $this->corrections();
        $this->assertGreaterThanOrEqual(60, count($corrections));
        $seen = [];
        foreach ($corrections as $c) {
            $this->assertSame('add', $c['action']);
            $this->assertSame('day', $c['precision']);
            $this->assertContains($c['type'], ['opening', 'opening_passenger', 'opening_freight'], $c['line'].' '.$c['date']);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $c['date']);
            $this->assertNotNull(date_create($c['date']), $c['date']);
            $key = $c['line'].'|'.$c['date'].'|'.$c['type'].'|'.($c['from'] ?? '').'|'.($c['to'] ?? '');
            $this->assertArrayNotHasKey($key, $seen, 'doublon '.$key);
            $seen[$key] = true;
        }
    }

    public function test_every_correction_cites_a_revision_and_a_quote_that_states_its_day(): void
    {
        foreach ($this->corrections() as $c) {
            $label = $c['line'].' '.$c['date'];
            $this->assertMatchesRegularExpression('#^https://fr\.wikipedia\.org/w/index\.php\?title=[^&]+&oldid=\d+$#', $c['source_url'], $label);
            [$y, $m, $d] = explode('-', $c['date']);
            $quote = (string) $c['quote'];
            $this->assertMatchesRegularExpression('/(?<!\d)'.(int) $d.'(?:er)?\s+'.self::MONTHS[(int) $m].'/u', $quote, "{$label} : le jour et le mois ne sont pas dans la citation");
            $this->assertStringContainsString($y, $quote, $label);
            $this->assertLessThanOrEqual(300, mb_strlen($quote), $label);
            $this->assertNotSame('', trim((string) $c['reason']), $label);
        }
    }

    public function test_no_correction_contains_a_long_dash(): void
    {
        $this->assertDoesNotMatchRegularExpression('/[\x{2014}\x{2013}]|(?<![-\w!<])--(?![-\w>])/u', (string) file_get_contents(database_path('data/'.self::FILE)));
    }

    public function test_a_year_opening_gets_its_sections_dated_to_the_day(): void
    {
        $by = [];
        foreach ($this->corrections() as $c) {
            $by[$c['line']][] = $c;
        }
        $line = $this->rfnLine('070000', 'Ligne de Paris-Est à Strasbourg-Ville');
        DB::table('line_events')->insert([
            'line_id' => $line->id, 'type' => 'opening', 'event_date' => '1849-01-01', 'date_precision' => 'year',
            'description' => 'Import', 'source' => 'wikipedia', 'confidence' => 'medium', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->useDataFile(self::FILE, $by['070000']);

        $this->artisan('vff:apply-event-corrections', ['--line' => '070000'])->assertSuccessful();

        $days = DB::table('line_events')->where('line_id', $line->id)->where('source', 'manual')->where('date_precision', 'day')->orderBy('event_date')->get();
        $this->assertCount(count($by['070000']), $days);
        $this->assertSame('1849-07-05', $days->first()->event_date);
        $this->assertSame(['Paris-Est', 'Meaux'], [$days->first()->section_from, $days->first()->section_to]);
        $this->assertSame('1852-08-12', $days->last()->event_date);
        $this->assertSame(1, DB::table('line_events')->where('line_id', $line->id)->where('source', 'wikipedia')->count(), 'l\'événement d\'année importé reste');
    }
}
