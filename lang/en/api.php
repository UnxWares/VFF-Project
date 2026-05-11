<?php

return [
    'meta_title' => 'API access',
    'meta_description' => 'Public API documentation of VFF (coming).',

    'hero' => [
        'eyebrow' => 'Public API',
        'title' => 'Build your own tools on our data',
        'subtitle' => "An open, documented, free REST + GeoJSON API — to build applications, visualizations and research around the French rail network.",
        'status' => 'Phase 6 — Spec in progress',
    ],

    'endpoints' => [
        'title' => 'Planned endpoints',
        'lines' => 'List and details of lines — by era, status, region.',
        'segments' => 'Segments as GeoJSON or MVT — to embed in a third-party map.',
        'stations' => 'Stations — current and historical, with opening/closing dates.',
        'materials' => 'Rolling stock — pages, operating periods, lines travelled.',
        'events' => 'Historical events — inaugurations, closures, dismantling.',
        'media' => 'Media — geolocated and dated archive photos.',
    ],

    'format' => [
        'title' => 'Supported formats',
        'json' => 'JSON (classical REST)',
        'geojson' => 'GeoJSON (cartographic layers)',
        'mvt' => 'MVT — Mapbox Vector Tiles (for MapLibre / Leaflet)',
    ],

    'example' => [
        'title' => 'Preview example',
        'description' => 'Fetch all lines in service in 2000 (to get a feel for the format):',
    ],

    'cta' => [
        'title' => 'Stay tuned',
        'text' => "The API is coming in phase 6 of the roadmap. Follow progress on Discord or GitHub.",
        'discord' => 'Discord',
        'github' => 'See the code',
    ],
];
