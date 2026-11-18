<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="theme-color" content="#ff6500" />
        <meta name="description" content="VFF - La plus grande encyclopédie collaborative du rail français." />

        <title inertia>{{ config('app.name', 'VFF') }}</title>

        <link rel="icon" href="{{ asset('favicon.ico') }}" />

        @vite(['resources/css/general.css', 'resources/js/app.js'])
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
