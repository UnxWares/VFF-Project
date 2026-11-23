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

    /**
     * Les ajouts seuls (le fichier compte aussi une neutralisation : l'inauguration de Creil - Beauvais).
     *
     * @return list<array<string, mixed>>
     */
    private function additions(): array
    {
        return array_values(array_filter($this->corrections(), fn (array $c): bool => $c['action'] === 'add'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        $this->era(2030, current: true);
    }

    public function test_at_least_one_hundred_and_thirty_openings_are_dated_to_the_day_and_only_openings(): void
    {
        $corrections = $this->additions();
        $this->assertGreaterThanOrEqual(130, count($corrections));
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
        foreach ($this->additions() as $c) {
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
        foreach ($this->additions() as $c) {
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

    public function test_the_second_pass_covers_the_lines_left_without_a_day_and_neutralises_a_festive_inauguration(): void
    {
        $lines = array_unique(array_column($this->additions(), 'line'));
        foreach (['146000', '172000', '261000', '372000', '441000', '584000', '650000', '707000', '843000', '486000', '316000'] as $code) {
            $this->assertContains($code, $lines, $code);
        }
        $neutralised = array_values(array_filter($this->corrections(), fn (array $c): bool => $c['action'] === 'neutralize'));
        $this->assertCount(1, $neutralised);
        $this->assertSame('316000', $neutralised[0]['line']);
        $this->assertSame(['source' => 'wikipedia', 'type' => 'opening', 'date' => '1857-06-28'], $neutralised[0]['match']);
        $this->assertMatchesRegularExpression('/28 juin 1857/u', (string) $neutralised[0]['quote']);
    }

    public function test_creil_beauvais_keeps_its_service_date_and_loses_its_inauguration_as_an_opening(): void
    {
        $line = $this->rfnLine('316000', 'Ligne de Creil à Beauvais');
        DB::table('line_events')->insert([
            'line_id' => $line->id, 'type' => 'opening', 'event_date' => '1857-06-28', 'date_precision' => 'day',
            'description' => 'Import', 'source' => 'wikipedia', 'confidence' => 'medium', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->useDataFile(self::FILE, array_values(array_filter($this->corrections(), fn (array $c): bool => $c['line'] === '316000')));

        $this->artisan('vff:apply-event-corrections', ['--line' => '316000'])->assertSuccessful();

        $this->assertSame(1, DB::table('line_events')->where('line_id', $line->id)->where('type', 'other')->where('event_date', '1857-06-28')->count());
        $this->assertSame(1, DB::table('line_events')->where('line_id', $line->id)->where('type', 'opening')->where('event_date', '1857-09-01')->where('source', 'manual')->count());
        $this->assertSame(0, DB::table('line_events')->where('line_id', $line->id)->where('type', 'opening')->where('event_date', '1857-06-28')->count());
    }
}
