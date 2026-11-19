<?php

// MCP server (app/Mcp, docs/MCP.md): texts of the answers to AI assistants. Server side only.
return [
    'attribution' => [
        'notice' => 'Data from French Railway Lines (Voies Ferrées de France, vff-project.org), database under the ODbL 1.0: credit “Voies Ferrées de France (vff-project.org)”, the link to the entry and the sources below.',
        'terms' => 'VFF data may be reused, including commercially, under the Open Database License (ODbL) 1.0: credit “Voies Ferrées de France (vff-project.org)” and its sources, including “© OpenStreetMap contributors”, and publish any derived database under the same licence. Exception: data from a source that only allows non-commercial use (source field) may only be reused for non-commercial purposes, crediting the source with a link.',
        'restricted' => '(no open licence: reuse only with this credit and the link to the source)',
        'providers' => [
            'vff' => 'French Railway Lines (Voies Ferrées de France, vff-project.org), UnxWares, ODbL 1.0',
            'osm' => '© OpenStreetMap contributors, ODbL 1.0 (https://www.openstreetmap.org/copyright)',
            'sncf' => 'SNCF Open Data (SNCF Réseau, SNCF Voyageurs, SNCF Gares & Connexions), ODbL 1.0',
            'ohm' => 'OpenHistoricalMap, CC0 1.0',
            'ign' => 'IGN (BD TOPO, historical BD ORTHO), Licence Ouverte 2.0',
            'wikipedia' => 'Wikipedia, CC BY-SA 4.0 (texts); facts reused with a link to the article',
            'wikidata' => 'Wikidata, CC0 1.0',
            'commons' => 'Wikimedia Commons, licence specific to each file',
            'communes' => 'Etalab and IGN ADMIN EXPRESS (administrative boundaries), ODbL',
            'jorf' => 'DILA, Journal officiel (Légifrance), Licence Ouverte',
            'archeoferroviaire' => 'archeoferroviaire.free.fr (J. Randé, F. Librini, K. Moreau), non-commercial use, with a link to the article',
            'lignes-oubliees' => 'lignes-oubliees.com, information reproduced with credit and a link to the entry',
            'argentanwebferro' => 'Argentan Web Ferroviaire (Thierry Danglos), facts reused with a link to the article',
            'bea-tt' => 'BEA-TT (French land transport accident investigation bureau), Open Licence 2.0 (Etalab); general overview of investigations and report links, with the overview’s update date',
        ],
    ],

    'errors' => [
        'unknown_arguments' => 'Unknown arguments: :names.',
        'line_not_found' => 'Line not found: “:query”. Look up its identifier with the search tool (suggestions attached).',
        'profile_not_computed' => 'The longitudinal profile of “:line” has not been computed yet.',
        'station_not_found' => 'Station not found: “:query”. Look up its identifier with the search tool (suggestions attached).',
        'material_not_found' => 'Rolling stock class not found: “:query”. Look up its identifier with the search or materials tool (suggestions attached).',
        'accident_not_found' => 'Accident not found: “:query”. Look up its identifier with the accidents tool (q filter).',
        'era_not_found' => 'No network map for :year (see the available eras).',
        'same_era' => 'Both years fall in the same map era (:year): choose two different decades (see the available eras).',
        'graph_not_built' => 'The rail graph for this period has not been computed yet: no route can be calculated for now.',
        'ambiguous_station' => 'Several stations match: use a slug from the candidates.',
        'same_station' => 'Departure and arrival are the same station.',
        'station_not_in_network' => 'One of the stations is not served by the network in operation at that time.',
        'no_path' => 'No route between :from and :to on the network in operation at that time.',
        'invalid_uri' => 'Invalid resource address: expected vff://{lang}/lines/{slug}, lang = fr, en or de.',
    ],

    'truncated' => 'Answer shortened to stay under the maximum size: narrow the request (period, types, limit).',
    'truncated_page' => 'Page shortened to stay under the maximum size: next_cursor resumes at the first item not returned, so nothing is lost.',
    'between' => 'between :from and :to (no source dates this change)',
    'today' => 'today',

    'accidents' => [
        'review_note' => 'Record “to be checked”: at least one point is uncertain (tolls or dates that differ between sources, place known only to the commune, uncertain line). Do not present it as certain: the details are in review.notes of get_accident.',
    ],

    'compare' => [
        'opened' => 'Opened or reopened between the two eras (in service at the second, not at the first)',
        'closed' => 'Closed with the track still in place at the second era, not at the first',
        'removed' => 'Lifted or gone at the second era (in service or closed at the first)',
        'kept' => 'In service at both eras',
    ],

    'traffic' => [
        'passenger' => 'Passengers only',
    ],

    'electrification' => [
        'none' => 'Not electrified',
        'marginal' => 'Marginally electrified (station tracks, connecting line)',
        'partial' => 'Partly electrified',
        'full' => 'Electrified',
    ],

    'route' => [
        'duration_note' => 'Indicative duration at an average of 100 km/h, not a historical timetable.',
    ],

    'markdown' => [
        'code' => 'Line code',
        'length' => 'Route length',
        'in_service_today' => 'in service today',
        'max_speed' => 'Maximum speed',
        'electrification' => 'Electrification',
        'signalling' => 'Signalling',
        'phases' => 'Phases',
        'chronology' => 'Chronology',
        'source' => 'source',
        'more_events' => ':n more events: line_events tool.',
        'sources' => 'Sources and licences',
        'sheet' => 'VFF entry',
        'licences_title' => 'Licences and credits for VFF data',
        'licence' => 'Licence',
        'mention' => 'Credit',
    ],
];
