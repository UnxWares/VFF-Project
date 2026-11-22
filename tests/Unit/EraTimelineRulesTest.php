<?php

namespace Tests\Unit;

use App\Console\Commands\BuildEraSegments;
use App\Services\ConsistencyChecker;
use App\Services\EraTimeline;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClassConstant;

/**
 * Règles métier de la carte décennale, telles que données par l'utilisateur,
 * vérifiées sur le moteur EraTimeline (logique pure, sans base) :
 *   - ère Y = état au 31/12/Y ;
 *   - voyageurs implique mixte, sauf LGV (voyageurs seuls) ;
 *   - services par défaut selon la catégorie de réseau ;
 *   - voie déposée visible dans la seule décennie qui suit sa dépose ;
 *   - patrimoine : train touristique / musée en service, vélorail fermé ;
 *   - priorité des sources, précision de la portion avant fiabilité ;
 *   - ouvertures « toute la ligne ».
 * Complète EraTimelineTest (cas réels) par des tables de cas et des propriétés
 * vérifiées sur de nombreuses chronologies.
 */
class EraTimelineRulesTest extends TestCase
{
    /** Ères de la carte, de 1830 à 2030 (ère courante). */
    private const ERAS = [1830, 1840, 1850, 1860, 1870, 1880, 1890, 1900, 1910, 1920, 1930, 1940, 1950, 1960, 1970, 1980, 1990, 2000, 2010, 2020, 2030];

    private static function ev(string $type, string $date, array $extra = []): array
    {
        return ['type' => $type, 'date' => $date] + $extra;
    }

    // ─── Instantané au 31 décembre ───────────────────────────────────────

    /** @return array<string, array{0: string, 1: int, 2: string}> */
    public static function snapshotProvider(): array
    {
        return [
            'fermée le 31/12/1980 : fermée dans l\'ère 1980' => ['1980-12-31', 1980, 'abandoned'],
            'fermée le 01/01/1981 : encore ouverte dans l\'ère 1980' => ['1981-01-01', 1980, 'in_service'],
            'fermée en 1980 (année seule) : fermée dans l\'ère 1980' => ['1980-01-01', 1980, 'abandoned'],
            'fermée le 15/06/1980 : fermée dans l\'ère 1980' => ['1980-06-15', 1980, 'abandoned'],
            'fermée en 1979 : fermée dans l\'ère 1980' => ['1979-03-01', 1980, 'abandoned'],
            'fermée le 31/12/1979 : encore ouverte dans l\'ère 1970' => ['1979-12-31', 1970, 'in_service'],
        ];
    }

    #[DataProvider('snapshotProvider')]
    public function test_each_era_shows_the_state_on_december_31st(string $closure, int $era, string $expected): void
    {
        $t = new EraTimeline([self::ev('opening', '1900-01-01'), self::ev('closure', $closure)]);

        $this->assertSame($expected, $t->stateAt($era)['status']);
    }

    public function test_line_opened_on_december_31st_appears_in_that_era(): void
    {
        $t = new EraTimeline([self::ev('opening', '1970-12-31')]);

        $this->assertNull($t->stateAt(1960));
        $this->assertSame('in_service', $t->stateAt(1970)['status']);
    }

    public function test_line_opened_on_january_1st_of_the_next_year_is_absent(): void
    {
        $this->assertNull((new EraTimeline([self::ev('opening', '1971-01-01')]))->stateAt(1970));
    }

    // ─── Ouverte en 1970, fermée en partie en 1980 ───────────────────────

    public function test_partly_closed_line_keeps_only_its_open_part_active_and_reopening_restores_it(): void
    {
        $opening = self::ev('opening', '1970-05-01');
        $closedPart = new EraTimeline([$opening, self::ev('closure', '1980-09-28'), self::ev('reopening', '2005-12-11')]);
        $openPart = new EraTimeline([$opening]);

        foreach ([1970 => ['in_service', 'in_service'], 1980 => ['abandoned', 'in_service'], 1990 => ['abandoned', 'in_service'],
            2000 => ['abandoned', 'in_service'], 2010 => ['in_service', 'in_service']] as $year => [$closed, $open]) {
            $this->assertSame($closed, $closedPart->stateAt($year)['status'], "partie fermée, ère {$year}");
            $this->assertSame($open, $openPart->stateAt($year)['status'], "partie restée ouverte, ère {$year}");
        }
    }

    // ─── Trafic : voyageurs implique mixte, sauf LGV ─────────────────────

    /** @return array<string, array{0: list<array>, 1: string}> */
    public static function trafficProvider(): array
    {
        return [
            'ouverture générique' => [[self::ev('opening', '1900-01-01')], 'mixed'],
            'ouverture aux voyageurs seuls' => [[self::ev('opening_passenger', '1900-01-01')], 'mixed'],
            'ouverture au fret seul' => [[self::ev('opening_freight', '1900-01-01')], 'freight'],
            'fermée au fret, voyageurs maintenus : mixte' => [[self::ev('opening', '1900-01-01'), self::ev('closure_freight', '1980-01-01')], 'mixed'],
            'fermée aux voyageurs : fret seul' => [[self::ev('opening', '1900-01-01'), self::ev('closure_passenger', '1950-01-01')], 'freight'],
            'desserte voyageurs saisonnière sur une ligne de fret : mixte' => [[self::ev('opening_freight', '1900-01-01'), self::ev('opening_passenger', '1985-07-01')], 'mixed'],
            'réouverture partielle aux voyageurs : mixte' => [[self::ev('opening', '1900-01-01'), self::ev('closure_passenger', '1950-01-01'),
                self::ev('reopening_passenger', '1995-06-01')], 'mixed'],
            'réouverture au fret après fermeture totale' => [[self::ev('opening', '1900-01-01'), self::ev('closure', '1950-01-01'),
                self::ev('reopening_freight', '1990-01-01')], 'freight'],
        ];
    }

    #[DataProvider('trafficProvider')]
    public function test_passenger_service_on_a_classic_line_is_mixed(array $events, string $expected): void
    {
        $this->assertSame(['status' => 'in_service', 'traffic' => $expected], (new EraTimeline($events))->stateAt(2000));
    }

    public function test_classic_line_is_never_passenger_only_whatever_its_chronology(): void
    {
        foreach ($this->chronologies() as $label => $events) {
            foreach (self::ERAS as $i => $year) {
                $state = (new EraTimeline($events))->stateAt($year, $year === 2030, $i > 0 ? self::ERAS[$i - 1] : null);
                $this->assertNotSame('passenger', $state['traffic'] ?? null, "{$label}, ère {$year}");
            }
        }
    }

    public function test_high_speed_line_is_passenger_only(): void
    {
        // Comme BuildEraSegments pour une LGV : services par défaut 'P', pas de fret implicite.
        $t = new EraTimeline([self::ev('opening', '1981-09-27')], 'P', passengerImpliesFreight: false);

        $this->assertNull($t->stateAt(1980));
        $this->assertSame(['status' => 'in_service', 'traffic' => 'passenger'], $t->stateAt(1990));
        $this->assertSame(['status' => 'in_service', 'traffic' => 'passenger'], $t->stateAt(2030, true, 2020));
    }

    public function test_no_high_speed_event_before_1981_is_kept(): void
    {
        $this->assertSame(1981, EraTimeline::HIGH_SPEED_FIRST_YEAR);
        foreach (['opening', 'opening_passenger', 'closure', 'dismantling', 'heritage_start'] as $type) {
            $this->assertTrue(EraTimeline::predatesHighSpeed($type, '1980-12-31'), $type);
            $this->assertFalse(EraTimeline::predatesHighSpeed($type, '1981-01-01'), $type);
        }
        // Événements hors chronologie (concession, électrification…) : non concernés.
        $this->assertFalse(EraTimeline::predatesHighSpeed('electrification', '1960-01-01'));
    }

    // ─── Services par défaut selon la catégorie de réseau ───────────────

    public function test_default_services_by_network_category(): void
    {
        $expected = ['principal' => 'PF', 'secondaire' => 'PF', 'particulier' => 'F', 'militaire' => ''];

        // Même table dans le moteur et dans le contrôle de cohérence.
        $this->assertSame($expected, (new ReflectionClassConstant(BuildEraSegments::class, 'DEFAULT_SERVICES'))->getValue());
        $this->assertSame($expected, (new ReflectionClassConstant(ConsistencyChecker::class, 'DEFAULT_SERVICES'))->getValue());

        $traffic = fn (string $services) => (new EraTimeline([self::ev('opening', '1900-01-01')], $services))->stateAt(1910);
        $this->assertSame(['status' => 'in_service', 'traffic' => 'mixed'], $traffic($expected['principal']));
        $this->assertSame(['status' => 'in_service', 'traffic' => 'mixed'], $traffic($expected['secondaire']));
        $this->assertSame(['status' => 'in_service', 'traffic' => 'freight'], $traffic($expected['particulier']));
        $this->assertSame(['status' => 'in_service', 'traffic' => 'unknown'], $traffic($expected['militaire']));
    }

    public function test_explicit_passenger_opening_on_an_industrial_line_is_mixed(): void
    {
        $t = new EraTimeline([self::ev('opening', '1900-01-01'), self::ev('opening_passenger', '1920-01-01')], 'F');

        $this->assertSame('freight', $t->stateAt(1910)['traffic']);
        $this->assertSame('mixed', $t->stateAt(1930)['traffic']);
    }

    // ─── Voie déposée : une seule décennie, puis plus rien ───────────────

    /** @return array<string, array{0: string, 1: array<int, ?string>}> */
    public static function datedRemovalProvider(): array
    {
        // Fermée en 1960, déposée à la date donnée : statut attendu par ère (null = absente).
        return [
            'déposée en 1975' => ['1975-01-01', [1960 => 'abandoned', 1970 => 'abandoned', 1980 => 'dismantled', 1990 => null, 2000 => null]],
            'déposée le 31/12/1970' => ['1970-12-31', [1960 => 'abandoned', 1970 => 'dismantled', 1980 => null]],
            'déposée le 01/01/1971' => ['1971-01-01', [1970 => 'abandoned', 1980 => 'dismantled', 1990 => null]],
            'déposée l\'année de sa fermeture' => ['1960-11-01', [1950 => 'in_service', 1960 => 'dismantled', 1970 => null]],
        ];
    }

    #[DataProvider('datedRemovalProvider')]
    public function test_dated_removal_is_shown_only_in_the_following_decade(string $removal, array $expected): void
    {
        $t = new EraTimeline([self::ev('opening', '1900-01-01'), self::ev('closure', '1960-01-01'), self::ev('dismantling', $removal)]);

        foreach ($expected as $year => $status) {
            $this->assertSame($status, $t->stateAt($year)['status'] ?? null, "ère {$year}");
        }
    }

    public function test_undated_removal_is_placed_right_after_the_closure(): void
    {
        foreach (['dismantled', 'greenway', 'road', 'built_over'] as $present) {
            $t = (new EraTimeline([self::ev('opening', '1900-01-01'), self::ev('closure', '1965-04-01')]))->withPresentState($present);

            $this->assertSame('in_service', $t->stateAt(1960)['status'], $present);
            $this->assertSame('abandoned', $t->stateAt(1970)['status'], "{$present} : fermée dans l'ère de la fermeture");
            $this->assertSame('dismantled', $t->stateAt(1980)['status'], "{$present} : déposée dans l'ère suivante");
            $this->assertNull($t->stateAt(1990), "{$present} : absente ensuite");
            $this->assertNull($t->stateAt(2030, true, 2020), $present);
        }
    }

    public function test_dated_removal_prevents_an_earlier_inferred_removal(): void
    {
        // Fermée en 1965, déposée (datée) en 1994, disparue aujourd'hui : la dépose déduite
        // « juste après la fermeture » (ère 1980) ne doit pas l'emporter sur la date connue.
        $t = (new EraTimeline([self::ev('opening', '1900-01-01'), self::ev('closure', '1965-01-01'), self::ev('dismantling', '1994-01-01')]))
            ->withPresentState('dismantled');

        $this->assertSame('abandoned', $t->stateAt(1970)['status']);
        $this->assertSame('abandoned', $t->stateAt(1980)['status']);
        $this->assertSame('abandoned', $t->stateAt(1990)['status']);
        $this->assertSame('dismantled', $t->stateAt(2000)['status']);
        $this->assertNull($t->stateAt(2010));
    }

    public function test_track_left_in_place_is_never_shown_dismantled(): void
    {
        $t = (new EraTimeline([self::ev('opening', '1900-01-01'), self::ev('closure', '1950-01-01')]))->withPresentState('track_in_place', goneByDefault: true);

        foreach ([1950, 1960, 1990, 2020] as $year) {
            $this->assertSame('abandoned', $t->stateAt($year)['status'], "ère {$year}");
        }
        $this->assertSame('abandoned', $t->stateAt(2030, true, 2020)['status']);
    }

    /**
     * Voie verte construite À CÔTÉ de la voie restée en place : la reconversion datée ne
     * prouve pas la dépose quand des rails sont observés aujourd'hui.
     */
    public function test_greenway_built_beside_a_track_left_in_place_does_not_dismantle_it(): void
    {
        $t = (new EraTimeline([
            self::ev('opening', '1900-01-01'),
            self::ev('closure', '1990-01-01'),
            self::ev('conversion', '2005-01-01', ['reuse' => 'greenway', 'source' => 'lignes-oubliees']),
        ]))->withPresentState('track_in_place');

        // Rails observés en place : la reconversion laisse la portion « fermée » (avec sa
        // réutilisation), jamais « déposée » puis effacée.
        $this->assertSame('abandoned', $t->stateAt(2010)['status']);
        $this->assertSame('abandoned', $t->stateAt(2030, true, 2020)['status'] ?? null);
    }

    public function test_dismantled_portion_is_never_shown_dismantled_two_eras_in_a_row(): void
    {
        foreach ($this->chronologies() as $label => $events) {
            foreach ([null, 'dismantled', 'greenway', 'track_in_place'] as $present) {
                $t = (new EraTimeline($events))->withPresentState($present, goneByDefault: true);
                $previous = null;
                foreach (self::ERAS as $i => $year) {
                    $state = $t->stateAt($year, $year === 2030, $i > 0 ? self::ERAS[$i - 1] : null);
                    $status = $state['status'] ?? null;
                    $this->assertFalse($status === 'dismantled' && $previous === 'dismantled',
                        "{$label} (état actuel ".($present ?? 'inconnu').'), déposée en '.(self::ERAS[$i - 1] ?? '?')." et en {$year}");
                    $previous = $status;
                }
            }
        }
    }

    public function test_vanished_portion_never_reappears_without_a_reopening(): void
    {
        foreach ($this->chronologies() as $label => $events) {
            if (array_filter($events, fn ($e) => str_starts_with($e['type'], 'reopening') || $e['type'] === 'heritage_start')) {
                continue;
            }
            $t = (new EraTimeline($events))->withPresentState('dismantled');
            $seen = false;
            $gone = false;
            foreach (self::ERAS as $i => $year) {
                $state = $t->stateAt($year, $year === 2030, $i > 0 ? self::ERAS[$i - 1] : null);
                if ($state !== null && $gone) {
                    $this->fail("{$label} : réapparaît en {$year} après avoir disparu");
                }
                $gone = $gone || ($seen && $state === null);
                $seen = $seen || $state !== null;
            }
        }
        $this->addToAssertionCount(1);
    }

    // ─── Patrimoine ──────────────────────────────────────────────────────

    /** @return array<string, array{0: string, 1: string}> */
    public static function heritageProvider(): array
    {
        return [
            'train touristique : en service' => ['tourist_train', 'in_service'],
            'musée : en service' => ['museum', 'in_service'],
            'vélorail : fermée (pas de train)' => ['velorail', 'abandoned'],
        ];
    }

    #[DataProvider('heritageProvider')]
    public function test_heritage_operation_after_commercial_closure(string $kind, string $status): void
    {
        $t = new EraTimeline([
            self::ev('opening', '1890-01-01'),
            self::ev('closure', '1955-01-01'),
            self::ev('heritage_start', '1985-05-01', ['heritage_kind' => $kind]),
        ]);

        $this->assertSame('abandoned', $t->stateAt(1960)['status']);
        $this->assertSame(['status' => $status, 'traffic' => 'unknown', 'heritage' => $kind], $t->stateAt(1990));
        $this->assertSame($kind, $this->state($t, 2030, true, 2020)['heritage']);
    }

    public function test_heritage_kind_defaults_to_tourist_train(): void
    {
        $t = new EraTimeline([self::ev('opening', '1890-01-01'), self::ev('closure', '1955-01-01'), self::ev('heritage_start', '1985-01-01')]);

        $this->assertSame('tourist_train', $this->state($t, 1990)['heritage']);
    }

    public function test_end_of_heritage_operation_closes_the_portion_again(): void
    {
        $t = new EraTimeline([
            self::ev('opening', '1890-01-01'),
            self::ev('closure', '1955-01-01'),
            self::ev('heritage_start', '1985-01-01', ['heritage_kind' => 'tourist_train']),
            self::ev('heritage_end', '2012-10-01'),
        ]);

        $this->assertSame('tourist_train', $this->state($t, 2010)['heritage']);
        $this->assertSame(['status' => 'abandoned', 'traffic' => 'unknown'], $t->stateAt(2020));
    }

    public function test_commercial_traffic_wins_over_a_heritage_operation_on_the_same_portion(): void
    {
        // Train touristique sur une ligne encore ouverte au fret : le trafic commercial reste.
        $t = new EraTimeline([
            self::ev('opening', '1890-01-01'),
            self::ev('closure_passenger', '1950-01-01'),
            self::ev('heritage_start', '1990-01-01', ['heritage_kind' => 'tourist_train']),
        ]);

        $this->assertSame(['status' => 'in_service', 'traffic' => 'freight'], $t->stateAt(2000));
    }

    // ─── Priorité des sources ────────────────────────────────────────────

    public function test_source_priority_order(): void
    {
        $order = ['manual', 'contrib', 'lignes-oubliees', 'sncf', 'wikipedia', 'ohm', 'lignes-oubliees-pdf', 'wikidata', 'estimation-reseau'];
        $priority = EraTimeline::SOURCE_PRIORITY;

        foreach ($order as $source) {
            $this->assertArrayHasKey($source, $priority);
        }
        $this->assertSame(0, $priority['manual']);
        for ($i = 1; $i < count($order); $i++) {
            $this->assertLessThan($priority[$order[$i]], $priority[$order[$i - 1]], "{$order[$i - 1]} avant {$order[$i]}");
        }
        // Une source inconnue passe après toutes les autres.
        $this->assertGreaterThan(max($priority), (new \ReflectionMethod(EraTimeline::class, 'priority'))->invoke(null, 'blog-inconnu'));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function sourcePairProvider(): array
    {
        $order = ['manual', 'contrib', 'lignes-oubliees', 'sncf', 'wikipedia', 'ohm', 'lignes-oubliees-pdf', 'wikidata'];
        $pairs = [];
        for ($i = 1; $i < count($order); $i++) {
            $pairs["{$order[$i - 1]} avant {$order[$i]}"] = [$order[$i - 1], $order[$i]];
        }

        return $pairs;
    }

    #[DataProvider('sourcePairProvider')]
    public function test_more_reliable_source_wins_for_the_same_event(string $better, string $worse): void
    {
        // Même ouverture à 2 ans d'écart, à cheval sur deux décennies.
        $early = new EraTimeline([self::ev('opening', '1858-06-01', ['source' => $worse]), self::ev('opening', '1860-06-01', ['source' => $better])]);
        $late = new EraTimeline([self::ev('opening', '1860-06-01', ['source' => $worse]), self::ev('opening', '1858-06-01', ['source' => $better])]);

        $this->assertNull($early->stateAt(1859), "{$better} (1860) l'emporte sur {$worse} (1858)");
        $this->assertNotNull($late->stateAt(1859), "{$better} (1858) l'emporte sur {$worse} (1860)");

        // Idem pour une fermeture.
        $closure = new EraTimeline([self::ev('opening', '1858-06-01', ['source' => $better]),
            self::ev('closure', '1948-01-01', ['source' => $worse]), self::ev('closure', '1951-01-01', ['source' => $better])]);
        $this->assertSame('in_service', $closure->stateAt(1950)['status']);
        $this->assertSame('abandoned', $closure->stateAt(1960)['status']);
    }

    public function test_precise_portion_beats_whole_line_even_from_a_less_reliable_source(): void
    {
        // « Toute la ligne » saisie à la main (manual) face à une portion datée par Wikidata :
        // la précision de la portion passe avant la fiabilité de la source.
        $t = new EraTimeline([
            self::ev('closure', '1938-01-01', ['source' => 'manual', 'whole' => true]),
            self::ev('opening', '1880-01-01', ['source' => 'wikidata', 'whole' => false]),
            self::ev('closure', '1952-01-01', ['source' => 'wikidata', 'whole' => false]),
        ]);

        $this->assertSame('in_service', $t->stateAt(1940)['status']);
        $this->assertSame('abandoned', $t->stateAt(1960)['status']);
    }

    // ─── Ouvertures « toute la ligne » ───────────────────────────────────

    public function test_whole_line_opening_does_not_reopen_a_portion_opened_then_closed(): void
    {
        $t = new EraTimeline([
            self::ev('opening', '1880-01-01', ['source' => 'ohm', 'whole' => false]),
            self::ev('closure', '1930-01-01', ['source' => 'ohm', 'whole' => false]),
            self::ev('opening', '1950-01-01', ['source' => 'wikipedia', 'whole' => true]),
        ]);

        $this->assertSame('in_service', $t->stateAt(1920)['status']);
        $this->assertSame('abandoned', $t->stateAt(1960)['status']);
        $this->assertSame('abandoned', $t->stateAt(2030, true, 2020)['status']);
    }

    public function test_single_whole_line_opening_dates_a_portion_without_its_own(): void
    {
        $t = new EraTimeline([self::ev('opening', '1872-01-01', ['source' => 'wikidata', 'whole' => true])]);

        $this->assertNull($t->stateAt(1870));
        $this->assertSame('in_service', $t->stateAt(1880)['status']);
    }

    /**
     * Règle donnée par l'utilisateur : faute d'ouverture propre à la portion, on retient
     * l'ouverture « toute la ligne » la plus ANCIENNE (EraTimeline::wholeLineOpening), même
     * avec deux dates du MÊME événement à 2 ans d'écart : la portion apparaît en 1860.
     */
    public function test_earliest_whole_line_opening_is_kept_without_a_portion_opening(): void
    {
        $t = new EraTimeline([
            self::ev('opening', '1859-08-01', ['source' => 'wikidata', 'whole' => true]),
            self::ev('opening', '1861-05-01', ['source' => 'wikipedia', 'whole' => true]),
        ]);

        $this->assertSame('in_service', $t->stateAt(1860)['status'] ?? null);
    }

    public function test_whole_line_opening_after_a_closure_becomes_a_reopening_only_when_flagged(): void
    {
        $events = EraTimeline::wholeOpeningsAsReopenings([
            self::ev('opening', '1880-01-01', ['whole' => true]),
            self::ev('closure', '1939-09-01', ['whole' => true]),
            self::ev('opening_passenger', '1947-01-01', ['whole' => true]),
        ]);

        $this->assertSame('reopening_passenger', $events[2]['type']);
        $this->assertSame('mixed', (new EraTimeline($events))->stateAt(1950)['traffic']);
        // Une ouverture propre à la portion n'est jamais convertie.
        $own = EraTimeline::wholeOpeningsAsReopenings([
            self::ev('opening', '1880-01-01', ['whole' => false]),
            self::ev('closure', '1939-09-01', ['whole' => false]),
            self::ev('opening', '1947-01-01', ['whole' => false]),
        ]);
        $this->assertSame('opening', $own[2]['type']);
    }

    // ─── Robustesse ──────────────────────────────────────────────────────

    public function test_unknown_event_types_are_ignored(): void
    {
        $t = new EraTimeline([self::ev('electrification', '1950-01-01'), self::ev('concession', '1850-01-01'), self::ev('opening', '1900-01-01')]);

        $this->assertSame(['status' => 'in_service', 'traffic' => 'mixed'], $t->stateAt(1960));
        $this->assertFalse((new EraTimeline([self::ev('electrification', '1950-01-01')]))->isDated());
        $this->assertNull((new EraTimeline([]))->stateAt(2000));
    }

    public function test_events_are_replayed_in_date_order_whatever_the_input_order(): void
    {
        $events = [self::ev('dismantling', '1975-01-01'), self::ev('closure', '1960-01-01'), self::ev('opening', '1900-01-01')];

        foreach ([$events, array_reverse($events)] as $input) {
            $t = new EraTimeline($input);
            $this->assertSame('in_service', $t->stateAt(1950)['status']);
            $this->assertSame('abandoned', $t->stateAt(1970)['status']);
            $this->assertSame('dismantled', $t->stateAt(1980)['status']);
        }
    }

    // ─── Fermetures par service : la meilleure source ne dit pas tout ────
    //
    // Règle (EraTimeline::serviceClosuresNotCovered) : fermer aux voyageurs, au fret ou à tout
    // trafic sont des faits distincts. Une source qui ne date que la fermeture totale ou au fret
    // d'une section ne contredit pas la fermeture aux voyageurs, souvent bien antérieure, donnée
    // par une autre source ou pour toute la ligne : la portion est « fret » entre les deux, plus
    // « mixte » (cas signalés 643000 et 413000).

    /** 643000 Port-Sainte-Marie - Riscle, portion Feugarolles - Condom (événements réels). */
    private static function portSainteMarieFeugarollesCondom(): array
    {
        return [
            self::ev('opening', '1880-01-01', ['source' => 'wikidata', 'whole' => true]),
            self::ev('opening', '1880-06-06', ['source' => 'wikipedia', 'whole' => false]),
            self::ev('closure_passenger', '1940-06-27', ['source' => 'wikipedia', 'whole' => true]),
            self::ev('closure', '2022-07-13', ['source' => 'lignes-oubliees-pdf', 'whole' => false]),
        ];
    }

    public function test_643000_whole_line_passenger_closure_is_not_hidden_by_a_later_local_total_closure(): void
    {
        $t = new EraTimeline(self::portSainteMarieFeugarollesCondom());

        $this->assertSame(['status' => 'in_service', 'traffic' => 'mixed'], $t->stateAt(1930));
        foreach ([1940, 1950, 1980, 2000, 2020] as $year) {
            $this->assertSame(['status' => 'in_service', 'traffic' => 'freight'], $t->stateAt($year), "ère {$year}");
        }
        $this->assertSame('abandoned', $t->stateAt(2030, true, 2020, '2026-10-19')['status']);
    }

    public function test_643000_whole_line_passenger_closure_is_not_hidden_by_a_local_freight_closure(): void
    {
        // Portion Eauze - Riscle : fermée aux voyageurs en 1940 (toute la ligne), au fret en 2007.
        $t = new EraTimeline([
            self::ev('opening', '1880-01-01', ['source' => 'wikidata', 'whole' => true]),
            self::ev('closure_passenger', '1940-06-27', ['source' => 'wikipedia', 'whole' => true]),
            self::ev('closure_freight', '2007-01-01', ['source' => 'wikipedia', 'whole' => false]),
        ]);

        $this->assertSame(['status' => 'in_service', 'traffic' => 'freight'], $t->stateAt(2000));
        $this->assertSame('abandoned', $t->stateAt(2010)['status'], 'plus aucun trafic après 2007 (et non « mixte » à jamais)');
    }

    public function test_413000_passenger_closure_known_by_year_only_with_low_confidence_still_counts(): void
    {
        // Caen - Vire, portion Louvigny - Aunay-Saint-Georges : « fermée aux voyageurs en 1938 »
        // (année seule, confiance basse) ; fermée en 1972, déclassée en 1975.
        $t = new EraTimeline([
            self::ev('opening', '1886-08-22', ['source' => 'wikipedia', 'whole' => false]),
            self::ev('closure_passenger', '1938-01-01', ['source' => 'wikipedia', 'whole' => true, 'confidence' => 'low', 'date_precision' => 'year']),
            self::ev('closure', '1972-04-04', ['source' => 'wikipedia', 'whole' => false]),
            self::ev('declassification', '1975-02-24', ['source' => 'lignes-oubliees-pdf', 'whole' => false]),
        ]);

        $this->assertSame('mixed', $t->stateAt(1930)['traffic']);
        foreach ([1940, 1950, 1960] as $year) {
            $this->assertSame(['status' => 'in_service', 'traffic' => 'freight'], $t->stateAt($year), "ère {$year}");
        }
        $this->assertSame('abandoned', $t->stateAt(1980)['status']);
    }

    public function test_year_only_passenger_closure_applies_from_the_era_of_its_year(): void
    {
        $events = fn (string $date) => [
            self::ev('opening', '1900-05-01', ['source' => 'lignes-oubliees']),
            self::ev('closure_passenger', $date, ['source' => 'wikipedia', 'whole' => true]),
            self::ev('closure', '1975-01-01', ['source' => 'lignes-oubliees']),
        ];

        // « en 1940 » (01/01/1940) : ère 1940 ; « en 1941 » : encore mixte au 31/12/1940.
        $this->assertSame('freight', (new EraTimeline($events('1940-01-01')))->stateAt(1940)['traffic']);
        $this->assertSame('mixed', (new EraTimeline($events('1941-01-01')))->stateAt(1940)['traffic']);
    }

    /** @return array<string, array{0: string, 1: string, 2: bool, 3: string, 4: bool}> */
    public static function maskingProvider(): array
    {
        $cases = [];
        foreach (['closure', 'closure_freight', 'declassification'] as $other) {
            foreach (['manual', 'lignes-oubliees', 'wikipedia', 'ohm', 'lignes-oubliees-pdf'] as $otherSource) {
                foreach ([['wikipedia', true], ['wikidata', true], ['ohm', false], ['estimation-reseau', false]] as [$cpSource, $cpWhole]) {
                    $cases["{$other} {$otherSource} / voyageurs {$cpSource}".($cpWhole ? ' (toute la ligne)' : '')] = [$other, $otherSource, false, $cpSource, $cpWhole];
                }
            }
        }

        return $cases;
    }

    /**
     * Propriété : une fermeture aux voyageurs qu'aucune autre fermeture aux voyageurs ne
     * contredit et qu'aucune réouverture ne suit rend la portion « fret » (ou fermée) après
     * elle, quelles que soient la source et la portée de la fermeture plus tardive.
     */
    #[DataProvider('maskingProvider')]
    public function test_uncontradicted_passenger_closure_is_never_masked(string $other, string $otherSource, bool $otherWhole, string $cpSource, bool $cpWhole): void
    {
        $t = new EraTimeline([
            self::ev('opening', '1880-01-01', ['source' => 'lignes-oubliees']),
            self::ev('closure_passenger', '1938-05-15', ['source' => $cpSource, 'whole' => $cpWhole]),
            self::ev($other, '1985-01-01', ['source' => $otherSource, 'whole' => $otherWhole]),
        ]);

        foreach ([1940, 1950, 1960, 1970, 1980] as $year) {
            $this->assertSame(['status' => 'in_service', 'traffic' => 'freight'], $t->stateAt($year), "ère {$year}");
        }
        $this->assertNotSame('mixed', $t->stateAt(1990)['traffic'] ?? null);
    }

    public function test_conflicting_passenger_closures_keep_the_most_reliable_source(): void
    {
        // 705000 Commentry - Moulins : Wikipédia « 1969 » (année seule), lignes-oubliees le 06/03/1972.
        $t = new EraTimeline([
            self::ev('opening', '1859-11-07', ['source' => 'lignes-oubliees']),
            self::ev('closure_passenger', '1969-01-01', ['source' => 'wikipedia']),
            self::ev('closure_passenger', '1972-03-06', ['source' => 'lignes-oubliees']),
            self::ev('closure_freight', '2005-01-01', ['source' => 'lignes-oubliees']),
        ]);

        $this->assertSame('mixed', $t->stateAt(1970)['traffic'], 'la source la plus fiable date la fin des voyageurs');
        $this->assertSame('freight', $t->stateAt(1980)['traffic']);
        $this->assertSame('abandoned', $t->stateAt(2010)['status']);
    }

    public function test_localized_passenger_closure_beats_a_whole_line_one_from_a_better_source(): void
    {
        // Portion datée (OHM, 1950) contre « toute la ligne » (manual, 1938) : la portion l'emporte.
        $t = new EraTimeline([
            self::ev('opening', '1880-01-01', ['source' => 'lignes-oubliees']),
            self::ev('closure_passenger', '1938-01-01', ['source' => 'manual', 'whole' => true]),
            self::ev('closure_passenger', '1950-06-01', ['source' => 'ohm', 'whole' => false]),
            self::ev('closure', '1990-01-01', ['source' => 'lignes-oubliees', 'whole' => false]),
        ]);

        $this->assertSame('mixed', $t->stateAt(1940)['traffic']);
        $this->assertSame('freight', $t->stateAt(1950)['traffic']);
    }

    public function test_passenger_closure_after_the_best_source_total_closure_is_another_story(): void
    {
        // Fermée en 1950 puis rouverte en 1955 (meilleure source) ; « fermée aux voyageurs en
        // 1960 » d'une source moins fiable : après la fermeture totale, elle ne concerne pas ce cycle.
        $t = new EraTimeline([
            self::ev('opening', '1880-01-01', ['source' => 'lignes-oubliees']),
            self::ev('closure', '1950-01-01', ['source' => 'lignes-oubliees']),
            self::ev('reopening', '1955-01-01', ['source' => 'lignes-oubliees']),
            self::ev('closure_passenger', '1960-01-01', ['source' => 'wikipedia', 'whole' => true]),
        ]);

        $this->assertSame('abandoned', $t->stateAt(1950)['status']);
        $this->assertSame('mixed', $t->stateAt(1960)['traffic']);
    }

    public function test_passenger_reopening_after_a_masked_closure_restores_mixed_traffic(): void
    {
        $t = new EraTimeline([
            self::ev('opening', '1880-01-01', ['source' => 'lignes-oubliees']),
            self::ev('closure_passenger', '1938-01-01', ['source' => 'wikipedia', 'whole' => true]),
            self::ev('reopening_passenger', '1990-06-01', ['source' => 'wikipedia']),
            self::ev('closure_freight', '1975-01-01', ['source' => 'lignes-oubliees']),
        ]);

        $this->assertSame('freight', $t->stateAt(1960)['traffic']);
        $this->assertSame('abandoned', $t->stateAt(1980)['status'], 'ni voyageurs (1938) ni fret (1975)');
        $this->assertSame(['status' => 'in_service', 'traffic' => 'mixed'], $t->stateAt(1990), 'rouverte aux voyageurs : mixte');
    }

    public function test_every_passenger_closure_of_the_other_source_is_kept_across_a_reopening(): void
    {
        // Deux fins des voyageurs (1938, puis 1960 après la réouverture de 1945), toutes deux
        // d'une source qui n'est pas la meilleure des fermetures (fermeture totale de 1980).
        $t = new EraTimeline([
            self::ev('opening', '1880-01-01', ['source' => 'lignes-oubliees']),
            self::ev('closure_passenger', '1938-05-15', ['source' => 'wikipedia', 'whole' => true]),
            self::ev('reopening_passenger', '1945-06-01', ['source' => 'wikipedia']),
            self::ev('closure_passenger', '1960-09-01', ['source' => 'wikipedia', 'whole' => true]),
            self::ev('closure', '1980-01-01', ['source' => 'lignes-oubliees']),
        ]);

        $this->assertSame('freight', $t->stateAt(1940)['traffic']);
        $this->assertSame('mixed', $t->stateAt(1950)['traffic']);
        $this->assertSame('freight', $t->stateAt(1960)['traffic'], 'seconde fermeture aux voyageurs');
        $this->assertSame('abandoned', $t->stateAt(1980)['status']);
    }

    public function test_freight_closure_of_another_source_ends_the_freight_only_period(): void
    {
        // La meilleure source ne date que la fin des voyageurs : la fermeture au fret d'une autre
        // source termine la période « fret seul » (sinon fret jusqu'en 2020).
        $t = new EraTimeline([
            self::ev('opening', '1880-01-01', ['source' => 'lignes-oubliees']),
            self::ev('closure_passenger', '1938-05-15', ['source' => 'lignes-oubliees']),
            self::ev('closure_freight', '1962-01-01', ['source' => 'wikipedia']),
        ]);

        $this->assertSame('freight', $t->stateAt(1950)['traffic']);
        $this->assertSame('abandoned', $t->stateAt(1970)['status']);
    }

    public function test_total_closure_of_the_best_source_dates_the_end_of_freight(): void
    {
        // Fermeture totale de la meilleure source en 1970 : c'est elle qui dit quand le fret a
        // cessé ; une « fermeture au fret » de 1950 d'une source moins fiable la contredit.
        $t = new EraTimeline([
            self::ev('opening', '1880-01-01', ['source' => 'lignes-oubliees']),
            self::ev('closure_passenger', '1938-05-15', ['source' => 'lignes-oubliees']),
            self::ev('closure', '1970-01-01', ['source' => 'lignes-oubliees']),
            self::ev('closure_freight', '1950-01-01', ['source' => 'wikipedia']),
        ]);

        $this->assertSame('freight', $t->stateAt(1960)['traffic']);
        $this->assertSame('abandoned', $t->stateAt(1970)['status']);
    }

    public function test_dedupe_keeps_the_uncovered_passenger_closure_and_only_the_best_one(): void
    {
        $kept = EraTimeline::dedupe([
            self::ev('closure_passenger', '1938-01-01', ['source' => 'wikidata', 'whole' => true]),
            self::ev('closure_passenger', '1940-06-27', ['source' => 'wikipedia', 'whole' => true]),
            self::ev('closure_freight', '2007-01-01', ['source' => 'wikipedia', 'whole' => false]),
        ]);

        // (Sortie triée par fiabilité puis date ; la fermeture de Wikidata, moins fiable, est écartée.)
        $this->assertSame([['closure_passenger', '1940-06-27'], ['closure_freight', '2007-01-01']],
            array_map(fn ($e) => [$e['type'], $e['date']], $kept));
    }

    public function test_masked_closures_are_still_masked_when_the_best_source_speaks_of_the_service(): void
    {
        // La meilleure source dit déjà quand les voyageurs et le fret ont cessé : rien d'autre.
        $kept = EraTimeline::dedupe([
            self::ev('closure_passenger', '1938-05-15', ['source' => 'lignes-oubliees']),
            self::ev('closure_freight', '1967-01-01', ['source' => 'lignes-oubliees']),
            self::ev('closure_passenger', '1950-01-01', ['source' => 'wikipedia']),
            self::ev('closure_freight', '1960-01-01', ['source' => 'wikipedia']),
        ]);

        $this->assertSame(['lignes-oubliees'], array_values(array_unique(array_column($kept, 'source'))));
    }

    public function test_high_speed_line_closed_to_passengers_by_another_source_has_no_traffic_left(): void
    {
        // LGV (voyageurs seuls) : la fermeture aux voyageurs d'une autre source n'y laisse pas de fret.
        $t = new EraTimeline([
            self::ev('opening', '1990-01-01', ['source' => 'lignes-oubliees']),
            self::ev('closure_passenger', '2010-01-01', ['source' => 'wikipedia', 'whole' => true]),
            self::ev('closure', '2020-01-01', ['source' => 'lignes-oubliees']),
        ], 'P', passengerImpliesFreight: false);

        $this->assertSame(['status' => 'in_service', 'traffic' => 'passenger'], $t->stateAt(2000));
        $this->assertSame('abandoned', $t->stateAt(2010)['status']);
    }

    public function test_reduced_or_seasonal_passenger_service_is_not_a_closure(): void
    {
        // 379000 Mézidon - Trouville : desserte d'été seulement. Le parseur et les corrections en
        // font un « other » (Réduction de la desserte voyageurs), sans effet sur la carte.
        $t = new EraTimeline([
            self::ev('opening', '1880-01-01', ['source' => 'lignes-oubliees']),
            self::ev('other', '1970-01-01', ['source' => 'wikipedia', 'whole' => true]),
            self::ev('closure_freight', '1990-01-01', ['source' => 'lignes-oubliees']),
        ]);

        $this->assertSame('mixed', $t->stateAt(1980)['traffic']);
        $this->assertSame('mixed', $t->stateAt(2000)['traffic'], 'voyageurs maintenus : toujours mixte');
    }

    public function test_current_operation_drops_the_last_partial_closure_not_the_uncovered_passenger_one(): void
    {
        // Portion exploitée aujourd'hui (SNCF) : fermée aux voyageurs en 1940 (toute la ligne), au
        // fret en 2007 (section). La fermeture au fret, la plus récente, est contredite : fret seul.
        $events = [
            self::ev('opening', '1880-06-06', ['source' => 'wikipedia', 'whole' => false]),
            self::ev('closure_passenger', '1940-06-27', ['source' => 'wikipedia', 'whole' => true]),
            self::ev('closure_freight', '2007-01-01', ['source' => 'wikipedia', 'whole' => false]),
        ];

        $kept = EraTimeline::withoutContradictedPartialClosures($events, 'PF', true, 2026);
        $t = new EraTimeline($kept);

        $this->assertSame(['closure_passenger'], array_values(array_filter(array_column($kept, 'type'), fn ($type) => str_starts_with($type, 'closure'))));
        $this->assertSame('freight', $t->stateAt(1950)['traffic']);
        $this->assertSame('freight', $t->stateAt(2020)['traffic']);
    }

    public function test_same_day_opening_and_closure_leaves_the_portion_closed(): void
    {
        // Ordre d'application d'une même date : ouverture, puis fermeture.
        $t = new EraTimeline([self::ev('closure', '1939-09-02'), self::ev('opening', '1939-09-02')]);

        $this->assertSame('abandoned', $t->stateAt(1940)['status']);
    }

    /**
     * Chronologies variées (ouvertures, fermetures partielles ou totales, déposes datées
     * ou non, reconversions, réouvertures, patrimoine) pour les propriétés ci-dessus.
     *
     * @return array<string, list<array>>
     */
    private function chronologies(): array
    {
        $out = [];
        $openings = ['1855' => '1855-06-01', '1901' => '1901-12-31', '1981' => '1981-09-27'];
        $endings = [
            'aucune fin' => [],
            'fermée 1938' => [['closure', '1938-05-15']],
            'voyageurs 1938, fret 1969' => [['closure_passenger', '1938-05-15'], ['closure_freight', '1969-01-01']],
            'fermée 1952, déposée 1953' => [['closure', '1952-01-01'], ['dismantling', '1953-06-01']],
            'fermée 1952, déposée 1979' => [['closure', '1952-01-01'], ['dismantling', '1979-12-31']],
            'fermée 1990, voie verte 2008' => [['closure', '1990-01-01'], ['conversion', '2008-01-01', 'greenway']],
            'fermée 2016' => [['closure', '2016-12-11']],
            'fermée 1960, rouverte 1985' => [['closure', '1960-01-01'], ['reopening', '1985-01-01']],
            'fermée 1960, train touristique 1975' => [['closure', '1960-01-01'], ['heritage_start', '1975-01-01', null, 'tourist_train']],
            'fermée 1970, vélorail 1995' => [['closure', '1970-01-01'], ['heritage_start', '1995-01-01', null, 'velorail']],
            'déclassée 1975' => [['declassification', '1975-01-01']],
        ];
        foreach ($openings as $o => $date) {
            foreach ($endings as $label => $ends) {
                $events = [self::ev('opening', $date)];
                foreach ($ends as $e) {
                    if ($e[1] <= $date) {
                        continue 2;
                    }
                    $events[] = self::ev($e[0], $e[1], array_filter(['reuse' => $e[2] ?? null, 'heritage_kind' => $e[3] ?? null]));
                }
                $out["ouverte {$o}, {$label}"] = $events;
            }
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private function state(EraTimeline $timeline, int $year, bool $isCurrent = false, ?int $previousYear = null): array
    {
        return $timeline->stateAt($year, $isCurrent, $previousYear) ?? [];
    }
}
