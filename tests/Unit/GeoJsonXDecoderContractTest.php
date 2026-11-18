<?php

namespace Tests\Unit;

use App\Services\GeoJsonXDecoder;
use PHPUnit\Framework\TestCase;

/**
 * GeoJSONX (ol-ext) : contrat de sortie du décodeur (features GeoJSON
 * valides, propriétés robustes aux entrées incomplètes, précision de la
 * reprojection en WGS84), en complément des tests de décodage existants.
 */
class GeoJsonXDecoderContractTest extends TestCase
{
    private GeoJsonXDecoder $d;

    protected function setUp(): void
    {
        $this->d = new GeoJsonXDecoder;
    }

    /** Encode un entier comme ol-ext (zigzag puis base 91). */
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

    public function test_every_feature_is_a_geojson_feature(): void
    {
        $features = $this->d->decodeCollection(['decimals' => 0, 'features' => [
            [self::enc(5).','.self::enc(-5)],
            [[1, 'A,A;C,C'], [0, 'x']],
        ]]);

        $this->assertSame(['type' => 'Feature', 'geometry' => ['type' => 'Point', 'coordinates' => [5.0, -5.0]], 'properties' => []], $features[0]);
        $this->assertSame('Feature', $features[1]['type']);
        $this->assertSame(['0' => 'x'], $features[1]['properties']);
    }

    public function test_point_written_with_its_type_index_keeps_flat_coordinates(): void
    {
        // Point écrit sous la forme [0, « x,y »] (et non en chaîne seule) : jamais [[x, y]].
        $features = $this->d->decodeCollection(['decimals' => 1, 'features' => [[[0, self::enc(15).','.self::enc(25)]]]]);

        $this->assertSame(['type' => 'Point', 'coordinates' => [1.5, 2.5]], $features[0]['geometry']);
    }

    public function test_decimals_given_as_a_string_are_honoured(): void
    {
        $features = $this->d->decodeCollection(['decimals' => '2', 'features' => [[self::enc(12345).','.self::enc(-678)]]]);

        $this->assertSame([123.45, -6.78], $features[0]['geometry']['coordinates']);
    }

    public function test_odd_property_list_drops_the_dangling_key(): void
    {
        // [clé, valeur, clé sans valeur] : la clé orpheline est ignorée, sans erreur.
        $features = $this->d->decodeCollection(['decimals' => 0, 'hashProperties' => ['name', 'gauge'], 'features' => [
            [[1, 'A,A;C,C'], [0, 'Ligne', 1]],
        ]]);

        $this->assertSame(['name' => 'Ligne'], $features[0]['properties']);
    }

    public function test_non_array_properties_are_ignored(): void
    {
        $features = $this->d->decodeCollection(['decimals' => 0, 'features' => [
            [[1, 'A,A;C,C'], 'pas un tableau'],
            [self::enc(1).','.self::enc(2), 'pas un tableau'],
        ]]);

        $this->assertSame([], $features[0]['properties']);
        $this->assertSame([], $features[1]['properties']);
    }

    public function test_web_mercator_is_converted_to_seven_decimals(): void
    {
        $this->assertSame([2.3499939, 48.8532702], GeoJsonXDecoder::toWgs84([261600.123, 6250000.987]));
        $this->assertSame([[2.3499939, 48.8532702], [0.0, 0.0]], GeoJsonXDecoder::toWgs84([[261600.123, 6250000.987], [0, 0]]));
    }
}
