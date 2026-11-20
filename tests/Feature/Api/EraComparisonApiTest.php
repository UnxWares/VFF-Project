<?php

namespace Tests\Feature\Api;

use App\Models\Era;
use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\DecodesMvt;
use Tests\TestCase;

/**
 * Comparaison de deux ères (résumé et tuiles) et tuiles de toutes les ères empilées.
 *
 * Réseau fictif en Île-de-France (tuile 8/129/88), un tronçon par ligne, même géométrie d'une
 * ère à l'autre comme le produit le moteur de l'historique.
 */
class EraComparisonApiTest extends TestCase
{
    use DecodesMvt;
    use RefreshDatabase;

    private const TILE = '8/129/88';

    /** @var array<string, Line> */
    private array $lines = [];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $e1930 = Era::factory()->create(['year' => 1930]);
        $e1980 = Era::factory()->create(['year' => 1980]);
        Era::factory()->current()->create(['year' => 2030]);

        // Ligne => [statut en 1930, statut en 1980] (null : absente de l'ère), longueur en mètres.
        $network = [
            'kept' => ['in_service', 'in_service', 10_000],
            'closed' => ['in_service', 'abandoned', 4_000],
            'gone' => ['in_service', null, 3_000],
            'lifted' => ['abandoned', 'dismantled', 2_000],
            'new' => [null, 'in_service', 1_500],
            'already_closed' => ['abandoned', 'abandoned', 700],
        ];
        $lat = 48.40;
        foreach ($network as $name => [$before, $after, $meters]) {
            $lat += 0.05;
            $this->lines[$name] = Line::factory()->create(['name' => "Ligne {$name}", 'slug' => "ligne-{$name}"]);
            foreach ([[$e1930, $before], [$e1980, $after]] as [$era, $status]) {
                if ($status !== null) {
                    $this->segment($this->lines[$name], $era, $status, $lat, $meters);
                }
            }
        }
    }

    public function test_summary_counts_each_change_in_km_and_lines(): void
    {
        $response = $this->getJson('/api/v1/eras/1930/compare/1980')->assertOk();

        $response->assertJsonPath('data.from', 1930)->assertJsonPath('data.to', 1980);
        $this->assertSame([
            'opened' => ['km' => 2, 'lines' => 1],
            'closed' => ['km' => 4, 'lines' => 1],
            'removed' => ['km' => 5, 'lines' => 2],
            'kept' => ['km' => 10, 'lines' => 1],
        ], $response->json('data.changes'));
        // En service : kept + closed + gone en 1930 (17 km), kept + new en 1980 (11,5 km).
        $this->assertSame(['from' => 17, 'to' => 12], $response->json('data.open_km'));
        $response->assertJsonPath('data.links.map', url('/maps?era=1930&compare=1980'));
        $response->assertJsonPath('data.links.tiles', url('/api/v1/eras/1930/compare/1980/tiles').'/{z}/{x}/{y}.mvt');
    }

    public function test_summary_is_the_same_whatever_the_order_of_the_years(): void
    {
        $this->assertSame(
            $this->getJson('/api/v1/eras/1930/compare/1980')->json('data'),
            $this->getJson('/api/v1/eras/1980/compare/1930')->json('data'),
        );
    }

    public function test_same_year_is_rejected_and_unknown_year_is_404(): void
    {
        $this->getJson('/api/v1/eras/1930/compare/1930')->assertStatus(422);
        $this->getJson('/api/v1/eras/1935/compare/1930')
            ->assertNotFound()
            ->assertJsonPath('message', 'Unknown era 1935: eras are the decades 1830 to 2030.');
        $this->get('/api/v1/eras/1930/compare/1980/tiles/'.self::TILE.'.mvt')->assertOk();
        $this->get('/api/v1/eras/1930/compare/1890/tiles/'.self::TILE.'.mvt')->assertNotFound();
        $this->get('/api/v1/eras/1930/compare/1980/tiles/2/9/0.mvt')->assertNotFound();
    }

    public function test_compare_tile_tags_each_segment_with_its_change(): void
    {
        $response = $this->get('/api/v1/eras/1980/compare/1930/tiles/'.self::TILE.'.mvt')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/x-protobuf');

        $changes = [];
        foreach ($this->decodeMvt($response->getContent())['vff-compare'] ?? [] as $props) {
            $changes[$props['line_slug']] = $props['change'];
        }
        ksort($changes);
        $this->assertSame([
            'ligne-closed' => 'closed',
            'ligne-gone' => 'removed',
            'ligne-kept' => 'kept',
            'ligne-lifted' => 'removed',
            'ligne-new' => 'opened',
        ], $changes, 'une ligne déjà fermée aux deux dates ne figure pas dans la comparaison');
    }

    public function test_stacked_tile_gives_first_and_last_decade_in_service(): void
    {
        $response = $this->get('/api/v1/eras/stacked/tiles/'.self::TILE.'.mvt')->assertOk();

        $spans = [];
        foreach ($this->decodeMvt($response->getContent())['vff-stack'] ?? [] as $props) {
            $spans[$props['line_slug']] = [$props['first_year'], $props['last_year']];
        }
        ksort($spans);
        $this->assertSame([
            'ligne-closed' => [1930, 1930],
            'ligne-gone' => [1930, 1930],
            'ligne-kept' => [1930, 1980],
            'ligne-new' => [1980, 1980],
        ], $spans, 'seuls les tronçons en service à au moins une décennie, une fois chacun');
    }

    private function segment(Line $line, Era $era, string $status, float $lat, int $meters): void
    {
        DB::table('line_segments')->insert([
            'line_id' => $line->id, 'era_id' => $era->id, 'status' => $status, 'traffic_type' => 'mixed',
            'length_meters' => $meters,
            'geom' => DB::raw(sprintf("ST_GeomFromText('LINESTRING(2.0 %F, 2.6 %F)', 4326)", $lat, $lat)),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
