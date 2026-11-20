<?php

namespace Tests\Feature\GraphQL;

use App\Models\Contribution;
use App\Models\LineEvent;
use App\Models\Material;
use App\Models\Media;
use App\Models\User;
use App\Services\Api\ApiTokens;
use App\Services\Contributions\MediaContributions;
use App\Services\Media\ImageSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\ActsAsRoles;
use Tests\Support\BuildsRailNetwork;
use Tests\Support\BuildsTestImages;
use Tests\TestCase;

/**
 * Mutation `submitContribution` étendue aux événements datés (`input.event`) et aux images
 * (`input.media` : Wikimedia Commons, ou fichier déposé en requête multipart), avec les règles du
 * site, puis publication par `validateContribution`.
 */
class EventMediaContributionMutationsTest extends TestCase
{
    use ActsAsRoles;
    use BuildsRailNetwork;
    use BuildsTestImages;
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private const SUBMIT = 'mutation ($input: ContributionInput!) {
        submitContribution(input: $input) {
            id type action status approvals approvals_required payload
            line { slug }
            station { slug name }
            material { slug name }
        }
    }';

    private const VALIDATE = 'mutation ($id: ID!) {
        validateContribution(id: $id, decision: APPROVE) { outcome contribution { status payload } }
    }';

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
        config(['api.rate_limits.anonymous' => 300, 'api.rate_limits.member' => 1200]);
        Storage::fake(MediaContributions::PRIVATE_DISK);
        Storage::fake(MediaContributions::PUBLIC_DISK);
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            $this->deleteTestImages();
        }
    }

    private function token(User $user): string
    {
        return app(ApiTokens::class)->create($user, 'Script', true)->plainTextToken;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return TestResponse<Response>
     */
    private function submit(string $token, array $input): TestResponse
    {
        return $this->withToken($token)->graphQL(self::SUBMIT, ['input' => $input]);
    }

    /** @return array<string, mixed> */
    private function event(array $overrides = []): array
    {
        return [
            'line_slug' => $this->line->slug,
            'type' => 'electrification',
            'precision' => 'year',
            'date' => '1976',
            'portion' => 'whole',
            'source_kind' => 'print',
            'source_title' => 'La Vie du Rail, n° 1550, p. 12',
            ...$overrides,
        ];
    }

    /** @return TestResponse<Response> */
    private function approveTwice(int|string $id): TestResponse
    {
        $this->withToken($this->token($this->member('validator')))->graphQL(self::VALIDATE, ['id' => (string) $id]);

        return $this->withToken($this->token($this->member('validator')))->graphQL(self::VALIDATE, ['id' => (string) $id]);
    }

    public function test_a_dated_event_is_proposed_then_published_by_two_approvals(): void
    {
        $response = $this->submit($this->token($this->member()), ['event' => $this->event()])
            ->assertJsonPath('data.submitContribution.type', 'LINE_EVENT')
            ->assertJsonPath('data.submitContribution.action', 'CREATE')
            ->assertJsonPath('data.submitContribution.status', 'PENDING')
            ->assertJsonPath('data.submitContribution.line.slug', $this->line->slug)
            ->assertJsonPath('data.submitContribution.payload.date', '1976-01-01')
            ->assertJsonPath('data.submitContribution.payload.precision', 'year');
        $id = $response->json('data.submitContribution.id');

        $this->approveTwice($id)->assertJsonPath('data.validateContribution.outcome', 'APPLIED');

        $event = LineEvent::query()->where('fingerprint', "contribution:{$id}")->firstOrFail();
        $this->assertSame(['electrification', 'year', 'contrib'], [$event->getRawOriginal('type'), $event->date_precision, $event->source]);
        $this->assertSame('La Vie du Rail, n° 1550, p. 12', $event->source_quote);
    }

    public function test_event_input_errors_carry_the_field_path(): void
    {
        $token = $this->token($this->member());

        $this->submit($token, ['event' => $this->event(['source_kind' => 'web'])])
            ->assertGraphQLValidationError('input.event.source_url', __('proposals.event.errors.source_url', locale: 'en'));
        $this->submit($token, ['event' => $this->event(['precision' => 'day', 'date' => '1976-02-30'])])
            ->assertGraphQLValidationError('input.event.date', __('editor.errors.bad_date', locale: 'en'));
        $this->submit($token, ['event' => $this->event(['line_slug' => 'inconnue'])])
            ->assertGraphQLValidationKeys(['input.event.line_slug']);
        $this->submit($token, ['event' => $this->event(), 'media' => ['source' => 'commons', 'target_type' => 'line']])
            ->assertGraphQLValidationError('input', 'Provide exactly one of "segment", "trace", "event", "media", "line_material" or "series".');
        $this->assertSame(0, Contribution::count());
    }

    public function test_a_commons_image_is_proposed_for_a_station(): void
    {
        Http::fake(['commons.wikimedia.org/*' => Http::response(['query' => ['pages' => [[
            'pageid' => 777,
            'title' => 'File:Gare de Paris-Saint-Lazare.jpg',
            'imageinfo' => [[
                'url' => 'https://upload.wikimedia.org/wikipedia/commons/a/ab/Gare.jpg',
                'thumburl' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/Gare.jpg/500px-Gare.jpg',
                'descriptionurl' => 'https://commons.wikimedia.org/wiki/File:Gare_de_Paris-Saint-Lazare.jpg',
                'width' => 2000, 'height' => 1500, 'mime' => 'image/jpeg',
                'extmetadata' => ['LicenseShortName' => ['value' => 'CC0'], 'License' => ['value' => 'cc0'], 'Artist' => ['value' => 'Anonyme']],
            ]],
        ]]]])]);

        $this->submit($this->token($this->member()), ['media' => [
            'source' => 'commons',
            'target_type' => 'station',
            'station_slug' => $this->origin->slug,
            'commons_url' => 'https://commons.wikimedia.org/wiki/File:Gare_de_Paris-Saint-Lazare.jpg',
        ]])
            ->assertJsonPath('data.submitContribution.type', 'MEDIA')
            ->assertJsonPath('data.submitContribution.line', null)
            ->assertJsonPath('data.submitContribution.station.slug', $this->origin->slug)
            ->assertJsonPath('data.submitContribution.payload.commons.page_id', 777)
            ->assertJsonPath('data.submitContribution.payload.commons.rights_status', 'public_domain');
    }

    public function test_a_commons_image_is_proposed_for_a_rolling_stock_series(): void
    {
        Http::fake(['commons.wikimedia.org/*' => Http::response(['query' => ['pages' => [[
            'pageid' => 778,
            'title' => 'File:X 73500.jpg',
            'imageinfo' => [[
                'url' => 'https://upload.wikimedia.org/wikipedia/commons/a/ab/X73500.jpg',
                'thumburl' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/X73500.jpg/500px-X73500.jpg',
                'descriptionurl' => 'https://commons.wikimedia.org/wiki/File:X_73500.jpg',
                'width' => 2000, 'height' => 1500, 'mime' => 'image/jpeg',
                'extmetadata' => ['LicenseShortName' => ['value' => 'CC0'], 'License' => ['value' => 'cc0'], 'Artist' => ['value' => 'Anonyme']],
            ]],
        ]]]])]);
        $material = Material::factory()->create(['slug' => 'x-73500', 'name' => 'X 73500']);

        $id = $this->submit($this->token($this->member()), ['media' => [
            'source' => 'commons',
            'target_type' => 'material',
            'material_slug' => 'x-73500',
            'commons_url' => 'https://commons.wikimedia.org/wiki/File:X_73500.jpg',
        ]])
            ->assertJsonPath('data.submitContribution.type', 'MEDIA')
            ->assertJsonPath('data.submitContribution.line', null)
            ->assertJsonPath('data.submitContribution.material.slug', 'x-73500')
            ->assertJsonPath('data.submitContribution.payload.material_name', 'X 73500')
            ->json('data.submitContribution.id');

        $this->approveTwice($id)->assertJsonPath('data.validateContribution.contribution.status', 'APPLIED');
        $this->assertSame(1, Media::query()->where('mediable_type', Material::class)->where('mediable_id', $material->id)->count());
        $this->graphQL('{ media(first: 5, filter: { material: "x-73500" }) { edges { node { source_url } } } }')
            ->assertGraphQLErrorFree()
            ->assertJsonPath('data.media.edges.0.node.source_url', 'https://commons.wikimedia.org/wiki/File:X_73500.jpg')
            ->assertJsonCount(1, 'data.media.edges');

        $this->submit($this->token($this->member()), ['media' => [
            'source' => 'commons', 'target_type' => 'material', 'material_slug' => 'inconnue',
            'commons_url' => 'https://commons.wikimedia.org/wiki/File:X_73500.jpg',
        ]])->assertGraphQLValidationKeys(['input.media.material_slug']);
    }

    public function test_an_uploaded_image_goes_through_a_multipart_request_without_its_metadata(): void
    {
        $operations = [
            'query' => self::SUBMIT,
            'variables' => ['input' => ['media' => [
                'source' => 'upload',
                'target_type' => 'line',
                'line_slug' => $this->line->slug,
                'file' => null,
                'media_type' => 'postcard',
                'title' => 'Carte postale de la gare de Versailles',
                'author' => 'Auteur inconnu',
                'license' => 'public_domain',
                'captured_precision' => 'year',
                'captured_date' => '1910',
                'rights_confirmed' => true,
            ]]],
        ];

        $response = $this->multipartGraphQL(
            $operations,
            ['0' => ['variables.input.media.file']],
            ['0' => $this->uploaded($this->jpegWithLocation(1200, 800, 1))],
            ['Authorization' => 'Bearer '.$this->token($this->member())],
        )->assertJsonPath('data.submitContribution.status', 'PENDING');

        $payload = $response->json('data.submitContribution.payload');
        $this->assertArrayNotHasKey('path', $payload['file'], 'le chemin de la réserve privée ne sort pas');
        $this->assertContains('gps', $payload['file']['removed']);
        $contribution = Contribution::query()->findOrFail($response->json('data.submitContribution.id'));
        $bytes = (string) Storage::disk(MediaContributions::PRIVATE_DISK)->get($contribution->payload['file']['path']);
        $this->assertStringNotContainsString(self::TEST_GPS_MARK, $bytes);
        $this->assertFalse(ImageSanitizer::metadataLeft($bytes, 'jpeg'));

        $this->approveTwice($contribution->id)->assertJsonPath('data.validateContribution.contribution.status', 'APPLIED');
        $media = Media::query()->where('external_id', 'contribution:'.$contribution->id)->firstOrFail();
        $this->assertSame(['postcard', 'Public domain', 1910], [$media->getRawOriginal('type'), $media->license, $media->depicted_year]);
        $this->assertTrue(Storage::disk(MediaContributions::PUBLIC_DISK)->exists((string) $media->file_path));
    }

    public function test_an_upload_without_a_free_licence_is_refused(): void
    {
        $operations = ['query' => self::SUBMIT, 'variables' => ['input' => ['media' => [
            'source' => 'upload', 'target_type' => 'line', 'line_slug' => $this->line->slug, 'file' => null,
            'media_type' => 'photo', 'title' => 'Photo de la gare', 'author' => 'Jean', 'license' => 'all_rights_reserved', 'rights_confirmed' => true,
        ]]]];

        $this->multipartGraphQL(
            $operations,
            ['0' => ['variables.input.media.file']],
            ['0' => $this->uploaded($this->cleanJpeg(800, 600))],
            ['Authorization' => 'Bearer '.$this->token($this->member())],
        )->assertGraphQLValidationKeys(['input.media.license']);

        $this->assertSame(0, Contribution::count());
        $this->assertSame([], Storage::disk(MediaContributions::PRIVATE_DISK)->allFiles());
    }
}
