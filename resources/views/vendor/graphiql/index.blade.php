{{--
    Console GraphiQL (/graphiql), publiée depuis mll-lab/laravel-graphiql aux couleurs de VFF :
    barre commune des documentations, thème du site (clair, sombre), requête d'exemple VFF, aucun CDN.
    Les fichiers de GraphiQL et de React sont servis depuis public/vendor/graphiql
    (`bin/php artisan graphiql:download-assets` les télécharge une fois ; ils sont ensuite versionnés).
    L'interface de GraphiQL n'existe qu'en anglais : sa zone porte lang="en" (ses fenêtres, ajoutées au <body>,
    sont marquées par resources/js/docs/graphiql.js).
--}}
@php
    $asset = fn (string $path) => asset("vendor/graphiql/$path");
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('docs._head', ['title' => __('docs.title_graphiql'), 'description' => __('docs.desc_graphiql')])
    <link rel="stylesheet" href="{{ $asset('graphiql.css') }}" />
    <link rel="stylesheet" href="{{ $asset('graphiql-plugin-explorer.css') }}" />
    @vite(['resources/js/docs/graphiql.js'])
    <script src="{{ $asset('react.production.min.js') }}"></script>
    <script src="{{ $asset('react-dom.production.min.js') }}"></script>
</head>
<body class="vff-docs vff-docs--graphiql">
    @include('docs._bar', ['current' => 'graphiql'])
    <noscript><p class="vff-docs-noscript">{{ __('docs.noscript') }}</p></noscript>
    <main id="vff-docs-app" tabindex="-1" lang="en">
        <div id="graphiql"><p class="vff-docs-loading">{{ __('docs.loading') }}</p></div>
    </main>

    <script src="{{ $asset('graphiql.js') }}"></script>
    <script src="{{ $asset('graphiql-plugin-explorer.js') }}"></script>
    <script type="application/json" id="graphiql-config">{!! json_encode([
        'url' => $url,
        'subscriptionUrl' => $subscriptionUrl,
        'query' => __('docs.graphiql_query'),
        'locale' => app()->getLocale(),
    ], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
</body>
</html>
