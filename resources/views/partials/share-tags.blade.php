{{--
    Aperçus de partage écrits par le serveur (Facebook, Mastodon, Discord et beaucoup de robots ne
    lisent pas le JavaScript) : description, adresse canonique, Open Graph, Twitter et JSON-LD de la
    page, d'après App\Support\Seo\PageMetadata. L'attribut data-vff-seo les désigne au navigateur :
    Components/SeoHead.svelte les reprend telles quelles tant que la page est celle du serveur et les
    retire dès qu'on navigue ailleurs (lib/serverSeo.js), donc jamais de doublon dans la page vivante.
--}}
<meta data-vff-seo name="description" content="{{ $seo->metaDescription }}" />
<link data-vff-seo rel="canonical" href="{{ $seo->url }}" />
<meta data-vff-seo property="og:site_name" content="{{ $seo->siteName }}" />
<meta data-vff-seo property="og:type" content="{{ $seo->type }}" />
<meta data-vff-seo property="og:title" content="{{ $seo->title }}" />
<meta data-vff-seo property="og:description" content="{{ $seo->description }}" />
<meta data-vff-seo name="twitter:description" content="{{ $seo->description }}" />
<meta data-vff-seo property="og:url" content="{{ $seo->url }}" />
<meta data-vff-seo property="og:locale" content="{{ $seo->locale }}" />
<meta data-vff-seo name="twitter:card" content="{{ $seo->image ? 'summary_large_image' : 'summary' }}" />
<meta data-vff-seo name="twitter:title" content="{{ $seo->title }}" />
@if ($seo->image)
    <meta data-vff-seo property="og:image" content="{{ $seo->image['url'] }}" />
    <meta data-vff-seo name="twitter:image" content="{{ $seo->image['url'] }}" />
    @if ($seo->image['alt'])
        <meta data-vff-seo property="og:image:alt" content="{{ $seo->image['alt'] }}" />
        <meta data-vff-seo name="twitter:image:alt" content="{{ $seo->image['alt'] }}" />
    @endif
@endif
@foreach ($seo->jsonLd as $json)
    <script data-vff-seo type="application/ld+json">{!! $json !!}</script>
@endforeach
