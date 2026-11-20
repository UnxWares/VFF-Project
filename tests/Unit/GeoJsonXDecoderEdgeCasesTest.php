<?php

namespace Tests\Unit;

use App\Services\GeoJsonXDecoder;
use PHPUnit\Framework\TestCase;

/**
 * GeoJSONX (ol-ext, cartes IGN « Ma carte ») : cas limites du décodage, en
 * complément de GeoJsonXDecoderTest (types de géométrie, propriétés, précision
 * des deltas, reprojection).
 */
class GeoJsonXDecoderEdgeCasesTest extends TestCase
{
    private GeoJsonXDecoder $d;

    protected function setUp(): void
    {
        $this->d = new GeoJsonXDecoder;
    }

    /** Encode un entier comme ol-ext (zigzag puis base 91), pour fabriquer des entrées. */
    private static function enc(int $value): string
    {
        $radix = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/ !#$%&\'()*-.:<=>?@[]^_`{|}~';
        $n = $value >= 0 ? 2 * $value : -2 * $value - 1;
        $out = '';
        do {
            $out = $radix[$n % 91].$out;
            $n = intdiv($n, 91);
        } while ($n > 0);

        return $out;
    }

    public function test_numbers_round_trip_through_the_zigzag_base91_encoding(): void
    {
        foreach ([0, 1, -1, 45, -46, 90, 91, -91, 8280, 123456789, -987654321] as $value) {
            $this->assertSame((float) $value, $this->d->decodeNumber(self::enc($value), 0), (string) $value);
        }
        $this->assertSame(-12.345, $this->d->decodeNumber(self::enc(-12345), 3));
    }

    public function test_deltas_do_not_accumulate_floating_point_drift(): void
    {
        // 1000 pas de 0,1 : on doit tomber sur 100,0 exactement.
        $encoded = implode(';', array_fill(0, 1000, self::enc(10).','.self::enc(-10)));
        $points = $this->d->decodeCoordinates($encoded, 2);

        $this->assertCount(1000, $points);
        $this->assertSame([0.1, -0.1], $points[0]);
        $this->assertSame([100.0, -100.0], $points[999]);
    }

    public function test_every_geometry_type_is_decoded(): void
    {
        $line = self::enc(100).','.self::enc(200).';'.self::enc(10).','.self::enc(0);
        $ring = self::enc(0).','.self::enc(0).';'.self::enc(10).','.self::enc(0).';'.self::enc(0).','.self::enc(10).';'.self::enc(-10).','.self::enc(-10);

        $features = $this->d->decodeCollection(['decimals' => '0', 'features' => [
            [self::enc(5).','.self::enc(-5)],                // Point (chaîne seule)
            [[1, $line]],                                    // LineString
            [[2, [$ring]]],                                  // Polygon
            [[4, [$line, $line]]],                           // MultiLineString
            [[5, [[$ring]]]],                                // MultiPolygon
        ]]);

        $this->assertSame(['Point', 'LineString', 'Polygon', 'MultiLineString', 'MultiPolygon'], array_map(fn ($f) => $f['geometry']['type'], $features));
        $this->assertSame([5.0, -5.0], $features[0]['geometry']['coordinates']);
        $this->assertSame([[100.0, 200.0], [110.0, 200.0]], $features[1]['geometry']['coordinates']);
        $this->assertSame([[0.0, 0.0], [10.0, 0.0], [10.0, 10.0], [0.0, 0.0]], $features[2]['geometry']['coordinates'][0], 'anneau fermé');
        $this->assertCount(2, $features[3]['geometry']['coordinates']);
        $this->assertSame([10.0, 10.0], $features[4]['geometry']['coordinates'][0][0][2]);
    }

    public function test_multipoint_with_a_single_point_is_still_a_list_of_points(): void
    {
        // ol-ext écrit un MultiPoint d'un seul point sans « ; » : « x,y ».
        $features = $this->d->decodeCollection(['decimals' => 0, 'features' => [[[3, self::enc(7).','.self::enc(8)]]]]);

        $this->assertSame('MultiPoint', $features[0]['geometry']['type']);
        $this->assertSame([[7.0, 8.0]], $features[0]['geometry']['coordinates']);
    }

    public function test_unknown_geometry_type_is_skipped(): void
    {
        $features = $this->d->decodeCollection(['decimals' => 0, 'features' => [
            [[9, 'A,A;A,A']],
            [[1, 'A,A;C,C']],
        ]]);

        $this->assertCount(1, $features);
        $this->assertSame('LineString', $features[0]['geometry']['type']);
    }

    public function test_properties_without_hash_table_keep_their_index(): void
    {
        $features = $this->d->decodeCollection(['decimals' => 0, 'hashProperties' => ['name'], 'features' => [
            [[1, 'A,A;C,C'], [0, 'Ligne de Carhaix à Morlaix', 3, 'voie unique']],
            [[1, 'A,A;C,C'], []],
            [[1, 'A,A;C,C']],
        ]]);

        $this->assertSame(['name' => 'Ligne de Carhaix à Morlaix', '3' => 'voie unique'], $features[0]['properties']);
        $this->assertSame([], $features[1]['properties']);
        $this->assertSame([], $features[2]['properties']);
    }

    public function test_web_mercator_coordinates_of_paris_and_nested_arrays(): void
    {
        // Notre-Dame de Paris : 2,3499° E, 48,8530° N.
        [$lon, $lat] = GeoJsonXDecoder::toWgs84([261589.7, 6249955.3]);
        $this->assertEqualsWithDelta(2.3499, $lon, 1e-4);
        $this->assertEqualsWithDelta(48.8530, $lat, 1e-4);

        $nested = GeoJsonXDecoder::toWgs84([[[0, 0], [M_PI * 6378137.0 / 2, 0]]]);
        $this->assertSame([[[0.0, 0.0], [90.0, 0.0]]], $nested);
        // Hémisphère sud et ouest.
        [$lon, $lat] = GeoJsonXDecoder::toWgs84([-M_PI * 6378137.0 / 4, -2000000]);
        $this->assertSame(-45.0, $lon);
        $this->assertLessThan(0, $lat);
    }
}
