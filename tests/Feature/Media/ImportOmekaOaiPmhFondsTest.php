<?php

namespace Tests\Feature\Media;

use App\Models\Media;
use App\Models\Station;
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
 * Fonds de l'agent fonds-images-seuil : Bibliothèque numérique du Limousin (API Omeka S, licence par notice) et L'Empreinte de
 * Valence (OAI-PMH, domaine public par notice). Lecture polie (une requête toutes les 5 s pour le premier, 2 s pour le second,
 * jamais une image), licence lue notice par notice, crédit exact. Extraits réels dans tests/Fixtures/OpenData.
 */
class ImportOmekaOaiPmhFondsTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Sleep::fake();
    }

    private function stationAt(string $name, string $city): Station
    {
        return Station::factory()->create(['name' => $name, 'city' => $city, 'type' => 'gare', 'geom' => Point::makeGeodetic(44.9, 4.9)]);
    }

    public function test_the_omeka_api_is_read_one_search_at_a_time_and_no_image_is_requested(): void
    {
        $items = (string) file_get_contents($this->fixturePath('OpenData/bnl-limousin-items-extrait.json'));
        Http::fake(['bnl-bfm.limoges.fr/api/items*' => Http::response($items, 200, ['Content-Type' => 'application/ld+json'])]);
        $dataset = (require base_path('database/data/open_data_media.php'))['bnl-limousin'];
        $dataset['searches'] = ['chemin de fer', 'gare'];

        $fetcher = new OpenDataFetcher;
        // Quatre items par réponse (moins que la page de 50) : une seule page par mot ; les mêmes items ne comptent qu'une fois.
        $this->assertSame(4, $fetcher->refresh('bnl-limousin', $dataset));

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'fulltext_search=chemin%20de%20fer') && $r->hasHeader('User-Agent', 'VFF-Bot/1.0 (+https://vff-project.org)') && $r->hasHeader('Accept', 'application/ld+json'));
        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), '/files/'));
        Sleep::assertSlept(fn ($d): bool => $d->totalMilliseconds >= 4000, 1);
    }

    public function test_only_open_licence_images_are_kept_and_the_reserved_fonds_boudeau_is_refused(): void
    {
        $items = (string) file_get_contents($this->fixturePath('OpenData/bnl-limousin-items-extrait.json'));
        Http::fake(['bnl-bfm.limoges.fr/api/items*' => Http::response($items)]);
        $dataset = (require base_path('database/data/open_data_media.php'))['bnl-limousin'];
        $dataset['searches'] = ['train'];
        (new OpenDataFetcher)->refresh('bnl-limousin', $dataset);

        $this->artisan('vff:import-open-data-media', ['source' => ['bnl-limousin'], '--offline' => true, '--dry-run' => true])
            ->expectsOutputToContain('2 vue(s) ferroviaire(s) retenue(s)')->expectsOutputToContain('"licence_inconnue":2')->assertSuccessful();
    }

    public function test_the_oai_pmh_set_is_read_page_after_page_with_its_resumption_token(): void
    {
        Http::fake([
            'www.lempreinte.valenceromansagglo.fr/oai/oai2.php*' => Http::sequence()
                ->push((string) file_get_contents($this->fixturePath('OpenData/lempreinte-oai-extrait.xml')))
                ->push((string) file_get_contents($this->fixturePath('OpenData/lempreinte-oai-extrait-fin.xml'))),
        ]);
        $dataset = (require base_path('database/data/open_data_media.php'))['lempreinte'];

        $this->assertSame(5, (new OpenDataFetcher)->refresh('lempreinte', $dataset));

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'set=theme%3Agares-et-chemins-de-fer') && str_contains($r->url(), 'verb=ListRecords'));
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'resumptionToken=1795466800988'));
        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), '/media/docs/'));
    }

    public function test_a_valence_station_photo_is_written_with_the_exact_credit_and_the_footbridge_and_avenue_are_not_attached(): void
    {
        File::ensureDirectoryExists(OpenDataFetcher::cacheDir());
        Http::fake([
            'www.lempreinte.valenceromansagglo.fr/oai/oai2.php*' => Http::sequence()
                ->push((string) file_get_contents($this->fixturePath('OpenData/lempreinte-oai-extrait.xml')))
                ->push((string) file_get_contents($this->fixturePath('OpenData/lempreinte-oai-extrait-fin.xml'))),
        ]);
        $valence = $this->stationAt('Valence', 'Valence');

        $this->artisan('vff:import-open-data-media', ['source' => ['lempreinte'], '--refresh' => true])->expectsOutputToContain('0 erreur(s)')->assertSuccessful();

        $ids = Media::query()->where('provider', 'lempreinte')->where('mediable_id', $valence->id)->pluck('external_id')->all();
        sort($ids);
        // Les deux « La Gare » (1902 et 1906) sont gardées ; l'avenue de la Gare (abords), le boulevard Bancel et la passerelle de
        // Jappe-Renard (le mot « gare » n'est que dans les sujets) ne sont jamais rattachés.
        $this->assertSame(['B263626101_CP1547', 'B263626101_CP1554'], $ids);

        $m = Media::query()->where('provider', 'lempreinte')->where('external_id', 'B263626101_CP1547')->firstOrFail();
        $this->assertSame('public_domain', $m->rights_status);
        $this->assertTrue($m->commercial_ok);
        $this->assertSame('Médiathèque Publique et Universitaire de Valence, L\'Empreinte, B263626101_CP1547, 1906, Domaine public', $m->attribution);
        $this->assertSame('https://www.lempreinte.valenceromansagglo.fr/valence-la-gare,B263626101_CP1547.htm', $m->source_url);
        $this->assertSame('https://www.lempreinte.valenceromansagglo.fr/media/docs/1000/B263626101_CP1547/B263626101_CP1547_R.jpg', $m->display_url);
        $this->assertSame('https://www.lempreinte.valenceromansagglo.fr/media/docs/340/B263626101_CP1547/B263626101_CP1547_R.jpg', $m->thumb_url);
    }
}
