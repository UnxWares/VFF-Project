<?php

namespace Tests\Feature\Commands;

use App\Models\Line;
use App\Services\ArgentanWebFerro\EngineFacts;
use App\Services\EraTimeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:apply-argentanwebferro : faits relus d'Argentan Web Ferroviaire versés au moteur
 * (line_events, source_stations), au rang décidé (après Wikipédia, avant OpenHistoricalMap),
 * rejouable, avec les faits écartés et les arrêts confirmés de database/data/argentanwebferro_moteur.php.
 */
class ApplyArgentanWebFerroCommandTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private const URL = 'https://argentanwebferro.fr/de-ste-gauburge-a-mortagne-au-perche/';

    private Line $line;

    private int $article;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        $this->line = $this->rfnLine('425000', 'Ligne de Sainte-Gauburge à Mortagne');
        $this->article = (int) DB::table('argentanwebferro_articles')->insertGetId([
            'wp_id' => 1, 'wp_type' => 'page', 'slug' => 'de-ste-gauburge-a-mortagne-au-perche', 'url' => self::URL,
            'title' => 'De Ste Gauburge à Mortagne', 'categories' => '[]', 'is_line_article' => true, 'line_ids' => json_encode([$this->line->id]),
            'line_match' => 'curated', 'content_sha1' => str_repeat('a', 40), 'fetched_at' => now(),
        ]);
    }

    public function test_reviewed_facts_become_ranked_events_and_stops_with_their_link(): void
    {
        $this->fact('1', ['event_type' => 'closure', 'event_date' => '1953-01-01', 'date_precision' => 'year',
            'section_from' => 'Ste Gauburge', 'section_to' => 'Moulins la Marche', 'source_quote' => 'La ligne ferme en 1953.']);
        $this->fact('2', ['event_type' => 'closure_passenger', 'event_date' => '1938-01-01', 'date_precision' => 'year']);
        // Gardés en source : rejeté, période, sans date, accident, une seule borne, sans ligne, écarté.
        $this->fact('3', ['event_type' => 'closure', 'event_date' => '1950-01-01', 'date_precision' => 'year', 'review' => 'rejected']);
        $this->fact('4', ['event_type' => 'closure', 'event_date' => '1966-01-01', 'date_precision' => 'range', 'year_to' => 1971]);
        $this->fact('5', ['event_type' => 'dismantling', 'event_date' => null, 'date_precision' => null]);
        $this->fact('6', ['event_type' => 'accident', 'event_date' => '1920-05-02', 'date_precision' => 'day']);
        $this->fact('7', ['event_type' => 'reopening_passenger', 'event_date' => '1941-01-01', 'date_precision' => 'year', 'section_to' => 'Mortagne']);
        $this->fact('8', ['event_type' => 'closure', 'event_date' => '1936-01-01', 'date_precision' => 'year', 'line_id' => null]);
        $this->fact('9', ['event_type' => 'closure', 'event_date' => '1990-01-01', 'date_precision' => 'year']);
        // Arrêts : connu de VFF (calage), absent confirmé, absent sans confirmation, bifurcation.
        $this->fact('a', ['kind' => 'stop', 'stop_name' => 'Ste Gauburge', 'pk_m' => 0, 'vff_status' => 'présent', 'vff_detail' => 'VFF : Sainte-Gauburge-Sainte-Colombe']);
        $this->fact('b', ['kind' => 'stop', 'stop_name' => 'Moulins la Marche', 'pk_m' => 25100, 'vff_status' => 'absent de VFF']);
        $this->fact('c', ['kind' => 'stop', 'stop_name' => 'Le Rendez-Vous', 'stop_nature' => 'halte', 'pk_m' => 28000, 'vff_status' => 'absent de VFF']);
        $this->fact('d', ['kind' => 'stop', 'stop_name' => 'Bifur de St Sulpice', 'stop_nature' => 'embranchement', 'vff_status' => 'présent']);
        $this->decisions(skip: [$this->fp('9') => 'contredit par Wikipédia et OpenHistoricalMap (1989)'], stops: [$this->fp('b') => [
            'name' => 'Moulins-la-Marche', 'source_url' => 'https://fr.wikipedia.org/w/index.php?title=X&oldid=1', 'quote' => 'gare de Moulins-la-Marche',
            'reason' => 'gare citée par l\'article de la ligne'],
        ]);

        $this->artisan('vff:apply-argentanwebferro')->assertSuccessful();

        $events = DB::table('line_events')->where('source', EngineFacts::SOURCE)->orderBy('event_date')->get();
        $this->assertSame(['closure_passenger', 'closure'], $events->pluck('type')->all());
        $closure = $events[1];
        $this->assertSame('Fermeture (tout trafic) de Sainte-Gauburge à Moulins la Marche', $closure->description);
        $this->assertSame(['Sainte-Gauburge', 'Moulins la Marche', 'pending', 'year', self::URL, 'La ligne ferme en 1953.'],
            [$closure->section_from, $closure->section_to, $closure->section_resolution, $closure->date_precision, $closure->source_url, $closure->source_quote]);
        $this->assertSame('argentanwebferro:'.$this->fp('1'), $closure->fingerprint);
        // Sans portion : toute la ligne.
        $this->assertNull($events[0]->section_resolution);
        $this->assertSame('Fermeture aux voyageurs de la ligne', $events[0]->description);

        $stops = DB::table('source_stations')->where('source', EngineFacts::SOURCE)->orderBy('pk_m')->get();
        $this->assertSame(['Sainte-Gauburge-Sainte-Colombe', 'Moulins-la-Marche'], $stops->pluck('name')->all());
        $this->assertSame($this->line->id, (int) $stops[1]->line_id);
        $props = json_decode($stops[1]->properties, true);
        $this->assertSame(self::URL, $props['source_url']);
        $this->assertSame('gare citée par l\'article de la ligne', $props['confirmation']['reason']);
    }

    public function test_it_is_replayable_and_removes_facts_that_leave(): void
    {
        $this->fact('1', ['event_type' => 'closure', 'event_date' => '1953-01-01', 'date_precision' => 'year',
            'section_from' => 'Mortagne', 'section_to' => 'Soligny la Trappe']);
        $this->fact('2', ['event_type' => 'opening', 'event_date' => '1872-01-01', 'date_precision' => 'year']);
        $this->decisions();
        $this->artisan('vff:apply-argentanwebferro')->assertSuccessful();
        // Portion résolue entre-temps par vff:resolve-event-sections : gardée tant qu'elle ne change pas.
        DB::table('line_events')->where('fingerprint', 'argentanwebferro:'.$this->fp('1'))->update(['section_resolution' => 'places', 'section_length_m' => 17000]);

        $this->artisan('vff:apply-argentanwebferro')->assertSuccessful();
        $this->assertSame(2, DB::table('line_events')->where('source', EngineFacts::SOURCE)->count());
        $this->assertSame('places', DB::table('line_events')->where('fingerprint', 'argentanwebferro:'.$this->fp('1'))->value('section_resolution'));

        // Le fait est écarté ensuite (contradiction tranchée) : il quitte le moteur.
        $this->decisions(skip: [$this->fp('2') => 'année de la concession, ouverture le 1er février 1872 (Wikipédia)']);
        $this->artisan('vff:apply-argentanwebferro')->assertSuccessful();
        $this->assertSame([$this->fp('1')], DB::table('line_events')->where('source', EngineFacts::SOURCE)
            ->pluck('fingerprint')->map(fn ($f) => substr($f, strlen(EngineFacts::FINGERPRINT_PREFIX)))->all());
    }

    public function test_dry_run_writes_nothing_and_invalid_decisions_fail(): void
    {
        $this->fact('1', ['event_type' => 'closure', 'event_date' => '1953-01-01', 'date_precision' => 'year']);
        $this->decisions();
        $this->artisan('vff:apply-argentanwebferro', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, DB::table('line_events')->count());

        $this->decisions(stops: [$this->fp('1') => ['name' => 'X', 'source_url' => 'pas un lien', 'reason' => 'r']]);
        $this->artisan('vff:apply-argentanwebferro')->assertFailed();
    }

    public function test_the_source_ranks_after_wikipedia_and_before_openhistoricalmap(): void
    {
        $p = EraTimeline::SOURCE_PRIORITY;
        $this->assertLessThan($p[EngineFacts::SOURCE], $p['wikipedia']);
        $this->assertLessThan($p['ohm'], $p[EngineFacts::SOURCE]);
    }

    public function test_place_names_are_written_like_vff(): void
    {
        $this->assertSame('Sainte-Gauburge', EngineFacts::place('Ste Gauburge'));
        $this->assertSame('Saint-Ouen-sur-Iton', EngineFacts::place('St Ouen/Iton'));
        $this->assertSame('Neuville-sur-Touques', EngineFacts::place('Neuville/Touques'));
        $this->assertSame('Stade', EngineFacts::place('Stade'));
        $this->assertNull(EngineFacts::place('  '));
        $this->assertSame('Messei', EngineFacts::knownName('VFF : Messei (gare), article : halte'));
        $this->assertSame('Périers-en-Cotentin', EngineFacts::knownName('VFF : Périers-en-Cotentin'));
        $this->assertNull(EngineFacts::knownName('aucune gare de ce nom sur la ligne dans VFF'));
    }

    private function fp(string $seed): string
    {
        return sha1('fait-'.$seed);
    }

    /** @param  array<string, mixed>  $attrs */
    private function fact(string $seed, array $attrs): void
    {
        DB::table('argentanwebferro_facts')->insert($attrs + [
            'article_id' => $this->article, 'fingerprint' => $this->fp($seed), 'kind' => 'event', 'line_id' => $this->line->id,
            'line_match' => 'curated', 'source_url' => self::URL, 'review' => 'curated', 'vff_status' => 'nouveau',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, string>  $skip
     * @param  array<string, array<string, mixed>>  $stops
     */
    private function decisions(array $skip = [], array $stops = []): void
    {
        $this->useDataFile('argentanwebferro_moteur.php', ['skip' => $skip, 'stops' => $stops]);
    }
}
