<?php

namespace Tests\Feature\Media;

use App\Enums\MediaType;
use App\Models\Media;
use App\Models\Station;
use App\Services\Api\MediaGallery;
use App\Services\Media\Sources\MediaRecord;
use App\Services\Media\Sources\MediaWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Droits par média (migration 2026_12_11_060000) et réglage `vff.media.show_non_commercial` : un média NC
 * disparaît de la galerie, de l'API REST, de GraphQL et des compteurs quand le réglage est faux ; MediaRecord
 * refuse un média aux droits incomplets ; MediaWriter est idempotent et compte les gares gagnées.
 */
class MediaRightsSettingTest extends TestCase
{
    use RefreshDatabase;

    private Station $station;

    protected function setUp(): void
    {
        parent::setUp();
        $this->station = Station::factory()->create(['slug' => 'gare-temoin-medias']);
    }

    private function record(string $id, ?bool $commercial = true, ?bool $derivatives = true, array $overrides = []): MediaRecord
    {
        return new MediaRecord(...array_merge([
            'provider' => 'panoramax',
            'externalId' => $id,
            'mediableType' => Station::class,
            'mediableId' => $this->station->id,
            'rightsStatus' => Media::RIGHTS_OPEN_LICENSE,
            'license' => $commercial === false ? 'CC BY-NC 4.0' : 'Licence Ouverte 2.0',
            'licenseUrl' => 'https://www.etalab.gouv.fr/licence-ouverte-open-licence/',
            'commercialOk' => $commercial,
            'derivativesOk' => $derivatives,
            'shareAlike' => false,
            'attribution' => 'Photo : témoin, Panoramax (IGN), Licence Ouverte 2.0',
            'author' => 'témoin',
            'sourceUrl' => 'https://panoramax.ign.fr/#focus=pic&pic='.$id,
            'thumbUrl' => 'https://example.org/'.$id.'/thumb.jpg',
            'displayUrl' => 'https://example.org/'.$id.'/sd.jpg',
            'type' => MediaType::Photo,
            'capturedAt' => '2024-07-17',
            'datePrecision' => 'day',
            'depictedYear' => 2024,
            'lat' => 48.1,
            'lng' => -1.6,
        ], $overrides));
    }

    private function seedTwo(): void
    {
        app(MediaWriter::class)->write([$this->record('libre'), $this->record('nc', commercial: false, derivatives: false)]);
    }

    public function test_writer_writes_rights_and_is_idempotent(): void
    {
        $writer = app(MediaWriter::class);
        $first = $writer->write([$this->record('libre'), $this->record('nc', commercial: false, derivatives: false)]);
        $this->assertSame(2, $first->inserted);
        $this->assertSame(1, $first->gainedCount(Station::class));

        $again = $writer->write([$this->record('libre'), $this->record('nc', commercial: false, derivatives: false)]);
        $this->assertSame(0, $again->inserted);
        $this->assertSame(2, $again->updated);
        $this->assertSame(0, $again->gainedCount(Station::class));
        $this->assertSame(2, Media::query()->count());

        $nc = Media::query()->where('external_id', 'nc')->firstOrFail();
        $this->assertFalse($nc->commercial_ok);
        $this->assertFalse($nc->derivatives_ok);
        $this->assertFalse($nc->isCroppable());
        $this->assertSame('day', $nc->date_precision);
        $this->assertSame('2024-07-17', $nc->captured_at?->toDateString());
    }

    public function test_dry_run_counts_without_writing(): void
    {
        $report = app(MediaWriter::class)->write([$this->record('libre')], dryRun: true);

        $this->assertSame(1, $report->inserted);
        $this->assertSame(1, $report->gainedCount(Station::class));
        $this->assertSame(0, Media::query()->count());
    }

    public function test_record_with_incomplete_rights_is_refused(): void
    {
        $report = app(MediaWriter::class)->write([
            $this->record('sans-commercial', commercial: null),
            $this->record('sans-nd', derivatives: null),
            $this->record('sans-licence', overrides: ['license' => null]),
            $this->record('sans-credit', overrides: ['attribution' => ' ']),
            $this->record('lien-javascript', overrides: ['sourceUrl' => 'javascript:alert(1)']),
            $this->record('precision', overrides: ['datePrecision' => 'week']),
        ]);

        $this->assertSame(0, $report->inserted);
        $this->assertSame(['commercial_ok' => 1, 'derivatives_ok' => 1, 'license' => 1, 'attribution' => 1, 'source_url' => 1, 'date_precision' => 1], $report->refused);
        $this->assertSame(0, Media::query()->count());
    }

    public function test_non_commercial_media_are_shown_by_default(): void
    {
        $this->seedTwo();

        $this->getJson('/api/v1/stations/gare-temoin-medias/media')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/media?station=gare-temoin-medias')->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame(2, app(MediaGallery::class)->count($this->station));
    }

    public function test_setting_hides_non_commercial_media_everywhere(): void
    {
        $this->seedTwo();
        config(['vff.media.show_non_commercial' => false]);

        $gallery = $this->getJson('/api/v1/stations/gare-temoin-medias/media')->assertOk();
        $gallery->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.title', null)
            ->assertJsonPath('data.0.commercial_ok', true)->assertJsonPath('data.0.derivatives_ok', true)
            ->assertJsonPath('facets.providers.panoramax', 1);
        $this->getJson('/api/v1/media?station=gare-temoin-medias')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/media?station=gare-temoin-medias&commercial_ok=false')->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame(1, app(MediaGallery::class)->count($this->station));
        $this->assertSame(1, Media::query()->displayable()->count());
        $this->assertSame(2, Media::query()->count(), 'rien n\'est effacé');

        $graphql = $this->postJson('/graphql', ['query' => '{ station(slug: "gare-temoin-medias") { media(first: 10) { edges { node { id derivatives_ok commercial_ok } } } } }']);
        $graphql->assertOk()->assertJsonCount(1, 'data.station.media.edges');
        $this->assertSame(1, (int) DB::selectOne('SELECT count(*) AS n FROM media WHERE '.Media::displayableSql('media'))->n);
    }

    public function test_unknown_commercial_right_stays_visible(): void
    {
        DB::table('media')->insert([...$this->record('inconnu')->toRow(), 'commercial_ok' => null, 'created_at' => now(), 'updated_at' => now()]);
        config(['vff.media.show_non_commercial' => false]);

        $this->assertSame(1, Media::query()->displayable()->count());
    }

    /** Un fonds « incertain » du registre des droits (evry-cp) et un fonds sûr (panoramax). */
    private function seedUncertain(): void
    {
        app(MediaWriter::class)->write([
            $this->record('sur'),
            $this->record('incertain', overrides: ['provider' => 'evry-cp', 'license' => 'Licence Ouverte 2.0 (Etalab)']),
        ]);
    }

    public function test_uncertain_funds_are_shown_by_default_and_flagged(): void
    {
        $this->seedUncertain();

        $gallery = $this->getJson('/api/v1/stations/gare-temoin-medias/media')->assertOk();
        $gallery->assertJsonPath('meta.total', 2);
        $flags = [];
        foreach ((array) $gallery->json('data') as $m) {
            $flags[(string) $m['provider']] = $m['rights_uncertain'];
        }
        $this->assertSame(['panoramax' => false, 'evry-cp' => true], $flags);
        $this->assertTrue(Media::query()->where('provider', 'evry-cp')->firstOrFail()->isRightsUncertain());
        $this->assertSame(['evry-cp', 'gadagne-cp', 'pireneas'], array_values(array_intersect(['evry-cp', 'gadagne-cp', 'pireneas'], Media::uncertainProviders())));
    }

    public function test_setting_hides_uncertain_funds_everywhere_and_deletes_nothing(): void
    {
        $this->seedUncertain();
        config(['vff.media.show_uncertain' => false]);

        $this->getJson('/api/v1/stations/gare-temoin-medias/media')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.provider', 'panoramax');
        $this->getJson('/api/v1/media?station=gare-temoin-medias')->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame(1, app(MediaGallery::class)->count($this->station));
        $this->assertSame(1, Media::query()->displayable()->count());
        $this->assertSame(1, Media::query()->redistributable()->count());
        $this->assertSame(2, Media::query()->count(), 'rien n\'est effacé');
        $this->assertSame(1, (int) DB::selectOne('SELECT count(*) AS n FROM media WHERE '.Media::displayableSql('media'))->n);

        $graphql = $this->postJson('/graphql', ['query' => '{ station(slug: "gare-temoin-medias") { media(first: 10) { edges { node { id rights_uncertain } } } } }']);
        $graphql->assertOk()->assertJsonCount(1, 'data.station.media.edges')->assertJsonPath('data.station.media.edges.0.node.rights_uncertain', false);
    }

    public function test_both_settings_combine(): void
    {
        $this->seedUncertain();
        app(MediaWriter::class)->write([$this->record('nc', commercial: false, derivatives: false)]);
        config(['vff.media.show_uncertain' => false, 'vff.media.show_non_commercial' => false]);

        $this->assertSame(1, Media::query()->displayable()->count());
        $this->assertStringStartsWith('media.commercial_ok IS DISTINCT FROM FALSE AND media.provider NOT IN (', Media::displayableSql('media'));
    }
}
