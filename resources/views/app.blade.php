<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="theme-color" content="#ff6500" />
        <meta name="description" content="VFF — La plus grande encyclopédie collaborative du rail français." />

        <title inertia>{{ config('app.name', 'VFF') }}</title>

        <link rel="icon" href="{{ asset('favicon.ico') }}" />

        @vite(['resources/css/general.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
