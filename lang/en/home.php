<?php

return [
    'meta_title' => 'VFF - Railways of France',
    'meta_description' => 'A collaborative encyclopaedia of French railways from 1830 to the present day: lines, stations, a map of the network for every decade and sourced archives.',

    // Introduction (original design: logo, title, text, two buttons).
    'whoarewe' => [
        'title' => 'VFF - Where rails meet',
        'paragraph' => 'Created by two railway enthusiasts, VFF is a collaborative encyclopaedia of French railways from 1830 to the present day. It describes every line, open or closed: its route, its stations, its dates and the source of each piece of information. You can browse it, suggest corrections and reuse its data through its public API.',
        'cta_history' => 'Our story',
        'cta_donate' => 'Donate',
    ],

    // Campaign notices (lines supported by VFF). Title, message and button come from the database.
    'advocacy' => [
        'label' => 'Lines supported by VFF',
        'kind_threatened' => 'Threatened line',
        'kind_reopening' => 'Reopening campaign',
        'threatened' => 'Threatened line',
        'reopening' => 'Reopen the line',
        'see_line' => 'See the line page',
        'act' => 'Take action',
        'default_title_threatened' => 'Line threatened with closure',
        'default_title_reopening' => 'For the reopening of the line',
        'default_message_threatened' => 'We support keeping this line open.',
        'default_message_reopening' => 'We support the reopening of this line.',
        'default_cta' => 'Take action for the line',
    ],

    // Orange band: three ways into the encyclopaedia (original design).
    'maps' => [
        'box1_title' => 'Time-travel map',
        'box1_text' => 'The French rail network decade by decade, from 1830 to the present day: the lines open, closed or lifted in each period.',
        'box1_link' => 'Open the map',
        'box2_title' => 'Detailed pages',
        'box2_text' => 'For each line: its route, its stations, a dated chronology, archive photographs and the source of each piece of information.',
        'box2_link' => 'Browse the lines',
        'box3_title' => 'Open data',
        'box3_text' => "VFF's data is available through a public API (REST and GraphQL), for your research and projects, under the licence of each source.",
        'box3_link' => 'Read the API documentation',
    ],

    'genesis' => [
        'title' => 'Before the rails fade away',
        'p1' => 'Around 1930, France had nearly 60,000 kilometres of railway lines, secondary networks included. Fewer than 30,000 are still in service. The others were closed, lifted, sometimes wiped from the landscape, and their history is scattered across archives, old maps and memories.',
        'p2' => 'VFF brings this memory together: recording every line, dating it, placing it on the map, citing its sources, and sharing it so that others can add to it.',
        'signature' => 'The founders',
    ],

    // Network profile: length of open lines in each decade (database figures).
    'network' => [
        'title' => 'The network, decade by decade',
        'intro' => 'From 1830 to 1930, the French rail network kept growing. It then contracted and has since lost half of its length.',
        'chart_caption' => 'Length of lines open on 31 December of each decade, in kilometres, based on the opening and closure dates recorded in VFF. Choose a decade to open its map.',
        'today' => 'Today',
        'bar' => ':year: :km, :lines. Open the map for this decade.',
        'bar_today' => 'Today: :km, :lines. Open the map of the current network.',
        'km' => ":value\u{00A0}km",
        'lines_one' => ":value\u{00A0}line",
        'lines_other' => ":value\u{00A0}lines",
        'table_toggle' => 'Show the figures by decade',
        'table_caption' => 'Length of open lines and number of lines, by decade',
        'col_decade' => 'Decade',
        'col_length' => 'Length',
        'col_lines' => 'Open lines',
        'milestones_title' => 'Milestones',
        'm1827' => 'Saint-Étienne - Andrézieux, the first French railway line, carries coal from the Loire basin in horse-drawn wagons.',
        'm1842' => 'The law of 11 June 1842 lays out a star-shaped network around Paris, built with the State and operated by companies.',
        'm1879' => 'The Freycinet plan launches thousands of kilometres of lines to serve every sub-prefecture.',
        'm1930' => 'The network reaches its greatest extent: nearly 60,000 km, secondary networks included.',
        'm1938' => 'The major railway companies are merged into the SNCF, created on 1 January 1938.',
        'm1981' => 'The Paris - Lyon high-speed line opens its first section in September 1981.',
        'cta' => 'Open the map',
    ],

    // Gallery: real Wikimedia Commons images linked to the lines.
    'archives' => [
        'title' => 'The railway in pictures',
        'intro' => 'Photographs and postcards published on Wikimedia Commons and linked to the line pages, with their author and licence.',
        'decade' => 'Around :year',
        'author_unknown' => 'Unknown author',
        'source' => 'Wikimedia Commons',
        'cta' => 'Browse the lines',
    ],

    // Contents of the encyclopaedia, with the real counts from the database.
    'contents' => [
        'title' => 'In the encyclopaedia',
        'lines_title' => 'Lines',
        'lines_text' => 'The route, stations, chronology and sources of every line, open or closed.',
        'lines_count_one' => ":value\u{00A0}page",
        'lines_count_other' => ":value\u{00A0}pages",
        'stations_title' => 'Stations',
        'stations_text' => 'Stations and halts, with their dates and the lines that serve them.',
        'stations_count_one' => ":value\u{00A0}page",
        'stations_count_other' => ":value\u{00A0}pages",
        'materials_title' => 'Rolling stock',
        'materials_text' => 'Locomotives, multiple units and carriages, with the lines they ran on.',
        'materials_count_one' => ":value\u{00A0}page",
        'materials_count_other' => ":value\u{00A0}pages",
        'map_title' => 'Historical map',
        'map_text' => 'The network as it was in every decade, from 1830 to the present day.',
        'map_count_one' => ":value\u{00A0}decade",
        'map_count_other' => ":value\u{00A0}decades",
        'media_title' => 'Archive images',
        'media_text' => 'The photographs and documents linked to the lines, with their author and licence.',
        'media_count_one' => ":value\u{00A0}image",
        'media_count_other' => ":value\u{00A0}images",
        'api_title' => 'API and data',
        'api_text' => "VFF's data in REST, GraphQL and GeoJSON, to reuse under the licence of each source.",
    ],

    // Closing invitation (a single "cta" band per page).
    'contribute' => [
        'title' => 'Take part in the encyclopaedia',
        'subtitle' => 'VFF is built with its readers. Two validators review each proposal before it is published, and every piece of information cites its source.',
        'data_title' => 'Document a line',
        'data_text' => 'Correct a date or complete a route in the editor, stating your source.',
        'media_title' => 'Share archives',
        'media_text' => 'Do you have old photographs, plans or documents? Write to us to publish them with their author and licence.',
        'code_title' => 'Improve the code',
        'code_text' => "VFF's source code is published on GitHub under the MIT licence: report a problem, suggest a translation or a fix.",
        'cta' => 'How to contribute',
        'donate' => 'Donate',
        'discord' => 'Discord',
        'github' => 'GitHub',
    ],
];
