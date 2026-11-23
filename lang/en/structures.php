<?php

// Line structures (vff:import-structures): line sheet section (LineStructures.svelte).
return [
    'line' => [
        'title' => 'Engineering structures',
        'intro' => 'Tunnels, viaducts and large bridges on the line, in kilometre-post order.',
        'summary' => 'The line has :list.',
        'tunnels_length' => ':length of tunnels in all',
        'count' => [
            'tunnel_one' => ':count tunnel',
            'tunnel_other' => ':count tunnels',
            'viaduct_one' => ':count viaduct',
            'viaduct_other' => ':count viaducts',
            'bridge_one' => ':count large bridge',
            'bridge_other' => ':count large bridges',
        ],
        'kind' => [
            'tunnel' => 'Tunnel',
            'viaduct' => 'Viaduct',
            'bridge' => 'Bridge',
        ],
        'unnamed' => [
            'tunnel' => 'Unnamed tunnel',
            'viaduct' => 'Unnamed viaduct',
            'bridge' => 'Unnamed bridge',
        ],
        'pk' => 'km post :pk',
        'pk_range' => 'km posts :from to :to',
        'built' => 'built in :date',
        'built_day' => 'built on :date',
        'opened' => 'opened in :date',
        'opened_day' => 'opened on :date',
        'closed' => 'closed in :date',
        'closed_day' => 'closed on :date',
        'heritage' => [
            'classified' => 'listed historic monument (classé)',
            'registered' => 'registered historic monument (inscrit)',
            'protected' => 'protected historic monument',
        ],
        'not_current' => 'no longer in service on the line',
        'wikipedia' => 'Wikipedia',
        'merimee' => 'Mérimée',
        'wikidata' => 'Wikidata',
        'photo_credit' => 'Photo :author, :license',
        'photo_license' => 'Photo, :license',
        'all_closed' => 'The line is no longer in service: these structures remain, some converted to other uses.',
        'photo_alt' => 'Photo of :name',
        'notable' => 'Named structures and those of 300 m or more. The full list is folded below.',
        'show_all' => 'Show all :count structures',
        'list_label' => 'All engineering structures on the line',
        'source' => 'Alignments and lengths: IGN, BD TOPO (Licence Ouverte 2.0). Names, dates and heritage status: Wikidata (CC0). Kilometre posts estimated on the elevation profile, calibrated on the stations. Unnamed tunnels under 50 m and unnamed bridges under 100 m are not listed.',
    ],
];
