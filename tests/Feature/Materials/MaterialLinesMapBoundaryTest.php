<?php

namespace Tests\Feature\Materials;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineMaterial;
use App\Models\LineMaterialPeriod;
use App\Models\LineSegment;
use App\Models\Material;
use App\Services\Materials\MaterialLinesMap;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cas limites de la carte des lignes d'une série : série sans lien, lien sans tracé, deux liens pour une ligne,
 * périodes (tri, confiance), tracé des ères réunis sans doublon, arrondi, tracé retenu et affichage, cache.
 */
class MaterialLinesMapBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private Material $material;

    private Era $era;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->material = Material::factory()->create(['slug' => 'z-9999', 'name' => 'Z 9999', 'type' => 'automotrice', 'in_service_from' => 1990, 'in_service_to' => null]);
        $this->era = Era::factory()->create(['year' => 1990]);
    }

    /** @return array<string, mixed> */
    private function map(bool $display = false): array
    {
        return app(MaterialLinesMap::class)->for($this->material->fresh(), $display);
    }

    private function line(string $name, string $slug): Line
    {
        return Line::factory()->create(['name' => $name, 'slug' => $slug, 'code' => null]);
    }

    private function segment(Line $line, string $source = 'osm', float $lat = 48.8, ?Era $era = null, float $lng = 2.1234567): void
    {
        LineSegment::factory()->create(['line_id' => $line->id, 'era_id' => ($era ?? $this->era)->id, 'source' => $source,
            'geom' => LineString::make([Point::makeGeodetic($lat, $lng), Point::makeGeodetic($lat - 0.1, $lng - 0.1)])]);
    }

    private function link(Line $line, string $source = 'wikipedia', ?string $confidence = 'high', ?array $evidence = null): LineMaterial
    {
        return LineMaterial::create(['line_id' => $line->id, 'material_id' => $this->material->id, 'source' => $source, 'confidence' => $confidence,
            'source_url' => "https://example.org/{$source}", 'evidence' => $evidence]);
    }

    private function period(LineMaterial $link, ?string $start, ?string $end, string $confidence = 'high', string $fingerprint = ''): void
    {
        LineMaterialPeriod::create(['line_material_id' => $link->id, 'start_date' => $start, 'start_precision' => $start === null ? null : 'day',
            'end_date' => $end, 'end_precision' => $end === null ? null : 'day', 'confidence' => $confidence, 'origin' => 'line_article',
            'source' => 'wikipedia', 'source_url' => 'https://example.org/p', 'fingerprint' => sha1("{$start}|{$end}|{$confidence}|{$fingerprint}")]);
    }

    public function test_a_series_without_any_link_has_an_empty_map_and_its_identity(): void
    {
        $map = $this->map();

        $this->assertSame([], $map['lines']);
        $this->assertSame(['slug' => 'z-9999', 'name' => 'Z 9999', 'in_service_from' => 1990, 'in_service_to' => null], $map['material']);
    }

    public function test_a_link_without_any_segment_gives_a_null_geometry_that_is_not_withheld(): void
    {
        $this->link($this->line('Ligne A', 'a'));

        $line = $this->map()['lines'][0];

        $this->assertNull($line['geometry']);
        $this->assertFalse($line['geometry_withheld']);
        $this->assertSame([], $line['periods']);
    }

    public function test_lines_come_in_name_order_and_the_evidence_fields_are_null_without_evidence(): void
    {
        foreach (['Ligne C', 'Ligne A', 'Ligne B'] as $i => $name) {
            $this->link($this->line($name, "l{$i}"), 'wikipedia', 'medium', $name === 'Ligne A' ? [['kind' => 'k', 'article' => 'Art', 'quote' => 'Cit']] : null);
        }

        $lines = $this->map()['lines'];

        $this->assertSame(['Ligne A', 'Ligne B', 'Ligne C'], array_column($lines, 'name'));
        $this->assertSame(['k', 'Art', 'Cit'], [$lines[0]['kind'], $lines[0]['article'], $lines[0]['quote']]);
        $this->assertSame([null, null, null], [$lines[1]['kind'], $lines[1]['article'], $lines[1]['quote']]);
    }

    public function test_two_links_for_one_line_give_one_entry_with_the_surest_link_and_all_periods(): void
    {
        $line = $this->line('Ligne A', 'a');
        $this->link($line, 'wikidata', 'low');
        $surest = $this->link($line, 'wikipedia', 'high', [['kind' => 'sure', 'article' => null, 'quote' => 'q']]);
        $this->link($line, 'manual', 'medium');
        $this->period($surest, '2000-01-01', null);

        $lines = $this->map()['lines'];

        $this->assertCount(1, $lines);
        $this->assertSame(['high', 'wikipedia', 'sure'], [$lines[0]['confidence'], $lines[0]['source'], $lines[0]['kind']]);
        $this->assertCount(1, $lines[0]['periods']);
    }

    public function test_a_link_without_confidence_ranks_last(): void
    {
        $line = $this->line('Ligne A', 'a');
        $this->link($line, 'wikidata', null);
        $this->link($line, 'wikipedia', 'low');

        $this->assertSame('low', $this->map()['lines'][0]['confidence']);
    }

    public function test_periods_are_sorted_by_start_then_end_and_only_shown_confidences_are_kept(): void
    {
        $link = $this->link($this->line('Ligne A', 'a'));
        $this->period($link, '2010-05-05', '2012-01-01');
        $this->period($link, null, '2005-01-01');
        $this->period($link, '1999-09-09', null);
        $this->period($link, '1980-01-01', '1981-01-01', 'low');
        $this->period($link, '2001-01-01', null, 'medium');

        $periods = $this->map()['lines'][0]['periods'];

        $this->assertSame(['1999-09-09', '2001-01-01', null, '2010-05-05'], array_column($periods, 'start_date'));
        $this->assertSame([null, null, '2005-01-01', '2012-01-01'], array_column($periods, 'end_date'));
        $this->assertSame(['day', 'day', null, 'day'], array_column($periods, 'start_precision'));
    }

    public function test_identical_geometries_of_several_eras_are_drawn_once_and_the_coordinates_have_four_decimals(): void
    {
        $line = $this->line('Ligne A', 'a');
        $this->link($line);
        $this->segment($line, 'osm', 48.8);
        $this->segment($line, 'osm', 48.8, Era::factory()->create(['year' => 1960]));
        $this->segment($line, 'osm', 48.8, Era::factory()->create(['year' => 1970]));

        $geometry = $this->map()['lines'][0]['geometry'];

        $this->assertSame('MultiLineString', $geometry['type']);
        $this->assertCount(1, $geometry['coordinates']);
        foreach ($geometry['coordinates'][0] as [$lng, $lat]) {
            $this->assertSame(round($lng, 4), $lng);
            $this->assertSame(round($lat, 4), $lat);
        }
        $this->assertSame(2.1235, $geometry['coordinates'][0][0][0]);
    }

    public function test_different_geometries_of_the_same_line_are_all_kept(): void
    {
        $line = $this->line('Ligne A', 'a');
        $this->link($line);
        $this->segment($line, 'osm', 48.8);
        $this->segment($line, 'osm', 47.0, Era::factory()->create(['year' => 1960]));

        $this->assertCount(2, $this->map()['lines'][0]['geometry']['coordinates']);
    }

    public function test_the_geometry_of_another_line_is_never_mixed_in(): void
    {
        $a = $this->line('Ligne A', 'a');
        $b = $this->line('Ligne B', 'b');
        $this->link($a);
        $this->segment($b, 'osm', 40.0);

        $this->assertNull($this->map()['lines'][0]['geometry']);
    }

    public function test_the_display_map_draws_the_withheld_geometry_and_the_api_does_not(): void
    {
        $line = $this->line('Ligne A', 'a');
        $this->link($line);
        $this->segment($line, 'archeoferroviaire', 48.8);

        $api = $this->map(false)['lines'][0];
        $display = $this->map(true)['lines'][0];

        $this->assertNull($api['geometry']);
        $this->assertTrue($api['geometry_withheld']);
        $this->assertNotNull($display['geometry']);
        $this->assertFalse($display['geometry_withheld']);
    }

    public function test_a_partly_withheld_line_serves_its_open_part_and_says_a_part_is_missing(): void
    {
        $line = $this->line('Ligne A', 'a');
        $this->link($line);
        $this->segment($line, 'osm', 48.8);
        $this->segment($line, 'archeoferroviaire', 47.0, Era::factory()->create(['year' => 1960]));

        $r = $this->map()['lines'][0];

        $this->assertTrue($r['geometry_withheld']);
        $this->assertCount(1, $r['geometry']['coordinates']);
    }

    public function test_a_segment_without_a_source_is_withheld_not_served(): void
    {
        $line = $this->line('Ligne A', 'a');
        $this->link($line);
        $this->segment($line, 'osm', 48.8);
        DB::table('line_segments')->update(['source' => null]);

        $r = $this->map()['lines'][0];

        $this->assertNull($r['geometry']);
        $this->assertTrue($r['geometry_withheld']);
    }

    public function test_the_trace_is_simplified_to_about_forty_metres(): void
    {
        $line = $this->line('Ligne A', 'a');
        $this->link($line);
        LineSegment::factory()->create(['line_id' => $line->id, 'era_id' => $this->era->id, 'source' => 'osm', 'geom' => LineString::make([
            Point::makeGeodetic(48.0, 2.0), Point::makeGeodetic(48.00005, 2.05), Point::makeGeodetic(48.0, 2.1), Point::makeGeodetic(48.05, 2.15),
        ])]);

        $coords = $this->map()['lines'][0]['geometry']['coordinates'][0];

        // Le point écarté de 5 m est supprimé, le coude de 5 km est gardé.
        $this->assertCount(3, $coords);
    }

    public function test_the_two_modes_do_not_share_their_cache(): void
    {
        $line = $this->line('Ligne A', 'a');
        $this->link($line);
        $this->segment($line, 'archeoferroviaire', 48.8);

        $display = $this->map(true)['lines'][0]['geometry'];
        $api = $this->map(false)['lines'][0]['geometry'];

        $this->assertNotNull($display);
        $this->assertNull($api);
    }

    public function test_a_new_period_or_link_is_seen_at_once_despite_the_cache(): void
    {
        $line = $this->line('Ligne A', 'a');
        $link = $this->link($line);
        $this->assertSame([], $this->map()['lines'][0]['periods']);

        $this->travel(2)->minutes();
        $this->period($link, '2001-01-01', null);
        $this->assertCount(1, $this->map()['lines'][0]['periods']);

        $this->travel(2)->minutes();
        $this->link($this->line('Ligne B', 'b'));
        $this->assertCount(2, $this->map()['lines']);
    }

    public function test_the_answer_is_cached_for_a_day(): void
    {
        $this->link($this->line('Ligne A', 'a'));
        $this->map();

        $this->assertSame(86_400, MaterialLinesMap::CACHE_TTL);
        $this->assertCount(1, array_filter(array_keys($this->cacheKeys()), fn (string $k): bool => str_starts_with($k, 'material-lines-map:')));
    }

    /** @return array<string, mixed> */
    private function cacheKeys(): array
    {
        $store = Cache::getStore();

        return (new \ReflectionProperty($store, 'storage'))->getValue($store);
    }

    public function test_the_links_of_another_series_are_not_listed(): void
    {
        $other = Material::factory()->create(['slug' => 'y-1', 'name' => 'Y 1', 'type' => 'automotrice']);
        $line = $this->line('Ligne A', 'a');
        LineMaterial::create(['line_id' => $line->id, 'material_id' => $other->id, 'source' => 'wikipedia', 'confidence' => 'high']);

        $this->assertSame([], $this->map()['lines']);
    }
}
