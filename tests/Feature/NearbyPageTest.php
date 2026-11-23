<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Page « Autour de moi » (/nearby) : elle ne reçoit jamais la position de l'internaute, seulement un nom de commune.
 */
class NearbyPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_the_page_renders_without_any_position(): void
    {
        $this->get('/nearby')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Network/Nearby')->where('place', null)->where('query', '')->where('matches', []));
    }

    public function test_coordinates_in_the_address_are_ignored_and_never_echoed(): void
    {
        $this->get('/nearby?lat=45.83361&lng=1.26114')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('place', null)->missing('lat')->missing('lng'));
    }

    public function test_an_unknown_commune_gives_no_place(): void
    {
        $this->get('/nearby?commune=99999-nulle-part')->assertOk()->assertInertia(fn (Assert $page) => $page->where('place', null));
    }

    public function test_a_short_query_gives_no_matches(): void
    {
        $this->get('/nearby?q=a')->assertOk()->assertInertia(fn (Assert $page) => $page->where('query', 'a')->where('matches', []));
    }
}
