<?php

namespace Tests\Feature;

use App\Models\Accident;
use App\Models\Line;
use App\Models\Material;
use App\Models\Station;
use App\Services\Seo\SitemapBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Plans du site : index, une famille par type de fiche publiée, pagination, fiches non publiées absentes. */
class SitemapTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> */
    private function locs(string $path): array
    {
        $xml = simplexml_load_string((string) $this->get($path)->assertOk()->getContent());
        $this->assertNotFalse($xml);

        return array_map('strval', iterator_to_array($xml->xpath('//*[local-name()="loc"]'), false));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $line = Line::factory()->create(['slug' => 'rfn-123456', 'code' => '123456']);
        DB::table('lines')->where('id', $line->id)->update(['encyclopedia_updated_at' => '2026-05-04 10:00:00']);
        Station::factory()->create(['slug' => 'gare-essai']);
        Material::factory()->create(['slug' => 'serie-essai']);
        Accident::factory()->create(['slug' => '2000-01-01-publie', 'kind' => 'collision']);
        Accident::factory()->create(['slug' => '2000-01-02-masque', 'kind' => 'person_struck']);
        $keep = Accident::factory()->create(['slug' => '2000-01-03-original', 'kind' => 'collision']);
        $alias = Accident::factory()->create(['slug' => '2000-01-03-alias', 'kind' => 'collision']);
        DB::table('accidents')->where('id', $alias->id)->update(['alias_of_id' => $keep->id]);
    }

    public function test_index_lists_the_journal_and_every_family(): void
    {
        $locs = $this->locs('/sitemap.xml');
        $this->assertSame(url('/journal/sitemap.xml'), $locs[0]);
        foreach (SitemapBuilder::FAMILIES as $family) {
            $this->assertContains(url("/sitemap-{$family}-1.xml"), $locs);
        }
    }

    public function test_families_hold_published_pages_with_a_real_lastmod(): void
    {
        $this->assertContains(url('/lines/rfn-123456'), $this->locs('/sitemap-lines-1.xml'));
        $this->assertStringContainsString('<lastmod>2026-05-04</lastmod>', (string) $this->get('/sitemap-lines-1.xml')->getContent());
        $this->assertContains(url('/stations/gare-essai'), $this->locs('/sitemap-stations-1.xml'));
        $this->assertContains(url('/materials/serie-essai'), $this->locs('/sitemap-materials-1.xml'));
        $pages = $this->locs('/sitemap-pages-1.xml');
        foreach (['/', '/whoarewe', '/lines', '/api', '/credits/tech', '/privacy'] as $path) {
            $this->assertContains(rtrim(url($path), '/') ?: url('/'), array_map(fn (string $u): string => rtrim($u, '/'), $pages));
        }
        foreach ($pages as $page) {
            $this->assertDoesNotMatchRegularExpression('#/(login|register|profile|validate|contribute/(mine|edit|event|media|material|series|trace|thanks))#', $page);
        }
    }

    public function test_unpublished_accidents_are_absent(): void
    {
        $locs = $this->locs('/sitemap-accidents-1.xml');
        $this->assertContains(url('/accidents/2000-01-01-publie'), $locs);
        $this->assertContains(url('/accidents/2000-01-03-original'), $locs);
        $this->assertNotContains(url('/accidents/2000-01-02-masque'), $locs);
        $this->assertNotContains(url('/accidents/2000-01-03-alias'), $locs);
    }

    public function test_pages_are_split_by_page_size_and_listed_in_the_index(): void
    {
        Accident::factory()->create(['slug' => '2000-01-04-troisieme', 'kind' => 'derailment']);
        $this->app->instance(SitemapBuilder::class, new SitemapBuilder(2));
        $index = $this->locs('/sitemap.xml');
        $this->assertContains(url('/sitemap-accidents-2.xml'), $index);
        $this->assertNotContains(url('/sitemap-accidents-3.xml'), $index);
        $this->assertCount(2, $this->locs('/sitemap-accidents-1.xml'));
        $this->assertCount(1, $this->locs('/sitemap-accidents-2.xml'));
        $this->get('/sitemap-accidents-3.xml')->assertNotFound();
    }

    public function test_unknown_family_or_page_is_not_found(): void
    {
        $this->get('/sitemap-nope-1.xml')->assertNotFound();
        $this->get('/sitemap-lines-0.xml')->assertNotFound();
        $this->get('/sitemap-lines-2.xml')->assertNotFound();
    }

    public function test_robots_declares_the_index(): void
    {
        $this->assertStringContainsString('Sitemap: https://vff-project.org/sitemap.xml', (string) file_get_contents(public_path('robots.txt')));
    }
}
