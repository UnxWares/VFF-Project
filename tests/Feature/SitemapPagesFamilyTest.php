<?php

namespace Tests\Feature;

use App\Services\Seo\SitemapBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Famille `pages` du plan du site : découpée en pages de la taille donnée, comme les fiches. Avant, chaque page
 * annoncée par l'index redonnait les 32 pages éditoriales (doublons dès que la taille de page passe sous 32).
 */
class SitemapPagesFamilyTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> */
    private function locs(?string $xml): array
    {
        preg_match_all('#<loc>([^<]+)</loc>#', (string) $xml, $m);

        return $m[1];
    }

    public function test_each_page_of_the_pages_family_holds_its_own_slice_without_duplicates(): void
    {
        $all = $this->locs((new SitemapBuilder)->family('https://h.org', 'pages', 1));
        $this->assertCount(32, $all);
        $this->assertNull((new SitemapBuilder)->family('https://h.org', 'pages', 2));

        // Le cache ne distingue pas la taille de page (une seule en production) : vidé avant l'autre taille.
        Cache::flush();
        $small = new SitemapBuilder(5);
        $pages = array_map(fn (int $p): array => $this->locs($small->family('https://h.org', 'pages', $p)), [1, 2, 3, 4, 5, 6, 7]);

        $this->assertSame([5, 5, 5, 5, 5, 5, 2], array_map('count', $pages));
        $this->assertSame($all, array_merge(...$pages));
    }
}
