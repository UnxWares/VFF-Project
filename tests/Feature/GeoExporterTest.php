<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use App\Services\GeoExporter;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\DecodesMvt;
use Tests\TestCase;

/**
 * App\Services\GeoExporter : propriétés exportées vers la carte (tuiles MVT,
 * GeoJSON des lignes et des gares). Les tuiles sont décodées (DecodesMvt) :
 * on vérifie la VALEUR de chaque attribut par entité.
 *
 * Tuile 8/129/88 : environ 1,4°-2,8° E, 48,0°-49,0° N (Île-de-France).
 */
class GeoExporterTest extends TestCase
{
    use DecodesMvt;
    use RefreshDatabase;

    private Era $current;

    private Era $past;

    protected function setUp(): void
    {
        parent::setUp();
        $this->past = Era::factory()->create(['year' => 1960]);
        $this->current = Era::factory()->current()->create(['year' => 2030]);
    }

    public function test_tile_advocacy_kind_comes_from_the_campaign_then_the_threatened_flag(): void
    {
        $reopening = $this->line('Ligne de Alpha à Bravo', 48.50, ['advocacy' => ['kind' => 'reopening', 'title' => 'Rouvrons-la']]);
        $threatened = $this->line('Ligne de Charlie à Delta', 48.55, ['is_threatened' => true]);
        $both = $this->line('Ligne de Echo à Foxtrot', 48.60, ['is_threatened' => true, 'advocacy' => ['kind' => 'reopening']]);
        $none = $this->line('Ligne de Golf à Hotel', 48.65);

        $props = $this->tileProps();

        $this->assertSame('reopening', $props[$reopening->id]['advocacy_kind']);
        $this->assertSame('threatened', $props[$threatened->id]['advocacy_kind']);
        $this->assertTrue($props[$threatened->id]['is_threatened']);
        $this->assertSame('reopening', $props[$both->id]['advocacy_kind'], 'la campagne prime sur le simple drapeau');
        $this->assertArrayNotHasKey('advocacy_kind', $props[$none->id], 'pas de campagne : attribut absent (NULL)');
        $this->assertFalse($props[$none->id]['is_threatened']);
    }

    public function test_tile_exports_heritage_lgv_and_traffic_per_segment(): void
    {
        $velorail = $this->line('Ligne de India à Juliett', 48.50, segment: ['status' => 'abandoned', 'traffic_type' => 'unknown',
            'is_heritage' => true, 'heritage_kind' => 'velorail']);
        $tourist = $this->line('Ligne de Kilo à Lima', 48.55, segment: ['status' => 'in_service', 'traffic_type' => 'unknown',
            'is_heritage' => true, 'heritage_kind' => 'tourist_train']);
        $lgvByName = $this->line('LGV Interconnexion Est', 48.60, segment: ['traffic_type' => 'passenger']);
        $lgvBySpeed = $this->line('Ligne nouvelle de Mike', 48.65, segment: ['traffic_type' => 'passenger', 'max_speed_kmh' => 300]);
        $freight = $this->line('Ligne de November à Oscar', 48.70, ['network' => 'particulier'], segment: ['traffic_type' => 'freight']);

        $props = $this->tileProps();

        $this->assertSame(['abandoned', 'velorail', true], [$props[$velorail->id]['status'], $props[$velorail->id]['heritage_kind'], $props[$velorail->id]['is_heritage']]);
        $this->assertSame(['in_service', 'tourist_train'], [$props[$tourist->id]['status'], $props[$tourist->id]['heritage_kind']]);
        $this->assertTrue($props[$lgvByName->id]['is_lgv']);
        $this->assertTrue($props[$lgvBySpeed->id]['is_lgv']);
        $this->assertFalse($props[$freight->id]['is_lgv']);
        $this->assertSame(['freight', 'particulier'], [$props[$freight->id]['traffic_type'], $props[$freight->id]['network']]);
        $this->assertArrayNotHasKey('heritage_kind', $props[$freight->id]);
        $this->assertFalse($props[$freight->id]['is_heritage']);
        // Agrégats ligne : COALESCE(ligne, tronçon).
        $this->assertSame(300, $props[$lgvBySpeed->id]['line_max_speed']);
    }

    public function test_tile_only_contains_the_requested_era(): void
    {
        $line = $this->line('Ligne de Papa à Quebec', 48.50, segment: ['status' => 'in_service']);
        DB::table('line_segments')->where('line_id', $line->id)->update(['era_id' => $this->past->id]);

        $this->assertSame([], $this->tileProps());
        $this->assertArrayHasKey($line->id, $this->tileProps(1960));
    }

    public function test_tile_without_any_segment_has_no_layer(): void
    {
        $this->line('Ligne de Romeo à Sierra', 48.50);

        // Tuile du Pacifique : rien.
        $this->assertSame([], $this->decodeMvt(app(GeoExporter::class)->linesAsMvt(2030, 8, 0, 0)));
    }

    public function test_streamed_geojson_carries_heritage_and_lgv_flags(): void
    {
        $lgv = $this->line('Ligne de Tango à Uniform', 48.50, ['code' => '752000'], segment: ['traffic_type' => 'passenger']);
        DB::table('sncf_line_catlig')->insert(['code_ligne' => '752000', 'catlig' => 'Ligne à grande vitesse']);
        $museum = $this->line('Ligne de Victor à Whisky', 48.55, segment: ['is_heritage' => true, 'heritage_kind' => 'museum', 'source' => 'osm']);

        $features = collect(iterator_to_array(app(GeoExporter::class)->streamLinesGeoJson(2030), false))->keyBy('properties.line_id');

        $this->assertTrue($features[$lgv->id]['properties']['is_lgv'], 'catégorie SNCF « grande vitesse »');
        $this->assertSame('museum', $features[$museum->id]['properties']['heritage_kind']);
        $this->assertTrue($features[$museum->id]['properties']['is_heritage']);
        $this->assertFalse($features[$museum->id]['properties']['is_lgv']);
        $this->assertSame('LineString', $features[$museum->id]['geometry']['type']);
        $this->assertEqualsWithDelta([2.0, 48.55], $features[$museum->id]['geometry']['coordinates'][0], 1e-6);
    }

    public function test_station_geojson_shows_uncertain_stations_as_uncertain(): void
    {
        $open = $this->station('Gare Alpha', 'active');
        $uncertain = $this->station('Gare Bravo', 'active', uncertain: true);
        $closedUncertain = $this->station('Gare Charlie', 'declassified', uncertain: true);
        // Voie fermée mais en place sous les gares : la gare fermée reste sur la carte.
        $this->line('Ligne de Papa à Quebec', 48.50, segment: ['status' => 'abandoned', 'traffic_type' => 'unknown']);
        $freight = $this->station('Gare Delta', 'no_commercial');
        $tourist = $this->station('Gare Echo', 'tourist');

        $props = collect(app(GeoExporter::class)->stationsAsGeoJson(2030)['features'])->keyBy('properties.name')->map->properties;

        $this->assertSame(['active', 'passenger'], [$props['Gare Alpha']['status'], $props['Gare Alpha']['service']]);
        $this->assertSame(['uncertain', 'active', 'uncertain'],
            [$props['Gare Bravo']['status'], $props['Gare Bravo']['sources_status'], $props['Gare Bravo']['service']]);
        $this->assertSame(['declassified', 'closed'], [$props['Gare Charlie']['status'], $props['Gare Charlie']['service']], 'une gare fermée reste fermée');
        $this->assertSame('freight', $props['Gare Delta']['service']);
        $this->assertSame('tourist', $props['Gare Echo']['service']);
        $this->assertCount(5, $props);
        unset($open, $uncertain, $closedUncertain, $freight, $tourist);
    }

    public function test_closed_station_leaves_the_map_once_its_line_is_dismantled(): void
    {
        $this->line('Ligne de Romeo à Sierra', 48.50);
        $this->line('Ligne de Tango à Uniform', 48.55, segment: ['status' => 'abandoned', 'traffic_type' => 'unknown']);
        $this->line('Ligne de Victor à Whiskey', 48.60, segment: ['status' => 'dismantled', 'traffic_type' => 'unknown']);
        $this->line('Ligne de Xray à Yankee', 48.65, segment: ['status' => 'razed', 'traffic_type' => 'unknown']);
        $this->station('Fermée sur ligne exploitée', 'declassified', lat: 48.50);
        $this->station('Fermée sur voie en place', 'declassified', lat: 48.55);
        $this->station('Fermée sur ligne déposée', 'declassified', lat: 48.60);
        $this->station('Fermée sur emprise effacée', 'declassified', lat: 48.65);
        $this->station('Fermée sans aucune voie', 'declassified', lat: 48.80);
        $this->station('Ouverte sans voie', 'active', uncertain: true, lat: 48.85);
        // Voie à 250 m (dans le rayon de 300 m) puis à 400 m (hors rayon).
        $this->station('Fermée à 250 m', 'declassified', lat: 48.50 + 250 / 111_320);
        $this->station('Fermée à 400 m', 'declassified', lat: 48.50 - 400 / 111_320);

        $names = collect(app(GeoExporter::class)->stationsAsGeoJson(2030)['features'])->pluck('properties.name')->sort()->values()->all();

        $this->assertSame(['Fermée sur ligne exploitée', 'Fermée sur voie en place', 'Fermée à 250 m', 'Ouverte sans voie'], $names);
    }

    public function test_closed_station_filter_uses_the_tracks_of_the_requested_era(): void
    {
        $line = $this->line('Ligne de Zulu à Alpha', 48.50, segment: ['status' => 'dismantled', 'traffic_type' => 'unknown']);
        DB::table('line_segments')->insert(['line_id' => $line->id, 'era_id' => $this->past->id, 'status' => 'abandoned', 'traffic_type' => 'unknown',
            'geom' => DB::raw("ST_GeomFromText('LINESTRING(2.0 48.5, 2.6 48.5)', 4326)"), 'created_at' => now(), 'updated_at' => now()]);
        $station = $this->station('Gare Bravo', 'declassified');
        DB::table('station_eras')->insert(['station_id' => $station->id, 'era_id' => $this->past->id, 'status' => 'declassified',
            'uncertain' => false, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertCount(1, app(GeoExporter::class)->stationsAsGeoJson(1960)['features'], 'voie fermée en place en 1960 : gare affichée');
        $this->assertCount(0, app(GeoExporter::class)->stationsAsGeoJson(2030)['features'], 'voie déposée en 2030 : gare retirée');
    }

    public function test_station_geojson_without_computed_eras(): void
    {
        Station::factory()->create(['name' => 'Gare ouverte', 'status' => 'active', 'geom' => Point::makeGeodetic(48.5, 2.3)]);
        Station::factory()->create(['name' => 'Gare fermée', 'status' => 'declassified', 'geom' => Point::makeGeodetic(48.6, 2.3)]);

        // Ère passée non calculée : aucune gare ; ère courante : les gares non fermées.
        $this->assertSame([], app(GeoExporter::class)->stationsAsGeoJson(1960)['features']);
        $this->assertSame(['Gare ouverte'], array_map(fn ($f) => $f['properties']['name'], app(GeoExporter::class)->stationsAsGeoJson(2030)['features']));
    }

    // ─── Fabrique ────────────────────────────────────────────────────────

    /** @return array<int, array<string, mixed>> line_id → propriétés de la tuile 8/129/88 */
    private function tileProps(int $year = 2030): array
    {
        $layers = $this->decodeMvt(app(GeoExporter::class)->linesAsMvt($year, 8, 129, 88));

        return collect($layers['vff-lines'] ?? [])->keyBy('line_id')->all();
    }

    private function line(string $name, float $lat, array $attributes = [], array $segment = []): Line
    {
        static $n = 0;
        $n++;
        $line = Line::factory()->create($attributes + ['name' => $name, 'code' => sprintf('%06d', 100000 + $n), 'network' => 'principal',
            'is_lgv' => false, 'is_threatened' => false, 'advocacy' => null, 'max_speed_kmh' => null]);
        DB::table('line_segments')->insert($segment + [
            'line_id' => $line->id, 'era_id' => $this->current->id, 'status' => 'in_service', 'traffic_type' => 'mixed',
            'geom' => DB::raw(sprintf("ST_GeomFromText('LINESTRING(2.0 %F, 2.6 %F)', 4326)", $lat, $lat)),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $line;
    }

    private function station(string $name, string $status, bool $uncertain = false, float $lat = 48.5): Station
    {
        $station = Station::factory()->create(['name' => $name, 'status' => $status, 'geom' => Point::makeGeodetic($lat, 2.3)]);
        DB::table('station_eras')->insert(['station_id' => $station->id, 'era_id' => $this->current->id, 'status' => $status,
            'uncertain' => $uncertain, 'created_at' => now(), 'updated_at' => now()]);

        return $station;
    }
}
