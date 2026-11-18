<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Models\LineSegment;
use App\Support\TrackPriority;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Voies partagées entre lignes (vff:build-shared-track) : sur une voie
 * commune, seule la ligne la plus active est dessinée ; une voie qui diverge
 * reste dessinée ; un recouvrement caduc est ignoré par les tuiles.
 */
class SharedTrackTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    private LineSegment $active;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
        $this->line->update(['network' => 'principal']);
        $this->active = LineSegment::where('line_id', $this->line->id)->where('era_id', $this->currentEra->id)->firstOrFail();
        $this->active->update(['traffic_type' => 'freight']);
    }

    /** Ligne posée sur le tronc Saint-Lazare → (48.8530, 2.2400), puis qui diverge vers le nord. */
    private function sharedLine(string $slug, string $network, string $status, string $traffic): LineSegment
    {
        $line = Line::factory()->create(['slug' => $slug, 'name' => "Ligne {$slug}", 'network' => $network]);

        return LineSegment::factory()->create([
            'line_id' => $line->id,
            'era_id' => $this->currentEra->id,
            'geom' => LineString::make([
                Point::makeGeodetic(48.8763, 2.3253),
                Point::makeGeodetic(48.8530, 2.2400),
                Point::makeGeodetic(48.9000, 2.2000),
            ]),
            'status' => $status,
            'traffic_type' => $traffic,
            'is_heritage' => false,
        ]);
    }

    /** @return array<string, float> longueur (m) dessinée / masquée de la portion, telle que la découpent les tuiles */
    private function parts(LineSegment $segment): array
    {
        $rows = DB::select('SELECT part.covered, ST_Length(part.g::geography) AS m FROM line_segments ls '
            .TrackPriority::partsJoinSql('ls').' WHERE ls.id = ?', [$segment->id]);
        $out = ['drawn' => 0.0, 'covered' => 0.0];
        foreach ($rows as $r) {
            $out[$r->covered ? 'covered' : 'drawn'] += $r->m;
        }

        return $out;
    }

    public function test_closed_line_on_shared_track_is_hidden_but_its_branch_stays_drawn(): void
    {
        $closed = $this->sharedLine('fermee', 'principal', 'abandoned', 'unknown');

        $this->artisan('vff:build-shared-track', ['--era' => 2030])->assertSuccessful();

        $closedParts = $this->parts($closed);
        // Tronc commun (~6,8 km) masqué, branche divergente (~6,0 km) dessinée.
        $this->assertEqualsWithDelta(6770, $closedParts['covered'], 150);
        $this->assertEqualsWithDelta(6000, $closedParts['drawn'], 150);
        // La ligne en service n'est jamais masquée par une ligne fermée.
        $this->assertSame(0.0, $this->parts($this->active)['covered']);
        $this->assertSame(0, DB::table('line_segment_covers')->where('segment_id', $this->active->id)->count());
    }

    public function test_private_line_does_not_recolour_the_rfn(): void
    {
        // Ligne particulière « mixte » posée sur une ligne RFN « fret » : le RFN reste dessiné.
        $private = $this->sharedLine('mine', 'particulier', 'in_service', 'mixed');

        $this->artisan('vff:build-shared-track', ['--era' => 2030])->assertSuccessful();

        $this->assertGreaterThan(6000, $this->parts($private)['covered']);
        $this->assertSame(0.0, $this->parts($this->active)['covered']);
    }

    public function test_stale_cover_is_ignored_by_tiles(): void
    {
        $closed = $this->sharedLine('fermee', 'principal', 'abandoned', 'unknown');
        $this->artisan('vff:build-shared-track', ['--era' => 2030])->assertSuccessful();
        $this->assertGreaterThan(0, $this->parts($closed)['covered']);

        // La ligne recouvrante ferme (moteur relancé, statut corrigé…) : recouvrement caduc.
        $this->active->update(['status' => 'abandoned']);
        $this->assertSame(0.0, $this->parts($closed)['covered']);

        // Recalcul : à état égal, la ligne la plus ancienne (plus petite line_id) l'emporte.
        $this->artisan('vff:build-shared-track', ['--era' => 2030])->assertSuccessful();
        $this->assertSame(0.0, $this->parts($this->active)['covered']);
        $this->assertGreaterThan(6000, $this->parts($closed)['covered']);
    }

    public function test_tile_exports_draw_rank_and_covered(): void
    {
        $this->sharedLine('fermee', 'principal', 'abandoned', 'unknown');
        $this->artisan('vff:build-shared-track', ['--era' => 2030])->assertSuccessful();

        $body = $this->get('/api/v1/eras/2030/tiles/8/129/88.mvt')->assertOk()->getContent();
        $this->assertStringContainsString('draw_rank', $body);
        $this->assertStringContainsString('covered', $body);
    }
}
