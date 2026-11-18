<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use App\Services\LineClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * vff:classify-lines (App\Services\LineClassifier) : trafic officiel de l'ère
 * courante à partir des référentiels SNCF (liste-des-gares, types de ligne,
 * catégorie LGV, formes des lignes exploitées).
 *
 * Règles vérifiées :
 *   - ligne SNCF avec des gares voyageurs → mixte (voyageurs implique fret),
 *     même si une seule gare est ouverte aux voyageurs ;
 *   - LGV (catégorie SNCF, nom, vitesse) → voyageurs seuls ;
 *   - ligne sans code SNCF → trafic du moteur conservé ;
 *   - code non exploité par SNCF Réseau mais en service → mixte, confiance basse ;
 *   - raccordement : hérite de ses voisines, voyageurs seuls s'il dessert une LGV,
 *     jamais une voie-mère ;
 *   - tronçon fermé → trafic inconnu.
 *
 * Trafic PAR PORTION (gares de liste-des-gares placées, réseau fabriqué de
 * Tests\Support\BuildsRailNetwork) :
 *   - mixte jusqu'à la dernière gare voyageurs, fret seul au-delà (tronçon coupé à la gare) ;
 *   - desserte saisonnière ou partielle : reste mixte, même sous une fermeture ancienne ;
 *   - LGV : voyageurs, jamais coupée ;
 *   - raccordement : hérite de la portion qu'il touche ;
 *   - drapeau voyageurs périmé (tronçon neutralisé, fermeture récente, portion rouverte
 *     ailleurs seulement) : ignoré ;
 *   - ligne traversante, gare terminus, gare de bifurcation au bout d'une ligne fermée aux voyageurs.
 *
 * Lignes est-ouest espacées de ~2,2 km (0,02°) : aucune n'est voisine d'une
 * autre (seuil de 60 m) sauf par les raccordements. Les lignes par portion sont
 * espacées de 0,1° (11 km) : aucune gare à moins de 1 km du bout d'une autre.
 */
class LineClassifierTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    private Era $current;

    protected function setUp(): void
    {
        parent::setUp();
        $this->current = Era::factory()->current()->create(['year' => 2030]);
    }

    public function test_sncf_line_with_passenger_stations_is_mixed(): void
    {
        $line = $this->line('Ligne de Alpha à Bravo', '100000', 47.10, traffic: 'freight');
        $this->exploited('100000');
        $this->gare('100000', voyageurs: true, fret: true);

        $this->classify();

        $this->assertTraffic($line, ['mixed']);
        $this->assertSame('mixed', $line->fresh()->type?->value);
        $this->assertSource($line, 'high', 'sncf-liste-des-gares');
    }

    public function test_a_single_passenger_station_is_enough_for_mixed(): void
    {
        // Desserte voyageurs partielle (une halte) sur une ligne surtout fret.
        $line = $this->line('Ligne de Charlie à Delta', '100001', 47.12, traffic: 'freight');
        $this->exploited('100001');
        $this->gare('100001', voyageurs: true, fret: false);
        foreach (range(1, 3) as $i) {
            $this->gare('100001', voyageurs: false, fret: true);
        }

        $this->classify();

        $this->assertTraffic($line, ['mixed']);
    }

    public function test_high_speed_lines_are_passenger_only(): void
    {
        $byCategory = $this->line('Ligne de Paris à Lyon (nouvelle)', '752000', 47.14, traffic: 'mixed');
        DB::table('sncf_line_catlig')->insert(['code_ligne' => '752000', 'catlig' => 'Ligne à grande vitesse']);
        $this->exploited('752000');
        $this->gare('752000', voyageurs: true, fret: false); // gares TGV : voyageurs, sans effet sur la LGV
        $byName = $this->line('LGV Est européenne', '005000', 47.16, traffic: 'freight');
        $this->exploited('005000');
        $bySpeed = $this->line('Ligne nouvelle de Charlie', '006000', 47.18, traffic: 'unknown', speed: 320);
        $this->exploited('006000');

        $this->classify();

        foreach ([$byCategory, $byName, $bySpeed] as $line) {
            $this->assertTraffic($line, ['passenger'], $line->name);
            $this->assertTrue($line->fresh()->is_lgv, $line->name);
            $this->assertSame('passenger', $line->fresh()->type?->value);
        }
        $this->assertSource($byCategory, 'high', 'sncf-catlig-lgv');
    }

    public function test_line_without_code_keeps_the_traffic_dated_by_the_engine(): void
    {
        $tramway = $this->line('Tramway de Echo à Foxtrot', null, 47.20, traffic: 'freight', network: 'secondaire');
        $empty = $this->line('Ligne de Golf à Hotel', '', 47.22, traffic: 'mixed');

        $this->classify();

        $this->assertTraffic($tramway, ['freight']);
        $this->assertTraffic($empty, ['mixed']);
        $this->assertSame(0, DB::table('line_sources')->whereIn('line_id', [$tramway->id, $empty->id])->count());
    }

    public function test_code_not_operated_by_sncf_but_in_service_is_mixed_with_low_confidence(): void
    {
        // Ligne cédée à un autre opérateur (RER A, Corse…) : absente de liste-des-gares.
        $line = $this->line('Ligne de India à Juliett', '990000', 47.24, traffic: 'freight');
        DB::table('sncf_line_geometries')->insert($this->sncfGeometry('990000', 'Déclassée (Plus utilisable)', 47.24));

        $stats = $this->classify();

        $this->assertTraffic($line, ['mixed']);
        $this->assertSource($line, 'low', 'hors-rfn-en-service');
        $this->assertContains('990000', array_column($stats['report'], 'code'));
    }

    public function test_heritage_segments_are_never_given_commercial_traffic(): void
    {
        // 795000 Bonson - Sembadel : code hors RFN en service (« mixte », confiance basse), mais le
        // tronçon du Chemin de fer du Haut-Forez est associatif : il garde le trafic du moteur.
        $line = $this->line('Ligne de Kilo à Lima', '795000', 47.26, traffic: 'freight');
        $this->segment($line, 47.26, 'in_service', 'mixed', lon1: 2.60, lon2: 2.90);
        DB::table('line_segments')->where('line_id', $line->id)->where('traffic_type', 'mixed')
            ->update(['is_heritage' => true, 'heritage_kind' => 'tourist_train']);
        DB::table('sncf_line_geometries')->insert($this->sncfGeometry('795000', 'Déclassée (Plus utilisable)', 47.26));

        $this->classify();

        $rows = DB::table('line_segments')->where('line_id', $line->id)->orderBy('is_heritage')->get(['is_heritage', 'heritage_kind', 'traffic_type']);
        $this->assertSame([[false, null, 'mixed'], [true, 'tourist_train', 'unknown']],
            $rows->map(fn ($r) => [(bool) $r->is_heritage, $r->heritage_kind, $r->traffic_type])->all());
        $this->assertSame('mixed', $line->fresh()->type?->value);
    }

    public function test_line_whose_segments_in_service_are_all_heritage_is_not_classified(): void
    {
        // 131000 Cernay - Sewen : seul le Train Thur Doller Alsace circule (classé « mixte » à tort).
        $line = $this->line('Ligne de Mike à November', '131000', 47.28, traffic: 'mixed');
        $this->segment($line, 47.28, 'abandoned', 'unknown', lon1: 2.60, lon2: 2.70);
        DB::table('line_segments')->where('line_id', $line->id)->where('status', 'in_service')
            ->update(['is_heritage' => true, 'heritage_kind' => 'tourist_train']);
        DB::table('lines')->where('id', $line->id)->update(['type' => 'mixed']);
        DB::table('sncf_line_geometries')->insert($this->sncfGeometry('131000', 'Fermée', 47.28));
        $this->gare('131000', true, true);

        $this->classify();

        $this->assertTraffic($line, ['unknown']);
        $this->assertNull($line->fresh()->type);
        $this->assertSame([], $this->sourceFields($line), 'pas de classement SNCF');
    }

    public function test_freight_only_and_unknown_lines(): void
    {
        $freight = $this->line('Ligne de Kilo à Lima', '200000', 47.26, traffic: 'mixed');
        $this->exploited('200000');
        $this->gare('200000', voyageurs: false, fret: true);

        $noStation = $this->line('Ligne de Mike à November', '200001', 47.28, traffic: 'mixed');
        $this->exploited('200001');
        DB::table('sncf_line_types')->insert(['code_ligne' => '200001', 'type_ligne' => 'Ligne']);

        $port = $this->line('Voie du port de Oscar', '200002', 47.30, traffic: 'mixed');
        $this->exploited('200002');
        DB::table('sncf_line_types')->insert(['code_ligne' => '200002', 'type_ligne' => 'Vport']);

        $nothing = $this->line('Ligne de Papa à Quebec', '200003', 47.32, traffic: 'mixed');
        $this->exploited('200003');

        $this->classify();

        $this->assertTraffic($freight, ['freight']);
        $this->assertSource($freight, 'high', 'sncf-liste-des-gares-fret');
        $this->assertTraffic($noStation, ['freight']);
        $this->assertSource($noStation, 'low', 'fallback-ligne-sans-gare');
        $this->assertTraffic($port, ['freight']);
        // Aucun signal : jamais « fret » par défaut.
        $this->assertTraffic($nothing, ['unknown']);
        $this->assertSource($nothing, 'insufficient', 'aucun');
        $this->assertNull($nothing->fresh()->type);
    }

    public function test_closed_segments_have_no_traffic(): void
    {
        $line = $this->line('Ligne de Romeo à Sierra', '300000', 47.34, traffic: 'mixed');
        $this->segment($line, 47.34, 'abandoned', 'freight', lon1: 2.60, lon2: 2.80);
        $this->exploited('300000');
        $this->gare('300000', voyageurs: true, fret: true);

        $this->classify();

        $this->assertSame(['abandoned' => 'unknown', 'in_service' => 'mixed'],
            DB::table('line_segments')->where('line_id', $line->id)->orderBy('status')->pluck('traffic_type', 'status')->all());
    }

    public function test_junctions_inherit_from_their_neighbours(): void
    {
        $lgv = $this->line('LGV Atlantique', '429000', 47.40, traffic: 'passenger');
        DB::table('sncf_line_catlig')->insert(['code_ligne' => '429000', 'catlig' => 'Ligne à grande vitesse']);
        $classic = $this->line('Ligne de Tango à Uniform', '400000', 47.42, traffic: 'mixed');
        $this->gare('400000', voyageurs: true, fret: true);
        $other = $this->line('Ligne de Victor à Whisky', '401000', 47.44, traffic: 'freight');
        $this->gare('401000', voyageurs: true, fret: true);
        $freightOnly = $this->line('Ligne de Xray à Yankee', '402000', 47.46, traffic: 'freight');
        $this->gare('402000', voyageurs: false, fret: true);
        foreach (['429000', '400000', '401000', '402000'] as $code) {
            $this->exploited($code);
        }

        // Raccordement LGV : une extrémité branchée sur la LGV.
        $toLgv = $this->junction('Raccordement de Tango', '429300', 2.30, 47.40, 47.42);
        // Raccordement entre deux lignes classiques.
        $classicJunction = $this->junction('Raccordement de Victor', '400300', 2.40, 47.42, 47.44);
        // Raccordement vers une ligne fret seul.
        $freightJunction = $this->junction('Raccordement de Xray', '401300', 2.50, 47.44, 47.46);
        // Voie-mère branchée sur la LGV : jamais LGV.
        $siding = $this->junction('Voie-mère de l\'usine Zulu', '429900', 2.35, 47.40, 47.39, type: 'Vmère');

        $this->classify();

        $this->assertTraffic($toLgv, ['passenger']);
        $this->assertTrue($toLgv->fresh()->is_lgv);
        $this->assertTraffic($classicJunction, ['mixed']);
        $this->assertFalse($classicJunction->fresh()->is_lgv);
        $this->assertTraffic($freightJunction, ['mixed'], 'une voisine voyageurs suffit : mixte');
        $this->assertNotSame(['passenger'], $this->traffic($siding));
        $this->assertFalse($siding->fresh()->is_lgv);
        $this->assertSource($toLgv, 'medium', 'inherit-voisins');
    }

    public function test_dry_run_writes_nothing(): void
    {
        $line = $this->line('Ligne de Alpha à Bravo', '100000', 47.10, traffic: 'freight');
        $this->exploited('100000');
        $this->gare('100000', voyageurs: true, fret: true);

        $this->artisan('vff:classify-lines', ['--dry-run' => true])->expectsOutputToContain('DRY-RUN')->assertSuccessful();

        $this->assertTraffic($line, ['freight']);
        $this->assertSame(0, DB::table('line_sources')->count());
    }

    public function test_command_classifies_the_current_era_only(): void
    {
        $past = Era::factory()->create(['year' => 1990]);
        $line = $this->line('Ligne de Alpha à Bravo', '100000', 47.10, traffic: 'freight');
        $this->segment($line, 47.10, 'in_service', 'freight', era: $past);
        $this->exploited('100000');
        $this->gare('100000', voyageurs: true, fret: true);

        $this->artisan('vff:classify-lines')->assertSuccessful();

        $this->assertSame('mixed', DB::table('line_segments')->where('line_id', $line->id)->where('era_id', $this->current->id)->value('traffic_type'));
        $this->assertSame('freight', DB::table('line_segments')->where('line_id', $line->id)->where('era_id', $past->id)->value('traffic_type'));
    }

    // ─── Trafic par portion ──────────────────────────────────────────────

    public function test_mixed_up_to_the_last_passenger_station_then_freight_beyond(): void
    {
        // Longueville - Esternay : voyageurs jusqu'à Provins (2,3), fret seul au-delà ; un seul tronçon du moteur.
        $lat = 46.10;
        $line = $this->portionLine($this->current->id, '310000', $lat, name: 'Ligne de Longueville à Esternay');
        $this->portionStation('310900', $lat, 2.0, voyageurs: true, fret: true, name: 'Longueville'); // gare de bifurcation, autre code
        $this->portionStation('310000', $lat, 2.1, voyageurs: true, fret: false);
        $this->portionStation('310000', $lat, 2.2, voyageurs: true, fret: false);
        $this->portionStation('310000', $lat, 2.3, voyageurs: true, fret: true, name: 'Provins');
        $this->portionStation('310000', $lat, 2.45, voyageurs: false, fret: true, name: 'Léchelle');
        $this->portionStation('310000', $lat, 2.55, voyageurs: false, fret: false);
        $this->portionStation('310800', $lat, 2.6, voyageurs: false, fret: true, name: 'Esternay');

        $stats = $this->classify();

        $this->assertSame('mixed', $this->portionTrafficAt($line, $this->current->id, $lat, 2.05), 'Longueville - première gare : vers la bifurcation');
        $this->assertSame('mixed', $this->portionTrafficAt($line, $this->current->id, $lat, 2.25));
        $this->assertSame('freight', $this->portionTrafficAt($line, $this->current->id, $lat, 2.35), 'au-delà de Provins');
        $this->assertSame('freight', $this->portionTrafficAt($line, $this->current->id, $lat, 2.58));
        // Coupé à Provins : deux tronçons bout à bout, longueur conservée.
        $pieces = DB::table('line_segments')->where('line_id', $line->id)->selectRaw('ST_XMin(geom::box2d) AS x1, ST_XMax(geom::box2d) AS x2, traffic_type')->orderBy('x1')->get();
        $this->assertCount(2, $pieces);
        $this->assertEqualsWithDelta(2.3, $pieces[0]->x2, 0.002);
        $this->assertEqualsWithDelta(2.3, $pieces[1]->x1, 0.002);
        $this->assertSame('mixed', $line->fresh()->type?->value);
        $this->assertSource($line, 'high', 'sncf-liste-des-gares-portions');
        $this->assertSame(1, $stats['portions']['split']);

        // Idempotent : un second passage ne recoupe rien et garde les mêmes trafics.
        $again = $this->classify();
        $this->assertSame(0, $again['portions']['split']);
        $this->assertSame(2, DB::table('line_segments')->where('line_id', $line->id)->count());
        $this->assertSame('freight', $this->portionTrafficAt($line, $this->current->id, $lat, 2.35));
    }

    public function test_seasonal_or_partial_passenger_service_stays_mixed(): void
    {
        // Deux haltes desservies (saison, quelques trains) sur une ligne surtout fret, fermée aux
        // voyageurs en 1980 : le drapeau actuel l'emporte sur une fermeture ancienne.
        $lat = 46.20;
        $line = $this->portionLine($this->current->id, '320000', $lat, pieces: [[2.0, 2.3], [2.3, 2.6]], traffic: 'freight');
        $this->portionEvent($line, 'closure_passenger', '1980-05-31', $lat, 2.0, 2.6);
        $this->portionStation('320000', $lat, 2.1, voyageurs: true, fret: false, name: 'Halte saisonnière');
        $this->portionStation('320000', $lat, 2.3, voyageurs: false, fret: true);
        $this->portionStation('320000', $lat, 2.5, voyageurs: true, fret: false, name: 'Halte du dimanche');

        $this->classify();

        foreach ([2.15, 2.25, 2.35, 2.45] as $lon) {
            $this->assertSame('mixed', $this->portionTrafficAt($line, $this->current->id, $lat, $lon), "lon {$lon}");
        }
        $this->assertSame('freight', $this->portionTrafficAt($line, $this->current->id, $lat, 2.05), 'avant la première halte');
        $this->assertSame('freight', $this->portionTrafficAt($line, $this->current->id, $lat, 2.55), 'après la dernière halte');
    }

    public function test_high_speed_line_with_stations_is_passenger_and_never_cut(): void
    {
        $lat = 46.30;
        $line = $this->portionLine($this->current->id, '330000', $lat, pieces: [[2.0, 2.6]], traffic: 'mixed', name: 'Ligne de Alpha à Bravo (LGV)');
        DB::table('sncf_line_catlig')->insert(['code_ligne' => '330000', 'catlig' => 'Ligne à grande vitesse']);
        $this->portionStation('330000', $lat, 2.2, voyageurs: true, fret: false, name: 'Gare TGV');
        $this->portionStation('330000', $lat, 2.4, voyageurs: false, fret: true, name: 'Base travaux');

        $this->classify();

        $this->assertTraffic($line, ['passenger']);
        $this->assertSame(1, DB::table('line_segments')->where('line_id', $line->id)->count());
        $this->assertTrue($line->fresh()->is_lgv);
    }

    public function test_junction_inherits_the_traffic_of_the_portion_it_touches(): void
    {
        $lat = 46.40;
        $line = $this->portionLine($this->current->id, '340000', $lat);
        $this->portionStation('340000', $lat, 2.1, voyageurs: true, fret: false);
        $this->portionStation('340000', $lat, 2.3, voyageurs: true, fret: true);
        $this->portionStation('340000', $lat, 2.5, voyageurs: false, fret: true);
        // Raccordements nord-sud branchés sur la portion mixte (2,2) et sur la portion fret (2,5).
        $toMixed = $this->junction('Raccordement de Charlie', '340300', 2.2, $lat, $lat + 0.02);
        $toFreight = $this->junction('Raccordement de Delta', '340301', 2.5, $lat, $lat + 0.02);

        $this->classify();

        $this->assertSame('freight', $this->portionTrafficAt($line, $this->current->id, $lat, 2.5));
        $this->assertTraffic($toMixed, ['mixed']);
        $this->assertTraffic($toFreight, ['freight']);
        $this->assertSource($toFreight, 'medium', 'inherit-voisins');
    }

    public function test_stale_passenger_flags_are_ignored(): void
    {
        // a) Gares voyageurs sur un tronçon SNCF neutralisé (2,3 - 2,6) : ignorées ; ce tronçon garde le trafic du moteur.
        $lat = 46.50;
        $neutral = $this->portionLine($this->current->id, '350000', $lat, pieces: [[2.0, 2.3], [2.3, 2.6]], traffic: 'unknown',
            status: [[2.0, 2.3, 'Exploitée'], [2.3, 2.6, 'Neutralisée']]);
        $this->portionStation('350000', $lat, 2.05, voyageurs: true, fret: false);
        $this->portionStation('350000', $lat, 2.15, voyageurs: true, fret: true);
        $this->portionStation('350000', $lat, 2.45, voyageurs: true, fret: false, name: 'Halte neutralisée');

        // b) Fermeture aux voyageurs en 2016 de 2,2 à 2,6, sans réouverture : les gares à l'intérieur sont
        //    ignorées, celle qui borne la fermeture (2,2) reste desservie.
        $lat2 = 46.60;
        $closed = $this->portionLine($this->current->id, '360000', $lat2);
        $this->portionEvent($closed, 'closure_passenger', '2016-04-03', $lat2, 2.2, 2.6);
        $this->portionStation('360000', $lat2, 2.1, voyageurs: true, fret: false);
        $this->portionStation('360000', $lat2, 2.2, voyageurs: true, fret: false, name: 'Borne');
        $this->portionStation('360000', $lat2, 2.3, voyageurs: false, fret: true);
        $this->portionStation('360000', $lat2, 2.4, voyageurs: true, fret: false, name: 'Neuilly');
        $this->portionStation('360000', $lat2, 2.5, voyageurs: true, fret: true, name: 'Oulchy');

        // c) Fermeture aux voyageurs de toute la ligne en 2018 : plus aucune portion voyageurs.
        $lat3 = 46.70;
        $whole = $this->portionLine($this->current->id, '361000', $lat3, name: 'Ligne de Kalhausen à Sarralbe');
        $this->portionEvent($whole, 'closure_passenger', '2018-12-22', $lat3, 2.0, 2.6);
        $this->portionStation('361000', $lat3, 2.3, voyageurs: true, fret: false, name: 'Herbitzheim');
        $this->portionStation('361000', $lat3, 2.4, voyageurs: false, fret: true);

        $this->classify();

        $this->assertSame('mixed', $this->portionTrafficAt($neutral, $this->current->id, $lat, 2.1));
        $this->assertSame('unknown', $this->portionTrafficAt($neutral, $this->current->id, $lat, 2.45), 'neutralisé : trafic du moteur');
        $this->assertStringContainsString('Halte neutralisée (tronçon SNCF non exploité)', $this->sourceFields($neutral)['stale_stations'][0]);

        $this->assertSame('mixed', $this->portionTrafficAt($closed, $this->current->id, $lat2, 2.15));
        $this->assertSame('freight', $this->portionTrafficAt($closed, $this->current->id, $lat2, 2.35));
        $this->assertSame('freight', $this->portionTrafficAt($closed, $this->current->id, $lat2, 2.55));
        $stale = implode(' | ', $this->sourceFields($closed)['stale_stations']);
        $this->assertStringContainsString('Neuilly', $stale);
        $this->assertStringContainsString('Oulchy', $stale);
        $this->assertStringNotContainsString('Borne', $stale);

        $this->assertTraffic($whole, ['freight']);
        $this->assertSame('freight', $whole->fresh()->type?->value);
    }

    public function test_station_of_a_portion_reopened_elsewhere_only_is_stale(): void
    {
        // Rive droite du Rhône (800000) : fermée aux voyageurs en 1973, rouverte en 2022 de Pont-Saint-Esprit
        // (2,4) au bout de ligne seulement. Saint-Péray (2,1), « voyageurs » dans liste-des-gares, est un arrêt
        // de car : ni zone voyageurs jusqu'à elle, ni prolongement vers Givors.
        $lat = 47.10;
        $line = $this->portionLine($this->current->id, '395000', $lat, pieces: [[2.0, 2.3], [2.3, 2.6]], name: 'Ligne de Givors-Canal à Grezan');
        $this->portionEvent($line, 'closure_passenger', '1973-08-06', $lat, 2.0, 2.6);
        $this->portionEvent($line, 'reopening_passenger', '2022-08-29', $lat, 2.4, 2.6);
        $this->portionStation('395900', $lat, 2.0, voyageurs: true, fret: true, name: 'Givors-Canal');
        $this->portionStation('395000', $lat, 2.1, voyageurs: true, fret: false, name: 'Saint-Péray');
        $this->portionStation('395000', $lat, 2.2, voyageurs: false, fret: true);
        $this->portionStation('395000', $lat, 2.4, voyageurs: true, fret: false, name: 'Pont-Saint-Esprit');
        $this->portionStation('395000', $lat, 2.5, voyageurs: true, fret: false, name: 'Bagnols');
        $this->portionStation('395901', $lat, 2.6, voyageurs: true, fret: false, name: 'Nîmes');

        $this->classify();

        $this->assertSame('freight', $this->portionTrafficAt($line, $this->current->id, $lat, 2.05));
        $this->assertSame('freight', $this->portionTrafficAt($line, $this->current->id, $lat, 2.15), 'Saint-Péray : arrêt de car');
        $this->assertSame('freight', $this->portionTrafficAt($line, $this->current->id, $lat, 2.35));
        $this->assertSame('mixed', $this->portionTrafficAt($line, $this->current->id, $lat, 2.45));
        $this->assertSame('mixed', $this->portionTrafficAt($line, $this->current->id, $lat, 2.55), 'jusqu\'à la bifurcation (Nîmes)');
        $this->assertSame(['Saint-Péray (portion fermée aux voyageurs, rouverte ailleurs seulement)'], $this->sourceFields($line)['stale_stations']);
    }

    public function test_through_line_terminus_and_junction_station(): void
    {
        // Ligne traversante (Culmont - Merrey) : haltes fermées jusqu'aux gares de jonction d'autres codes.
        $lat = 46.80;
        $through = $this->portionLine($this->current->id, '370000', $lat, pieces: [[2.0, 2.2], [2.2, 2.4], [2.4, 2.6]]);
        $this->portionStation('370900', $lat, 2.0, voyageurs: true, fret: true, name: 'Culmont');
        $this->portionStation('370000', $lat, 2.1, voyageurs: false, fret: false);
        $this->portionStation('370000', $lat, 2.3, voyageurs: true, fret: true, name: 'Merrey');
        $this->portionStation('370000', $lat, 2.5, voyageurs: false, fret: false);
        $this->portionStation('370901', $lat, 2.6, voyageurs: true, fret: false, name: 'Toul');

        // Gare terminus en cul-de-sac (Fécamp) : fermée aux voyageurs en 1970, rouverte (portion inconnue).
        $lat2 = 46.90;
        $terminus = $this->portionLine($this->current->id, '380000', $lat2, name: 'Ligne de Bréauté à Fécamp');
        $this->portionEvent($terminus, 'closure_passenger', '1970-01-01', $lat2, 2.0, 2.6);
        $this->portionEvent($terminus, 'reopening_passenger', '1981-01-01', $lat2);
        $this->portionStation('380900', $lat2, 2.0, voyageurs: true, fret: false, name: 'Bréauté');
        $this->portionStation('380000', $lat2, 2.3, voyageurs: false, fret: false);
        $this->portionStation('380000', $lat2, 2.6, voyageurs: true, fret: true, name: 'Fécamp');

        // Gare de bifurcation au bout d'une ligne fermée aux voyageurs en 1939 (Rognac - Aix) : fret.
        $lat3 = 47.00;
        $branch = $this->portionLine($this->current->id, '390000', $lat3, name: 'Ligne de Rognac à Aix');
        $this->portionEvent($branch, 'closure_passenger', '1939-04-18', $lat3, 2.0, 2.6);
        $this->portionStation('390000', $lat3, 2.0, voyageurs: true, fret: true, name: 'Rognac');
        $this->portionStation('390000', $lat3, 2.4, voyageurs: false, fret: true);
        $main = $this->portionLine($this->current->id, '390100', $lat3, pieces: [], name: 'Ligne de Paris à Marseille');
        DB::table('line_segments')->insert(['line_id' => $main->id, 'era_id' => $this->current->id, 'status' => 'in_service', 'traffic_type' => 'mixed',
            'geom' => DB::raw(sprintf("ST_GeomFromText('LINESTRING(2.0 %F, 2.0 %F)', 4326)", $lat3 - 0.05, $lat3 + 0.05)), 'created_at' => now(), 'updated_at' => now()]);
        $this->portionStation('390100', $lat3, 2.0, voyageurs: true, fret: true, name: 'Rognac');

        $this->classify();

        $this->assertTraffic($through, ['mixed']);
        $this->assertSame(3, DB::table('line_segments')->where('line_id', $through->id)->count(), 'rien à couper');
        $this->assertTraffic($terminus, ['mixed']);
        $this->assertTraffic($branch, ['freight']);
    }

    // ─── Fabrique ────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function classify(): array
    {
        return app(LineClassifier::class)->classifyEra($this->current->id);
    }

    private function line(string $name, ?string $code, float $lat, string $traffic, string $network = 'principal', ?int $speed = null): Line
    {
        $line = Line::factory()->create(['name' => $name, 'code' => $code, 'type' => null, 'network' => $network, 'is_lgv' => false]);
        $this->segment($line, $lat, 'in_service', $traffic, speed: $speed);

        return $line;
    }

    private function segment(Line $line, float $lat, string $status, string $traffic, float $lon1 = 2.00, float $lon2 = 2.60, ?int $speed = null, ?Era $era = null): void
    {
        DB::table('line_segments')->insert([
            'line_id' => $line->id, 'era_id' => ($era ?? $this->current)->id, 'status' => $status, 'traffic_type' => $traffic, 'max_speed_kmh' => $speed,
            'geom' => DB::raw(sprintf("ST_GeomFromText('LINESTRING(%F %F, %F %F)', 4326)", $lon1, $lat, $lon2, $lat)),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Raccordement nord-sud entre deux latitudes, à la longitude donnée. */
    private function junction(string $name, string $code, float $lon, float $lat1, float $lat2, string $type = 'Rac'): Line
    {
        $line = Line::factory()->create(['name' => $name, 'code' => $code, 'type' => null, 'network' => 'principal', 'is_lgv' => false]);
        DB::table('line_segments')->insert([
            'line_id' => $line->id, 'era_id' => $this->current->id, 'status' => 'in_service', 'traffic_type' => 'unknown',
            'geom' => DB::raw(sprintf("ST_GeomFromText('LINESTRING(%F %F, %F %F)', 4326)", $lon, $lat1, $lon, $lat2)),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('sncf_line_types')->insert(['code_ligne' => $code, 'type_ligne' => $type]);
        $this->exploited($code);

        return $line;
    }

    private function exploited(string $code): void
    {
        DB::table('sncf_line_geometries')->insert($this->sncfGeometry($code, 'Exploitée', 46.0));
    }

    /** @return array<string, mixed> */
    private function sncfGeometry(string $code, string $libelle, float $lat): array
    {
        return ['code_ligne' => $code, 'libelle' => $libelle, 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw(sprintf("ST_GeomFromText('LINESTRING(5.0 %F, 5.1 %F)', 4326)", $lat, $lat))];
    }

    private function gare(string $code, bool $voyageurs, bool $fret): void
    {
        static $n = 0;
        $n++;
        DB::table('sncf_gares')->insert(['code_uic' => sprintf('87%06d', $n), 'code_ligne' => $code, 'voyageurs' => $voyageurs, 'fret' => $fret, 'libelle' => "Gare {$n}"]);
    }

    /** @return list<string> trafics distincts des tronçons en service de la ligne */
    private function traffic(Line $line): array
    {
        return DB::table('line_segments')->where('line_id', $line->id)->where('era_id', $this->current->id)
            ->where('status', 'in_service')->distinct()->orderBy('traffic_type')->pluck('traffic_type')->all();
    }

    private function assertTraffic(Line $line, array $expected, string $message = ''): void
    {
        $this->assertSame($expected, $this->traffic($line), $message ?: $line->name);
    }

    /** @return array<string, mixed> champs de la source 'sncf-classification' de la ligne */
    private function sourceFields(Line $line): array
    {
        return json_decode((string) DB::table('line_sources')->where('line_id', $line->id)->where('source_type', 'sncf-classification')->value('fields_provided'), true) ?? [];
    }

    private function assertSource(Line $line, string $confidence, string $signal): void
    {
        $source = DB::table('line_sources')->where('line_id', $line->id)->where('source_type', 'sncf-classification')->first();
        $this->assertNotNull($source, $line->name);
        $this->assertSame($confidence, $source->confidence, $line->name);
        $this->assertSame($signal, json_decode($source->fields_provided, true)['signal'], $line->name);
    }
}
