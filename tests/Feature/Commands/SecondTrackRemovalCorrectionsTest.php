<?php

namespace Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * Corrections de database/data/line_event_corrections_donnees.php relevées par le quatrième contrôle à la main des
 * audits : six « dépose » qui sont celles d'UNE des deux voies d'une ligne restée en service (611000 Limoges -
 * Périgueux en 1962 : « la deuxième voie a été déposée entre Nexon et Périgueux »). Appliquée à la ligne
 * reconstituée, chaque correction neutralise cette seule dépose.
 */
class SecondTrackRemovalCorrectionsTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private const FILE = 'line_event_corrections_donnees.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        $this->era(2030, current: true);
    }

    /** @return iterable<string, array{0: string, 1: string, 2: ?string, 3: string}> */
    public static function secondTrackRemovals(): iterable
    {
        yield 'Limoges - Périgueux' => ['611000', '1962-01-01', 'Nexon', 'places'];
        yield 'Paray-le-Monial - Givors' => ['775000', '1995-01-01', 'Lozanne', 'places'];
        yield 'Valence - Moirans' => ['908000', '1959-01-01', 'Romans', 'places'];
        yield 'La Ferté-Hauterive - Gannat' => ['789000', '1941-01-01', null, 'unresolved'];
        yield 'Jessains - Sorcy' => ['015000', '1942-01-01', null, 'unresolved'];
        yield 'Arches - Saint-Dié' => ['062000', '1944-01-01', null, 'whole_line'];
    }

    #[DataProvider('secondTrackRemovals')]
    public function test_the_removal_of_one_of_two_tracks_is_not_a_dismantling(string $code, string $date, ?string $from, string $resolution): void
    {
        $corrections = array_values(array_filter(require database_path('data/'.self::FILE), fn (array $c) => $c['line'] === $code));
        $this->assertCount(1, $corrections);
        $this->assertStringContainsString('oldid=', $corrections[0]['source_url']);
        $this->assertMatchesRegularExpression('/voie|voies/u', $corrections[0]['quote']);
        $line = $this->rfnLine($code, 'Ligne d\'essai '.$code);
        $event = fn (string $source, string $type, string $on) => DB::table('line_events')->insertGetId([
            'line_id' => $line->id, 'type' => $type, 'event_date' => $on, 'date_precision' => 'year', 'description' => 'Import', 'source' => $source,
            'source_url' => $corrections[0]['source_url'], 'confidence' => 'medium', 'section_resolution' => $resolution, 'section_from' => $from,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $removal = $event('wikipedia', 'dismantling', $date);
        $kept = $event('wikipedia', 'opening', '1880-01-01');
        $this->useDataFile(self::FILE, $corrections);

        $this->artisan('vff:apply-event-corrections', ['--line' => $code])->expectsOutputToContain('neutralisé (1)')->assertSuccessful();

        $this->assertSame('other', DB::table('line_events')->where('id', $removal)->value('type'));
        $this->assertSame('opening', DB::table('line_events')->where('id', $kept)->value('type'));
        $this->assertStringStartsWith($corrections[0]['label'], (string) DB::table('line_events')->where('id', $removal)->value('description'));
    }
}
