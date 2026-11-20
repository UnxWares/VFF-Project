<?php

namespace Tests\Feature\Media;

use App\Models\Line;
use App\Models\Media;
use App\Services\Contributions\MediaContributions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Adresses venues de Commons lues dans la base (REST, GraphQL, fiche d'une série, proposition en
 * attente) : une adresse qui n'est pas en http(s) devient nulle, même enregistrée avant le contrôle
 * de l'import (relecture du 20/11/2026, `license_url` en `javascript:`). La licence reste nommée.
 */
class ExternalUrlSchemeTest extends TestCase
{
    use BuildsRailNetwork;
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private const BAD = [
        'license_url' => 'javascript:fetch(\'/validate/accounts\')',
        'source_url' => 'JaVaScRiPt:alert(1)',
        'thumb_url' => '   javascript:alert(1)',
        'display_url' => 'data:text/html,<script>alert(1)</script>',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
    }

    /** Média enregistré directement en base, comme par un import antérieur au contrôle. */
    private function storedMedia(): int
    {
        return DB::table('media')->insertGetId([
            'mediable_type' => Line::class, 'mediable_id' => $this->line->id, 'provider' => 'commons', 'external_id' => 'x',
            'type' => 'photo', 'title' => 'Gare', 'license' => 'CC BY-SA 4.0', 'rights_status' => 'open_license',
            'mime_type' => 'image/jpeg', 'created_at' => now(), 'updated_at' => now(), ...self::BAD,
        ]);
    }

    public function test_the_rest_api_never_gives_an_address_that_is_not_http(): void
    {
        $id = $this->storedMedia();

        $media = $this->getJson("/api/v1/media/{$id}")->assertOk()->json('data');

        foreach (array_keys(self::BAD) as $key) {
            $this->assertNull($media[$key], $key);
        }
        $this->assertSame('CC BY-SA 4.0', $media['license']);
        $line = $this->getJson("/api/v1/lines/{$this->line->slug}/media")->assertOk()->json('data.0');
        $this->assertNull($line['license_url']);
        $this->assertNull($line['source_url']);
    }

    public function test_graphql_never_gives_an_address_that_is_not_http(): void
    {
        $id = $this->storedMedia();

        $media = $this->graphQL('query ($id: ID!) { mediaItem(id: $id) { license license_url source_url thumb_url display_url } }', ['id' => $id])
            ->assertGraphQLErrorFree()->json('data.mediaItem');

        $this->assertSame(['license' => 'CC BY-SA 4.0', 'license_url' => null, 'source_url' => null, 'thumb_url' => null, 'display_url' => null], $media);
    }

    public function test_the_model_refuses_to_write_an_address_that_is_not_http(): void
    {
        $media = Media::factory()->create(['mediable_type' => Line::class, 'mediable_id' => $this->line->id, ...self::BAD]);

        $row = DB::table('media')->where('id', $media->id)->first(array_keys(self::BAD));
        foreach (array_keys(self::BAD) as $key) {
            $this->assertNull($row->{$key}, $key);
        }
    }

    public function test_the_image_of_a_series_never_gives_an_address_that_is_not_http(): void
    {
        DB::table('materials')->where('id', $this->material->id)->update(['image' => json_encode([
            'provider' => 'commons', 'file' => 'File:Z.jpg', 'license' => 'CC BY 4.0', 'rights_status' => 'open_license',
            'attribution' => 'X, CC BY 4.0, via Wikimedia Commons', ...self::BAD,
        ])]);

        $image = $this->getJson("/api/v1/materials/{$this->material->slug}")->assertOk()->json('data.image');

        foreach (array_keys(self::BAD) as $key) {
            $this->assertNull($image[$key], $key);
        }
        $this->assertSame('CC BY 4.0', $image['license']);
    }

    public function test_a_pending_commons_proposal_shows_no_address_that_is_not_http(): void
    {
        $payload = ['source' => 'commons', 'commons' => ['license' => 'CC BY-SA 4.0', 'page_id' => 1, ...self::BAD]];

        $shown = MediaContributions::withoutPrivatePath($payload)['commons'];
        $summary = MediaContributions::commonsSummary($payload['commons']);

        foreach (array_keys(self::BAD) as $key) {
            $this->assertNull($shown[$key], $key);
            $this->assertNull($summary[$key], $key);
        }
        $this->assertSame('CC BY-SA 4.0', $shown['license']);
    }
}
