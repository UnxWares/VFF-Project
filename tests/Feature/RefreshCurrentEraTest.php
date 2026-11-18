<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

/**
 * vff:refresh-current-era : recalcul quotidien de l'ère courante (« 2030 » = aujourd'hui)
 * pour les seules lignes et gares dont un événement est daté depuis le dernier passage.
 *
 * Chaque ligne : tracé est-ouest de 0,2° (≈ 15 km) à sa propre latitude (cf. BuildEraSegmentsTest).
 */
class RefreshCurrentEraTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, Era> */
    private array $eras = [];

    private float $lat = 46.0;

    private string $state;

    private string $lock;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([2010, 2020, 2030] as $year) {
            $this->eras[$year] = $year === 2030 ? Era::factory()->current()->create(['year' => $year]) : Era::factory()->create(['year' => $year]);
        }
        $dir = sys_get_temp_dir().'/vff-refresh-'.getmypid().'-'.bin2hex(random_bytes(4));
        mkdir($dir);
        $this->state = $dir.'/state.json';
        $this->lock = $dir.'/.era-segments.lock';
    }

    protected function tearDown(): void
    {
        foreach ([$this->state, $this->lock] as $file) {
            @unlink($file);
        }
        @rmdir(dirname($this->state));
        parent::tearDown();
    }

    public function test_event_dated_tomorrow_appears_tomorrow_after_the_refresh(): void
    {
        $this->travelTo('2026-10-19 04:30:00');
        $closesTomorrow = $this->line('Ligne de Alpha à Bravo');
        $this->event($closesTomorrow, 'opening', '1900-01-01');
        $this->event($closesTomorrow, 'closure', '2026-10-20');
        $closesLater = $this->line('Ligne de Charlie à Delta');
        $this->event($closesLater, 'opening', '1900-01-01');
        $this->event($closesLater, 'closure', '2026-10-25');
        $this->build();
        $this->refresh()->expectsOutputToContain('Rien à recalculer')->assertSuccessful();

        // Aujourd'hui (19/10) : la fermeture de demain n'est pas encore sur la carte courante.
        $this->assertSame(['in_service/mixed'], array_keys($this->km($closesTomorrow, 2030)));
        $untouched = $this->segmentIds($closesLater);

        $this->travelTo('2026-10-20 04:30:00');
        $this->refresh()->expectsOutputToContain('1 ligne(s), 0 gare(s)')->assertSuccessful();

        $this->assertSame(['abandoned/unknown'], array_keys($this->km($closesTomorrow, 2030)), 'fermée ce jour : fermée');
        $this->assertSame(['in_service/mixed'], array_keys($this->km($closesTomorrow, 2020)), 'ères passées inchangées');
        $this->assertSame($untouched, $this->segmentIds($closesLater), 'ligne sans événement dans la fenêtre : non reconstruite');
        $this->assertSame(['in_service/mixed'], array_keys($this->km($closesLater, 2030)));
        $this->assertSame('2026-10-20', $this->readState()['as_of']);
        $this->assertSame([$closesTomorrow->id], $this->readState()['lines']);
    }

    public function test_nothing_to_do_exits_without_rebuilding(): void
    {
        $this->travelTo('2026-10-19 04:30:00');
        $line = $this->line('Ligne de Echo à Foxtrot');
        $this->event($line, 'opening', '1900-01-01');
        $this->event($line, 'closure', '2026-10-01');
        $this->build();
        $before = $this->segmentIds($line);
        $this->writeState('2026-10-18');

        $this->refresh()->expectsOutputToContain('Rien à recalculer')->doesntExpectOutputToContain('vff:build-era-segments')->assertSuccessful();

        $this->assertSame($before, $this->segmentIds($line));
        $this->assertSame('2026-10-19', $this->readState()['as_of']);

        // Second passage le même jour : déjà à jour, aucune requête de recherche.
        $this->refresh()->expectsOutputToContain('déjà à jour')->assertSuccessful();
        $this->assertSame($before, $this->segmentIds($line));
    }

    public function test_lock_taken_postpones_the_refresh(): void
    {
        $this->travelTo('2026-10-19 04:30:00');
        $line = $this->line('Ligne de Golf à Hotel');
        $this->event($line, 'opening', '1900-01-01');
        $this->event($line, 'closure', '2026-10-20');
        $this->build();
        $this->writeState('2026-10-19');
        $this->travelTo('2026-10-20 04:30:00');

        // Reconstruction complète en cours (flock storage/app/reports/agents/.era-segments.lock).
        $held = fopen($this->lock, 'c');
        $this->assertTrue(flock($held, LOCK_EX | LOCK_NB));
        try {
            $this->refresh()->expectsOutputToContain('reporté')->assertSuccessful();
        } finally {
            flock($held, LOCK_UN);
            fclose($held);
        }

        $this->assertSame(['in_service/mixed'], array_keys($this->km($line, 2030)), 'rien reconstruit');
        $this->assertSame('2026-10-19', $this->readState()['as_of'], 'fenêtre conservée pour le passage suivant');

        // Verrou relâché : le passage suivant (une heure plus tard) rattrape la fenêtre.
        $this->travelTo('2026-10-20 05:30:00');
        $this->refresh()->assertSuccessful();
        $this->assertSame(['abandoned/unknown'], array_keys($this->km($line, 2030)));
    }

    public function test_several_lines_and_stations_in_one_run(): void
    {
        $this->travelTo('2026-10-17 04:30:00');
        $closes = $this->line('Ligne de India à Juliett');
        $this->event($closes, 'opening', '1900-01-01');
        $this->event($closes, 'closure', '2026-10-18');
        $opens = $this->line('Ligne de Kilo à Lima');
        $this->event($opens, 'opening', '2026-10-19');
        $reopens = $this->line('Ligne de Mike à November');
        $this->event($reopens, 'opening', '1900-01-01');
        $this->event($reopens, 'closure', '1990-01-01');
        $this->event($reopens, 'reopening', '2026-10-19');
        $station = Station::factory()->create(['name' => 'Oscar', 'closed_at' => '2026-10-19']);
        Station::factory()->create(['name' => 'Papa', 'closed_at' => '2026-10-17']);
        $this->build();
        $this->assertSame(['in_service/mixed'], array_keys($this->km($closes, 2030)));
        $this->assertSame([], $this->km($opens, 2030), 'pas encore ouverte');
        $this->assertSame(['abandoned/unknown'], array_keys($this->km($reopens, 2030)));
        $this->writeState('2026-10-17');

        // Deux jours sans passage (machine éteinte) : la fenêtre couvre le 18 et le 19.
        $this->travelTo('2026-10-19 04:30:00');
        $this->refresh(['--dry-run' => true])->expectsOutputToContain('3 ligne(s), 1 gare(s)')->assertSuccessful();
        $this->assertSame('2026-10-17', $this->readState()['as_of'], '--dry-run : état inchangé');

        $this->refresh()->expectsOutputToContain('3 ligne(s), 1 gare(s)')->assertSuccessful();

        $this->assertSame(['abandoned/unknown'], array_keys($this->km($closes, 2030)));
        $this->assertSame(['in_service/mixed'], array_keys($this->km($opens, 2030)), 'ouverte ce jour');
        $this->assertSame(['in_service/mixed'], array_keys($this->km($reopens, 2030)), 'rouverte ce jour');
        $state = $this->readState();
        $this->assertSame([$closes->id, $opens->id, $reopens->id], $state['lines']);
        $this->assertSame([$station->id], $state['stations']);
    }

    public function test_task_is_scheduled_daily_without_overlapping(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($e) => str_contains((string) $e->command, 'vff:refresh-current-era'))->values();

        $this->assertCount(1, $events);
        $this->assertSame('30 4-23 * * *', $events[0]->expression);
        $this->assertTrue($events[0]->withoutOverlapping);
        $this->assertSame('Europe/Paris', $events[0]->timezone);
    }

    // ─── Fabrique ────────────────────────────────────────────────────────

    private function refresh(array $args = []): PendingCommand
    {
        DB::statement('DROP TABLE IF EXISTS tmp_chunks');

        return $this->artisan('vff:refresh-current-era', $args + ['--state-file' => $this->state, '--lock-file' => $this->lock]);
    }

    /** Construction complète de toutes les ères (une ligne par appel, cf. BuildEraSegmentsTest::build). */
    private function build(): void
    {
        foreach (DB::table('lines')->orderBy('id')->pluck('id') as $id) {
            DB::statement('DROP TABLE IF EXISTS tmp_chunks');
            $this->artisan('vff:build-era-segments', ['--line' => (string) $id, '--no-classify' => true, '--no-enrich' => true])->assertSuccessful();
        }
    }

    private function writeState(string $asOf): void
    {
        file_put_contents($this->state, json_encode(['as_of' => $asOf]));
    }

    /** @return array<string, mixed> */
    private function readState(): array
    {
        return json_decode((string) file_get_contents($this->state), true);
    }

    /** @return list<int> */
    private function segmentIds(Line $line): array
    {
        return DB::table('line_segments')->where('line_id', $line->id)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return array<string, float> « statut/trafic » → km de la ligne à l'ère */
    private function km(Line $line, int $year): array
    {
        $rows = DB::table('line_segments')->where('line_id', $line->id)->where('era_id', $this->eras[$year]->id)
            ->selectRaw("status || '/' || traffic_type AS k, SUM(ST_Length(geom::geography)) / 1000.0 AS km")->groupBy('k')->orderBy('k')->get();

        return $rows->mapWithKeys(fn ($r) => [$r->k => round((float) $r->km, 2)])->all();
    }

    private function line(string $name): Line
    {
        $this->lat += 0.05;
        $line = Line::factory()->create(['name' => $name, 'code' => null, 'network' => 'principal', 'is_lgv' => false, 'gauge_mm_default' => 1435]);
        $geom = $this->multi(2.00, 2.20, $this->lat);
        $now = now();
        DB::table('line_reference_geometries')->insert(['line_id' => $line->id, 'length_m' => 15200, 'sources' => '[]',
            'geom' => DB::raw($geom), 'created_at' => $now, 'updated_at' => $now]);
        DB::table('line_reference_parts')->insert(['line_id' => $line->id, 'source' => 'osm', 'source_trace_ids' => '[]', 'length_m' => 15200,
            'geom' => DB::raw($geom), 'created_at' => $now, 'updated_at' => $now]);

        return $line;
    }

    private function event(Line $line, string $type, string $date): void
    {
        DB::table('line_events')->insert([
            'line_id' => $line->id, 'type' => $type, 'event_date' => $date, 'description' => "{$type} {$date}", 'source' => 'wikipedia',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function multi(float $lon1, float $lon2, float $lat): string
    {
        return sprintf("ST_GeomFromText('MULTILINESTRING((%F %F, %F %F, %F %F))', 4326)", $lon1, $lat, ($lon1 + $lon2) / 2, $lat, $lon2, $lat);
    }
}
