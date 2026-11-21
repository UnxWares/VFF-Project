{{--
    Barre commune aux documentations de l'API (Scalar, Swagger UI, GraphiQL) : même identité que l'en-tête
    du site (logo, nom, filet orange), liens vers la page API et les cinq documentations, choix de la
    langue et du thème. Sous 1 400 px, la liste se range dans un menu (popover natif : Échap ferme et
    rend le focus au bouton). $current : api | scalar | swagger | graphql | graphiql ; $lang (facultatif) :
    langue de la barre quand la page est déclarée dans une autre (Swagger UI : `en`).
--}}
@php
    $links = [
        ['key' => 'api', 'href' => route('api.docs'), 'label' => __('docs.api_page')],
        ['key' => 'scalar', 'href' => '/docs/api', 'label' => __('docs.rest_scalar')],
        ['key' => 'swagger', 'href' => '/docs/swagger', 'label' => __('docs.rest_swagger')],
        ['key' => 'openapi', 'href' => '/docs/api.json', 'label' => __('docs.openapi_json')],
        ['key' => 'graphql', 'href' => '/docs/graphql', 'label' => __('docs.graphql_docs')],
        ['key' => 'graphiql', 'href' => '/graphiql', 'label' => __('docs.graphiql')],
    ];
    $languages = ['fr' => 'Français', 'en' => 'English', 'de' => 'Deutsch'];
@endphp
<a class="vff-docs-skip" href="#vff-docs-app"@isset($lang) lang="{{ $lang }}"@endisset>{{ __('nav.skip_to_content') }}</a>
<header class="vff-docs-bar"@isset($lang) lang="{{ $lang }}"@endisset>
    <a class="vff-docs-brand" href="/">
        <img src="{{ Vite::asset('resources/images/vff-320.webp') }}" alt="" width="32" height="32" />
        <span class="long">{{ __('common.app_full') }}</span>
        {{-- Un seul des deux noms est affiché selon la largeur (docs.css) : l'autre, en display: none, sort
             aussi de l'arbre d'accessibilité, et le lien garde un nom (RGAA 6.2) à 320 px. --}}
        <span class="short">VFF</span>
    </a>
    <nav class="vff-docs-nav" aria-label="{{ __('docs.nav_label') }}">
        <button type="button" class="vff-docs-menu-button" popovertarget="vff-docs-menu" aria-controls="vff-docs-menu">
            <svg viewBox="0 0 16 16" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5z"/></svg>
            <span>{{ __('docs.menu') }}</span>
        </button>
        <ul id="vff-docs-menu" class="vff-docs-menu" popover>
            @foreach ($links as $link)
                <li>
                    <a href="{{ $link['href'] }}" @if ($current === $link['key']) aria-current="page" @endif>{{ $link['label'] }}</a>
                </li>
            @endforeach
            <li class="langs">
                <span class="vff-docs-lang-label">{{ __('docs.lang_switch') }}</span>
                @foreach ($languages as $code => $name)
                    <a href="/locale/{{ $code }}" lang="{{ $code }}" hreflang="{{ $code }}" aria-label="{{ $name }}" title="{{ $name }}" @if (app()->getLocale() === $code) aria-current="true" @endif>{{ strtoupper($code) }}</a>
                @endforeach
            </li>
        </ul>
    </nav>
    <button type="button" class="vff-docs-theme" data-theme-toggle aria-label="{{ __('docs.theme_toggle') }}" title="{{ __('docs.theme_toggle') }}">
        <svg class="sun" viewBox="0 0 16 16" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M8 11a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm0 1a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM8 0a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 0zm0 13a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 13zm8-5a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2a.5.5 0 0 1 .5.5zM3 8a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2A.5.5 0 0 1 3 8zm10.66-5.66a.5.5 0 0 1 0 .7l-1.41 1.42a.5.5 0 1 1-.71-.71l1.41-1.41a.5.5 0 0 1 .71 0zM4.46 11.54a.5.5 0 0 1 0 .71l-1.41 1.41a.5.5 0 0 1-.71-.7l1.41-1.42a.5.5 0 0 1 .71 0zm9.2 2.12a.5.5 0 0 1-.7 0l-1.42-1.41a.5.5 0 1 1 .71-.71l1.41 1.41a.5.5 0 0 1 0 .71zM4.46 4.46a.5.5 0 0 1-.71 0L2.34 3.05a.5.5 0 1 1 .7-.71l1.42 1.41a.5.5 0 0 1 0 .71z"/></svg>
        <svg class="moon" viewBox="0 0 16 16" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M6 .278a.77.77 0 0 1 .08.858 7.2 7.2 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277q.792-.001 1.533-.16a.79.79 0 0 1 .81.316.73.73 0 0 1-.031.893A8.35 8.35 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71 0 4.266 2.114 1.312 5.124.06A.75.75 0 0 1 6 .278z"/></svg>
    </button>
</header>
