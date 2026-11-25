<?php

namespace Tests\Feature\Api;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Models\Station;
use App\Services\TrackLayout\TrackLayout;
use App\Services\TrackLayout\TrackLayoutPart;
use App\Services\TrackLayout\TrackLayoutSource;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

/**
 * Livret de parcours (GET /api/v1/routebook, GraphQL `routebook`, outil MCP `routebook`, page /routebook) sur un
 * mini-réseau d'aujourd'hui, avec des sources de schéma des voies factices lues par TrackLayout (celui de track-layout) :
 *
 *   d (PK 72 200 de 200000)
 *   │                 e (PK 11 100 de 300000)
 *   │                 │
 *   a ─────────────── b ────────── J ──── c       ligne 100000 : a PK 0, b PK 7 450, c PK 14 900 (forme SNCF 0 à 14 900)
 *                     │ 200000 part de b (PK 50 000) vers d ; 300000 part de J (pleine voie, PK 0) vers e
 */
class RoutebookApiTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private Era $era;

    protected function setUp(): void
    {
        parent::setUp();
        $this->era = Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd\'hui']);

        $l1 = $this->line('100000', 'Ligne de A à C', [[48.0, 2.00], [48.0, 2.20]], [0, 14900]);
        $l2 = $this->line('200000', 'Ligne de B à D', [[48.0, 2.10], [48.2, 2.10]], null);
        $l3 = $this->line('300000', 'Ligne de J à E', [[48.0, 2.15], [48.1, 2.15]], [0, 11100]);

        $this->station('a', 48.0, 2.00, [[$l1, 0]]);
        $this->station('b', 48.0, 2.10, [[$l1, 7450], [$l2, 50000]]);
        $this->station('c', 48.0, 2.20, [[$l1, 14900]]);
        $this->station('d', 48.2, 2.10, [[$l2, 72200]]);
        $this->station('e', 48.1, 2.15, [[$l3, 11100]]);

        $this->artisan('vff:build-network-graph')->assertSuccessful();
        $this->app->instance(TrackLayout::class, new TrackLayout([new FakeSource('rinf'), new FakeSource('osm')]));
    }

    /**
     * @param  list<array{0: float, 1: float}>  $coords
     * @param  array{0: int, 1: int}|null  $sncfPk  PK de début et de fin de la forme SNCF (null : pas de forme)
     */
    private function line(string $code, string $name, array $coords, ?array $sncfPk): Line
    {
        $line = Line::factory()->create(['code' => $code, 'slug' => "rfn-{$code}", 'name' => $name, 'is_lgv' => false, 'network' => 'principal']);
        LineSegment::factory()->create([
            'line_id' => $line->id, 'era_id' => $this->era->id,
            'geom' => LineString::make(array_map(fn ($c) => Point::makeGeodetic($c[0], $c[1]), $coords)),
            'status' => 'in_service', 'traffic_type' => 'mixed', 'is_heritage' => false, 'gauge_mm' => 1435, 'source' => 'osm',
        ]);
        if ($sncfPk !== null) {
            DB::table('sncf_line_geometries')->insert(['code_ligne' => $code, 'libelle' => 'Exploitée',
                'pk_debut_m' => $sncfPk[0], 'pk_fin_m' => $sncfPk[1],
                'geom' => DB::raw(sprintf("ST_GeomFromText('LINESTRING(%F %F, %F %F)', 4326)", $coords[0][1], $coords[0][0], $coords[1][1], $coords[1][0])),
                'created_at' => now(), 'updated_at' => now()]);
        }

        return $line;
    }

    /** @param  list<array{0: Line, 1: int}>  $pks */
    private function station(string $slug, float $lat, float $lng, array $pks): void
    {
        $station = Station::factory()->create([
            'slug' => $slug, 'name' => 'Gare '.strtoupper($slug), 'city' => 'Ville '.strtoupper($slug),
            'geom' => Point::makeGeodetic($lat, $lng), 'status' => 'active',
        ]);
        DB::table('station_eras')->insert(['station_id' => $station->id, 'era_id' => $this->era->id, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now()]);
        foreach ($pks as [$line, $pk]) {
            DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $line->id, 'method' => 'sncf_pk',
                'pk_m' => $pk, 'pk_source' => 'sncf', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function test_legs_follow_the_running_direction_with_line_kilometre_points(): void
    {
        $r = $this->getJson('/api/v1/routebook?from=a&to=d')->assertOk();

        $r->assertJsonPath('from.slug', 'a')->assertJsonPath('to.slug', 'd')->assertJsonPath('era_year', 2030)
            ->assertJsonPath('legs.*.line.code', ['100000', '200000'])
            ->assertJsonPath('legs.0.pk_from_m', 0)->assertJsonPath('legs.0.pk_to_m', 7450)->assertJsonPath('legs.0.direction', 'up')
            ->assertJsonPath('legs.0.pk_precision', 'exact')
            ->assertJsonPath('legs.1.pk_from_m', 50000)->assertJsonPath('legs.1.pk_to_m', 72200)
            ->assertJsonPath('legs.1.from.kind', 'station')->assertJsonPath('legs.1.from.slug', 'b')
            ->assertJsonPath('line_changes.0.to_line', 'rfn-200000')->assertJsonPath('line_changes.0.at.slug', 'b');
        // 0,1° de longitude à 48° N ≈ 7,45 km ; 0,2° de latitude ≈ 22,2 km.
        $this->assertEqualsWithDelta(29.7, $r->json('distance_km'), 0.5);
        $this->assertEqualsWithDelta(7.45, $r->json('legs.1.route_from_km'), 0.2);

        $back = $this->getJson('/api/v1/routebook?from=d&to=a')->assertOk();
        $back->assertJsonPath('legs.*.line.code', ['200000', '100000'])
            ->assertJsonPath('legs.0.direction', 'down')->assertJsonPath('legs.1.direction', 'down')
            ->assertJsonPath('legs.1.pk_from_m', 7450)->assertJsonPath('legs.1.pk_to_m', 0);
    }

    public function test_objects_are_placed_by_kilometre_travelled_in_the_running_direction(): void
    {
        $book = $this->getJson('/api/v1/routebook?from=d&to=a')->assertOk()->json();

        // Tout objet est trié par km parcouru, garde son PK de ligne, sa ligne et sa provenance.
        $kms = array_column($book['switches'], 'route_km');
        $this->assertSame($kms, array_values(collect($kms)->sort()->all()));
        [$onL2, $onL1] = $book['switches'];
        $this->assertSame([60000, 'rfn-200000', '0:sw-2', 'osm'], [$onL2['pk_m'], $onL2['line_slug'], $onL2['id'], $onL2['source']]);
        $this->assertSame([3000, '100000', '1:sw-1', '1:t-1', 'rinf'], [$onL1['pk_m'], $onL1['line_code'], $onL1['id'], $onL1['from_track'], $onL1['source']]);
        // 200000 parcourue de 72 200 à 50 000 : le PK 60 000 est à 55 % de la portion.
        $this->assertEqualsWithDelta(0.5495 * 22.2, $onL2['route_km'], 0.3);
        $this->assertEqualsWithDelta(22.2 + 0.5973 * 7.45, $onL1['route_km'], 0.3);
        // Aiguillage pris en pointe dans le sens des PK croissants : en talon à PK décroissants.
        $this->assertFalse($onL1['travel_facing']);
        $this->assertSame('opposite', $book['signals'][0]['travel']);
        // Hors de la portion parcourue (PK 12 000 de 100000, au-delà de b) : écarté.
        $this->assertNotContains(12000, array_column($book['signals'], 'pk_m'));

        // Déclivité de la ligne en rampe (+2,5 ‰ à PK croissants) : pente dans le sens de marche.
        $gradient = collect((array) $book['strip']['gradients'])->firstWhere('line_slug', 'rfn-100000');
        $this->assertSame([-2.5, 2.5], [$gradient['permille'], $gradient['line_permille']]);
        // Voies coupées aux bornes de la portion ; rangées retournées à PK décroissants.
        $running = collect((array) $book['tracks'])->firstWhere('id', '1:t-1');
        $this->assertSame([0, 7450, 7450], [$running['pk_from_m'], $running['pk_to_m'], $running['length_m']]);
        $this->assertSame([0, 1], [$running['lane'], $running['route_lane']]);
        $this->assertSame(1, collect((array) $book['tracks'])->firstWhere('id', '1:t-2')['lane']);
        $this->assertSame(0, collect((array) $book['tracks'])->firstWhere('id', '1:t-2')['route_lane']);

        // La gare de changement de ligne n'est listée qu'une fois, avec ses deux lignes.
        $b = array_values(array_filter($book['operational_points'], fn (array $p): bool => $p['station_slug'] === 'b'));
        $this->assertCount(1, $b);
        $this->assertSame(['rfn-200000', 'rfn-100000'], $b[0]['lines']);
        $this->assertSame(count($book['operational_points']) + count($book['tracks']) + count($book['switches'])
            + count($book['signals']) + count($book['crossings']), $book['objects']);
        $this->assertSame(['osm', 'rinf'], collect((array) $book['sources'])->pluck('key')->sort()->values()->all());
    }

    public function test_a_line_change_on_open_line_is_placed_on_both_lines(): void
    {
        $r = $this->getJson('/api/v1/routebook?from=c&to=e')->assertOk();

        $r->assertJsonPath('legs.*.line.code', ['100000', '300000'])
            ->assertJsonPath('legs.0.to.kind', 'junction')->assertJsonPath('legs.0.direction', 'down')
            ->assertJsonPath('legs.0.pk_precision', 'derived')
            ->assertJsonPath('legs.1.from.kind', 'junction')->assertJsonPath('legs.1.direction', 'up')
            ->assertJsonPath('line_changes.0.at.kind', 'junction');
        // J à mi-chemin de b et c sur 100000 (forme SNCF), au début de 300000.
        $this->assertEqualsWithDelta(11175, $r->json('legs.0.pk_to_m'), 150);
        $this->assertEqualsWithDelta(0, $r->json('legs.1.pk_from_m'), 150);
        $this->assertSame(11100, $r->json('legs.1.pk_to_m'));
    }

    public function test_a_portion_without_detail_is_said_honestly(): void
    {
        $this->app->instance(TrackLayout::class, new TrackLayout([new FakeSource('osm')]));

        $book = $this->getJson('/api/v1/routebook?from=a&to=d')->assertOk()->json();

        $this->assertFalse($book['legs'][0]['has_detail']);
        $this->assertTrue($book['legs'][1]['has_detail']);
        $missing = collect((array) $book['coverage']['missing'])->firstWhere('line_code', '100000');
        $this->assertSame('all', $missing['what']);
        $this->assertSame(0.0, (float) $missing['route_from_km']);
        $this->assertEqualsWithDelta(7.45, $missing['route_to_km'], 0.2);
        $this->assertSame([], array_values(array_filter($book['switches'], fn (array $s): bool => $s['line_code'] === '100000')));
    }

    /**
     * A park or attraction train without any VFF line (station only "tourist" today, no line: "Petit Train de la Tête
     * d'Or", snapped onto Collonges-Fontaines - Lyon-Guillotière in the middle of Lille-Flandres - Marseille) is not a
     * node of the graph; a tourist station on a line of its own stays one.
     */
    public function test_a_park_train_without_any_line_is_not_on_the_route(): void
    {
        $park = Station::factory()->create(['slug' => 'petit-train', 'name' => 'Petit Train du Parc',
            'geom' => Point::makeGeodetic(48.0001, 2.05), 'status' => 'tourist']);
        DB::table('station_eras')->insert(['station_id' => $park->id, 'era_id' => $this->era->id, 'status' => 'tourist',
            'created_at' => now(), 'updated_at' => now()]);
        $this->artisan('vff:build-network-graph')->assertSuccessful();

        $this->assertFalse(DB::table('station_edges')->where('from_station_id', $park->id)->orWhere('to_station_id', $park->id)->exists());
        $this->assertNotContains('petit-train', $this->getJson('/api/v1/route?from=a&to=c')->assertOk()->json('stations.*.slug'));
        $this->getJson('/api/v1/routebook?from=a&to=c')->assertOk()->assertJsonPath('legs.*.line.code', ['100000'])
            ->assertJsonPath('legs.0.from.slug', 'a')->assertJsonPath('legs.0.to.slug', 'c');

        // With a line of its own, a tourist station is a station of the network.
        DB::table('station_lines')->insert(['station_id' => $park->id, 'line_id' => Line::query()->where('code', '100000')->value('id'),
            'method' => 'sncf_pk', 'pk_m' => 3725, 'pk_source' => 'sncf', 'created_at' => now(), 'updated_at' => now()]);
        $this->artisan('vff:build-network-graph')->assertSuccessful();
        $this->assertContains('petit-train', $this->getJson('/api/v1/route?from=a&to=c')->assertOk()->json('stations.*.slug'));
    }

    /**
     * The route book keeps to passenger tracks: a short freight-only line (penalised four times by `/route`, still the
     * cheapest there) is left for the passenger line; a station served only by a freight line is still reached by the
     * path of `/route`, and `non_passenger_m` says how far.
     *
     *   p ── fret ── m ── fret ── q        p, q : passenger line round by the north (about 26 km), m a freight station
     *                                      q ──── fret ──── f
     */
    public function test_the_route_book_keeps_to_passenger_tracks(): void
    {
        $round = $this->line('500000', 'Ligne de P à Q par le nord', [[47.0, 2.0], [47.1, 2.0], [47.1, 2.05], [47.0, 2.05]], null);
        $freight = $this->line('510000', 'Ligne de fret de P à Q', [[47.0, 2.0], [47.0, 2.05]], null);
        $spur = $this->line('520000', 'Ligne de fret de Q à F', [[47.0, 2.05], [47.0, 2.08]], null);
        LineSegment::query()->whereIn('line_id', [$freight->id, $spur->id])->update(['traffic_type' => 'freight']);
        $this->station('p', 47.0, 2.0, [[$round, 0], [$freight, 0]]);
        $this->station('q', 47.0, 2.05, [[$round, 26000], [$freight, 3800], [$spur, 0]]);
        $this->station('m', 47.0, 2.025, [[$freight, 1900]]);
        $this->station('f', 47.0, 2.08, [[$spur, 2300]]);
        $this->artisan('vff:build-network-graph')->assertSuccessful();

        $this->getJson('/api/v1/route?from=p&to=q')->assertOk()->assertJsonPath('lines.*.line_code', ['510000'])->assertJsonPath('non_passenger_m', fn (int $m): bool => $m > 3000);
        $book = $this->getJson('/api/v1/routebook?from=p&to=q')->assertOk()
            ->assertJsonPath('legs.*.line.code', ['500000'])->assertJsonPath('non_passenger_m', 0);
        $this->assertGreaterThan(25.0, $book->json('distance_km'));

        // F only by freight: the path of `/route` (passenger mode), freight metres said.
        $toF = $this->getJson('/api/v1/routebook?from=p&to=f')->assertOk()->assertJsonPath('to.slug', 'f');
        $this->assertGreaterThan(2000, $toF->json('non_passenger_m'));
    }

    public function test_errors_are_those_of_the_route(): void
    {
        $this->getJson('/api/v1/routebook?from=nowhere&to=a')->assertNotFound()->assertJsonPath('error', 'station_not_found');
        $this->getJson('/api/v1/routebook?from=a&to=a')->assertStatus(422)->assertJsonPath('error', 'same_station');
        $this->getJson('/api/v1/routebook?from=a')->assertUnprocessable();
    }

    public function test_query_budget_does_not_grow_with_the_number_of_lines(): void
    {
        $count = function (string $url): int {
            $n = 0;
            DB::listen(function (QueryExecuted $q) use (&$n): void {
                $n++;
            });
            $this->getJson($url)->assertOk();

            return $n;
        };
        $this->app['cache']->flush();
        $short = $count('/api/v1/routebook?from=a&to=d');
        $this->app['cache']->flush();
        $long = $count('/api/v1/routebook?from=c&to=e');

        // Plus court chemin (ères, graphe, gares : 8 lectures), gares et arêtes, lignes, points de changement,
        // PK (2), cache : aucune lecture par portion ni par objet (la source factice n'en fait aucune).
        $this->assertLessThanOrEqual(16, $short);
        $this->assertSame($short + 2, $long, 'un changement de ligne en pleine voie ajoute deux lectures : son point, son PK sur la forme SNCF');
        // Seconde visite : le livret est lu dans le cache.
        $this->assertLessThanOrEqual(3, $count('/api/v1/routebook?from=c&to=e'));
    }

    public function test_graphql_and_mcp_share_the_routebook(): void
    {
        $this->graphQL('{ routebook(from: "d", to: "a") { from { slug } distance_km objects legs { index line_code direction pk_from_m pk_to_m from { kind slug } } switches } }')
            ->assertJsonPath('data.routebook.from.slug', 'd')
            ->assertJsonPath('data.routebook.legs.*.line_code', ['200000', '100000'])
            ->assertJsonPath('data.routebook.legs.1.direction', 'down')
            ->assertJsonPath('data.routebook.switches.1.id', '1:sw-1');
        $this->graphQL('{ routebook(from: "nowhere", to: "a") { distance_m } }')->assertJsonPath('data.routebook', null);

        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
                'params' => ['name' => 'routebook', 'arguments' => ['from' => 'a', 'to' => 'd', 'kinds' => ['signals']]]])
            ->assertOk()->json('result');
        $this->assertFalse($result['isError']);
        $data = json_decode($result['content'][0]['text'], true);
        $this->assertSame(['100000', '200000'], array_column($data['legs'], 'code'));
        $this->assertArrayHasKey('signals', $data);
        $this->assertArrayNotHasKey('switches', $data);
        $this->assertStringEndsWith('/routebook?from=a&to=d', $data['vff_url']);
    }

    /**
     * Every reference of the routebook names an object of the routebook (review of 25/11/2026: on Lille-Flandres -
     * Marseille-St-Charles, 842 `op_id` of tracks and 96 `tracks` of crossings kept the identifiers of their line while
     * the objects they cite were prefixed by their leg), and the buffer stops and milestones of the track layout reach
     * the routebook, which the page already draws.
     */
    public function test_every_reference_names_an_object_of_the_routebook_with_buffer_stops_and_milestones(): void
    {
        $this->app->instance(TrackLayout::class, new TrackLayout([new ReferencesSource('rinf'), new FakeSource('osm')]));

        $book = $this->getJson('/api/v1/routebook?from=d&to=a')->assertOk()->json();

        $tracks = array_column($book['tracks'], 'id');
        $points = array_column($book['operational_points'], 'id');
        // Station b ends leg 0 and starts leg 1: listed once, as the point of leg 0, which its tracks of leg 1 cite.
        $this->assertSame(['0:op-b', '1:op-a'], array_values(array_filter($points, fn (string $id): bool => in_array($id, ['0:op-b', '1:op-b', '1:op-a'], true))));
        $this->assertSame('0:op-b', collect((array) $book['tracks'])->firstWhere('id', '1:t-2')['op_id']);
        foreach ($book['tracks'] as $t) {
            $this->assertTrue($t['op_id'] === null || in_array($t['op_id'], $points, true), (string) $t['op_id']);
        }
        $diamond = collect((array) $book['crossings'])->firstWhere('id', '1:x-1');
        $this->assertSame(['1:t-1', '1:t-2'], $diamond['tracks']);
        $this->assertSame([['1:bs-1', '1:t-2', 6800]], array_map(fn (array $b): array => [$b['id'], $b['track'], $b['pk_m']], $book['buffer_stops']));
        $this->assertSame([['1:km-5', '5', 5000]], array_map(fn (array $m): array => [$m['id'], $m['label'], $m['pk_m']], $book['milestones']));
        foreach ([...$book['buffer_stops'], ...$book['milestones']] as $o) {
            $this->assertSame([1, 'rfn-100000'], [$o['leg'], $o['line_slug']]);
            $this->assertArrayHasKey('route_km', $o);
        }
        foreach ([...array_column($book['buffer_stops'], 'track'), ...$diamond['tracks']] as $id) {
            $this->assertContains($id, $tracks);
        }
        // The other objects of the routebook keep their count (`objects`).
        $this->assertSame(count($book['operational_points']) + count($book['tracks']) + count($book['switches'])
            + count($book['signals']) + count($book['crossings']), $book['objects']);

        $this->graphQL('{ routebook(from: "d", to: "a") { buffer_stops milestones } }')
            ->assertJsonPath('data.routebook.buffer_stops.0.track', '1:t-2')
            ->assertJsonPath('data.routebook.milestones.0.label', '5');
    }

    /**
     * The departure and the arrival are always named: the point of the station is kept at the end of its leg when the
     * source places it beyond the PK of the station on its line (Lille-Flandres on 289000: station PK 0+654, RINF point
     * PK 0+021, missing from the routebook to Marseille-St-Charles on 25/11/2026); another point beyond the leg stays out.
     */
    public function test_the_point_of_an_end_station_placed_beyond_the_leg_is_kept_at_the_end(): void
    {
        $this->app->instance(TrackLayout::class, new TrackLayout([new ReferencesSource('rinf', -300), new FakeSource('osm')]));

        $book = $this->getJson('/api/v1/routebook?from=d&to=a')->assertOk()->json();

        $a = collect((array) $book['operational_points'])->firstWhere('station_slug', 'a');
        $this->assertSame(['1:op-a', -300, round($book['distance_m'] / 1000, 3)], [$a['id'], $a['pk_m'], $a['route_km']]);
        $this->assertSame('1:op-a', $book['operational_points'][count($book['operational_points']) - 1]['id']);
        $this->assertNotContains('1:op-j', array_column($book['operational_points'], 'id'));
    }

    /**
     * The `vff` source of the track layout serves SNCF Réseau speeds and level crossings (ODbL) and gradients computed on
     * IGN altitudes (Licence Ouverte 2.0): the MCP routebook credits both, as line_track_layout does (on Ajaccio - Bastia,
     * 25/11/2026, its gradients came with no IGN mention).
     */
    public function test_the_mcp_routebook_credits_the_providers_behind_the_vff_source(): void
    {
        $this->app->instance(TrackLayout::class, new TrackLayout([new ReferencesSource('rinf'), new ReferencesSource('vff')]));

        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
                'params' => ['name' => 'routebook', 'arguments' => ['from' => 'd', 'to' => 'a', 'kinds' => ['strip', 'buffer_stops', 'milestones']]]])
            ->assertOk()->json('result');
        $this->assertFalse($result['isError']);
        $data = json_decode($result['content'][0]['text'], true);

        $this->assertContains('vff', array_column($data['strip']['gradients'], 'source'));
        $providers = array_column((array) $data['attribution']['sources'], 'provider');
        $this->assertContains('ign', $providers);
        $this->assertContains('sncf', $providers);
        $this->assertContains('rinf', $providers);
        $this->assertSame(['1:bs-1'], array_column($data['buffer_stops'], 'id'));
        $this->assertSame(['1:km-5'], array_column($data['milestones'], 'id'));
    }

    public function test_page_shows_the_routebook_and_the_form_alone_without_stations(): void
    {
        $this->get('/routebook?from=a&to=d')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Routebook/Show')
            ->where('book.to.slug', 'd')
            ->where('book.legs.1.line.code', '200000')
            ->where('error', null));

        $this->get('/routebook?from=a')->assertOk()->assertInertia(fn ($page) => $page
            ->where('query.from', 'a')->where('book', null)->where('error', null));

        $this->get('/routebook?from=a&to=nowhere')->assertOk()->assertInertia(fn ($page) => $page
            ->where('book', null)->where('error.error', 'station_not_found')->where('error.side', 'to'));
    }
}

/**
 * Source de schéma des voies factice (TrackLayoutSource, objets au format du contrat sans `lane`, PK de la ligne) :
 * quelques objets sur 100000 (clé `rinf`) ou sur 200000 (clé `osm`). La portion demandée n'est pas filtrée ici
 * (TrackLayout et le livret coupent eux-mêmes).
 */
final class FakeSource implements TrackLayoutSource
{
    public function __construct(private readonly string $key) {}

    public function key(): string
    {
        return $this->key;
    }

    public function part(Line $line, ?int $fromM = null, ?int $toM = null): TrackLayoutPart
    {
        if ($this->key === 'rinf' && $line->code === '100000') {
            return new TrackLayoutPart(
                source: ['key' => 'rinf', 'label' => 'RINF (ERA)', 'licence' => 'CC BY 4.0', 'dataset' => null, 'fetched_at' => null, 'url' => null],
                operationalPoints: [
                    ['id' => 'op-a', 'name' => 'Gare A', 'kind' => 'station', 'pk_m' => 0, 'uopid' => null, 'station_slug' => 'a', 'source' => 'rinf', 'precision' => 'exact'],
                    ['id' => 'op-b', 'name' => 'Gare B', 'kind' => 'station', 'pk_m' => 7450, 'uopid' => null, 'station_slug' => 'b', 'source' => 'rinf', 'precision' => 'exact'],
                ],
                tracks: [
                    ['id' => 't-1', 'ref' => '1', 'kind' => 'running', 'pk_from_m' => 0, 'pk_to_m' => 14900, 'op_id' => null, 'length_m' => 14900, 'electrified' => true, 'platform' => false, 'source' => 'rinf', 'precision' => 'exact'],
                    ['id' => 't-2', 'ref' => '2', 'kind' => 'siding', 'pk_from_m' => 6800, 'pk_to_m' => 7900, 'op_id' => 'op-b', 'length_m' => 1100, 'electrified' => true, 'platform' => false, 'source' => 'rinf', 'precision' => 'exact'],
                ],
                switches: [['id' => 'sw-1', 'ref' => null, 'pk_m' => 3000, 'kind' => 'simple', 'from_track' => 't-1', 'to_track' => 't-2', 'facing' => 'up', 'source' => 'rinf', 'precision' => 'exact']],
                signals: [
                    ['id' => 'sig-1', 'ref' => 'C1', 'pk_m' => 5000, 'kind' => 'main', 'track' => 't-1', 'direction' => 'up', 'source' => 'rinf', 'precision' => 'exact'],
                    ['id' => 'sig-2', 'ref' => 'C2', 'pk_m' => 12000, 'kind' => 'main', 'track' => 't-1', 'direction' => 'up', 'source' => 'rinf', 'precision' => 'exact'],
                ],
                speeds: [['pk_from_m' => 0, 'pk_to_m' => 14900, 'kmh' => 160, 'source' => 'rinf']],
                gradients: [['pk_from_m' => 0, 'pk_to_m' => 14900, 'permille' => 2.5, 'source' => 'rinf', 'precision' => 'derived']],
                covered: array_fill_keys(['tracks', 'switches', 'signals', 'crossings', 'operational_points'], [[0, 14900]]),
            );
        }
        if ($this->key === 'osm' && $line->code === '200000') {
            return new TrackLayoutPart(
                source: ['key' => 'osm', 'label' => 'OpenStreetMap', 'licence' => 'ODbL', 'dataset' => null, 'fetched_at' => null, 'url' => null],
                operationalPoints: [
                    ['id' => 'op-b', 'name' => 'Gare B', 'kind' => 'station', 'pk_m' => 50000, 'uopid' => null, 'station_slug' => 'b', 'source' => 'osm', 'precision' => 'derived'],
                    ['id' => 'op-d', 'name' => 'Gare D', 'kind' => 'station', 'pk_m' => 72200, 'uopid' => null, 'station_slug' => 'd', 'source' => 'osm', 'precision' => 'derived'],
                ],
                tracks: [['id' => 't-1', 'ref' => null, 'kind' => 'running', 'pk_from_m' => 50000, 'pk_to_m' => 72200, 'op_id' => null, 'length_m' => 22200, 'electrified' => false, 'platform' => false, 'source' => 'osm', 'precision' => 'derived']],
                switches: [['id' => 'sw-2', 'ref' => null, 'pk_m' => 60000, 'kind' => 'simple', 'from_track' => null, 'to_track' => null, 'facing' => null, 'source' => 'osm', 'precision' => 'derived']],
                crossings: [['id' => 'pn-1', 'pk_m' => 65000, 'kind' => 'level_crossing', 'ref' => 'PN 12', 'source' => 'osm']],
                covered: ['tracks' => [[50000, 72200]], 'switches' => [[50000, 72200]]],
            );
        }

        return TrackLayoutPart::empty();
    }
}

/**
 * Track layout source of the reference test: on 100000 (key `rinf`), the points of a and b, track 1 and siding 2 of
 * station b, a diamond crossing of both, the buffer stop of siding 2 and milestone 5; on 200000 (key `vff`), a
 * gradient of the long profile.
 */
final class ReferencesSource implements TrackLayoutSource
{
    /** @param  int  $stationAPk  PK of the point of station a (0: the PK of the station on its line) */
    public function __construct(private readonly string $key, private readonly int $stationAPk = 0) {}

    public function key(): string
    {
        return $this->key;
    }

    public function part(Line $line, ?int $fromM = null, ?int $toM = null): TrackLayoutPart
    {
        if ($this->key === 'rinf' && $line->code === '100000') {
            return new TrackLayoutPart(
                source: ['key' => 'rinf', 'label' => 'RINF (ERA)', 'licence' => 'EUPL 1.2', 'dataset' => null, 'fetched_at' => null, 'url' => null],
                operationalPoints: [
                    ['id' => 'op-a', 'name' => 'Gare A', 'kind' => 'station', 'pk_m' => $this->stationAPk, 'uopid' => null, 'station_slug' => 'a', 'source' => 'rinf', 'precision' => 'exact'],
                    ['id' => 'op-b', 'name' => 'Gare B', 'kind' => 'station', 'pk_m' => 7450, 'uopid' => null, 'station_slug' => 'b', 'source' => 'rinf', 'precision' => 'exact'],
                    ['id' => 'op-j', 'name' => 'Bif', 'kind' => 'junction', 'pk_m' => -200, 'uopid' => null, 'station_slug' => null, 'source' => 'rinf', 'precision' => 'exact'],
                ],
                tracks: [
                    ['id' => 't-1', 'ref' => '1', 'kind' => 'running', 'pk_from_m' => 0, 'pk_to_m' => 14900, 'op_id' => null, 'length_m' => 14900, 'source' => 'rinf', 'precision' => 'exact'],
                    ['id' => 't-2', 'ref' => '2', 'kind' => 'siding', 'pk_from_m' => 6800, 'pk_to_m' => 7900, 'op_id' => 'op-b', 'length_m' => 1100, 'source' => 'rinf', 'precision' => 'exact'],
                ],
                crossings: [['id' => 'x-1', 'pk_m' => 7000, 'kind' => 'railway_crossing', 'ref' => null, 'tracks' => ['t-1', 't-2'], 'source' => 'rinf', 'precision' => 'exact']],
                covered: array_fill_keys(['tracks', 'switches', 'signals', 'crossings', 'operational_points'], [[0, 14900]]),
                bufferStops: [['id' => 'bs-1', 'pk_m' => 6800, 'track' => 't-2', 'source' => 'rinf', 'precision' => 'exact']],
                milestones: [['id' => 'km-5', 'pk_m' => 5000, 'label' => '5', 'source' => 'rinf', 'precision' => 'exact']],
            );
        }
        if ($this->key === 'vff' && $line->code === '200000') {
            return new TrackLayoutPart(
                source: ['key' => 'vff', 'label' => 'VFF', 'licence' => 'ODbL 1.0', 'dataset' => null, 'fetched_at' => null, 'url' => null],
                gradients: [['pk_from_m' => 50000, 'pk_to_m' => 72200, 'permille' => 3.0, 'source' => 'vff', 'precision' => 'derived']],
            );
        }

        return TrackLayoutPart::empty();
    }
}
