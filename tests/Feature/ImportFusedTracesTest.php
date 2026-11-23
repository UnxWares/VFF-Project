<?php

namespace Tests\Feature;

use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * vff:import-fused-traces : lecture seule par défaut ; avec --write, morceaux insérés ou remplacés dans
 * source_traces (source fusion), anciens morceaux de la ligne retirés, morceau « précis » au-delà de 3 m refusé
 * comme précis.
 */
class ImportFusedTracesTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = (string) tempnam(sys_get_temp_dir(), 'fusion');
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
        parent::tearDown();
    }

    /**
     * @param  list<array{0: string, 1: float, 2: int}>  $pieces  classe, erreur, début
     */
    private function write(int $line, array $pieces): void
    {
        $features = array_map(fn (array $p): array => [
            'type' => 'Feature',
            'properties' => ['ligne' => $line, 'chaine' => 0, 'classe' => $p[0], 'erreur_estimee_max_m' => $p[1], 'debut_m' => $p[2]],
            'geometry' => ['type' => 'LineString', 'coordinates' => [[-0.37 + $p[2] / 1e5, 49.25], [-0.36 + $p[2] / 1e5, 49.25]]],
        ], $pieces);
        file_put_contents($this->file, json_encode(['type' => 'FeatureCollection', 'features' => $features]));
    }

    public function test_without_write_nothing_is_written(): void
    {
        $line = Line::factory()->create();
        $this->write($line->id, [['precis', 1.2, 0]]);

        $this->artisan('vff:import-fused-traces', ['files' => [$this->file]])
            ->expectsOutputToContain('1 morceaux sur 1 lignes : 1 précis, 0 estimés.')
            ->assertSuccessful();
        $this->assertSame(0, DB::table('source_traces')->where('source', 'fusion')->count());
    }

    public function test_write_inserts_then_replaces_the_line_pieces(): void
    {
        $line = Line::factory()->create();
        $this->write($line->id, [['precis', 1.2, 0], ['precis', 4.0, 900]]);
        $this->artisan('vff:import-fused-traces', ['files' => [$this->file], '--write' => true])->assertSuccessful();

        $rows = DB::table('source_traces')->where('source', 'fusion')->orderBy('source_ref')->get();
        $this->assertSame(['precis', 'estime'], $rows->pluck('layer')->all());
        $this->assertGreaterThan(700, $rows[0]->length_m);
        $this->assertSame(1.2, json_decode($rows[0]->properties, true)['erreur_p90_m']);

        // Nouveau calcul : un seul morceau ; l'ancien morceau à 900 m disparaît, celui à 0 m est remplacé.
        $this->write($line->id, [['estime', 6.0, 0]]);
        $this->artisan('vff:import-fused-traces', ['files' => [$this->file], '--write' => true])->assertSuccessful();
        $rows = DB::table('source_traces')->where('source', 'fusion')->get();
        $this->assertCount(1, $rows);
        $this->assertSame('estime', $rows[0]->layer);
    }

    public function test_unknown_line_is_refused_without_writing(): void
    {
        $this->write(987654, [['precis', 1.0, 0]]);

        $this->artisan('vff:import-fused-traces', ['files' => [$this->file], '--write' => true])
            ->expectsOutputToContain('Lignes inconnues : 987654')
            ->assertFailed();
        $this->assertSame(0, DB::table('source_traces')->where('source', 'fusion')->count());
    }

    public function test_invalid_file_fails(): void
    {
        file_put_contents($this->file, json_encode(['type' => 'Feature']));

        $this->artisan('vff:import-fused-traces', ['files' => [$this->file]])->assertFailed();
    }

    public function test_file_that_is_not_json_fails_cleanly(): void
    {
        file_put_contents($this->file, '{"type": "FeatureCollection", "features": [{"properties": {"erreur_estimee_max_m": Infinity}}]}');

        $this->artisan('vff:import-fused-traces', ['files' => [$this->file]])->assertFailed();
    }
}
