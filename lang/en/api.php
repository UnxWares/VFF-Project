<?php

return [
    'meta_title' => 'API access',
    'meta_description' => 'Public API documentation for VFF (coming soon).',

    'hero' => [
        'eyebrow' => 'Public API',
        'title' => 'Build your own tools on our data',
        'subtitle' => 'An open, documented, free REST + GeoJSON API to build applications, visualisations and research around the French rail network.',
        'status' => 'Phase 6 - Specification in progress',
    ],

    'endpoints' => [
        'title' => 'Planned endpoints',
        'lines' => 'List and details of lines, by era, status and region.',
        'segments' => 'Segments as GeoJSON or MVT, to embed in a third-party map.',
        'stations' => 'Current and historical stations, with opening and closing dates.',
        'materials' => 'Rolling stock: pages, operating periods, lines travelled.',
        'events' => 'Historical events: inaugurations, closures, track lifting.',
        'media' => 'Media: geolocated and dated archive photos.',
        'items' => [
            'lines_geojson' => 'GeoJSON FeatureCollection of every segment in a given era.',
            'stations_geojson' => 'FeatureCollection of the stations open in that era (filtered by status).',
            'tiles' => 'Mapbox vector tile (binary) for MapLibre rendering. Properties: status, traffic_type, electrified, is_heritage, voltage_v, max_speed_kmh, line_slug, line_name, line_code.',
            'search' => 'Autocomplete search across lines, stations and rolling stock. At least 2 characters.',
            'history' => 'Full history: every chronological event and the status in each era.',
            'pk' => 'Converts (lat, lng) into a kilometre point (PK) on the line with PostGIS ST_LineLocatePoint. Sub-metre precision.',
            'stops' => 'Ordered sequence of the stations of the line (GTFS stop_times equivalent): position, kilometre point, effective status in the era, is_terminal.',
            'route' => 'Rail itinerary between 2 stations (Dijkstra on the materialised graph): distance, estimated duration, stages, polyline.',
            'gtfs' => 'Full GTFS export of an era\'s network (agency, routes, stops, trips, stop_times, shapes, calendar), compatible with any standard GTFS tool.',
        ],
    ],

    'format' => [
        'title' => 'Supported formats',
        'json' => 'JSON (standard REST)',
        'geojson' => 'GeoJSON (cartographic layers)',
        'mvt' => 'MVT: Mapbox Vector Tiles (for MapLibre / Leaflet)',
    ],

    'example' => [
        'title' => 'Sneak-preview example',
        'description' => 'Fetch all lines in service in 2000 (to get a feel for the format):',
        'response' => 'Expected response:',
        'curl_lines' => 'Fetch every line in service in 2030 (GeoJSON)',
        'curl_pk' => 'Find the kilometre point (PK) of a location on the Paris - Brest line',
        'curl_search' => 'Autocomplete search (lines, stations and rolling stock)',
    ],

    'graphql' => [
        'title' => 'GraphQL API (Lighthouse)',
        'text' => 'Besides the REST API, VFF exposes a single GraphQL endpoint for complex queries: a line with its segments, events, rolling stock and stations in one request.',
        'explorer_desc' => 'Explore the schema, autocomplete, run queries',
        'endpoint_desc' => 'Single endpoint for every query',
        'example_comment' => 'Example: the Argentan - Granville line with its stations and events',
    ],

    'openapi' => [
        'title' => 'Interactive OpenAPI 3 documentation',
        'text_before' => 'The OpenAPI 3.1 specification is generated automatically from the Laravel controllers (with',
        'text_middle' => ') and displayed with',
        'text_after' => '.',
        'docs' => 'Interactive documentation',
        'docs_desc' => 'Scalar interface with live testing',
        'spec' => 'OpenAPI 3.1 specification (JSON)',
        'spec_desc' => 'To import into Postman, Insomnia…',
    ],

    'cta' => [
        'title' => 'Stay up to date',
        'text' => 'The API is coming in phase 6 of the roadmap. Follow progress on Discord or GitHub.',
        'discord' => 'Discord',
        'github' => 'See the code',
    ],
];
