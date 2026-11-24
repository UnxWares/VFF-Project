<?php

namespace Tests\Unit\Routebook;

use App\Services\Routebook\Routebook;
use App\Services\Routebook\RouteLegs;
use PHPUnit\Framework\TestCase;

/** Découpage d'un itinéraire en portions de ligne et calage des PK sur les kilomètres parcourus (RouteLegs). */
class RouteLegsTest extends TestCase
{
    public function test_split_gives_route_bounds_and_the_edge_of_each_line_change_on_open_line(): void
    {
        // Gare 0 → 1 sur la ligne 10 ; gare 1 → 2 : 4 km sur 10, puis 6 km sur 20 (changement en pleine voie).
        $legs = RouteLegs::split([[[10, 5000]], [[10, 4000], [20, 6000]]]);

        $this->assertCount(2, $legs);
        $this->assertSame([10, 9000, 0, 9000, 0, null], [$legs[0]['line_id'], $legs[0]['length_m'], $legs[0]['route_from_m'],
            $legs[0]['route_to_m'], $legs[0]['from_index'], $legs[0]['to_index']]);
        $this->assertNull($legs[0]['from_at']);
        $this->assertSame(['edge' => 1, 'offset_m' => 4000], $legs[0]['to_at']);
        $this->assertSame([20, 9000, 15000, null, 2], [$legs[1]['line_id'], $legs[1]['route_from_m'], $legs[1]['route_to_m'],
            $legs[1]['from_index'], $legs[1]['to_index']]);
        $this->assertSame(['edge' => 1, 'offset_m' => 4000], $legs[1]['from_at']);
    }

    public function test_short_connections_are_folded_like_the_route(): void
    {
        // 300 m de raccordement (ligne 30) entre deux morceaux de la ligne 10 : une seule portion.
        $legs = RouteLegs::split([[[10, 5000], [30, 300], [10, 5000]]]);

        $this->assertCount(1, $legs);
        $this->assertSame([10, 10300, 0, 0], [$legs[0]['line_id'], $legs[0]['route_to_m'], $legs[0]['from_index'], $legs[0]['route_from_m']]);
    }

    public function test_direction_and_route_metres(): void
    {
        $this->assertSame('up', RouteLegs::direction(1000, 5000));
        $this->assertSame('down', RouteLegs::direction(5000, 1000));
        $this->assertNull(RouteLegs::direction(null, 1000));
        $this->assertNull(RouteLegs::direction(1000, 1000));

        // Portion parcourue à PK décroissants, de 72 200 à 50 000, du km 0 au km 22,2.
        $this->assertSame(0, RouteLegs::routeMetres(72200, 72200, 50000, 0, 22200));
        $this->assertSame(12200, RouteLegs::routeMetres(60000, 72200, 50000, 0, 22200));
        // Écart entre PK et longueur mesurée réparti sur la portion.
        $this->assertSame(11000, RouteLegs::routeMetres(5000, 0, 10000, 1000, 21000));
        $this->assertTrue(RouteLegs::covers(60000, 72200, 50000));
        $this->assertFalse(RouteLegs::covers(49999, 72200, 50000));
    }

    public function test_weakest_precision(): void
    {
        $this->assertSame('derived', Routebook::weakest('exact', 'derived'));
        $this->assertSame('approximate', Routebook::weakest('approximate', 'exact'));
        $this->assertNull(Routebook::weakest('exact', null));
    }
}
