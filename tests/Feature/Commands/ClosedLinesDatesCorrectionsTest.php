<?php

namespace Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * database/data/line_event_corrections_lignes_fermees.php : ouvertures, fermetures et déclassements de lignes
 * fermées, restés à l'année dans l'import, datés au jour d'après l'article de la ligne (révision citée).
 */
class ClosedLinesDatesCorrectionsTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private const FILE = 'line_event_corrections_lignes_fermees.php';

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

    public function test_at_least_one_hundred_facts_are_dated_to_the_day_without_duplicates(): void
    {
        $corrections = $this->corrections();
        $this->assertGreaterThanOrEqual(100, count($corrections));
        $seen = [];
        foreach ($corrections as $c) {
            $this->assertSame('add', $c['action']);
            $this->assertSame('day', $c['precision']);
            $this->assertContains($c['type'], ['opening', 'opening_passenger', 'closure', 'closure_passenger', 'closure_freight', 'declassification', 'reopening', 'reopening_freight']);
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
            $this->assertMatchesRegularExpression('/(?<!\d)'.(int) $d.'(?:er)?\s+'.self::MONTHS[(int) $m].'/u', $quote, "{$label} : jour et mois absents de la citation");
            $this->assertStringContainsString($y, $quote, $label);
            $this->assertLessThanOrEqual(300, mb_strlen($quote), $label);
            $this->assertNotSame('', trim((string) $c['reason']), $label);
        }
    }

    public function test_no_correction_contains_a_long_dash(): void
    {
        $this->assertDoesNotMatchRegularExpression('/[\x{2014}\x{2013}]|(?<![-\w!<])--(?![-\w>])/u', (string) file_get_contents(database_path('data/'.self::FILE)));
    }

    public function test_a_year_closure_gets_its_day_and_its_section(): void
    {
        $rows = array_values(array_filter($this->corrections(), fn (array $c): bool => $c['line'] === '259000'));
        $this->assertNotEmpty($rows);
        $line = $this->rfnLine('259000', 'Ligne de Saint-Just-en-Chaussée à Douai');
        DB::table('line_events')->insert([
            'line_id' => $line->id, 'type' => 'closure_passenger', 'event_date' => '1970-01-01', 'date_precision' => 'year',
            'description' => 'Import', 'source' => 'wikipedia', 'confidence' => 'medium', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->useDataFile(self::FILE, $rows);

        $this->artisan('vff:apply-event-corrections', ['--line' => '259000'])->assertSuccessful();

        $day = DB::table('line_events')->where('line_id', $line->id)->where('source', 'manual')->where('date_precision', 'day')->where('type', 'closure_passenger')->first();
        $this->assertNotNull($day);
        $this->assertSame('1970-04-01', $day->event_date);
        $this->assertSame(['Montdidier', 'Cambrai'], [$day->section_from, $day->section_to]);
        $this->assertSame(1, DB::table('line_events')->where('line_id', $line->id)->where('source', 'wikipedia')->count(), "l'événement d'année importé reste");
    }
}
