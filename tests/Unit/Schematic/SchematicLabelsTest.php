<?php

namespace Tests\Unit\Schematic;

use App\Services\Schematic\SchematicGraphLoader;
use App\Services\Schematic\SchematicLabels;
use PHPUnit\Framework\TestCase;

/**
 * Côté du nom de chaque gare du plan (SchematicLabels) et gare principale d'une ville
 * (SchematicGraphLoader::principal), sur des axes écrits à la main autour du point (0, 0).
 */
class SchematicLabelsTest extends TestCase
{
    /**
     * @param  list<array{0: float, 1: float}>  $coords
     * @return array{line: ?int, category: string, coords: list<array{0: float, 1: float}>}
     */
    private static function axis(array $coords): array
    {
        return ['line' => 1, 'category' => 'mixed', 'coords' => $coords];
    }

    /** @return array{x: float, y: float, tier: int, name: string, city: ?string} */
    private static function label(int $tier = 3, string $name = 'Gare', ?string $city = null): array
    {
        return ['x' => 0.0, 'y' => 0.0, 'tier' => $tier, 'name' => $name, 'city' => $city];
    }

    public function test_the_name_of_a_station_on_a_horizontal_axis_goes_below_or_above_it(): void
    {
        $pieces = [self::axis([[-50000.0, 0.0], [50000.0, 0.0]])];

        $order = (new SchematicLabels)->rankings($pieces, [self::label()])[0];

        $this->assertSame('top', $order[0], 'texte posé sous le point (ancrage top)');
        $this->assertSame('bottom', $order[1]);
        $this->assertEqualsCanonicalizing(array_keys(SchematicLabels::ANCHORS), $order);
        // À droite ou à gauche, le nom serait sur l'axe : derniers choix.
        $this->assertSame(['left', 'right'], array_values(array_intersect($order, ['left', 'right'])));
        $this->assertGreaterThan(5, array_search('left', $order, true));
    }

    public function test_the_name_of_a_station_on_a_vertical_axis_goes_beside_it(): void
    {
        $pieces = [self::axis([[0.0, -50000.0], [0.0, 50000.0]])];

        $order = (new SchematicLabels)->rankings($pieces, [self::label()])[0];

        $this->assertSame(['left', 'right'], array_slice($order, 0, 2));
    }

    public function test_a_hub_label_takes_the_free_quarter(): void
    {
        // Axes vers l'est, l'ouest et le nord : le nom va dessous (ancrage top), seul côté libre.
        $pieces = [
            self::axis([[0.0, 0.0], [50000.0, 0.0]]),
            self::axis([[0.0, 0.0], [-50000.0, 0.0]]),
            self::axis([[0.0, 0.0], [0.0, 50000.0]]),
        ];

        $order = (new SchematicLabels)->rankings($pieces, [self::label(2)])[0];

        $this->assertContains($order[0], ['top', 'top-left', 'top-right']);
    }

    public function test_a_city_node_is_measured_with_the_city_name_then_with_its_station_name(): void
    {
        $labels = new SchematicLabels;
        $labels->useAxes([self::axis([[0.0, 0.0], [5_000_000.0, 0.0]])]);
        $label = self::label(0, 'Abcdefgh-Ijklmnop', 'Ab');

        // Axe vers l'est sous tout le nom posé à droite : sa longueur à l'écran est celle du nom.
        $city = $labels->axisPixels($label, 'left', 5.0);
        $station = $labels->axisPixels($label, 'left', SchematicLabels::STATION_NAMES_ZOOM);
        [$x0, , $x1] = SchematicLabels::box('Ab', 14.0, true, 'left');

        $this->assertEqualsWithDelta($x1 - $x0, $city, 0.01);
        $this->assertGreaterThan(3 * $city, $station);
        $this->assertSame(0.0, $labels->axisPixels($label, 'right', 5.0));
    }

    public function test_label_boxes_sit_on_the_side_of_their_anchor(): void
    {
        [$x0, $y0, $x1, $y1] = SchematicLabels::box('Nantes', 13.0, false, 'left');
        $this->assertEqualsWithDelta(0.7 * 13, $x0, 1e-9);
        $this->assertEqualsWithDelta(-$y0, $y1, 1e-9);

        [, , $x1] = SchematicLabels::box('Nantes', 13.0, false, 'right');
        $this->assertEqualsWithDelta(-0.7 * 13, $x1, 1e-9);

        [$x0, $y0, $x1] = SchematicLabels::box('Nantes', 13.0, false, 'top');
        $this->assertEqualsWithDelta(0.6 * 13, $y0, 1e-9);
        $this->assertEqualsWithDelta(-$x0, $x1, 1e-9);

        $this->assertGreaterThan(SchematicLabels::textEm('iii'), SchematicLabels::textEm('MMM'));
        // Un nom plus long que 12 em passe sur deux lignes.
        [, $y0, , $y1] = SchematicLabels::box(str_repeat('Abcdefgh ', 4), 13.0, false, 'left');
        $this->assertEqualsWithDelta(2 * 1.2 * 13, $y1 - $y0, 1e-9);
    }

    public function test_the_principal_station_bears_the_city_name_alone_or_with_ville_or_centre(): void
    {
        $this->assertTrue(SchematicGraphLoader::principal('Nantes', 'Nantes'));
        $this->assertTrue(SchematicGraphLoader::principal('Metz-Ville', 'Metz'));
        $this->assertTrue(SchematicGraphLoader::principal('Avignon-Centre', 'Avignon'));
        $this->assertTrue(SchematicGraphLoader::principal('St-Étienne', 'Saint-Étienne'));
        $this->assertFalse(SchematicGraphLoader::principal('Nantes-Legé', 'Nantes'));
        $this->assertFalse(SchematicGraphLoader::principal('Marseille-Blancarde', 'Marseille'));
        $this->assertFalse(SchematicGraphLoader::principal('Villeneuve', 'Ville'));
        $this->assertFalse(SchematicGraphLoader::principal('Nantes', null));
    }
}
