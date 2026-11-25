<?php

namespace Tests\Unit\Signalling;

use App\Services\Signalling\KvbPieces;
use PHPUnit\Framework\TestCase;

/** Real cases from the SNCF Réseau datasets of 19/11/2026 (`mode-de-cantonnement-des-lignes`, `lignes-equipees-de-kvb`). */
class KvbPiecesTest extends TestCase
{
    public function test_975900_part_1_is_cut_where_its_kvb_begins(): void
    {
        $this->assertSame(
            [['from' => 9_906, 'to' => 10_380, 'kvb' => false], ['from' => 10_380, 'to' => 12_985, 'kvb' => true]],
            KvbPieces::split(9_906, 12_985, [[10_380, 12_985]]));
    }

    public function test_a_range_inside_a_kvb_portion_stays_whole(): void
    {
        // 975900 part 2: BAL and KVB both from 12+000 to 14+594.
        $this->assertSame([['from' => 12_000, 'to' => 14_594, 'kvb' => true]], KvbPieces::split(12_000, 14_594, [[12_000, 14_594]]));
    }

    public function test_229000_two_portions_give_equipped_and_bare_pieces(): void
    {
        // BAL from 2+500 to 69+251, KVB from 2+500 to 4+100 only; the other portion (136+943 to 140+600) lies elsewhere.
        $this->assertSame(
            [['from' => 2_500, 'to' => 4_100, 'kvb' => true], ['from' => 4_100, 'to' => 69_251, 'kvb' => false]],
            KvbPieces::split(2_500, 69_251, [[136_943, 140_600], [2_500, 4_100]]));
    }

    public function test_001000_portions_join_and_leave_a_gap(): void
    {
        // BMDV from 411+397 to 436+700: KVB from 433+621 to 442+682.
        $this->assertSame(
            [['from' => 411_397, 'to' => 433_621, 'kvb' => false], ['from' => 433_621, 'to' => 436_700, 'kvb' => true]],
            KvbPieces::split(411_397, 436_700, [[433_621, 442_682], [442_682, 491_081]]));
    }

    public function test_a_range_that_only_touches_a_portion_gets_no_kvb(): void
    {
        // Touching at one PK (or overlapping by less than a few metres) is not being equipped: 892000, « _Autre » range
        // from 211+701 to 212+340 after a KVB that ends at 211+700.
        $this->assertSame([], KvbPieces::split(211_701, 212_340, [[142_628, 211_700]]));
        $this->assertSame([], KvbPieces::split(10_000, 20_000, [[0, 10_000]]));
        $this->assertSame([], KvbPieces::split(10_000, 20_000, [[0, 10_005]]));
        $this->assertSame([], KvbPieces::split(10_000, 20_000, []));
    }

    public function test_a_few_metres_of_rounding_do_not_make_a_piece(): void
    {
        // 892000: block range from 139+802 to 211+701, KVB from 139+428 to 142+628 and 142+628 to 211+700.
        $this->assertSame([['from' => 139_802, 'to' => 211_701, 'kvb' => true]], KvbPieces::split(139_802, 211_701, [[142_628, 211_700], [139_428, 142_628]]));
        // 316000: block range from 80+370 to 84+220, KVB from 50+381 to 84+217.
        $this->assertSame([['from' => 80_370, 'to' => 84_220, 'kvb' => true]], KvbPieces::split(80_370, 84_220, [[50_381, 84_217]]));
        // Two portions 4 m apart are one.
        $this->assertSame([['from' => 0, 'to' => 9_000, 'kvb' => true]], KvbPieces::split(0, 9_000, [[0, 5_000], [5_004, 9_000]]));
    }

    public function test_a_real_short_gap_is_kept(): void
    {
        // 579000: BAL from -0+252 to 1+280, KVB from -0+235 to 1+660 then on: the first 17 m are not equipped.
        $this->assertSame(
            [['from' => -252, 'to' => -235, 'kvb' => false], ['from' => -235, 'to' => 1_280, 'kvb' => true]],
            KvbPieces::split(-252, 1_280, [[-235, 1_660], [1_660, 10_200]]));
    }

    public function test_a_very_short_range_is_equipped_when_a_portion_covers_it(): void
    {
        $this->assertSame([['from' => 500, 'to' => 504, 'kvb' => true]], KvbPieces::split(500, 504, [[0, 1_000]]));
        $this->assertSame([], KvbPieces::split(500, 504, [[504, 1_000]]));
        $this->assertSame([['from' => 500, 'to' => 500, 'kvb' => true]], KvbPieces::split(500, 500, [[0, 1_000]]));
    }

    public function test_reversed_pks_are_read(): void
    {
        $this->assertSame(
            [['from' => 0, 'to' => 3_000, 'kvb' => true], ['from' => 3_000, 'to' => 9_000, 'kvb' => false]],
            KvbPieces::split(9_000, 0, [[3_000, 0]]));
    }
}
