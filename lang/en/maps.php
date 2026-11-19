<?php

return [
    'meta_title' => 'Map of French railways',
    'meta_description' => 'Interactive map of the French railway network, in service and vanished, era by era.',

    'hero' => [
        'title' => 'The map of French railways, from 1830 to today',
        'subtitle' => 'Lines in service, closed or lifted, decade by decade. Click a route to open its page.',
    ],

    // Heading (read aloud, not shown) of the map area.
    'map_title' => 'Network map',
    // Shortcuts next to the title, to the sections below the map.
    'jumps_label' => 'On this page',
    'era_selector' => 'Era',

    'search' => [
        'label' => 'Search for a line or station',
        'placeholder' => 'Search for a line or station…',
        'lines' => 'Lines',
        'stations' => 'Stations',
        'materials' => 'Rolling stock',
        'loading' => 'Searching…',
        'results_one' => ':count result',
        'results_other' => ':count results',
        'no_results' => 'No line or station matches “:query”.',
        'error' => 'Search is unavailable at the moment. Please try again shortly.',
        'clear' => 'Clear search',
    ],

    'overlay' => [
        'label' => 'Overlay',
        'none' => 'None',
        'orm_standard' => 'OpenRailwayMap - Infrastructure',
        'orm_maxspeed' => 'OpenRailwayMap - Max speeds',
        'orm_signals' => 'OpenRailwayMap - Signals',
        'orm_electrification' => 'OpenRailwayMap - Electrification',
        'orm_gauge' => 'OpenRailwayMap - Gauge',
    ],

    'legend' => [
        'heritage_train' => 'Run by an association: heritage railway',
        'heritage_velorail' => 'Run by an association: rail bikes',
        'title' => 'Network in :year',
        'title_current' => 'Current network',
        'threatened' => 'Threatened line (red halo)',
        'reopening' => 'Reopening campaign supported by VFF (teal halo)',
        'hint' => 'Click an entry to show or hide it.',
        'collapse' => 'Collapse legend',
        'expand' => 'Legend',
        'expand_label' => 'Expand legend',
        // Stations (colour = status in the selected decade, see railStyle.js STATION_STYLES).
        'stations' => 'Stations at that time',
        'stations_hint' => 'Shown when you zoom in.',
        'station_active' => 'Open to passengers (and freight)',
        'station_tourist' => 'Heritage railway',
        'station_freight' => 'Freight only',
        'station_uncertain' => 'Uncertain (no known track at that time)',
        'station_closed' => 'Closed',
    ],

    // Type of volunteer-run operation (line_segments.heritage_kind).
    'heritage_kind' => [
        'tourist_train' => 'Heritage railway',
        'velorail' => 'Rail bikes (vélorail)',
        'museum' => 'Operating railway museum',
    ],

    // Colour of IN-SERVICE sections = traffic type in the selected decade.
    'traffic' => [
        'title' => 'Network in service',
        'lgv' => 'High-speed line (LGV)',
        'passenger' => 'High-speed (passenger)',
        'mixed' => 'Conventional network: passenger and freight',
        'freight' => 'Conventional network: freight only',
        'unknown' => 'Conventional network: traffic unknown',
    ],

    // Stroke of each section according to its status in the selected decade.
    'status' => [
        'title' => 'Section status',
        'in_service' => 'In service',
        'single_track' => 'In service (single track)',
        'service_track' => 'Service / port track',
        'construction' => 'Under construction',
        'abandoned' => 'Closed (track in place)',
        'dismantled' => 'Track lifted',
        'razed' => 'Vanished (trackbed erased)',
        'gone' => 'Lifted / vanished',
    ],

    // Map display mode (legend): line colour by traffic, electrification or
    // gauge in the selected decade (vff:enrich-era-segments).
    'mode' => [
        'label' => 'Colour routes by',
        'traffic' => 'Traffic',
        'electrification' => 'Electrification',
        'gauge' => 'Gauge',
    ],

    'electrification' => [
        'title' => 'Electrification at that time',
        'ac25' => '25 kV 50 Hz (AC)',
        'dc1500' => '1.5 kV DC',
        'dc3000' => '3 kV DC',
        'ac15' => '15 kV 16.7 Hz (AC)',
        'low' => '1,000 V or less (third rail)',
        'other' => 'Other voltage (Midi 12 kV…)',
        'unknown_voltage' => 'Electrified, voltage unknown',
        'unknown_date' => 'Electrified today, date unknown',
        'none' => 'Not electrified',
        'since' => 'electrified since :year',
    ],

    'gauge' => [
        'title' => 'Track gauge',
        'standard' => 'Standard gauge (1,435 mm)',
        'metre' => 'Metre gauge (1,000 mm)',
        'narrow' => 'Narrow gauge (600, 700, 750 mm…)',
        'broad' => 'Broad gauge',
        'unknown' => 'Gauge unknown',
    ],

    'networks' => [
        'principal' => 'Main network',
        'secondaire' => 'Secondary network',
        'militaire' => 'Military network',
        'particulier' => 'Private network',
    ],

    'era' => [
        'no_data' => 'No route has been reconstructed for :year yet: this decade is still being mapped.',
        'no_data_short' => 'No data yet',
    ],

    // Timeline. The "current" decade (2030) shows today's network: it is shown as "Today",
    // never as a future year.
    'timeline' => [
        'aria_label' => 'Era timeline',
        'group_aria' => 'Period',
        'slider' => 'Decade shown',
        'era' => 'Decade',
        'today' => 'Today',
        'today_caption' => 'The network today',
        'since' => 'Since :year',
        'value_today' => 'Today, current network',
        'value_empty' => ':year, no data yet',
        'play' => 'Play through the decades',
        'pause' => 'Stop playing',
    ],

    // Line sheet on click (LineDrawer) and popup of the embedded map.
    'drawer' => [
        'fallback_line_name' => 'Segment',
        'view_full_sheet' => 'View the line page',
        'view_station' => 'View the station page',
        'suggest_edit' => 'Suggest an edit',
        'close' => 'Close the panel',
        'status' => 'Status',
        'status_at' => 'Status in :year',
        'status_today' => 'Current status',
        'max_speed' => 'Maximum speed',
        'electrification' => 'Electrification',
        'traffic' => 'Traffic',
        'network' => 'Network',
        'heritage' => 'Run by an association',
        'today' => 'today',
    ],

    'embed' => [
        'button' => 'Embed',
        'title' => 'Embed the map on your site',
        'intro' => 'Adjust the map, then copy the code and paste it into your page.',
        'width' => 'Width',
        'width_hint' => 'In pixels or as a percentage, for example 100% or 800.',
        'height' => 'Height (px)',
        'theme' => 'Theme',
        'theme_auto' => 'Automatic (visitor setting)',
        'theme_light' => 'Light',
        'theme_dark' => 'Dark',
        'mode' => 'Route colours',
        'options' => 'Elements shown',
        'show_timeline' => 'Era timeline',
        'show_legend' => 'Legend',
        'show_vanished' => 'Closed and lifted lines',
        'show_attribution' => 'Link to VFF (recommended)',
        'snippet_label' => 'Code to paste into your page',
        'copy' => 'Copy the code',
        'copied' => 'Code copied',
        'copy_failed' => 'Automatic copying is not possible: the code is selected, so copy it with Ctrl+C (Cmd+C on a Mac).',
        'preview' => 'Preview',
        'iframe_title' => 'Interactive map of the French railway network - VFF',
        'preview_title' => 'Preview of the embedded map',
        'credit' => 'Map: VFF',
    ],

    // "Reading the map" section, below the map.
    'read' => [
        'title' => 'Reading the map',
        'era_term' => 'Era',
        'era_text' => 'The timeline below the map selects the decade, from 1830 to today. The play button steps through the decades one by one.',
        'color_term' => 'Colour',
        'color_text' => 'On a line in service, the colour shows what runs on it: purple for high speed, orange for the conventional network open to passengers and freight, blue for freight only, green for a line run by an association. The Electrification and Gauge modes of the legend colour the same routes by voltage or track gauge.',
        'line_term' => 'Stroke',
        'line_text' => 'Solid line: in service. Red dashes: closed line with the track still in place. Grey dots: track lifted or trackbed gone.',
        'sheet_term' => 'Line pages',
        'sheet_text' => 'Click a route to see its status in the selected era and open the line page. Stations appear when you zoom in.',
        'route_term' => 'Journey',
        'route_text' => 'Enter two stations and a year: the map draws the journey on the network in service at that time and lists the lines used.',
        'layers_term' => 'Overlays',
        'layers_text' => 'The OpenRailwayMap overlay adds today\'s infrastructure: signals, speeds, electrification. The clock button, at the top right of the map, shows old IGN maps.',
    ],

    'tech' => [
        'title' => 'How the map is made',
        'paragraph' => 'The map is drawn with MapLibre GL JS on an OpenStreetMap background. Routes are stored in a PostgreSQL database with PostGIS, then sent to the browser as vector tiles: the map stays smooth with thousands of line sections spread over two centuries.',
        'credits' => 'Software and data sources used by VFF',
    ],

    'cta' => [
        'title' => 'Is a line missing or a route wrong?',
        'text' => 'Suggest a correction from the line page, or retrace it on the map. Every proposal is reviewed before it is applied.',
        'contribute' => 'Suggest a correction',
        'discord' => 'Join the Discord',
    ],

    // Map buttons (MapLibre): tooltips and names read by screen readers.
    'controls' => [
        'map' => 'Interactive railway network map',
        'zoom_in' => 'Zoom in',
        'zoom_out' => 'Zoom out',
        'reset_bearing' => 'Drag to rotate the map, click to reset to north',
        'fullscreen_enter' => 'Show full screen',
        'fullscreen_exit' => 'Exit full screen',
        'attribution' => 'Show or hide map credits',
        'map_feedback' => 'Report an error in the base map',
        'popup_close' => 'Close popup',
    ],
];
