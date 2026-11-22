<?php

namespace Tests\Unit\Enums;

use App\Enums\TileFields;
use App\Http\Controllers\Api\GeoController;
use PHPUnit\Framework\TestCase;

/**
 * Tuiles allégées de la carte (`fields=map`) : chaque propriété des tracés que lit la carte du site
 * (expressions MapLibre de `RailMap.svelte` et `lib/railStyle.js`, clic sur un tracé) doit rester dans
 * la tuile. Une propriété lue par la carte et retirée de la tuile ferait disparaître une couleur, un
 * filtre de la légende ou une ligne de la fiche sans aucune erreur.
 */
class TileFieldsTest extends TestCase
{
    /** Propriétés lues par la carte sur ses AUTRES sources (gares, comparaison, superposition, itinéraire). */
    private const OTHER_SOURCES = ['name', 'kind', 'change', 'last_year'];

    private static function source(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 3).'/resources/js/'.$path);
    }

    public function test_every_property_read_by_the_map_layers_is_in_the_light_tile(): void
    {
        $read = [];
        foreach (['Components/Map/RailMap.svelte', 'lib/railStyle.js', 'lib/lineCasing.js'] as $path) {
            preg_match_all("/\\['get', '([a-z_]+)'\\]/", self::source($path), $m);
            $read = [...$read, ...$m[1]];
        }
        $read = array_values(array_diff(array_unique($read), self::OTHER_SOURCES));

        $this->assertNotEmpty($read);
        $this->assertSame([], array_values(array_diff($read, TileFields::MAP_PROPERTIES)));
    }

    public function test_every_property_read_on_a_line_click_is_in_the_light_tile(): void
    {
        $railMap = self::source('Components/Map/RailMap.svelte');
        $start = strpos($railMap, "map.on('click', LINE_LAYERS");
        $this->assertNotFalse($start);
        $end = strpos($railMap, "map.on('mouseenter', LINE_LAYERS", $start);
        $this->assertNotFalse($end);
        preg_match_all('/\bprops\.([a-z_]+)/', substr($railMap, $start, $end - $start), $m);
        $read = array_unique($m[1]);

        // Repli sur la valeur du tronçon quand la ligne n'en a pas : la valeur de ligne (COALESCE) la contient déjà.
        $fallbacks = ['max_speed_kmh'];
        $this->assertContains('line_slug', $read);
        $this->assertSame([], array_values(array_diff($read, TileFields::MAP_PROPERTIES, $fallbacks)));
    }

    public function test_the_default_tile_keeps_its_cache_key(): void
    {
        $this->assertSame('', TileFields::All->cacheSuffix());
        $this->assertSame(':map', TileFields::Map->cacheSuffix());
        $this->assertSame(TileFields::Map, TileFields::from('map'));
    }

    public function test_the_api_documentation_lists_every_public_property_of_the_light_tile(): void
    {
        $doc = (string) (new \ReflectionMethod(GeoController::class, 'tile'))->getDocComment();
        $light = substr($doc, (int) strpos($doc, '**Tuile allégée**'), 800);

        // `advocacy_kind` (halo de réouverture) n'est volontairement pas décrit dans la doc publique
        // (AdvocacyNotPublishedTest) : il reste dans la tuile sans être annoncé.
        foreach (array_diff(TileFields::MAP_PROPERTIES, ['advocacy_kind']) as $property) {
            $this->assertStringContainsString("`{$property}`", $light, "propriété {$property} absente de la documentation de fields=map");
        }
    }
}
