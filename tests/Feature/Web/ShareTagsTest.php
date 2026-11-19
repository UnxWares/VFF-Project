<?php

namespace Tests\Feature\Web;

use App\Enums\MediaType;
use App\Models\Accident;
use App\Models\Line;
use App\Models\Material;
use App\Models\Media;
use App\Models\Station;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Aperçus de partage : Facebook, Mastodon, Discord ne lisent pas le JavaScript, donc le HTML initial
 * de l'accueil, des fiches ligne, gare, matériel et accident porte déjà les balises og: / twitter:,
 * l'adresse canonique et le JSON-LD (resources/views/partials/share-tags.blade.php), repérés par
 * data-vff-seo pour que le navigateur les reprenne sans les dupliquer.
 */
class ShareTagsTest extends TestCase
{
    use RefreshDatabase;

    /** @param  TestResponse<Response>  $response */
    private function document(TestResponse $response): HTMLDocument
    {
        return HTMLDocument::createFromString((string) $response->getContent(), LIBXML_NOERROR);
    }

    private function meta(HTMLDocument $doc, string $key): ?string
    {
        return $doc->querySelector("head meta[property=\"{$key}\"], head meta[name=\"{$key}\"]")?->getAttribute('content');
    }

    /** @return list<array<string, mixed>> objets JSON-LD du <head>, tels que les lit un robot */
    private function jsonLd(HTMLDocument $doc): array
    {
        $out = [];
        foreach ($doc->querySelectorAll('head script[type="application/ld+json"][data-vff-seo]') as $script) {
            $data = json_decode($script->textContent, true);
            $this->assertIsArray($data, 'JSON-LD illisible : '.substr($script->textContent, 0, 120));
            $out[] = $data;
        }

        return $out;
    }

    public function test_line_sheet_has_open_graph_twitter_canonical_and_json_ld(): void
    {
        $line = Line::factory()->create([
            'slug' => 'rfn-610000', 'code' => '610000', 'name' => 'Ligne de Limoges-Bénédictins à Angoulême',
            'description' => 'Une ligne régionale entre la Haute-Vienne et la Charente.',
        ]);
        Media::factory()->create([
            'mediable_type' => Line::class, 'mediable_id' => $line->id, 'type' => MediaType::Photo->value, 'provider' => 'commons',
            'title' => 'X2800 Saint-Junien', 'caption' => 'Autorail X 2800 à Saint-Junien', 'author' => 'Quelqu\'un', 'license' => 'CC BY-SA 4.0',
            'thumb_url' => 'https://upload.wikimedia.org/thumb/500.jpg', 'display_url' => 'https://upload.wikimedia.org/thumb/1280.jpg',
            'depicted_year' => 1950, 'source_url' => 'https://commons.wikimedia.org/wiki/File:X2800.jpg', 'external_id' => 'File:X2800.jpg',
        ]);

        $response = $this->withHeader('Accept-Language', 'fr')->get('/lines/rfn-610000')->assertOk();
        $doc = $this->document($response);

        $this->assertStringContainsString('<meta data-vff-seo property="og:title" content="Ligne de Limoges-Bénédictins à Angoulême" />', (string) $response->getContent());
        $this->assertSame('Ligne de Limoges-Bénédictins à Angoulême', $this->meta($doc, 'og:title'));
        $this->assertSame('Ligne de Limoges-Bénédictins à Angoulême', $this->meta($doc, 'twitter:title'));
        $this->assertSame('Une ligne régionale entre la Haute-Vienne et la Charente.', $this->meta($doc, 'og:description'));
        $this->assertSame('article', $this->meta($doc, 'og:type'));
        $this->assertSame('fr_FR', $this->meta($doc, 'og:locale'));
        $this->assertSame('summary_large_image', $this->meta($doc, 'twitter:card'));
        $this->assertSame('https://upload.wikimedia.org/thumb/1280.jpg', $this->meta($doc, 'og:image'));
        $this->assertSame('Autorail X 2800 à Saint-Junien', $this->meta($doc, 'og:image:alt'));
        $url = url('/lines/rfn-610000');
        $this->assertSame($url, $this->meta($doc, 'og:url'));
        $this->assertSame($url, $doc->querySelector('head link[rel="canonical"]')->getAttribute('href'));
        $this->assertSame('Ligne de Limoges-Bénédictins à Angoulême - VFF', $doc->querySelector('head title')->textContent);

        [$place, $breadcrumb] = $this->jsonLd($doc);
        $this->assertSame('Place', $place['@type']);
        $this->assertSame('610000', $place['identifier']['value']);
        $this->assertSame('https://upload.wikimedia.org/thumb/1280.jpg', $place['image'][0]['contentUrl']);
        $this->assertSame('Quelqu\'un', $place['image'][0]['creator']['name']);
        $this->assertSame('BreadcrumbList', $breadcrumb['@type']);
        $this->assertSame(['Voies Ferrées de France', 'Lignes', 'Ligne de Limoges-Bénédictins à Angoulême'], array_column($breadcrumb['itemListElement'], 'name'));
    }

    public function test_a_media_without_image_is_skipped_for_the_share_image(): void
    {
        $line = Line::factory()->create(['slug' => 'rfn-570000', 'name' => 'Ligne de Paris à Bordeaux']);
        $media = fn (array $attributes) => Media::factory()->create([
            'mediable_type' => Line::class, 'mediable_id' => $line->id, 'type' => MediaType::Document->value, 'provider' => 'gallica',
            'thumb_url' => null, 'display_url' => null, 'file_path' => null, ...$attributes,
        ]);
        // Le plus ancien (premier de la galerie) n'a aucune image : il est sauté, sans erreur 500.
        $media(['depicted_year' => 1850, 'caption' => 'Acte de concession', 'external_id' => 'doc-1']);
        $this->assertSame(Response::HTTP_OK, $this->get('/lines/rfn-570000')->getStatusCode());

        $media(['depicted_year' => 1900, 'type' => MediaType::Photo->value, 'provider' => 'commons', 'caption' => 'Gare de Poitiers',
            'thumb_url' => '//upload.wikimedia.org/thumb/500.jpg', 'external_id' => 'File:Poitiers.jpg']);
        $doc = $this->document($this->get('/lines/rfn-570000')->assertOk());

        $this->assertSame('https://upload.wikimedia.org/thumb/500.jpg', $this->meta($doc, 'og:image'));
        $this->assertSame('Gare de Poitiers', $this->meta($doc, 'og:image:alt'));
        [$place] = $this->jsonLd($doc);
        $this->assertCount(1, $place['image']);
    }

    public function test_a_line_whose_media_have_no_image_has_no_share_image(): void
    {
        $line = Line::factory()->create(['slug' => 'rfn-571000']);
        Media::factory()->create([
            'mediable_type' => Line::class, 'mediable_id' => $line->id, 'type' => MediaType::Document->value, 'provider' => 'gallica',
            'thumb_url' => null, 'display_url' => null, 'file_path' => null, 'external_id' => 'doc-2',
        ]);
        $doc = $this->document($this->get('/lines/rfn-571000')->assertOk());

        $this->assertNull($this->meta($doc, 'og:image'));
        $this->assertSame('summary', $this->meta($doc, 'twitter:card'));
    }

    public function test_every_share_tag_appears_once_and_the_generic_description_is_replaced(): void
    {
        Line::factory()->create(['slug' => 'rfn-1', 'name' => 'Ligne 1']);

        $doc = $this->document($this->get('/lines/rfn-1')->assertOk());

        foreach (['og:title', 'og:description', 'og:url', 'og:type', 'twitter:card', 'twitter:title'] as $key) {
            $this->assertSame(1, $doc->querySelectorAll("head meta[property=\"{$key}\"], head meta[name=\"{$key}\"]")->length, "{$key} dupliqué");
        }
        $this->assertSame(1, $doc->querySelectorAll('head meta[name="description"]')->length, 'une seule description');
        $this->assertSame(1, $doc->querySelectorAll('head link[rel="canonical"]')->length);
        $this->assertSame('summary', $this->meta($doc, 'twitter:card'), 'sans image : carte simple');
        $this->assertNull($this->meta($doc, 'og:image'));
    }

    public function test_home_page(): void
    {
        $doc = $this->document($this->withHeader('Accept-Language', 'en')->get('/')->assertOk());

        $this->assertSame('VFF - French Railway Lines', $this->meta($doc, 'og:title'));
        $this->assertSame('website', $this->meta($doc, 'og:type'));
        $this->assertSame('en_GB', $this->meta($doc, 'og:locale'));
        $this->assertSame('French Railway Lines', $this->meta($doc, 'og:site_name'));
        $this->assertSame(url('/icons/icon-512.png'), $this->meta($doc, 'og:image'));
        $this->assertSame('VFF - French Railway Lines', $doc->querySelector('head title')->textContent);

        $graph = $this->jsonLd($doc)[0]['@graph'];
        $this->assertSame('WebSite', $graph[0]['@type']);
        $this->assertSame('en', $graph[0]['inLanguage']);
        $this->assertSame('Organization', $graph[1]['@type']);
    }

    public function test_station_material_and_accident_sheets(): void
    {
        Station::factory()->create(['slug' => 'bondy', 'name' => 'Bondy', 'city' => 'Bondy', 'uic_code' => '87113407']);
        Material::factory()->fromWikidata()->create(['slug' => 'bb-15000', 'name' => 'BB 15000', 'type' => 'locomotive_electrique']);
        Accident::factory()->on('1842-05-08')->create([
            'slug' => '1842-05-08-meudon', 'name' => ['fr' => 'Accident ferroviaire de Meudon', 'en' => 'Versailles rail accident', 'de' => 'Eisenbahnunfall von Meudon'],
            'summary' => ['fr' => 'Un déraillement.', 'en' => 'A derailment.', 'de' => 'Eine Entgleisung.'],
        ]);

        $station = $this->document($this->get('/stations/bondy')->assertOk());
        $this->assertSame('Bondy', $this->meta($station, 'og:title'));
        $this->assertSame('TrainStation', $this->jsonLd($station)[0]['@type']);
        $this->assertSame('87113407', $this->jsonLd($station)[0]['identifier']['value']);

        $material = $this->document($this->withHeader('Accept-Language', 'fr')->get('/materials/bb-15000')->assertOk());
        $this->assertSame('BB 15000', $this->meta($material, 'og:title'));
        $this->assertSame('BB 15000, série de matériel roulant : caractéristiques sourcées, lignes parcourues et photos créditées.', $this->meta($material, 'og:description'));
        $this->assertSame('Thing', $this->jsonLd($material)[0]['@type']);
        $this->assertNotNull($this->meta($material, 'og:image'), 'la série importée a sa photo Commons');

        $accident = $this->document($this->withHeader('Accept-Language', 'de')->get('/accidents/1842-05-08-meudon')->assertOk());
        $this->assertSame('Eisenbahnunfall von Meudon', $this->meta($accident, 'og:title'));
        $this->assertSame('de_DE', $this->meta($accident, 'og:locale'));
        $event = $this->jsonLd($accident)[0];
        $this->assertSame('Event', $event['@type']);
        $this->assertSame('Eine Entgleisung.', $event['description']);
        $this->assertSame('1842-05-08', $event['startDate']);
    }

    public function test_a_title_that_looks_like_markup_cannot_break_out_of_the_tags(): void
    {
        Line::factory()->create(['slug' => 'rfn-2', 'name' => '</script><script>alert(1)</script> "x"', 'description' => '<img src=x onerror=alert(1)>']);

        $response = $this->get('/lines/rfn-2')->assertOk();
        $doc = $this->document($response);

        $injected = $doc->querySelectorAll('head img, head script:not([type]):not([data-theme-init]):not([src])');
        $this->assertSame(0, $injected->length, $injected->length > 0 ? $injected[0]->outerHTML : '');
        $this->assertSame('</script><script>alert(1)</script> "x"', $this->meta($doc, 'og:title'));
        $this->assertSame('</script><script>alert(1)</script> "x"', $this->jsonLd($doc)[0]['name']);
    }

    public function test_other_pages_keep_the_generic_description_and_no_share_tags(): void
    {
        $response = $this->get('/lines')->assertOk();
        $doc = $this->document($response);

        $this->assertSame(1, $doc->querySelectorAll('head meta[name="description"]')->length);
        $this->assertStringNotContainsString('data-vff-seo', (string) $response->getContent());
        $this->assertNull($this->meta($doc, 'og:title'));
    }
}
