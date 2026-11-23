<?php

namespace Tests\Feature\Media;

use App\Models\Line;
use App\Services\Media\OpenData\OpenDataMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Rattachement d'une vue de viaduc ou de tunnel à une ligne : rayon de 100 m, 3 lignes au plus (base requise). */
class OpenDataMatcherLinesTest extends TestCase
{
    use RefreshDatabase;

    private const M = 111194.9266;

    /** Ligne est-ouest à `$metersNorth` mètres au nord du point (48.0, 2.0). */
    private function lineAt(string $name, float $metersNorth): Line
    {
        $line = Line::factory()->create(['name' => $name]);
        $lat = 48.0 + $metersNorth / self::M;
        DB::table('line_reference_parts')->insert(['line_id' => $line->id, 'source' => 'osm', 'source_trace_ids' => '[]', 'length_m' => 500,
            'geom' => DB::raw("ST_Multi(ST_GeomFromText('LINESTRING(1.99 {$lat}, 2.01 {$lat})', 4326))")]);

        return $line;
    }

    /** @return array<string, mixed> */
    private static function candidate(): array
    {
        return ['commune' => 'Meudon', 'title' => 'Le viaduc', 'title_alt' => null, 'station_hint' => null, 'caption' => null,
            'themes' => null, 'subjects' => null, 'lat' => 48.0, 'lng' => 2.0];
    }

    private function matcher(): OpenDataMatcher
    {
        $m = new OpenDataMatcher(false);
        $m->add(1, 'Meudon', 'Meudon', 'gare', 'active', 48.5, 2.5);

        return $m;
    }

    public function test_lines_near_keeps_those_within_100_metres_nearest_first(): void
    {
        $far = $this->lineAt('Ligne éloignée', 100.5);
        $near = $this->lineAt('Ligne proche', 40);
        $edge = $this->lineAt('Ligne au bord', 99);
        $lines = $this->matcher()->linesNear(48.0, 2.0);
        $this->assertSame([$near->id, $edge->id], array_column($lines, 'id'));
        $this->assertSame([40, 99], array_column($lines, 'distance_m'));
        $this->assertNotContains($far->id, array_column($lines, 'id'));
    }

    public function test_lines_near_is_limited_to_six_and_empty_far_from_any_line(): void
    {
        foreach (range(1, 8) as $i) {
            $this->lineAt("Ligne {$i}", $i);
        }
        $this->assertCount(6, $this->matcher()->linesNear(48.0, 2.0));
        $this->assertSame([], $this->matcher()->linesNear(10.0, 10.0));
    }

    public function test_a_view_is_attached_to_up_to_three_lines_and_more_are_a_doubt(): void
    {
        $a = $this->lineAt('Ligne A', 10);
        $b = $this->lineAt('Ligne B', 20);
        $c = $this->lineAt('Ligne C', 30);
        $dataset = ['point_precise' => true];
        $r = $this->matcher()->match(self::candidate(), $dataset);
        $this->assertSame(['line', $a->id, 'ligne-point', [$b->id, $c->id]], [$r['kind'], $r['id'], $r['method'], $r['also']]);

        $this->lineAt('Ligne D', 40);
        $r = $this->matcher()->match(self::candidate(), $dataset);
        $this->assertSame(['kind' => null, 'reason' => 'lignes_trop_nombreuses'], $r);
    }

    public function test_a_view_with_one_line_or_none(): void
    {
        $r = $this->matcher()->match(self::candidate(), ['point_precise' => true]);
        $this->assertNull($r['kind']);
        $a = $this->lineAt('Ligne A', 10);
        $r = $this->matcher()->match(self::candidate(), ['point_precise' => true]);
        $this->assertSame([$a->id, []], [$r['id'], $r['also']]);
    }
}
