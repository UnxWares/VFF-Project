<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use App\Services\LineStations;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ReadsInertiaProps;
use Tests\TestCase;

/**
 * Fiche ligne (LineController::show) : phases datées et ligne entière à son plus grand
 * développement, chronique par portion (sources en note, désaccords signalés), décennies
 * avec gares, TOUTES les gares de la ligne, autres noms avec sources, texte « À propos »
 * attribué, signalisation (cantonnement et contrôle de vitesse).
 *
 * Ligne fictive inspirée de 434000 : portion A ouverte le 06/12/1869, B le 15/03/1881 ;
 * B fermée en 1940 puis déposée (non daté), A passée au fret le 26/05/1991.
 */
class LineSheetPageTest extends TestCase
{
    use ReadsInertiaProps;
    use RefreshDatabase;

    private Line $line;

    /** @var array<int, Era> */
    private array $eras = [];

    private const A = 'LINESTRING(0.30 48.60, 0.40 48.60)';

    private const B = 'LINESTRING(0.40 48.60, 0.50 48.60)';

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([1860, 1870, 1880, 1890, 1940, 1950, 2030] as $y) {
            $this->eras[$y] = $y === 2030 ? Era::factory()->current()->create(['year' => $y]) : Era::factory()->create(['year' => $y]);
        }
        $this->line = Line::factory()->create([
            'slug' => 'ligne-test-434000', 'code' => '434000', 'name' => 'Ligne de Briouze à La Ferté-Macé', 'type' => null,
            'network' => 'principal', 'is_lgv' => false, 'wikipedia_title' => 'Ligne de Briouze à Couterne',
            'description' => 'La ligne de Briouze à Couterne — ancienne ligne normande.',
            'alt_names' => [
                'wikidata' => 'Q106368432', 'name_source' => 'sncf',
                'names' => [['name' => 'Ligne de Couterne à Briouze', 'kind' => 'name', 'source' => 'wikidata', 'source_url' => 'https://www.wikidata.org/wiki/Q106368432', 'from' => null, 'to' => null]],
                'merged' => [['name' => 'Ancienne fiche fusionnée', 'slug' => 'x']],
            ],
        ]);

        $states = [
            1870 => ['A' => ['in_service', 'mixed']],
            1880 => ['A' => ['in_service', 'mixed']],
            1890 => ['A' => ['in_service', 'mixed'], 'B' => ['in_service', 'mixed']],
            1940 => ['A' => ['in_service', 'mixed'], 'B' => ['abandoned', 'unknown']],
            1950 => ['A' => ['in_service', 'mixed'], 'B' => ['dismantled', 'unknown']],
            2030 => ['A' => ['in_service', 'freight']],
        ];
        foreach ($states as $year => $portions) {
            foreach ($portions as $p => [$status, $traffic]) {
                DB::table('line_segments')->insert(['line_id' => $this->line->id, 'era_id' => $this->eras[$year]->id,
                    'geom' => DB::raw("ST_GeomFromText('".constant(self::class.'::'.$p)."', 4326)"), 'status' => $status,
                    'traffic_type' => $traffic, 'length_meters' => 7300, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        $ev = fn (string $type, string $date, ?string $portion, ?string $from = null, ?string $to = null, string $precision = 'day', string $source = 'wikipedia', ?string $url = 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Briouze_%C3%A0_Couterne&oldid=1') => DB::table('line_events')->insertGetId([
            'line_id' => $this->line->id, 'type' => $type, 'event_date' => $date, 'date_precision' => $precision,
            'description' => "{$type} — {$date}", 'source' => $source, 'source_url' => $url, 'confidence' => 'high',
            'section_from' => $from, 'section_to' => $to, 'section_resolution' => $portion ? 'places' : null,
            'section_geom' => $portion ? DB::raw("ST_Multi(ST_GeomFromText('".constant(self::class.'::'.$portion)."', 4326))") : null,
            'created_at' => now(), 'updated_at' => now()]);
        $ev('opening', '1869-12-06', 'A', 'Briouze', 'La Ferté-Macé', 'day', 'manual', 'https://example.org/source');
        $ev('opening', '1881-03-15', 'B', 'La Ferté-Macé', 'Couterne', 'day', 'manual', 'https://example.org/source');
        $ev('closure', '1940-01-01', 'B', 'Couterne', 'Bagnoles-de-l’Orne', 'year');
        $ev('closure_passenger', '1941-02-17', 'B', 'Bagnoles-de-l’Orne', 'Couterne');
        $ev('closure_passenger', '1991-05-26', 'A', 'Briouze', 'La Ferté-Macé');
        $ev('concession', '1866-01-01', null, null, null, 'year');

        // Gares : toutes rattachées à la ligne (station_lines, comme vff:attach-stations) ;
        // S1 desservie aujourd'hui, S3 desservie autrefois (arrêt de 1890 seulement), S2
        // rattachée sans arrêt connu.
        $station = fn (string $slug, float $lng, string $status) => Station::factory()->create([
            'slug' => $slug, 'name' => ucfirst($slug), 'geom' => Point::makeGeodetic(48.60, $lng), 'status' => $status,
            'opened_at' => '1869-12-06', 'opened_precision' => 'day', 'closed_at' => $status === 'declassified' ? '1940-01-01' : null,
            'closed_precision' => $status === 'declassified' ? 'year' : null,
        ]);
        $s1 = $station('briouze', 0.30, 'active');
        $s2 = $station('couterne', 0.50, 'declassified');
        $s3 = $station('la-ferte-mace', 0.40, 'declassified');
        $stop = fn ($s, int $year, int $pk, int $pos, bool $terminal = false) => DB::table('line_stops')->insert(['line_id' => $this->line->id,
            'era_id' => $this->eras[$year]->id, 'station_id' => $s->id, 'position' => $pos, 'pk_m' => $pk, 'effective_status' => 'active',
            'is_terminal' => $terminal, 'created_at' => now(), 'updated_at' => now()]);
        $stop($s1, 2030, 0, 1, true);
        $stop($s3, 1890, 7300, 2);
        foreach ([[$s1, 0, 1869, null], [$s3, 7300, 1869, 1940], [$s2, 14600, 1881, 1940]] as [$s, $pk, $from, $until]) {
            DB::table('station_lines')->insert(['station_id' => $s->id, 'line_id' => $this->line->id, 'method' => 'sncf_pk', 'pk_m' => $pk,
                'first_year' => $from, 'last_year' => $until, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ([[$s1, 1890, 'active'], [$s1, 2030, 'no_commercial'], [$s3, 1890, 'active'], [$s3, 1950, 'declassified'], [$s2, 1890, 'active'], [$s2, 1950, 'declassified']] as [$s, $y, $st]) {
            DB::table('station_eras')->insert(['station_id' => $s->id, 'era_id' => $this->eras[$y]->id, 'status' => $st, 'created_at' => now(), 'updated_at' => now()]);
        }

        DB::table('line_sections')->insert(['line_id' => $this->line->id, 'name' => 'Section de Briouze', 'alt_names' => json_encode(['Tronçon nord']),
            'pk_start_m' => 0, 'pk_end_m' => 7300, 'opening_year_from' => 1869, 'opening_year_to' => 1869,
            'source_url' => 'https://fr.wikipedia.org/wiki/X', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('line_sources')->insert(['line_id' => $this->line->id, 'source_type' => 'wikipedia-intro',
            'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Briouze_%C3%A0_Couterne&oldid=99', 'fetched_at' => '2026-10-19 23:00:00',
            'fields_provided' => json_encode(['fields' => ['description'], 'title' => 'Ligne de Briouze à Couterne', 'revision_id' => 99, 'license' => 'CC BY-SA 4.0'])]);
        DB::table('line_signalling')->insert(['line_id' => $this->line->id, 'era_id' => $this->eras[2030]->id, 'pk_start_m' => 0, 'pk_end_m' => 7300,
            'signalling' => 'BAPR-VB', 'train_protection' => json_encode(['KVB']), 'kvb_equipped' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_phases_are_dated_by_real_events_and_open_on_the_greatest_extent(): void
    {
        $this->get('/lines/ligne-test-434000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Lines/Show')
            ->has('portions', 2)
            ->where('portions.0.geometry.type', 'LineString')
            ->where('phases', function ($phases) {
                $dates = $this->rows($phases)->map(fn ($p) => [$p['date'], $p['precision']])->all();
                $this->assertSame([
                    ['1869-12-06', 'day'], ['1881-03-15', 'day'], ['1940-01-01', 'year'],
                    ['1950-12-31', 'range'], ['1991-05-26', 'day'],
                ], $dates);
                $this->assertSame(['mixed', 'mixed'], $phases[1]['state'], 'toute la ligne à chaque phase');
                $this->assertSame(['mixed', 'closed'], $phases[2]['state']);
                $this->assertSame(['mixed', 'dismantled'], $phases[3]['state']);
                $this->assertSame(['freight', 'dismantled'], $phases[4]['state']);
                $this->assertSame(['from' => 'mixed', 'to' => 'closed'], array_intersect_key($phases[2]['changes'][0], ['from' => 1, 'to' => 1]));
                $this->assertSame([1941, 1950], [$phases[3]['from_year'], $phases[3]['to_year']]);
                $this->assertSame(['growth', 'growth', 'decline', 'removal', 'traffic'], $this->rows($phases)->pluck('kind')->all());

                return true;
            })
            ->where('best_phase', 1)
            ->where('decades', function ($decades) {
                $byYear = $this->rows($decades)->keyBy('year');
                $this->assertSame([1870, 1880, 1890, 1940, 1950, 2030], $byYear->keys()->all());
                $this->assertSame(['dismantled' => 7300, 'freight' => 7300], $byYear[2030]['length_by_category'], 'la voie déposée reste comptée');
                // Gares : mêmes règles que App\Services\LineStations::byEra (agent « gares »).
                $this->assertSame([3, 0, 0], [$byYear[1890]['stations']['open'], $byYear[1890]['stations']['closed'], $byYear[1890]['stations']['uncertain']]);
                $this->assertSame(2, $byYear[1950]['stations']['closed']);
                // Fermées en 1940 (date retenue) mais le fil des gares ne passe à « fermée » qu'à l'ère 1950
                // (pas d'état connu en 1940) : listées en 1950, comme le compteur (jamais plus de noms que
                // de fermetures comptées).
                $this->assertSame([0, []], [$byYear[1940]['stations']['closed'], $byYear[1940]['stations']['closures']]);
                $this->assertEqualsCanonicalizing(['couterne', 'la-ferte-mace'], $this->rows($byYear[1950]['stations']['closures'])->pluck('slug')->all(), 'fermées à l’ère 1950');

                return true;
            })
        );
    }

    public function test_chronicle_groups_events_by_portion_with_notes_and_conflicts(): void
    {
        $this->get('/lines/ligne-test-434000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('chronicle.events', 6)
            ->where('chronicle', function ($c) {
                $events = $this->rows($c['events'])->groupBy('type')->map->first();
                $this->assertSame('retained', $events['opening']['status']);
                $this->assertSame('context', $events['concession']['status']);
                $this->assertSame('opening - 1869-12-06', $events['opening']['description'], 'pas de tiret long');
                $this->assertContains('closure_order', $this->rows($c['conflicts'])->pluck('family')->all(), '1940 contre 17/02/1941');
                $this->assertSame([1, 2], $this->rows($c['notes'])->pluck('n')->all());
                $this->assertSame('whole', $c['groups'][0]['key']);
                $this->assertContains('bagnoles de l orne|couterne', $this->rows($c['groups'])->pluck('key')->all());

                return true;
            })
        );
    }

    public function test_all_stations_of_the_line_are_listed_in_route_order(): void
    {
        $this->get('/lines/ligne-test-434000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('stations', 3)
            ->where('stations.0.slug', 'briouze')
            ->where('stations.1.slug', 'la-ferte-mace')
            ->where('stations.2.slug', 'couterne')
            ->where('stations.0.status_today', 'no_commercial')
            ->where('stations.1.status_today', null) // n'existe plus à l'ère courante, mais reste sur la fiche
            ->where('stations.2.pk_label', '14+600')
            ->where('stations.2.closed_at', '1940-01-01')
            ->where('stations.1.eras', fn ($eras) => $this->rows($eras)->all() === [1890 => 'active', 1950 => 'declassified'])
            ->where('stats.station_count', 3)
        );
    }

    /**
     * Non-régression (fiche 434000 : « 0 gare » dans l'en-tête, liste vide) : une ligne
     * entièrement fermée et déposée affiche TOUTES ses gares, avec leurs dates de fermeture,
     * et l'en-tête compte ces mêmes gares. Une seule source : App\Services\LineStations
     * (liste, en-tête, décennies), sans repli spatial ni line_stops d'une seule ère.
     */
    public function test_a_closed_line_lists_all_its_stations_and_counts_them_in_the_header(): void
    {
        $line = Line::factory()->create(['slug' => 'ligne-fermee', 'code' => '999000', 'name' => 'Ligne fermée', 'type' => null,
            'alt_names' => null, 'description' => null]);
        $geom = "ST_GeomFromText('LINESTRING(1.00 47.00, 1.20 47.00)', 4326)";
        foreach ([1890 => 'in_service', 1940 => 'in_service', 1950 => 'dismantled', 2030 => 'dismantled'] as $year => $status) {
            DB::table('line_segments')->insert(['line_id' => $line->id, 'era_id' => $this->eras[$year]->id, 'geom' => DB::raw($geom),
                'status' => $status, 'traffic_type' => $status === 'in_service' ? 'mixed' : 'unknown', 'length_meters' => 15000,
                'created_at' => now(), 'updated_at' => now()]);
        }
        $make = fn (string $slug, float $lng, ?string $closed) => Station::factory()->create([
            'slug' => $slug, 'name' => ucfirst($slug), 'geom' => Point::makeGeodetic(47.00, $lng), 'status' => $closed ? 'declassified' : 'active',
            'opened_at' => '1885-06-01', 'opened_precision' => 'day', 'closed_at' => $closed, 'closed_precision' => $closed ? 'day' : null,
        ]);
        $gares = [
            [$make('origine', 1.00, '1946-03-02'), 0],
            [$make('milieu', 1.10, '1946-03-02'), 7500],
            [$make('terminus', 1.20, '1944-09-30'), 15000],
        ];
        foreach ($gares as [$st, $pk]) {
            DB::table('station_lines')->insert(['station_id' => $st->id, 'line_id' => $line->id, 'method' => 'sncf_pk', 'pk_m' => $pk,
                'first_year' => 1885, 'last_year' => 1946, 'created_at' => now(), 'updated_at' => now()]);
            foreach ([1890 => 'active', 1940 => 'active', 1950 => 'declassified', 2030 => 'declassified'] as $y => $status) {
                DB::table('station_eras')->insert(['station_id' => $st->id, 'era_id' => $this->eras[$y]->id, 'status' => $status,
                    'created_at' => now(), 'updated_at' => now()]);
            }
        }
        // Une gare ouverte à 100 m du tracé mais rattachée à aucune ligne : jamais comptée.
        $voisine = $make('voisine', 1.05, null);
        DB::table('station_eras')->insert(['station_id' => $voisine->id, 'era_id' => $this->eras[2030]->id, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now()]);

        $this->get('/lines/ligne-fermee')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('stations', 3)
            ->where('stations', fn ($s) => $this->rows($s)->pluck('slug')->all() === ['origine', 'milieu', 'terminus'])
            ->where('stations', fn ($s) => $this->rows($s)->pluck('closed_at')->all() === ['1946-03-02', '1946-03-02', '1944-09-30'])
            ->where('stations', fn ($s) => $this->rows($s)->pluck('status_today')->unique()->values()->all() === ['declassified'])
            ->where('stats.station_count', 3)
            ->where('stats.station_open_count', 0)
            ->where('decades', function ($decades) {
                $byYear = $this->rows($decades)->keyBy('year');
                $this->assertSame([3, 0], [$byYear[1940]['stations']['open'], $byYear[1940]['stations']['closed']]);
                $this->assertSame([0, 3], [$byYear[1950]['stations']['open'], $byYear[1950]['stations']['closed']]);
                $this->assertSame(['origine', 'milieu', 'terminus'], $this->rows($byYear[1950]['stations']['closures'])->pluck('slug')->all());
                $this->assertSame('1944-09-30', $this->rows($byYear[1950]['stations']['closures'])->firstWhere('slug', 'terminus')['closed_at']);

                return true;
            })
        );
    }

    public function test_header_shows_one_line_length_and_what_is_in_service_today(): void
    {
        // Une seule longueur par fiche : le tracé de VFF, toutes portions (A en service, B déposée),
        // la même que le rail, les phases et les décennies (14,6 km) ; la part en service (A, 7,3 km)
        // dans active_length_km ; plus de second chiffre « à son apogée ».
        $this->get('/lines/ligne-test-434000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('stats.length_greatest_km', null)
            ->where('stats.length_km', 14.6)
            ->where('stats.active_length_km', 7.3)
            ->where('stats.length_definition', 'trace')
            ->where('decades', fn ($d) => array_sum($this->rows($d)->firstWhere('is_current', true)['length_by_category']) === 14600)
            ->where('chronicle.groups.0.key', 'whole')
            ->where('chronicle.groups.0.length_m', 14600)
            ->where('line.current_state', 'open')
            ->missing('stats.length_source')
        );
    }

    public function test_header_shows_a_single_length_when_the_line_is_whole_today(): void
    {
        // B rouverte en 2030 : toute la ligne est en service.
        DB::table('line_segments')->insert(['line_id' => $this->line->id, 'era_id' => $this->eras[2030]->id,
            'geom' => DB::raw("ST_GeomFromText('".self::B."', 4326)"), 'status' => 'in_service',
            'traffic_type' => 'freight', 'length_meters' => 7300, 'created_at' => now(), 'updated_at' => now()]);

        $this->get('/lines/ligne-test-434000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('stats.length_greatest_km', null)
            ->where('stats.length_km', fn ($km) => abs($km - 14.6) < 0.2)
            ->where('stats.active_length_km', fn ($km) => abs($km - 14.6) < 0.2)
            ->where('line.current_state', 'open')
        );
    }

    /** @return array<string, array{string, string}> */
    public static function shutToday(): array
    {
        return [
            'entièrement fermée' => ['abandoned', 'closed'],
            'entièrement déposée' => ['dismantled', 'gone'],
        ];
    }

    #[DataProvider('shutToday')]
    public function test_header_states_a_line_closed_or_dismantled_today(string $status, string $state): void
    {
        // Plus rien en service aujourd'hui : l'apogée et l'état (« Aujourd'hui fermée »,
        // « Aujourd'hui déposée ») au lieu d'une longueur « aujourd'hui » trompeuse.
        DB::table('line_segments')->where('line_id', $this->line->id)->where('era_id', $this->eras[2030]->id)
            ->update(['status' => $status]);

        $this->get('/lines/ligne-test-434000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('line.current_state', $state)
            ->where('stats.length_greatest_km', null)
            ->where('stats.length_km', 14.6)
            ->where('stats.active_length_km', 0)
            ->where('translations', fn ($t) => is_string($label = $this->rows($t)->get("lines.stats.today_{$state}")) && $label !== '')
        );
    }

    public function test_the_sheet_uses_line_stations_everywhere_without_a_divergent_fallback(): void
    {
        // Mêmes gares, mêmes dates, mêmes comptes que App\Services\LineStations.
        $service = app(LineStations::class);
        $expected = collect($service->forLine($this->line->id)['stations']);
        $byEra = collect($service->byEra($this->line->id))->keyBy('year');
        $this->get('/lines/ligne-test-434000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('stations', fn ($s) => $this->rows($s)->pluck('slug')->all() === $expected->pluck('slug')->all())
            ->where('stations', fn ($s) => $this->rows($s)->pluck('closed_at')->all() === $expected->pluck('closed_at')->all())
            ->where('stats.station_count', $expected->count())
            ->where('decades', function ($decades) use ($byEra) {
                foreach ($decades as $d) {
                    $e = $byEra[$d['year']];
                    $this->assertSame([$e['open'], $e['uncertain'], $e['closed']], [$d['stations']['open'], $d['stations']['uncertain'], $d['stations']['closed']], (string) $d['year']);
                    $this->assertSame(array_column($e['closures'], 'slug'), array_column($d['stations']['closures'], 'slug'), (string) $d['year']);
                }

                return true;
            })
        );

        // Une ligne sans gare rattachée n'invente pas de gares le long de son tracé.
        $bare = Line::factory()->create(['slug' => 'ligne-sans-gare', 'type' => null, 'alt_names' => null, 'description' => null]);
        DB::table('line_segments')->insert(['line_id' => $bare->id, 'era_id' => $this->eras[2030]->id,
            'geom' => DB::raw("ST_GeomFromText('".self::A."', 4326)"), 'status' => 'in_service', 'traffic_type' => 'mixed',
            'length_meters' => 7300, 'created_at' => now(), 'updated_at' => now()]);
        $this->get('/lines/ligne-sans-gare')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('stations', 0)->where('stats.station_count', 0)->where('stats.station_open_count', 0));
    }

    public function test_names_description_and_signalling(): void
    {
        $this->get('/lines/ligne-test-434000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('line.names', function ($names) {
                $byName = $this->rows($names)->keyBy('name');
                $this->assertSame('wikidata', $byName['Ligne de Couterne à Briouze']['source']);
                $this->assertSame(['section', 1869], [$byName['Section de Briouze']['kind'], $byName['Section de Briouze']['from']]);
                $this->assertSame('section', $byName['Tronçon nord']['kind']);
                // « Ligne de Briouze à Couterne » (titre Wikipédia) = « Ligne de Couterne à Briouze » à l'ordre
                // près : un seul des deux.
                $this->assertFalse($byName->has('Ligne de Briouze à Couterne'), 'même nom, lieux inversés');
                $this->assertSame('merged', $byName['Ancienne fiche fusionnée']['kind']);
                $this->assertFalse($byName->has('Ligne de Briouze à La Ferté-Macé'), 'jamais le nom principal');

                return true;
            })
            ->where('line.alt_names', fn ($n) => $this->rows($n)->contains('Ligne de Couterne à Briouze'))
            ->where('line.wikidata', 'Q106368432')
            ->where('line.description', 'La ligne de Briouze à Couterne - ancienne ligne normande.')
            ->where('line.description_source.type', 'wikipedia')
            ->where('line.description_source.license', 'CC BY-SA 4.0')
            ->where('line.description_source.url', 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Briouze_%C3%A0_Couterne&oldid=99')
            ->where('stats.signalling', 'BAPR-VB')
            ->where('stats.train_protection', 'KVB')
            ->where('signalling.0.train_protection', ['KVB'])
            ->has('sections', 1)
        );
    }

    public function test_dual_cab_range_counts_both_systems_and_names_them_once(): void
    {
        DB::table('line_signalling')->insert(['line_id' => $this->line->id, 'era_id' => $this->eras[2030]->id, 'pk_start_m' => 7300, 'pk_end_m' => 14600,
            'signalling' => 'ETCS_L2', 'signalling_systems' => json_encode(['ETCS_L2', 'TVM-300']),
            'train_protection' => json_encode(['ERTMS_L2', 'TVM-300']), 'kvb_equipped' => false, 'created_at' => now(), 'updated_at' => now()]);

        $this->get('/lines/ligne-test-434000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('stats.signalling', 'BAPR-VB, ETCS_L2, TVM-300')
            ->where('signalling.1.signalling_systems', ['ETCS_L2', 'TVM-300'])
            // La TVM est nommée comme cantonnement : pas une seconde fois dans le contrôle de vitesse.
            ->where('signalling.1.train_protection', ['ERTMS_L2'])
        );
    }

    public function test_named_sections_list_their_dated_openings_with_their_sources(): void
    {
        // « Section de Briouze » (ouverte en 1869, Wikipédia) : l'ouverture datée du 06/12/1869 de la
        // portion A est son étape ; celle de 1881 (portion B) tombe hors de ses années d'ouverture.
        $opening = (int) DB::table('line_events')->where('line_id', $this->line->id)->where('event_date', '1869-12-06')->value('id');
        $this->get('/lines/ligne-test-434000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('sections', 1)
            ->where('sections.0.name', 'Section de Briouze')
            ->where('sections.0.stages', function ($stages) use ($opening) {
                $this->assertSame([['phase' => 0, 'date' => '1869-12-06', 'precision' => 'day', 'length_m' => 7300, 'event_ids' => [$opening]]],
                    $this->rows($stages)->all());

                return true;
            })
            // La source de l'étape est une note de la chronique (appel [n] sur la fiche).
            ->where('chronicle.events', fn ($e) => is_int($this->rows($e)->firstWhere('id', $opening)['note'] ?? null))
            ->where('translations', fn ($t) => is_string($this->rows($t)->get('lines.sections.stages_title')))
        );
        // API REST et GraphQL : même contenu.
        $this->getJson('/api/v1/lines/ligne-test-434000')->assertOk()
            ->assertJsonPath('data.sections.0.stages.0.date', '1869-12-06')
            ->assertJsonPath('data.sections.0.stages.0.event_ids', [$opening]);
        $this->postJson('/graphql', ['query' => '{ line(slug: "ligne-test-434000") { sheet { sections { name stages { phase date precision length_m event_ids } } } } }'])
            ->assertOk()->assertJsonMissingPath('errors')
            ->assertJsonPath('data.line.sheet.sections.0.stages', [['phase' => 0, 'date' => '1869-12-06', 'precision' => 'day', 'length_m' => 7300, 'event_ids' => [$opening]]]);
    }

    public function test_a_provisional_station_closure_is_named_in_its_decade(): void
    {
        // Briouze fermée pendant la décennie 1940 (frise : fermée en 1940, rouverte en 1950) : comptée
        // fermée en 1940 ET nommée, avec l'intervalle de la frise, sans date inventée.
        $briouze = Station::query()->where('slug', 'briouze')->value('id');
        DB::table('station_eras')->insert([
            ['station_id' => $briouze, 'era_id' => $this->eras[1940]->id, 'status' => 'declassified', 'created_at' => now(), 'updated_at' => now()],
            ['station_id' => $briouze, 'era_id' => $this->eras[1950]->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->get('/lines/ligne-test-434000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('decades', function ($decades) {
                $d1940 = $this->rows($decades)->firstWhere('year', 1940)['stations'];
                $this->assertSame(1, $d1940['closed']);
                $this->assertSame([['slug' => 'briouze', 'name' => 'Briouze', 'closed_at' => '1940-12-31', 'closed_precision' => 'range',
                    'closed_after' => 1890, 'reopened' => true]], $d1940['closures']);

                return true;
            })
        );
    }

    public function test_line_without_any_segment_has_no_phase(): void
    {
        $empty = Line::factory()->create(['type' => null, 'alt_names' => null, 'description' => null]);
        $this->get('/lines/'.$empty->slug)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('phases', 0)->has('portions', 0)->where('best_phase', null)->has('decades', 0)
            ->where('line.names', [])->where('line.description_source', null));
    }

    public static function locales(): array
    {
        return [
            'fr' => ['fr', 'Phases de la ligne', 'Voyageurs et fret'],
            'en' => ['en', 'Phases of the line', 'Passengers and freight'],
            'de' => ['de', 'Phasen der Strecke', 'Personen- und Güterverkehr'],
        ];
    }

    #[DataProvider('locales')]
    public function test_the_page_is_translated(string $locale, string $title, string $mixed): void
    {
        $this->withCookie('locale', $locale)->get('/lines/ligne-test-434000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('translations', function ($t) use ($title, $mixed) {
                $t = $this->rows($t);
                $this->assertSame($title, $t['lines.phases.title']);
                $this->assertSame($mixed, $t['lines.categories.mixed']);
                foreach (['lines.chronicle.status.conflict', 'lines.changes.passenger_end', 'lines.decades.col_stations', 'lines.signalling.codes.BAPR-VB', 'lines.facts.roles.exploitant'] as $key) {
                    $this->assertNotEmpty($t[$key] ?? null, $key);
                }

                return true;
            })
        );
    }
}
