<?php

// "Open data" page (/open-data): datasets built by vff:export-open-data.
return [
    'meta_title' => 'Open data',
    'meta_description' => 'Download VFF data under the ODbL: the French railway network by decade (lines, sections, stations), dated events, accidents and rolling stock, as CSV and GeoJSON.',

    'title' => 'Open data',
    'subtitle' => 'The railway network by decade, dated events, accidents and rolling stock, to download as CSV and GeoJSON. Every value keeps its source.',

    'licence' => [
        'title' => 'Licence and attribution',
        'text' => 'The datasets are published under the Open Database License (ODbL) 1.0. You may copy, modify and reuse them, including for commercial purposes.',
        'licence_link' => 'Licence text',
        'attribution' => 'Credit "VFF - Voies Ferrées de France" with a link to vff-project.org, along with the sources listed in the LICENCE.txt file of each dataset.',
        'share_alike' => 'A derived database that you make public must be released under the same licence.',
        'api' => 'For targeted queries, the API serves the same data, up to date.',
        'api_link' => 'API access',
    ],

    'thematic' => [
        'title' => 'Thematic datasets',
        'intro' => 'All decades together, one CSV file per dataset.',
    ],
    'network' => [
        'title' => 'Network by decade',
        'intro' => 'For each decade: the lines, their sections (portions in a single state) and the stations, as CSV and GeoJSON. The 2030 decade gives the state of the network on the export day.',
        'current' => 'Today',
    ],

    'kinds' => [
        'events' => ['title' => 'Dated events', 'text' => 'Openings, closures, electrifications and other line dates, with the portion and the source.'],
        'accidents' => ['title' => 'Accidents', 'text' => 'Accidents published on VFF: date, place, kind, toll and sources.'],
        'materials' => ['title' => 'Rolling stock', 'text' => 'Rolling stock series: builder, operators, years in service and characteristics.'],
    ],

    'cols' => [
        'dataset' => 'Dataset',
        'decade' => 'Decade',
        'date' => 'Date',
        'size' => 'Size',
        'contents' => 'Contents',
        'download' => 'Download',
    ],
    'zip' => 'ZIP archive',
    'zip_label' => 'Download the ZIP archive of the :name dataset (:size)',
    'files' => 'Separate files',
    'rows_one' => ':count item',
    'rows_other' => ':count items',
    'lines_one' => ':count line',
    'lines_other' => ':count lines',
    'sections_one' => ':count section',
    'sections_other' => ':count sections',
    'stations_one' => ':count station',
    'stations_other' => ':count stations',

    'empty' => [
        'title' => 'No dataset yet',
        'text' => 'The datasets are being prepared. In the meantime, the API already serves the same data.',
    ],

    'rights' => [
        'title' => 'What the datasets leave out',
        'intro' => 'Only data whose source has a written open licence that allows commercial use is included. Texts (descriptions, summaries, quotations) are not included. Everything else is removed, or replaced by the best open value available: for example, the trace of a section is replaced by the OpenStreetMap or IGN trace when it follows the same track within 15 m.',
        'col_item' => 'Data',
        'col_replaced' => 'Replaced',
        'col_removed' => 'Removed',
        'items' => [
            'sections_geometry' => 'Section traces',
            'sections_gauge' => 'Section gauges',
            'lines' => 'Lines created from a non-open trace',
            'lines_name' => 'Line names',
            'stations_name' => 'Station names',
            'stations_geometry' => 'Station positions',
            'stations_opened_on' => 'Station opening dates',
            'stations_closed_on' => 'Station closing dates',
            'events' => 'Dated events',
            'accidents' => 'Accidents',
            'accidents_location' => 'Accident positions',
            'accidents_deaths' => 'Death tolls',
            'accidents_injuries' => 'Injury counts',
            'accidents_kind' => 'Accident kinds',
            'accidents_name' => 'Accident names',
            'accidents_sources' => 'Cited accident sources',
            'materials' => 'Rolling stock series',
            'other' => 'Field :field',
        ],
    ],

    'credits' => [
        'title' => 'Cited sources',
        'intro' => 'Providers whose data appears in at least one dataset, with their licence.',
    ],

    // Données structurées (JSON-LD) du jeu de données.
    'json_ld' => [
        'keywords' => 'railways, railway lines, stations, history, France, open data, ODbL',
    ],
];
