<?php

return [
    'meta_title' => 'Our maps',
    'meta_description' => 'Interactive map of the French railway network, in service and vanished, era by era.',

    'hero' => [
        'eyebrow' => 'Cartography',
        'title' => "The French rail network like you've never seen it",
        'subtitle' => 'An interactive map of the French rail network, living or vanished, spanning the decades from 1990 to today.',
        'status' => 'MVP in development',
    ],

    'features' => [
        'title' => 'What the map will offer',
        'time_title' => 'Time selector',
        'time_text' => 'A slider to switch between 1990, 2000, 2010, 2020 and 2030. Vanished routes appear and disappear in real time.',
        'lines_title' => 'Detailed routes',
        'lines_text' => 'In service, single track, abandoned, lifted, razed — each status has its style, each line its page.',
        'materials_title' => 'Rolling stock',
        'materials_text' => "Click on a line, discover the locomotives, multiple units and trainsets that travelled it, with archive photos.",
        'archives_title' => 'Geolocated archives',
        'archives_text' => 'Photos, plans, documents, dated and placed on the map. A spatio-temporal journey through the heritage.',
    ],

    'tech' => [
        'title' => 'Under the hood',
        'paragraph' => "The map is built with MapLibre GL JS on an OpenStreetMap background. Our railway data is stored as LINESTRING in PostgreSQL+PostGIS and served as MVT vector tiles for optimal performance, even with thousands of polylines stacked across multiple eras. An optional OpenRailwayMap overlay shows detailed infrastructure (signals, electrification, speeds).",
    ],

    'progress' => [
        'title' => 'Progress',
        'phase' => 'Phase 2 — Interactive map (MVP)',
        'description' => 'See the <a href=":roadmap_url" target="_blank" rel="noopener">full roadmap</a> for the detail of upcoming features.',
    ],

    'cta' => [
        'title' => 'Get notified at launch',
        'text' => 'Join our Discord to be informed as soon as the interactive map opens.',
        'discord' => 'Join Discord',
    ],
];
