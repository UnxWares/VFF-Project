<?php

namespace Tests\Feature\Commands;

use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:import-wikidata-lines (Wikidata CC0, source la moins fiable de la
 * cascade) : rattachement par code RFN, complément des seuls champs vides,
 * événements datés (précision, confiance), rejouable, rapports. Rejoué depuis
 * un cache local (--from-cache) : aucune requête réseau.
 */
class WikidataLinesCommandTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private const QID = 'Q3239771';

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        $this->era(2030, current: true);
        $this->line = $this->rfnLine('482000', 'Ligne de Carhaix à Morlaix');
    }

    /** @param  array<string, mixed>  $value */
    private static function statement(string $pid, mixed $value, array $extra = []): array
    {
        return ['mainsnak' => ['snaktype' => 'value', 'property' => $pid, 'datavalue' => ['value' => $value]], 'rank' => 'normal',
            'id' => self::QID.'$'.$pid.'-'.md5(json_encode($value))] + $extra;
    }

    /** @return array<string, mixed> */
    private function entity(bool $withClosure = true): array
    {
        $claims = [
            'P1671' => [self::statement('P1671', '482 000')],
            'P1619' => [self::statement('P1619', ['time' => '+1891-09-28T00:00:00Z', 'precision' => 11],
                ['references' => [['snaks' => ['P854' => [['datavalue' => ['value' => 'https://example.org/source']]]]]]])],
            'P2043' => [self::statement('P2043', ['amount' => '+49', 'unit' => 'http://www.wikidata.org/entity/Q828224'])],
        ];
        if ($withClosure) {
            $claims['P3999'] = [self::statement('P3999', ['time' => '+1967-00-00T00:00:00Z', 'precision' => 9])];
        }

        return [
            'labels' => ['fr' => ['value' => 'ligne de Carhaix à Morlaix']],
            'sitelinks' => ['frwiki' => ['title' => 'Ligne de Carhaix à Morlaix']],
            'claims' => $claims,
        ];
    }

    private function writeCache(bool $withClosure = true): void
    {
        $dir = storage_path('app/sources/wikidata');
        File::ensureDirectoryExists($dir);
        File::put($dir.'/items.json', json_encode(['fetched_at' => '2026-10-15 10:00:00', 'items' => [self::QID => ['Q728937']]]));
        File::put($dir.'/entities.json', json_encode([self::QID => $this->entity($withClosure)]));
        File::put($dir.'/refs.json', json_encode([]));
        File::put($dir.'/stations.json', json_encode([self::QID => []]));
        File::put($dir.'/admin.json', json_encode([]));
    }

    /** @return list<array<string, mixed>> */
    private function events(): array
    {
        return DB::table('line_events')->where('line_id', $this->line->id)->where('source', 'wikidata')->orderBy('event_date')
            ->get(['type', 'event_date', 'date_precision', 'confidence', 'section_resolution'])->map(fn ($e) => (array) $e)->all();
    }

    public function test_item_is_linked_by_rfn_code_and_fills_empty_fields_only(): void
    {
        Http::fake();
        $this->writeCache();
        $this->line->update(['wikipedia_title' => 'Titre saisi à la main']);

        $this->artisan('vff:import-wikidata-lines', ['--from-cache' => true])->assertSuccessful();

        $line = $this->line->fresh();
        $this->assertSame(self::QID, json_decode($line->getRawOriginal('alt_names'), true)['wikidata']);
        $this->assertSame(49000, $line->length_official_m, 'champ vide : complété');
        $this->assertSame('Titre saisi à la main', $line->wikipedia_title, 'champ déjà rempli : jamais écrasé');
        $this->assertSame([
            ['type' => 'opening', 'event_date' => '1891-09-28', 'date_precision' => 'day', 'confidence' => 'high', 'section_resolution' => 'whole_line'],
            ['type' => 'closure', 'event_date' => '1967-01-01', 'date_precision' => 'year', 'confidence' => 'low', 'section_resolution' => 'whole_line'],
        ], $this->events(), 'date au jour sourcée : confiance haute ; année seule : basse');

        $staging = DB::table('wikidata_lines')->where('qid', self::QID)->first();
        $this->assertSame([$this->line->id], [(int) $staging->line_id]);
        foreach (['wikidata-conflits.csv', 'wikidata-lignes-absentes.csv'] as $report) {
            $this->assertFileExists(storage_path('app/reports/'.$report));
        }
        $this->assertStringContainsString('Titre saisi à la main', File::get(storage_path('app/reports/wikidata-conflits.csv')), 'écart signalé, pas écrasé');
        Http::assertNothingSent();
    }

    public function test_import_is_replayable_and_drops_events_no_longer_produced(): void
    {
        $this->writeCache();
        DB::table('line_events')->insert(['line_id' => $this->line->id, 'type' => 'opening', 'event_date' => '1891-01-01', 'source' => 'wikipedia',
            'description' => 'Ouverture (Wikipédia)', 'created_at' => now(), 'updated_at' => now()]);
        $this->artisan('vff:import-wikidata-lines', ['--from-cache' => true])->assertSuccessful();
        $before = $this->snapshot(['line_events', 'lines'], ['id']);

        $this->artisan('vff:import-wikidata-lines', ['--from-cache' => true])->assertSuccessful();
        $this->assertSame($before, $this->snapshot(['line_events', 'lines'], ['id']), 'second passage : rien ne change');

        // La date de fermeture disparaît de Wikidata : l'événement wikidata aussi, pas celui de Wikipédia.
        $this->writeCache(withClosure: false);
        $this->artisan('vff:import-wikidata-lines', ['--from-cache' => true])->assertSuccessful();
        $this->assertSame(['opening'], array_column($this->events(), 'type'));
        $this->assertTrue(DB::table('line_events')->where('source', 'wikipedia')->exists());
    }

    public function test_dry_run_and_missing_cache(): void
    {
        $this->writeCache();
        $before = $this->snapshot(['line_events', 'lines', 'wikidata_lines']);

        $this->artisan('vff:import-wikidata-lines', ['--from-cache' => true, '--dry-run' => true])->assertSuccessful();
        $this->assertSame($before, $this->snapshot(['line_events', 'lines', 'wikidata_lines']));

        File::delete(storage_path('app/sources/wikidata/items.json'));
        $this->artisan('vff:import-wikidata-lines', ['--from-cache' => true])
            ->expectsOutputToContain('Cache absent')
            ->assertFailed();
    }
}
