<?php

namespace Tests\Feature\Api;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Services\TraceExport\LineTraceExport;
use App\Support\SourceRights;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exports du tracé d'une ligne : `/api/v1/lines/{line}/gpx`, `/kml` et `/geojson`.
 * Format (GPX 1.1, KML 2.2), en-têtes, mentions des sources et règle des droits : une géométrie dont
 * la source n'a pas de licence ouverte écrite n'est jamais exportée, et la réponse le dit.
 */
class LineTraceExportTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    private Era $era1930;

    private Era $today;

    protected function setUp(): void
    {
        parent::setUp();
        $this->era1930 = Era::factory()->create(['year' => 1930, 'label' => 'Années 1930']);
        $this->today = Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd\'hui']);
        $this->line = Line::factory()->create(['slug' => 'rfn-999000', 'name' => 'Ligne d\'essai & <test>', 'code' => '999000']);
    }

    private function segment(Era $era, string $source, int $pkStart, int $pkEnd, float $lat = 48.0): LineSegment
    {
        return LineSegment::factory()->inService()->create([
            'line_id' => $this->line->id,
            'era_id' => $era->id,
            'source' => $source,
            'pk_start_m' => $pkStart,
            'pk_end_m' => $pkEnd,
            'length_meters' => $pkEnd - $pkStart,
            'geom' => LineString::make([Point::makeGeodetic($lat, 2.0), Point::makeGeodetic($lat + 0.01, 2.01)]),
        ]);
    }

    private function dom(string $xml): \DOMDocument
    {
        $dom = new \DOMDocument;
        $this->assertTrue($dom->loadXML($xml), 'XML bien formé');

        return $dom;
    }

    public function test_gpx_has_one_segment_per_section_in_pk_order_and_the_mentions(): void
    {
        $this->segment($this->era1930, 'bdtopo', 5000, 9000, 48.1);
        $this->segment($this->era1930, 'osm', 0, 5000, 48.0);

        $response = $this->get('/api/v1/lines/rfn-999000/gpx?era=1930')->assertOk();

        $response->assertHeader('Content-Type', 'application/gpx+xml; charset=UTF-8');
        $this->assertSame('attachment; filename=rfn-999000-1930.gpx', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=300', (string) $response->headers->get('Cache-Control'));

        $xml = (string) $response->getContent();
        $dom = $this->dom($xml);
        $this->assertSame('http://www.topografix.com/GPX/1/1', $dom->documentElement->namespaceURI);
        $this->assertSame('1.1', $dom->documentElement->getAttribute('version'));

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('g', 'http://www.topografix.com/GPX/1/1');
        $this->assertSame(1, $xpath->query('//g:trk')->length);
        $this->assertSame(2, $xpath->query('//g:trkseg')->length);
        $this->assertSame(4, $xpath->query('//g:trkpt')->length);
        $this->assertSame('Ligne d\'essai & <test>', $xpath->query('//g:trk/g:name')->item(0)?->textContent);
        // Premier tronçon = PK 0 (OSM, lat 48.0), même si le tronçon IGN a été créé en premier.
        $this->assertSame('48', $xpath->query('//g:trkseg[1]/g:trkpt[1]/@lat')->item(0)?->nodeValue);
        $this->assertSame('48.1', $xpath->query('//g:trkseg[2]/g:trkpt[1]/@lat')->item(0)?->nodeValue);

        $desc = (string) $xpath->query('//g:metadata/g:desc')->item(0)?->textContent;
        $this->assertStringContainsString('état du réseau au 31 décembre 1930', $desc);
        $this->assertStringContainsString(url('/lines/rfn-999000'), $desc);
        $this->assertStringContainsString('OpenStreetMap', $desc);
        $this->assertStringContainsString('Licence Ouverte 2.0', $desc);
        $this->assertSame(url('/lines/rfn-999000'), $xpath->query('//g:metadata/g:link/@href')->item(0)?->nodeValue);
        $this->assertStringContainsString('ODbL', (string) $xpath->query('//g:metadata/g:copyright/@author')->item(0)?->nodeValue);
    }

    public function test_gpx_validates_against_the_official_schema_when_available(): void
    {
        $this->segment($this->era1930, 'osm', 0, 5000);
        $dom = $this->dom((string) $this->get('/api/v1/lines/rfn-999000/gpx?era=1930')->getContent());

        // Ordre des éléments imposé par gpx.xsd : metadata avant trk, name avant desc, etc.
        $names = [];
        foreach ($dom->documentElement->childNodes as $node) {
            if ($node instanceof \DOMElement) {
                $names[] = $node->localName;
            }
        }
        $this->assertSame(['metadata', 'trk'], $names);
        $trk = [];
        foreach ($dom->getElementsByTagName('trk')->item(0)->childNodes as $node) {
            if ($node instanceof \DOMElement) {
                $trk[] = $node->localName;
            }
        }
        $this->assertSame(['name', 'desc', 'src', 'link', 'type', 'trkseg'], $trk);
    }

    public function test_kml_has_one_linestring_per_section_and_the_document_metadata(): void
    {
        $this->segment($this->era1930, 'osm', 0, 5000, 48.0);
        $this->segment($this->era1930, 'sncf', 5000, 12345, 48.1);

        $response = $this->get('/api/v1/lines/rfn-999000/kml?era=1930&lang=en')->assertOk();

        $response->assertHeader('Content-Type', 'application/vnd.google-earth.kml+xml; charset=UTF-8');
        $this->assertSame('attachment; filename=rfn-999000-1930.kml', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('max-age=300', (string) $response->headers->get('Cache-Control'));

        $dom = $this->dom((string) $response->getContent());
        $this->assertSame('http://www.opengis.net/kml/2.2', $dom->documentElement->namespaceURI);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('k', 'http://www.opengis.net/kml/2.2');
        $xpath->registerNamespace('atom', 'http://www.w3.org/2005/Atom');
        $this->assertSame(2, $xpath->query('//k:Placemark')->length);
        $this->assertSame(2, $xpath->query('//k:Placemark/k:LineString/k:coordinates')->length);
        $this->assertSame('2,48 2.01,48.01', $xpath->query('//k:Placemark[1]/k:LineString/k:coordinates')->item(0)?->textContent);
        $this->assertSame(url('/lines/rfn-999000'), $xpath->query('//k:Document/atom:link/@href')->item(0)?->nodeValue);
        $this->assertStringContainsString('state of the network on 31 December 1930', (string) $xpath->query('//k:Document/k:name')->item(0)?->textContent);
        $this->assertStringContainsString('PK 5+000 to 12+345', (string) $xpath->query('//k:Placemark[2]/k:description')->item(0)?->textContent);
        $this->assertStringContainsString('OpenStreetMap', (string) $xpath->query('//k:Document/k:description')->item(0)?->textContent);
        $this->assertStringContainsString('ODbL', (string) $xpath->query('//k:Document/k:description')->item(0)?->textContent);
    }

    public function test_default_era_is_the_current_one_and_the_file_is_named_after_it(): void
    {
        $this->segment($this->today, 'osm', 0, 5000);

        $this->get('/api/v1/lines/rfn-999000/gpx')
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=rfn-999000-2030.gpx');
        $this->assertStringContainsString('état actuel du réseau', (string) $this->get('/api/v1/lines/rfn-999000/kml')->getContent());
    }

    public function test_texts_follow_the_requested_language(): void
    {
        $this->segment($this->era1930, 'osm', 0, 5000);

        $de = (string) $this->get('/api/v1/lines/rfn-999000/gpx?era=1930&lang=de')->getContent();

        $this->assertStringContainsString('Stand des Netzes am 31. Dezember 1930', $de);
    }

    public function test_sections_of_a_source_without_open_licence_are_never_exported(): void
    {
        $this->segment($this->era1930, 'osm', 0, 5000, 48.0);
        $this->segment($this->era1930, 'archeoferroviaire', 5000, 8000, 47.0);

        $gpx = (string) $this->get('/api/v1/lines/rfn-999000/gpx?era=1930')->assertOk()->getContent();
        $kml = (string) $this->get('/api/v1/lines/rfn-999000/kml?era=1930')->assertOk()->getContent();

        foreach ([$gpx, $kml] as $file) {
            $this->assertStringNotContainsString('47.0', $file, 'aucune coordonnée du tronçon écarté');
            $this->assertStringNotContainsString('47,', $file);
            $this->assertStringContainsString('1 tronçon (3,0 km) n’est pas inclus', $file, 'le fichier dit ce qui manque');
        }
        $this->assertSame(1, substr_count($gpx, '<trkseg>'));
        $this->assertSame(1, substr_count($kml, '<Placemark>'));
    }

    public function test_a_route_with_only_withheld_sources_answers_451_with_the_detail(): void
    {
        $this->segment($this->era1930, 'archeoferroviaire', 0, 8000);

        foreach (['gpx', 'kml'] as $format) {
            $this->getJson("/api/v1/lines/rfn-999000/{$format}?era=1930")
                ->assertStatus(451)
                ->assertJsonPath('error', 'geometry_withheld')
                ->assertJsonPath('withheld.0.provider', 'archeoferroviaire')
                ->assertJsonPath('withheld.0.reason', 'licence_not_open')
                ->assertJsonPath('withheld.0.sections', 1)
                ->assertJsonPath('withheld.0.length_m', 8000);
        }
    }

    public function test_a_source_missing_from_the_registry_is_withheld(): void
    {
        $this->segment($this->era1930, 'inconnue', 0, 4000);

        $this->getJson('/api/v1/lines/rfn-999000/gpx?era=1930')
            ->assertStatus(451)
            ->assertJsonPath('withheld.0.provider', null)
            ->assertJsonPath('withheld.0.reason', 'unknown_source');
    }

    public function test_a_line_without_route_in_the_era_answers_404(): void
    {
        $this->segment($this->today, 'osm', 0, 5000);

        foreach (['gpx', 'kml'] as $format) {
            $this->getJson("/api/v1/lines/rfn-999000/{$format}?era=1930")->assertNotFound()->assertJsonPath('error', 'no_geometry');
        }
    }

    public function test_unknown_era_and_invalid_parameters(): void
    {
        $this->segment($this->era1930, 'osm', 0, 5000);

        foreach (['gpx', 'kml', 'geojson'] as $format) {
            $this->getJson("/api/v1/lines/rfn-999000/{$format}?era=1935")->assertNotFound();
            $this->getJson("/api/v1/lines/rfn-999000/{$format}?era=abc")->assertStatus(422)->assertJsonValidationErrors('era');
            $this->getJson("/api/v1/lines/rfn-999000/{$format}?lang=xx")->assertStatus(422)->assertJsonValidationErrors('lang');
            $this->getJson("/api/v1/lines/inconnue/{$format}?era=1930")->assertNotFound();
        }
    }

    public function test_geojson_withholds_the_geometry_of_a_source_without_open_licence(): void
    {
        $this->segment($this->era1930, 'osm', 0, 5000, 48.0);
        $this->segment($this->era1930, 'archeoferroviaire', 5000, 8000, 47.0);

        $response = $this->getJson('/api/v1/lines/rfn-999000/geojson?era=1930')->assertOk();

        $response->assertHeader('Content-Type', 'application/geo+json');
        $features = $response->json('features');
        $this->assertCount(2, $features);
        $this->assertSame('LineString', $features[0]['geometry']['type']);
        $this->assertFalse($features[0]['properties']['geometry_withheld']);
        $this->assertNull($features[1]['geometry']);
        $this->assertTrue($features[1]['properties']['geometry_withheld']);
        $this->assertSame('archeoferroviaire', $features[1]['properties']['provenance']['provider']);
        $response->assertJsonPath('withheld.0.provider', 'archeoferroviaire')
            ->assertJsonPath('withheld.0.sections', 1)
            ->assertJsonPath('line.url', url('/lines/rfn-999000'));
        $this->assertSame(['vff', 'osm'], array_column($response->json('attribution.sources'), 'provider'));
        $this->assertStringNotContainsString('47.0', (string) $response->getContent());
    }

    public function test_segments_with_geometry_follow_the_same_rule(): void
    {
        $this->segment($this->era1930, 'archeoferroviaire', 0, 3000, 47.0);

        $response = $this->getJson('/api/v1/lines/rfn-999000/segments?era=1930&geometry=true')->assertOk();

        $this->assertNull($response->json('data.0.geometry'));
        $this->assertTrue($response->json('data.0.geometry_withheld'));
        $this->assertStringNotContainsString('47.0', (string) $response->getContent());
        $this->getJson('/api/v1/lines/rfn-999000/segments?era=1930')->assertOk()->assertJsonMissingPath('data.0.geometry_withheld');
    }

    public function test_line_sheet_links_and_download_eras(): void
    {
        $this->segment($this->era1930, 'osm', 0, 5000);
        $this->segment($this->today, 'archeoferroviaire', 0, 5000);

        $links = $this->getJson('/api/v1/lines/rfn-999000')->assertOk()->json('data.links');
        $this->assertStringEndsWith('/api/v1/lines/rfn-999000/gpx', $links['gpx']);
        $this->assertStringEndsWith('/api/v1/lines/rfn-999000/kml', $links['kml']);

        // Seule l'ère dont une source est redistribuable est proposée au téléchargement.
        $this->assertSame([1930], app(LineTraceExport::class)->exportableEraYears($this->line));
    }

    public function test_every_geometry_source_of_the_registry_follows_its_status(): void
    {
        $rights = app(SourceRights::class);
        $export = app(LineTraceExport::class);

        foreach ($rights->sources()[LineTraceExport::TABLE] as $source => $provider) {
            $this->assertSame(
                $rights->status($provider) === SourceRights::SAFE,
                $export->redistributable($source),
                "source {$source} ({$provider})",
            );
        }
        $this->assertFalse($export->redistributable(null));
        $this->assertFalse($export->redistributable('inconnue'));
    }

    public function test_openapi_documents_both_files_in_french_with_their_errors(): void
    {
        $paths = $this->getJson('/docs/api.json')->assertOk()->json('paths');

        foreach (['gpx' => 'application/gpx+xml; charset=UTF-8', 'kml' => 'application/vnd.google-earth.kml+xml; charset=UTF-8'] as $format => $mediaType) {
            $operation = $paths["/lines/{line}/{$format}"]['get'];
            foreach ([200, 404, 422, 429, 451] as $code) {
                $this->assertArrayHasKey((string) $code, $operation['responses'], "{$format} : réponse {$code}");
            }
            $this->assertArrayHasKey($mediaType, $operation['responses']['200']['content']);
            $this->assertNotEmpty($operation['responses']['451']['content']['application/json']['schema']['examples'] ?? null);
            $this->assertEqualsCanonicalizing(['line', 'era', 'lang'], array_column($operation['parameters'], 'name'));
            $this->assertStringContainsString('licence', $operation['description']);
            $this->assertCount(2, $operation['x-codeSamples']);
        }
    }
}
