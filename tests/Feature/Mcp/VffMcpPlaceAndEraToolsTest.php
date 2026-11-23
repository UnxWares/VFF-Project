<?php

namespace Tests\Feature\Mcp;

use App\Models\Accident;
use App\Models\Line;
use App\Models\LineSegment;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Outils de lieu et d'époque du serveur MCP : what_was_here, network_in_area, compare_line_eras, line_stops et
 * station_aerial. Règle commune vérifiée ici : les faits d'une source à usage non commercial (archeoferroviaire)
 * ne sortent jamais, et un tracé non redistribuable ne donne ni coordonnée ni distance précise.
 *
 * Réseau : BuildsRailNetwork (ère 1960 et ère courante 2030, ligne Paris-Saint-Lazare - Versailles-Rive-Droite
 * tracée par OSM aux deux ères), plus une ligne fermée tracée par archeoferroviaire en 1960, des états de gare,
 * deux événements de 1960 (Wikipédia, archeoferroviaire), une période de matériel et un accident de 1960.
 */
class VffMcpPlaceAndEraToolsTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    /** Point sur le tracé, entre les deux gares. */
    private const LAT = 48.8530;

    private const LNG = 2.2400;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->buildRailNetwork();
        $now = now();

        // Ligne voisine fermée en 1960, tracée d'après archeoferroviaire (géométrie non redistribuable).
        $closed = Line::factory()->create(['slug' => 'archeo-ligne-voisine', 'name' => 'Ligne voisine', 'code' => null]);
        LineSegment::factory()->create([
            'line_id' => $closed->id, 'era_id' => $this->pastEra->id, 'status' => 'abandoned', 'traffic_type' => 'unknown',
            'electrified' => false, 'is_heritage' => false, 'source' => 'archeoferroviaire', 'pk_start_m' => 0, 'pk_end_m' => 5_000,
            'geom' => LineString::make([Point::makeGeodetic(48.8560, 2.2300), Point::makeGeodetic(48.8560, 2.2500)]),
        ]);

        DB::table('station_eras')->insert([
            ['station_id' => $this->origin->id, 'era_id' => $this->pastEra->id, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['station_id' => $this->destination->id, 'era_id' => $this->pastEra->id, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['station_id' => $this->origin->id, 'era_id' => $this->currentEra->id, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['station_id' => $this->destination->id, 'era_id' => $this->currentEra->id, 'status' => 'declassified', 'created_at' => $now, 'updated_at' => $now],
        ]);
        // En 1960, les deux gares sont ouvertes ; aujourd'hui Versailles-Rive-Droite est fermée (line_stops de 2030 corrigé).
        DB::table('line_stops')->where('era_id', $this->currentEra->id)->where('station_id', $this->destination->id)->update(['effective_status' => 'declassified']);
        DB::table('line_stops')->insert([
            ['line_id' => $this->line->id, 'era_id' => $this->pastEra->id, 'station_id' => $this->origin->id, 'position' => 1, 'pk_m' => 0,
                'effective_status' => 'active', 'is_terminal' => true, 'created_at' => $now, 'updated_at' => $now],
            ['line_id' => $this->line->id, 'era_id' => $this->pastEra->id, 'station_id' => $this->destination->id, 'position' => 2, 'pk_m' => 17_250,
                'effective_status' => 'active', 'is_terminal' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('line_events')->insert([
            ['line_id' => $this->line->id, 'type' => 'electrification', 'event_date' => '1962-05-01', 'date_precision' => 'month',
                'description' => 'Électrification de la ligne.', 'source' => 'wikipedia', 'source_url' => 'https://fr.wikipedia.org/wiki/X',
                'created_at' => $now, 'updated_at' => $now],
            ['line_id' => $this->line->id, 'type' => 'modernization', 'event_date' => '1962-09-01', 'date_precision' => 'month',
                'description' => 'Fait relevé sur archeoferroviaire.', 'source' => 'archeoferroviaire', 'source_url' => 'http://archeoferroviaire.free.fr/x',
                'created_at' => $now, 'updated_at' => $now],
            // Lien vers archeoferroviaire sous une source composite : écarté lui aussi.
            ['line_id' => $this->line->id, 'type' => 'other', 'event_date' => '1962-10-01', 'date_precision' => 'month',
                'description' => 'Fait manuel citant archeoferroviaire.', 'source' => 'manual', 'source_url' => 'http://archeoferroviaire.free.fr/y',
                'created_at' => $now, 'updated_at' => $now],
        ]);

        $link = DB::table('line_materials')->where('line_id', $this->line->id)->where('material_id', $this->material->id)->value('id');
        DB::table('line_material_periods')->insert([
            'line_material_id' => $link, 'start_date' => '1960-01-01', 'start_precision' => 'year', 'end_date' => '1975-01-01', 'end_precision' => 'year',
            'confidence' => 'high', 'origin' => 'line_article', 'source' => 'wikipedia', 'source_url' => 'https://fr.wikipedia.org/wiki/Z_6400',
            'fingerprint' => 'test-z6400', 'created_at' => $now, 'updated_at' => $now,
        ]);

        Accident::factory()->on('1962-03-04')->at(48.8531, 2.2405)->create(['slug' => '1962-03-04-test', 'kind' => 'derailment']);
        Accident::factory()->on('1980-03-04')->at(48.8531, 2.2405)->create(['slug' => '1980-03-04-test', 'kind' => 'derailment']);
    }

    public function test_what_was_here_describes_the_lines_stations_materials_and_accidents_of_the_year(): void
    {
        [$data, $error] = $this->tool('what_was_here', ['lat' => self::LAT, 'lng' => self::LNG, 'year' => 1962, 'radius_m' => 1000]);

        $this->assertFalse($error);
        $this->assertSame(1962, $data['year']);
        $this->assertSame(1960, $data['era']['year']);
        $this->assertSame(['paris-saint-lazare-versailles-rd', 'archeo-ligne-voisine'], array_column($data['lines'], 'slug'), 'la plus proche d\'abord');

        $line = $data['lines'][0];
        $this->assertSame('in_service', $line['status']);
        $this->assertTrue($line['electrified']);
        $this->assertSame(1500, $line['voltage_v']);
        $this->assertFalse($line['geometry_withheld']);
        $this->assertLessThan(5, $line['distance_m']);
        $this->assertSame(['z-6400-test'], array_column($line['materials'], 'slug'));
        $this->assertSame('dated_period', $line['materials'][0]['certainty']);
        $this->assertSame(['electrification'], array_column($line['events'], 'type'), 'événements non commerciaux écartés');

        $other = $data['lines'][1];
        $this->assertTrue($other['geometry_withheld']);
        $this->assertNull($other['distance_m'], 'pas de distance à un tracé non redistribuable');
        $this->assertSame(0, $other['pk_m'] % 100, 'PK arrondi à 100 m');
        $this->assertSame('abandoned', $other['status']);

        $this->assertSame(['1962-03-04-test'], array_column($data['accidents'], 'slug'), 'accidents de l\'année seulement');
        $this->assertNotContains('paris-saint-lazare', array_column($data['stations'], 'slug'), 'gare hors du rayon');
        $this->assertStringContainsString('center=2.24000,48.85300', $data['links']['map']);
        // Aucun fait ni lien de la source non commerciale dans les données (son crédit reste dans `attribution`,
        // le statut d'une ligne qu'elle seule trace étant un calcul de VFF).
        $this->assertStringNotContainsString('archeoferroviaire.free.fr', (string) json_encode(array_diff_key($data, ['attribution' => 1]), JSON_UNESCAPED_SLASHES));
    }

    public function test_what_was_here_validates_its_arguments_and_finds_nothing_far_away(): void
    {
        $response = $this->rpc('tools/call', ['name' => 'what_was_here', 'arguments' => (object) ['lat' => 48.85, 'lng' => 2.24, 'radius_m' => 50_000]]);
        $this->assertTrue($response->json('result.isError') ?? false, 'rayon trop grand refusé');

        [$data, $error] = $this->tool('what_was_here', ['lat' => 43.3, 'lng' => 5.4, 'year' => 1962]);
        $this->assertFalse($error);
        $this->assertSame([], $data['lines']);
        $this->assertSame([], $data['accidents']);
    }

    public function test_network_in_area_measures_the_network_inside_the_area_only(): void
    {
        [$data, $error] = $this->tool('network_in_area', ['south' => 48.80, 'west' => 2.10, 'north' => 48.90, 'east' => 2.35, 'year' => 1965]);

        $this->assertFalse($error);
        $this->assertSame(1960, $data['era']['year']);
        $this->assertSame(2, $data['lines_total']);
        $this->assertGreaterThan(10, $data['totals']['open_km']);
        $this->assertGreaterThan(1, $data['totals']['closed_km']);
        $this->assertSame($data['totals']['open_km'], $data['totals']['electrified_km']);
        $this->assertSame('paris-saint-lazare-versailles-rd', $data['lines'][0]['slug']);
        $this->assertSame('passenger', $data['lines'][0]['main_traffic']);
        $this->assertSame(2, $data['stations_total']);
        $this->assertSame(['active' => 2], $data['stations_by_status']);

        // Cercle de 2 km autour de Paris-Saint-Lazare : une partie du tracé seulement.
        [$circle] = $this->tool('network_in_area', ['lat' => 48.8763, 'lng' => 2.3253, 'radius_km' => 2]);
        $this->assertSame(2030, $circle['era']['year']);
        $this->assertLessThan($data['totals']['open_km'], $circle['totals']['open_km']);
        $this->assertGreaterThan(1, $circle['totals']['open_km']);
        $this->assertSame(['paris-saint-lazare'], array_column($circle['stations'], 'slug'));
    }

    public function test_network_in_area_refuses_invalid_and_oversized_areas(): void
    {
        [$mixed, $error] = $this->tool('network_in_area', ['south' => 48.8, 'west' => 2.1, 'north' => 48.9, 'east' => 2.3, 'lat' => 48.8]);
        $this->assertTrue($error);
        $this->assertSame('invalid_area', $mixed['error']);

        [$inverted] = $this->tool('network_in_area', ['south' => 48.9, 'west' => 2.1, 'north' => 48.8, 'east' => 2.3]);
        $this->assertSame('invalid_area', $inverted['error']);

        [$large] = $this->tool('network_in_area', ['south' => 47.0, 'west' => 2.1, 'north' => 48.9, 'east' => 2.3]);
        $this->assertSame('area_too_large', $large['error']);

        [$small] = $this->tool('network_in_area', ['south' => 48.85, 'west' => 2.24, 'north' => 48.852, 'east' => 2.30]);
        $this->assertSame('area_too_small', $small['error'], 'pas de zone minuscule qui redessinerait un tracé retenu');

        $response = $this->rpc('tools/call', ['name' => 'network_in_area', 'arguments' => (object) ['lat' => 48.8, 'lng' => 2.2, 'radius_km' => 80]]);
        $this->assertTrue($response->json('result.isError') ?? false, 'rayon de plus de 50 km refusé par la validation');
    }

    public function test_compare_line_eras_reports_closed_stations_and_the_events_between_the_two_eras(): void
    {
        [$data, $error] = $this->tool('compare_line_eras', ['line' => $this->line->slug, 'from' => 2026, 'to' => 1963]);

        $this->assertFalse($error);
        $this->assertSame(1960, $data['from']['year'], 'ères remises dans l\'ordre');
        $this->assertSame(2030, $data['to']['year']);
        $this->assertSame(['active' => 2], $data['from']['stations']);
        $this->assertSame(['active' => 1, 'declassified' => 1], $data['to']['stations']);
        $this->assertSame(1, $data['changes']['stations_closed_total']);
        $this->assertSame('versailles-rive-droite', $data['changes']['stations_closed'][0]['slug']);
        $this->assertSame('declassified', $data['changes']['stations_closed'][0]['status_to']);
        $this->assertSame(0, $data['changes']['stations_opened_total']);
        $this->assertSame(1, $data['events_total'], 'les faits non commerciaux ne sont pas comptés');
        $this->assertSame('electrification', $data['events'][0]['type']);

        [$same] = $this->tool('compare_line_eras', ['line' => $this->line->slug, 'from' => 1961, 'to' => 1969]);
        $this->assertSame('same_era', $same['error']);
    }

    public function test_line_stops_lists_the_stops_of_the_era_with_the_gtfs_link(): void
    {
        [$data, $error] = $this->tool('line_stops', ['line' => '999000', 'year' => 1964]);

        $this->assertFalse($error);
        $this->assertSame(1960, $data['era']['year']);
        $this->assertSame(['paris-saint-lazare', 'versailles-rive-droite'], array_column(array_column($data['stops'], 'station'), 'slug'));
        $this->assertSame('17+250', $data['stops'][1]['pk_label']);
        $this->assertStringEndsWith('/api/v1/eras/1960/gtfs.zip', $data['gtfs']['url']);

        [$today] = $this->tool('line_stops', ['line' => $this->line->slug, 'only_open' => true]);
        $this->assertSame(2030, $today['era']['year']);
        $this->assertSame(['paris-saint-lazare'], array_column(array_column($today['stops'], 'station'), 'slug'));

        $rest = $this->getJson("/api/v1/lines/{$this->line->slug}/stops?era=1960")->assertOk()->json('stops');
        $this->assertSame(array_column($rest, 'pk_m'), array_column($data['stops'], 'pk_m'), 'mêmes PK que l\'API REST');
    }

    public function test_station_aerial_gives_absolute_links_and_the_ign_licence(): void
    {
        config(['vff.media.aerial.enabled' => true]);
        [$data, $error] = $this->tool('station_aerial', ['station' => 'paris-saint-lazare']);

        $this->assertFalse($error);
        $this->assertSame(3, $data['count']);
        $this->assertSame('1950-1965', $data['views'][0]['period']);
        $this->assertStringStartsWith((string) config('mcp.vff.public_url').'/media/aerial/paris-saint-lazare/', $data['views'][0]['url']);
        $this->assertSame('Licence Ouverte 2.0', $data['views'][0]['license']);
        $this->assertContains('ign', array_column($data['attribution']['sources'], 'provider'));

        config(['vff.media.aerial.enabled' => false]);
        [$off] = $this->tool('station_aerial', ['station' => 'paris-saint-lazare']);
        $this->assertSame([], $off['views']);

        [$missing, $error] = $this->tool('station_aerial', ['station' => 'gare-inconnue']);
        $this->assertTrue($error);
        $this->assertSame('station_not_found', $missing['error']);
    }

    public function test_line_events_and_network_events_never_serve_a_non_commercial_source(): void
    {
        [$events] = $this->tool('line_events', ['line' => $this->line->slug]);
        $this->assertNotContains('archeoferroviaire', array_column($events['events'], 'source'));
        $this->assertNotContains('manual', array_column($events['events'], 'source'), 'lien vers archeoferroviaire écarté');

        [$network] = $this->tool('network_events', ['line' => $this->line->slug]);
        $this->assertNotContains('archeoferroviaire', array_column($network['events'], 'source'));
        $this->assertSame(2, $network['count'], 'ouverture et électrification seulement');

        [$sheet] = $this->tool('get_line', ['line' => $this->line->slug]);
        $this->assertSame(2, $sheet['line']['event_count']);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function tool(string $tool, array $arguments = []): array
    {
        $result = $this->rpc('tools/call', ['name' => $tool, 'arguments' => (object) $arguments])->assertOk()->json('result');
        $text = $result['content'][0]['text'];

        return [json_decode($text, true) ?? ['raw' => $text], (bool) ($result['isError'] ?? false)];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return TestResponse<Response>
     */
    private function rpc(string $method, array $params = []): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params]);
    }
}
