<?php

// Ouvrages d’art des lignes (vff:import-structures) : section de la fiche ligne (LineStructures.svelte).
// Tracés et longueurs IGN BD TOPO (Licence Ouverte 2.0), noms et dates Wikidata (CC0).
return [
    'line' => [
        'title' => 'Ouvrages d’art',
        'intro' => 'Tunnels, viaducs et grands ponts de la ligne, dans l’ordre des points kilométriques.',
        'summary' => 'La ligne compte :list.',
        'tunnels_length' => ':length de tunnels au total',
        'count' => [
            'tunnel_one' => ':count tunnel',
            'tunnel_other' => ':count tunnels',
            'viaduct_one' => ':count viaduc',
            'viaduct_other' => ':count viaducs',
            'bridge_one' => ':count grand pont',
            'bridge_other' => ':count grands ponts',
        ],
        'kind' => [
            'tunnel' => 'Tunnel',
            'viaduct' => 'Viaduc',
            'bridge' => 'Pont',
        ],
        'unnamed' => [
            'tunnel' => 'Tunnel sans nom connu',
            'viaduct' => 'Viaduc sans nom connu',
            'bridge' => 'Pont sans nom connu',
        ],
        'pk' => 'PK :pk',
        'pk_range' => 'PK :from à :to',
        'built' => 'construit en :date',
        'built_day' => 'construit le :date',
        'opened' => 'mis en service en :date',
        'opened_day' => 'mis en service le :date',
        'closed' => 'fermé en :date',
        'closed_day' => 'fermé le :date',
        'heritage' => [
            'classified' => 'classé monument historique',
            'registered' => 'inscrit monument historique',
            'protected' => 'protégé au titre des monuments historiques',
        ],
        'not_current' => 'plus de service sur la ligne ici',
        'wikipedia' => 'Wikipédia',
        'merimee' => 'Mérimée',
        'wikidata' => 'Wikidata',
        'photo_credit' => 'Photo :author, :license',
        'photo_license' => 'Photo, :license',
        'all_closed' => 'La ligne n’est plus en service : ces ouvrages restent, parfois reconvertis.',
        'photo_alt' => 'Photo de l’ouvrage :name',
        'notable' => 'Ouvrages nommés ou de 300 m et plus. La liste complète est repliée plus bas.',
        'show_all' => 'Voir les :count ouvrages',
        'list_label' => 'Tous les ouvrages d’art de la ligne',
        'source' => 'Tracés et longueurs : IGN, BD TOPO (Licence Ouverte 2.0). Noms, dates et protection : Wikidata (CC0). PK estimés sur le profil en long, calé sur les gares. Les tunnels de moins de 50 m et les ponts de moins de 100 m sans nom ne sont pas listés.',
    ],
];
