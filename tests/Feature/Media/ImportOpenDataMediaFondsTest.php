<?php

namespace Tests\Feature\Media;

use App\Models\Line;
use App\Models\Media;
use App\Models\Station;
use App\Services\Media\OpenData\IiifManifestReader;
use App\Services\Media\OpenData\OpenDataFetcher;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * Fonds de l'agent medias-3 : cartes postales de Saône-et-Loire, Getty Open Content, École des ponts et chaussées, Numistral.
 * Lecture réseau polie et limitée à ce que leurs robots.txt permettent (plan du site et manifestes IIIF, jamais la recherche de
 * l'École ; jamais media.getty.edu ni une image), reprise d'une lecture interrompue, puis écriture avec droits et crédit exacts.
 * Les lignes viennent d'extraits réels (tests/Fixtures/OpenData), sans image.
 */
class ImportOpenDataMediaFondsTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Sleep::fake();
    }

    private function seedCache(string $key, ?string $fixture = null): void
    {
        File::ensureDirectoryExists(OpenDataFetcher::cacheDir());
        File::copy($this->fixturePath('OpenData/'.($fixture ?? $key).'-extrait.jsonl'), OpenDataFetcher::cachePath($key));
    }

    private function stationAt(string $name, string $city, float $lat, float $lng, string $type = 'gare'): Station
    {
        return Station::factory()->create(['name' => $name, 'city' => $city, 'type' => $type, 'geom' => Point::makeGeodetic($lat, $lng)]);
    }

    private function fixture(string $file): string
    {
        return (string) file_get_contents($this->fixturePath('OpenData/'.$file));
    }

    public function test_the_getty_is_read_by_sparql_and_one_linked_art_object_each_and_never_from_the_image_host(): void
    {
        Http::fake([
            'data.getty.edu/museum/collection/sparql*' => Http::response($this->fixture('getty-sparql-extrait.json')),
            'data.getty.edu/museum/collection/object/*' => Http::response($this->fixture('getty-objet-tarascon.json')),
        ]);
        $dataset = (require base_path('database/data/open_data_media.php'))['getty'];

        $this->assertSame(5, (new OpenDataFetcher)->refresh('getty', $dataset));

        Http::assertSent(fn (Request $r): bool => str_starts_with($r->url(), 'https://data.getty.edu/museum/collection/sparql')
            && $r->hasHeader('Accept', 'application/sparql-results+json')
            && $r->hasHeader('User-Agent', 'VFF-Bot/1.0 (+https://vff-project.org)')
            && str_contains($r->url(), 'query='));
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/museum/collection/object/') && $r->hasHeader('Accept', 'application/ld+json'));
        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), 'media.getty.edu') || str_contains($r->url(), '/iiif/'));
        // 1 requête SPARQL + 5 objets, une pause de 2 s avant chacune sauf la première.
        Http::assertSentCount(6);
        Sleep::assertSleptTimes(5);
        $rows = iterator_to_array(OpenDataFetcher::cached('getty'));
        $this->assertSame('France', $rows[0]['country']);
        $this->assertSame('84.XO.401.34', $rows[0]['accession']);
    }

    public function test_the_ecole_des_ponts_is_read_by_sitemap_and_iiif_manifests_only_and_the_read_resumes(): void
    {
        Http::fake([
            'heritage.ecoledesponts.fr/sitemap.txt' => Http::sequence()
                ->push("https://heritage.ecoledesponts.fr/enpc/content/accueil-fr\nhttps://heritage.ecoledesponts.fr/ark:/12148/btv1b104831371\nhttps://heritage.ecoledesponts.fr/ark:/12148/bpt6k1\nhttps://heritage.ecoledesponts.fr/ark:/12148/btv1b10483050b\nhttps://heritage.ecoledesponts.fr/ark:/12148/btv1b12002069\n")
                ->push("https://heritage.ecoledesponts.fr/ark:/12148/btv1b104831371\nhttps://heritage.ecoledesponts.fr/ark:/12148/btv1b10483050b\nhttps://heritage.ecoledesponts.fr/ark:/12148/btv1b12002069\nhttps://heritage.ecoledesponts.fr/ark:/12148/btv1b104822266\n"),
            'heritage.ecoledesponts.fr/iiif/ark:/12148/btv1b10483050b/*' => Http::response('', 500),
            'heritage.ecoledesponts.fr/iiif/ark:/12148/*' => Http::response($this->fixture('enpc-manifeste-murat.json')),
        ]);
        $dataset = (require base_path('database/data/open_data_media.php'))['enpc-ph'];

        $this->assertSame(2, (new OpenDataFetcher)->refresh('enpc-ph', $dataset));

        // Le plan du site, puis un manifeste par ark d'image : jamais la recherche, un chemin à paramètres ni une image.
        Http::assertSentCount(4);
        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), '/services/engine/search') || str_contains($r->url(), '?') || str_contains($r->url(), '/full/') || str_contains($r->url(), 'bpt6k1'));
        $lines = iterator_to_array(IiifManifestReader::cachedManifests('enpc-ph'));
        $this->assertCount(3, $lines);
        $this->assertStringContainsString('500', $lines[1]['error']);
        $this->assertFileExists(OpenDataFetcher::cacheDir().'/ca-bundle-enpc-ph.pem');

        // Relecture : les arks déjà lus (y compris celui en erreur) ne sont pas redemandés.
        $this->assertSame(3, (new OpenDataFetcher)->refresh('enpc-ph', $dataset));
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'btv1b104822266/manifest.json'));
        $this->assertCount(4, iterator_to_array(IiifManifestReader::cachedManifests('enpc-ph')));
    }

    public function test_a_failing_manifest_does_not_stop_the_read_and_a_timeout_is_kept_as_an_error(): void
    {
        Http::fake([
            'heritage.ecoledesponts.fr/sitemap.txt' => Http::response("https://heritage.ecoledesponts.fr/ark:/12148/btv1b1\nhttps://heritage.ecoledesponts.fr/ark:/12148/btv1b2\n"),
            'heritage.ecoledesponts.fr/iiif/ark:/12148/btv1b1/*' => fn () => throw new ConnectionException('cURL error 28: Operation timed out'),
            'heritage.ecoledesponts.fr/iiif/*' => Http::response($this->fixture('enpc-manifeste-murat.json')),
        ]);
        $dataset = (require base_path('database/data/open_data_media.php'))['enpc-ph'];

        $this->assertSame(1, (new OpenDataFetcher)->refresh('enpc-ph', $dataset));
        $this->assertStringContainsString('timed out', iterator_to_array(IiifManifestReader::cachedManifests('enpc-ph'))[0]['error']);
    }

    public function test_an_ark_read_twice_or_after_an_error_counts_once(): void
    {
        File::ensureDirectoryExists(OpenDataFetcher::cacheDir());
        file_put_contents(IiifManifestReader::manifestCache('enpc-ph'), implode("\n", [
            json_encode(['ark' => 'btv1b1', 'error' => 'Lecture refusée (500)']),
            json_encode(['ark' => 'btv1b1', 'title' => 'Gare de Murat']),
            json_encode(['ark' => 'btv1b1', 'title' => 'Doublon']),
            json_encode(['ark' => 'btv1b2', 'title' => 'Viaduc']),
        ])."\n");

        $this->assertSame(['Gare de Murat', 'Viaduc'], array_column(IiifManifestReader::rowsFromCache('enpc-ph'), 'title'));
    }

    public function test_retry_errors_asks_again_only_the_manifests_that_failed(): void
    {
        File::ensureDirectoryExists(OpenDataFetcher::cacheDir());
        file_put_contents(IiifManifestReader::manifestCache('enpc-ph'), json_encode(['ark' => 'btv1b1', 'error' => 'Lecture refusée (500)'])."\n".json_encode(['ark' => 'btv1b2', 'title' => 'Gare de Nice', 'repository' => 'x'])."\n");
        Http::fake([
            'heritage.ecoledesponts.fr/sitemap.txt' => Http::response("https://heritage.ecoledesponts.fr/ark:/12148/btv1b1\nhttps://heritage.ecoledesponts.fr/ark:/12148/btv1b2\n"),
            'heritage.ecoledesponts.fr/iiif/*' => Http::response($this->fixture('enpc-manifeste-murat.json')),
        ]);
        $dataset = (require base_path('database/data/open_data_media.php'))['enpc-ph'];

        $fetcher = new OpenDataFetcher;
        $fetcher->retryErrors = true;
        $this->assertSame(2, $fetcher->refresh('enpc-ph', $dataset));

        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'btv1b1/manifest.json'));
        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), 'btv1b2/manifest.json'));
    }

    public function test_numistral_is_searched_without_an_html_accept_header_and_pages_of_fifty(): void
    {
        $page = fn (int $total, string $arks): string => "<html><title>{$total} résultats</title>{$arks}</html>";
        Http::fake([
            'numistral.bnf.fr/services/engine/search/sru*' => Http::sequence()
                ->push($page(60, '<a href="/ark:/12148/btv1b102164183">x</a> <a href="/ark:/12148/cb41911232s">notice</a>'))
                ->push($page(60, '<a href="/ark:/12148/btv1b10207138t">y</a>')),
            'numistral.bnf.fr/iiif/ark:/12148/*' => Http::response($this->fixture('numistral-manifeste-schlestadt.json')),
        ]);
        $dataset = (require base_path('database/data/open_data_media.php'))['numistral'];
        $dataset['searches'] = ['(gallica all "chemin de fer") and (dc.type all "image")'];

        $this->assertSame(2, (new OpenDataFetcher)->refresh('numistral', $dataset));

        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'search/sru') && ! $r->hasHeader('Accept', 'text/html')
            && str_contains($r->url(), 'operation=searchRetrieve') && str_contains($r->url(), 'startRecord=50'));
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/iiif/ark:/12148/btv1b10207138t/manifest.json'));
        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), 'cb41911232s'));
    }

    public function test_the_ecole_des_ponts_images_are_attached_by_title_with_licence_and_credit_and_the_run_is_idempotent(): void
    {
        $this->seedCache('enpc-ph');
        $nice = $this->stationAt('Nice-Ville', 'Nice', 43.7041, 7.2619);
        $this->stationAt('Châteaudun', 'Châteaudun', 48.0706, 1.3367);
        $pau = Line::factory()->create(['name' => 'Ligne de Pau à Oloron-Sainte-Marie']);
        $before = DB::table('media')->count();

        $this->artisan('vff:import-open-data-media', ['source' => ['enpc-ph'], '--offline' => true, '--dry-run' => true])->expectsOutputToContain('Mesure à blanc')->assertSuccessful();
        $this->assertSame($before, DB::table('media')->count());

        $this->artisan('vff:import-open-data-media', ['source' => ['enpc-ph'], '--offline' => true])->expectsOutputToContain('0 erreur(s)')->assertSuccessful();
        $count = Media::query()->where('provider', 'enpc-ph')->count();
        $this->assertGreaterThanOrEqual(3, $count);
        $this->artisan('vff:import-open-data-media', ['source' => ['enpc-ph'], '--offline' => true])->assertSuccessful();
        $this->assertSame($count, Media::query()->where('provider', 'enpc-ph')->count());

        $m = Media::query()->where('provider', 'enpc-ph')->where('mediable_type', Station::class)->where('mediable_id', $nice->id)->firstOrFail();
        $this->assertSame('btv1b10483104f', $m->external_id);
        $this->assertSame('Gare de Nice', $m->title);
        $this->assertSame('Licence Ouverte (Etalab)', $m->license);
        $this->assertSame('open_license', $m->rights_status);
        $this->assertTrue($m->commercial_ok);
        $this->assertStringContainsString('École nationale des ponts et chaussées', (string) $m->attribution);
        $this->assertStringContainsString('PH 125(1) A.12', (string) $m->attribution);
        $this->assertSame('https://heritage.ecoledesponts.fr/ark:/12148/btv1b10483104f', $m->source_url);
        $this->assertSame('https://heritage.ecoledesponts.fr/iiif/ark:/12148/btv1b10483104f/f1/full/1600,/0/native.jpg', $m->display_url);
        $this->assertSame('https://heritage.ecoledesponts.fr/iiif/ark:/12148/btv1b10483104f/f1/full/400,/0/native.jpg', $m->thumb_url);
        $this->assertSame(1873, $m->depicted_year);
        $this->assertSame('nom-titre-ville', $m->provider_meta['method']);
        $this->assertSame('basse', $m->provider_meta['confidence']);

        $line = Media::query()->where('provider', 'enpc-ph')->where('mediable_type', Line::class)->where('mediable_id', $pau->id)->first();
        $this->assertNotNull($line, 'Souterrain de Bélair, ligne de Pau à Oloron');
        $this->assertSame('ligne-titre-debut', $line->provider_meta['method']);
        // Une carte du dépôt de la BnF (autre régime de réutilisation) et un manuscrit ne sont jamais écrits.
        $this->assertSame(0, Media::query()->where('provider', 'enpc-ph')->where('external_id', 'btv1b53085108c')->count());
    }

    public function test_only_documents_of_the_ecole_are_read_not_the_ones_of_the_bnf(): void
    {
        $this->seedCache('enpc-ph');
        $review = OpenDataFetcher::cacheDir().'/review.jsonl';

        $this->artisan('vff:import-open-data-media', ['source' => ['enpc-ph'], '--offline' => true, '--dry-run' => true, '--review' => $review])
            ->expectsOutputToContain('"hors_fonds":')
            ->assertSuccessful();
    }

    public function test_getty_images_are_written_as_cc0_once_per_plate_and_foreign_or_rightless_objects_are_refused(): void
    {
        $this->seedCache('getty');
        $perrache = $this->stationAt('Lyon-Perrache', 'Lyon 2e Arrondissement', 45.7487, 4.8264);
        $tarare = $this->stationAt('Tarare', 'Tarare', 45.89, 4.43);
        $this->stationAt('Vichy', 'Vichy', 46.1219, 3.4256);
        $this->stationAt('Montargis', 'Montargis', 48.0, 2.74);

        $this->artisan('vff:import-open-data-media', ['source' => ['getty'], '--offline' => true])->expectsOutputToContain('0 erreur(s)')->assertSuccessful();

        $m = Media::query()->where('provider', 'getty')->where('mediable_type', Station::class)->where('mediable_id', $perrache->id)->where('external_id', '84.XO.401.3')->first();
        $this->assertNotNull($m);
        $this->assertSame('CC0 1.0', $m->license);
        $this->assertSame('public_domain', $m->rights_status);
        $this->assertStringContainsString('The J. Paul Getty Museum, Los Angeles (Open Content)', (string) $m->attribution);
        $this->assertStringContainsString('Édouard Baldus', (string) $m->attribution);
        $this->assertStringStartsWith('https://media.getty.edu/iiif/image/', (string) $m->display_url);
        $this->assertStringStartsWith('https://www.getty.edu/art/collection/object/', (string) $m->source_url);
        $this->assertSame(1861, $m->depicted_year);
        // La même planche reliée dans deux albums (84.XO.401.3 et 84.XO.734.1.3) n'est écrite qu'une fois pour la gare de Lyon.
        $this->assertSame(0, Media::query()->where('provider', 'getty')->where('external_id', '84.XO.734.1.3')->count());
        $this->assertSame(1, Media::query()->where('provider', 'getty')->where('external_id', '84.XM.407.38')->where('mediable_id', $tarare->id)->count());
        // Objet sans lieu ni album français (« Chemin de Fer du Righi ») : refusé.
        $this->assertSame(0, Media::query()->where('provider', 'getty')->where('external_id', '84.XC.979.2874')->count());
    }

    public function test_the_getty_refuses_an_object_without_the_cc0_declaration(): void
    {
        File::ensureDirectoryExists(OpenDataFetcher::cacheDir());
        $row = json_decode((string) file($this->fixturePath('OpenData/getty-extrait.jsonl'), FILE_IGNORE_NEW_LINES)[2], true);
        $row['rights_uri'] = null;
        $row['rights_display'] = null;
        file_put_contents(OpenDataFetcher::cachePath('getty'), json_encode($row, JSON_UNESCAPED_UNICODE)."\n");
        $this->stationAt('Lyon-Perrache', 'Lyon 2e Arrondissement', 45.7487, 4.8264);

        $this->artisan('vff:import-open-data-media', ['source' => ['getty'], '--offline' => true])->expectsOutputToContain('"hors_fonds":1')->assertSuccessful();
        $this->assertSame(0, Media::query()->where('provider', 'getty')->count());
    }

    public function test_rebuild_rewrites_the_rows_from_the_manifests_already_read_without_any_request(): void
    {
        File::ensureDirectoryExists(OpenDataFetcher::cacheDir());
        File::copy($this->fixturePath('OpenData/enpc-ph-extrait.jsonl'), IiifManifestReader::manifestCache('enpc-ph'));

        $this->artisan('vff:import-open-data-media', ['source' => ['enpc-ph'], '--rebuild' => true, '--dry-run' => true])
            ->expectsOutputToContain('ligne(s) reconstruites')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertCount(10, iterator_to_array(OpenDataFetcher::cached('enpc-ph')));
    }
}
