<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Models\Station;
use App\Services\StationCorrections;
use App\Services\StationTimeline;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Corrections sourcées des gares (database/data/gares_corrections.php, StationCorrections) et
 * règles de vff:build-station-eras issues de la relecture des gares douteuses (2026-10-20).
 */
class StationCorrectionsTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    private Era $past;

    private Era $current;

    protected function setUp(): void
    {
        parent::setUp();
        $this->past = Era::factory()->create(['year' => 2020]);
        $this->current = Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd\'hui']);
        $this->line = Line::factory()->create(['code' => '062000', 'name' => 'Ligne d\'Arches à Saint-Dié', 'is_lgv' => false]);
        foreach ([$this->past, $this->current] as $era) {
            LineSegment::factory()->create([
                'line_id' => $this->line->id, 'era_id' => $era->id, 'status' => 'in_service', 'traffic_type' => 'mixed', 'is_heritage' => false,
                'geom' => LineString::make([Point::makeGeodetic(48.20, 6.50), Point::makeGeodetic(48.20, 6.90)]),
            ]);
        }
    }

    /** @param  array<string, array{0: string, 1?: bool, 2?: bool}>  $sources  source => [cycle de vie, voyageurs, fret] */
    private function station(string $name, float $lon, array $sources, string $status = 'active', ?string $uic = null, float $lat = 48.20, bool $attach = true): Station
    {
        $station = Station::factory()->create([
            'name' => $name, 'geom' => Point::makeGeodetic($lat, $lon), 'status' => $status, 'source_status' => $status, 'uic_code' => $uic,
            'position_source' => 'sncf', 'provenance' => ['status' => array_key_first($sources) === 'osm' ? 'osm' : 'sncf'], 'opened_at' => null,
        ]);
        foreach ($sources as $source => $s) {
            DB::table('station_sources')->insert([
                'source' => $source, 'source_ref' => $source === 'osm' ? 'n'.$station->id : "{$name}-{$source}", 'station_id' => $station->id, 'name' => $name,
                'lifecycle' => $s[0], 'passenger' => $s[1] ?? null, 'freight' => $s[2] ?? null,
                'seen_at' => '2026-10-15 17:08:34', 'created_at' => now(), 'updated_at' => now(),
                'geom' => DB::raw("ST_SetSRID(ST_MakePoint({$lon}, {$lat}), 4326)"),
            ]);
        }
        if ($attach) {
            DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $this->line->id, 'method' => 'sncf_pk',
                'pk_m' => (int) (($lon - 6.5) * 100_000), 'pk_source' => 'sncf', 'distance_m' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $station;
    }

    private function event(Station $s, string $type, string $date, string $source, string $precision = 'day'): void
    {
        DB::table('station_events')->insert(['station_id' => $s->id, 'type' => $type, 'event_date' => $date, 'date_precision' => $precision,
            'source' => $source, 'is_inferred' => false, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function lineEvent(string $type, string $date): void
    {
        DB::table('line_events')->insert(['line_id' => $this->line->id, 'type' => $type, 'event_date' => $date, 'date_precision' => 'day',
            'description' => $type, 'confidence' => 'high', 'section_resolution' => 'whole_line', 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param  list<array<string, mixed>>  $corrections */
    private function useCorrections(array $corrections): void
    {
        $this->app->instance(StationCorrections::class, new StationCorrections($corrections));
    }

    private function eraStatus(Station $s, Era $era): ?string
    {
        return DB::table('station_eras')->where('station_id', $s->id)->where('era_id', $era->id)->value('status');
    }

    public function test_versioned_corrections_are_well_formed_and_sourced(): void
    {
        $all = require database_path('data/gares_corrections.php');
        $this->assertNotEmpty($all);
        foreach ($all as $i => $c) {
            $this->assertSame([], StationCorrections::validate($c), "correction #{$i} (".($c['station']['name'] ?? '?').')');
        }
        // Une même correction n'apparaît qu'une fois.
        $this->assertSame(count($all), count(array_unique(array_map('json_encode', $all))));
    }

    public function test_validation_rejects_incomplete_corrections(): void
    {
        $ok = ['station' => ['name' => 'X', 'uic' => '1'], 'action' => 'add', 'type' => 'closure', 'date' => '2018-12-22',
            'source_url' => 'https://fr.wikipedia.org/w/index.php?oldid=1', 'reason' => 'r'];
        $this->assertSame([], StationCorrections::validate($ok));
        $this->assertNotEmpty(StationCorrections::validate(array_merge($ok, ['date' => '22/12/2018'])));
        $this->assertNotEmpty(StationCorrections::validate(array_merge($ok, ['source_url' => null])));
        $this->assertNotEmpty(StationCorrections::validate(array_merge($ok, ['station' => ['name' => 'X']])));
        $this->assertNotEmpty(StationCorrections::validate(array_merge($ok, ['reason' => "a \u{2014} b"])));
        $this->assertNotEmpty(StationCorrections::validate(array_merge($ok, ['action' => 'kind', 'kind' => 'technical'])));
    }

    public function test_corrections_are_applied_idempotently_and_target_one_station(): void
    {
        $a = $this->station('Commequiers', 6.60, ['sncf' => ['disused', false, false]], 'declassified', '87486548');
        $this->event($a, 'closure', '1992-12-09', 'wikipedia');
        $grasse = $this->station('Grasse', 6.70, ['sncf_voyageurs' => ['active', true]], 'active', '87757724');
        $ghost = $this->station('Grasse', 5.3806, ['sncf' => ['active', true, false]], 'active', '87757724', 43.3028, false);
        $url = 'https://fr.wikipedia.org/w/index.php?oldid=1';
        $corrections = new StationCorrections([
            ['station' => ['name' => 'Commequiers', 'uic' => '87486548'], 'action' => 'add', 'type' => 'closure', 'date' => '1982-01-01', 'precision' => 'year',
                'source_url' => $url, 'quote' => 'la gare de Commequiers est fermée', 'reason' => 'raccordement de 1982'],
            ['station' => ['name' => 'Commequiers', 'uic' => '87486548'], 'action' => 'neutralize',
                'match' => ['source' => 'wikipedia', 'type' => 'closure', 'date' => '1992-12-09'], 'source_url' => $url, 'reason' => 'déclassement du tronçon'],
            ['station' => ['name' => 'Grasse', 'uic' => '87757724', 'near' => [5.3806, 43.3028, 2]], 'action' => 'kind', 'kind' => 'excluded',
                'source_url' => $url, 'reason' => 'fiche erronée'],
            ['station' => ['name' => 'Grasse', 'uic' => '87757724'], 'action' => 'position', 'lng' => 6.71, 'lat' => 48.2, 'source_url' => $url, 'reason' => 'ambiguë'],
            ['station' => ['name' => 'Inconnue', 'uic' => '0'], 'action' => 'tourist', 'source_url' => $url, 'reason' => 'absente'],
        ]);
        foreach ([1, 2] as $pass) {
            $r = $corrections->apply();
            $this->assertSame([$ghost->id => 'excluded'], $r['kinds'], "passage {$pass}");
            $this->assertSame(3, $r['applied'], "passage {$pass}");
            $this->assertCount(2, $r['skipped'], "passage {$pass} : fiche ambiguë et gare introuvable");
        }
        $events = DB::table('station_events')->where('station_id', $a->id)->get();
        $this->assertSame([['closure', '1982-01-01', StationTimeline::CORRECTION]],
            $events->map(fn ($e) => [$e->type, $e->event_date, $e->source])->all());
        $this->assertStringContainsString('raccordement de 1982', (string) $events[0]->note);
        $this->assertSame([], $corrections->touristStations());
        // Position ambiguë : non appliquée.
        $this->assertEqualsWithDelta(6.70, DB::selectOne('SELECT ST_X(geom) AS x FROM stations WHERE id = ?', [$grasse->id])->x, 1e-6);
    }

    public function test_build_applies_corrections_and_rules(): void
    {
        // Halte fermée en 2018, OSM / Wikidata désaffectée, drapeau SNCF voyageurs périmé ; la ligne rouvre en 2021.
        $halt = $this->station('Lépanges', 6.60, ['sncf' => ['active', true, false], 'wikidata' => ['disused'], 'osm' => ['disused']], 'active', '87144048');
        $this->event($halt, 'closure', '2018-12-14', 'wikidata');
        // Gare rouverte avec la ligne (desserte attestée par gares-de-voyageurs).
        $served = $this->station('Bruyères', 6.70, ['sncf' => ['active', true, false], 'sncf_voyageurs' => ['active', true], 'osm' => ['disused']], 'active');
        $this->event($served, 'closure', '2018-12-14', 'wikidata');
        $this->lineEvent('opening', '1876-01-01');
        $this->lineEvent('reopening', '2021-12-12');
        // Gare d'un tramway, et train de parc hors des lignes VFF.
        $tram = $this->station('Noailles', 6.80, ['wikidata' => ['active']], 'active', null, 43.30, false);
        $park = $this->station('Disneyland Railroad Main Street Station', 6.85, ['osm' => ['active']], 'tourist', null, 45.0, false);
        $url = 'https://fr.wikipedia.org/w/index.php?oldid=1';
        $this->useCorrections([
            ['station' => ['name' => 'Lépanges', 'uic' => '87144048'], 'action' => 'add', 'type' => 'closure', 'date' => '2018-12-22',
                'source_url' => $url, 'reason' => 'halte non rouverte'],
            ['station' => ['name' => 'Noailles', 'wikidata' => 'Noailles-wikidata'], 'action' => 'kind', 'kind' => 'excluded',
                'source_url' => $url, 'reason' => 'station de tramway'],
        ]);

        $this->artisan('vff:build-station-eras', ['--today' => '2026-10-20'])->assertSuccessful();

        // Pas de réouverture déduite pour la halte que ses sources disent désaffectée.
        $this->assertFalse(DB::table('station_events')->where('station_id', $halt->id)->where('type', 'reopening')->exists());
        $this->assertTrue(DB::table('station_events')->where('station_id', $served->id)->where('type', 'reopening')->exists());
        $this->assertSame([StationTimeline::CLOSED, 'active'], [$this->eraStatus($halt, $this->current), $this->eraStatus($served, $this->current)]);
        // Date retenue : la correction (22/12/2018), pas Wikidata (14/12/2018).
        $this->assertSame(['2018-12-22', 'day', StationTimeline::CORRECTION],
            [$halt->fresh()->closed_at?->format('Y-m-d'), $halt->fresh()->closed_precision, $halt->fresh()->provenance['closed_at'] ?? null]);
        // Hors du réseau ferré : ni ère, ni annuaire, ni date.
        $this->assertSame('excluded', $tram->fresh()->provenance['kind'] ?? null);
        $this->assertSame(0, DB::table('station_eras')->where('station_id', $tram->id)->count());
        $this->get('/stations')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('stations.data', fn ($rows) => ! in_array('Noailles', array_column(is_array($rows) ? $rows : $rows->all(), 'name'), true))->etc());
        $this->get('/stations/'.$tram->fresh()->slug)->assertOk()->assertInertia(fn (Assert $page) => $page->where('station.status', 'excluded')->etc());
        // Train de parc attesté aujourd'hui par OSM : touristique, pas incertain.
        $row = DB::table('station_eras')->where('station_id', $park->id)->where('era_id', $this->current->id)->first();
        $this->assertSame(['tourist', false], [$row?->status, (bool) $row?->uncertain]);
    }

    /** Ouverture corrigée = date de la fiche gare (Mâcon-Loché-TGV : Wikidata 19/09/1981, article 27/09/1981). */
    public function test_corrected_opening_becomes_the_station_opening_date(): void
    {
        $tgv = $this->station('Mâcon-Loché-TGV', 6.60, ['sncf' => ['active', true, false], 'wikidata' => ['active']], 'active', '87725705');
        DB::table('stations')->where('id', $tgv->id)->update(['opened_at' => '1981-09-19', 'opened_precision' => 'day',
            'provenance' => json_encode(['status' => 'sncf', 'opened_at' => 'wikidata'])]);
        $this->event($tgv, 'opening', '1981-09-19', 'wikidata');
        $this->lineEvent('opening', '1981-09-27');
        $url = 'https://fr.wikipedia.org/w/index.php?oldid=239205680';
        $this->useCorrections([
            ['station' => ['name' => 'Mâcon-Loché-TGV', 'uic' => '87725705'], 'action' => 'add', 'type' => 'opening', 'date' => '1981-09-27',
                'source_url' => $url, 'reason' => 'article de la gare'],
            ['station' => ['name' => 'Mâcon-Loché-TGV', 'uic' => '87725705'], 'action' => 'neutralize',
                'match' => ['source' => 'wikidata', 'type' => 'opening', 'date' => '1981-09-19'], 'source_url' => $url, 'reason' => 'date Wikidata'],
        ]);

        $this->artisan('vff:build-station-eras', ['--today' => '2026-10-20'])->assertSuccessful();

        $fresh = $tgv->fresh();
        $this->assertSame(['1981-09-27', 'day', StationTimeline::CORRECTION], [$fresh->opened_at?->format('Y-m-d'), $fresh->opened_precision, $fresh->provenance['opened_at'] ?? null]);
        $this->assertSame(['1981-09-27'], DB::table('station_events')->where('station_id', $tgv->id)->where('type', 'opening')->where('is_inferred', false)->pluck('event_date')->all());
        $this->assertSame(1981, (int) DB::table('station_lines')->where('station_id', $tgv->id)->value('first_year'));
    }
}
