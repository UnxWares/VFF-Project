<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * vff:build-era-segments de bout en bout (tracé de référence + événements datés
 * par portion → tronçons de chaque ère), sur les règles métier de la carte :
 *   - ouverte en totalité en 1970, fermée en partie en 1980 : en 1980, seule la
 *     partie non fermée est active ;
 *   - ère courante = état au 31/12 de min(2030, année actuelle) ;
 *   - LGV : voyageurs seuls, rien avant 1981 ;
 *   - services par défaut selon la catégorie de réseau ;
 *   - voie déposée visible dans la seule décennie qui suit la dépose ;
 *   - voie verte À CÔTÉ de rails restés en place : pas de dépose ;
 *   - patrimoine : train touristique en service, vélorail fermé ;
 *   - pas de tronçon de longueur nulle aux raccords en T (nœuds au millimètre).
 *
 * Chaque ligne : tracé est-ouest de 0,2° (≈ 15 km) à sa propre latitude.
 */
class BuildEraSegmentsTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, Era> */
    private array $eras = [];

    private float $lat = 46.0;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([1960, 1970, 1980, 1990, 2000, 2010, 2020, 2030] as $year) {
            $this->eras[$year] = $year === 2030 ? Era::factory()->current()->create(['year' => $year]) : Era::factory()->create(['year' => $year]);
        }
    }

    public function test_line_opened_in_1970_and_partly_closed_in_1980(): void
    {
        $line = $this->line('Ligne de Alpha à Bravo');
        $this->event($line, 'opening', '1970-05-01');
        $this->event($line, 'closure', '1980-09-28', section: [2.10, 2.20]);

        $this->build();

        $this->assertSame([], $this->km($line, 1960));
        $this->assertEqualsWithDelta(15.2, $this->km($line, 1970)['in_service/mixed'], 0.3);
        $this->assertSame(['abandoned/unknown', 'in_service/mixed'], array_keys($this->km($line, 1980)));
        $this->assertEqualsWithDelta(7.6, $this->km($line, 1980)['in_service/mixed'], 0.3, 'partie restée ouverte');
        $this->assertEqualsWithDelta(7.6, $this->km($line, 1980)['abandoned/unknown'], 0.3, 'partie fermée en 1980');
        $this->assertSame(['abandoned/unknown', 'in_service/mixed'], array_keys($this->km($line, 2030)));
    }

    public function test_current_era_shows_the_state_of_today(): void
    {
        $this->travelTo('2026-10-19 12:00:00');
        $closedLaterThisYear = $this->line('Ligne de Charlie à Delta');
        $this->event($closedLaterThisYear, 'opening', '1900-01-01');
        $this->event($closedLaterThisYear, 'closure', '2026-12-01');
        $closedEarlierThisYear = $this->line('Ligne de Echo à Foxtrot');
        $this->event($closedEarlierThisYear, 'opening', '1900-01-01');
        $this->event($closedEarlierThisYear, 'closure', '2026-01-15');
        $closedToday = $this->line('Ligne de Golf à Hotel');
        $this->event($closedToday, 'opening', '1900-01-01');
        $this->event($closedToday, 'closure', '2026-10-19');

        $this->build();

        // « 2030 » = aujourd'hui (19/10/2026) : la fermeture de décembre n'y est pas encore.
        $this->assertSame(['in_service/mixed'], array_keys($this->km($closedLaterThisYear, 2030)));
        $this->assertSame(['abandoned/unknown'], array_keys($this->km($closedEarlierThisYear, 2030)));
        $this->assertSame(['abandoned/unknown'], array_keys($this->km($closedToday, 2030)), 'fermée ce jour : fermée');
        $this->assertSame(['in_service/mixed'], array_keys($this->km($closedEarlierThisYear, 2020)));

        // Le lendemain de la fermeture de décembre, la carte courante la montre.
        $this->travelTo('2026-12-02 12:00:00');
        $this->build();
        $this->assertSame(['abandoned/unknown'], array_keys($this->km($closedLaterThisYear, 2030)));
    }

    public function test_high_speed_line_is_passenger_only_and_absent_before_1981(): void
    {
        $lgv = $this->line('LGV Sud-Est');
        // Date d'une ligne classique voisine (way OHM sous la tolérance) : sans objet pour une LGV.
        $this->event($lgv, 'opening', '1866-01-01', source: 'ohm');
        $this->event($lgv, 'opening', '1981-09-27');

        $this->build();

        foreach ([1960, 1970, 1980] as $year) {
            $this->assertSame([], $this->km($lgv, $year), "ère {$year}");
        }
        foreach ([1990, 2030] as $year) {
            $this->assertSame(['in_service/passenger'], array_keys($this->km($lgv, $year)), "ère {$year}");
        }
    }

    public function test_default_services_follow_the_network_category(): void
    {
        $lines = [];
        foreach (['principal' => 'mixed', 'secondaire' => 'mixed', 'particulier' => 'freight', 'militaire' => 'unknown'] as $network => $traffic) {
            $lines[$network] = [$this->line("Ligne {$network}", ['network' => $network]), $traffic];
            $this->event($lines[$network][0], 'opening', '1955-01-01');
        }

        $this->build();

        foreach ($lines as $network => [$line, $traffic]) {
            $this->assertSame(["in_service/{$traffic}"], array_keys($this->km($line, 1970)), $network);
        }
    }

    public function test_dated_removal_shows_only_in_the_following_decade(): void
    {
        $line = $this->line('Ligne de Golf à Hotel');
        $this->event($line, 'opening', '1900-01-01');
        $this->event($line, 'closure', '1965-01-01');
        $this->event($line, 'dismantling', '1985-01-01');

        $this->build();

        $this->assertSame(['in_service/mixed'], array_keys($this->km($line, 1960)));
        $this->assertSame(['abandoned/unknown'], array_keys($this->km($line, 1970)));
        $this->assertSame(['abandoned/unknown'], array_keys($this->km($line, 1980)));
        $this->assertSame(['dismantled/unknown'], array_keys($this->km($line, 1990)));
        foreach ([2000, 2010, 2020, 2030] as $year) {
            $this->assertSame([], $this->km($line, $year), "ère {$year}");
        }
    }

    public function test_greenway_beside_a_track_left_in_place_does_not_remove_it(): void
    {
        $beside = $this->line('Ligne de India à Juliett');
        $this->event($beside, 'opening', '1900-01-01');
        $this->event($beside, 'closure', '1991-01-01');
        $this->present($beside, 'greenway', 'osm');
        $this->present($beside, 'track_in_place', 'bdtopo', offsetDeg: 0.00005); // rails à ~5 m

        $converted = $this->line('Ligne de Kilo à Lima');
        $this->event($converted, 'opening', '1900-01-01');
        $this->event($converted, 'closure', '1991-01-01');
        $this->present($converted, 'greenway', 'osm');

        $this->build();

        // Rails observés : la voie reste fermée, jamais « déposée ».
        foreach ([2000, 2010, 2020, 2030] as $year) {
            $this->assertSame(['abandoned/unknown'], array_keys($this->km($beside, $year)), "voie verte à côté, ère {$year}");
        }
        // Voie verte sur l'emprise, sans rails : déposée juste après la fermeture, puis absente.
        $this->assertSame(['abandoned/unknown'], array_keys($this->km($converted, 2000)));
        $this->assertSame(['dismantled/unknown'], array_keys($this->km($converted, 2010)));
        $this->assertSame('greenway', DB::table('line_segments')->where('line_id', $converted->id)->where('era_id', $this->eras[2010]->id)->value('reuse'));
        $this->assertSame([], $this->km($converted, 2020));
    }

    public function test_heritage_operations_on_two_portions(): void
    {
        $line = $this->line('Ligne de Mike à November');
        $this->event($line, 'opening', '1900-01-01');
        $this->event($line, 'closure', '1960-01-01');
        $this->event($line, 'heritage_start', '1985-06-01', section: [2.00, 2.10], heritage: 'tourist_train');
        $this->event($line, 'heritage_start', '1995-06-01', section: [2.10, 2.20], heritage: 'velorail');

        $this->build();

        $rows = fn (int $year) => DB::table('line_segments')->where('line_id', $line->id)->where('era_id', $this->eras[$year]->id)
            ->orderBy('status')->get(['status', 'is_heritage', 'heritage_kind'])->map(fn ($r) => [$r->status, (bool) $r->is_heritage, $r->heritage_kind])->unique(fn ($r) => json_encode($r))->values()->all();

        $this->assertSame([['abandoned', false, null]], $rows(1980));
        $this->assertSame([['abandoned', false, null], ['in_service', true, 'tourist_train']], $rows(1990));
        $this->assertSame([['abandoned', true, 'velorail'], ['in_service', true, 'tourist_train']], $rows(2000));
    }

    public function test_manual_whole_line_heritage_date_beats_an_attested_observed_portion(): void
    {
        // Ligne hors RFN (vff:seed-off-network-lines) : exploitation associative datée « toute la
        // ligne » (manual, 1971) ; vff:import-heritage-lines n'observe qu'une portion, « attestée » en 2026.
        $line = $this->line('Ligne de Noyelles à Oscar', ['network' => 'particulier']);
        $this->event($line, 'opening', '1887-07-01', source: 'manual');
        $this->event($line, 'closure', '1969-12-31', source: 'manual');
        $this->event($line, 'heritage_start', '1971-07-04', source: 'manual', heritage: 'tourist_train');
        $this->event($line, 'heritage_start', '2026-01-01', section: [2.00, 2.10], source: 'heritage', heritage: 'tourist_train');

        $this->build();

        $heritageKm = fn (int $year) => round((float) DB::table('line_segments')->where('line_id', $line->id)
            ->where('era_id', $this->eras[$year]->id)->where('is_heritage', true)->sum('length_meters') / 1000, 1);
        $this->assertSame(['abandoned/unknown'], array_keys($this->km($line, 1970)));
        $this->assertEqualsWithDelta(15.5, $heritageKm(1980), 0.2, 'toute la ligne exploitée par l\'association dès 1980');
        $this->assertEqualsWithDelta(15.5, $heritageKm(2020), 0.2);
    }

    public function test_same_year_whole_openings_do_not_cap_an_inferred_later_opening(): void
    {
        // 752000 : « 1981 » (Wikidata, année) et 27/09/1981 (Wikipédia) pour toute la ligne ; une
        // portion non datée voisine d'une section ouverte en 2001 n'est pas ramenée à 1981.
        $line = $this->line('Ligne de Papa à Quebec (LGV)', ['is_lgv' => true]);
        $this->event($line, 'opening', '1981-01-01', source: 'wikidata');
        $this->event($line, 'opening', '1981-09-27');
        $this->event($line, 'opening', '1981-09-27', section: [2.00, 2.05], source: 'ohm');
        $this->event($line, 'opening', '2001-06-10', section: [2.05, 2.10], source: 'ohm');

        $this->build();

        $km1990 = array_sum($this->km($line, 1990));
        $this->assertEqualsWithDelta(3.8, $km1990, 0.4, 'seule la section de 1981 en 1990');
        $this->assertEqualsWithDelta(15.5, array_sum($this->km($line, 2010)), 0.4);
    }

    public function test_events_of_unresolved_portions_are_ignored(): void
    {
        $line = $this->line('Ligne de Oscar à Papa');
        $this->event($line, 'opening', '1900-01-01');
        // Fermeture citée « de Oscar à Quebec » mais non localisée : ne ferme rien.
        DB::table('line_events')->insert(['line_id' => $line->id, 'type' => 'closure_passenger', 'event_date' => '1950-01-01',
            'description' => 'Fermeture aux voyageurs de Oscar à Quebec', 'section_from' => 'Oscar', 'section_to' => 'Quebec',
            'section_resolution' => 'unresolved', 'source' => 'wikipedia', 'created_at' => now(), 'updated_at' => now()]);

        $this->build();

        $this->assertSame(['in_service/mixed'], array_keys($this->km($line, 1960)));
    }

    public function test_rebuild_is_idempotent(): void
    {
        $line = $this->line('Ligne de Romeo à Sierra');
        $this->event($line, 'opening', '1970-05-01');
        $this->event($line, 'closure', '1980-09-28', section: [2.10, 2.20]);

        $this->build();
        $first = DB::table('line_segments')->count();
        $this->build();

        $this->assertSame($first, DB::table('line_segments')->count());
    }

    public function test_single_line_run_reclassifies_the_current_era_with_sncf_data(): void
    {
        $line = $this->line('Ligne de Mike à November', ['code' => '999000']);
        $this->event($line, 'opening', '1970-05-01');
        $this->event($line, 'closure_passenger', '1975-06-01');
        // Une gare voyageurs à chaque bout : toute la ligne est desservie (classement par portion ou par ligne).
        foreach ([['87999000', 'Gare de Mike', 0, 2.00], ['87999001', 'Gare de November', 15200, 2.20]] as [$uic, $name, $pk, $lon]) {
            DB::table('sncf_gares')->insert(['code_uic' => $uic, 'code_ligne' => '999000', 'fret' => false, 'voyageurs' => true,
                'libelle' => $name, 'rg_troncon' => 1, 'pk_m' => $pk, 'lat' => $this->lat, 'lon' => $lon]);
        }

        DB::statement('DROP TABLE IF EXISTS tmp_chunks');
        $this->artisan('vff:build-era-segments', ['--line' => (string) $line->id])->assertSuccessful();

        $this->assertSame(['in_service/freight'], array_keys($this->km($line, 2020)), 'ère passée : trafic du moteur');
        $this->assertSame(['in_service/mixed'], array_keys($this->km($line, 2030)), 'ère courante : gare voyageurs SNCF, donc mixte');
    }

    public function test_single_line_run_replays_the_electrification_and_the_speeds_unless_no_enrich(): void
    {
        $line = $this->line('Ligne de Oscar à Papa', ['code' => '999100']);
        $this->event($line, 'opening', '1970-05-01');
        $enriched = fn () => DB::table('line_segments')->where('line_id', $line->id)->whereNull('electrification_source')->count();

        // --no-enrich : tronçons du moteur seuls, jamais enrichis.
        $this->artisan('vff:build-era-segments', ['--line' => (string) $line->id, '--no-classify' => true, '--no-enrich' => true])
            ->doesntExpectOutputToContain('Pas de cache local des vitesses SNCF')->assertSuccessful();
        $this->assertGreaterThan(0, $enriched(), 'moteur seul : électrification à rejouer');

        // Relance par ligne : électrification (vff:enrich-era-segments) et vitesses (cache seul, sans réseau).
        Http::preventStrayRequests();
        $this->artisan('vff:build-era-segments', ['--line' => (string) $line->id, '--no-classify' => true])
            ->expectsOutputToContain('Pas de cache local des vitesses SNCF')->assertSuccessful();
        $this->assertSame(0, $enriched(), 'chaque tronçon régénéré est enrichi');
        $this->assertGreaterThan(0, DB::table('line_segments')->where('line_id', $line->id)->count());
    }

    public function test_a_connector_end_a_hair_beyond_the_track_leaves_no_zero_length_segment(): void
    {
        $line = $this->line('Ligne de Oscar à Papa');
        $this->event($line, 'opening', '1970-05-01');
        // Raccord en T (vff:build-line-references) : extrémité au point le plus proche de la voie,
        // arrondie 1e-7 m au-delà (allers-retours Lambert-93 / WGS84) ; sans sommet de la voie à cet endroit.
        DB::insert(<<<'SQL'
            INSERT INTO line_reference_parts (line_id, source, source_trace_ids, matched_source, length_m, geom, created_at, updated_at)
            SELECT ?, 'osm', '[]', 'connector', 10, ST_Multi(ST_Transform(ST_MakeLine(p, ST_Translate(ST_ClosestPoint(m, p), 0, -1e-7)), 4326)), NOW(), NOW()
            FROM (SELECT ST_Transform(geom, 2154) AS m FROM line_reference_parts WHERE line_id = ?) r,
                 LATERAL (SELECT ST_Translate(ST_LineInterpolatePoint(ST_GeometryN(r.m, 1), 0.3), 0, 10) AS p) q
            SQL, [$line->id, $line->id]);

        $this->build();

        $lengths = DB::table('line_segments')->where('line_id', $line->id)->where('era_id', $this->eras[1970]->id)
            ->orderByRaw('ST_Length(geom::geography)')->pluck(DB::raw('ST_Length(geom::geography) AS m'))->map(fn ($m) => (float) $m)->all();
        $this->assertGreaterThan(5, $lengths[0], 'aucun tronçon de longueur nulle');
        $this->assertCount(3, $lengths, 'voie coupée au raccord en T, et le raccord');
        $reference = (float) DB::selectOne('SELECT SUM(ST_Length(geom::geography)) AS m FROM line_reference_parts WHERE line_id = ?', [$line->id])->m;
        $this->assertEqualsWithDelta($reference, array_sum($lengths), 1);
    }

    // ─── Trafic des ères passées : fermetures aux voyageurs ──────────────
    //
    // Une fermeture aux voyageurs (toute la ligne ou section) n'est pas masquée par une
    // fermeture totale ou au fret d'une autre source plus tardive : la portion est « fret »
    // entre les deux (EraTimeline::serviceClosuresNotCovered). Cas réels 643000, 413000.

    public function test_643000_whole_line_passenger_closure_under_local_closures_of_other_sources(): void
    {
        $line = $this->line('Ligne de Port-Sainte-Marie à Riscle');
        $this->event($line, 'opening', '1880-01-01', source: 'wikidata');
        $this->event($line, 'opening', '1880-06-06', section: [2.00, 2.20]);
        $this->event($line, 'closure_passenger', '1940-06-27');
        $this->event($line, 'closure_freight', '2007-01-01', section: [2.10, 2.20]);
        $this->event($line, 'closure', '2022-07-13', section: [2.00, 2.10], source: 'lignes-oubliees');

        $this->build();

        foreach ([1960, 1970, 1980, 1990, 2000] as $year) {
            $this->assertSame(['in_service/freight'], array_keys($this->km($line, $year)), "ère {$year} : fermée aux voyageurs en 1940");
        }
        foreach ([2010, 2020] as $year) {
            $km = $this->km($line, $year);
            $this->assertSame(['abandoned/unknown', 'in_service/freight'], array_keys($km), "ère {$year}");
            $this->assertEqualsWithDelta(7.6, $km['abandoned/unknown'], 0.3, 'Eauze - Riscle fermée au fret en 2007');
        }
        $this->assertSame(['abandoned/unknown'], array_keys($this->km($line, 2030)));
    }

    public function test_413000_passenger_closure_known_by_year_with_low_confidence_and_closures_by_section(): void
    {
        $line = $this->line('Ligne de Caen à Vire');
        $this->event($line, 'opening', '1886-08-22', section: [2.00, 2.20]);
        DB::table('line_events')->insert(['line_id' => $line->id, 'type' => 'closure_passenger', 'event_date' => '1938-01-01',
            'description' => 'Fermeture aux voyageurs', 'source' => 'wikipedia', 'confidence' => 'low', 'date_precision' => 'year',
            'section_resolution' => 'whole_line', 'created_at' => now(), 'updated_at' => now()]);
        $this->event($line, 'closure', '1954-11-12', section: [2.10, 2.20], source: 'ohm');
        $this->event($line, 'closure', '1972-04-04', section: [2.00, 2.10]);
        $this->event($line, 'declassification', '1994-07-11', section: [2.00, 2.10], source: 'lignes-oubliees');

        $this->build();

        $km1960 = $this->km($line, 1960);
        $this->assertSame(['abandoned/unknown', 'in_service/freight'], array_keys($km1960), 'fermée aux voyageurs en 1938, une section fermée en 1954');
        $this->assertSame(['abandoned/unknown'], array_keys($this->km($line, 1980)), 'plus aucune section en service après 1972');
    }

    public function test_passenger_closure_of_one_section_leaves_the_other_section_mixed(): void
    {
        $line = $this->line('Ligne de Tango à Uniform');
        $this->event($line, 'opening', '1900-01-01');
        $this->event($line, 'closure_passenger', '1938-10-02', section: [2.00, 2.10]);
        $this->event($line, 'closure', '1975-01-01', section: [2.10, 2.20], source: 'lignes-oubliees');

        $this->build();

        $km = $this->km($line, 1960);
        $this->assertSame(['in_service/freight', 'in_service/mixed'], array_keys($km));
        $this->assertEqualsWithDelta(7.6, $km['in_service/freight'], 0.3, 'section fermée aux voyageurs');
        $this->assertEqualsWithDelta(7.6, $km['in_service/mixed'], 0.3, 'autre section : voyageurs jusqu\'à sa fermeture totale');
        $this->assertSame(['abandoned/unknown', 'in_service/freight'], array_keys($this->km($line, 1980)));
    }

    public function test_passenger_reopening_of_one_section_after_a_whole_line_closure(): void
    {
        $line = $this->line('Ligne de Victor à Whiskey');
        $this->event($line, 'opening', '1900-01-01');
        $this->event($line, 'closure_passenger', '1938-10-02');
        $this->event($line, 'closure', '1995-01-01', section: [2.10, 2.20], source: 'lignes-oubliees');
        $this->event($line, 'reopening_passenger', '1985-06-01', section: [2.00, 2.10]);

        $this->build();

        $this->assertSame(['in_service/freight'], array_keys($this->km($line, 1980)));
        $km = $this->km($line, 1990);
        $this->assertSame(['in_service/freight', 'in_service/mixed'], array_keys($km), 'section rouverte aux voyageurs : mixte');
        $this->assertSame(['abandoned/unknown', 'in_service/mixed'], array_keys($this->km($line, 2000)));
    }

    public function test_conflicting_passenger_closures_follow_the_most_reliable_source(): void
    {
        $line = $this->line('Ligne de Xray à Yankee');
        $this->event($line, 'opening', '1900-01-01', source: 'lignes-oubliees');
        $this->event($line, 'closure_passenger', '1969-01-01');
        $this->event($line, 'closure_passenger', '1972-03-06', source: 'lignes-oubliees');
        $this->event($line, 'closure', '1995-01-01', section: [2.00, 2.20], source: 'ohm');

        $this->build();

        $this->assertSame(['in_service/mixed'], array_keys($this->km($line, 1970)), 'lignes-oubliees (1972) prime sur Wikipédia (1969)');
        $this->assertSame(['in_service/freight'], array_keys($this->km($line, 1980)));
    }

    public function test_reduced_seasonal_passenger_service_stays_mixed(): void
    {
        // Desserte d'été seulement (379000) : « other » (Réduction de la desserte voyageurs).
        $line = $this->line('Ligne de Zulu à Alpha');
        $this->event($line, 'opening', '1900-01-01');
        $this->event($line, 'other', '1970-01-01');
        $this->event($line, 'closure_freight', '1990-01-01', section: [2.00, 2.20], source: 'lignes-oubliees');

        $this->build();

        foreach ([1980, 2000, 2020] as $year) {
            $this->assertSame(['in_service/mixed'], array_keys($this->km($line, $year)), "ère {$year}");
        }
    }

    public function test_high_speed_line_never_turns_freight_whatever_closures_say(): void
    {
        $lgv = $this->line('LGV Atlantique', ['is_lgv' => true]);
        $this->event($lgv, 'opening', '1989-09-24');
        $this->event($lgv, 'closure_freight', '2000-01-01', section: [2.00, 2.20], source: 'lignes-oubliees');

        $this->build();

        foreach ([1990, 2000, 2010, 2020] as $year) {
            $this->assertSame(['in_service/passenger'], array_keys($this->km($lgv, $year)), "ère {$year}");
        }
    }

    public function test_line_exploited_today_keeps_freight_after_its_passenger_closure(): void
    {
        // Exploitée aujourd'hui (SNCF) : la fermeture au fret de 2007 est contredite ; la
        // fermeture aux voyageurs de 1940 (toute la ligne) reste : fret, pas mixte.
        $line = $this->line('Ligne de Bravo à Charlie', ['code' => '998000']);
        $this->event($line, 'opening', '1880-06-06', section: [2.00, 2.20]);
        $this->event($line, 'closure_passenger', '1940-06-27');
        $this->event($line, 'closure_freight', '2007-01-01', section: [2.00, 2.20]);
        DB::table('sncf_line_geometries')->insert(['code_ligne' => '998000', 'libelle' => 'Exploitée', 'pk_debut_m' => 0, 'pk_fin_m' => 15200,
            'geom' => DB::raw(sprintf("ST_GeomFromText('LINESTRING(2.0 %F, 2.2 %F)', 4326)", $line->getAttribute('ref_lat'), $line->getAttribute('ref_lat'))),
            'created_at' => now(), 'updated_at' => now()]);

        $this->build();

        foreach ([1960, 2000, 2010, 2020] as $year) {
            $this->assertSame(['in_service/freight'], array_keys($this->km($line, $year)), "ère {$year}");
        }
    }

    public function test_several_lines_can_be_rebuilt_in_one_run(): void
    {
        $a = $this->line('Ligne de Delta à Echo', ['code' => '997000']);
        $b = $this->line('Ligne de Foxtrot à Golf');
        $other = $this->line('Ligne de Hotel à India');
        foreach ([$a, $b, $other] as $line) {
            $this->event($line, 'opening', '1900-01-01');
            $this->event($line, 'closure_passenger', '1938-01-01');
            $this->event($line, 'closure', '1990-01-01', section: [2.00, 2.20], source: 'lignes-oubliees');
        }

        DB::statement('DROP TABLE IF EXISTS tmp_chunks');
        $this->artisan('vff:build-era-segments', ['--line' => "997000, {$b->id}", '--no-classify' => true, '--no-enrich' => true])->assertSuccessful();

        $this->assertSame(['in_service/freight'], array_keys($this->km($a, 1960)), 'par son code');
        $this->assertSame(['in_service/freight'], array_keys($this->km($b, 1960)), 'par son id');
        $this->assertSame([], $this->km($other, 1960), 'ligne non demandée : intacte');
    }

    // ─── Fabrique ────────────────────────────────────────────────────────

    /**
     * Une ligne par appel : la table temporaire du moteur (ON COMMIT DROP) n'est pas
     * supprimée dans la transaction englobante de RefreshDatabase, on la supprime entre deux.
     */
    private function build(): void
    {
        foreach (DB::table('lines')->orderBy('id')->pluck('id') as $id) {
            DB::statement('DROP TABLE IF EXISTS tmp_chunks');
            $this->artisan('vff:build-era-segments', ['--line' => (string) $id, '--no-classify' => true, '--no-enrich' => true])->assertSuccessful();
        }
    }

    /** @return array<string, float> « statut/trafic » → km de la ligne à l'ère, triés par clé */
    private function km(Line $line, int $year): array
    {
        $rows = DB::table('line_segments')->where('line_id', $line->id)->where('era_id', $this->eras[$year]->id)
            ->selectRaw("status || '/' || traffic_type AS k, SUM(ST_Length(geom::geography)) / 1000.0 AS km")->groupBy('k')->orderBy('k')->get();

        return $rows->mapWithKeys(fn ($r) => [$r->k => round((float) $r->km, 2)])->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return Line&object{lat: float} ligne portant la latitude de son axe (attribut de test)
     */
    private function line(string $name, array $attributes = []): Line
    {
        $this->lat += 0.05;
        /** @var Line&object{lat: float} $line */
        $line = Line::factory()->create($attributes + ['name' => $name, 'code' => null, 'network' => 'principal', 'is_lgv' => false,
            'gauge_mm_default' => 1435]);
        $line->setAttribute('lat', $this->lat);
        $geom = $this->multi(2.00, 2.20, $this->lat);
        $now = now();
        DB::table('line_reference_geometries')->insert(['line_id' => $line->id, 'length_m' => 15200, 'sources' => '[]',
            'geom' => DB::raw($geom), 'created_at' => $now, 'updated_at' => $now]);
        DB::table('line_reference_parts')->insert(['line_id' => $line->id, 'source' => 'osm', 'source_trace_ids' => '[]', 'length_m' => 15200,
            'geom' => DB::raw($geom), 'created_at' => $now, 'updated_at' => $now]);
        unset($line->lat);
        $line->setAttribute('ref_lat', $this->lat);

        return $line;
    }

    /** @param  array{0: float, 1: float}|null  $section  longitudes de la portion (toute la ligne si null) */
    private function event(Line $line, string $type, string $date, ?array $section = null, string $source = 'wikipedia', ?string $heritage = null): void
    {
        $lat = $line->getAttribute('ref_lat');
        DB::table('line_events')->insert([
            'line_id' => $line->id, 'type' => $type, 'event_date' => $date, 'description' => "{$type} {$date}", 'source' => $source,
            'heritage_kind' => $heritage,
            'section_resolution' => $section ? 'pk' : null,
            'section_pk_start_m' => $section ? (int) (($section[0] - 2.00) * 76000) : null,
            'section_pk_end_m' => $section ? (int) (($section[1] - 2.00) * 76000) : null,
            'section_geom' => $section ? DB::raw($this->multi($section[0], $section[1], $lat)) : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function present(Line $line, string $state, string $source, float $offsetDeg = 0.0): void
    {
        DB::table('present_states')->insert(['source' => $source, 'source_ref' => "{$source}-{$line->id}-{$state}", 'state' => $state,
            'geom' => DB::raw($this->multi(2.00, 2.20, $line->getAttribute('ref_lat') + $offsetDeg)), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function multi(float $lon1, float $lon2, float $lat): string
    {
        return sprintf("ST_GeomFromText('MULTILINESTRING((%F %F, %F %F, %F %F))', 4326)", $lon1, $lat, ($lon1 + $lon2) / 2, $lat, $lon2, $lat);
    }
}
