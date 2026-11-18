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
