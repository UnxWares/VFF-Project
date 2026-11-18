<?php

namespace Tests\Feature\Commands;

use App\Models\Era;
use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * Commandes de curation manuelle (statut, trafic, patrimoine, menaces,
 * sections) : effet exact en base, périmètre (ère, zone), codes de sortie,
 * idempotence.
 */
class CurationCommandsTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private Era $past;

    private Era $current;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        $this->past = $this->era(1930);
        $this->current = $this->era(2030, current: true);
        $this->line = $this->rfnLine('100000', 'Ligne de Alpha à Bravo');
    }

    /** Deux tronçons par ère : l'un près de Paris (48.80, 2.30), l'autre 50 km plus au sud. */
    private function network(): void
    {
        foreach ([$this->past, $this->current] as $era) {
            $this->track($this->line, $era, [[48.80, 2.30], [48.81, 2.31]]);
            $this->track($this->line, $era, [[48.35, 2.30], [48.36, 2.31]]);
        }
    }

    /** @return array<int, list<string>> année => statuts triés par latitude décroissante */
    private function column(string $column): array
    {
        return DB::table('line_segments')->join('eras', 'eras.id', '=', 'line_segments.era_id')
            ->where('line_id', $this->line->id)
            ->orderBy('eras.year')->orderByRaw('ST_YMax(geom) DESC')
            ->get(['eras.year', $column])
            ->groupBy('year')->map(fn ($rows) => $rows->pluck($column)->all())->all();
    }

    // ─── vff:set-status ─────────────────────────────────────────────────

    public function test_set_status_all_targets_the_current_era_only_and_clears_traffic_of_dead_track(): void
    {
        $this->network();

        // Code saisi avec espace : même résolution que le code stocké sans espace.
        $this->artisan('vff:set-status', ['code' => '100 000', 'status' => 'abandoned', '--all' => true])
            ->expectsOutputToContain('2 segments')
            ->assertSuccessful();

        $this->assertSame([1930 => ['in_service', 'in_service'], 2030 => ['abandoned', 'abandoned']], $this->column('status'));
        // Tronçon fermé : plus de trafic (invariant segments.inactive_with_traffic).
        $this->assertSame([1930 => ['mixed', 'mixed'], 2030 => ['unknown', 'unknown']], $this->column('traffic_type'));
    }

    public function test_set_status_of_a_past_era_and_back_in_service_keeps_other_eras(): void
    {
        $this->network();

        $this->artisan('vff:set-status', ['code' => '100000', 'status' => 'dismantled', '--all' => true, '--era' => 1930])->assertSuccessful();
        $this->assertSame([1930 => ['dismantled', 'dismantled'], 2030 => ['in_service', 'in_service']], $this->column('status'));

        // Remise en service : le statut change, le trafic (inconnu) sera recalculé par vff:classify-lines.
        $this->artisan('vff:set-status', ['code' => '100000', 'status' => 'in_service', '--all' => true, '--era' => 1930])->assertSuccessful();
        $this->assertSame([1930 => ['in_service', 'in_service'], 2030 => ['in_service', 'in_service']], $this->column('status'));
        $this->assertSame([1930 => ['unknown', 'unknown'], 2030 => ['mixed', 'mixed']], $this->column('traffic_type'));
    }

    public function test_set_status_by_zone_touches_only_track_within_the_radius(): void
    {
        $this->network();

        $this->artisan('vff:set-status', ['code' => '100000', 'status' => 'razed', '--lat' => 48.805, '--lng' => 2.305, '--radius' => 2000])
            ->expectsOutputToContain('1 segments')
            ->assertSuccessful();

        $this->assertSame([1930 => ['in_service', 'in_service'], 2030 => ['razed', 'in_service']], $this->column('status'));
        $this->assertSame([1930 => ['mixed', 'mixed'], 2030 => ['unknown', 'mixed']], $this->column('traffic_type'));
    }

    public function test_set_status_is_idempotent(): void
    {
        $this->network();
        $args = ['code' => '100000', 'status' => 'single_track', '--all' => true];

        $this->artisan('vff:set-status', $args)->assertSuccessful();
        $once = $this->snapshot(['line_segments']);
        $this->artisan('vff:set-status', $args)->assertSuccessful();

        $this->assertSame($once, $this->snapshot(['line_segments']));
        $this->assertSame([1930 => ['in_service', 'in_service'], 2030 => ['single_track', 'single_track']], $this->column('status'));
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidSetStatus(): iterable
    {
        yield 'statut inconnu' => [['code' => '100000', 'status' => 'flying', '--all' => true], 'Statut invalide : flying'];
        yield 'ligne inconnue' => [['code' => '999999', 'status' => 'abandoned', '--all' => true], 'Ligne 999999 introuvable'];
        yield 'ère inconnue' => [['code' => '100000', 'status' => 'abandoned', '--all' => true, '--era' => 1855], 'Ère introuvable'];
        yield 'ni --all ni zone' => [['code' => '100000', 'status' => 'abandoned'], 'Fournir soit --all soit --lat --lng --radius'];
        yield 'zone incomplète' => [['code' => '100000', 'status' => 'abandoned', '--lat' => 48.8, '--lng' => 2.3], 'Fournir soit --all soit --lat --lng --radius'];
    }

    #[DataProvider('invalidSetStatus')]
    public function test_set_status_rejects_invalid_input_without_writing(array $args, string $error): void
    {
        $this->network();
        $before = $this->snapshot(['line_segments']);

        $this->artisan('vff:set-status', $args)->expectsOutputToContain($error)->assertFailed();

        $this->assertSame($before, $this->snapshot(['line_segments']));
    }

    // ─── vff:set-traffic ────────────────────────────────────────────────

    public function test_set_traffic_passenger_on_a_classic_line_is_stored_as_mixed(): void
    {
        $this->network();

        // Règle VFF : voyageurs ⇒ mixte, sauf LGV.
        $this->artisan('vff:set-traffic', ['code' => '100000', 'traffic' => 'passenger', '--all' => true])
            ->expectsOutputToContain('traffic=mixed')
            ->assertSuccessful();

        $this->assertSame([1930 => ['mixed', 'mixed'], 2030 => ['mixed', 'mixed']], $this->column('traffic_type'));
    }

    public function test_set_traffic_passenger_on_a_high_speed_line_stays_passenger(): void
    {
        $lgv = $this->rfnLine('752000', 'LGV Sud-Est', ['is_lgv' => true]);
        $this->track($lgv, $this->current, [[47.0, 4.0], [47.1, 4.1]], ['traffic_type' => 'unknown']);

        $this->artisan('vff:set-traffic', ['code' => '752000', 'traffic' => 'passenger', '--all' => true])->assertSuccessful();

        $this->assertSame(['passenger'], DB::table('line_segments')->where('line_id', $lgv->id)->pluck('traffic_type')->all());
    }

    public function test_set_traffic_never_types_closed_track(): void
    {
        $this->network();
        DB::table('line_segments')->where('line_id', $this->line->id)->where('era_id', $this->past->id)
            ->update(['status' => 'dismantled', 'traffic_type' => 'unknown']);

        $this->artisan('vff:set-traffic', ['code' => '100000', 'traffic' => 'freight', '--all' => true])->assertSuccessful();

        $this->assertSame([1930 => ['unknown', 'unknown'], 2030 => ['freight', 'freight']], $this->column('traffic_type'));
    }

    public function test_set_traffic_by_zone(): void
    {
        $this->network();

        $this->artisan('vff:set-traffic', ['code' => '100000', 'traffic' => 'freight', '--lat' => 48.355, '--lng' => 2.305, '--radius' => 1500])
            ->assertSuccessful();

        $this->assertSame([1930 => ['mixed', 'freight'], 2030 => ['mixed', 'freight']], $this->column('traffic_type'));
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidSetTraffic(): iterable
    {
        yield 'type inconnu' => [['code' => '100000', 'traffic' => 'tgv', '--all' => true], 'Type invalide : tgv'];
        yield 'ligne inconnue' => [['code' => '999999', 'traffic' => 'freight', '--all' => true], 'Ligne 999999 introuvable'];
        yield 'ni --all ni zone' => [['code' => '100000', 'traffic' => 'freight', '--radius' => 100], 'Fournir soit --all soit --lat --lng --radius'];
    }

    #[DataProvider('invalidSetTraffic')]
    public function test_set_traffic_rejects_invalid_input_without_writing(array $args, string $error): void
    {
        $this->network();
        $before = $this->snapshot(['line_segments']);

        $this->artisan('vff:set-traffic', $args)->expectsOutputToContain($error)->assertFailed();

        $this->assertSame($before, $this->snapshot(['line_segments']));
    }

    // ─── vff:flag-heritage / vff:flag-area ──────────────────────────────

    public function test_flag_heritage_flags_every_segment_of_each_line_then_unflags(): void
    {
        $this->network();
        $other = $this->rfnLine('200000', 'Ligne de Charlie à Delta');
        $this->track($other, $this->current, [[45.0, 3.0], [45.1, 3.1]]);
        $untouched = $this->rfnLine('300000', 'Ligne de Echo à Foxtrot');
        $this->track($untouched, $this->current, [[44.0, 3.0], [44.1, 3.1]]);

        $this->artisan('vff:flag-heritage', ['codes' => ['100000', '200 000']])
            ->expectsOutputToContain('Flaggé : Ligne de Alpha à Bravo (100000) → 4 segments')
            ->expectsOutputToContain('Flaggé : Ligne de Charlie à Delta (200000) → 1 segments')
            ->assertSuccessful();

        $flags = fn () => DB::table('line_segments')->join('lines', 'lines.id', '=', 'line_id')
            ->selectRaw('lines.code, bool_and(is_heritage) AS all_flagged, bool_or(is_heritage) AS any_flagged')
            ->groupBy('lines.code')->orderBy('lines.code')->get()
            ->mapWithKeys(fn ($r) => [$r->code => [$r->all_flagged, $r->any_flagged]])->all();
        $this->assertSame(['100000' => [true, true], '200000' => [true, true], '300000' => [false, false]], $flags());

        $this->artisan('vff:flag-heritage', ['codes' => ['200000'], '--unflag' => true])
            ->expectsOutputToContain('Déflaggé')->assertSuccessful();
        $this->assertSame(['100000' => [true, true], '200000' => [false, false], '300000' => [false, false]], $flags());
    }

    public function test_flag_heritage_reports_unknown_codes_with_a_failure_but_flags_the_others(): void
    {
        $this->network();

        $this->artisan('vff:flag-heritage', ['codes' => ['999999', '100000']])
            ->expectsOutputToContain('Ligne 999999 introuvable')
            ->expectsOutputToContain('→ 4 segments')
            ->assertFailed();

        $this->assertSame(4, DB::table('line_segments')->where('is_heritage', true)->count());
    }

    public function test_flag_area_flags_only_the_segments_in_the_circle(): void
    {
        $this->network();

        $this->artisan('vff:flag-area', ['code' => '100000', 'lat' => 48.355, 'lng' => 2.305, 'radius' => 1500])
            ->expectsOutputToContain('2 segments dans rayon 1500m')
            ->assertSuccessful();
        $this->assertSame([1930 => [false, true], 2030 => [false, true]], $this->column('is_heritage'));

        $this->artisan('vff:flag-area', ['code' => '100000', 'lat' => 48.355, 'lng' => 2.305, 'radius' => 1500, '--unflag' => true])
            ->assertSuccessful();
        $this->assertSame([1930 => [false, false], 2030 => [false, false]], $this->column('is_heritage'));

        $this->artisan('vff:flag-area', ['code' => '999999', 'lat' => 48.0, 'lng' => 2.0, 'radius' => 10])
            ->expectsOutputToContain('Ligne 999999 introuvable')->assertFailed();
    }

    // ─── vff:flag-threatened ────────────────────────────────────────────

    public function test_flag_threatened_stores_the_campaign_and_forgets_home_stats(): void
    {
        Cache::put('home_stats', ['stale' => true]);

        $this->artisan('vff:flag-threatened', ['code' => '100000', '--title' => 'Sauvons la ligne', '--message' => 'Écrivez au préfet.', '--url' => 'https://example.org/petition'])
            ->expectsOutputToContain('ligne menacée')
            ->expectsOutputToContain('→ https://example.org/petition')
            ->assertSuccessful();

        $line = DB::table('lines')->find($this->line->id);
        $this->assertTrue($line->is_threatened);
        $this->assertSame([
            'kind' => 'threatened',
            'title' => 'Sauvons la ligne',
            'message' => 'Écrivez au préfet.',
            'cta_label' => 'Agir pour la ligne',
            'cta_url' => 'https://example.org/petition',
        ], json_decode($line->advocacy, true));
        $this->assertNull(Cache::get('home_stats'));
    }

    public function test_reopening_campaign_has_default_texts_and_no_red_flag(): void
    {
        $this->artisan('vff:flag-threatened', ['code' => '100000', '--kind' => 'reopening'])->assertSuccessful();

        $line = DB::table('lines')->find($this->line->id);
        $this->assertFalse($line->is_threatened);
        $this->assertSame([
            'kind' => 'reopening',
            'title' => 'Pour la réouverture de la ligne',
            'message' => 'Nous soutenons la réouverture de cette ligne.',
            'cta_label' => 'Agir pour la ligne',
            'cta_url' => null,
        ], json_decode($line->advocacy, true));
    }

    public function test_flag_threatened_off_removes_the_campaign_and_rejects_bad_input(): void
    {
        $this->artisan('vff:flag-threatened', ['code' => '100000'])->assertSuccessful();
        $this->artisan('vff:flag-threatened', ['code' => '100000', '--off' => true])
            ->expectsOutputToContain('message de soutien retiré')->assertSuccessful();

        $line = DB::table('lines')->find($this->line->id);
        $this->assertFalse($line->is_threatened);
        $this->assertNull($line->advocacy);

        $this->artisan('vff:flag-threatened', ['code' => '100000', '--kind' => 'closure'])
            ->expectsOutputToContain('--kind doit valoir threatened ou reopening')->assertFailed();
        $this->artisan('vff:flag-threatened', ['code' => '999999'])
            ->expectsOutputToContain('Ligne 999999 introuvable')->assertFailed();
        $this->assertNull(DB::table('lines')->where('id', $this->line->id)->value('advocacy'));
    }

    // ─── vff:seed-line-sections ─────────────────────────────────────────

    public function test_seed_line_section_inserts_one_section_once(): void
    {
        $args = [
            '--code' => '100 000', '--name' => 'Section Alpha - Bravo', '--pk-start' => 1500, '--pk-end' => 42_250,
            '--alt-names' => ' Alpha-Bravo , ,Tronçon nord', '--opening-from' => 1855, '--opening-to' => 1857,
            '--description' => 'Première section ouverte.', '--source-url' => 'https://fr.wikipedia.org/wiki/Ligne',
        ];

        $this->artisan('vff:seed-line-sections', $args)
            ->expectsOutputToContain('(PK 1+500 → 42+250) ajoutée')
            ->assertSuccessful();
        $this->artisan('vff:seed-line-sections', $args)
            ->expectsOutputToContain('Section déjà présente')
            ->assertSuccessful();

        $rows = DB::table('line_sections')->get();
        $this->assertCount(1, $rows);
        $this->assertSame($this->line->id, $rows[0]->line_id);
        $this->assertSame(['Alpha-Bravo', 'Tronçon nord'], json_decode($rows[0]->alt_names, true));
        $this->assertSame([1500, 42_250, 1855, 1857], [$rows[0]->pk_start_m, $rows[0]->pk_end_m, $rows[0]->opening_year_from, $rows[0]->opening_year_to]);
        $this->assertSame(['Première section ouverte.', 'https://fr.wikipedia.org/wiki/Ligne'], [$rows[0]->description, $rows[0]->source_url]);
    }

    public function test_seed_line_section_optional_fields_are_null(): void
    {
        $this->artisan('vff:seed-line-sections', ['--code' => '100000', '--name' => 'Section sud', '--pk-start' => 0, '--pk-end' => 900])
            ->expectsOutputToContain('(PK 0+000 → 0+900)')
            ->assertSuccessful();

        $row = DB::table('line_sections')->first();
        $this->assertNull($row->alt_names);
        $this->assertNull($row->opening_year_from);
        $this->assertNull($row->description);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidSections(): iterable
    {
        yield 'sans nom' => [['--code' => '100000', '--pk-start' => 0, '--pk-end' => 10], 'Options requises'];
        yield 'sans PK de fin' => [['--code' => '100000', '--name' => 'S', '--pk-start' => 0], 'Options requises'];
        yield 'PK inversés' => [['--code' => '100000', '--name' => 'S', '--pk-start' => 500, '--pk-end' => 500], 'PK incohérents'];
        yield 'ligne inconnue' => [['--code' => '999999', '--name' => 'S', '--pk-start' => 0, '--pk-end' => 10], 'Ligne 999999 introuvable'];
    }

    #[DataProvider('invalidSections')]
    public function test_seed_line_section_rejects_invalid_input(array $args, string $error): void
    {
        $this->artisan('vff:seed-line-sections', $args)->expectsOutputToContain($error)->assertFailed();
        $this->assertSame(0, DB::table('line_sections')->count());
    }
}
