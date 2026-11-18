<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use App\Services\ConsistencyChecker;
use App\Services\GeoExporter;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * vff:check-consistency : règles métier R1 (« si y'a du passager, y'a du
 * fret ») et R2 (une gare ne peut pas être plus active que ses lignes),
 * contrôles d'événements et correction --fix journalisée.
 *
 * Mini-réseau (2 ères : 1960 et 2030 courante), lignes parallèles espacées
 * de ~11 km pour que chaque gare ne soit desservie que par « sa » ligne :
 *   A  ligne classique en service, typée à tort « voyageurs seuls » (R1)
 *   B  ligne fermée en 2030 ; sa gare reste « fret » (bug signalé : R2)
 *   C  ligne fret seul ; sa gare est « voyageurs » (R2, plafond fret)
 *   D  LGV : « voyageurs seuls » légitime
 */
class CheckConsistencyCommandTest extends TestCase
{
    use RefreshDatabase;

    private Era $past;

    private Era $current;

    /** @var array<string, Line> */
    private array $lines = [];

    /** @var array<string, Station> */
    private array $stations = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->past = Era::factory()->create(['year' => 1960]);
        $this->current = Era::factory()->current()->create(['year' => 2030]);

        $this->lines['A'] = $this->line('Ligne de Alpha à Bravo', 'passenger', lat: 48.80);
        $this->lines['B'] = $this->line('Ligne de Charlie à Delta', 'mixed', lat: 48.90);
        $this->lines['C'] = $this->line('Ligne de Echo à Foxtrot', 'freight', lat: 49.00);
        $this->lines['D'] = $this->line('LGV Golf - Hotel', 'passenger', lat: 49.10, lgv: true);

        foreach ([$this->past, $this->current] as $era) {
            $this->segment('A', $era, 'in_service', $era->is_current ? 'passenger' : 'mixed', 48.80);
            $this->segment('B', $era, $era->is_current ? 'abandoned' : 'in_service', $era->is_current ? 'unknown' : 'mixed', 48.90);
            $this->segment('C', $era, 'in_service', 'freight', 49.00);
            $this->segment('D', $era, 'in_service', 'passenger', 49.10);
        }

        $this->stations['A'] = $this->station('Gare Alpha', 48.80, ['1960' => 'active', '2030' => 'active']);
        $this->stations['B'] = $this->station('Gare Charlie', 48.90, ['1960' => 'active', '2030' => 'no_commercial'], uic: '87000001');
        $this->stations['C'] = $this->station('Gare Echo', 49.00, ['1960' => 'no_commercial', '2030' => 'active']);

        // SNCF liste-des-gares marque la gare de la ligne fermée « fret » : le cas signalé.
        DB::table('sncf_gares')->insert(['code_uic' => '87000001', 'code_ligne' => '000002', 'fret' => true, 'voyageurs' => false, 'libelle' => 'Charlie']);

        // Événements contradictoires sur B (même portion = toute la ligne).
        $this->event('B', 'opening', '1900-05-01');
        $this->event('B', 'closure', '1890-01-01', precision: 'year');          // fermeture avant l'ouverture
        $this->event('B', 'dismantling', '1950-06-01');                         // dépose…
        $this->event('B', 'closure_freight', '1960-06-01');                     // … avant la fermeture au fret
        $this->event('B', 'reopening', '2099-01-01', precision: 'year');        // date future
    }

    public function test_reports_rule_violations_with_counts_and_fails(): void
    {
        $json = storage_path('framework/testing/consistency.json');
        @unlink($json);

        $this->artisan('vff:check-consistency', ['--json' => $json])
            ->expectsOutputToContain('stations.open_on_closed_lines')
            ->assertFailed();

        $findings = collect(json_decode(file_get_contents($json), true)['findings'])->keyBy('key');

        // R2 : gare « fret » de la ligne fermée (ère 2030 seulement), signalée aussi via le drapeau SNCF.
        $this->assertSame(1, $findings['stations.open_on_closed_lines']['count']);
        $this->assertSame([2030 => 1], $findings['stations.open_on_closed_lines']['by_era']);
        $this->assertSame('Gare Charlie', $findings['stations.open_on_closed_lines']['examples'][0]['name']);
        $this->assertSame(1, $findings['stations.sncf_flag_on_closed_line']['count']);
        // R2 : gare voyageurs sur ligne fret seul (2030).
        $this->assertSame(1, $findings['stations.passenger_on_freight_only']['count']);
        // Carte : ces deux gares sont affichées avec la couleur d'un autre statut.
        $this->assertSame(2, $findings['stations.display_color_mismatch']['count']);
        $this->assertSame(0, $findings['stations.palette']['count'], json_encode($findings['stations.palette']['examples']));

        // R1 : voyageurs seuls hors LGV (tronçon 2030 de A + lines.type de A), pas la LGV.
        $this->assertSame(1, $findings['segments.passenger_outside_lgv']['count']);
        $this->assertSame(1, $findings['lines.type_passenger_outside_lgv']['count']);

        // Événements.
        $this->assertSame(1, $findings['events.closure_before_opening']['count']);
        $this->assertSame(1, $findings['events.dismantling_before_closure']['count']);
        $this->assertSame(1, $findings['events.future_date']['count']);
        // Ligne B en service en 1960 alors que ses événements (dépose 1950) la disent déposée.
        $this->assertSame([1960 => 1], $findings['segments.in_service_but_closed_by_events']['by_era']);
    }

    public function test_fix_applies_rules_1_and_2_only_and_logs_every_change(): void
    {
        $before = glob(storage_path('logs/consistency/fix-*.jsonl')) ?: [];

        $this->artisan('vff:check-consistency', ['--fix' => true])->assertSuccessful();

        // R2 : la gare de la ligne fermée est fermée (ère + statut affiché), celle de la ligne fret devient fret seul.
        $this->assertSame('declassified', $this->eraStatus('B', $this->current));
        $this->assertSame('declassified', DB::table('stations')->where('id', $this->stations['B']->id)->value('status'));
        $this->assertSame('no_commercial', $this->eraStatus('C', $this->current));
        $this->assertSame('active', $this->eraStatus('B', $this->past), 'ère 1960 : ligne B en service, rien à corriger');
        $this->assertSame('active', $this->eraStatus('A', $this->current));

        // R1 : mixte hors LGV, la LGV garde « voyageurs seuls ».
        $this->assertSame(0, DB::table('line_segments')->where('line_id', $this->lines['A']->id)->where('traffic_type', 'passenger')->count());
        $this->assertSame('mixed', DB::table('lines')->where('id', $this->lines['A']->id)->value('type'));
        $this->assertSame(2, DB::table('line_segments')->where('line_id', $this->lines['D']->id)->where('traffic_type', 'passenger')->count());
        $this->assertSame('passenger', DB::table('lines')->where('id', $this->lines['D']->id)->value('type'));

        // Événements contradictoires : signalés, jamais modifiés.
        $this->assertSame(5, DB::table('line_events')->where('line_id', $this->lines['B']->id)->count());

        // La carte montre la gare de la ligne fermée comme fermée (gris), la gare de la ligne fret en fret seul (bleu).
        $geo = collect(app(GeoExporter::class)->stationsAsGeoJson(2030)['features'])->keyBy(fn ($f) => $f['properties']['name']);
        $this->assertNotContains($geo['Gare Charlie']['properties']['status'] ?? 'declassified', ConsistencyChecker::OPEN_STATIONS);
        $this->assertSame('no_commercial', $geo['Gare Echo']['properties']['status']);

        // Journal : une ligne JSON par modification, avec avant / après / raison.
        $logs = array_values(array_diff(glob(storage_path('logs/consistency/fix-*.jsonl')) ?: [], $before));
        $this->assertCount(1, $logs);
        $entries = array_map(fn ($l) => json_decode($l, true), file($logs[0], FILE_IGNORE_NEW_LINES));
        $this->assertContains(['station_eras', 'no_commercial', 'declassified'],
            array_map(fn ($e) => [$e['table'], $e['before'] ?? null, $e['after']], $entries));
        $this->assertContains(['line_segments', 'passenger', 'mixed'],
            array_map(fn ($e) => [$e['table'], $e['before'] ?? null, $e['after']], $entries));
        foreach ($entries as $e) {
            $this->assertContains($e['rule'], ['R1', 'R2']);
            $this->assertNotEmpty($e['reason']);
        }
        @unlink($logs[0]);

        // Relance : plus aucune violation R1 / R2.
        $findings = collect((new ConsistencyChecker)->run())->keyBy('key');
        foreach (['stations.open_on_closed_lines', 'stations.passenger_on_freight_only', 'stations.display_color_mismatch',
            'segments.passenger_outside_lgv', 'lines.type_passenger_outside_lgv'] as $key) {
            $this->assertSame(0, $findings[$key]['count'], $key);
        }
    }

    public function test_era_option_limits_checks_and_fixes_to_one_decade(): void
    {
        $this->artisan('vff:check-consistency', ['--era' => 1960, '--fix' => true, '--only' => 'gares,portions']);

        // Rien à corriger en 1960 : 2030 reste en l'état.
        $this->assertSame('no_commercial', $this->eraStatus('B', $this->current));
        $this->assertSame(1, DB::table('line_segments')->where('line_id', $this->lines['A']->id)->where('traffic_type', 'passenger')->count());
    }

    public function test_station_builder_output_satisfies_rule_2(): void
    {
        // Le moteur des gares applique le même plafond que le contrôle : aucune violation R2 après lui.
        $this->artisan('vff:build-station-eras', ['--no-stops' => true])->assertSuccessful();

        $findings = collect((new ConsistencyChecker(null, ['gares']))->run())->keyBy('key');
        $this->assertSame(0, $findings['stations.open_on_closed_lines']['count']);
        $this->assertSame(0, $findings['stations.passenger_on_freight_only']['count']);
    }

    public function test_station_on_undated_lines_is_not_capped_but_marked_uncertain(): void
    {
        // Cas Fellering 1900 : la ligne de la gare n'a aucun tronçon historique faute de
        // date d'ouverture (tracé de référence seul, tronçon à l'ère courante) → « absente »
        // n'est pas « fermée » : la gare suit ses propres sources, statut incertain.
        $e1900 = Era::factory()->create(['year' => 1900]);
        $this->segment('A', $e1900, 'in_service', 'mixed', 48.80); // l'ère 1900 est reconstituée
        $this->lines['U'] = $this->line('Ligne de Wesserling à Kruth', 'mixed', lat: 49.60);
        DB::table('line_reference_geometries')->insert(['line_id' => $this->lines['U']->id, 'length_m' => 7300, 'sources' => '[]',
            'geom' => DB::raw("ST_GeomFromText('MULTILINESTRING((2.30 49.60, 2.40 49.60))', 4326)"), 'created_at' => now(), 'updated_at' => now()]);
        $this->segment('U', $this->current, 'in_service', 'mixed', 49.60);
        $fellering = $this->station('Fellering', 49.60, ['1960' => 'active', '2030' => 'active']);

        // Contre-exemple strict : ligne V datée (tronçon en 1960) mais absente en 1900 → gare fermée en 1900.
        $this->lines['V'] = $this->line('Ligne de Victor à Whisky', 'mixed', lat: 49.70);
        $this->segment('V', $this->past, 'in_service', 'mixed', 49.70);
        $this->segment('V', $this->current, 'in_service', 'mixed', 49.70);
        $victor = $this->station('Gare Victor', 49.70, ['1960' => 'active', '2030' => 'active']);

        foreach ([$fellering, $victor] as $station) {
            $line = $station->is($fellering) ? 'U' : 'V';
            DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $this->lines[$line]->id, 'method' => 'test',
                'created_at' => now(), 'updated_at' => now()]);
            DB::table('station_events')->insert(['station_id' => $station->id, 'type' => 'opening', 'event_date' => '1863-07-01',
                'source' => 'osm', 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->artisan('vff:build-station-eras', ['--no-stops' => true])->assertSuccessful();

        $row = fn ($station, $era) => DB::table('station_eras')->where('station_id', $station->id)->where('era_id', $era->id)->first();
        $this->assertSame('active', $row($fellering, $e1900)->status);
        $this->assertStringContainsString('Statut incertain', $row($fellering, $e1900)->note);
        $this->assertSame('declassified', $row($victor, $e1900)->status);
        $this->assertSame('active', $row($victor, $this->past)->status);

        // Le contrôle applique la même exception : aucune violation R2, une gare « incertaine ».
        $findings = collect((new ConsistencyChecker(null, ['gares']))->run())->keyBy('key');
        $this->assertSame(0, $findings['stations.open_on_closed_lines']['count']);
        $this->assertSame([1900 => 1, 1960 => 1], $findings['stations.uncertain_undated_lines']['by_era']);
        $this->assertSame('Fellering', $findings['stations.uncertain_undated_lines']['examples'][0]['name']);
    }

    public function test_completeness_gauges_for_old_decades(): void
    {
        $e1930 = Era::factory()->create(['year' => 1930]);
        $e1950 = Era::factory()->create(['year' => 1950]);
        $this->segment('A', $e1950, 'in_service', 'mixed', 48.80); // l'ère 1950 est reconstituée
        // E : présente en 1930, absente en 1950, sans aucune fermeture datée → signalée.
        $this->lines['E'] = $this->line('Ligne de India à Juliett', 'mixed', lat: 49.20);
        $this->segment('E', $e1930, 'in_service', 'mixed', 49.20);
        // F : même cas, mais fermée et déposée en 1940 → expliqué.
        $this->lines['F'] = $this->line('Ligne de Kilo à Lima', 'mixed', lat: 49.30);
        $this->segment('F', $e1930, 'in_service', 'mixed', 49.30);
        $this->event('F', 'dismantling', '1940-01-01', precision: 'year');
        // G : tracé de référence, aucun tronçon historique, aucune date. H : idem, ouverture en attente de résolution.
        $this->lines['G'] = $this->line('Ligne de Mike à November', 'mixed', lat: 49.40);
        $this->lines['H'] = $this->line('Ligne de Oscar à Papa', 'mixed', lat: 49.50);
        foreach (['G' => 49.40, 'H' => 49.50] as $key => $lat) {
            DB::table('line_reference_geometries')->insert(['line_id' => $this->lines[$key]->id, 'length_m' => 7300, 'sources' => '[]',
                'geom' => DB::raw(sprintf("ST_GeomFromText('MULTILINESTRING((2.30 %F, 2.40 %F))', 4326)", $lat, $lat)),
                'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('line_events')->insert(['line_id' => $this->lines['H']->id, 'type' => 'opening', 'event_date' => '1890-01-01',
            'description' => 'Ouverture de Oscar à Papa', 'section_from' => 'Oscar', 'section_to' => 'Papa', 'section_resolution' => 'pending',
            'source' => 'wikipedia', 'created_at' => now(), 'updated_at' => now()]);

        $findings = collect((new ConsistencyChecker(null, ['completude']))->run())->keyBy('key');

        $vanished = $findings['completeness.vanished_1930_1950'];
        $this->assertSame(1, $vanished['count']);
        $this->assertSame('Ligne de India à Juliett', $vanished['examples'][0]['name']);

        $undated = $findings['completeness.undated_lines'];
        $this->assertSame(2, $undated['count']);
        $this->assertSame(1, $undated['split']['aucune date']['lines']);
        $this->assertSame(1, $undated['split']['en attente de résolution']['lines']);

        // Jauge km : 4 lignes en service de 7,3 km en 1960, très loin des ~38 000 km historiques.
        $gauge = $findings['completeness.network_km'];
        $row1960 = collect($gauge['table'])->firstWhere('era', 1960);
        $this->assertEqualsWithDelta(29, $row1960['principal'], 1);
        $this->assertContains(1960, array_keys($gauge['by_era']));
    }

    public function test_private_over_rfn_counts_each_private_km_once(): void
    {
        // Embranchement particulier de ~1,4 km (2.30 → 2.32 à 49,80°N), posé sur :
        //   R1 : ligne RFN dessinée par DEUX tronçons (une voie par sens, à ~3 m) ;
        //   R2 : autre ligne RFN qui partage la même voie (tronc commun).
        // Ancienne mesure (somme des paires de tronçons) : 2 × 1,4 km pour R1, 4,3 km au total.
        $this->lines['P'] = Line::factory()->create(['name' => 'Embranchement particulier de Quebec', 'code' => null, 'network' => 'particulier']);
        $this->lines['R1'] = $this->line('Ligne de Romeo à Sierra', 'mixed', lat: 49.80);
        $this->lines['R2'] = $this->line('Ligne de Tango à Uniform', 'mixed', lat: 49.80);
        $put = function (string $line, float $lon1, float $lon2, float $lat): void {
            DB::table('line_segments')->insert([
                'line_id' => $this->lines[$line]->id, 'era_id' => $this->current->id, 'status' => 'in_service', 'traffic_type' => 'mixed',
                'geom' => DB::raw(sprintf("ST_GeomFromText('LINESTRING(%F %F, %F %F)', 4326)", $lon1, $lat, $lon2, $lat)),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        };
        $put('P', 2.30, 2.32, 49.80);
        $put('R1', 2.29, 2.33, 49.80);
        $put('R1', 2.29, 2.33, 49.80003);
        $put('R2', 2.28, 2.34, 49.80);

        $finding = collect((new ConsistencyChecker(2030, ['categories']))->run())->keyBy('key')['lines.private_over_rfn'];

        $length = DB::selectOne("SELECT ST_Length(ST_Transform(ST_GeomFromText('LINESTRING(2.30 49.80, 2.32 49.80)', 4326), 2154)) / 1000 AS km")->km;
        $this->assertSame(2, $finding['count'], 'deux paires : P × R1 et P × R2');
        $this->assertSame([2030 => 2], $finding['by_era']);
        foreach ($finding['examples'] as $example) {
            $this->assertSame($this->lines['P']->id, $example['line_id']);
            $this->assertEqualsWithDelta($length, $example['km'], 0.1, "paire P × {$example['rfn_name']} : portion comptée une fois");
        }
        // Total : la portion privée recouverte, une fois, même si deux lignes RFN partagent la voie.
        $this->assertEqualsWithDelta($length, $finding['km'], 0.1);
    }

    // ─── Fabrique du mini-réseau ─────────────────────────────────────────

    private function line(string $name, string $type, float $lat, bool $lgv = false): Line
    {
        static $n = 0;
        $n++;

        return Line::factory()->create([
            'name' => $name, 'code' => sprintf('%06d', $n), 'type' => $type, 'network' => 'principal', 'is_lgv' => $lgv,
        ]);
    }

    private function segment(string $line, Era $era, string $status, string $traffic, float $lat): void
    {
        DB::table('line_segments')->insert([
            'line_id' => $this->lines[$line]->id, 'era_id' => $era->id, 'status' => $status, 'traffic_type' => $traffic,
            'geom' => DB::raw(sprintf("ST_GeomFromText('LINESTRING(2.30 %F, 2.40 %F)', 4326)", $lat, $lat)),
            'length_meters' => 7300, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @param  array<string, string>  $statusByEra */
    private function station(string $name, float $lat, array $statusByEra, ?string $uic = null): Station
    {
        $current = $statusByEra[(string) $this->current->year];
        $station = Station::factory()->create([
            'name' => $name, 'geom' => Point::makeGeodetic($lat, 2.35), 'opened_at' => '1880-01-01', 'closed_at' => null,
            'status' => $current, 'source_status' => $current, 'uic_code' => $uic, 'position_source' => null,
        ]);
        foreach ([$this->past, $this->current] as $era) {
            DB::table('station_eras')->insert(['station_id' => $station->id, 'era_id' => $era->id,
                'status' => $statusByEra[(string) $era->year], 'created_at' => now(), 'updated_at' => now()]);
        }

        return $station;
    }

    private function event(string $line, string $type, string $date, string $precision = 'day'): void
    {
        DB::table('line_events')->insert([
            'line_id' => $this->lines[$line]->id, 'type' => $type, 'event_date' => $date, 'date_precision' => $precision,
            'description' => "{$type} {$date}", 'source' => 'wikipedia', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function eraStatus(string $station, Era $era): ?string
    {
        return DB::table('station_eras')->where('station_id', $this->stations[$station]->id)->where('era_id', $era->id)->value('status');
    }
}
