<?php

namespace Tests\Unit;

use App\Services\EraTimeline;
use PHPUnit\Framework\TestCase;

class EraTimelineTest extends TestCase
{
    public function test_portion_closed_in_1980_is_no_longer_active_in_the_1980_era(): void
    {
        // Portion ouverte en 1970 avec le reste de la ligne, fermée en 1980.
        $closed = new EraTimeline([
            ['type' => 'opening', 'date' => '1970-01-01', 'source' => 'wikipedia'],
            ['type' => 'closure', 'date' => '1980-01-01', 'source' => 'wikipedia'],
        ]);
        // Portion restée ouverte.
        $open = new EraTimeline([
            ['type' => 'opening', 'date' => '1970-01-01', 'source' => 'wikipedia'],
        ]);

        $this->assertNull($closed->stateAt(1960));
        $this->assertSame(['status' => 'in_service', 'traffic' => 'mixed'], $closed->stateAt(1970));
        $this->assertSame(['status' => 'abandoned', 'traffic' => 'unknown'], $closed->stateAt(1980));
        $this->assertSame(['status' => 'in_service', 'traffic' => 'mixed'], $open->stateAt(1980));
    }

    public function test_passenger_closure_turns_the_portion_into_freight_only(): void
    {
        $t = new EraTimeline([
            ['type' => 'opening', 'date' => '1891-09-28'],
            ['type' => 'closure_passenger', 'date' => '1938-05-15'],
            ['type' => 'closure_freight', 'date' => '1967-01-01'],
            ['type' => 'dismantling', 'date' => '1972-01-01'],
        ]);

        $this->assertSame('mixed', $t->stateAt(1930)['traffic']);
        $this->assertSame(['status' => 'in_service', 'traffic' => 'freight'], $t->stateAt(1940));
        $this->assertSame(['status' => 'abandoned', 'traffic' => 'unknown'], $t->stateAt(1967));
        $this->assertSame('dismantled', $t->stateAt(1980)['status']);
    }

    public function test_reopening_to_passengers_after_freight_only_period(): void
    {
        $t = new EraTimeline([
            ['type' => 'opening', 'date' => '1900-01-01'],
            ['type' => 'closure_passenger', 'date' => '1950-01-01'],
            ['type' => 'reopening_passenger', 'date' => '2005-06-01'],
        ]);

        $this->assertSame('freight', $t->stateAt(1990)['traffic']);
        $this->assertSame('mixed', $t->stateAt(2010)['traffic']);
    }

    public function test_passenger_service_implies_freight_except_on_high_speed_lines(): void
    {
        $events = [['type' => 'opening_passenger', 'date' => '1981-09-27']];

        $this->assertSame('mixed', (new EraTimeline($events))->stateAt(1990)['traffic']);
        $this->assertSame('passenger', (new EraTimeline($events, 'PF', passengerImpliesFreight: false))->stateAt(1990)['traffic']);
    }

    public function test_industrial_network_opens_freight_only(): void
    {
        $t = new EraTimeline([['type' => 'opening', 'date' => '1920-01-01']], 'F');

        $this->assertSame(['status' => 'in_service', 'traffic' => 'freight'], $t->stateAt(1930));
    }

    public function test_military_line_is_in_service_with_unknown_traffic_until_closed(): void
    {
        $t = new EraTimeline([
            ['type' => 'opening', 'date' => '1915-01-01'],
            ['type' => 'closure', 'date' => '1919-01-01'],
        ], '');

        $this->assertSame(['status' => 'in_service', 'traffic' => 'unknown'], $t->stateAt(1915));
        $this->assertSame('abandoned', $t->stateAt(1920)['status']);
    }

    public function test_same_event_from_two_sources_keeps_the_most_reliable(): void
    {
        $t = new EraTimeline([
            ['type' => 'opening', 'date' => '1853-08-17', 'source' => 'ohm'],
            ['type' => 'opening', 'date' => '1855-09-22', 'source' => 'lignes-oubliees'],
        ]);

        // L'ouverture retenue est celle de lignes-oubliees (1855) : rien en 1854.
        $this->assertNull($t->stateAt(1854));
        $this->assertNotNull($t->stateAt(1855));
    }

    public function test_vague_closure_from_a_weaker_source_does_not_override_detailed_closures(): void
    {
        $t = new EraTimeline([
            ['type' => 'opening', 'date' => '1891-01-01', 'source' => 'lignes-oubliees'],
            ['type' => 'closure_passenger', 'date' => '1938-05-15', 'source' => 'lignes-oubliees'],
            ['type' => 'closure_freight', 'date' => '1967-01-01', 'source' => 'lignes-oubliees'],
            // Wikidata : « fermeture » sans précision, sur toute la ligne.
            ['type' => 'closure', 'date' => '1938-01-01', 'source' => 'wikidata'],
        ]);

        $this->assertSame(['status' => 'in_service', 'traffic' => 'freight'], $t->stateAt(1950));
        $this->assertSame('abandoned', $t->stateAt(1970)['status']);
    }

    public function test_conversion_to_greenway_marks_the_portion_dismantled(): void
    {
        $t = new EraTimeline([
            ['type' => 'opening', 'date' => '1895-01-01'],
            ['type' => 'closure', 'date' => '1990-01-01', 'source' => 'sncf'],
            ['type' => 'conversion', 'date' => '2021-06-01', 'source' => 'osm', 'reuse' => 'greenway'],
        ]);

        $this->assertSame(['status' => 'abandoned', 'traffic' => 'unknown'], $t->stateAt(2020));
        $this->assertSame(['status' => 'dismantled', 'traffic' => 'unknown', 'reuse' => 'greenway'], $t->stateAt(2026));
    }

    public function test_dismantled_line_shows_once_then_disappears(): void
    {
        $t = new EraTimeline([
            ['type' => 'opening', 'date' => '1900-01-01'],
            ['type' => 'closure', 'date' => '1968-01-01'],
            ['type' => 'dismantling', 'date' => '1975-01-01'],
        ]);

        $this->assertSame('abandoned', $t->stateAt(1970)['status']);
        $this->assertSame('dismantled', $t->stateAt(1980)['status']);
        $this->assertNull($t->stateAt(1990));
    }

    public function test_undated_removal_proven_today_is_placed_right_after_closure(): void
    {
        $t = (new EraTimeline([
            ['type' => 'opening', 'date' => '1900-01-01'],
            ['type' => 'closure', 'date' => '1991-01-01'],
        ]))->withPresentState('greenway');

        $this->assertSame('abandoned', $t->stateAt(2000)['status']); // fermée depuis 1991, voie encore là
        $this->assertSame(['status' => 'dismantled', 'traffic' => 'unknown', 'reuse' => 'greenway'], $t->stateAt(2010));
        $this->assertNull($t->stateAt(2020));
        $this->assertNull($t->stateAt(2026, isCurrent: true));
    }

    public function test_greenway_opening_year_dates_the_removal(): void
    {
        $t = (new EraTimeline([
            ['type' => 'opening', 'date' => '1900-01-01'],
            ['type' => 'closure', 'date' => '1990-01-01'],
        ]))->withPresentState('greenway', 2021);

        $this->assertSame('abandoned', $t->stateAt(2020)['status']);
        $this->assertSame(['status' => 'dismantled', 'traffic' => 'unknown', 'reuse' => 'greenway'], $t->stateAt(2026, isCurrent: true));
        $this->assertNull($t->stateAt(2040));
    }

    public function test_track_left_in_place_stays_closed(): void
    {
        $t = (new EraTimeline([
            ['type' => 'opening', 'date' => '1900-01-01'],
            ['type' => 'closure', 'date' => '1950-01-01'],
        ]))->withPresentState('track_in_place', goneByDefault: true);

        $this->assertSame('abandoned', $t->stateAt(2026, isCurrent: true)['status']);
    }

    public function test_vanished_line_without_known_closure_is_not_on_the_current_map(): void
    {
        $t = (new EraTimeline([['type' => 'opening', 'date' => '1900-01-01']]))->withPresentState(null, goneByDefault: true);

        $this->assertSame('in_service', $t->stateAt(1910)['status']);
        $this->assertNull($t->stateAt(2026, isCurrent: true));
    }

    public function test_section_dates_beat_a_whole_line_completion_date(): void
    {
        $t = new EraTimeline([
            // Infobox : « ouverture (toute la ligne) 1871 » = date d'achèvement.
            ['type' => 'opening', 'date' => '1871-01-01', 'source' => 'wikipedia', 'whole' => true],
            // Ouverture réelle de CETTE portion.
            ['type' => 'opening', 'date' => '1856-10-20', 'source' => 'ohm', 'whole' => false],
        ]);

        $this->assertNotNull($t->stateAt(1860));
    }

    public function test_whole_line_completion_date_does_not_reopen_an_old_alignment(): void
    {
        // Ancien tracé « jusqu'en 1866 » ; l'infobox donne 1856 (début) et 1871 (achèvement).
        $t = new EraTimeline([
            ['type' => 'opening', 'date' => '1856-01-01', 'source' => 'wikidata', 'whole' => true],
            ['type' => 'opening', 'date' => '1871-01-01', 'source' => 'wikipedia', 'whole' => true],
            ['type' => 'closure', 'date' => '1866-01-01', 'source' => 'archeoferroviaire', 'whole' => false],
        ]);

        $this->assertSame('in_service', $t->stateAt(1860)['status']);
        $this->assertSame('abandoned', $t->stateAt(1870)['status']);
        $this->assertSame('abandoned', $t->stateAt(1875)['status']);
    }

    public function test_portion_run_by_an_association_after_closure(): void
    {
        $t = (new EraTimeline([
            ['type' => 'opening', 'date' => '1909-01-01'],
            ['type' => 'closure', 'date' => '1969-01-01'],
            ['type' => 'heritage_start', 'date' => '1982-06-01', 'heritage_kind' => 'tourist_train'],
        ]))->withPresentState(null, goneByDefault: true);

        $this->assertSame('abandoned', $t->stateAt(1970)['status']);
        $this->assertSame(['status' => 'in_service', 'traffic' => 'unknown', 'heritage' => 'tourist_train'], $t->stateAt(1990));
        $this->assertSame('tourist_train', $this->state($t, 2026, isCurrent: true)['heritage']);
    }

    public function test_velorail_keeps_the_track_but_runs_no_trains(): void
    {
        $t = new EraTimeline([
            ['type' => 'opening', 'date' => '1890-01-01'],
            ['type' => 'closure', 'date' => '1990-01-01'],
            ['type' => 'heritage_start', 'date' => '2005-01-01', 'heritage_kind' => 'velorail'],
        ]);

        $this->assertSame(['status' => 'abandoned', 'traffic' => 'unknown', 'heritage' => 'velorail'], $t->stateAt(2010));
    }

    public function test_recently_closed_line_already_gone_is_dismantled_on_the_current_map(): void
    {
        $t = (new EraTimeline([
            ['type' => 'opening', 'date' => '1900-01-01'],
            ['type' => 'closure', 'date' => '2018-01-01'],
        ]))->withPresentState('greenway');

        $this->assertSame(['status' => 'dismantled', 'traffic' => 'unknown', 'reuse' => 'greenway'], $t->stateAt(2026, isCurrent: true));
    }

    public function test_dated_removal_prevents_an_earlier_inferred_one(): void
    {
        $t = (new EraTimeline([
            ['type' => 'opening', 'date' => '1900-01-01'],
            ['type' => 'closure', 'date' => '1998-09-14'],
            ['type' => 'conversion', 'date' => '2014-01-01', 'reuse' => 'greenway'],
        ]))->withPresentState('greenway');

        $this->assertSame('abandoned', $t->stateAt(2010)['status']);
        $this->assertSame('dismantled', $t->stateAt(2020)['status']);
        $this->assertNull($t->stateAt(2026 + 4)); // 2030 : dépose de 2014 trop ancienne
    }

    public function test_administrative_closure_decision_does_not_hide_the_real_closure(): void
    {
        $t = new EraTimeline([
            ['type' => 'opening', 'date' => '1900-01-01', 'source' => 'lignes-oubliees'],
            ['type' => 'closure', 'date' => '1987-05-31', 'source' => 'wikipedia'],
            ['type' => 'closure', 'date' => '2018-03-01', 'source' => 'lignes-oubliees-pdf'],
        ]);

        $this->assertSame('abandoned', $t->stateAt(1990)['status']);
    }

    public function test_association_run_portion_without_commercial_opening_date_is_shown(): void
    {
        $t = new EraTimeline([
            ['type' => 'heritage_start', 'date' => '1969-06-14', 'heritage_kind' => 'tourist_train'],
        ]);

        $this->assertTrue($t->isDated());
        $this->assertSame('tourist_train', $this->state($t, 1970)['heritage']);
    }

    public function test_undated_portion_is_reported(): void
    {
        $this->assertFalse((new EraTimeline([['type' => 'closure', 'date' => '1950-01-01']]))->isDated());
        $this->assertTrue((new EraTimeline([['type' => 'opening_freight', 'date' => '1950-01-01']]))->isDated());
    }

    public function test_later_total_closure_from_a_weaker_source_ends_the_freight_only_period(): void
    {
        // La meilleure source ne dit que la fin des voyageurs (1938) : la fermeture totale
        // d'une source moins bien classée (1950) clôt la période « fret seul ».
        $t = new EraTimeline([
            ['type' => 'opening', 'date' => '1900-01-01', 'source' => 'lignes-oubliees'],
            ['type' => 'closure_passenger', 'date' => '1938-05-15', 'source' => 'lignes-oubliees'],
            ['type' => 'closure', 'date' => '1950-01-01', 'source' => 'wikipedia-reseaux'],
        ]);

        $this->assertSame(['status' => 'in_service', 'traffic' => 'freight'], $t->stateAt(1940));
        $this->assertSame('abandoned', $t->stateAt(1950)['status']);
    }

    public function test_later_total_closure_from_a_weaker_source_ends_a_passenger_only_period(): void
    {
        // 3606 Beaurieux - Pontavert : la meilleure source ne date que la fin du fret (1962) ;
        // la fin du réseau (1966, source moins bien classée) clôt la portion au lieu de la
        // laisser « mixte » jusqu'à aujourd'hui.
        $t = new EraTimeline([
            ['type' => 'opening', 'date' => '1921-01-01', 'source' => 'wikipedia-reseaux', 'whole' => true],
            ['type' => 'closure_freight', 'date' => '1962-07-01', 'source' => 'wikipedia-reseaux', 'whole' => true],
            ['type' => 'closure', 'date' => '1966-01-01', 'source' => 'estimation-reseau', 'whole' => true],
        ]);

        $this->assertSame(['status' => 'in_service', 'traffic' => 'mixed'], $t->stateAt(1960));
        $this->assertSame('abandoned', $t->stateAt(1970)['status']);
        $this->assertNotSame('in_service', $t->stateAt(2020)['status'] ?? null);
    }

    public function test_total_closure_close_to_a_freight_closure_is_the_same_event(): void
    {
        // Fin du fret 1962 (meilleure source), fermeture « totale » 1964 d'une source moins
        // fiable : même fin, en moins précis (DEDUPE_WINDOW_YEARS) ; la règle ne l'ajoute pas.
        $t = new EraTimeline([
            ['type' => 'opening', 'date' => '1921-01-01', 'source' => 'lignes-oubliees'],
            ['type' => 'closure_freight', 'date' => '1962-07-01', 'source' => 'lignes-oubliees'],
            ['type' => 'closure', 'date' => '1964-01-01', 'source' => 'wikipedia-reseaux'],
        ]);

        $this->assertSame('in_service', $t->stateAt(1970)['status']);
    }

    public function test_earlier_total_closure_from_a_weaker_source_is_ignored(): void
    {
        // Fermeture « totale » de 1937 face à la fermeture aux voyageurs de 1938 : même
        // événement, moins précis. La portion reste ouverte au fret.
        $t = new EraTimeline([
            ['type' => 'opening', 'date' => '1900-01-01', 'source' => 'lignes-oubliees'],
            ['type' => 'closure_passenger', 'date' => '1938-05-15', 'source' => 'lignes-oubliees'],
            ['type' => 'closure', 'date' => '1937-01-01', 'source' => 'wikipedia-reseaux'],
        ]);

        $this->assertSame(['status' => 'in_service', 'traffic' => 'freight'], $t->stateAt(1950));
    }

    public function test_much_later_opening_from_a_better_source_does_not_hide_an_earlier_one(): void
    {
        // Paris - Brest : OHM date Laval - Rennes de 1857 ; le texte Wikipédia donne 1965
        // (mise en service de l'électrification, mal typée en ouverture).
        $t = new EraTimeline([
            ['type' => 'opening', 'date' => '1857-05-01', 'source' => 'ohm'],
            ['type' => 'opening', 'date' => '1965-07-01', 'source' => 'wikipedia'],
        ]);
        // Écart de quelques années : la source la plus fiable garde la main.
        $close = new EraTimeline([
            ['type' => 'opening', 'date' => '1850-01-01', 'source' => 'ohm'],
            ['type' => 'opening', 'date' => '1860-01-01', 'source' => 'wikipedia'],
        ]);

        // Saint-Étienne - Lyon : Givors - Rive-de-Gier 1830 (OHM), « tracé définitif » 1857.
        $rebuilt = new EraTimeline([
            ['type' => 'opening', 'date' => '1830-06-28', 'source' => 'ohm'],
            ['type' => 'opening', 'date' => '1857-11-24', 'source' => 'wikipedia'],
        ]);

        $this->assertSame('in_service', $t->stateAt(1860)['status']);
        $this->assertSame('in_service', $rebuilt->stateAt(1830)['status']);
        $this->assertNull($close->stateAt(1855));
        $this->assertSame('in_service', $close->stateAt(1860)['status']);
    }

    public function test_high_speed_line_events_before_1981_are_out_of_scope(): void
    {
        // Way OHM d'une ligne classique (1866) sous la tolérance d'une LGV ou d'un raccordement LGV.
        $this->assertTrue(EraTimeline::predatesHighSpeed('opening', '1866-01-01'));
        $this->assertFalse(EraTimeline::predatesHighSpeed('opening', '1981-09-27'));
        $this->assertFalse(EraTimeline::predatesHighSpeed('concession', '1970-01-01'));
    }

    public function test_line_opened_in_stages_without_portion_date_keeps_the_earliest_whole_opening(): void
    {
        // Infobox : « mise en service 1856 » (première section), « 1871 » (achèvement) ; rien
        // ne date CETTE portion : règle établie, la plus ancienne (la ligne existait depuis).
        // Les étapes se localisent par les sections résolues ou les portions voisines datées,
        // pas en repoussant la portion à l'achèvement.
        $t = new EraTimeline([
            ['type' => 'opening', 'date' => '1856-01-01', 'source' => 'wikidata', 'whole' => true],
            ['type' => 'opening', 'date' => '1871-01-01', 'source' => 'wikipedia', 'whole' => true],
        ]);

        $this->assertSame('in_service', $t->stateAt(1860)['status']);
        // L'achèvement ne sert plus que de borne haute à une ouverture déduite d'une voisine.
        $this->assertSame('1871-01-01', EraTimeline::completionOpening([
            ['type' => 'opening', 'date' => '1856-01-01'], ['type' => 'opening', 'date' => '1871-01-01'],
        ])['date']);
    }

    public function test_recent_sourced_closure_beats_a_possibly_stale_current_status(): void
    {
        // 761000 : fermée aux voyageurs et au fret en mars 2020 (lignes-oubliees, par PK), mais
        // encore « exploitée » dans les formes SNCF : la fermeture s'applique en 2020 et en 2030.
        $events = [
            ['type' => 'opening', 'date' => '1867-09-16', 'source' => 'lignes-oubliees'],
            ['type' => 'closure_passenger', 'date' => '2020-03-01', 'source' => 'lignes-oubliees'],
            ['type' => 'closure_freight', 'date' => '2020-03-01', 'source' => 'lignes-oubliees'],
        ];

        $this->assertTrue(EraTimeline::isRecentSourcedClosure($events[2], 2026));
        $this->assertCount(3, EraTimeline::withoutContradictedPartialClosures($events, 'PF', true, 2026));
        $this->assertSame('abandoned', (new EraTimeline($events))->stateAt(2020)['status']);
        // Ancienne, « toute la ligne » ou de source faible : le statut actuel garde la main.
        $this->assertFalse(EraTimeline::isRecentSourcedClosure(['type' => 'closure', 'date' => '1990-01-01', 'source' => 'lignes-oubliees'], 2026));
        $this->assertFalse(EraTimeline::isRecentSourcedClosure(['type' => 'closure', 'date' => '2020-10-18', 'source' => 'wikipedia', 'whole' => true], 2026));
        $this->assertFalse(EraTimeline::isRecentSourcedClosure(['type' => 'closure', 'date' => '2020-10-18', 'source' => 'wikidata'], 2026));
    }

    public function test_association_without_known_closure_ends_commercial_traffic_when_asked(): void
    {
        // Abreschviller - Grand-Soldat : ligne particulière (fret) de 1884, train touristique en 1968.
        $events = [
            ['type' => 'opening', 'date' => '1884-01-01'],
            ['type' => 'heritage_start', 'date' => '1968-06-01', 'heritage_kind' => 'tourist_train'],
        ];

        $this->assertSame(['status' => 'in_service', 'traffic' => 'unknown', 'heritage' => 'tourist_train'],
            (new EraTimeline($events, 'F'))->heritageEndsCommercialTraffic()->stateAt(1970));
        // Réseau principal encore exploité selon SNCF : le trafic commercial reste.
        $this->assertSame('freight', (new EraTimeline($events, 'F'))->stateAt(1970)['traffic']);
    }

    public function test_greenway_beside_rails_left_in_place_keeps_the_portion_closed_with_its_reuse(): void
    {
        $t = (new EraTimeline([
            ['type' => 'opening', 'date' => '1900-01-01'],
            ['type' => 'closure', 'date' => '1990-01-01'],
            ['type' => 'conversion', 'date' => '2005-01-01', 'reuse' => 'greenway'],
        ]))->withPresentState('track_in_place');

        $this->assertSame(['status' => 'abandoned', 'traffic' => 'unknown', 'reuse' => 'greenway'], $t->stateAt(2010));
    }

    public function test_whole_line_opening_long_after_the_first_is_not_a_completion(): void
    {
        // 1880 puis 1947 (prolongement ou réouverture mal typée) : la portion existe depuis 1880.
        $t = new EraTimeline([
            ['type' => 'opening', 'date' => '1880-01-01', 'source' => 'wikidata', 'whole' => true],
            ['type' => 'opening', 'date' => '1947-01-01', 'source' => 'wikipedia', 'whole' => true],
        ]);

        $this->assertSame('in_service', $t->stateAt(1890)['status']);
    }

    public function test_dated_removal_shows_only_once_with_the_current_era(): void
    {
        $t = new EraTimeline([
            ['type' => 'opening', 'date' => '1900-01-01'],
            ['type' => 'closure', 'date' => '2010-06-01'],
            ['type' => 'dismantling', 'date' => '2017-01-01'],
        ]);

        $this->assertSame('dismantled', $t->stateAt(2020)['status']);
        // Ère courante (état 2026) : l'ère précédente est 2020, qui montrait déjà la dépose.
        $this->assertNull($t->stateAt(2026, isCurrent: true, previousYear: 2020));
    }

    public function test_undated_removal_shows_only_once_with_the_current_era(): void
    {
        $closedIn = fn (string $date) => (new EraTimeline([
            ['type' => 'opening', 'date' => '1900-01-01'],
            ['type' => 'closure', 'date' => $date],
        ]))->withPresentState('greenway');

        // Fermée en 2008 : fermée en 2010, déposée en 2020, plus rien en 2030.
        $this->assertSame('abandoned', $closedIn('2008-01-01')->stateAt(2010)['status']);
        $this->assertSame('dismantled', $closedIn('2008-01-01')->stateAt(2020)['status']);
        $this->assertNull($closedIn('2008-01-01')->stateAt(2026, isCurrent: true, previousYear: 2020));
        // Fermée en 2015 : fermée en 2020, déposée sur la carte actuelle.
        $this->assertSame('abandoned', $closedIn('2015-01-01')->stateAt(2020)['status']);
        $this->assertSame('dismantled', $closedIn('2015-01-01')->stateAt(2026, isCurrent: true, previousYear: 2020)['status']);
    }

    public function test_whole_line_opening_is_a_reopening_only_after_a_closure(): void
    {
        $reopened = EraTimeline::wholeOpeningsAsReopenings([
            ['type' => 'opening', 'date' => '1880-01-01', 'whole' => true],
            ['type' => 'closure', 'date' => '1939-09-01', 'whole' => true],
            ['type' => 'opening', 'date' => '1947-01-01', 'whole' => true],
        ]);
        $completed = EraTimeline::wholeOpeningsAsReopenings([
            ['type' => 'opening', 'date' => '1856-01-01', 'whole' => true],
            ['type' => 'opening', 'date' => '1871-01-01', 'whole' => true],
        ]);

        $this->assertSame('reopening', $reopened[2]['type']);
        $this->assertSame('opening', $completed[1]['type']);
    }

    public function test_partial_closures_contradicted_by_current_operation_are_dropped(): void
    {
        $events = [
            ['type' => 'opening', 'date' => '1900-01-01', 'source' => 'lignes-oubliees'],
            ['type' => 'closure_passenger', 'date' => '1970-01-01', 'source' => 'lignes-oubliees'],
            ['type' => 'closure_freight', 'date' => '2015-01-01', 'source' => 'lignes-oubliees'],
        ];

        // Exploitée aujourd'hui selon SNCF : la fermeture au fret de 2015 est contredite,
        // celle aux voyageurs de 1970 reste (fret seul), sans « clignotement » en 2020.
        $kept = EraTimeline::withoutContradictedPartialClosures($events, 'PF', true, 2026);
        $this->assertCount(2, $kept);
        $this->assertSame(['status' => 'in_service', 'traffic' => 'freight'], (new EraTimeline($kept))->stateAt(2020));
    }

    public function test_removal_proven_today_is_not_placed_before_a_later_reopening(): void
    {
        // Pontorson - Moidrey (id 3944) : ouverte en 1872, exploitation arrêtée en 1886, plateforme
        // reprise par le tramway du Mont-Saint-Michel en 1901, fermé en 1938, disparu aujourd'hui.
        // La disparition observée aujourd'hui suit la DERNIÈRE fermeture : la voie n'est pas
        // « déposée » en 1900 pour revenir « en service » en 1910.
        $t = (new EraTimeline([
            ['type' => 'opening', 'date' => '1872-10-10', 'source' => 'ohm'],
            ['type' => 'closure', 'date' => '1886-06-30', 'source' => 'ohm'],
            ['type' => 'reopening', 'date' => '1901-07-29', 'source' => 'ohm'],
            ['type' => 'closure', 'date' => '1938-06-01', 'source' => 'ohm'],
        ]))->withPresentState(null, goneByDefault: true);

        $this->assertSame('in_service', $t->stateAt(1880)['status']);
        $this->assertSame(['status' => 'abandoned', 'traffic' => 'unknown'], $t->stateAt(1890));
        $this->assertSame(['status' => 'abandoned', 'traffic' => 'unknown'], $t->stateAt(1900));
        $this->assertSame('in_service', $t->stateAt(1910)['status']);
        $this->assertSame('abandoned', $t->stateAt(1940)['status']);
        $this->assertSame('dismantled', $t->stateAt(1950)['status']);
        $this->assertNull($t->stateAt(1960));
    }

    /**
     * @return array<string, string>
     */
    private function state(EraTimeline $timeline, int $year, bool $isCurrent = false, ?int $previousYear = null): array
    {
        return $timeline->stateAt($year, $isCurrent, $previousYear) ?? [];
    }
}
