<?php

// Livret de parcours (/routebook) : textes de la page. Les données viennent de GET /api/v1/routebook.
return [
    'meta_title' => 'Livret de parcours',
    'meta_description' => 'Le parcours d’aujourd’hui entre deux gares, ligne par ligne : points kilométriques, schéma des voies, vitesses et déclivités dans le sens de marche, avec la source de chaque donnée.',
    'hero_title' => 'Livret de parcours',
    'hero_subtitle' => 'Choisissez deux gares : les lignes traversées s’enchaînent dans le sens de marche, avec leurs PK, leurs voies et la source de chaque donnée.',

    'submit' => 'Afficher le livret',
    'clear' => 'Effacer',
    'print' => 'Imprimer',
    'loading' => 'Calcul du parcours…',

    'summary_one' => ':km km, :count objet',
    'summary_other' => ':km km, :count objets',
    'lines_one' => ':count ligne',
    'lines_other' => ':count lignes',
    'changes_one' => ':count changement de ligne',
    'changes_other' => ':count changements de ligne',
    'today' => 'Réseau d’aujourd’hui, plus court chemin pour les voyageurs.',

    'legs_title' => 'Lignes traversées',
    'legs_intro' => 'Dans l’ordre du parcours. Le départ et l’arrivée sont au centre de la gare, à son PK sur la ligne.',
    'leg_km' => 'km :from à :to du parcours',
    'leg_pk' => 'PK :from à :to',
    'leg_pk_unknown' => 'PK inconnus sur cette ligne',
    'junction' => 'changement de ligne en pleine voie',
    'direction' => [
        'up' => 'PK croissants',
        'down' => 'PK décroissants',
    ],
    'precision' => [
        'exact' => 'PK des gares donnés par une source',
        'derived' => 'PK calculés par VFF sur le tracé de SNCF Réseau',
        'approximate' => 'PK approchés, interpolés entre deux gares',
    ],
    'no_detail' => 'Pas encore de schéma des voies détaillé',

    'layout_title' => 'Schéma des voies',
    'coverage_none' => 'Aucune portion de ce parcours n’a encore de schéma des voies détaillé (RINF, OpenStreetMap) : les lignes et leurs PK sont donnés ci-dessus. Une portion sans donnée n’est pas une portion sans voie : la source ne la décrit pas encore.',
    'coverage_partial' => ':km km du parcours n’ont pas encore de schéma des voies détaillé (lignes :lines).',

    'sources_title' => 'Sources',

    'errors' => [
        'station_not_in_network' => 'La gare :station n’est pas desservie par le réseau d’aujourd’hui.',
        'no_path' => 'Ces deux gares ne sont pas reliées par le réseau d’aujourd’hui.',
        'graph_not_built' => 'Le réseau d’aujourd’hui n’est pas encore disponible pour les parcours.',
    ],

    'from_station' => 'Partir d’ici : livret de parcours',
    'from_line' => 'Livret de parcours de :from à :to',
];
