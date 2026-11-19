<?php

namespace App\Mcp\Resources;

use App\Mcp\Support\Attribution;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Name('licences')]
#[Title('Licences et attributions')]
#[Description('Conditions de réutilisation des données de VFF (base sous ODbL 1.0) et licence de chaque source, avec la mention à reproduire, en Markdown (français ; outil sources_and_licences pour l\'anglais ou l\'allemand).')]
#[Uri('vff://licences')]
#[MimeType('text/markdown')]
class LicencesResource extends Resource
{
    public function handle(Request $request, Attribution $attribution): Response
    {
        $lang = 'fr';
        $out = ['# '.trans('mcp.markdown.licences_title', [], $lang), '', (string) trans('mcp.attribution.terms', [], $lang), ''];
        foreach ($attribution->providerKeys() as $key) {
            $p = $attribution->publicProvider($key, $lang);
            if ($p === null) {
                continue;
            }
            $out[] = '## '.$p['name'];
            $out[] = '- '.trans('mcp.markdown.licence', [], $lang).' : '.$p['licence'];
            $out[] = '- '.trans('mcp.markdown.mention', [], $lang).' : '.$p['mention'];
            foreach ($p['proof'] as $url) {
                $out[] = '- '.$url;
            }
            $out[] = '';
        }

        return Response::text(implode("\n", $out));
    }
}
