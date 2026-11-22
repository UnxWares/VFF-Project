<?php

namespace App\Http\Controllers;

use App\Services\Seo\SitemapBuilder;
use App\Support\Seo\SiteOrigin;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Index `/sitemap.xml` et plans par famille `/sitemap-{famille}-{page}.xml` (SitemapBuilder). */
class SitemapController extends Controller
{
    public function index(Request $request, SitemapBuilder $sitemaps): Response
    {
        return $this->xml($sitemaps->index(SiteOrigin::get()));
    }

    public function family(Request $request, SitemapBuilder $sitemaps, string $family, int $page): Response
    {
        $body = $sitemaps->family(SiteOrigin::get(), $family, $page);
        abort_if($body === null, 404);

        return $this->xml($body);
    }

    private function xml(string $body): Response
    {
        return response($body, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
