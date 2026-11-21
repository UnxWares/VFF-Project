@php($seo = app(\App\Support\Seo\PageMetadata::class)->forPage($page))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="color-scheme" content="light dark" />
        {{--
            Thème posé avant le premier rendu (pas de flash) : cookie `theme` (dark | light) s'il
            existe, sinon préférence du système. Repris ensuite par lib/stores/theme.svelte.js.
            Même règle que resources/views/docs/_head.blade.php.
        --}}
        <script data-theme-init nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
            (function () {
                var root = document.documentElement;
                var saved = document.cookie.match(/(?:^|; )theme=(dark|light)(?:;|$)/);
                var dark = saved
                    ? saved[1] === 'dark'
                    : !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
                root.setAttribute('data-theme', dark ? 'dark' : 'light');
                var meta = document.querySelector('meta[name="color-scheme"]');
                if (meta) meta.setAttribute('content', dark ? 'dark' : 'light');
            })();
        </script>
        {{-- Application web installable (PWA) : barre du navigateur aux couleurs du site, dans chaque thème. --}}
        <meta name="theme-color" content="#ff6500" media="(prefers-color-scheme: light)" />
        <meta name="theme-color" content="#0f1419" media="(prefers-color-scheme: dark)" />
        {{-- Pages à aperçu de partage : balises complètes (partials/share-tags) ; les autres gardent la description générale. --}}
        @if ($seo)
            @include('partials.share-tags')
        @else
            <meta name="description" content="{{ __('common.app_description') }}" />
        @endif

        <title inertia>{{ $seo?->documentTitle ?? config('app.name', 'VFF') }}</title>

        <link rel="icon" href="{{ asset('favicon.ico') }}" />
        <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/icon-192.png') }}" />
        <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}" />
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}" />
        <meta name="apple-mobile-web-app-title" content="VFF" />

        {{--
            Dictionnaire de l'interface (resources/js/lib/i18n.js, loadTranslations) demandé dès la lecture de
            l'en-tête, en même temps que les scripts : la page n'attend pas la fin de app.js pour le réclamer.
        --}}
        @if (is_string($page['props']['translations_url'] ?? null))
            <link rel="preload" href="{{ $page['props']['translations_url'] }}" as="fetch" crossorigin="anonymous" />
        @endif
        {{--
            Composant de la page (et ses dépendances, CSS compris) annoncé avec les scripts communs : sans cela,
            le navigateur ne le découvre qu'après avoir exécuté app.js (un aller-retour de plus).
        --}}
        @php($__pageEntry = 'resources/js/Pages/'.($page['component'] ?? '').'.svelte')
        @vite(['resources/css/general.css', 'resources/js/app.js', ...(is_file(base_path($__pageEntry)) ? [$__pageEntry] : [])])
        @inertiaHead
    </head>
    <body>
        {{--
            Équivalent de @inertia (inertia-laravel 3.1), mais JSON encodé avec
            JSON_HEX_TAG : le @inertia d'origine écrit json_encode($page) brut dans
            la balise <script>, et un texte saisi contenant « <!--<script> » fait
            basculer l'analyseur HTML5 (script data double escaped) : la balise
            avale la suite du document, la page ne démarre plus (cf.
            tests/Feature/Web/TraceEditorRoutesTest, tests/Support/ParsesInertiaHtml).
        --}}
        @php($__inertiaSsrResponse = app(\Inertia\Ssr\SsrState::class)->setPage($page)->dispatch())
        @if ($__inertiaSsrResponse)
            {!! $__inertiaSsrResponse->body !!}
        @else
            <script data-page="app" type="application/json">{!! json_encode($page, JSON_HEX_TAG) !!}</script><div id="app"></div>
        @endif
    </body>
</html>
