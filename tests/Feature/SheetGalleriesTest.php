<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\Line;
use App\Models\LineEvent;
use App\Models\Material;
use App\Models\Media;
use App\Models\Station;
use App\Models\User;
use App\Services\Contributions\MediaContributions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\Support\ActsAsRoles;
use Tests\Support\BuildsRailNetwork;
use Tests\Support\BuildsTestImages;
use Tests\TestCase;

/**
 * Images publiées des gares, des séries de matériel et des événements : galerie des fiches gare et
 * série (présente seulement s'il y a des images), images d'un événement dans la chronologie de la
 * fiche ligne, points d'accès de galerie de l'API, GraphQL et MCP. Une image proposée et encore en
 * attente de validation n'apparaît nulle part.
 */
class SheetGalleriesTest extends TestCase
{
    use ActsAsRoles;
    use BuildsRailNetwork;
    use BuildsTestImages;
    use MakesGraphQLRequests;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
        $this->withCookie('locale', 'fr');
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

    /**
     * Propose une photo déposée (comme le formulaire /contribute/media).
     *
     * @param  array<string, mixed>  $target
     */
    private function propose(User $author, array $target): Contribution
    {
        $this->actingAs($author)->post('/contribute/media', [
            'source' => 'upload',
            'line_slug' => $this->line->slug,
            'file' => $this->uploaded($this->jpegWithLocation(1600, 1200, 1)),
            'media_type' => 'photo',
            'title' => 'Bâtiment voyageurs, côté cour',
            'author' => 'Marie Durand',
            'license' => 'cc-by-4.0',
            'rights_confirmed' => '1',
            ...$target,
        ])->assertRedirect()->assertSessionHasNoErrors();

        return Contribution::query()->latest('id')->firstOrFail();
    }

    /**
     * Image Commons déjà publiée (rattachée directement, comme MediaContributions::apply).
     *
     * @param  class-string  $type
     */
    private function commonsMedia(string $type, int $id, ?int $contributionId = 77): Media
    {
        $mediaId = DB::table('media')->insertGetId([
            'mediable_type' => $type, 'mediable_id' => $id, 'provider' => 'commons', 'external_id' => (string) random_int(1, 999999),
            'type' => 'photo', 'title' => 'Gare de Nontron', 'caption' => 'La gare de Nontron vue du quai.',
            'author' => 'Jean Dupont', 'license' => 'CC BY-SA 4.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0',
            'attribution' => 'Jean Dupont, CC BY-SA 4.0, via Wikimedia Commons', 'rights_status' => 'open_license',
            'commercial_ok' => true, 'share_alike' => true, 'mime_type' => 'image/jpeg', 'width' => 3000, 'height' => 2000,
            'depicted_year' => 1962, 'is_featured' => false,
            'thumb_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/Gare_de_Nontron.jpg/500px-Gare_de_Nontron.jpg',
            'display_url' => 'https://upload.wikimedia.org/wikipedia/commons/a/ab/Gare_de_Nontron.jpg',
            'source_url' => 'https://commons.wikimedia.org/wiki/File:Gare_de_Nontron.jpg',
            'provider_meta' => json_encode(array_filter(['source' => $contributionId ? "contribution:{$contributionId}" : null, 'contribution_id' => $contributionId])),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return Media::query()->findOrFail($mediaId);
    }

    // ─── Gare ───────────────────────────────────────────────────────────────

    public function test_a_station_without_images_has_no_gallery(): void
    {
        $this->get("/stations/{$this->origin->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Stations/Show')->where('media_count', 0));
        $this->getJson("/api/v1/stations/{$this->origin->slug}/media")
            ->assertOk()
            ->assertJsonPath('station.slug', $this->origin->slug)
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_a_pending_station_image_is_never_shown_then_appears_once_approved(): void
    {
        $contribution = $this->propose($this->member(), ['target_type' => 'station', 'station_slug' => $this->origin->slug]);
        $private = (string) $contribution->payload['file']['path'];
        $this->assertTrue(Storage::disk(MediaContributions::PRIVATE_DISK)->exists($private));

        // En attente (puis approuvée une fois) : ni la fiche, ni l'API, ni GraphQL, ni MCP ne la montrent.
        foreach ([null, $this->member('validator')] as $validator) {
            if ($validator !== null) {
                $this->actingAs($validator)->post("/validate/{$contribution->id}/approve")->assertSessionHas('success');
            }
            auth()->logout();
            $this->get("/stations/{$this->origin->slug}")->assertInertia(fn (Assert $page) => $page->where('media_count', 0));
            $gallery = $this->getJson("/api/v1/stations/{$this->origin->slug}/media")->assertOk()->assertJsonPath('meta.total', 0);
            $list = $this->getJson("/api/v1/media?station={$this->origin->slug}")->assertOk()->assertJsonCount(0, 'data');
            $this->assertStringNotContainsString(basename($private), $gallery->getContent().$list->getContent());
            $this->graphQL('query ($slug: String!) { station(slug: $slug) { media(first: 10) { edges { node { id } } } } }', ['slug' => $this->origin->slug])
                ->assertJsonCount(0, 'data.station.media.edges');
            $this->assertSame(0, Media::query()->count());
        }

        // Seconde approbation (d'un autre validateur) : la contribution est publiée.
        $this->actingAs($this->member('validator'))->post("/validate/{$contribution->id}/approve")->assertSessionHas('success');
        $media = Media::query()->findOrFail($contribution->fresh()->payload['applied']['media_id']);
        auth()->logout();

        $this->get("/stations/{$this->origin->slug}")->assertInertia(fn (Assert $page) => $page->where('media_count', 1));
        $item = $this->getJson("/api/v1/stations/{$this->origin->slug}/media")
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=300, public')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('facets.providers.upload', 1)
            ->assertJsonPath('facets.periods.since2000', 0)
            ->json('data.0');
        $this->assertSame($media->id, $item['id']);
        $this->assertSame(['upload', 'VFF', 'Marie Durand', 'CC BY 4.0', 'Marie Durand, CC BY 4.0'], [$item['provider'], $item['provider_label'], $item['author'], $item['license'], $item['attribution']]);
        $this->assertSame('https://creativecommons.org/licenses/by/4.0/', $item['license_url']);
        $this->assertSame($contribution->id, $item['contribution_id']);
        $this->assertNull($item['source_url']);
        // Fichier publié servi comme ceux de la fiche ligne (disque public, /storage/…), jamais la réserve privée.
        $this->assertStringContainsString('/storage/'.MediaContributions::PUBLIC_DIR.'/'.$contribution->id.'-', (string) $item['thumb_url']);
        $this->assertSame($item['thumb_url'], $item['display_url']);
        $this->assertStringNotContainsString('contributions/media', (string) $item['thumb_url']);
    }

    public function test_the_station_gallery_validates_its_parameters(): void
    {
        $slug = $this->origin->slug;
        $this->getJson("/api/v1/stations/{$slug}/media?per_page=61")->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson("/api/v1/stations/{$slug}/media?sort=random")->assertUnprocessable()->assertJsonValidationErrors('sort');
        $this->getJson("/api/v1/stations/{$slug}/media?period=1850s")->assertUnprocessable()->assertJsonValidationErrors('period');
        $this->getJson('/api/v1/stations/inconnue/media')->assertNotFound();
    }

    // ─── Série de matériel ──────────────────────────────────────────────────

    public function test_a_series_gallery_appears_only_with_images_and_carries_its_credits(): void
    {
        $material = Material::factory()->create(['slug' => 'x-73500', 'name' => 'X 73500']);
        $this->get('/materials/x-73500')->assertOk()->assertInertia(fn (Assert $page) => $page->where('media_count', 0));

        $media = $this->commonsMedia(Material::class, $material->id);
        $this->commonsMedia(Station::class, $this->origin->id); // une autre fiche : pas comptée

        $this->get('/materials/x-73500')->assertInertia(fn (Assert $page) => $page->where('media_count', 1));
        $this->getJson('/api/v1/materials/x-73500/media')
            ->assertOk()
            ->assertJsonPath('material', ['slug' => 'x-73500', 'name' => 'X 73500'])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $media->id)
            ->assertJsonPath('data.0.author', 'Jean Dupont')
            ->assertJsonPath('data.0.license_url', 'https://creativecommons.org/licenses/by-sa/4.0')
            ->assertJsonPath('data.0.source_url', 'https://commons.wikimedia.org/wiki/File:Gare_de_Nontron.jpg')
            ->assertJsonPath('data.0.provider_label', 'Wikimedia Commons')
            ->assertJsonPath('data.0.contribution_id', 77)
            ->assertJsonPath('facets.periods.1945_1969', 1);
        $this->getJson('/api/v1/materials/x-73500')->assertJsonPath('data.links.media', route('api.materials.media', 'x-73500'));
        $this->getJson('/api/v1/materials/inconnue/media')->assertNotFound();
    }

    public function test_an_imported_media_has_no_contribution_number(): void
    {
        $media = $this->commonsMedia(Line::class, $this->line->id, null);

        $this->getJson("/api/v1/lines/{$this->line->slug}/media")->assertJsonPath('data.0.id', $media->id)->assertJsonPath('data.0.contribution_id', null);
    }

    // ─── Événement ──────────────────────────────────────────────────────────

    public function test_an_event_image_is_shown_in_the_line_chronicle_with_its_credits(): void
    {
        $event = LineEvent::query()->where('line_id', $this->line->id)->orderBy('id')->firstOrFail();
        $pending = $this->propose($this->member(), ['target_type' => 'event', 'event_id' => $event->id]);
        $media = $this->commonsMedia(LineEvent::class, $event->id);

        $this->get("/lines/{$this->line->slug}")
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($event, $media): void {
                /** @var list<array<string, mixed>> $list */
                $list = $page->toArray()['props']['chronicle']['events'];
                $events = collect($list)->keyBy('id');
                $this->assertSame([$media->id], array_column($events[$event->id]['media'], 'id'));
                $image = $events[$event->id]['media'][0];
                $this->assertSame(['Jean Dupont', 'CC BY-SA 4.0', 'Wikimedia Commons', 77], [$image['author'], $image['license'], $image['provider_label'], $image['contribution_id']]);
                $this->assertSame('https://commons.wikimedia.org/wiki/File:Gare_de_Nontron.jpg', $image['source_url']);
                $this->assertArrayNotHasKey('location', $image);
                $this->assertTrue($events->except([$event->id])->every(fn (array $e): bool => $e['media'] === []));
            });

        // L'image en attente n'est ni dans la chronique, ni dans le filtre `event` de l'API.
        $this->assertSame('pending', $pending->fresh()->status->value);
        $this->getJson("/api/v1/media?event={$event->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject', ['type' => 'event', 'id' => $event->id]);
        $this->getJson('/api/v1/media?event=999999999')->assertUnprocessable()->assertJsonValidationErrors('event');
        $this->getJson("/api/v1/media?event={$event->id}&line={$this->line->slug}")->assertUnprocessable();
        $this->getJson("/api/v1/lines/{$this->line->slug}")->assertJsonPath('data.chronicle.events.0.media', fn ($m) => is_array($m));
    }

    public function test_the_chronicle_reads_event_images_in_one_query(): void
    {
        LineEvent::factory()->count(2)->create(['line_id' => $this->line->id, 'type' => 'electrification']);
        $events = LineEvent::query()->where('line_id', $this->line->id)->pluck('id');
        $this->assertGreaterThan(2, $events->count());
        foreach ($events as $id) {
            $this->commonsMedia(LineEvent::class, (int) $id);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get("/lines/{$this->line->slug}")->assertOk();
        // Requêtes sur les images d'événements (celle de l'aperçu de partage porte sur les images de la ligne).
        $mediaQueries = array_filter(DB::getQueryLog(), fn (array $q): bool => str_contains($q['query'], 'from "media"') && in_array(LineEvent::class, $q['bindings'], true));
        DB::disableQueryLog();

        $this->assertCount(1, $mediaQueries, 'une seule requête pour les images de tous les événements');
    }

    // ─── GraphQL et MCP ─────────────────────────────────────────────────────

    public function test_graphql_gives_the_images_of_a_series_and_of_an_event(): void
    {
        $material = Material::factory()->create(['slug' => 'x-73500', 'name' => 'X 73500']);
        $event = LineEvent::query()->where('line_id', $this->line->id)->firstOrFail();
        $this->commonsMedia(Material::class, $material->id);
        $this->commonsMedia(LineEvent::class, $event->id, null);

        $this->graphQL('query ($slug: String!) { material(slug: $slug) { media(first: 5) { edges { node { author license contribution_id material { slug } event { id } } } } } }', ['slug' => 'x-73500'])
            ->assertJsonPath('data.material.media.edges.0.node', ['author' => 'Jean Dupont', 'license' => 'CC BY-SA 4.0', 'contribution_id' => 77, 'material' => ['slug' => 'x-73500'], 'event' => null]);
        $this->graphQL('query ($id: Int!) { media(filter: { event: $id }) { edges { node { contribution_id event { id } line { id } } } } }', ['id' => $event->id])
            ->assertJsonPath('data.media.edges.0.node', ['contribution_id' => null, 'event' => ['id' => (string) $event->id], 'line' => null]);
    }

    public function test_mcp_station_and_series_sheets_count_their_images(): void
    {
        $material = Material::factory()->create(['slug' => 'x-73500', 'name' => 'X 73500']);
        $this->commonsMedia(Station::class, $this->origin->id);
        $this->commonsMedia(Station::class, $this->origin->id);

        $this->assertSame(2, $this->mcp('get_station', ['station' => $this->origin->slug])['media_count']);
        $this->assertSame(0, $this->mcp('get_material', ['material' => 'x-73500'])['media_count']);
        $event = LineEvent::query()->where('line_id', $this->line->id)->firstOrFail();
        $this->commonsMedia(LineEvent::class, $event->id);
        $this->assertSame(['event'], array_column(array_column($this->mcp('media', ['event' => $event->id])['media'], 'subject'), 'type'));
        $this->assertSame($material->slug, $this->mcp('get_material', ['material' => 'x-73500'])['material']['slug']);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function mcp(string $tool, array $arguments): array
    {
        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => (object) $arguments]])
            ->assertOk()->json('result');
        $this->assertFalse((bool) $result['isError'], $result['content'][0]['text']);

        return json_decode($result['content'][0]['text'], true);
    }
}
