{{-- En-tête commun des pages de documentation REST (Scalar, Swagger UI). --}}
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="theme-color" content="#ff6500" />
<meta name="description" content="{{ __('docs.title_scalar') }}" />
<link rel="icon" href="{{ asset('favicon.ico') }}" />
<script>
    // Thème appliqué avant le premier rendu (pas de flash) : cookie `theme` du site,
    // sinon préférence du système.
    (function () {
        var m = document.cookie.match(/(?:^|; )theme=([^;]*)/);
        var dark = m ? m[1] === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.dataset.theme = dark ? 'dark' : 'light';
    })();
</script>
