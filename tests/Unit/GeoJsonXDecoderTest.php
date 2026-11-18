<?php

namespace Tests\Unit;

use App\Services\GeoJsonXDecoder;
use PHPUnit\Framework\TestCase;

class GeoJsonXDecoderTest extends TestCase
{
    public function test_decode_number_uses_zigzag_base91(): void
    {
        $d = new GeoJsonXDecoder;

        // 'A'=0 → 0 ; 'B'=1 → -1 ; 'C'=2 → 1 ; 'BA' = 1×91+0 = 91 → -46
        $this->assertSame(0.0, $d->decodeNumber('A', 0));
        $this->assertSame(-1.0, $d->decodeNumber('B', 0));
        $this->assertSame(1.0, $d->decodeNumber('C', 0));
        $this->assertSame(-46.0, $d->decodeNumber('BA', 0));
        $this->assertSame(0.001, $d->decodeNumber('C', 3));
    }

    public function test_point_sequences_are_delta_encoded(): void
    {
        $d = new GeoJsonXDecoder;

        // (1,1) puis +1,+1 → (2,2) puis -1,+0 → (1,2)
        $this->assertSame([[1.0, 1.0], [2.0, 2.0], [1.0, 2.0]], $d->decodeCoordinates('C,C;C,C;B,A', 0));
    }

    public function test_decode_collection_maps_hashed_properties(): void
    {
        $features = (new GeoJsonXDecoder)->decodeCollection([
            'decimals' => 0,
            'hashProperties' => ['name', 'description'],
            'features' => [
                [[1, 'C,C;C,C'], [0, 'Carhaix Morlaix']],
                [[4, ['C,C;C,C', 'E,E;C,C']]],
            ],
        ]);

        $this->assertSame('LineString', $features[0]['geometry']['type']);
        $this->assertSame(['name' => 'Carhaix Morlaix'], $features[0]['properties']);
        $this->assertSame('MultiLineString', $features[1]['geometry']['type']);
        $this->assertSame([[2.0, 2.0], [3.0, 3.0]], $features[1]['geometry']['coordinates'][1]);
    }

    public function test_web_mercator_to_wgs84(): void
    {
        // Origine → (0,0) ; x = πR → 180° de longitude.
        $this->assertSame([0.0, 0.0], GeoJsonXDecoder::toWgs84([0, 0]));
        $this->assertEqualsWithDelta(180.0, GeoJsonXDecoder::toWgs84([M_PI * 6378137.0, 0])[0], 1e-6);
    }
}
