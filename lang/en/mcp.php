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
            'insee' => 'INSEE, Code officiel géographique (codes and names of French departments and regions), Open Licence 2.0',
            'gallica' => 'Source: gallica.bnf.fr / BnF; catalogue records of the Bibliothèque nationale de France, Open Licence, with the retrieval date (digitised documents: Gallica terms of use)',
        ],
    ],

    'errors' => [
        'unknown_arguments' => 'Unknown arguments: :names.',
        'line_not_found' => 'Line not found: “:query”. Look up its identifier with the search tool (suggestions attached).',
        'no_geometry' => 'The route of “:line” does not exist for the :year era: no kilometre point.',
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
        'event_not_on_line' => 'Event :event does not belong to line :line. Use an id given by line_events, or by line_stories without `event`.',
        'no_story' => 'Event :event has no story: fewer than 3 images and documents dated within 5 years (2 images at least), or an event type without stories.',
    ],

    'truncated' => 'Answer shortened to stay under the maximum size: narrow the request (period, types, limit).',
    'truncated_page' => 'Page shortened to stay under the maximum size: next_cursor resumes at the first item not returned, so nothing is lost.',
    'between' => 'between :from and :to (no source dates this change)',
    'today' => 'today',
    'dates' => [
        'unknown_after' => 'date unknown, after :year (no source gives it)',
        'unknown' => 'date unknown (no source gives it)',
    ],

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

    // Invites (prompts) MCP : message envoyé à l'assistant, dans la langue demandée (app/Mcp/Prompts).
    'prompts' => [
        'language' => 'English',
        'present_line' => [
            'message' => 'Present the line ":line" using data from VFF (French Railway Lines, vff-project.org).
1. :identify
2. Read its sheet with get_line, events included. If the timeline was shortened (truncated), fill it in with line_events.
3. Write a few paragraphs: where the line starts and ends, its length, its electrification and signalling, the main dates (opening, closures, electrification, track removal) and its state today.
4. Cite the source of every date and figure (source field and source_url link). Write each date only to its known precision: day, month or year. A phase with "range" precision is dated by no source: say only that it happened between two decades.
5. Use your own words. Do not copy text from Wikipedia or any other source.
6. End with the vff_url link of the sheet and the credits in the attribution block.
Answer in :language.',
            'found' => 'The line is ":name" (identifier :slug): pass this identifier to the tools. Its Markdown sheet is also available as the resource vff://:lang/lines/:slug.',
            'search' => 'Find its identifier with search {"query": ":line", "kinds": ["lines"]}. If several lines match, ask which one before going on.',
        ],
        'compare_region_eras' => [
            'message' => 'Compare the railway network of the :region region (INSEE code :code) in :from and in :to, using data from VFF. Departments of the region: :departments.
1. For each period, count open and closed lines with list_lines {"region": ":code", "era": <year>, "facets": true, "limit": 1}, and stations by status with list_stations {"region": ":code", "era": <year>, "facets": true, "limit": 1}.
2. List what changed between the two dates with network_events {"region": ":code", "from": ":events_from", "to": ":events_to"} (closures, openings, electrifications, track removals), following next_cursor while has_more is true.
3. To compare the region with the country, compare_eras {"from": :from, "to": :to} gives the same figures for the whole of France.
4. Give a table of the two periods, then the main changes line by line, each with its date, its precision and its source.
5. Explain that a line counts in the region if it serves a station there or if its sheet names one of its departments. The VFF map is a snapshot on 31 December of each decade; :era_note
6. End with the credits in the attribution block.
Answer in :language.',
            'current' => 'the :to period describes today\'s network.',
            'past' => 'both periods are snapshots on 31 December of their year.',
        ],
        'station_story' => [
            'message' => 'Tell the history of the station ":station" using data from VFF.
1. :identify
2. Read its sheet with get_station: opening and closing dates, status in each decade, lines served with their years of service, sources.
3. For the lines serving it, get_line (with include_events set to false) gives their opening and closure. Add accidents near the station with accidents {"station": "<identifier>"} and archive images with media {"station": "<identifier>"}.
4. Tell the story in order: the opening, lines arriving or closing, changes of status, the situation today. A decade with the status "uncertain" is not confirmed by the tracks of the time: say so.
5. Give every date with its precision and its source, use your own words without copying text from a source, and show each image with its attribution.
6. End with the vff_url link of the sheet and the credits in the attribution block.
Answer in :language.',
            'found' => 'The station is ":name" (:city, identifier :slug): pass this identifier to the tools.',
            'search' => 'Find its identifier with search {"query": ":station", "kinds": ["stations"]}. Several stations sometimes share a name: if in doubt, ask which one before going on.',
        ],
    ],
];
