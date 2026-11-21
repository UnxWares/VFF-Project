<?php

namespace Tests\Feature\Web;

use App\Models\Line;
use App\Services\LinePhases;
use App\Services\LineSheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Tracé de la carte d'une fiche de ligne, lu à part (GET /lines/{slug}/portions/{version}.json,
 * LinePortionsController) : la page n'en porte que le nombre et l'adresse.
 */
class LinePortionsTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
    }

    /** Adresse donnée par la page de la fiche. */
    private function pageUrl(): string
    {
        return $this->get('/lines/'.$this->line->slug)->assertOk()->viewData('page')['props']['portions_url'];
    }

    public function test_the_page_points_to_the_same_portions_as_the_complete_sheet(): void
    {
        $url = $this->pageUrl();
        $this->assertMatchesRegularExpression('#^/lines/'.$this->line->slug.'/portions/[0-9a-f]{12}\.json$#', $url);

        $response = $this->get($url)->assertOk()
            ->assertHeader('Content-Type', 'application/json; charset=utf-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $full = app(LineSheet::class)->build($this->line->fresh());

        // Mêmes portions, dans le même ordre (les phases de la page les désignent par leur index).
        $this->assertSame(json_encode($full['portions']), json_encode($response->json('portions')));
        $this->assertSame('LineString', $response->json('portions.0.geometry.type'));
        $this->assertStringEndsWith($response->json('version').'.json', $url);
    }

    public function test_the_current_version_is_cached_for_a_year_without_session(): void
    {
        $response = $this->get($this->pageUrl())->assertOk();

        $this->assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        // Hors du groupe web : aucun cookie de session, la même réponse pour tous.
        $this->assertSame([], $response->headers->getCookies());
        $this->assertNull($response->headers->get('X-VFF-Offline'));
    }

    public function test_an_old_version_gets_the_current_portions_without_long_cache(): void
    {
        $response = $this->get('/lines/'.$this->line->slug.'/portions/000000000000.json')->assertOk();

        $this->assertSame('no-cache, private', $response->headers->get('Cache-Control'));
        $this->assertSame(LinePhases::portions($this->line->fresh())['version'], $response->json('version'));
        $this->assertNotEmpty($response->json('portions'));
    }

    public function test_the_version_changes_with_the_segments(): void
    {
        $before = $this->pageUrl();
        // Écriture SQL brute, sans updated_at : la signature des tronçons change quand même.
        DB::statement('UPDATE line_segments SET status = status WHERE line_id = ?', [$this->line->id]);

        $this->assertNotSame($before, $this->pageUrl());
    }

    public function test_gzip_is_served_when_accepted_with_its_own_etag(): void
    {
        $url = $this->pageUrl();
        $plain = $this->get($url)->assertOk();
        $gzip = $this->get($url, ['Accept-Encoding' => 'gzip, br'])->assertOk()->assertHeader('Content-Encoding', 'gzip');

        $this->assertSame($plain->getContent(), gzdecode((string) $gzip->getContent()));
        $this->assertNotSame($plain->headers->get('ETag'), $gzip->headers->get('ETag'));
        $this->get($url, ['If-None-Match' => (string) $plain->headers->get('ETag')])->assertStatus(304);
    }

    public function test_a_line_without_segments_has_an_empty_route(): void
    {
        $empty = Line::factory()->create();
        $url = $this->get('/lines/'.$empty->slug)->assertOk()->viewData('page')['props']['portions_url'];

        $this->get($url)->assertOk()->assertJsonPath('portions', []);
    }

    public function test_unknown_lines_and_malformed_versions_are_404(): void
    {
        $this->get('/lines/rfn-000001/portions/0123456789ab.json')->assertNotFound();
        $this->get('/lines/'.$this->line->slug.'/portions/xyz.json')->assertNotFound();
    }
}
