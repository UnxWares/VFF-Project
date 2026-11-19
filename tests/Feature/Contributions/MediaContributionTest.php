<?php

namespace Tests\Feature\Contributions;

use App\Enums\AccountDeletionReason;
use App\Models\Contribution;
use App\Models\Line;
use App\Models\LineEvent;
use App\Models\Media;
use App\Models\Station;
use App\Models\User;
use App\Services\Account\AccountDeletion;
use App\Services\Contributions\MediaContributions;
use App\Services\Media\ImageSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ActsAsRoles;
use Tests\Support\BuildsRailNetwork;
use Tests\Support\BuildsTestImages;
use Tests\TestCase;

/**
 * Proposer une image (/contribute/media) : fichier déposé (type réel, taille, dimensions, licence de
 * la liste fermée, métadonnées et position GPS retirées avant l'enregistrement, fichier jamais
 * public avant la publication, effacé au refus, au retrait et à la suppression du compte) ou
 * fichier Wikimedia Commons (licence vérifiée sur Commons, doublons refusés), puis publication dans
 * `media` avec sa provenance après deux approbations.
 */
class MediaContributionTest extends TestCase
{
    use ActsAsRoles;
    use BuildsRailNetwork;
    use BuildsTestImages;
    use RefreshDatabase;

    private const COMMONS_URL = 'https://commons.wikimedia.org/wiki/File:Gare_de_Nontron.jpg';

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
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function upload(array $overrides = []): array
    {
        return [
            'source' => 'upload',
            'target_type' => 'line',
            'line_slug' => $this->line->slug,
            'file' => $this->uploaded($this->jpegWithLocation(1600, 1200, 6)),
            'media_type' => 'photo',
            'title' => 'Gare de Saint-Cloud, quai voyageurs',
            'caption' => 'Vue prise depuis la passerelle.',
            'author' => 'Marie Durand',
            'license' => 'cc-by-sa-4.0',
            'captured_precision' => 'year',
            'captured_date' => '1972',
            'place' => 'Saint-Cloud',
            'lat' => '48.8445',
            'lng' => '2.2196',
            'rights_confirmed' => '1',
            'notes' => 'Photo de famille, négatif retrouvé en 2025.',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function commons(array $overrides = []): array
    {
        return ['source' => 'commons', 'target_type' => 'line', 'line_slug' => $this->line->slug, 'commons_url' => self::COMMONS_URL, ...$overrides];
    }

    /** @param  array<string, mixed>  $data */
    private function submit(User $author, array $data): Contribution
    {
        $this->actingAs($author)->post('/contribute/media', $data)->assertRedirect()->assertSessionHasNoErrors();

        return Contribution::query()->latest('id')->firstOrFail();
    }

    private function privatePath(Contribution $contribution): string
    {
        return (string) $contribution->payload['file']['path'];
    }

    private function publish(Contribution $contribution): void
    {
        foreach ([$this->member('validator'), $this->member('validator')] as $validator) {
            $this->actingAs($validator)->post("/validate/{$contribution->id}/approve")->assertSessionHas('success');
        }
    }

    /** Réponse de l'API de Commons pour un fichier (imageinfo + extmetadata). */
    private function fakeCommons(string $license = 'CC BY-SA 4.0', string $mime = 'image/jpeg', bool $nonFree = false): void
    {
        Http::fake(['commons.wikimedia.org/*' => Http::response(['batchcomplete' => true, 'query' => ['pages' => [[
            'pageid' => 4242,
            'ns' => 6,
            'title' => 'File:Gare de Nontron.jpg',
            'imageinfo' => [[
                'thumburl' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/Gare_de_Nontron.jpg/500px-Gare_de_Nontron.jpg',
                'url' => 'https://upload.wikimedia.org/wikipedia/commons/a/ab/Gare_de_Nontron.jpg',
                'descriptionurl' => self::COMMONS_URL,
                'width' => 3000,
                'height' => 2000,
                'mime' => $mime,
                'extmetadata' => [
                    'LicenseShortName' => ['value' => $license],
                    'License' => ['value' => strtolower(str_replace(' ', '-', $license))],
                    'LicenseUrl' => ['value' => 'https://creativecommons.org/licenses/by-sa/4.0'],
                    'Artist' => ['value' => '<a href="https://commons.wikimedia.org/wiki/User:Jdupont">Jean Dupont</a>'],
                    'ObjectName' => ['value' => 'Gare de Nontron'],
                    'ImageDescription' => ['value' => 'La gare de Nontron vue du quai.'],
                    'DateTimeOriginal' => ['value' => '1962-07-14'],
                    'NonFree' => ['value' => $nonFree ? 'true' : 'false'],
                ],
            ]],
        ]]]])]);
    }

    // ─── Accès et formulaire ────────────────────────────────────────────────

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/contribute/media')->assertRedirect('/login');
        $this->post('/contribute/media', $this->upload())->assertRedirect('/login');
        $this->assertSame([], Storage::disk(MediaContributions::PRIVATE_DISK)->allFiles());
    }

    public function test_the_form_offers_only_free_licences_and_preselects_the_event_and_its_line(): void
    {
        $event = LineEvent::query()->where('line_id', $this->line->id)->firstOrFail();

        $this->actingAs($this->member())
            ->get("/contribute/media?event={$event->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contribute/ProposeMedia')
                ->where('preselected.eventId', $event->id)
                ->where('preselected.line.slug', $this->line->slug)
                ->where('licenses', fn (Collection $licenses) => $licenses->pluck('value')->all() === ['public_domain', 'cc0-1.0', 'cc-by-4.0', 'cc-by-sa-4.0', 'etalab-2.0'])
                ->where('limits.max_bytes', 10 * 1024 * 1024)
                ->where('mediaTypes', ['photo', 'plan', 'document', 'map', 'postcard']));
    }

    // ─── Fichier déposé : contrôles ─────────────────────────────────────────

    public function test_an_uploaded_photo_is_stored_privately_without_its_metadata(): void
    {
        $contribution = $this->submit($this->member(), $this->upload());

        $this->assertSame([MediaContributions::TYPE, 'pending'], [$contribution->type, $contribution->status->value]);
        $this->assertSame([Line::class, $this->line->id], [$contribution->target_type, (int) $contribution->target_id]);
        $path = $this->privatePath($contribution);
        $this->assertStringStartsWith(MediaContributions::PRIVATE_DIR.'/', $path);
        $bytes = Storage::disk(MediaContributions::PRIVATE_DISK)->get($path);
        $this->assertNotNull($bytes);
        $this->assertStringNotContainsString(self::TEST_GPS_MARK, $bytes, 'position GPS retirée avant l’enregistrement');
        $this->assertStringNotContainsString('Jean', $bytes);
        $this->assertFalse(ImageSanitizer::metadataLeft($bytes, 'jpeg', 6));
        $file = $contribution->payload['file'];
        $this->assertSame(['image/jpeg', 'jpg', 1200, 1600, 6], [$file['mime'], $file['extension'], $file['width'], $file['height'], $file['orientation']]);
        $this->assertContains('gps', $file['removed']);
        $this->assertSame(hash('sha256', $bytes), $file['sha256']);
        $this->assertSame([], Storage::disk(MediaContributions::PUBLIC_DISK)->allFiles(), 'rien de public avant la publication');
        $this->assertSame(['date' => '1972-01-01', 'precision' => 'year'], $contribution->payload['captured']);
        $this->assertSame(['lat' => 48.8445, 'lng' => 2.2196], $contribution->payload['location']);
        $this->assertSame(['cc-by-sa-4.0', 'CC BY-SA 4.0'], [$contribution->payload['license'], $contribution->payload['license_name']]);
    }

    public function test_the_type_is_read_in_the_file_not_in_its_name(): void
    {
        $contribution = $this->submit($this->member(), $this->upload([
            'file' => $this->uploaded($this->pngWithMetadata(800, 600), 'photo.jpg', 'image/jpeg'),
        ]));

        $this->assertSame(['image/png', 'png'], [$contribution->payload['file']['mime'], $contribution->payload['file']['extension']]);
        $this->assertStringEndsWith('.png', $this->privatePath($contribution));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function refusedFiles(): array
    {
        return [
            'texte nommé .jpg' => ['text', 'file'],
            'image trop petite' => ['small', 'file'],
            'image trop grande' => ['huge', 'file'],
            'fichier de plus de 10 Mo' => ['heavy', 'file'],
        ];
    }

    /** @param  'text'|'small'|'huge'|'heavy'  $kind */
    #[DataProvider('refusedFiles')]
    public function test_a_file_that_breaks_the_rules_is_refused_and_not_kept(string $kind, string $field): void
    {
        $bytes = match ($kind) {
            'text' => 'Ceci est un texte, pas une photo.',
            'small' => $this->cleanJpeg(300, 300),
            'huge' => $this->cleanJpeg(13000, 800),
            'heavy' => $this->cleanJpeg(1200, 800, 10 * 1024 * 1024 + 1),
        };

        $this->actingAs($this->member())->from('/contribute/media')
            ->post('/contribute/media', $this->upload(['file' => $this->uploaded($bytes)]))
            ->assertRedirect('/contribute/media')
            ->assertSessionHasErrors($field);

        $this->assertSame(0, Contribution::count());
        $this->assertSame([], Storage::disk(MediaContributions::PRIVATE_DISK)->allFiles());
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidDetails(): array
    {
        return [
            'licence hors liste' => [['license' => 'all_rights_reserved'], 'license'],
            'licence non commerciale' => [['license' => 'cc-by-nc-4.0'], 'license'],
            'sans auteur' => [['author' => ''], 'author'],
            'sans titre' => [['title' => ''], 'title'],
            'droits non confirmés' => [['rights_confirmed' => null], 'rights_confirmed'],
            'nature inconnue' => [['media_type' => 'selfie'], 'media_type'],
            'latitude sans longitude' => [['lng' => null], 'lng'],
            'latitude impossible' => [['lat' => '123'], 'lat'],
            'date impossible' => [['captured_precision' => 'day', 'captured_date' => '1972-02-31'], 'captured_date'],
            'date sans précision' => [['captured_precision' => null], 'captured_precision'],
            'gare inconnue' => [['target_type' => 'station', 'station_slug' => 'nulle-part'], 'station_slug'],
            'rattachement inconnu' => [['target_type' => 'materiel'], 'target_type'],
        ];
    }

    /** @param  array<string, mixed>  $overrides */
    #[DataProvider('invalidDetails')]
    public function test_details_breaking_the_rules_are_refused(array $overrides, string $field): void
    {
        $this->actingAs($this->member())->from('/contribute/media')
            ->post('/contribute/media', $this->upload($overrides))
            ->assertRedirect('/contribute/media')
            ->assertSessionHasErrors($field);

        $this->assertSame(0, Contribution::count());
        $this->assertSame([], Storage::disk(MediaContributions::PRIVATE_DISK)->allFiles());
    }

    public function test_the_same_file_cannot_be_proposed_twice(): void
    {
        $bytes = $this->cleanJpeg(1024, 768);
        $this->submit($this->member(), $this->upload(['file' => $this->uploaded($bytes)]));

        $this->actingAs($this->member())->from('/contribute/media')
            ->post('/contribute/media', $this->upload(['file' => $this->uploaded($bytes)]))
            ->assertSessionHasErrors(['file' => __('proposals.media.errors.pending_duplicate')]);
        $this->assertSame(1, Contribution::count());
    }

    // ─── Fichier privé ──────────────────────────────────────────────────────

    public function test_the_pending_file_is_shown_only_to_its_author_and_validators(): void
    {
        $author = $this->member();
        $contribution = $this->submit($author, $this->upload());
        $url = "/contribute/media/{$contribution->id}/file";

        $this->app['auth']->forgetGuards();
        $this->get($url)->assertRedirect('/login');
        $this->actingAs($this->member())->get($url)->assertForbidden();
        foreach ([$author, $this->member('validator'), $this->member('admin')] as $viewer) {
            $response = $this->actingAs($viewer)->get($url)->assertOk();
            $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
            $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
            $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
            $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
            $this->assertStringContainsString('sandbox', (string) $response->headers->get('Content-Security-Policy'));
        }

        $this->actingAs($this->member('validator'))->get('/validate')
            ->assertInertia(fn (Assert $page) => $page
                ->where('contributions.0.file_url', route('contribute.media.file', ['contribution' => $contribution->id]))
                ->missing('contributions.0.payload.file.path'));
    }

    public function test_a_rejection_deletes_the_private_file(): void
    {
        $author = $this->member();
        $contribution = $this->submit($author, $this->upload());
        $path = $this->privatePath($contribution);

        $this->actingAs($this->member('validator'))
            ->post("/validate/{$contribution->id}/reject", ['notes' => 'Photo floue, la gare n’est pas reconnaissable.'])
            ->assertSessionHas('success');

        $this->assertFalse(Storage::disk(MediaContributions::PRIVATE_DISK)->exists($path));
        $this->actingAs($author)->get("/contribute/media/{$contribution->id}/file")->assertNotFound();
        $this->assertSame(0, Media::query()->where('provider', Media::PROVIDER_UPLOAD)->count());
    }

    public function test_an_administrator_removal_deletes_the_private_file(): void
    {
        $contribution = $this->submit($this->member(), $this->upload());
        $path = $this->privatePath($contribution);

        $this->actingAs($this->member('admin'))
            ->delete("/validate/{$contribution->id}", ['reason' => 'Photo d’une personne reconnaissable sans son accord.'])
            ->assertSessionHas('success');

        $this->assertNull(Contribution::find($contribution->id));
        $this->assertFalse(Storage::disk(MediaContributions::PRIVATE_DISK)->exists($path));
    }

    public function test_deleting_the_account_deletes_the_pending_file(): void
    {
        $author = $this->member();
        $contribution = $this->submit($author, $this->upload());
        $path = $this->privatePath($contribution);

        app(AccountDeletion::class)->delete($author, AccountDeletionReason::Owner);

        $this->assertFalse(Storage::disk(MediaContributions::PRIVATE_DISK)->exists($path));
    }

    // ─── Publication ────────────────────────────────────────────────────────

    public function test_two_approvals_publish_the_file_with_its_provenance(): void
    {
        $contribution = $this->submit($this->member(), $this->upload());
        $private = $this->privatePath($contribution);

        $this->actingAs($this->member('validator'))->post("/validate/{$contribution->id}/approve");
        $this->assertSame([], Storage::disk(MediaContributions::PUBLIC_DISK)->allFiles(), 'une approbation ne rend rien public');
        $this->actingAs($this->member('validator'))->post("/validate/{$contribution->id}/approve")->assertSessionHas('success');

        $contribution->refresh();
        $this->assertSame('applied', $contribution->status->value);
        $media = Media::query()->findOrFail($contribution->payload['applied']['media_id']);
        $this->assertSame([Line::class, $this->line->id], [$media->mediable_type, $media->mediable_id]);
        $this->assertSame([Media::PROVIDER_UPLOAD, 'contribution:'.$contribution->id], [$media->provider, $media->external_id]);
        $this->assertSame('contribution:'.$contribution->id, $media->provider_meta['source']);
        $this->assertSame(['Marie Durand', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0/'], [$media->author, $media->license, $media->license_url]);
        $this->assertSame([Media::RIGHTS_OPEN_LICENSE, true, true], [$media->rights_status, $media->commercial_ok, $media->share_alike]);
        $this->assertSame('Marie Durand, CC BY-SA 4.0', $media->attribution);
        $this->assertSame(['1972-01-01', 1972, 'year'], [$media->captured_at->toDateString(), $media->depicted_year, $media->provider_meta['date_precision']]);
        $this->assertSame([1200, 1600, 'image/jpeg'], [$media->width, $media->height, $media->mime_type]);
        $this->assertSame('Saint-Cloud', $media->provider_meta['place']);
        $location = DB::selectOne('SELECT ST_Y(location) AS lat, ST_X(location) AS lng FROM media WHERE id = ?', [$media->id]);
        $this->assertEqualsWithDelta([48.8445, 2.2196], [(float) $location->lat, (float) $location->lng], 0.00001);

        $this->assertStringStartsWith(MediaContributions::PUBLIC_DIR.'/'.$contribution->id.'-', (string) $media->file_path);
        $this->assertTrue(Storage::disk(MediaContributions::PUBLIC_DISK)->exists((string) $media->file_path));
        $public = (string) Storage::disk(MediaContributions::PUBLIC_DISK)->get((string) $media->file_path);
        $this->assertStringNotContainsString(self::TEST_GPS_MARK, $public);
        $this->assertSame($media->file_size, strlen($public));
        $this->assertFalse(Storage::disk(MediaContributions::PRIVATE_DISK)->exists($private), 'plus rien dans la réserve privée');

        $this->getJson("/api/v1/media/{$media->id}")
            ->assertOk()
            ->assertJsonPath('data.provider', 'upload')
            ->assertJsonPath('data.author', 'Marie Durand')
            ->assertJsonPath('data.license', 'CC BY-SA 4.0');
    }

    public function test_an_image_can_be_attached_to_an_event_of_the_line(): void
    {
        $event = LineEvent::query()->where('line_id', $this->line->id)->firstOrFail();
        $contribution = $this->submit($this->member(), $this->upload(['target_type' => 'event', 'event_id' => $event->id]));
        $this->assertSame($event->id, $contribution->payload['event']['id']);

        $this->publish($contribution);

        $media = Media::query()->findOrFail($contribution->fresh()->payload['applied']['media_id']);
        $this->assertSame([LineEvent::class, $event->id], [$media->mediable_type, $media->mediable_id]);
    }

    public function test_an_event_of_another_line_is_refused(): void
    {
        $other = LineEvent::factory()->create(['line_id' => Line::factory()->create()->id, 'type' => 'opening']);

        $this->actingAs($this->member())->from('/contribute/media')
            ->post('/contribute/media', $this->upload(['target_type' => 'event', 'event_id' => $other->id]))
            ->assertSessionHasErrors('event_id');
        $this->assertSame([], Storage::disk(MediaContributions::PRIVATE_DISK)->allFiles());
    }

    public function test_an_image_can_be_attached_to_a_station(): void
    {
        $contribution = $this->submit($this->member(), $this->upload(['target_type' => 'station', 'station_slug' => $this->origin->slug]));

        $this->assertSame([Station::class, $this->origin->id], [$contribution->target_type, (int) $contribution->target_id]);
        $this->assertSame($this->origin->name, $contribution->payload['station_name']);
        $this->publish($contribution);
        $this->assertSame(1, Media::query()->where('mediable_type', Station::class)->where('mediable_id', $this->origin->id)->count());
    }

    public function test_a_missing_private_file_fails_cleanly(): void
    {
        $contribution = $this->submit($this->member(), $this->upload());
        Storage::disk(MediaContributions::PRIVATE_DISK)->delete($this->privatePath($contribution));
        $this->actingAs($this->member('validator'))->post("/validate/{$contribution->id}/approve");

        $this->actingAs($this->member('validator'))->post("/validate/{$contribution->id}/approve")
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'réserve privée'));

        $this->assertSame('approved', $contribution->fresh()->status->value);
        $this->assertSame(0, Media::query()->where('provider', Media::PROVIDER_UPLOAD)->count());
    }

    // ─── Wikimedia Commons ──────────────────────────────────────────────────

    public function test_a_commons_file_is_checked_then_published_with_its_provenance(): void
    {
        $this->fakeCommons();

        $this->actingAs($this->member())
            ->getJson('/contribute/media/commons?url='.urlencode(self::COMMONS_URL))
            ->assertOk()
            ->assertJsonPath('file.page_id', 4242)
            ->assertJsonPath('file.author', 'Jean Dupont')
            ->assertJsonPath('file.license', 'CC BY-SA 4.0');

        $contribution = $this->submit($this->member(), $this->commons(['notes' => 'Vue de 1962.']));
        $this->assertSame(4242, $contribution->payload['commons']['page_id']);
        $this->assertArrayNotHasKey('file', $contribution->payload);
        Http::assertSent(fn ($request) => str_contains(urldecode((string) $request->url()), 'titles=File:Gare de Nontron.jpg')
            && $request->hasHeader('User-Agent', 'VFF-Bot/1.0 (+https://vff-project.org)'));

        $this->publish($contribution);

        $media = Media::query()->findOrFail($contribution->fresh()->payload['applied']['media_id']);
        $this->assertSame([Media::PROVIDER_COMMONS, '4242'], [$media->provider, $media->external_id]);
        $this->assertSame('contribution:'.$contribution->id, $media->provider_meta['source']);
        $this->assertSame(['Jean Dupont', 'CC BY-SA 4.0', 1962], [$media->author, $media->license, $media->depicted_year]);
        $this->assertSame(self::COMMONS_URL, $media->source_url);
        $this->assertNull($media->getAttribute('file_path'), 'l’image reste chez Wikimedia');
        $this->assertSame([], Storage::disk(MediaContributions::PUBLIC_DISK)->allFiles());
    }

    /** @return array<string, array{0: string, 1: string, 2: bool, 3: string}> */
    public static function refusedCommonsFiles(): array
    {
        return [
            'non commerciale' => ['CC BY-NC-SA 4.0', 'image/jpeg', false, 'commons_license'],
            'non libre' => ['Fair use', 'image/jpeg', true, 'commons_license'],
            'vidéo' => ['CC BY-SA 4.0', 'video/webm', false, 'commons_format'],
        ];
    }

    #[DataProvider('refusedCommonsFiles')]
    public function test_a_commons_file_without_a_compatible_licence_or_format_is_refused(string $license, string $mime, bool $nonFree, string $error): void
    {
        $this->fakeCommons($license, $mime, $nonFree);

        $this->actingAs($this->member())->from('/contribute/media')
            ->post('/contribute/media', $this->commons())
            ->assertSessionHasErrors(['commons_url' => __('proposals.media.errors.'.$error)]);
        $this->assertSame(0, Contribution::count());
    }

    public function test_a_missing_or_unreachable_commons_file_is_reported(): void
    {
        Http::fake(['commons.wikimedia.org/*' => Http::sequence()
            ->push(['batchcomplete' => true, 'query' => ['pages' => [['ns' => 6, 'title' => 'File:Gare de Nontron.jpg', 'missing' => true]]]])
            ->push('Service Unavailable', 503)]);
        $member = $this->member();

        $this->actingAs($member)->from('/contribute/media')->post('/contribute/media', $this->commons())
            ->assertSessionHasErrors(['commons_url' => __('proposals.media.errors.commons_missing')]);
        $this->actingAs($member)->from('/contribute/media')->post('/contribute/media', $this->commons())
            ->assertSessionHasErrors(['commons_url' => __('proposals.media.errors.commons_unreachable')]);
        $this->actingAs($member)->from('/contribute/media')->post('/contribute/media', $this->commons(['commons_url' => 'https://exemple.org/photo.jpg']))
            ->assertSessionHasErrors(['commons_url' => __('proposals.media.errors.commons_url')]);
    }

    public function test_a_commons_file_already_attached_to_the_page_is_refused(): void
    {
        $this->fakeCommons();
        Media::factory()->create([
            'mediable_type' => Line::class, 'mediable_id' => $this->line->id,
            'provider' => Media::PROVIDER_COMMONS, 'external_id' => '4242', 'file_path' => null,
        ]);

        $this->actingAs($this->member())->from('/contribute/media')->post('/contribute/media', $this->commons())
            ->assertSessionHasErrors(['commons_url' => __('proposals.media.errors.commons_attached')]);
        // La même image pour une autre fiche reste possible.
        $this->submit($this->member(), $this->commons(['target_type' => 'station', 'station_slug' => $this->origin->slug]));
    }
}
