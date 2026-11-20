<?php

namespace Tests\Unit;

use App\Services\StationTimeline;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Une seule date de fermeture par gare (StationTimeline::resolveClosure) et
 * état du jour de l'ère courante (StationTimeline::currentStatus).
 *
 * La date retenue tombe toujours dans l'intervalle où les ères (station_eras)
 * placent la fermeture : la carte, la fiche gare et la fiche ligne ne peuvent
 * plus se contredire. Cas réels : L'Isle-sur-Serein (756000, desserte arrêtée
 * en mai 1945, déclassement 12/11/1954 retenu à tort), Vuillafans (873000,
 * fin du fret 11/06/1956, « fermeture 1988 » de toute la ligne retenue à tort).
 */
class StationClosureResolutionTest extends TestCase
{
    private const TODAY = '2026-10-19';

    /** @return array<int, array{status: string, uncertain: bool}|null> */
    private function eras(array $map): array
    {
        $out = [];
        foreach ($map as $year => $status) {
            $out[$year] = $status === null ? null : ['status' => rtrim($status, '?'), 'uncertain' => str_ends_with($status, '?')];
        }

        return $out;
    }

    private function ev(string $type, string $date, string $precision = 'day', bool $line = false, bool $inferred = false, string $source = 'wikipedia'): array
    {
        return ['type' => $type, 'date' => $date, 'precision' => $precision, 'line' => $line, 'inferred' => $inferred || $line, 'source' => $line ? 'line_event:1' : $source];
    }

    public function test_open_today_has_no_closure_date(): void
    {
        $eras = $this->eras([1950 => 'active', 2030 => 'active']);
        $this->assertNull(StationTimeline::resolveClosure($eras, 2030, self::TODAY, [$this->ev('closure', '1960-01-01')]));
        $this->assertNull(StationTimeline::resolveClosure($this->eras([1950 => 'active', 2030 => 'no_commercial']), 2030, self::TODAY, []));
        $this->assertNull(StationTimeline::resolveClosure($this->eras([1950 => 'active', 2030 => 'active?']), 2030, self::TODAY, []));
    }

    public function test_never_shown_has_no_closure_date(): void
    {
        $this->assertNull(StationTimeline::resolveClosure([], 2030, self::TODAY, [$this->ev('closure', '1960-01-01')]));
    }

    public function test_line_end_of_service_inside_the_bracket_beats_a_late_declassification(): void
    {
        // L'Isle-sur-Serein : 1940 fret seul, 1950 fermée (absente ensuite).
        $eras = $this->eras([1930 => 'active', 1940 => 'no_commercial', 1950 => 'declassified']);
        $r = StationTimeline::resolveClosure($eras, 2030, self::TODAY, [
            $this->ev('closure', '1954-11-12', 'day', inferred: true, source: 'inferred:line_event'),
            $this->ev('closure_freight', '1945-05-01', 'month', line: true),
            $this->ev('declassification', '1954-11-12', 'day', line: true),
            $this->ev('closure_passenger', '1934-01-15', 'day', line: true),
        ]);
        $this->assertSame(['1945-05-01', 'month'], [$r['date'], $r['precision']]);
        $this->assertStringStartsWith('line_event:', $r['source']);
    }

    public function test_station_source_beats_line_events_in_the_bracket(): void
    {
        $eras = $this->eras([1950 => 'no_commercial', 1960 => 'declassified']);
        $r = StationTimeline::resolveClosure($eras, 2030, self::TODAY, [
            $this->ev('closure_freight', '1956-06-11', 'day', line: true),
            $this->ev('closure', '1957-01-01', 'year'),
        ]);
        $this->assertSame(['1957-01-01', 'year', 'wikipedia'], [$r['date'], $r['precision'], $r['source']]);
    }

    public function test_total_closure_beats_passenger_closure_and_precision_then_latest(): void
    {
        $eras = $this->eras([1970 => 'active', 1980 => 'declassified']);
        $r = StationTimeline::resolveClosure($eras, 2030, self::TODAY, [
            $this->ev('closure_passenger', '1972-05-28'),
            $this->ev('closure', '1975-01-01', 'year'),
            $this->ev('closure', '1975-09-28'),
        ]);
        $this->assertSame(['1975-09-28', 'day'], [$r['date'], $r['precision']]);
    }

    public function test_wikipedia_beats_bulk_wikidata_dates(): void
    {
        // Ballersdorf : Wikipédia 11/12/2011 (mise en service de la LGV Rhin-Rhône), Wikidata 30/12/2013 (date en masse).
        $eras = $this->eras([2010 => 'active', 2020 => 'declassified', 2030 => 'declassified']);
        $r = StationTimeline::resolveClosure($eras, 2030, self::TODAY, [
            $this->ev('closure', '2011-01-01', 'year', source: 'wikidata'),
            $this->ev('closure', '2013-12-30', 'day', source: 'wikidata'),
            $this->ev('closure', '2011-12-11', 'day', source: 'wikipedia'),
        ]);
        $this->assertSame(['2011-12-11', 'wikipedia'], [$r['date'], $r['source']]);
        // Belleville-Villette : Wikipédia (voyageurs) 23/07/1934 contre qualificatif Wikidata 01/04/1934.
        $eras = $this->eras([1930 => 'active', 1940 => 'declassified']);
        $r = StationTimeline::resolveClosure($eras, 2030, self::TODAY, [
            $this->ev('closure', '1934-04-01', 'day', source: 'wikidata:qualifiers'),
            $this->ev('closure_passenger', '1934-01-01', 'year', source: 'wikipedia'),
            $this->ev('closure_passenger', '1934-07-23', 'day', source: 'wikipedia'),
        ]);
        $this->assertSame('1934-07-23', $r['date']);
    }

    public function test_a_more_precise_date_of_the_same_closure_refines_it(): void
    {
        $eras = $this->eras([1960 => 'active', 1970 => 'declassified']);
        $r = StationTimeline::resolveClosure($eras, 2030, self::TODAY, [
            $this->ev('closure', '1965-01-01', 'year', source: 'wikipedia'),
            $this->ev('closure', '1965-05-30', 'day', source: 'wikidata'),
        ]);
        $this->assertSame(['1965-05-30', 'day'], [$r['date'], $r['precision']]);
    }

    public function test_date_outside_the_bracket_is_a_conflict_not_a_closure_date(): void
    {
        // Glanges : fermée à l'ère 1980, une source dit 1990.
        $eras = $this->eras([1970 => 'active', 1980 => 'declassified', 1990 => 'declassified']);
        $r = StationTimeline::resolveClosure($eras, 2030, self::TODAY, [$this->ev('closure', '1990-01-01', 'year')]);
        $this->assertSame([StationTimeline::RANGE, '1980-12-31', 1970], [$r['precision'], $r['date'], $r['after']]);
        $this->assertSame('1990-01-01', $r['conflict']['date']);
    }

    public function test_no_date_gives_the_era_bracket(): void
    {
        $eras = $this->eras([1950 => 'active', 1960 => 'declassified']);
        $r = StationTimeline::resolveClosure($eras, 2030, self::TODAY, []);
        $this->assertSame(['1960-12-31', StationTimeline::RANGE, 1950, 'vff:eras'], [$r['date'], $r['precision'], $r['after'], $r['source']]);
        $this->assertSame('entre 1951 et 1960', StationTimeline::closureLabel($r['date'], $r['precision'], $r['after']));
    }

    public function test_uncertain_eras_widen_the_bracket(): void
    {
        // Fermeture non datée : affirmée ouverte en 1950, incertaine ensuite, fermée aujourd'hui.
        $eras = $this->eras([1950 => 'active', 1960 => 'active?', 2020 => 'active?', 2030 => 'declassified']);
        $r = StationTimeline::resolveClosure($eras, 2030, self::TODAY, [$this->ev('closure', '1985-01-01', 'year')]);
        $this->assertSame(['1985-01-01', 'year'], [$r['date'], $r['precision']]);
        $none = StationTimeline::resolveClosure($eras, 2030, self::TODAY, []);
        $this->assertSame([self::TODAY, 1950], [$none['date'], $none['after']]);
    }

    public function test_closed_today_on_an_open_line_uses_today_as_upper_bound(): void
    {
        $eras = $this->eras([2020 => 'active', 2030 => 'declassified']);
        $r = StationTimeline::resolveClosure($eras, 2030, self::TODAY, [$this->ev('closure', self::TODAY, 'day', source: 'manual')]);
        $this->assertSame([self::TODAY, 'day', 'manual'], [$r['date'], $r['precision'], $r['source']]);
        // Fermeture de demain : hors intervalle.
        $tomorrow = StationTimeline::resolveClosure($eras, 2030, self::TODAY, [$this->ev('closure', '2026-10-20')]);
        $this->assertSame(StationTimeline::RANGE, $tomorrow['precision']);
    }

    public function test_absent_station_closes_at_the_next_era(): void
    {
        // Gare disparue : plus aucune ligne à l'ère suivante (absente, pas « fermée »).
        $eras = $this->eras([1930 => 'active', 1940 => null]);
        $r = StationTimeline::resolveClosure($eras, 2030, self::TODAY, [$this->ev('closure', '1938-05-15')]);
        $this->assertSame('1938-05-15', $r['date']);
    }

    public function test_reopened_station_uses_the_last_closure(): void
    {
        // Ouverte, fermée en 1970, rouverte en 2000, refermée en 2016.
        $eras = $this->eras([1960 => 'active', 1970 => 'declassified', 2000 => 'active', 2010 => 'active', 2020 => 'declassified', 2030 => 'declassified']);
        $r = StationTimeline::resolveClosure($eras, 2030, self::TODAY, [$this->ev('closure', '1969-05-31'), $this->ev('closure', '2016-12-16')]);
        $this->assertSame('2016-12-16', $r['date']);
    }

    public function test_decade_closure_is_a_range_tightened_by_the_eras(): void
    {
        $eras = $this->eras([1980 => 'active', 1990 => 'declassified']);
        $r = StationTimeline::resolveClosure($eras, 2030, self::TODAY, [$this->ev('closure', '1980-01-01', 'decade')]);
        $this->assertSame([StationTimeline::RANGE, '1989-12-31', 1980], [$r['precision'], $r['date'], $r['after']]);
        $this->assertSame('entre 1981 et 1989', StationTimeline::closureLabel($r['date'], $r['precision'], $r['after']));
        // Précise et vague dans l'intervalle : la date précise.
        $p = StationTimeline::resolveClosure($eras, 2030, self::TODAY, [$this->ev('closure', '1980-01-01', 'decade'), $this->ev('closure', '1987-05-31')]);
        $this->assertSame(['1987-05-31', 'day'], [$p['date'], $p['precision']]);
    }

    public function test_after_precision_is_never_a_date(): void
    {
        $eras = $this->eras([1950 => 'active', 1960 => 'declassified']);
        $r = StationTimeline::resolveClosure($eras, 2030, self::TODAY, [$this->ev('closure', '1955-01-01', 'after')]);
        $this->assertSame(StationTimeline::RANGE, $r['precision']);
    }

    /** @return array<string, array{0: ?string, 1: ?string, 2: ?int, 3: ?string}> */
    public static function labels(): array
    {
        return [
            'jour' => ['1954-11-12', 'day', null, '12/11/1954'],
            'mois' => ['1945-05-01', 'month', null, '05/1945'],
            'année' => ['1957-01-01', 'year', null, '1957'],
            'intervalle' => ['1960-12-31', 'range', 1950, 'entre 1951 et 1960'],
            'borne seule' => ['1960-12-31', 'range', null, 'au plus tard en 1960'],
            'aucune' => [null, null, null, null],
        ];
    }

    #[DataProvider('labels')]
    public function test_closure_label(?string $date, ?string $precision, ?int $after, ?string $expected): void
    {
        $this->assertSame($expected, StationTimeline::closureLabel($date, $precision, $after));
    }

    public function test_duplicate_closures_keep_the_most_reliable_source(): void
    {
        $ev = fn (string $type, string $date, string $source, string $precision = 'day') => ['type' => $type, 'date' => $date, 'source' => $source, 'precision' => $precision];
        // Boulevard-Masséna : fiche fusionnant la gare de Petite Ceinture (1934) et la halte du RER C (03/12/2000).
        $out = StationTimeline::dedupeClosures([
            $ev('opening', '1840-09-20', 'wikipedia'),
            $ev('closure', '1934-04-01', 'wikidata:qualifiers'),
            $ev('closure', '2000-01-01', 'wikidata', 'year'),
            $ev('closure', '2000-12-03', 'wikipedia'),
        ]);
        $this->assertSame(['1840-09-20', '2000-12-03'], array_column($out, 'date'));
        // Une réouverture sépare deux fermetures : les deux restent.
        $out = StationTimeline::dedupeClosures([
            $ev('closure', '1980-05-31', 'wikidata'), $ev('reopening', '2012-12-09', 'wikidata'), $ev('closure', '2020-01-01', 'osm', 'year'),
        ]);
        $this->assertCount(3, $out);
        // Niveaux distincts (voyageurs, totale) gardés ; déduites et « après » ignorées.
        $out = StationTimeline::dedupeClosures([
            $ev('closure_passenger', '1939-05-15', 'wikipedia'), $ev('closure', '2000-01-01', 'wikidata', 'year'),
            ['type' => 'closure', 'date' => '1990-01-01', 'source' => 'inferred:line_event', 'inferred' => true, 'precision' => 'day'],
            $ev('closure', '1950-01-01', 'wikipedia', 'after'),
        ]);
        $this->assertCount(4, $out);
    }

    // ── Nature du point ────────────────────────────────────────────────────

    public function test_point_without_public_proof_on_a_high_speed_line_is_technical(): void
    {
        // LGV Sud-Est : Marolles-TGV, Vaumort, Tonnerre-TGV… (liste SNCF seule, ni voyageurs ni fret).
        $this->assertSame(StationTimeline::KIND_TECHNICAL, StationTimeline::kind('Marolles-TGV', false, true));
        $this->assertSame(StationTimeline::KIND_TECHNICAL, StationTimeline::kind('Vaumort', false, true));
        // Gare TGV (preuve : voyageurs) : une gare.
        $this->assertSame(StationTimeline::KIND_STATION, StationTimeline::kind('Mâcon-Loché-TGV', true, true));
        // Ligne classique : la liste SNCF garde le code des gares fermées (Vertus) : une ancienne gare.
        $this->assertSame(StationTimeline::KIND_STATION, StationTimeline::kind('Vertus', false, false));
    }

    public function test_installation_names_are_technical_points(): void
    {
        foreach (['Azincourt-Garage', 'Lille-Bifurcation-de-la-Justice', 'Forêt-de-la-Londe (PK)', 'Chambéry-Poste-Bois', 'Bif de Saint-Germain'] as $name) {
            $this->assertSame(StationTimeline::KIND_TECHNICAL, StationTimeline::kind($name, false, false), $name);
        }
        foreach (['Port-sur-Saône', 'Garage-le-Village', 'Postel', 'Bifontaine'] as $name) {
            $this->assertSame(StationTimeline::KIND_STATION, StationTimeline::kind($name, false, false), $name);
        }
        // Une preuve d'ouverture au public l'emporte sur le nom.
        $this->assertSame(StationTimeline::KIND_STATION, StationTimeline::kind('Azincourt-Garage', true, false));
    }

    public function test_planned_station_never_opened_is_projected_not_closed(): void
    {
        $this->assertSame(StationTimeline::KIND_PROJECTED, StationTimeline::kind('Vandières TGV', false, false, planned: true));
        $this->assertSame(StationTimeline::KIND_STATION, StationTimeline::kind('Gare rouverte', true, false, planned: true));
    }

    // ── État du jour ───────────────────────────────────────────────────────

    private const SERVED = ['status_source' => 'sncf', 'voyageurs' => true, 'voyageurs_seen' => '2026-10-15 17:08:34'];

    public function test_closure_dated_today_closes_a_station_on_an_open_line(): void
    {
        [$status, $note] = StationTimeline::currentStatus('active', [$this->ev('closure', self::TODAY, source: 'manual')], self::TODAY, self::SERVED);
        $this->assertSame(StationTimeline::CLOSED, $status);
        $this->assertSame(StationTimeline::NOTE_CLOSED_TODAY, $note);
    }

    public function test_closure_dated_tomorrow_applies_tomorrow(): void
    {
        $events = [$this->ev('closure', '2026-10-20')];
        $this->assertSame(StationTimeline::ACTIVE, StationTimeline::currentStatus('active', $events, self::TODAY, self::SERVED)[0]);
        $this->assertSame(StationTimeline::CLOSED, StationTimeline::currentStatus('active', $events, '2026-10-20', self::SERVED)[0]);
    }

    public function test_served_after_an_old_closure_means_reopened(): void
    {
        // Gare listée par gares-de-voyageurs (relevé 2026) malgré une fermeture de 1976 : rouverte.
        $this->assertSame(StationTimeline::ACTIVE, StationTimeline::currentStatus('active', [$this->ev('closure', '1976-06-30')], self::TODAY, self::SERVED)[0]);
    }

    public function test_reopening_after_the_closure_keeps_the_station_open(): void
    {
        $events = [$this->ev('closure', '1992-05-31'), $this->ev('reopening', '2018-12-09')];
        $this->assertSame(StationTimeline::ACTIVE, StationTimeline::currentStatus('active', $events, self::TODAY, ['status_source' => 'osm', 'osm_active' => true])[0]);
    }

    public function test_dated_closure_without_current_service_proof_closes(): void
    {
        // Halte supprimée (Le Merlerault, 13/12/2019) que seul OSM dit encore en service.
        $events = [$this->ev('closure', '2019-12-13', 'day', source: 'wikidata')];
        $this->assertSame(StationTimeline::CLOSED, StationTimeline::currentStatus('active', $events, self::TODAY, ['status_source' => 'sncf', 'osm_active' => true])[0]);
    }

    public function test_inferred_events_do_not_drive_the_current_era(): void
    {
        $events = [$this->ev('closure', '1990-01-01', inferred: true)];
        $this->assertSame(StationTimeline::ACTIVE, StationTimeline::currentStatus('active', $events, self::TODAY, ['status_source' => 'osm', 'osm_active' => true])[0]);
        // … sauf une réouverture déduite de la réouverture de la ligne.
        $events = [$this->ev('closure', '2014-12-19', source: 'wikidata'), $this->ev('reopening', '2025-06-01', inferred: true)];
        $this->assertSame(StationTimeline::ACTIVE, StationTimeline::currentStatus('active', $events, self::TODAY, ['status_source' => 'osm', 'osm_active' => true])[0]);
    }

    public function test_not_served_any_more_is_closed_or_freight(): void
    {
        $stale = ['status_source' => 'sncf', 'voyageurs' => false, 'osm_active' => false, 'wikidata_active' => false, 'closure_proof' => true];
        $this->assertSame([StationTimeline::CLOSED, StationTimeline::NOTE_NOT_SERVED], StationTimeline::currentStatus('active', [], self::TODAY, $stale));
        $this->assertSame(StationTimeline::FREIGHT, StationTimeline::currentStatus('active', [], self::TODAY, ['sncf_freight' => true] + $stale)[0]);
        // Une preuve de desserte suffit à la garder ouverte.
        foreach (['voyageurs', 'osm_active', 'wikidata_active'] as $proof) {
            $this->assertSame(StationTimeline::ACTIVE, StationTimeline::currentStatus('active', [], self::TODAY, [$proof => true] + $stale)[0], $proof);
        }
        // Statut venu d'OSM (autre exploitant) : non concerné.
        $this->assertSame(StationTimeline::ACTIVE, StationTimeline::currentStatus('active', [], self::TODAY, ['status_source' => 'osm'] + $stale)[0]);
        // Sans preuve de fermeture (Grasse, tram-train de Mulhouse : absents du jeu gares-de-voyageurs) : ouverte.
        $this->assertSame(StationTimeline::ACTIVE, StationTimeline::currentStatus('active', [], self::TODAY, ['closure_proof' => false] + $stale)[0]);
        // Ligne rouverte (réouverture connue) : ouverte (Docelles-Cheniménil, Épinal - Saint-Dié).
        $this->assertSame(StationTimeline::ACTIVE, StationTimeline::currentStatus('active', [], self::TODAY, ['reopened' => true] + $stale)[0]);
    }

    public function test_gone_and_closed_sources(): void
    {
        $this->assertNull(StationTimeline::currentStatus('gone', [], self::TODAY)[0]);
        $this->assertNull(StationTimeline::currentStatus(null, [], self::TODAY)[0]);
        $this->assertSame(StationTimeline::CLOSED, StationTimeline::currentStatus('declassified', [$this->ev('reopening', '1990-01-01')], self::TODAY)[0]);
    }

    public function test_passenger_closure_keeps_freight_only_with_the_freight_flag(): void
    {
        $events = [$this->ev('closure_passenger', '2020-12-13')];
        $this->assertSame(StationTimeline::CLOSED, StationTimeline::currentStatus('active', $events, self::TODAY, ['status_source' => 'osm', 'osm_active' => true])[0]);
        $this->assertSame(StationTimeline::FREIGHT, StationTimeline::currentStatus('active', $events, self::TODAY, ['status_source' => 'sncf', 'sncf_freight' => true, 'osm_active' => true])[0]);
        // Fin du fret d'une gare fret seul.
        $this->assertSame(StationTimeline::CLOSED, StationTimeline::currentStatus('no_commercial', [$this->ev('closure_freight', '2021-01-01', 'year')], self::TODAY, ['status_source' => 'sncf'])[0]);
        // Fin du fret d'une gare voyageurs : rien ne change.
        $this->assertSame(StationTimeline::ACTIVE, StationTimeline::currentStatus('active', [$this->ev('closure_freight', '2021-01-01', 'year')], self::TODAY, self::SERVED)[0]);
    }

    public function test_tourist_train_still_stops_after_the_closure(): void
    {
        // Thiers - Ambert : fermée aux voyageurs SNCF en 1980, train touristique aujourd'hui.
        $events = [$this->ev('closure', '1980-09-29', source: 'ohm')];
        $this->assertSame(StationTimeline::TOURIST, StationTimeline::currentStatus('active', $events, self::TODAY, ['status_source' => 'osm', 'osm_active' => true, 'tourist' => true])[0]);
    }
}
