<?php

namespace Tests\Feature\Api;

use App\Enums\TileFields;
use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\DecodesMvt;
use Tests\TestCase;

/**
 * Tuiles allégées de la carte (`?fields=map`, rapport aprem-2111/tuiles-legeres.md) : seulement les
 * propriétés que lit la carte du site, booléens écrits seulement s'ils sont vrais, tracés aux propriétés
 * identiques réunis en une entité ; réponse par défaut inchangée, cache distinct, valeur inconnue refusée.
 */
class LightMapTilesTest extends TestCase
{
    use DecodesMvt;
    use RefreshDatabase;

    /** Tuile du zoom 8 qui contient les tracés témoins (Paris Saint-Lazare → Versailles). */
    private const TILE = '/api/v1/eras/2030/tiles/8/129/88.mvt';

    /** Propriétés de la réponse par défaut absentes de la tuile allégée. */
    private const DROPPED = ['id', 'line_id', 'max_speed_kmh', 'gauge_mm', 'electrified_year', 'operator', 'source',
        'source_trace_id', 'dismantled_at'];

    protected function setUp(): void
    {
        parent::setUp();
        $era = Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd\'hui']);
        $line = Line::factory()->create(['slug' => 'paris-saint-lazare-versailles-rd', 'code' => '999000',
            'name' => 'Paris-Saint-Lazare - Versailles-Rive-Droite', 'is_lgv' => false, 'network' => 'principal',
            'max_speed_kmh' => 120, 'electrified' => true, 'voltage_v' => 1500]);
        $branch = Line::factory()->create(['slug' => 'embranchement-de-test', 'code' => '999100',
            'name' => 'Embranchement de test', 'is_lgv' => false, 'network' => 'particulier',
            'max_speed_kmh' => null, 'electrified' => false, 'voltage_v' => null]);

        $common = ['era_id' => $era->id, 'status' => 'in_service', 'traffic_type' => 'mixed', 'is_heritage' => false,
            'source' => 'osm', 'gauge_mm' => 1435];
        // Deux portions voisines de la ligne principale, dans le même état : une seule entité allégée.
        foreach ([[[48.8763, 2.3253], [48.8530, 2.2400]], [[48.8530, 2.2400], [48.8091, 2.1347]]] as $i => $coords) {
            LineSegment::factory()->create($common + ['line_id' => $line->id, 'electrified' => true,
                'voltage_v' => 1500, 'max_speed_kmh' => 120, 'pk_start_m' => $i * 5000, 'pk_end_m' => ($i + 1) * 5000,
                'geom' => LineString::make(array_map(fn (array $c): Point => Point::makeGeodetic(...$c), $coords))]);
        }
        // Embranchement non électrifié, voie fermée.
        LineSegment::factory()->create(['line_id' => $branch->id, 'status' => 'abandoned', 'electrified' => false,
            'voltage_v' => null, 'max_speed_kmh' => 40, 'traffic_type' => 'freight',
            'geom' => LineString::make([Point::makeGeodetic(48.8530, 2.2400), Point::makeGeodetic(48.8400, 2.2000)])] + $common);
    }

    /** @return list<array<string, mixed>> propriétés des entités de la couche `vff-lines` */
    private function features(string $url): array
    {
        $layers = $this->decodeMvt((string) $this->get($url)->assertOk()
            ->assertHeader('Content-Type', 'application/x-protobuf')->getContent());
        $this->assertSame(['vff-lines'], array_keys($layers));

        return $layers['vff-lines'];
    }

    /** @param list<array<string, mixed>> $features */
    private function bySlug(array $features, string $slug): array
    {
        return array_values(array_filter($features, fn (array $f): bool => $f['line_slug'] === $slug));
    }

    public function test_the_default_tile_keeps_every_property(): void
    {
        $features = $this->features(self::TILE);
        $this->assertCount(3, $features);
        foreach ($features as $properties) {
            // Propriétés non nulles des tracés témoins (ST_AsMVT n'écrit pas une valeur NULL).
            foreach (['id', 'line_id', 'max_speed_kmh', 'gauge_mm', 'source', 'line_slug', 'line_name', 'status',
                'traffic_type', 'network', 'is_lgv', 'is_threatened', 'electrified', 'line_electrified', 'draw_rank'] as $key) {
                $this->assertArrayHasKey($key, $properties, $key);
            }
            // Les booléens faux restent écrits.
            $this->assertFalse($properties['is_heritage']);
            $this->assertFalse($properties['covered']);
        }
        $this->assertSame($this->features(self::TILE), $this->features(self::TILE.'?fields=all'));
    }

    public function test_the_light_tile_keeps_only_what_the_map_reads(): void
    {
        $features = $this->features(self::TILE.'?fields=map');
        foreach ($features as $properties) {
            $this->assertSame([], array_values(array_diff(array_keys($properties), TileFields::MAP_PROPERTIES)));
            foreach (self::DROPPED as $key) {
                $this->assertArrayNotHasKey($key, $properties);
            }
            // Booléens faux absents : la carte les compare à `true`.
            foreach (['is_lgv', 'is_threatened', 'is_heritage', 'covered'] as $key) {
                $this->assertArrayNotHasKey($key, $properties);
            }
        }

        // Les deux portions voisines de la ligne principale ne font plus qu'une entité.
        [$main] = $this->bySlug($features, 'paris-saint-lazare-versailles-rd');
        $this->assertCount(1, $this->bySlug($features, 'paris-saint-lazare-versailles-rd'));
        $this->assertSame('Paris-Saint-Lazare - Versailles-Rive-Droite', $main['line_name']);
        $this->assertSame('999000', $main['line_code']);
        $this->assertSame('in_service', $main['status']);
        $this->assertSame('mixed', $main['traffic_type']);
        $this->assertSame('principal', $main['network']);
        $this->assertTrue($main['electrified']);
        $this->assertSame(1500, $main['voltage_v']);
        $this->assertSame(120, $main['line_max_speed']);
        $this->assertTrue($main['line_electrified']);
        $this->assertSame(1500, $main['line_voltage_v']);
        $this->assertGreaterThan(0, $main['draw_rank']);

        // Embranchement : pas d'électrification ni de tension, mais `line_electrified` garde son faux (clic).
        [$branch] = $this->bySlug($features, 'embranchement-de-test');
        $this->assertSame('abandoned', $branch['status']);
        $this->assertArrayNotHasKey('electrified', $branch);
        $this->assertArrayNotHasKey('voltage_v', $branch);
        $this->assertFalse($branch['line_electrified']);
        $this->assertSame(40, $branch['line_max_speed']);
    }

    public function test_the_light_tile_is_lighter(): void
    {
        $full = strlen((string) $this->get(self::TILE)->getContent());
        $light = strlen((string) $this->get(self::TILE.'?fields=map')->getContent());
        // Trois tracés seulement : la géométrie domine, le gain est plus faible que sur une vraie vue d'ensemble (environ 60 %).
        $this->assertLessThan($full * 0.9, $light);
    }

    public function test_an_unknown_value_is_refused_in_json(): void
    {
        $this->get(self::TILE.'?fields=everything')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields']);
        $this->get(self::TILE.'?fields[]=map')->assertStatus(422)->assertJsonValidationErrors(['fields']);
    }

    /** Nombre de requêtes SQL qui calculent une tuile (ST_AsMVT) pendant `$run`. */
    private function tileQueries(callable $run): int
    {
        $count = 0;
        DB::listen(function (QueryExecuted $query) use (&$count): void {
            if (str_contains($query->sql, 'ST_AsMVT(')) {
                $count++;
            }
        });
        $run();

        return $count;
    }

    public function test_the_light_tile_has_its_own_cache_entry_and_etag(): void
    {
        $etags = [];
        $this->assertSame(2, $this->tileQueries(function () use (&$etags): void {
            $etags[] = $this->get(self::TILE)->assertOk()->headers->get('ETag');
            $etags[] = $this->get(self::TILE.'?fields=map')->assertOk()->headers->get('ETag');
        }));
        $this->assertNotSame($etags[0], $etags[1]);

        // Chacune ressort ensuite du cache, sans recalcul et sans se confondre avec l'autre.
        $this->assertSame(0, $this->tileQueries(function (): void {
            $this->assertArrayHasKey('source', $this->features(self::TILE)[0]);
            $this->assertArrayNotHasKey('source', $this->features(self::TILE.'?fields=map')[0]);
            $this->features(self::TILE.'?fields=all');
        }));
    }
}
