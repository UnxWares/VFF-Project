<?php

namespace Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * database/data/line_event_corrections_fermetures_dates.php : fermetures et réouvertures temporaires de lignes
 * (1930-1990) datées au jour d'après l'article de la ligne (révision citée).
 */
class FermeturesDatesCorrectionsTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private const FILE = 'line_event_corrections_fermetures_dates.php';

    private const MONTHS = [1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin',
        7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre'];

    /** @return list<array<string, mixed>> */
    private function corrections(string $action = 'add'): array
    {
        return array_values(array_filter(require database_path('data/'.self::FILE), fn (array $c): bool => $c['action'] === $action));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        $this->era(2030, current: true);
    }

    public function test_at_least_sixty_facts_are_dated_to_the_day_without_duplicates(): void
    {
        $seen = [];
        $adds = $this->corrections();
        $this->assertGreaterThanOrEqual(60, count($adds));
        foreach ($adds as $c) {
            $this->assertSame('day', $c['precision']);
            $this->assertContains($c['type'], ['closure', 'closure_passenger', 'closure_freight', 'reopening_passenger', 'opening_passenger']);
            $this->assertNotNull(date_create($c['date']));
            $this->assertGreaterThanOrEqual('1930', substr($c['date'], 0, 4));
            $key = $c['line'].'|'.$c['date'].'|'.$c['type'];
            $this->assertArrayNotHasKey($key, $seen, 'doublon '.$key);
            $seen[$key] = true;
        }
    }

    public function test_every_fact_cites_a_revision_and_a_quote_that_states_its_day(): void
    {
        foreach ($this->corrections() as $c) {
            $label = $c['line'].' '.$c['date'];
            $this->assertMatchesRegularExpression('#^https://fr\.wikipedia\.org/w/index\.php\?title=[^&]+&oldid=\d+$#', $c['source_url'], $label);
            [$y, $m, $d] = explode('-', $c['date']);
            $quote = (string) $c['quote'];
            $this->assertMatchesRegularExpression('/(?<!\d)0?'.(int) $d.'(?:er)?\s+'.self::MONTHS[(int) $m].'/u', $quote, $label);
            $this->assertStringContainsString($y, $quote, $label);
            $this->assertLessThanOrEqual(300, mb_strlen($quote), $label);
            $this->assertNotSame('', trim((string) $c['reason']), $label);
        }
    }

    public function test_no_long_dash(): void
    {
        $this->assertDoesNotMatchRegularExpression('/[\x{2014}\x{2013}]|(?<![-\w!<])--(?![-\w>])/u', (string) file_get_contents(database_path('data/'.self::FILE)));
    }

    public function test_a_closure_is_added_at_the_day_and_a_wrong_reopening_is_neutralised(): void
    {
        $line = $this->rfnLine('840000', 'Ligne de Bricon à Châtillon-sur-Seine');
        DB::table('line_events')->insert([
            'line_id' => $line->id, 'type' => 'reopening', 'event_date' => '1939-11-30', 'date_precision' => 'day',
            'description' => 'Import', 'source' => 'wikipedia', 'confidence' => 'medium', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->useDataFile(self::FILE, array_values(array_filter(require database_path('data/'.self::FILE), fn (array $c): bool => $c['line'] === '840000')));

        $this->artisan('vff:apply-event-corrections', ['--line' => '840000'])->assertSuccessful();

        $this->assertSame(1, DB::table('line_events')->where('line_id', $line->id)->where('type', 'closure_passenger')->where('event_date', '1939-11-30')->where('source', 'manual')->count());
        $this->assertSame(0, DB::table('line_events')->where('line_id', $line->id)->where('type', 'reopening')->where('event_date', '1939-11-30')->count());
    }
}
