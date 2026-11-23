<?php

namespace Tests\Feature\Media;

use App\Models\Media;
use App\Models\Station;
use App\Services\Media\OpenData\DublinCoreReader;
use App\Services\Media\OpenData\OpenDataFetcher;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * Fonds de l'agent medias-4 : Rosalis (Toulouse, domaine public) et Pireneas (Pau, réutilisation non commerciale). Lecture polie
 * (une requête toutes les 5 s, recherche puis une notice Dublin Core par ark, jamais une image), licence lue notice par notice,
 * écriture avec crédit exact. Les lignes viennent d'extraits réels de /services/OAIRecord (tests/Fixtures/OpenData), sans image.
 */
class ImportGallicaDublinCoreFondsTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Sleep::fake();
    }

    private function seedCache(string $key): void
    {
        File::ensureDirectoryExists(OpenDataFetcher::cacheDir());
        File::copy($this->fixturePath('OpenData/'.$key.'-extrait.jsonl'), OpenDataFetcher::cachePath($key));
    }

    private function stationAt(string $name, string $city): Station
    {
        return Station::factory()->create(['name' => $name, 'city' => $city, 'type' => 'gare', 'geom' => Point::makeGeodetic(43.0, 1.0)]);
    }

    public function test_rosalis_is_read_by_search_then_one_dublin_core_record_per_ark_every_five_seconds_and_never_an_image(): void
    {
        $page = '<html><title>2 résultats</title><a href="/ark:/12148/btv1b105741306">a</a> <a href="/ark:/12148/cb449232902">notice</a> <a href="/ark:/12148/btv1b10579001r">b</a></html>';
        Http::fake([
            'rosalis.bibliotheque.toulouse.fr/services/engine/search/sru*' => Http::response($page),
            'rosalis.bibliotheque.toulouse.fr/services/OAIRecord*' => Http::response((string) file_get_contents($this->fixturePath('OpenData/rosalis-oairecord-foix.xml'))),
        ]);
        $dataset = (require base_path('database/data/open_data_media.php'))['rosalis'];
        $dataset['searches'] = ['(gallica all "gare") and (dc.type all "image")'];

        $fetcher = new OpenDataFetcher;
        $this->assertSame(2, $fetcher->refresh('rosalis', $dataset));

        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'search/sru') && $r->hasHeader('User-Agent', 'VFF-Bot/1.0 (+https://vff-project.org)'));
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/services/OAIRecord?ark=btv1b10579001r') && $r->hasHeader('Accept', 'application/xml'));
        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), '/iiif/') || str_contains($r->url(), 'cb449232902') || str_contains($r->url(), '.thumbnail'));
        // 1 recherche + 2 notices : une pause de 5 s avant chaque requête sauf la première.
        Http::assertSentCount(3);
        Sleep::assertSleptTimes(2);
        Sleep::assertSlept(fn ($d): bool => $d->totalMilliseconds >= 4000, 2);

        // Relecture : les arks déjà lus ne sont pas redemandés.
        $this->assertSame(2, (new OpenDataFetcher)->refresh('rosalis', $dataset));
        Http::assertSentCount(4);
        $this->assertCount(2, iterator_to_array(DublinCoreReader::cachedRecords('rosalis')));
    }

    public function test_a_record_in_error_is_kept_and_asked_again_only_with_retry_errors(): void
    {
        Http::fake([
            'rosalis.bibliotheque.toulouse.fr/services/engine/search/sru*' => Http::response('<html>1 résultats <a href="/ark:/12148/btv1b1">a</a></html>'),
            'rosalis.bibliotheque.toulouse.fr/services/OAIRecord*' => Http::sequence()->push('', 500)->push((string) file_get_contents($this->fixturePath('OpenData/rosalis-oairecord-foix.xml'))),
        ]);
        $dataset = (require base_path('database/data/open_data_media.php'))['rosalis'];
        $dataset['searches'] = ['(gallica all "gare") and (dc.type all "image")'];

        $first = (new OpenDataFetcher)->refresh('rosalis', $dataset);
        $second = (new OpenDataFetcher)->refresh('rosalis', $dataset);
        $this->assertSame([0, 0], [$first, $second]);
        $retry = new OpenDataFetcher;
        $retry->retryErrors = true;
        $this->assertSame(1, $retry->refresh('rosalis', $dataset));
    }

    public function test_rosalis_images_are_attached_by_commune_with_the_public_domain_and_the_exact_credit_and_the_run_is_idempotent(): void
    {
        $this->seedCache('rosalis');
        $foix = $this->stationAt('Foix', 'Foix');
        $this->stationAt('Perpignan', 'Perpignan');
        $this->stationAt('Lourdes', 'Lourdes');

        $this->artisan('vff:import-open-data-media', ['source' => ['rosalis'], '--offline' => true])->expectsOutputToContain('0 erreur(s)')->assertSuccessful();
        $count = Media::query()->where('provider', 'rosalis')->count();
        $this->artisan('vff:import-open-data-media', ['source' => ['rosalis'], '--offline' => true])->assertSuccessful();
        $this->assertSame($count, Media::query()->where('provider', 'rosalis')->count());

        $m = Media::query()->where('provider', 'rosalis')->where('mediable_id', $foix->id)->where('external_id', 'btv1b105741306')->firstOrFail();
        $this->assertSame('A la gare, Foix, septembre 1882', $m->title);
        $this->assertSame('Domaine public', $m->license);
        $this->assertSame('public_domain', $m->rights_status);
        $this->assertTrue($m->commercial_ok);
        $this->assertTrue($m->derivatives_ok);
        $this->assertSame('Eugène Trutat', $m->author);
        $this->assertSame('Eugène Trutat, 1882, Association des Toulousains de Toulouse, Musée du Vieux-Toulouse, Mairie de Toulouse, Rosalis, btv1b105741306, Domaine public', $m->attribution);
        $this->assertSame('https://rosalis.bibliotheque.toulouse.fr/ark:/12148/btv1b105741306', $m->source_url);
        $this->assertSame('https://rosalis.bibliotheque.toulouse.fr/iiif/ark:/12148/btv1b105741306/f1/full/1600,/0/native.jpg', $m->display_url);
        $this->assertSame(1882, $m->depicted_year);
        $this->assertSame('nom', $m->provider_meta['method']);
    }

    public function test_foreign_bnf_and_viewpoint_records_are_refused_or_left_unattached(): void
    {
        $this->seedCache('rosalis');
        $this->stationAt('Lourdes', 'Lourdes');
        $this->stationAt('Foix', 'Foix');
        $review = OpenDataFetcher::cacheDir().'/review.jsonl';

        $this->artisan('vff:import-open-data-media', ['source' => ['rosalis'], '--offline' => true, '--dry-run' => true, '--review' => $review])
            ->expectsOutputToContain('"hors_fonds":')->assertSuccessful();

        $this->artisan('vff:import-open-data-media', ['source' => ['rosalis'], '--offline' => true])->assertSuccessful();
        // Monaco (hors France) et affiches de la BnF (autre régime) : jamais écrits ; « Vue prise de la Gare » : la gare est le point de vue.
        foreach (['btv1b10579168r', 'btv1b84578827', 'btv1b9007921r', 'btv1b10572923g'] as $ark) {
            $this->assertSame(0, Media::query()->where('provider', 'rosalis')->where('external_id', $ark)->count(), $ark);
        }
        $this->assertStringContainsString('point_de_vue', (string) file_get_contents($review));
        $this->assertSame(1, Media::query()->where('provider', 'rosalis')->where('external_id', 'btv1b10577325t')->count(), 'Le château et la gare, Lourdes');
    }

    public function test_a_record_whose_rights_are_not_public_domain_is_refused(): void
    {
        File::ensureDirectoryExists(OpenDataFetcher::cacheDir());
        $line = json_decode((string) file($this->fixturePath('OpenData/rosalis-extrait.jsonl'))[0], true);
        $line['rights'] = "Conditions spécifiques d'utilisation | specific conditions of use";
        File::put(OpenDataFetcher::cachePath('rosalis'), json_encode($line, JSON_UNESCAPED_UNICODE)."\n");
        $this->stationAt('Foix', 'Foix');

        $this->artisan('vff:import-open-data-media', ['source' => ['rosalis'], '--offline' => true, '--dry-run' => true])->expectsOutputToContain('"licence_inconnue":1')->assertSuccessful();
    }

    public function test_pireneas_images_are_written_non_commercial_masked_by_the_setting_with_the_exact_credit(): void
    {
        $this->seedCache('pireneas');
        $pau = $this->stationAt('Pau', 'Pau');
        $this->stationAt('Bielle', 'Bielle');

        $this->artisan('vff:import-open-data-media', ['source' => ['pireneas'], '--offline' => true])->expectsOutputToContain('0 erreur(s)')->assertSuccessful();
        $count = Media::query()->where('provider', 'pireneas')->count();
        $this->artisan('vff:import-open-data-media', ['source' => ['pireneas'], '--offline' => true])->assertSuccessful();
        $this->assertSame($count, Media::query()->where('provider', 'pireneas')->count());

        $m = Media::query()->where('provider', 'pireneas')->where('mediable_id', $pau->id)->where('external_id', 'btv1b105615953')->firstOrFail();
        $this->assertSame('Pau : La Gare', $m->title);
        $this->assertSame('Réutilisation non commerciale libre (Pireneas)', $m->license);
        $this->assertSame('open_license', $m->rights_status);
        $this->assertFalse($m->commercial_ok);
        $this->assertFalse($m->derivatives_ok);
        $this->assertSame('Pireneas, Bibliothèque patrimoniale de Pau, 4-023-4, 1907, Réutilisation non commerciale libre (Pireneas)', $m->attribution);
        $this->assertSame('https://www.pireneas.fr/ark:/12148/btv1b105615953', $m->source_url);
        $this->assertSame('https://www.pireneas.fr/iiif/ark:/12148/btv1b105615953/f1/full/400,/0/native.jpg', $m->thumb_url);
        $this->assertTrue($m->isRightsUncertain());

        config(['vff.media.show_non_commercial' => false]);
        $this->assertFalse(Media::query()->displayable()->where('provider', 'pireneas')->exists());
        config(['vff.media.show_non_commercial' => true, 'vff.media.show_uncertain' => false]);
        $this->assertFalse(Media::query()->displayable()->where('provider', 'pireneas')->exists());
    }

    public function test_pireneas_tramways_posters_toulouse_documents_viewpoints_and_gare_rubrics_are_not_attached(): void
    {
        $this->seedCache('pireneas');
        $this->stationAt('Pau', 'Pau');
        $this->stationAt('Lourdes', 'Lourdes');
        $this->stationAt('Bagnères-de-Bigorre', 'Bagnères-de-Bigorre');

        $this->artisan('vff:import-open-data-media', ['source' => ['pireneas'], '--offline' => true])->assertSuccessful();

        $written = Media::query()->where('provider', 'pireneas')->pluck('external_id')->all();
        // Tramway et funiculaire (pas la gare), affiche de compagnie, document de Toulouse (lu dans Rosalis), « Vue prise de la Gare »,
        // « Chaîne des Pyrénées » (rubrique « Gares -- Pau » seulement) : aucun rattachement.
        foreach (['btv1b10562970w', 'btv1b10561572x', 'btv1b10568455g', 'btv1b10577325t', 'btv1b10562864w', 'btv1b10566070v'] as $ark) {
            $this->assertNotContains($ark, $written, $ark);
        }
        $this->assertContains('btv1b105615953', $written);
    }

    public function test_pireneas_street_approaches_and_station_projects_are_not_attached_to_the_station(): void
    {
        $this->seedCache('pireneas');
        $this->stationAt('Pau', 'Pau');

        $this->artisan('vff:import-open-data-media', ['source' => ['pireneas'], '--offline' => true])->assertSuccessful();

        // « La Montée de la Gare » et « Le nouveau boulevard et la Gare » (rues), « Projet d'implantation de la gare » (plan) : refusés.
        $written = Media::query()->where('provider', 'pireneas')->pluck('external_id')->all();
        foreach (['btv1b10562933k', 'btv1b105629593', 'btv1b10584085f'] as $ark) {
            $this->assertNotContains($ark, $written, $ark);
        }
        $this->assertContains('btv1b105615953', $written, 'Pau : La Gare reste rattachée');
    }
}
