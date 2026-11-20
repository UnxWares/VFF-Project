<?php

namespace Tests\Feature\Api;

use App\Models\Line;
use App\Models\Material;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * GET /api/v1/media et /api/v1/media/{id} : médias de toutes les lignes et gares, avec auteur,
 * licence et lien vers la source de chacun.
 */
class MediaApiTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    /** @var array<string, int> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();

        $this->ids['sheet'] = $this->media(Line::class, $this->line->id, [
            'title' => 'Gare en 2010', 'type' => 'photo', 'depicted_year' => 2010, 'author' => 'Jean Photo',
            'license' => 'CC BY-SA 4.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0',
            'rights_status' => 'open_license', 'commercial_ok' => true, 'share_alike' => true,
            'attribution' => 'Jean Photo, CC BY-SA 4.0, via Wikimedia Commons',
            'source_url' => 'https://commons.wikimedia.org/wiki/File:A.jpg',
            'location' => DB::raw('ST_SetSRID(ST_MakePoint(2.3253, 48.8763), 4326)'),
        ]);
        $this->ids['card'] = $this->media(Station::class, $this->origin->id, [
            'title' => 'La gare vers 1905', 'type' => 'postcard', 'depicted_year' => 1905, 'license' => 'Public domain',
            'rights_status' => 'public_domain', 'commercial_ok' => true, 'share_alike' => false,
            'provider_meta' => json_encode(['date_precision' => 'circa', 'year_to' => 1910, 'attribution_required' => false]),
            'attribution' => 'Public domain, via Wikimedia Commons', 'source_url' => 'https://commons.wikimedia.org/wiki/File:B.jpg',
        ]);
        $this->ids['undated'] = $this->media(Station::class, $this->destination->id, [
            'title' => 'Sans date', 'type' => 'photo', 'depicted_year' => null, 'license' => null,
            'rights_status' => 'unknown', 'commercial_ok' => null, 'attribution' => 'licence non précisée, via Wikimedia Commons',
            'source_url' => 'https://commons.wikimedia.org/wiki/File:C.jpg',
        ]);
        $this->ids['map'] = $this->media(Material::class, $this->material->id, [
            'title' => 'Plan du matériel', 'type' => 'map', 'depicted_year' => 1950, 'license' => 'cc by-sa 4.0',
            'rights_status' => 'open_license', 'commercial_ok' => false, 'share_alike' => true, 'provider' => 'upload',
            'thumb_url' => null, 'file_path' => 'media/plan.png', 'source_url' => null,
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function media(string $subjectType, int $subjectId, array $attributes): int
    {
        static $n = 0;
        $n++;

        return DB::table('media')->insertGetId($attributes + [
            'mediable_type' => $subjectType, 'mediable_id' => $subjectId, 'provider' => 'commons', 'external_id' => "ext-{$n}",
            'mime_type' => 'image/jpeg', 'thumb_url' => "https://upload.wikimedia.org/thumb-{$n}.jpg",
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_lists_every_media_with_rights_source_and_subject(): void
    {
        $this->getJson('/api/v1/media')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=300, public')
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.title', 'Gare en 2010')
            ->assertJsonPath('data.0.author', 'Jean Photo')
            ->assertJsonPath('data.0.license', 'CC BY-SA 4.0')
            ->assertJsonPath('data.0.license_url', 'https://creativecommons.org/licenses/by-sa/4.0')
            ->assertJsonPath('data.0.attribution', 'Jean Photo, CC BY-SA 4.0, via Wikimedia Commons')
            ->assertJsonPath('data.0.source_url', 'https://commons.wikimedia.org/wiki/File:A.jpg')
            ->assertJsonPath('data.0.provider_label', 'Wikimedia Commons')
            ->assertJsonPath('data.0.location.lat', 48.8763)
            ->assertJsonPath('data.0.location.lng', 2.3253)
            ->assertJsonPath('data.0.subject', ['type' => 'line', 'slug' => $this->line->slug, 'name' => $this->line->name])
            ->assertJsonPath('data.1.subject.type', 'station')
            ->assertJsonPath('data.1.subject.slug', $this->origin->slug)
            ->assertJsonPath('data.1.date_precision', 'circa')
            ->assertJsonPath('data.1.depicted_year_to', 1910)
            ->assertJsonPath('data.1.attribution_required', false)
            ->assertJsonPath('data.2.license', null)
            ->assertJsonPath('data.2.rights_status', 'unknown')
            ->assertJsonPath('data.2.location', null)
            ->assertJsonPath('data.3.subject.type', 'material')
            ->assertJsonPath('meta.per_page', 24)
            ->assertJsonPath('links.next', null);
    }

    public function test_filters_by_line_and_by_station(): void
    {
        $this->getJson("/api/v1/media?line={$this->line->slug}")->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Gare en 2010');
        $this->getJson("/api/v1/media?station={$this->origin->slug}")->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'La gare vers 1905');
        $this->getJson("/api/v1/media?station={$this->destination->slug}")->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Sans date');
    }

    public function test_filters_by_type_provider_licence_and_rights(): void
    {
        $this->getJson('/api/v1/media?type=photo')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/media?type=postcard')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/media?provider=upload')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.thumb_url', '/storage/media/plan.png')
            ->assertJsonPath('data.0.provider_label', 'VFF');
        // La licence ignore la casse : « CC BY-SA 4.0 » et « cc by-sa 4.0 » sont la même.
        $this->getJson('/api/v1/media?license=CC%20BY-SA%204.0')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/media?license=Public%20domain')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/media?rights_status=unknown')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/media?rights_status=open_license')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/media?commercial_ok=true')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/media?commercial_ok=false')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Plan du matériel');
        $this->getJson('/api/v1/media?type=photo&rights_status=unknown')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_filters_by_period_and_years(): void
    {
        $this->getJson('/api/v1/media?period=1900_1944')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/media?period=since2000')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/media?period=undated')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Sans date');
        $this->getJson('/api/v1/media?from_year=1900&to_year=1950')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/media?from_year=1951')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/media?to_year=1904')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_orders_by_id_and_paginates_by_cursor(): void
    {
        $this->getJson('/api/v1/media?order=desc')->assertOk()->assertJsonPath('data.0.id', $this->ids['map']);

        $seen = [];
        $url = '/api/v1/media?per_page=3&order=asc';
        while ($url !== null) {
            $response = $this->getJson($url)->assertOk();
            array_push($seen, ...array_column($response->json('data'), 'id'));
            $url = $response->json('links.next');
        }
        $this->assertSame([...array_values($this->ids)], $seen);
    }

    public function test_rejects_invalid_filters(): void
    {
        $this->getJson("/api/v1/media?line={$this->line->slug}&station={$this->origin->slug}")->assertUnprocessable()->assertJsonValidationErrors(['line', 'station']);
        $this->getJson('/api/v1/media?line=inconnue')->assertUnprocessable()->assertJsonValidationErrors('line');
        $this->getJson('/api/v1/media?station=inconnue')->assertUnprocessable()->assertJsonValidationErrors('station');
        $this->getJson('/api/v1/media?type=video')->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->getJson('/api/v1/media?rights_status=libre')->assertUnprocessable()->assertJsonValidationErrors('rights_status');
        $this->getJson('/api/v1/media?commercial_ok=peut-etre')->assertUnprocessable()->assertJsonValidationErrors('commercial_ok');
        $this->getJson('/api/v1/media?period=hier')->assertUnprocessable()->assertJsonValidationErrors('period');
        $this->getJson('/api/v1/media?from_year=1950&to_year=1900')->assertUnprocessable()->assertJsonValidationErrors('to_year');
        $this->getJson('/api/v1/media?per_page=61')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }

    public function test_show_returns_one_media_with_its_subject(): void
    {
        $this->getJson("/api/v1/media/{$this->ids['card']}")
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=300, public')
            ->assertJsonPath('data.id', $this->ids['card'])
            ->assertJsonPath('data.title', 'La gare vers 1905')
            ->assertJsonPath('data.license', 'Public domain')
            ->assertJsonPath('data.source_url', 'https://commons.wikimedia.org/wiki/File:B.jpg')
            ->assertJsonPath('data.subject.slug', $this->origin->slug);

        $this->getJson("/api/v1/media/{$this->ids['sheet']}")->assertOk()
            ->assertJsonPath('data.location.lat', 48.8763);
    }

    public function test_show_unknown_or_oversized_id_is_a_404(): void
    {
        $this->getJson('/api/v1/media/999999999')->assertNotFound();
        $this->getJson('/api/v1/media/99999999999999999999')->assertNotFound();
        $this->getJson('/api/v1/media/abc')->assertNotFound();
    }

    public function test_line_media_endpoint_keeps_its_contract_without_subject(): void
    {
        $item = $this->getJson("/api/v1/lines/{$this->line->slug}/media")->assertOk()->json('data.0');

        $this->assertSame('Gare en 2010', $item['title']);
        $this->assertArrayNotHasKey('subject', $item);
        $this->assertSame(48.8763, $item['location']['lat']);
    }
}
