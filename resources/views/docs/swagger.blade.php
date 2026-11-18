{{--
    Documentation REST alternative (/docs/swagger) : Swagger UI, sur la même
    spécification OpenAPI que Scalar (/docs/api.json, générée par Scramble).
    Servie par Vite (paquet swagger-ui-dist), sans CDN tiers.
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('docs._head')
    <title>{{ __('docs.title_swagger') }}</title>
    @vite(['resources/js/docs/swagger.js'])
</head>
<body class="vff-docs vff-docs--swagger">
    @include('docs._bar', ['current' => 'swagger'])
    <noscript><p class="vff-docs-noscript">{{ __('docs.noscript') }} <a href="/docs/api.json">/docs/api.json</a></p></noscript>
    <main id="vff-docs-app" data-spec-url="/docs/api.json">
        <p class="vff-docs-loading">{{ __('docs.loading') }}</p>
    </main>
</body>
</html>
