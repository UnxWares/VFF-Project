<?php

// Route book (/routebook): page texts. The data comes from GET /api/v1/routebook.
return [
    'meta_title' => 'Route book',
    'meta_description' => 'Today’s route between two stations, line by line: kilometre points, track layout, speeds and gradients in the running direction, with the source of each piece of data.',
    'hero_title' => 'Route book',
    'hero_subtitle' => 'Choose two stations: the lines crossed follow one another in the running direction, with their kilometre points, their tracks and the source of each piece of data.',

    'submit' => 'Show the route book',
    'clear' => 'Clear',
    'print' => 'Print',
    'loading' => 'Working out the route…',

    'summary_one' => ':km km, :count object',
    'summary_other' => ':km km, :count objects',
    'lines_one' => ':count line',
    'lines_other' => ':count lines',
    'changes_one' => ':count line change',
    'changes_other' => ':count line changes',
    'today' => 'Today’s network, shortest route for passenger trains.',

    'legs_title' => 'Lines crossed',
    'legs_intro' => 'In the order of the route. Departure and arrival are at the centre of the station, at its kilometre point on the line.',
    'leg_km' => 'km :from to :to of the route',
    'leg_pk' => 'KP :from to :to',
    'leg_pk_unknown' => 'Kilometre points unknown on this line',
    'junction' => 'line change on open line',
    'direction' => [
        'up' => 'increasing KP',
        'down' => 'decreasing KP',
    ],
    'precision' => [
        'exact' => 'station kilometre points given by a source',
        'derived' => 'kilometre points worked out by VFF on the SNCF Réseau route',
        'approximate' => 'approximate kilometre points, interpolated between two stations',
    ],
    'no_detail' => 'No detailed track layout yet',

    'layout_title' => 'Track layout',
    'coverage_none' => 'No part of this route has a detailed track layout yet (RINF, OpenStreetMap): the lines and their kilometre points are given above. A part without data is not a part without tracks: the source does not describe it yet.',
    'coverage_partial' => ':km km of the route have no detailed track layout yet (lines :lines).',

    'sources_title' => 'Sources',

    'errors' => [
        'station_not_in_network' => ':station station is not served by today’s network.',
        'no_path' => 'These two stations are not linked by today’s network.',
        'graph_not_built' => 'Today’s network is not available for routes yet.',
    ],

    'from_station' => 'Start from here: route book',
    'from_line' => 'Route book from :from to :to',
];
