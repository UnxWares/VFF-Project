<?php

// "API access" page (/api). Vocabulary: "endpoint", "era", "reference" for the generated documentation.
return [
    'meta_title' => 'API access',
    'meta_description' => 'Access to the VFF API: REST endpoints, GraphQL API, OpenAPI specification and example requests. Read-only, no key or account needed.',

    'hero' => [
        'title' => 'VFF data for your own tools',
        'subtitle' => 'Lines, stations, timelines and routes of the rail network, from 1830 to today, as JSON, GeoJSON, vector tiles and GTFS. Read-only, no key or account needed.',
    ],

    'docs' => [
        'title' => 'Documentation',
        'intro' => 'All of them describe the same data: pick the interface you prefer.',
        'items' => [
            'scalar' => ['title' => 'REST reference (Scalar)', 'text' => 'The main reference, with live requests and code samples.'],
            'swagger' => ['title' => 'REST reference (Swagger UI)', 'text' => 'The same specification in a more traditional interface.'],
            'openapi' => ['title' => 'OpenAPI 3.1 specification', 'text' => 'A JSON file to import into Postman, Insomnia or a client generator.'],
            'graphql' => ['title' => 'GraphQL reference', 'text' => 'Types, queries and examples for the schema.'],
            'graphiql' => ['title' => 'GraphiQL', 'text' => 'A console to write and run GraphQL queries.'],
            'schema' => ['title' => 'GraphQL schema (SDL)', 'text' => 'The full schema, as plain text.'],
        ],
    ],

    'access' => [
        'title' => 'Access and limits',
        'readonly' => ['title' => 'Read-only', 'text' => 'Every REST request is a GET; GraphQL only offers read queries.'],
        'no_key' => ['title' => 'No key or account', 'text' => 'No sign-up: call the addresses directly.'],
        'limit' => ['title' => '300 requests per minute', 'text' => 'Limit per IP address, for REST and GraphQL alike. Beyond it, the server answers HTTP 429.'],
        'licence' => ['title' => 'Data licences', 'text' => 'Credit “VFF - Voies Ferrées de France” with a link to vff-project.org. Each source keeps its own licence: the details are in the references.'],
    ],

    'endpoints' => [
        'title' => 'REST endpoints',
        'intro' => 'Every address starts with /api/v1 and answers to GET. In the paths, {year} is a ten-year era (1830 to 2030) and {line} is a line identifier, for example rfn-420000.',
        'label' => 'REST endpoints of the VFF API',
        'items' => [
            'lines_geojson' => 'Network routes in an era, as GeoJSON (a FeatureCollection of segments).',
            'stations_geojson' => 'Stations in an era, as GeoJSON. Closed stations are included while a track still exists nearby, with their status.',
            'tiles' => 'Vector tile (MVT) for an era, for a MapLibre or OpenLayers source.',
            'gtfs' => 'The network of an era as GTFS Static (ZIP archive).',
            'search' => 'Search by name in lines, stations and rolling stock. At least 2 characters.',
            'history' => 'Timeline of a line: dated events and its state in each era.',
            'pk' => 'Kilometre point (PK) of a location on a line, from its latitude and longitude.',
            'stops' => 'Stations of a line in kilometre-point order, in a given era.',
            'signalling' => 'Kilometre-point ranges with homogeneous signalling.',
            'media' => 'Photos, postcards and plans linked to a line, with their rights.',
            'route' => 'Rail route between two stations on the network of an era: distance, stops and route geometry.',
        ],
    ],

    'example' => [
        'title' => 'REST examples',
        'intro' => 'Three requests to copy into a terminal, then the answer to the second one, as the server returns it.',
        'curl_lines' => 'Routes of today\'s network (era 2030), as GeoJSON',
        'curl_pk' => 'Kilometre point of a location on the Paris-Montparnasse - Brest line',
        'curl_search' => 'Search by name',
        'response' => 'Answer to the second request: the point is moved onto the nearest route (snap), 395 m away here.',
        'code_label' => 'Code sample',
    ],

    'graphql' => [
        'title' => 'GraphQL API',
        'text' => 'For richer queries, a single GraphQL endpoint, POST /graphql, returns a line with its segments and events in one go. Read the schema in the reference, or try your queries in GraphiQL.',
        'links' => 'GraphQL documentation',
        'example_comment' => 'A line with its events',
    ],

    'cta' => [
        'title' => 'A question, an idea?',
        'text' => 'Tell us what you are building or what is missing, on Discord, or report a problem on GitHub.',
        'discord' => 'Chat on Discord',
        'github' => 'View the code on GitHub',
    ],
];
