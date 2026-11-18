<?php

namespace Tests\Unit;

use App\Console\Commands\BuildStationEras;
use App\Console\Commands\ConsolidateStations;
use App\Models\Era;
use App\Services\GtfsExporter;
use App\Services\NetworkGraph\EraGraphBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Règles pures des commandes et exports, appelées par réflexion (sans base) :
 *   - vff:build-station-eras : événements retenus pour la chronologie d'une gare ;
 *   - vff:consolidate-stations : statut actuel d'une gare selon ses sources,
 *     similarité de noms, distance, préfixes « Gare de » ;
 *   - export GTFS : agence, calendrier, CSV, type de route ;
 *   - graphe par ère : tableaux PostgreSQL.
 */
class CommandHelpersTest extends TestCase
{
    private function call(object|string $target, string $method, mixed ...$args): mixed
    {
        $object = is_string($target) ? (new \ReflectionClass($target))->newInstanceWithoutConstructor() : $target;

        return (new \ReflectionMethod($object, $method))->invoke($object, ...$args);
    }

    // ─── BuildStationEras::canonicalEvents ───────────────────────────────

    private function ev(string $type, string $date, bool $inferred = false, string $source = 'osm'): array
    {
        return ['type' => $type, 'date' => $date, 'inferred' => $inferred, 'source' => $source];
    }

    private function canonical(array $events): array
    {
        return array_map(fn ($e) => $e['type'].' '.$e['date'], $this->call(BuildStationEras::class, 'canonicalEvents', $events));
    }

    public function test_source_events_beat_inferred_ones_of_the_same_kind(): void
    {
        $this->assertSame(['opening 1880-05-01'], $this->canonical([
            $this->ev('opening', '1870-01-01', inferred: true),
            $this->ev('opening', '1880-05-01'),
        ]));
    }

    public function test_inferred_events_fill_the_missing_kinds_only(): void
    {
        $this->assertSame(['opening 1870-01-01', 'closure_passenger 1938-01-01', 'closure 1950-01-01'], $this->canonical([
            $this->ev('opening', '1870-01-01', inferred: true),
            $this->ev('closure', '1950-01-01'),
            $this->ev('closure', '1960-01-01', inferred: true),       // une fermeture source existe déjà
            $this->ev('closure_passenger', '1938-01-01', inferred: true),
        ]));
    }

    public function test_demolition_counts_as_an_end_of_the_station(): void
    {
        $this->assertSame(['opening 1880-01-01', 'demolition 1975-01-01'], $this->canonical([
            $this->ev('opening', '1880-01-01'),
            $this->ev('demolition', '1975-01-01'),
            $this->ev('closure', '1960-01-01', inferred: true),
        ]));
    }

    public function test_oldest_of_several_openings_is_kept_once(): void
    {
        $this->assertSame(['opening 1855-09-22'], $this->canonical([
            $this->ev('opening', '1857-01-01', source: 'wikidata'),
            $this->ev('opening', '1855-09-22', source: 'osm'),
            $this->ev('opening', '1855-09-22', source: 'lignes-oubliees'),
        ]));
    }

    public function test_same_closure_from_two_sources_in_one_year_is_kept_once(): void
    {
        // Plusieurs fermetures totales sans réouverture entre elles = une seule fermeture
        // (StationTimeline::dedupeClosures, agent gares-fiche) : la source la plus sûre, puis la
        // plus précise, puis la plus ancienne (ici OSM 1938, à égalité de source avec Wikidata).
        $this->assertSame(['opening 1880-01-01', 'closure 1938-01-01', 'closure_passenger 1938-05-15'], $this->canonical([
            $this->ev('opening', '1880-01-01'),
            $this->ev('closure', '1938-10-02', source: 'wikidata'),
            $this->ev('closure', '1938-01-01', source: 'osm'),
            $this->ev('closure_passenger', '1938-05-15'),
            $this->ev('closure', '1952-01-01'),
        ]));
        // Source plus sûre (Wikipédia) : elle l'emporte, même plus tardive (fiche fusionnant deux gares homonymes).
        $this->assertSame(['opening 1880-01-01', 'closure 1952-01-01'], $this->canonical([
            $this->ev('opening', '1880-01-01'),
            $this->ev('closure', '1938-01-01', source: 'wikidata'),
            $this->ev('closure', '1952-01-01', source: 'wikipedia'),
        ]));
    }

    public function test_reopenings_are_all_kept_and_events_are_sorted(): void
    {
        $this->assertSame(
            ['opening 1870-05-01', 'closure 1939-01-01', 'reopening 1945-06-01', 'reopening 2007-12-09'],
            $this->canonical([
                $this->ev('reopening', '2007-12-09'),
                $this->ev('closure', '1939-01-01'),
                $this->ev('reopening', '1945-06-01'),
                $this->ev('opening', '1870-05-01'),
            ]),
        );
    }

    // ─── ConsolidateStations : statut actuel ────────────────────────────

    private function osmRec(string $lifecycle, array $props = []): object
    {
        return (object) ['lifecycle' => $lifecycle, 'props' => $props];
    }

    /** @return array<string, array{0: array<string, mixed>, 1: list<string>}> */
    public static function currentStateProvider(): array
    {
        $sncf = fn (bool $p, bool $f) => (object) ['passenger' => $p, 'freight' => $f];
        $osm = fn (string $l, array $props = []) => (object) ['lifecycle' => $l, 'props' => $props];
        $wd = fn (string $l, array $p31 = [], ?string $closed = null) => (object) ['lifecycle' => $l, 'props' => ['p31' => $p31], 'closed_on' => $closed];
        $tourist = $osm('active', ['railway:preserved' => 'yes']);

        return [
            'gare voyageurs SNCF (jeu gares-de-voyageurs)' => [['sncfV' => (object) []], ['active', 'active', 'sncf_voyageurs']],
            'liste SNCF voyageurs' => [['sncf' => $sncf(true, false)], ['active', 'active', 'sncf']],
            'liste SNCF voyageurs, ligne touristique OSM' => [['sncf' => $sncf(true, true), 'osmActive' => [$tourist]], ['tourist', 'active', 'sncf']],
            'liste SNCF fret seul' => [['sncf' => $sncf(false, true)], ['no_commercial', 'active', 'sncf']],
            'liste SNCF sans service, gare en service OSM (autre exploitant)' => [['sncf' => $sncf(false, false), 'osmActive' => [$osm('active')]], ['active', 'active', 'osm']],
            'liste SNCF sans service, gare RER Wikidata' => [['sncf' => $sncf(false, false), 'wd' => $wd('active', ['Q1793804'])], ['active', 'active', 'wikidata']],
            'liste SNCF sans service, Wikidata non RER' => [['sncf' => $sncf(false, false), 'wd' => $wd('active', ['Q55488'])], ['declassified', 'disused', 'sncf']],
            'liste SNCF sans service, OSM rasée' => [['sncf' => $sncf(false, false), 'osm' => [$osm('disused'), $osm('razed')]], ['declassified', 'razed', 'sncf']],
            'OSM seule, voie conservée' => [['osmActive' => [$tourist]], ['tourist', 'active', 'osm']],
            'OSM seule, en service' => [['osmActive' => [$osm('active', ['usage' => 'main'])]], ['active', 'active', 'osm']],
            'OSM abandonnée' => [['osm' => [$osm('historic'), $osm('abandoned')]], ['gone', 'abandoned', 'osm']],
            'Wikidata en service' => [['wd' => $wd('active')], ['active', 'active', 'wikidata']],
            'Wikidata inconnue avec date de fermeture' => [['wd' => $wd('unknown', [], '1950-01-01')], ['gone', 'disused', 'wikidata']],
            'Wikidata inconnue sans date' => [['wd' => $wd('unknown')], ['active', 'unknown', 'wikidata']],
            'Wikidata en projet' => [['wd' => $wd('planned')], ['gone', 'planned', 'wikidata']],
            'Wikidata démolie' => [['wd' => $wd('demolished')], ['gone', 'demolished', 'wikidata']],
            'OHM toutes fermées' => [['ohm' => [(object) ['closed_on' => '1938-01-01']]], ['gone', 'historic', 'ohm']],
            'bâtiment OSM seul' => [['osm' => [$osm('unknown')]], ['gone', 'unknown', 'osm']],
            'liste historique seule' => [[], ['gone', 'unknown', 'lignes-oubliees']],
        ];
    }

    /** @param  array<string, mixed>  $sources */
    #[DataProvider('currentStateProvider')]
    public function test_current_state_of_a_station_from_its_sources(array $sources, array $expected): void
    {
        $state = $this->call(ConsolidateStations::class, 'currentState',
            $sources['sncf'] ?? null, $sources['osm'] ?? [], $sources['osmActive'] ?? [], $sources['wd'] ?? null,
            $sources['ohm'] ?? [], 'lignes-oubliees', $sources['sncfV'] ?? null);

        $this->assertSame($expected, $state);
    }

    public function test_ohm_station_still_open_somewhere_is_not_gone_by_ohm(): void
    {
        $state = $this->call(ConsolidateStations::class, 'currentState', null, [], [], null,
            [(object) ['closed_on' => '1938-01-01'], (object) ['closed_on' => null]], 'ohm');

        $this->assertSame(['gone', 'unknown', 'ohm'], $state, 'repli : source de la liste, pas « historic »');
    }

    public function test_worst_osm_lifecycle_and_tourist_tags(): void
    {
        $this->assertSame('razed', $this->call(ConsolidateStations::class, 'worstOsm', [$this->osmRec('historic'), $this->osmRec('razed'), $this->osmRec('disused')]));
        $this->assertSame('disused', $this->call(ConsolidateStations::class, 'worstOsm', [$this->osmRec('historic'), $this->osmRec('disused')]));
        $this->assertSame('unknown', $this->call(ConsolidateStations::class, 'worstOsm', [$this->osmRec('active')]));

        foreach ([['usage' => 'tourism'], ['tourism' => 'yes'], ['railway:preserved' => 'yes']] as $props) {
            $this->assertTrue($this->call(ConsolidateStations::class, 'isTourist', $this->osmRec('active', $props)));
        }
        $this->assertFalse($this->call(ConsolidateStations::class, 'isTourist', $this->osmRec('active', ['usage' => 'main'])));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function prefixProvider(): array
    {
        return [
            'gare de' => ['Gare de Morlaix', 'Morlaix'],
            'ancienne gare d\'' => ['Ancienne gare d\'Auray', 'Auray'],
            'halte de la' => ['Halte de la Brohinière', 'Brohinière'],
            'gare ferroviaire du' => ['Gare ferroviaire du Mans', 'Mans'],
            'suffixe (gare)' => ['Morlaix (gare)', 'Morlaix'],
            'minuscule en tête' => ['gare de saint-brieuc', 'Saint-brieuc'],
            'rien à retirer' => ['Brest', 'Brest'],
            'mot « Gare » seul : nom gardé' => ['Gare', 'Gare'],
        ];
    }

    #[DataProvider('prefixProvider')]
    public function test_station_name_prefix_is_stripped(string $name, string $expected): void
    {
        $this->assertSame($expected, $this->call(ConsolidateStations::class, 'stripPrefix', $name));
    }

    public function test_name_similarity_and_distance(): void
    {
        $this->assertSame(1.0, $this->call(ConsolidateStations::class, 'nameSim', 'brest', 'brest'));
        $this->assertSame(0.9, $this->call(ConsolidateStations::class, 'nameSim', 'pontoise', 'pontoise ville'));
        $this->assertSame(0.9, $this->call(ConsolidateStations::class, 'nameSim', 'saint brieuc gare', 'saint brieuc'));
        $this->assertLessThan(0.5, $this->call(ConsolidateStations::class, 'nameSim', 'brest', 'quimper'));
        // Mot partiel : pas un mot entier contenu.
        $this->assertLessThan(0.9, $this->call(ConsolidateStations::class, 'nameSim', 'lens', 'valenciennes'));

        $this->assertEqualsWithDelta(111_195, $this->call(ConsolidateStations::class, 'haversine', 48.0, 2.0, 49.0, 2.0), 1);
        $this->assertSame(0.0, $this->call(ConsolidateStations::class, 'haversine', 48.0, 2.0, 48.0, 2.0));
    }

    // ─── GTFS ────────────────────────────────────────────────────────────

    public function test_gtfs_agency_and_calendar_cover_the_era_year(): void
    {
        $gtfs = new GtfsExporter;

        $csv = fn (string $content) => array_map(fn ($l) => str_getcsv($l, ',', '"', ''), explode("\n", trim($content)));
        $agency = $csv($this->call($gtfs, 'buildAgency'));
        $this->assertSame(['agency_id', 'agency_name', 'agency_url', 'agency_timezone', 'agency_lang'], $agency[0]);
        $this->assertSame('Europe/Paris', $agency[1][3]);
        $this->assertStringNotContainsString("\u{2014}", $agency[1][1]);

        $era = new Era;
        $era->year = 1960;
        $calendar = $csv($this->call($gtfs, 'buildCalendar', $era));
        $this->assertSame('service_id', $calendar[0][0]);
        $this->assertSame(['service-1960', '1', '1', '1', '1', '1', '1', '1', '19600101', '19601231'], $calendar[1]);
    }

    public function test_gtfs_csv_quotes_fields_that_need_it(): void
    {
        $csv = $this->call(new GtfsExporter, 'toCsv', [['route_id', 'route_long_name'], ['1', 'Ligne de Paris, Saint-Lazare à "Versailles"']]);

        $this->assertSame("route_id,route_long_name\n1,\"Ligne de Paris, Saint-Lazare à \"\"Versailles\"\"\"\n", $csv);
        $this->assertSame([['1', 'Ligne de Paris, Saint-Lazare à "Versailles"']], [str_getcsv(explode("\n", $csv)[1], ',', '"', '')]);
    }

    public function test_gtfs_route_types(): void
    {
        $map = (new \ReflectionClass(GtfsExporter::class))->getConstant('ROUTE_TYPE_MAP');

        // GTFS : 2 = Rail ; 100 = Railway Service (types étendus) pour le fret.
        $this->assertSame(['passenger' => 2, 'freight' => 100, 'mixed' => 2], $map);
    }

    // ─── Graphe par ère ──────────────────────────────────────────────────

    public function test_postgres_integer_array_literal(): void
    {
        $this->assertSame('{1,2,3}', $this->call(EraGraphBuilder::class, 'pgArray', [1, 2, 3]));
        $this->assertSame('{}', $this->call(EraGraphBuilder::class, 'pgArray', []));
        $this->assertSame('{4,5,0}', $this->call(EraGraphBuilder::class, 'pgArray', ['4', '5x', 'DROP']), 'jamais de texte injecté');
    }
}
