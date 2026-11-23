<?php

namespace Tests\Unit\Profiles;

use App\Services\Profiles\ProfileStats;
use App\Services\Profiles\RackRailways;
use Tests\TestCase;

/**
 * Liste versionnée des lignes à crémaillère (database/data/rack_railways.php) : chaque entrée est
 * complète et sourcée par le permalien d'une révision de Wikipédia.
 */
class RackRailwaysTest extends TestCase
{
    public function test_every_entry_is_complete_and_sourced(): void
    {
        $all = RackRailways::all();

        $this->assertNotEmpty($all);
        foreach ($all as $slug => $entry) {
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug);
            $this->assertContains($entry['system'], RackRailways::SYSTEMS, $slug);
            $this->assertTrue($entry['extent'] === null || in_array($entry['extent'], RackRailways::EXTENTS, true), $slug);
            // Plus raide que toute ligne à adhérence : sinon la ligne n'a rien à faire dans la liste.
            $this->assertGreaterThan(ProfileStats::MAX_PLAUSIBLE_GRADE * 1000, $entry['max_grade_permil'], $slug);
            $this->assertLessThanOrEqual(500, $entry['max_grade_permil'], $slug);
            $this->assertMatchesRegularExpression('#^https://fr\.wikipedia\.org/w/index\.php\?title=[^&]+&oldid=\d+$#', $entry['source_url'], $slug);
            $this->assertNotSame('', $entry['source_title']);
            $this->assertNotSame('', $entry['quote']);
            $this->assertLessThanOrEqual(300, mb_strlen($entry['quote']), $slug);
            $this->assertStringNotContainsString('—', $entry['quote'].$entry['source_title']);
        }
    }

    public function test_the_known_rack_railways_are_listed(): void
    {
        foreach (['ligne-de-saint-gervais-a-bionnassay', 'ligne-de-chamonix-a-montenvers', 'ligne-de-saint-ignace-a-la-rhune', 'hors-rfn-panoramique-des-domes'] as $slug) {
            $this->assertTrue(RackRailways::isRack($slug), $slug);
        }
        $this->assertFalse(RackRailways::isRack('rfn-896000'));
    }

    public function test_present_gives_the_source_and_its_licence(): void
    {
        $rack = RackRailways::present('ligne-de-chamonix-a-montenvers');

        $this->assertNotNull($rack);
        $this->assertSame('strub', $rack['system']);
        $this->assertSame('full', $rack['extent']);
        $this->assertSame(220, $rack['max_grade_permil']);
        $this->assertFalse($rack['braking_only']);
        $this->assertSame('wikipedia', $rack['source']['provider']);
        $this->assertSame('CC BY-SA 4.0', $rack['source']['license']);
        $this->assertNull(RackRailways::present('rfn-405000'));
    }
}
