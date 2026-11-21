<?php

namespace Tests\Unit;

use App\Console\Commands\ImportStationSources;
use App\Services\StationLineCoverage;
use App\Services\StationTimeline;
use PHPUnit\Framework\TestCase;

/**
 * Règles issues de la relecture des gares douteuses (2026-10-20, rapport gares-relecture.md).
 */
class StationRelectureRulesTest extends TestCase
{
    /** @return array<string, mixed> */
    private function ev(string $type, string $date, string $source, string $precision = 'year', bool $inferred = false): array
    {
        return ['type' => $type, 'date' => $date, 'source' => $source, 'precision' => $precision, 'inferred' => $inferred];
    }

    // ── Fret seul : « fermeture » Wikidata = fin des voyageurs ──────────────────

    public function test_mass_closure_of_a_freight_station_is_the_end_of_passenger_service(): void
    {
        // Aniche : Wikidata P3999 1992, Wikipédia « fermée au service des voyageurs en 1992 », fret encore aujourd'hui.
        $events = [$this->ev('opening', '1860-01-01', 'wikidata'), $this->ev('closure', '1992-01-01', 'wikidata'),
            $this->ev('closure_passenger', '1992-01-01', 'wikipedia')];
        $out = StationTimeline::massClosureAsPassenger($events, true);
        $this->assertSame(['opening', 'closure_passenger', 'closure_passenger'], array_column($out, 'type'));
        // Une fois dédoublonnée : fret seul après 1992, jamais « fermée » puis « fret seul ».
        $deduped = StationTimeline::dedupeClosures($out);
        $this->assertSame(StationTimeline::FREIGHT, StationTimeline::statusFromEvents($deduped, 2000, true));
        $this->assertSame(StationTimeline::ACTIVE, StationTimeline::statusFromEvents($deduped, 1990, true));
    }

    public function test_mass_closure_rule_needs_freight_today_a_better_source_and_the_same_year(): void
    {
        $events = [$this->ev('closure', '1992-01-01', 'wikidata'), $this->ev('closure_passenger', '1992-01-01', 'wikipedia')];
        // Gare fermée aujourd'hui : la fermeture Wikidata reste une fermeture totale.
        $this->assertSame('closure', StationTimeline::massClosureAsPassenger($events, false)[0]['type']);
        // Plus d'un an d'écart : deux faits distincts.
        $far = [$this->ev('closure', '1995-01-01', 'wikidata'), $this->ev('closure_passenger', '1992-01-01', 'wikipedia')];
        $this->assertSame('closure', StationTimeline::massClosureAsPassenger($far, true)[0]['type']);
        // Fermeture voyageurs d'une source en masse seulement (pas plus sûre) : pas de règle.
        $same = [$this->ev('closure', '1992-01-01', 'wikidata'), $this->ev('closure_passenger', '1992-01-01', 'osm')];
        $this->assertSame('closure', StationTimeline::massClosureAsPassenger($same, true)[0]['type']);
        // Fermeture totale d'une source plus sûre (Paris-Tolbiac, détruite en 2001) : reste totale.
        $total = [$this->ev('closure', '2001-01-01', 'wikidata'), $this->ev('closure_passenger', '2001-01-01', 'wikipedia'),
            $this->ev('closure', '2001-06-01', StationTimeline::CORRECTION, 'month')];
        $this->assertSame('closure', StationTimeline::massClosureAsPassenger($total, true)[0]['type']);
        // Événement déduit : jamais concerné.
        $inferred = [$this->ev('closure', '1992-01-01', 'wikidata', 'year', true), $this->ev('closure_passenger', '1992-01-01', 'wikipedia')];
        $this->assertSame('closure', StationTimeline::massClosureAsPassenger($inferred, true)[0]['type']);
    }

    // ── Gare fermée ou disparue : fermeture en masse = fin des voyageurs si le fret a continué ──

    public function test_mass_closure_of_a_closed_station_is_the_passenger_end_when_freight_ended_later(): void
    {
        // Livarot : infobox « 5 mai 1938 (voyageurs), 30 septembre 1990 (marchandises) », Wikidata « fermée en 1938 ».
        $events = [$this->ev('opening', '1873-01-01', 'wikidata'), $this->ev('closure', '1938-01-01', 'wikidata'),
            $this->ev('closure_passenger', '1938-05-05', 'wikipedia', 'day'), $this->ev('closure_freight', '1990-09-30', 'wikipedia', 'day')];
        $out = StationTimeline::massClosureAsPassenger($events, false);
        $this->assertSame(['opening', 'closure_passenger', 'closure_passenger', 'closure_freight'], array_column($out, 'type'));
        $timeline = StationTimeline::forTimeline(StationTimeline::dedupeClosures($out));
        foreach ([true, false] as $existsToday) {
            $this->assertSame(StationTimeline::ACTIVE, StationTimeline::statusFromEvents($timeline, 1930, $existsToday));
            $this->assertSame(StationTimeline::FREIGHT, StationTimeline::statusFromEvents($timeline, 1940, $existsToday));
            $this->assertSame(StationTimeline::FREIGHT, StationTimeline::statusFromEvents($timeline, 1980, $existsToday));
        }
        // Après la fin du fret : fermée si la gare est encore debout, absente de la carte si elle a disparu.
        $this->assertSame(StationTimeline::CLOSED, StationTimeline::statusFromEvents($timeline, 2000, true));
        $this->assertNull(StationTimeline::statusFromEvents($timeline, 2000, false));
        // Sans la règle, la fermeture Wikidata fermait tout en 1938.
        $this->assertNull(StationTimeline::statusFromEvents(StationTimeline::forTimeline(StationTimeline::dedupeClosures($events)), 1940, false));
    }

    public function test_freight_later_rule_covers_ohm_ends_wikidata_demolitions_and_a_better_total_closure(): void
    {
        // Pouilly-sous-Charlieu : fin de l'objet OHM le 20/05/1940, fret jusqu'en mai 2005.
        $ohm = [$this->ev('closure', '1940-05-20', 'ohm', 'day'), $this->ev('closure_passenger', '1940-05-20', 'wikipedia', 'day'),
            $this->ev('closure_freight', '2005-05-01', 'wikipedia', 'month')];
        $this->assertSame('closure_passenger', StationTimeline::massClosureAsPassenger($ohm, false)[0]['type']);
        // Le Lion-d'Or : « démolition » Wikidata (P576) à la date de la fin des voyageurs.
        $p576 = [$this->ev('demolition', '1938-03-01', 'wikidata', 'day'), $this->ev('closure_passenger', '1938-03-01', 'wikipedia', 'day'),
            $this->ev('closure_freight', '1969-11-03', 'wikipedia', 'day')];
        $this->assertSame('closure_passenger', StationTimeline::massClosureAsPassenger($p576, false)[0]['type']);
        // Fermeture totale plus tardive d'une source plus sûre (correction) : vaut aussi fin du fret.
        $total = [$this->ev('closure', '1970-01-01', 'wikidata'), $this->ev('closure_passenger', '1970-01-01', 'wikipedia'),
            $this->ev('closure', '1988-01-24', StationTimeline::CORRECTION, 'day')];
        $this->assertSame('closure_passenger', StationTimeline::massClosureAsPassenger($total, false)[0]['type']);
    }

    public function test_freight_later_rule_needs_a_better_passenger_end_of_the_same_year_and_a_later_better_freight_end(): void
    {
        $retyped = fn (array $events) => StationTimeline::massClosureAsPassenger($events, false)[0]['type'];
        // Plombières : la fermeture Wikidata (01/10/1978) est la fin du fret elle-même, la même année : gardée.
        $this->assertSame('closure', $retyped([$this->ev('closure', '1978-10-01', 'wikidata', 'day'),
            $this->ev('closure_passenger', '1977-01-01', 'wikipedia'), $this->ev('closure_freight', '1978-10-01', 'wikipedia', 'day')]));
        // Fin du fret seulement : pas de fin des voyageurs de la même année, rien à retyper.
        $this->assertSame('closure', $retyped([$this->ev('closure', '1938-01-01', 'wikidata'), $this->ev('closure_freight', '1990-01-01', 'wikipedia')]));
        // Fin des voyageurs trop éloignée (deux faits distincts).
        $this->assertSame('closure', $retyped([$this->ev('closure', '1945-01-01', 'wikidata'),
            $this->ev('closure_passenger', '1938-01-01', 'wikipedia'), $this->ev('closure_freight', '1990-01-01', 'wikipedia')]));
        // Fin du fret d'une source qui n'est pas mieux classée (lignes-oubliées, même rang qu'OHM ; Wikidata).
        $this->assertSame('closure', $retyped([$this->ev('closure', '1940-01-01', 'ohm'),
            $this->ev('closure_passenger', '1940-01-01', 'wikipedia'), $this->ev('closure_freight', '1990-01-01', 'lignes-oubliees')]));
        $this->assertSame('closure', $retyped([$this->ev('closure', '1940-01-01', 'wikidata'),
            $this->ev('closure_passenger', '1940-01-01', 'wikipedia'), $this->ev('closure_freight', '1990-01-01', 'wikidata')]));
        // Fermeture totale d'une source plus sûre la même année : la gare a bien fermé.
        $this->assertSame('closure', $retyped([$this->ev('closure', '1940-01-01', 'wikidata'), $this->ev('closure_passenger', '1940-01-01', 'wikipedia'),
            $this->ev('closure', '1940-06-01', 'wikipedia', 'month'), $this->ev('closure_freight', '1990-01-01', 'wikipedia')]));
        // Fin du fret déduite d'une ligne : ne compte pas.
        $this->assertSame('closure', $retyped([$this->ev('closure', '1940-01-01', 'wikidata'), $this->ev('closure_passenger', '1940-01-01', 'wikipedia'),
            $this->ev('closure_freight', '1990-01-01', 'inferred:line_event', 'year', true)]));
        $line = $this->ev('closure_freight', '1990-01-01', 'wikipedia') + ['line' => true];
        $this->assertSame('closure', $retyped([$this->ev('closure', '1940-01-01', 'wikidata'), $this->ev('closure_passenger', '1940-01-01', 'wikipedia'), $line]));
        // Démolition OHM ou OSM : jamais retypée (seule la P576 Wikidata est une date de masse sans portée).
        $this->assertSame('demolition', $retyped([$this->ev('demolition', '1940-01-01', 'osm'),
            $this->ev('closure_passenger', '1940-01-01', 'wikipedia'), $this->ev('closure_freight', '1990-01-01', 'wikipedia')]));
    }

    public function test_freight_end_before_the_passenger_end_closes_the_station_at_the_passenger_end(): void
    {
        // Aigues : fret fermé le 02/11/2009, voyageurs en décembre 2011.
        $events = [$this->ev('opening', '1900-01-01', 'wikidata'), $this->ev('closure_freight', '2009-11-02', 'wikipedia', 'day'),
            $this->ev('closure_passenger', '2011-12-01', 'wikipedia', 'month')];
        $this->assertSame(StationTimeline::ACTIVE, StationTimeline::statusFromEvents($events, 2010, false));
        $this->assertNull(StationTimeline::statusFromEvents($events, 2020, false));
        $this->assertSame(StationTimeline::CLOSED, StationTimeline::statusFromEvents($events, 2020, true));
        // Gare rouverte ensuite : présente à la réouverture.
        $reopened = [...$events, $this->ev('reopening', '2024-01-01', 'wikidata')];
        $this->assertSame(StationTimeline::CLOSED, StationTimeline::statusFromEvents($reopened, 2020, false));
    }

    // ── Fermeture contredite par la desserte actuelle ───────────────────────────

    public function test_mass_closure_contradicted_by_the_current_passenger_list_is_dropped(): void
    {
        // Vincelles : P3999 du 15/12/2017 (référentiel SNCF), gare dans gares-de-voyageurs relevé en 2026.
        $events = [$this->ev('opening', '1870-01-01', 'wikidata'), $this->ev('closure', '2017-12-15', 'wikidata', 'day')];
        $out = StationTimeline::dropContradictedClosures($events, '2026-10-15 17:08:34');
        $this->assertSame(['opening'], array_column($out, 'type'));
        $this->assertSame(StationTimeline::ACTIVE, StationTimeline::statusFromEvents($out, 2020, true));
        // Sans relevé de desserte : inchangé.
        $this->assertCount(2, StationTimeline::dropContradictedClosures($events, null));
        // Source sûre (Wikipédia, correction) : gardée (fermeture puis réouverture non datée possible).
        $wp = [$this->ev('closure', '2017-12-15', 'wikipedia', 'day'), $this->ev('closure', '2025-03-29', StationTimeline::CORRECTION, 'day')];
        $this->assertCount(2, StationTimeline::dropContradictedClosures($wp, '2026-10-15'));
        // Réouverture connue après la fermeture : la fermeture est vraie, gardée.
        $reopened = [$this->ev('closure', '1990-01-01', 'osm'), $this->ev('reopening', '2005-03-26', 'wikipedia', 'day')];
        $this->assertCount(2, StationTimeline::dropContradictedClosures($reopened, '2026-10-15'));
        // Fermeture postérieure au relevé : gardée.
        $this->assertCount(1, StationTimeline::dropContradictedClosures([$this->ev('closure', '2026-10-18', 'wikidata', 'day')], '2026-10-15'));
    }

    public function test_sourced_correction_beats_the_stale_passenger_list(): void
    {
        // Pont-de-Claix : fermée le 29/03/2025, encore dans gares-de-voyageurs (relevé 2026).
        $evidence = ['status_source' => 'sncf', 'voyageurs' => true, 'voyageurs_seen' => '2026-10-15 17:08:34', 'sncf_freight' => false];
        $corr = [$this->ev('opening', '1858-01-01', 'wikidata'), $this->ev('closure', '2025-03-29', StationTimeline::CORRECTION, 'day')];
        $this->assertSame(StationTimeline::CLOSED, StationTimeline::currentStatus('active', $corr, '2026-10-20', $evidence)[0]);
        // La même date venue d'une source ordinaire reste contredite par le relevé postérieur.
        $wd = [$this->ev('opening', '1858-01-01', 'wikidata'), $this->ev('closure', '2025-03-29', 'wikipedia', 'day')];
        $this->assertSame(StationTimeline::ACTIVE, StationTimeline::currentStatus('active', $wd, '2026-10-20', $evidence)[0]);
    }

    public function test_dated_freight_end_closes_a_freight_only_station(): void
    {
        // Fontenay-le-Comte : drapeau fret SNCF périmé, « désaffectée du trafic marchandises » en 2007.
        $events = [$this->ev('opening', '1881-10-17', 'wikidata', 'day'), $this->ev('closure_passenger', '1969-03-02', 'wikipedia', 'day'),
            $this->ev('closure_freight', '2007-01-01', 'wikipedia')];
        $this->assertSame(StationTimeline::CLOSED, StationTimeline::currentStatus('no_commercial', $events, '2026-10-20', ['status_source' => 'sncf', 'sncf_freight' => true])[0]);
        $this->assertSame(StationTimeline::FREIGHT, StationTimeline::statusFromEvents($events, 2000, true));
    }

    // ── Ordre des événements du même jour ───────────────────────────────────────

    public function test_same_day_opening_comes_before_closure(): void
    {
        // Lille-Saint-Sauveur : ouverte en 1865 comme gare aux marchandises seulement.
        $events = [$this->ev('closure_passenger', '1865-01-01', StationTimeline::CORRECTION), $this->ev('closure', '2003-01-01', 'wikipedia'),
            $this->ev('opening', '1865-01-01', 'wikidata')];
        $this->assertSame(StationTimeline::FREIGHT, StationTimeline::statusFromEvents($events, 1870, true));
        $this->assertSame(StationTimeline::FREIGHT, StationTimeline::statusFromEvents($events, 2000, true));
        $this->assertSame(StationTimeline::CLOSED, StationTimeline::statusFromEvents($events, 2010, true));
        $this->assertSame(['opening', 'closure_passenger', 'closure'], array_column(StationTimeline::forTimeline($events), 'type'));
        // Paris-Tolbiac : fermeture voyageurs et fermeture totale le même mois → fermée, pas fret seul.
        $tolbiac = [$this->ev('opening', '1840-01-01', 'wikidata'), $this->ev('closure', '2001-06-01', StationTimeline::CORRECTION, 'month'),
            $this->ev('closure_passenger', '2001-06-01', 'wikipedia', 'month')];
        $this->assertNull(StationTimeline::statusFromEvents($tolbiac, 2010, false));
        $this->assertSame(StationTimeline::CLOSED, StationTimeline::currentStatus('no_commercial', $tolbiac, '2026-10-20',
            ['status_source' => 'sncf', 'sncf_freight' => true])[0]);
    }

    /**
     * Airvault-Gare (Wikipédia : voyageurs fermés en 1980, « fermée à tout trafic ferroviaire en décembre
     * 2015 ») : fin des voyageurs et fin du fret le même mois. Le statut du jour ne dépend plus de l'ordre
     * des deux fermetures (il basculait entre « fret seul » et « fermée » d'un passage à l'autre).
     */
    public function test_passenger_and_freight_closures_close_the_station_in_any_order(): void
    {
        $opening = $this->ev('opening', '1874-01-01', 'wikidata');
        $passenger = $this->ev('closure_passenger', '2015-12-01', 'wikipedia', 'month');
        $freight = $this->ev('closure_freight', '2015-12-01', 'wikipedia', 'month');
        $evidence = ['status_source' => 'sncf', 'sncf_freight' => true];
        foreach ([[$opening, $passenger, $freight], [$opening, $freight, $passenger]] as $events) {
            $this->assertSame([StationTimeline::CLOSED, StationTimeline::NOTE_CLOSED_TODAY],
                StationTimeline::currentStatus('no_commercial', $events, '2026-11-19', $evidence));
        }
        // Gare voyageurs : la fin du fret après celle des voyageurs la ferme aussi (avant : restait ouverte).
        $this->assertSame(StationTimeline::CLOSED, StationTimeline::currentStatus('active',
            [$opening, $this->ev('closure_passenger', '1980-01-01', 'wikipedia'), $freight], '2026-11-19', $evidence)[0]);
        // Fin des voyageurs seule : fret seul, comme avant.
        $this->assertSame(StationTimeline::FREIGHT, StationTimeline::currentStatus('active', [$opening, $passenger], '2026-11-19', $evidence)[0]);
        // Réouverture entre les deux : la fermeture aux voyageurs antérieure ne compte plus.
        $this->assertSame(StationTimeline::FREIGHT, StationTimeline::currentStatus('no_commercial',
            [$opening, $this->ev('closure_freight', '1990-01-01', 'wikipedia'), $this->ev('reopening', '2000-01-01', 'wikipedia'), $passenger],
            '2026-11-19', $evidence)[0]);
    }

    // ── Conflit de date : fermeture voyageurs suivie de fret ────────────────────

    public function test_passenger_closure_before_freight_eras_is_not_a_conflict(): void
    {
        // Strasbourg-Koenigshoffen : voyageurs fermés en 1846, gare de marchandises ensuite.
        $eras = [1850 => ['status' => StationTimeline::FREIGHT], 1900 => ['status' => StationTimeline::FREIGHT, 'uncertain' => true],
            2020 => ['status' => StationTimeline::FREIGHT, 'uncertain' => true], 2030 => ['status' => StationTimeline::CLOSED]];
        $r = StationTimeline::resolveClosure($eras, 2030, '2026-10-20', [$this->ev('closure_passenger', '1846-07-01', 'wikipedia', 'month')]);
        $this->assertNotNull($r);
        $this->assertNull($r['conflict']);
        $this->assertSame([StationTimeline::RANGE, 'vff:eras'], [$r['precision'], $r['source']]);
        // Lille-Saint-Sauveur : jamais ouverte aux voyageurs (1865), fret jusqu'en 2003 : la date retenue est 2003.
        $lille = [1870 => ['status' => StationTimeline::FREIGHT, 'uncertain' => true], 2000 => ['status' => StationTimeline::FREIGHT, 'uncertain' => true],
            2010 => ['status' => StationTimeline::CLOSED], 2030 => ['status' => StationTimeline::CLOSED]];
        $r = StationTimeline::resolveClosure($lille, 2030, '2026-10-20', [$this->ev('closure_passenger', '1865-01-01', StationTimeline::CORRECTION),
            $this->ev('closure', '2003-01-01', 'wikipedia')]);
        $this->assertSame(['2003-01-01', 'year', null], [$r['date'] ?? null, $r['precision'] ?? null, $r['conflict'] ?? null]);
        // Une fermeture totale hors de l'intervalle reste un conflit.
        $r = StationTimeline::resolveClosure($eras, 2030, '2026-10-20', [$this->ev('closure', '1846-07-01', 'wikipedia', 'month')]);
        $this->assertSame('1846-07-01', $r['conflict']['date'] ?? null);
    }

    // ── Gares hors des lignes VFF ───────────────────────────────────────────────

    public function test_station_attested_today_is_not_uncertain_and_own_chronology_is_an_exemption(): void
    {
        $this->assertTrue(StationLineCoverage::attestedToday('osm', 'tourist'));
        $this->assertTrue(StationLineCoverage::attestedToday('wikidata', 'active'));
        $this->assertFalse(StationLineCoverage::attestedToday('sncf', 'active'), 'drapeau SNCF seul');
        $this->assertFalse(StationLineCoverage::attestedToday('osm', 'declassified'));
        // Gare SNCF sans ligne : drapeaux seuls → pas d'exemption ; chronologie propre → exemption.
        $this->assertNull(StationLineCoverage::exemption(false, false, false, 'sncf', false));
        $this->assertSame(StationLineCoverage::NOTE_UNMODELED, StationLineCoverage::exemption(false, false, false, 'sncf', false, ownChronology: true));
    }

    // ── Import OSM : autres modes que le train ──────────────────────────────────

    public function test_osm_off_network_modes_are_excluded_even_when_disused(): void
    {
        // Saint-Hilaire-du-Touvet : funiculaire désaffecté, « railway=station » resté en place.
        $this->assertTrue(ImportStationSources::isOffNetworkOsm(['railway' => 'station', 'disused:railway' => 'station',
            'disused:funicular' => 'yes', 'disused:train' => 'yes']));
        $this->assertTrue(ImportStationSources::isOffNetworkOsm(['railway' => 'station', 'station' => 'funicular']));
        $this->assertTrue(ImportStationSources::isOffNetworkOsm(['railway' => 'halt', 'tram' => 'yes']));
        $this->assertTrue(ImportStationSources::isOffNetworkOsm(['disused:railway' => 'station', 'disused:station' => 'subway']));
        $this->assertTrue(ImportStationSources::isOffNetworkOsm(['railway' => 'station', 'station' => 'miniature']));
        // Gare commune train + tram (tram-train) : gardée ; gare ordinaire : gardée.
        $this->assertFalse(ImportStationSources::isOffNetworkOsm(['railway' => 'station', 'tram' => 'yes', 'train' => 'yes']));
        $this->assertFalse(ImportStationSources::isOffNetworkOsm(['railway' => 'station', 'train' => 'yes']));
        $this->assertFalse(ImportStationSources::isOffNetworkOsm(['disused:railway' => 'halt']));
    }
}
