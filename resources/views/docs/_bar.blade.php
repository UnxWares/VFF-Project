{{-- Barre de navigation commune aux documentations de l'API. $current : scalar | swagger. --}}
<header class="vff-docs-bar">
    <a class="vff-docs-brand" href="{{ route('api.docs') }}" title="{{ __('docs.back') }}">
        <img src="{{ Vite::asset('resources/images/vff.png') }}" alt="VFF" width="28" height="28" />
        <span>{{ __('docs.brand') }}</span>
    </a>
    <nav class="vff-docs-nav" aria-label="{{ __('docs.nav_label') }}">
        <a href="/docs/api" @if ($current === 'scalar') aria-current="page" @endif>{{ __('docs.rest_scalar') }}</a>
        <a href="/docs/swagger" @if ($current === 'swagger') aria-current="page" @endif>{{ __('docs.rest_swagger') }}</a>
        <a href="/docs/api.json">{{ __('docs.openapi_json') }}</a>
        <a href="/docs/graphql">{{ __('docs.graphql_docs') }}</a>
        <a href="/graphiql">{{ __('docs.graphiql') }}</a>
    </nav>
    <button type="button" class="vff-docs-theme" data-theme-toggle aria-label="{{ __('docs.theme_toggle') }}" title="{{ __('docs.theme_toggle') }}">
        <svg class="sun" viewBox="0 0 16 16" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M8 11a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm0 1a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM8 0a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 0zm0 13a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 13zm8-5a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2a.5.5 0 0 1 .5.5zM3 8a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2A.5.5 0 0 1 3 8zm10.66-5.66a.5.5 0 0 1 0 .7l-1.41 1.42a.5.5 0 1 1-.71-.71l1.41-1.41a.5.5 0 0 1 .71 0zM4.46 11.54a.5.5 0 0 1 0 .71l-1.41 1.41a.5.5 0 0 1-.71-.7l1.41-1.42a.5.5 0 0 1 .71 0zm9.2 2.12a.5.5 0 0 1-.7 0l-1.42-1.41a.5.5 0 1 1 .71-.71l1.41 1.41a.5.5 0 0 1 0 .71zM4.46 4.46a.5.5 0 0 1-.71 0L2.34 3.05a.5.5 0 1 1 .7-.71l1.42 1.41a.5.5 0 0 1 0 .71z"/></svg>
        <svg class="moon" viewBox="0 0 16 16" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M6 .278a.77.77 0 0 1 .08.858 7.2 7.2 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277q.792-.001 1.533-.16a.79.79 0 0 1 .81.316.73.73 0 0 1-.031.893A8.35 8.35 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71 0 4.266 2.114 1.312 5.124.06A.75.75 0 0 1 6 .278z"/></svg>
    </button>
</header>
