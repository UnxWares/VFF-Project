<?php

namespace App\Http\Controllers;

use App\Services\Seo\SitemapBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Index `/sitemap.xml` et plans par famille `/sitemap-{famille}-{page}.xml` (SitemapBuilder). */
class SitemapController extends Controller
{
    public function index(Request $request, SitemapBuilder $sitemaps): Response
    {
        return $this->xml($sitemaps->index($request->getSchemeAndHttpHost()));
    }

    public function family(Request $request, SitemapBuilder $sitemaps, string $family, int $page): Response
    {
        $body = $sitemaps->family($request->getSchemeAndHttpHost(), $family, $page);
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
