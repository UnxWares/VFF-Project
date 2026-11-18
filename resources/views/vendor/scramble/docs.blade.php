{{--
    Documentation REST principale (/docs/api) : Scalar, sur la spécification
    OpenAPI générée par Scramble (/docs/api.json). Remplace la vue Stoplight
    Elements fournie par dedoc/scramble. Scalar est servi par Vite (paquet
    @scalar/api-reference), sans CDN tiers, sans télémétrie ni polices externes.
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('docs._head', ['title' => __('docs.title_scalar'), 'description' => __('docs.desc_scalar')])
    @vite(['resources/js/docs/scalar.js'])
</head>
<body class="vff-docs vff-docs--scalar">
    @include('docs._bar', ['current' => 'scalar'])
    <noscript><p class="vff-docs-noscript">{{ __('docs.noscript') }} <a href="/docs/api.json">/docs/api.json</a></p></noscript>
    <main id="vff-docs-app" tabindex="-1"
          data-spec-url="/docs/api.json"
          data-locale="{{ app()->getLocale() }}"
          data-title="{{ __('docs.title_scalar') }}">
        <p class="vff-docs-loading">{{ __('docs.loading') }}</p>
    </main>
</body>
</html>
