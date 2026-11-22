<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use App\Services\ConsistencyChecker;
use App\Services\DataInvariants;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * vff:check-invariants (App\Services\DataInvariants) : chaque invariant est
 * violé une fois par une donnée fabriquée, et un réseau propre passe sans
 * erreur. La commande ne fait que des SELECT.
 *
 * Ères 1970, 1980, 1990 et 2030 (courante). Lignes est-ouest de 0,1° (≈ 7 km)
 * espacées de 0,02° (≈ 2,2 km) en latitude.
 */
class CheckInvariantsCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, Era> */
    private array $eras = [];

    private float $lat = 47.0;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([1970, 1980, 1990, 2030] as $year) {
            $this->eras[$year] = $year === 2030 ? Era::factory()->current()->create(['year' => $year]) : Era::factory()->create(['year' => $year]);
        }
    }

    public function test_clean_network_satisfies_every_invariant(): void
    {
        $this->cleanNetwork();

        $this->artisan('vff:check-invariants')
            ->expectsOutputToContain('0 invariant(s) violé(s) sur '.count(DataInvariants::INVARIANTS))
            ->assertSuccessful();

        foreach ((new DataInvariants)->run() as $r) {
            $this->assertSame(0, $r['count'], $r['key'].' : '.implode(' | ', $r['examples']));
        }
    }

    public function test_each_violation_is_counted_with_examples(): void
    {
        $this->cleanNetwork();

        // Ère courante : tronçon « fret » qui porte une gare voyageurs desservie de son code.
        $b = $this->line('Ligne de Bravo à Charlie', [2030 => ['in_service', 'freight']], code: '200000');
        DB::table('sncf_gares')->insert(['code_ligne' => '200000', 'voyageurs' => true, 'fret' => false, 'libelle' => 'Bravo', 'lat' => $b->lat, 'lon' => 2.35]);
        // Conformes (règle par portion) : gare voyageurs au BOUT du tronçon fret (elle borne la portion
        // voyageurs voisine), et gare voyageurs périmée (tronçon SNCF neutralisé) au milieu.
        $c = $this->line('Ligne de Charlie à Delta', [2030 => ['in_service', 'freight']], code: '200001');
        DB::table('sncf_gares')->insert(['code_ligne' => '200001', 'voyageurs' => true, 'fret' => true, 'libelle' => 'Charlie', 'lat' => $c->lat, 'lon' => 2.40]);
        DB::table('sncf_gares')->insert(['code_ligne' => '200001', 'voyageurs' => true, 'fret' => false, 'libelle' => 'Halte neutralisée', 'lat' => $c->lat, 'lon' => 2.35]);
        DB::table('sncf_line_geometries')->insert(['code_ligne' => '200001', 'libelle' => 'Neutralisée', 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw(sprintf("ST_GeomFromText('LINESTRING(2.32 %F, 2.38 %F)', 4326)", $c->lat, $c->lat))]);
        // Ère courante : portion fermée sur une gare voyageurs desservie d'un tronçon SNCF « Exploitée »
        // (fausse fermeture d'une source, cas 366000 Lison - Cherbourg).
        $closed = $this->line('Ligne de Delta à Foxtrot', [2030 => ['abandoned', 'unknown']], code: '200002');
        DB::table('sncf_gares')->insert(['code_ligne' => '200002', 'voyageurs' => true, 'fret' => false, 'libelle' => 'Carentan', 'lat' => $closed->lat, 'lon' => 2.35]);
        DB::table('sncf_line_geometries')->insert(['code_ligne' => '200002', 'libelle' => 'Exploitée', 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw(sprintf("ST_GeomFromText('LINESTRING(2.30 %F, 2.40 %F)', 4326)", $closed->lat, $closed->lat))]);
        // LGV en service en 1970 et 1980.
        $this->line('LGV Sud-Est', [1970 => ['in_service', 'passenger'], 1980 => ['in_service', 'passenger'], 1990 => ['in_service', 'passenger'],
            2030 => ['in_service', 'passenger']]);
        // En service en 1970 alors que la ligne ouvre en 1985 (1980 toléré : ère de l'ouverture).
        $d = $this->line('Ligne de Delta à Echo', [1970 => ['in_service', 'mixed'], 1980 => ['in_service', 'mixed'], 1990 => ['in_service', 'mixed'],
            2030 => ['in_service', 'mixed']]);
        $this->event($d, 'opening', '1985-06-01');
        // Gare active en 1980 loin de toute voie ; une gare incertaine dans le même cas n'est pas comptée.
        $lonely = $this->station('Gare Foxtrot', 2.30, 45.0, [1980 => 'active']);
        $this->station('Gare Golf', 2.30, 45.1, [1980 => 'active'], uncertain: true);
        // Déposée en 1980 ET en 1990 sur la même portion (fermée et déposée en 1975-1976).
        $f = $this->line('Ligne de Hotel à India', [1970 => ['in_service', 'mixed'], 1980 => ['dismantled', 'unknown'], 1990 => ['dismantled', 'unknown']]);
        $this->event($f, 'closure', '1975-01-01');
        $this->event($f, 'dismantling', '1976-01-01');
        // Déposée en 1980 et en 1990 mais sur deux portions différentes : conforme.
        $g = $this->line('Ligne de Juliett à Kilo', [1980 => ['dismantled', 'unknown']]);
        $this->segment($g, 1990, 'dismantled', 'unknown', lon1: 3.0, lon2: 3.1);
        // Perte de km sans événement (1970 → 1980), expliquée (I), puis événement hors fenêtre (J, 1980 → 1990).
        $h = $this->line('Ligne de Lima à Mike', [1970 => ['in_service', 'mixed']]);
        $i = $this->line('Ligne de November à Oscar', [1970 => ['in_service', 'mixed']]);
        $this->event($i, 'closure', '1975-01-01');
        $j = $this->line('Ligne de Papa à Quebec', [1970 => ['in_service', 'mixed'], 1980 => ['in_service', 'mixed']]);
        $this->event($j, 'closure', '1980-06-01');
        foreach ([[$h, [1980, 1990, 2030]], [$i, [1980, 1990, 2030]], [$j, [1990, 2030]]] as [$line, $years]) {
            foreach ($years as $year) {
                $this->segment($line, $year, 'in_service', 'mixed', lon2: 2.32, lat: $line->lat);
            }
        }
        // Deux tronçons identiques (même ligne, même ère).
        $k = $this->line('Ligne de Romeo à Sierra', [2030 => ['in_service', 'mixed']]);
        $this->segment($k, 2030, 'in_service', 'mixed', lat: $k->lat);
        // Raccord de moins de 10 m (0,0001° ≈ 7 m) posé sur le tronçon : pas un doublon (DUPLICATE_MIN_M).
        $t = $this->line('Ligne de Tango à Uniform', [2030 => ['in_service', 'mixed']]);
        $this->segment($t, 2030, 'in_service', 'mixed', lon1: 2.35, lon2: 2.3501, lat: $t->lat);
        // Tracé archeo à 15 m de la voie BD TOPO d'une autre ligne sur 7 km : doublon décalé (jonction).
        // Conformes : à 40 m (voie distincte), et parallèle sans convergence signalé (kept_parallel).
        $this->offsetDuplicate('Ligne de Victor à Whiskey', 15.0);
        $this->offsetDuplicate('Ligne de Xray à Yankee', 40.0);
        $this->offsetDuplicate('Ligne de Zulu à Alpha', 15.0, keptParallel: true);
        // Conformes aussi : partage de voie impossible (garde-fou de junctionTrunk), voie distincte.
        $this->offsetDuplicate('Ligne de Papeete à Faaa (métrique)', 15.0, network: 'secondaire', otherNetwork: 'principal');
        $this->offsetDuplicate('Ligne de Nouméa à Païta (60 cm)', 15.0, gauge: 600, otherGauge: 1435);
        // Trou de 38 m dans le tracé de référence, sur une voie précise (pont) ; conforme : trou sans voie précise.
        $this->referenceHole('Ligne de Saül à Maripasoula', true);
        $this->referenceHole('Ligne de Sinnamary à Iracoubo', false);
        $this->junctionGaps();
        // Deux tronçons bout à bout : conforme.
        $l = $this->line('Ligne de Tango à Uniform', [2030 => ['in_service', 'mixed']]);
        $this->segment($l, 2030, 'in_service', 'mixed', lon1: 2.40, lon2: 2.50, lat: $l->lat);
        // Patrimoine incohérent (3 tronçons) et cohérent (1 tronçon).
        $m = $this->line('Ligne de Victor à Whisky', []);
        $this->segment($m, 2030, 'in_service', 'unknown', heritage: 'velorail', lat: $m->lat);
        $this->segment($m, 2030, 'abandoned', 'unknown', heritage: 'tourist_train', lon1: 2.4, lon2: 2.5, lat: $m->lat);
        $this->segment($m, 2030, 'in_service', 'unknown', heritage: 'museum', isHeritage: false, lon1: 2.5, lon2: 2.6, lat: $m->lat);
        $this->segment($m, 2030, 'abandoned', 'unknown', heritage: 'velorail', lon1: 2.6, lon2: 2.7, lat: $m->lat);
        // Tronçon fermé avec un trafic.
        $this->line('Ligne de Xray à Yankee', [2030 => ['abandoned', 'freight']]);
        // Tirets longs dans les textes affichés.
        $long = "\u{2014}";
        $dash = $this->line("Ligne de Zulu {$long} Alpha", [2030 => ['in_service', 'mixed']]);
        $this->event($dash, 'opening', '1960-01-01', 'Ouverture '.str_repeat('-', 2).' section de Zulu');
        Station::factory()->create(['name' => "Zulu {$long} Ville", 'geom' => Point::makeGeodetic($dash->lat, 2.35), 'description' => null]);
        // Réseau principal : 7 km par ère, très loin des ~30 000 km historiques.
        DB::table('lines')->where('id', $b->id)->update(['network' => 'principal']);

        $json = storage_path('framework/testing/invariants.json');
        @unlink($json);
        $this->artisan('vff:check-invariants', ['--json' => $json])
            ->expectsOutputToContain('segments.lgv_before_1981')
            ->expectsOutputToContain('Gare Foxtrot')
            ->assertFailed();

        /** @var array{invariants: list<array<string, mixed>>} $report */
        $report = json_decode(file_get_contents($json), true);
        $results = collect($report['invariants'])->keyBy('key');
        $counts = $results->map(fn (array $r) => $r['count'])->all();

        $this->assertSame([
            'eras.current' => 0,
            'segments.current_freight_on_passenger_code' => 1,
            'segments.current_closed_on_served_station' => 1,
            'segments.lgv_before_1981' => 1,
            'segments.in_service_before_first_opening' => 1,
            'stations.active_without_track' => 1,
            'stations.closed_at_outside_eras' => 0,
            'segments.dismantled_two_eras' => 1,
            'network.km_out_of_range' => 1,
            'lines.km_drop_without_event' => 2,
            'segments.duplicates' => 1,
            'lines.junction_offset_duplicates' => 1,
            'lines.reference_holes' => 1,
            'segments.heritage_status' => 3,
            'segments.inactive_with_traffic' => 1,
            'lines.junction_gap' => 1,
            // Jonction latérale (test_lateral_junction_gap_needs_a_through_track_a_shared_station_and_compatible_gauges) : aucune ici.
            'lines.junction_gap_lateral' => 0,
            'texts.long_dash' => 3,
            'segments.current_heritage_on_served_station' => 0,
            'segments.current_lgv_not_electrified' => 1,
            'segments.current_missing_speed' => 1,
            // Fiche gare contre fiche ligne (StationServiceYearsTest) : aucun rattachement daté ici.
            'stations.line_service_outside_station_life' => 0,
            'stations.opened_before_lines' => 0,
            // Dates OHM de la loi (OhmLawDateInvariantsTest) : aucune gare ici.
            'stations.ohm_opened_before_lines' => 0,
            'stations.ohm_opened_off_section' => 0,
            // Fret rouvert aux voyageurs ou sur une gare de voyageurs SNCF (PassengerStationOnTrackTest) : aucun ici.
            'segments.current_freight_on_reopened_or_station' => 0,
            // Écartement des liens ligne × matériel (MaterialGaugeInvariantTest) : aucun lien ici.
            'line_materials.gauge_conflict' => 0,
            // Fiabilité des liens douteux (LineMaterialDoubtTest) : aucun lien ici.
            'line_materials.doubtful_confidence' => 0,
            // Électrification des liens ligne × matériel (MaterialTractionInvariantTest) : aucun lien ici.
            'line_materials.traction_conflict' => 0,
        ], $counts);

        $this->assertStringContainsString('200000 Ligne de Bravo à Charlie', $results['segments.current_freight_on_passenger_code']['examples'][0]);
        $this->assertStringContainsString('sur la gare voyageurs Bravo', $results['segments.current_freight_on_passenger_code']['examples'][0]);
        $this->assertStringContainsString('200002 Ligne de Delta à Foxtrot : 7,', $results['segments.current_closed_on_served_station']['examples'][0]);
        $this->assertStringContainsString('gare voyageurs Carentan', $results['segments.current_closed_on_served_station']['examples'][0]);
        $this->assertStringContainsString('en service en 1970, 1980', $results['segments.lgv_before_1981']['examples'][0]);
        $this->assertStringContainsString('en service dès 1970, première ouverture datée le 1985-06-01', $results['segments.in_service_before_first_opening']['examples'][0]);
        $this->assertSame("1980 : Gare Foxtrot (gare #{$lonely->id})", $results['stations.active_without_track']['examples'][0]);
        $this->assertStringContainsString('déposée en 1980 et en 1990', $results['segments.dismantled_two_eras']['examples'][0]);
        $this->assertMatchesRegularExpression('/^2030 principal : 8 km, attendu 28 000 km ± 25 % \(-27 99\d km\)$/u', $results['network.km_out_of_range']['examples'][0]);
        $drops = implode("\n", $results['lines.km_drop_without_event']['examples']);
        $this->assertMatchesRegularExpression('/Ligne de Lima à Mike : 7,\d km en 1970, 1,5 km en 1980/', $drops);
        $this->assertStringContainsString('Ligne de Papa à Quebec', $drops);
        $this->assertStringNotContainsString('Oscar', $drops);
        $this->assertStringContainsString('Ligne de Romeo à Sierra', $results['segments.duplicates']['examples'][0]);
        $this->assertMatchesRegularExpression('/^- Ligne de Victor à Whiskey : \d+ m à 1[45] m de 300000 Ligne de Victor \(tronc\)/u', $results['lines.junction_offset_duplicates']['examples'][0]);
        $this->assertMatchesRegularExpression('/^- Ligne de Saül à Maripasoula : trou de 3[78] m \(47\.\d+, 2\.350\d+\)$/u', $results['lines.reference_holes']['examples'][0]);
        $heritage = implode("\n", $results['segments.heritage_status']['examples']);
        $this->assertStringContainsString('vélorail « in_service »', $heritage);
        $this->assertStringContainsString('tourist_train « abandoned »', $heritage);
        $this->assertStringContainsString('type patrimoine sans drapeau', $heritage);
        $this->assertStringContainsString('« abandoned » en trafic « freight »', $results['segments.inactive_with_traffic']['examples'][0]);
        $this->assertMatchesRegularExpression('/^2030 : - Ligne de Uniform à Xray : bout à 5,50 m de 200100 Ligne de Tango à Uniform \(\d+\.\d{5}, 2\.3\d{4}\)$/u', $results['lines.junction_gap']['examples'][0]);
        $this->assertStringContainsString('LGV Sud-Est : 7,', $results['segments.current_lgv_not_electrified']['examples'][0]);
        $this->assertStringContainsString('(1 tronçons jamais enrichis)', $results['segments.current_lgv_not_electrified']['examples'][0]);
        $this->assertMatchesRegularExpression('/^\d+ km sur \d+ km sans vitesse maximale \([\d,]+ %, seuil 20 %\), dont 2000\d\d Ligne de /u', $results['segments.current_missing_speed']['examples'][0]);
        $texts = implode("\n", $results['texts.long_dash']['examples']);
        foreach (['lines.name', 'stations.name', 'line_events.description'] as $field) {
            $this->assertStringContainsString($field, $texts);
        }
        foreach ($results as $r) {
            $this->assertLessThanOrEqual(DataInvariants::EXAMPLES, count($r['examples']));
            $this->assertCount(min($r['count'], DataInvariants::EXAMPLES), $r['examples'], $r['key']);
        }
    }

    /**
     * Jonction latérale : bout de ligne en service qui s'arrête à côté d'une voie traversante, dans une gare
     * desservie par les deux lignes. Seul le bout A est compté ; B (aucune gare commune), C (écartements
     * différents), D (portion fermée), E (à 40 m) sont conformes.
     */
    public function test_lateral_junction_gap_needs_a_through_track_a_shared_station_and_compatible_gauges(): void
    {
        $main = $this->line('Ligne de Main', [2030 => ['in_service', 'mixed']], code: '200100');
        $m = "(SELECT ST_Transform(geom, 2154) FROM line_segments WHERE line_id = {$main->id})";
        $at = fn (float $f, float $dy) => "ST_Translate(ST_LineInterpolatePoint({$m}, {$f}), 0, {$dy})";
        $branch = function (string $name, float $f, float $dy, string $status = 'in_service', ?int $gauge = null) use ($at): Line {
            $l = Line::factory()->create(['name' => $name, 'code' => null, 'network' => null, 'is_lgv' => false, 'description' => null, 'gauge_mm_default' => $gauge]);
            // Branche venue de 300 m au nord, qui longe la voie à $dy m sur les derniers 100 m puis s'arrête.
            DB::table('line_segments')->insert(['line_id' => $l->id, 'era_id' => $this->eras[2030]->id, 'status' => $status,
                'traffic_type' => $status === 'in_service' ? 'mixed' : 'unknown', 'is_heritage' => false, 'created_at' => now(), 'updated_at' => now(),
                'geom' => DB::raw("ST_Transform(ST_MakeLine(ARRAY[{$at($f - 0.02, 300)}, {$at($f - 0.01, $dy)}, {$at($f, $dy)}]), 4326)")]);

            return $l;
        };
        $serve = function (string $stationName, float $f, Line ...$lines) use ($main): void {
            $lon = 2.30 + 0.1 * $f;
            $station = $this->station($stationName, $lon, $main->lat, [2030 => 'active']);
            foreach ($lines as $line) {
                DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $line->id, 'method' => 'test', 'created_at' => now(), 'updated_at' => now()]);
            }
        };
        $a = $branch('Ligne de Branche A', 0.30, 6.0);
        $serve('Gare Lateral', 0.30, $main, $a);
        $b = $branch('Ligne de Branche B', 0.40, 6.0);
        $serve('Gare Seule B', 0.40, $b);
        $c = $branch('Ligne de Branche C', 0.50, 6.0, gauge: 1000);
        $serve('Gare Metrique', 0.50, $main, $c);
        $d = $branch('Ligne de Branche D', 0.60, 6.0, status: 'abandoned');
        $serve('Gare Fermee', 0.60, $main, $d);
        $e = $branch('Ligne de Branche E', 0.70, 40.0);
        $serve('Gare Eloignee', 0.70, $main, $e);

        $r = collect((new DataInvariants(['lines.junction_gap_lateral']))->run())->keyBy('key')['lines.junction_gap_lateral'];

        $this->assertSame(1, $r['count'], implode(' | ', $r['examples']));
        $this->assertMatchesRegularExpression('/^2030 : - Ligne de Branche A : bout à [56],\d\d m de 200100 Ligne de Main, gare commune Gare Lateral \(\d+\.\d{5}, 2\.3\d{4}\)$/u', $r['examples'][0]);
        // L'ancien invariant ne le comptait pas : bout parallèle, ni arrivée à 1,5 m ni cap vers la voie.
        $old = collect((new DataInvariants(['lines.junction_gap']))->run())->keyBy('key')['lines.junction_gap'];
        $this->assertSame(0, $old['count'], implode(' | ', $old['examples']));
    }

    public function test_heritage_segment_on_a_served_passenger_station(): void
    {
        $this->cleanNetwork();
        // Patrimoine posé sur une portion encore desservie (996000 : tramway de la Balagne, service CFC).
        $h = $this->line('Ligne de Hotel à India', [], code: '200010');
        $this->segment($h, 2030, 'in_service', 'unknown', lat: $h->lat, heritage: 'tourist_train');
        DB::table('sncf_gares')->insert(['code_ligne' => '200010', 'voyageurs' => true, 'fret' => false, 'libelle' => 'Juliett', 'lat' => $h->lat, 'lon' => 2.35]);
        // Conformes : gare de jonction au BOUT de la voie touristique, gare voyageurs sur un tronçon neutralisé.
        $k = $this->line('Ligne de Kilo à Lima', [], code: '200011');
        $this->segment($k, 2030, 'in_service', 'unknown', lat: $k->lat, heritage: 'tourist_train');
        DB::table('sncf_gares')->insert(['code_ligne' => '200011', 'voyageurs' => true, 'fret' => false, 'libelle' => 'Kilo', 'lat' => $k->lat, 'lon' => 2.30]);
        DB::table('sncf_gares')->insert(['code_ligne' => '200011', 'voyageurs' => true, 'fret' => false, 'libelle' => 'Halte fermée', 'lat' => $k->lat, 'lon' => 2.35]);
        DB::table('sncf_line_geometries')->insert(['code_ligne' => '200011', 'libelle' => 'Neutralisée', 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw(sprintf("ST_GeomFromText('LINESTRING(2.32 %F, 2.38 %F)', 4326)", $k->lat, $k->lat))]);

        $result = (new DataInvariants(['segments.current_heritage_on_served_station']))->run();

        $this->assertSame(1, $result[0]['count']);
        $this->assertStringContainsString('200010 Ligne de Hotel à India', $result[0]['examples'][0]);
        $this->assertStringContainsString('gare voyageurs desservie Juliett', $result[0]['examples'][0]);
    }

    public function test_short_piece_next_to_a_portion_dismantled_earlier_is_another_portion(): void
    {
        // Portion déposée visible en 1980 ; en 1990, seuls des morceaux de raccord accolés à son
        // extrémité (≈ 15 m et ≈ 1 m, chronologie propre) sont « déposés » : pas la même portion
        // (cas 665000 Buzy - Laruns et 213000 Marcq-Saint-Juvin - Baroncourt).
        $a = $this->line('Ligne de Hotel à India', [1980 => ['dismantled', 'unknown']]);
        $this->segment($a, 1990, 'dismantled', 'unknown', lon1: 2.40, lon2: 2.4002, lat: $a->lat);
        $this->segment($a, 1990, 'dismantled', 'unknown', lon1: 2.29999, lon2: 2.30, lat: $a->lat);
        // Portion déposée en 1980 dont la moitié est encore « déposée » en 1990 : la même portion.
        $b = $this->line('Ligne de Juliett à Kilo', [1980 => ['dismantled', 'unknown']]);
        $this->segment($b, 1990, 'dismantled', 'unknown', lon1: 2.38, lon2: 2.42, lat: $b->lat);

        $r = collect((new DataInvariants(['segments.dismantled_two_eras']))->run())->keyBy('key')['segments.dismantled_two_eras'];

        $this->assertSame(1, $r['count'], implode(' | ', $r['examples']));
        $this->assertStringContainsString('Ligne de Juliett à Kilo : déposée en 1980 et en 1990', $r['examples'][0]);
    }

    public function test_portion_transferred_to_a_service_track_is_not_a_km_loss(): void
    {
        // 812000 à L'Ardoise : en service en 1990, « transférée en voie de service » ensuite (desserte d'un embranchement).
        $a = $this->line('Ligne de Hotel à India', [1980 => ['in_service', 'mixed'], 1990 => ['service_track', 'unknown']]);
        // Même chute vers « fermée » : signalée.
        $b = $this->line('Ligne de Juliett à Kilo', [1980 => ['in_service', 'mixed'], 1990 => ['abandoned', 'unknown']]);

        $r = collect((new DataInvariants(['lines.km_drop_without_event']))->run())->keyBy('key')['lines.km_drop_without_event'];

        $this->assertSame(1, $r['count'], implode(' | ', $r['examples']));
        $this->assertStringContainsString('Ligne de Juliett à Kilo', $r['examples'][0]);
    }

    public function test_dated_heritage_takeover_explains_a_km_loss_but_not_a_mere_attestation(): void
    {
        // 689000 : Cyclorail du Sancerrois « depuis 2004 » (fin du trafic commercial datée) ; ères du test : 1980 et 1990.
        $a = $this->line('Ligne de Lima à Mike', [1980 => ['in_service', 'mixed'], 1990 => ['abandoned', 'unknown']]);
        $this->event($a, 'heritage_start', '1984-01-01', 'Exploitation associative : vélorail, depuis 1984 (d\'après article Wikipédia de la ligne).');
        // 4154 : vélorail « en activité en 2026 (date de début non trouvée) » : n'explique pas la chute.
        $b = $this->line('Ligne de November à Oscar', [1980 => ['in_service', 'mixed'], 1990 => ['abandoned', 'unknown']]);
        $this->event($b, 'heritage_start', '1986-01-01', 'Exploitation associative : vélorail, en activité en 1986 (date de début non trouvée ; attestation : Wikipédia (liste)).');

        $r = collect((new DataInvariants(['lines.km_drop_without_event']))->run())->keyBy('key')['lines.km_drop_without_event'];

        $this->assertSame(1, $r['count'], implode(' | ', $r['examples']));
        $this->assertStringContainsString('Ligne de November à Oscar', $r['examples'][0]);
    }

    public function test_lgv_must_be_electrified_at_the_current_era_only(): void
    {
        // Électrifiée en 2030 : conforme ; non électrifiée en 1990 (ère passée) : hors invariant.
        $ok = $this->line('LGV Atlantique', [1990 => ['in_service', 'passenger'], 2030 => ['in_service', 'passenger']]);
        DB::table('line_segments')->where('line_id', $ok->id)->where('era_id', $this->eras[2030]->id)
            ->update(['electrified' => true, 'voltage_v' => 25000, 'electrification_source' => 'sncf']);
        // Signal vitesse seul (≥ 250 km/h, nom et drapeau classiques) : LGV non électrifiée enrichie (source posée).
        $fast = $this->line('Ligne de Kilo à Lima', [2030 => ['in_service', 'passenger']], code: '400000');
        DB::table('line_segments')->where('line_id', $fast->id)->update(['max_speed_kmh' => 300, 'electrification_source' => 'unknown']);
        // LGV fermée : hors invariant.
        $this->line('LGV Est européenne', [2030 => ['abandoned', 'unknown']]);
        // Raccordement de service non électrifié selon la liste SNCF (752352 Crest) : attesté, hors invariant.
        $crest = $this->line('Raccordement de Crest (LGV)', [2030 => ['in_service', 'passenger']], code: '752352');
        DB::table('line_segments')->where('line_id', $crest->id)->update(['electrified' => false, 'electrification_source' => 'sncf']);

        $r = collect((new DataInvariants(['segments.current_lgv_not_electrified']))->run())->keyBy('key')['segments.current_lgv_not_electrified'];

        $this->assertSame(1, $r['count']);
        $this->assertSame('400000 Ligne de Kilo à Lima : 7,6 km non électrifiés', $r['examples'][0]);
    }

    public function test_missing_speed_share_is_measured_on_the_current_rfn_only(): void
    {
        // RFN 2030 : 3 lignes de 7,3 km dont une sans vitesse (33 %) ; ère passée, patrimoine et
        // ligne sans code RFN sans vitesse : hors invariant.
        foreach (['500000', '500001', '500002'] as $i => $code) {
            $line = $this->line("Ligne {$code}", [1990 => ['in_service', 'mixed'], 2030 => ['in_service', 'mixed']], code: $code);
            if ($i > 0) {
                DB::table('line_segments')->where('line_id', $line->id)->where('era_id', $this->eras[2030]->id)->update(['max_speed_kmh' => 160]);
            }
        }
        $y = $this->line('Ligne de Charlie à Delta', [2030 => ['in_service', 'mixed']]);
        $this->segment($y, 2030, 'in_service', 'unknown', heritage: 'tourist_train', lat: $y->lat);
        $only = ['segments.current_missing_speed'];

        $r = (new DataInvariants($only))->run()[0];
        $this->assertSame(1, $r['count']);
        $this->assertSame(['8 km sur 23 km sans vitesse maximale (33,3 %, seuil 20 %), dont 500000 Ligne 500000 (7,6 km)'], $r['examples']);

        // Sous le seuil (une ligne sans vitesse sur six) : conforme.
        foreach (['500003', '500004', '500005'] as $code) {
            $line = $this->line("Ligne {$code}", [2030 => ['in_service', 'mixed']], code: $code);
            DB::table('line_segments')->where('line_id', $line->id)->update(['max_speed_kmh' => 100]);
        }
        $this->assertSame(0, (new DataInvariants($only))->run()[0]['count']);
    }

    public function test_two_current_eras_break_the_current_era_invariant(): void
    {
        Era::factory()->current()->create(['year' => 2020]);

        $result = (new DataInvariants(['eras.current']))->run();

        $this->assertCount(1, $result);
        $this->assertSame(2, $result[0]['count']);
        $this->assertSame(['ères courantes : 2020, 2030'], $result[0]['examples']);
    }

    public function test_only_option_selects_invariants_by_key_or_family(): void
    {
        $keys = fn (array $only) => array_column((new DataInvariants($only))->run(), 'key');

        $this->assertSame(['texts.long_dash'], $keys(['texts.long_dash']));
        $this->assertSame(['stations.active_without_track', 'stations.closed_at_outside_eras', 'stations.line_service_outside_station_life',
            'stations.opened_before_lines', 'stations.ohm_opened_before_lines', 'stations.ohm_opened_off_section'], $keys(['stations']));
        $this->assertContains('segments.duplicates', $keys(['segments']));
        $this->assertNotContains('texts.long_dash', $keys(['segments']));

        $this->artisan('vff:check-invariants', ['--only' => 'gares'])->expectsOutputToContain('Invariant(s) inconnu(s) : gares')->assertExitCode(2);
    }

    public function test_command_only_reads_the_database(): void
    {
        $this->cleanNetwork();
        $this->line('Ligne de Xray à Yankee', [2030 => ['abandoned', 'freight']]);
        $before = $this->snapshot();

        DB::enableQueryLog();
        $this->artisan('vff:check-invariants')->assertFailed();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $this->assertNotEmpty($queries);
        foreach ($queries as $sql) {
            $this->assertMatchesRegularExpression('/^\s*(SELECT|WITH|SET TRANSACTION READ ONLY|SET LOCAL max_parallel_workers_per_gather)\b/i', $sql);
            $this->assertDoesNotMatchRegularExpression('/\b(INSERT|UPDATE|DELETE|TRUNCATE|DROP|ALTER|CREATE)\b/i', $sql);
        }
        $this->assertSame($before, $this->snapshot());
    }

    public function test_km_ranges_follow_the_consistency_checker_references(): void
    {
        $ref1900 = ConsistencyChecker::HISTORICAL_KM['principal'][1900];
        $this->assertNull(DataInvariants::kmOutOfRange($ref1900['km'] * 1.2, $ref1900), '+20 % : dans la tolérance');
        $this->assertNull(DataInvariants::kmOutOfRange($ref1900['km'] * 0.8, $ref1900), '-20 % : dans la tolérance');
        $this->assertStringContainsString('attendu 38 000 km', DataInvariants::kmOutOfRange(47526, $ref1900));
        $this->assertStringContainsString('-', DataInvariants::kmOutOfRange($ref1900['km'] * 0.5, $ref1900));
        // Petit réseau : écart relatif fort mais absolu faible (< COMPLETENESS_MIN_GAP_KM) toléré.
        $this->assertNull(DataInvariants::kmOutOfRange(1400, ['km' => 1000]));
        // Réseau secondaire quasi disparu : plafond.
        $this->assertNull(DataInvariants::kmOutOfRange(900, ConsistencyChecker::HISTORICAL_KM['secondaire'][1970]));
        $this->assertSame('1 500 km, plafond 1 000 km', DataInvariants::kmOutOfRange(1500, ConsistencyChecker::HISTORICAL_KM['secondaire'][1970]));
    }

    public function test_rebuild_history_runs_the_invariants_last_without_stopping(): void
    {
        $this->line('Ligne de Xray à Yankee', [2030 => ['abandoned', 'freight']]);

        $this->artisan('vff:rebuild-history', ['--from' => 'check-invariants'])
            ->expectsOutputToContain('vff:check-invariants')
            ->expectsOutputToContain('segments.inactive_with_traffic')
            ->expectsOutputToContain('Carte historique reconstruite')
            ->assertSuccessful();
    }

    // ─── Fabrique ────────────────────────────────────────────────────────

    /** Ligne propre : en service à toutes les ères, ouverte en 1960, gare sur la ligne, code avec gares voyageurs. */
    public function test_track_radius_is_relaxed_only_for_known_poor_positions(): void
    {
        // Gares-relecture (2026-10-20) : position déduite d'un PK / d'un schéma de ligne, précision connue
        // (provenance.geom_precision_m) : rayon = cette précision, au plus 300 m ; sinon 150 m.
        $line = $this->line('Ligne de Golf à Hotel', [1980 => ['in_service', 'mixed']]);
        $lat = $line->lat;
        $place = function (string $name, float $dLat, ?int $precision) use ($lat) {
            $s = $this->station($name, 2.35, $lat + $dLat, [1980 => 'active']);
            if ($precision !== null) {
                $s->update(['provenance' => ['geom_precision_m' => $precision]]);
            }

            return $s;
        };
        $place('Gare PK 500 m à 220 m', 0.002, 500);   // tolérée
        $exact = $place('Gare précise à 220 m', 0.002, null);
        $good = $place('Gare PK 80 m à 220 m', 0.002, 80);
        $far = $place('Gare PK 2 km à 400 m', 0.0036, 2000);

        $r = collect((new DataInvariants(['stations.active_without_track']))->run())->keyBy('key')['stations.active_without_track'];
        $this->assertSame(3, $r['count'], implode(' | ', $r['examples']));
        foreach ([$exact, $good, $far] as $s) {
            $this->assertTrue(collect($r['examples'])->contains(fn ($e) => str_contains($e, "#{$s->id})")), $s->name);
        }
    }

    private function cleanNetwork(): void
    {
        $a = $this->line('Ligne de Alpha à Bravo', [1970 => ['in_service', 'mixed'], 1980 => ['in_service', 'mixed'],
            1990 => ['in_service', 'mixed'], 2030 => ['in_service', 'mixed']], code: '100000');
        DB::table('sncf_gares')->insert(['code_ligne' => '100000', 'voyageurs' => true, 'fret' => true, 'libelle' => 'Alpha']);
        $this->event($a, 'opening', '1960-01-01');
        // Vitesse SNCF posée (vff:import-sncf-vmax) : le RFN de l'ère courante a ses vitesses.
        DB::table('line_segments')->where('line_id', $a->id)->where('era_id', $this->eras[2030]->id)->update(['max_speed_kmh' => 140]);
        $this->station('Gare Alpha', 2.35, $a->lat, [1970 => 'active', 1980 => 'active', 1990 => 'active', 2030 => 'active']);
        // Fermée et déposée : déposée dans la seule ère qui suit la dépose.
        $z = $this->line('Ligne de Bravo à Charlie', [1970 => ['in_service', 'mixed'], 1980 => ['abandoned', 'unknown'], 1990 => ['dismantled', 'unknown']]);
        $this->event($z, 'opening', '1900-01-01');
        $this->event($z, 'closure', '1975-01-01');
        $this->event($z, 'dismantling', '1985-01-01');
        // Train touristique (en service) et vélorail (fermé), tous deux patrimoine.
        $y = $this->line('Ligne de Charlie à Delta', []);
        $this->segment($y, 2030, 'in_service', 'unknown', heritage: 'tourist_train', lat: $y->lat);
        $this->segment($y, 2030, 'abandoned', 'unknown', heritage: 'velorail', lon1: 2.4, lon2: 2.5, lat: $y->lat);
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $states  année → [statut, trafic] du tronçon principal (2,30 → 2,40)
     */
    /**
     * Ligne B (voie BD TOPO rattachée, est-ouest) et ligne A dont le tracé de référence archeo non
     * recalé la longe à $offset m au nord ; $keptParallel : parallèle signalé par build-line-references.
     */
    private function offsetDuplicate(string $name, float $offset, bool $keptParallel = false, ?string $network = null, ?string $otherNetwork = null,
        ?int $gauge = null, ?int $otherGauge = null): void
    {
        $this->lat += 0.02;
        static $n = 0;
        $b = Line::factory()->create(['name' => explode(' à ', $name)[0].' (tronc)', 'code' => (string) (300000 + 1000 * $n++), 'description' => null,
            'network' => $otherNetwork, 'gauge_mm_default' => $otherGauge]);
        $a = Line::factory()->create(['name' => $name, 'code' => null, 'description' => null, 'network' => $network, 'gauge_mm_default' => $gauge]);
        $wkt = fn (float $dy) => sprintf("ST_Multi(ST_GeomFromText('LINESTRING(2.30 %F, 2.40 %F)', 4326))", $this->lat + $dy, $this->lat + $dy);
        DB::table('source_traces')->insert(['source' => 'bdtopo', 'source_ref' => 'inv-'.$n, 'layer' => 'Voie ferrée principale', 'line_id' => $b->id,
            'seen_at' => now(), 'geom' => DB::raw($wkt(0))]);
        $dy = $offset / 111320;
        DB::table('line_reference_parts')->insert(['line_id' => $a->id, 'source' => 'archeoferroviaire', 'source_trace_ids' => '[]', 'length_m' => 7300,
            'geom' => DB::raw($wkt($dy))]);
        if ($keptParallel) {
            DB::table('line_reference_duplicates')->insert(['line_id' => $a->id, 'action' => 'kept_parallel', 'reason' => 'parallèle à '.$b->code,
                'source' => 'archeoferroviaire', 'length_m' => 7300, 'gap_median_m' => $offset,
                'geom' => DB::raw(sprintf("ST_GeomFromText('LINESTRING(2.30 %F, 2.40 %F)', 4326)", $this->lat + $dy, $this->lat + $dy))]);
        }
    }

    /**
     * Jonctions à l'ère courante, sur la ligne principale Tango - Uniform (2,30 → 2,40) : embranchement qui
     * s'arrête 5,5 m avant elle, cap vers elle (violation) ; embranchement qui la touche (conforme) ;
     * terminus parallèle à 6 m sans cap vers elle (pas une jonction, conforme).
     */
    private function junctionGaps(): void
    {
        // Lignes fermées (trafic inconnu) : n'interfèrent pas avec les km en service ni les règles de trafic.
        $main = $this->line('Ligne de Tango à Uniform', [2030 => ['abandoned', 'unknown']], code: '200100');
        // Géométries construites en Lambert-93 sur la corde dessinée de la ligne principale (un parallèle
        // n'est pas droit en Lambert-93 : le milieu d'une corde de 0,1° s'en écarte de ~1,7 m).
        $m = "(SELECT ST_Transform(geom, 2154) FROM line_segments WHERE line_id = {$main->id})";
        $seg = function (string $name, string $sql2154) {
            $l = Line::factory()->create(['name' => $name, 'code' => null, 'network' => null, 'is_lgv' => false, 'description' => null]);
            DB::table('line_segments')->insert(['line_id' => $l->id, 'era_id' => $this->eras[2030]->id, 'status' => 'abandoned', 'traffic_type' => 'unknown',
                'is_heritage' => false, 'geom' => DB::raw("ST_Transform({$sql2154}, 4326)"), 'created_at' => now(), 'updated_at' => now()]);
        };
        $at = fn (float $f, float $dy) => "ST_Translate(ST_LineInterpolatePoint({$m}, {$f}), 0, {$dy})";
        $seg('Ligne de Uniform à Xray', "ST_MakeLine({$at(0.5, 300)}, {$at(0.5, 5.5)})");
        $seg('Ligne de Uniform à Yankee', "ST_MakeLine({$at(0.7, 300)}, {$at(0.7, 0)})");
        $seg('Ligne de Uniform à Zoulou', "ST_MakeLine(ARRAY[{$at(0.1, 300)}, {$at(0.2, 6)}, {$at(0.3, 6)}])");
    }

    /** Tracé de référence en deux morceaux séparés de 0,0005° (≈ 38 m) ; $cover : voie BD TOPO non rattachée qui traverse le trou. */
    private function referenceHole(string $name, bool $cover): void
    {
        $this->lat += 0.02;
        $a = Line::factory()->create(['name' => $name, 'code' => null, 'description' => null]);
        DB::table('line_reference_parts')->insert(['line_id' => $a->id, 'source' => 'bdtopo', 'source_trace_ids' => '[]', 'length_m' => 7300,
            'geom' => DB::raw(sprintf("ST_GeomFromText('MULTILINESTRING((2.30 %F, 2.35 %F), (2.3505 %F, 2.40 %F))', 4326)", $this->lat, $this->lat, $this->lat, $this->lat))]);
        if ($cover) {
            DB::table('source_traces')->insert(['source' => 'bdtopo', 'source_ref' => 'trou-'.$a->id, 'layer' => 'Voie ferrée principale', 'line_id' => null,
                'seen_at' => now(), 'geom' => DB::raw(sprintf("ST_Multi(ST_GeomFromText('LINESTRING(2.349 %F, 2.352 %F)', 4326))", $this->lat, $this->lat))]);
        }
    }

    /**
     * @param  array<int, array{string, string}>  $states
     * @return Line&object{lat: float} ligne portant la latitude de son axe (attribut de test)
     */
    private function line(string $name, array $states, ?string $code = null): Line
    {
        $this->lat += 0.02;
        /** @var Line&object{lat: float} $line */
        $line = Line::factory()->create(['name' => $name, 'code' => $code, 'network' => null, 'is_lgv' => false, 'description' => null]);
        $line->setAttribute('lat', $this->lat);
        foreach ($states as $year => [$status, $traffic]) {
            $this->segment($line, $year, $status, $traffic, lat: $this->lat);
        }

        return $line;
    }

    private function segment(Line $line, int $year, string $status, string $traffic, float $lon1 = 2.30, float $lon2 = 2.40, ?float $lat = null,
        ?string $heritage = null, ?bool $isHeritage = null): void
    {
        $lat ??= $this->lat + 1;
        DB::table('line_segments')->insert([
            'line_id' => $line->id, 'era_id' => $this->eras[$year]->id, 'status' => $status, 'traffic_type' => $traffic,
            'heritage_kind' => $heritage, 'is_heritage' => $isHeritage ?? $heritage !== null,
            'geom' => DB::raw(sprintf("ST_GeomFromText('LINESTRING(%F %F, %F %F)', 4326)", $lon1, $lat, $lon2, $lat)),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function event(Line $line, string $type, string $date, ?string $description = null): void
    {
        DB::table('line_events')->insert(['line_id' => $line->id, 'type' => $type, 'event_date' => $date,
            'description' => $description ?? "{$type} {$date}", 'source' => 'wikipedia', 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param  array<int, string>  $statusByEra */
    private function station(string $name, float $lon, float $lat, array $statusByEra, bool $uncertain = false): Station
    {
        $station = Station::factory()->create(['name' => $name, 'geom' => Point::makeGeodetic($lat, $lon), 'description' => null]);
        foreach ($statusByEra as $year => $status) {
            DB::table('station_eras')->insert(['station_id' => $station->id, 'era_id' => $this->eras[$year]->id, 'status' => $status,
                'uncertain' => $uncertain, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $station;
    }

    /** @return array<string, int> nombre de lignes par table */
    private function snapshot(): array
    {
        $out = [];
        foreach (['lines', 'line_segments', 'line_events', 'stations', 'station_eras', 'eras', 'sncf_gares'] as $table) {
            $out[$table] = DB::table($table)->count();
        }
        $out['updated'] = (int) DB::table('line_segments')->max(DB::raw('EXTRACT(EPOCH FROM updated_at)'));

        return $out;
    }
}
