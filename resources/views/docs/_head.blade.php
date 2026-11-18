{{--
    En-tête commun des pages de documentation (Scalar, Swagger UI, GraphiQL).
    $title : titre de l'onglet ; $description : description pour les moteurs de recherche.
--}}
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="color-scheme" content="light dark" />
<meta name="theme-color" content="#ff6500" />
<meta name="description" content="{{ $description }}" />
<title>{{ $title }}</title>
<link rel="icon" href="{{ asset('favicon.ico') }}" />
{{-- Thème posé avant le premier rendu (pas de flash) : cookie `theme` du site (dark | light) s'il existe,
     sinon préférence du système. Même règle que resources/views/app.blade.php. --}}
<script>
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
