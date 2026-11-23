<?php

namespace Tests\Feature;

use App\Models\Accident;
use App\Models\Line;
use App\Models\Material;
use App\Models\Station;
use App\Services\Seo\SitemapBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Contenu exact des plans du site : XML, bornes de pagination, dates, ordre, cache. */
class SitemapBuilderMutationTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> */
    private function locs(string $xml): array
    {
        $doc = simplexml_load_string($xml);
        $this->assertNotFalse($doc);

        return array_map('strval', iterator_to_array($doc->xpath('//*[local-name()="loc"]'), false));
    }

    private function stations(int $n): void
    {
        for ($i = 1; $i <= $n; $i++) {
            Station::factory()->create(['slug' => "gare-$i"]);
        }
    }

    public function test_the_path_of_a_plan(): void
    {
        $this->assertSame('/sitemap-lines-3.xml', SitemapBuilder::path('lines', 3));
        $this->assertSame(50000, SitemapBuilder::PAGE_SIZE);
        $this->assertSame(3600, SitemapBuilder::TTL);
    }

    public function test_the_index_is_a_well_formed_sitemapindex_in_the_documented_order(): void
    {
        $xml = (new SitemapBuilder)->index('https://vff-project.org');

        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $xml);
        $this->assertSame(
            [
                'https://vff-project.org/journal/sitemap.xml',
                'https://vff-project.org/sitemap-pages-1.xml',
                'https://vff-project.org/sitemap-lines-1.xml',
                'https://vff-project.org/sitemap-stations-1.xml',
                'https://vff-project.org/sitemap-communes-1.xml',
                'https://vff-project.org/sitemap-departments-1.xml',
                'https://vff-project.org/sitemap-companies-1.xml',
                'https://vff-project.org/sitemap-materials-1.xml',
                'https://vff-project.org/sitemap-accidents-1.xml',
            ],
            $this->locs($xml),
        );
        $this->assertSame(9, substr_count($xml, '<sitemap>'));
        $this->assertStringContainsString("<sitemap>\n", $xml);
    }

    public function test_an_empty_family_still_has_one_page_and_the_page_count_is_a_ceiling(): void
    {
        $builder = new SitemapBuilder(2);
        $this->assertContains('https://h.org/sitemap-stations-1.xml', $this->locs($builder->index('https://h.org')));
        $this->assertNotNull($builder->family('https://h.org', 'stations', 1), 'famille vide : une page');
        $this->assertNull($builder->family('https://h.org', 'stations', 2));

        $this->stations(2);
        Cache::flush();
        $this->assertNotContains('https://h.org/sitemap-stations-2.xml', $this->locs($builder->index('https://h.org')), 'deux fiches, deux par page : une page');
        $this->assertNull($builder->family('https://h.org', 'stations', 2));

        $this->stations(0);
        Station::factory()->create(['slug' => 'gare-3']);
        Cache::flush();
        $this->assertContains('https://h.org/sitemap-stations-2.xml', $this->locs($builder->index('https://h.org')));
        $this->assertNotNull($builder->family('https://h.org', 'stations', 2));
        $this->assertNull($builder->family('https://h.org', 'stations', 3));
    }

    public function test_the_pages_family_lists_the_editorial_pages_in_pages_of_the_given_size(): void
    {
        Accident::factory()->create(['slug' => '2000-01-01-pas-dans-les-pages', 'kind' => 'collision']);
        $all = $this->locs((new SitemapBuilder)->family('https://h.org', 'pages', 1) ?? '');
        $this->assertCount(32, $all);
        $this->assertSame('https://h.org/', $all[0]);
        $this->assertSame('https://h.org/whoarewe', $all[1]);
        $this->assertSame('https://h.org/credits/media', $all[31]);
        $this->assertStringNotContainsString('lastmod', (string) (new SitemapBuilder)->family('https://h.org', 'pages', 1));

        $small = new SitemapBuilder(5);
        $this->assertNotNull($small->family('https://h.org', 'pages', 7));
        $this->assertNull($small->family('https://h.org', 'pages', 8));
        $this->assertCount(7, array_filter($this->locs($small->index('https://h.org')), fn (string $l): bool => str_contains($l, 'sitemap-pages-')));
    }

    public function test_rows_follow_the_order_of_ids_and_the_offset_of_the_page(): void
    {
        $this->stations(5);
        $builder = new SitemapBuilder(2);

        $this->assertSame(['https://h.org/stations/gare-1', 'https://h.org/stations/gare-2'], $this->locs($builder->family('https://h.org', 'stations', 1) ?? ''));
        $this->assertSame(['https://h.org/stations/gare-3', 'https://h.org/stations/gare-4'], $this->locs($builder->family('https://h.org', 'stations', 2) ?? ''));
        $this->assertSame(['https://h.org/stations/gare-5'], $this->locs($builder->family('https://h.org', 'stations', 3) ?? ''));
    }

    public function test_a_page_number_below_one_or_an_unknown_family_gives_nothing(): void
    {
        $this->stations(1);
        $builder = new SitemapBuilder;
        $this->assertNull($builder->family('https://h.org', 'stations', 0));
        $this->assertNull($builder->family('https://h.org', 'stations', -1));
        $this->assertNull($builder->family('https://h.org', 'journal', 1));
        $this->assertNotNull($builder->family('https://h.org', 'stations', 1));
    }

    public function test_the_urlset_declares_its_namespace_and_each_url_has_loc_then_lastmod(): void
    {
        $station = Station::factory()->create(['slug' => 'gare-datee']);
        DB::table('stations')->where('id', $station->id)->update(['updated_at' => '2026-03-02 23:30:00']);

        $xml = (new SitemapBuilder)->family('https://h.org', 'stations', 1) ?? '';

        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $xml);
        $this->assertMatchesRegularExpression('#<url>\s*<loc>https://h.org/stations/gare-datee</loc>\s*<lastmod>2026-03-02</lastmod>\s*</url>#', $xml);
    }

    public function test_lastmod_is_the_encyclopedia_date_then_the_update_date_and_absent_when_unknown(): void
    {
        $a = Line::factory()->create(['slug' => 'ligne-a']);
        $b = Line::factory()->create(['slug' => 'ligne-b']);
        $c = Line::factory()->create(['slug' => 'ligne-c']);
        DB::table('lines')->where('id', $a->id)->update(['encyclopedia_updated_at' => '2026-05-04 10:00:00', 'updated_at' => '2025-01-01 00:00:00']);
        DB::table('lines')->where('id', $b->id)->update(['encyclopedia_updated_at' => null, 'updated_at' => '2025-02-03 00:00:00']);
        DB::table('lines')->where('id', $c->id)->update(['encyclopedia_updated_at' => null, 'updated_at' => null]);

        $xml = (new SitemapBuilder)->family('https://h.org', 'lines', 1) ?? '';

        $this->assertMatchesRegularExpression('#/lines/ligne-a</loc>\s*<lastmod>2026-05-04</lastmod>#', $xml);
        $this->assertMatchesRegularExpression('#/lines/ligne-b</loc>\s*<lastmod>2025-02-03</lastmod>#', $xml);
        $this->assertMatchesRegularExpression('#/lines/ligne-c</loc>\s*</url>#', $xml);
        $this->assertSame(2, substr_count($xml, '<lastmod>'));
    }

    public function test_every_family_uses_its_own_base_path_and_date_column(): void
    {
        $m = Material::factory()->create(['slug' => 'serie z 2']);
        DB::table('materials')->where('id', $m->id)->update(['updated_at' => '2024-06-07 00:00:00']);
        $accident = Accident::factory()->create(['slug' => '2001-02-03-essai', 'kind' => 'collision']);
        DB::table('accidents')->where('id', $accident->id)->update(['updated_at' => '2023-08-09 00:00:00']);

        $materials = (new SitemapBuilder)->family('https://h.org', 'materials', 1) ?? '';
        $accidents = (new SitemapBuilder)->family('https://h.org', 'accidents', 1) ?? '';

        $this->assertSame(['https://h.org/materials/serie%20z%202'], $this->locs($materials));
        $this->assertStringContainsString('<lastmod>2024-06-07</lastmod>', $materials);
        $this->assertSame(['https://h.org/accidents/2001-02-03-essai'], $this->locs($accidents));
        $this->assertStringContainsString('<lastmod>2023-08-09</lastmod>', $accidents);
    }

    public function test_a_plan_is_cached_per_origin_and_family_page(): void
    {
        $this->stations(1);
        $builder = new SitemapBuilder;
        $first = $builder->family('https://a.org', 'stations', 1);
        $this->stations(0);
        Station::factory()->create(['slug' => 'gare-nouvelle']);

        $this->assertSame($first, $builder->family('https://a.org', 'stations', 1), 'gardé dans le cache');
        $this->assertCount(2, $this->locs($builder->family('https://b.org', 'stations', 1) ?? ''), 'une autre origine a son plan');
        $this->assertTrue(Cache::has('sitemap:stations:1:'.md5('https://a.org')));

        $index = $builder->index('https://a.org');
        Station::factory()->create(['slug' => 'gare-encore']);
        $this->assertSame($index, $builder->index('https://a.org'));
        $this->assertTrue(Cache::has('sitemap:index:'.md5('https://a.org')));
        $this->assertNotSame($index, $builder->index('https://c.org'));
    }

    public function test_the_index_page_count_counts_only_published_accidents(): void
    {
        $keep = Accident::factory()->create(['slug' => '2000-01-01-a', 'kind' => 'collision']);
        $alias = Accident::factory()->create(['slug' => '2000-01-01-b', 'kind' => 'collision']);
        DB::table('accidents')->where('id', $alias->id)->update(['alias_of_id' => $keep->id]);
        Accident::factory()->create(['slug' => '2000-01-01-c', 'kind' => 'person_struck']);

        $builder = new SitemapBuilder(1);

        $this->assertNotContains('https://h.org/sitemap-accidents-2.xml', $this->locs($builder->index('https://h.org')));
        $this->assertNull($builder->family('https://h.org', 'accidents', 2));
    }

    public function test_the_page_count_rounds_up_for_every_family(): void
    {
        $this->stations(4);
        for ($i = 1; $i <= 4; $i++) {
            Line::factory()->create(['slug' => "ligne-$i"]);
            Material::factory()->create(['slug' => "serie-$i"]);
        }
        $builder = new SitemapBuilder(3);

        $index = $this->locs($builder->index('https://h.org'));
        foreach (['stations', 'lines', 'materials'] as $family) {
            $this->assertContains("https://h.org/sitemap-{$family}-2.xml", $index);
            $this->assertNotContains("https://h.org/sitemap-{$family}-3.xml", $index);
            $this->assertCount(1, $this->locs($builder->family('https://h.org', $family, 2) ?? ''));
        }
        $this->assertNotContains('https://h.org/sitemap-accidents-2.xml', $index);
    }
}
