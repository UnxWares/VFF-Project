{{--
    Documentation REST alternative (/docs/swagger) : Swagger UI, sur la même
    spécification OpenAPI que Scalar (/docs/api.json, générée par Scramble).
    Servie par Vite (paquet swagger-ui-dist), sans CDN tiers.
    Langue (RGAA 8.7) : l'interface de Swagger UI n'existe qu'en anglais, la page est donc déclarée
    `lang="en"` ; la barre du site et le message sans JavaScript reprennent la langue du visiteur, les
    textes de la spécification (français) sont balisés par resources/js/docs/language.js.
--}}
<!doctype html>
<html lang="en">
<head>
    @include('docs._head', ['title' => __('docs.title_swagger'), 'description' => __('docs.desc_swagger')])
    @vite(['resources/js/docs/swagger.js'])
</head>
<body class="vff-docs vff-docs--swagger">
    @include('docs._bar', ['current' => 'swagger', 'lang' => app()->getLocale()])
    <noscript><p class="vff-docs-noscript" lang="{{ app()->getLocale() }}">{{ __('docs.noscript') }} <a href="/docs/api.json">/docs/api.json</a></p></noscript>
    <main id="vff-docs-app" tabindex="-1" data-spec-url="/docs/api.json">
        <p class="vff-docs-loading" lang="{{ app()->getLocale() }}">{{ __('docs.loading') }}</p>
    </main>
</body>
</html>
