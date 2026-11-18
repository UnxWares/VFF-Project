<?php

namespace Tests\Feature\Api;

use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * GET /api/v1/lines/{slug}/media : médias externes (Commons) avec leurs
 * droits, tri par époque, filtres et compteurs.
 */
class LineMediaApiTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();

        $base = [
            'mediable_type' => Line::class, 'mediable_id' => $this->line->id, 'provider' => 'commons',
            'mime_type' => 'image/jpeg', 'created_at' => now(), 'updated_at' => now(),
        ];
        $rows = [
            $base + ['external_id' => '1', 'type' => 'photo', 'title' => 'Gare en 2010', 'depicted_year' => 2010,
                'author' => 'Jean Photo', 'license' => 'CC BY-SA 4.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0',
                'rights_status' => 'open_license', 'commercial_ok' => true, 'share_alike' => true,
                'attribution' => 'Jean Photo, CC BY-SA 4.0, via Wikimedia Commons',
                'thumb_url' => 'https://upload.wikimedia.org/a/500px-a.jpg', 'source_url' => 'https://commons.wikimedia.org/wiki/File:A.jpg'],
            $base + ['external_id' => '2', 'type' => 'postcard', 'title' => 'La gare vers 1905', 'depicted_year' => 1905,
                'license' => 'Public domain', 'rights_status' => 'public_domain', 'commercial_ok' => true, 'share_alike' => false,
                'attribution' => 'Public domain, via Wikimedia Commons',
                'provider_meta' => json_encode(['date_precision' => 'circa']),
                'thumb_url' => 'https://upload.wikimedia.org/b/500px-b.jpg', 'source_url' => 'https://commons.wikimedia.org/wiki/File:B.jpg'],
            $base + ['external_id' => '3', 'type' => 'photo', 'title' => 'Sans date', 'depicted_year' => null,
                'license' => null, 'rights_status' => 'unknown', 'attribution' => 'licence non précisée, via Wikimedia Commons',
                'thumb_url' => 'https://upload.wikimedia.org/c/500px-c.jpg', 'source_url' => 'https://commons.wikimedia.org/wiki/File:C.jpg'],
        ];
        foreach ($rows as $row) {
            DB::table('media')->insert($row);
        }
    }

    public function test_sorted_by_period_with_rights_on_each_item(): void
    {
        $this->getJson("/api/v1/lines/{$this->line->slug}/media")
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.title', 'La gare vers 1905')
            ->assertJsonPath('data.0.date_precision', 'circa')
            ->assertJsonPath('data.0.rights_status', 'public_domain')
            ->assertJsonPath('data.0.source_url', 'https://commons.wikimedia.org/wiki/File:B.jpg')
            ->assertJsonPath('data.1.license', 'CC BY-SA 4.0')
            ->assertJsonPath('data.1.attribution', 'Jean Photo, CC BY-SA 4.0, via Wikimedia Commons')
            ->assertJsonPath('data.2.title', 'Sans date')
            ->assertJsonPath('data.2.license', null)
            ->assertJsonPath('facets.types.photo', 2)
            ->assertJsonPath('facets.periods.1900_1944', 1)
            ->assertJsonPath('facets.periods.undated', 1)
            ->assertJsonPath('facets.rights.unknown', 1);
    }

    public function test_filters_pagination_and_validation(): void
    {
        $slug = $this->line->slug;

        $this->getJson("/api/v1/lines/{$slug}/media?type=postcard")
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.type', 'postcard');
        $this->getJson("/api/v1/lines/{$slug}/media?period=since2000")
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.depicted_year', 2010);
        $this->getJson("/api/v1/lines/{$slug}/media?per_page=1&page=2&sort=newest")
            ->assertOk()->assertJsonPath('meta.last_page', 3)->assertJsonPath('data.0.depicted_year', 1905);

        $this->getJson("/api/v1/lines/{$slug}/media?per_page=500")->assertUnprocessable();
        $this->getJson("/api/v1/lines/{$slug}/media?period=1850s")->assertUnprocessable();
        $this->getJson('/api/v1/lines/inexistante/media')->assertNotFound();
    }
}
