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
