<?php

return [
    'meta_title' => 'Nos cartes',
    'meta_description' => 'Carte interactive du réseau ferré français, en service et disparu, par époque.',

    'hero' => [
        'title' => 'La carte du rail français, de 1830 à aujourd’hui',
        'subtitle' => 'Les lignes en service, fermées ou déposées, décennie par décennie. Cliquez sur un tracé pour ouvrir sa fiche.',
    ],

    // Titre (lu, non affiché) de la zone de la carte.
    'map_title' => 'Carte du réseau',
    'era_selector' => 'Époque',

    'search' => [
        'label' => 'Chercher une ligne ou une gare',
        'placeholder' => 'Chercher une ligne ou une gare…',
        'lines' => 'Lignes',
        'stations' => 'Gares',
        'materials' => 'Matériel roulant',
        'loading' => 'Recherche en cours…',
        'results_one' => ':count résultat',
        'results_other' => ':count résultats',
        'no_results' => 'Aucune ligne ni gare ne correspond à « :query ».',
        'error' => 'La recherche est indisponible pour le moment. Réessayez dans un instant.',
        'clear' => 'Effacer la recherche',
    ],

    'overlay' => [
        'label' => 'Calque',
        'none' => 'Aucun',
        'orm_standard' => 'OpenRailwayMap - Infrastructure',
        'orm_maxspeed' => 'OpenRailwayMap - Vitesses',
        'orm_signals' => 'OpenRailwayMap - Signalisation',
        'orm_electrification' => 'OpenRailwayMap - Électrification',
        'orm_gauge' => 'OpenRailwayMap - Écartement',
    ],

    'legend' => [
        'heritage_train' => 'Exploitée par une association : train touristique',
        'heritage_velorail' => 'Exploitée par une association : vélorail',
        'title' => 'Réseau en :year',
        'title_current' => 'Réseau actuel',
        'threatened' => 'Ligne menacée (halo rouge)',
        'reopening' => 'Campagne de réouverture soutenue par VFF (halo turquoise)',
        'hint' => 'Cliquez sur une entrée pour l’afficher ou la masquer.',
        'collapse' => 'Replier la légende',
        'expand' => 'Légende',
        'expand_label' => 'Déplier la légende',
        // Gares (couleur = statut à la décennie, cf. railStyle.js STATION_STYLES).
        'stations' => 'Gares à cette époque',
        'stations_hint' => 'Visibles en zoomant.',
        'station_active' => 'Ouverte aux voyageurs (et au fret)',
        'station_tourist' => 'Touristique',
        'station_freight' => 'Fret seul',
        'station_uncertain' => 'Incertaine (aucune voie connue à cette époque)',
        'station_closed' => 'Fermée',
    ],

    // Type d'exploitation associative (line_segments.heritage_kind).
    'heritage_kind' => [
        'tourist_train' => 'Train touristique',
        'velorail' => 'Vélorail',
        'museum' => 'Musée ferroviaire roulant',
    ],

    // Couleur des portions EN SERVICE = type de trafic à la décennie affichée.
    'traffic' => [
        'title' => 'Réseau en service',
        'lgv' => 'Ligne à grande vitesse (LGV)',
        'passenger' => 'Grande vitesse (voyageurs)',
        'mixed' => 'Réseau classique : voyageurs et fret',
        'freight' => 'Réseau classique : fret seul',
        'unknown' => 'Réseau classique : trafic non renseigné',
    ],

    // Trait des portions selon leur statut à la décennie affichée.
    'status' => [
        'title' => 'Statut de la portion',
        'in_service' => 'En service',
        'single_track' => 'En service (voie unique)',
        'service_track' => 'Voie de service / portuaire',
        'construction' => 'En construction',
        'abandoned' => 'Fermée (voie en place)',
        'dismantled' => 'Déposée',
        'razed' => 'Disparue (emprise effacée)',
        'gone' => 'Déposée / disparue',
    ],

    // Mode d'affichage de la carte (légende) : couleur des tracés selon le trafic,
    // l'électrification ou l'écartement à la décennie affichée (vff:enrich-era-segments).
    'mode' => [
        'label' => 'Colorer les tracés selon',
        'traffic' => 'Trafic',
        'electrification' => 'Électrification',
        'gauge' => 'Écartement',
    ],

    'electrification' => [
        'title' => 'Électrification à cette époque',
        'ac25' => '25 kV 50 Hz (alternatif)',
        'dc1500' => '1,5 kV continu',
        'dc3000' => '3 kV continu',
        'ac15' => '15 kV 16,7 Hz (alternatif)',
        'low' => '1 000 V et moins (troisième rail)',
        'other' => 'Autre tension (12 kV du Midi…)',
        'unknown_voltage' => 'Électrifiée, tension inconnue',
        'unknown_date' => 'Électrifiée aujourd’hui, date inconnue',
        'none' => 'Non électrifiée',
        'since' => 'électrifiée depuis :year',
    ],

    'gauge' => [
        'title' => 'Écartement des voies',
        'standard' => 'Voie normale (1 435 mm)',
        'metre' => 'Voie métrique (1 000 mm)',
        'narrow' => 'Voie étroite (600, 700, 750 mm…)',
        'broad' => 'Voie large',
        'unknown' => 'Écartement inconnu',
    ],

    'networks' => [
        'principal' => 'Réseau principal',
        'secondaire' => 'Réseau secondaire',
        'militaire' => 'Réseau militaire',
        'particulier' => 'Réseau particulier',
    ],

    'era' => [
        'no_data' => 'Aucun tracé reconstitué pour :year pour l’instant : cette décennie est en cours de cartographie.',
        'no_data_short' => 'Pas encore de données',
    ],

    // Frise des époques. La décennie « actuelle » (2030) montre le réseau du jour : elle est
    // affichée « Aujourd’hui », jamais comme une année future.
    'timeline' => [
        'aria_label' => 'Frise des époques',
        'group_aria' => 'Période',
        'slider' => 'Décennie affichée',
        'era' => 'Décennie',
        'today' => 'Aujourd’hui',
        'today_caption' => 'Le réseau aujourd’hui',
        'since' => 'Depuis :year',
        'value_today' => 'Aujourd’hui, réseau actuel',
        'value_empty' => ':year, pas encore de données',
        'play' => 'Faire défiler les décennies',
        'pause' => 'Arrêter le défilement',
    ],

    // Fiche d'une ligne au clic sur la carte (LineDrawer) et bulle de la carte intégrée.
    'drawer' => [
        'fallback_line_name' => 'Tronçon',
        'view_full_sheet' => 'Voir la fiche de la ligne',
        'view_station' => 'Voir la fiche de la gare',
        'suggest_edit' => 'Suggérer une modification',
        'close' => 'Fermer la fiche',
        'status' => 'Statut',
        'status_at' => 'Statut en :year',
        'status_today' => 'Statut actuel',
        'max_speed' => 'Vitesse maximale',
        'electrification' => 'Électrification',
        'traffic' => 'Trafic',
        'network' => 'Réseau',
        'heritage' => 'Exploitation associative',
        'today' => 'aujourd’hui',
    ],

    'embed' => [
        'button' => 'Intégrer',
        'title' => 'Intégrer la carte sur votre site',
        'intro' => 'Réglez la carte, puis copiez le code et collez-le dans votre page.',
        'width' => 'Largeur',
        'width_hint' => 'En pixels ou en pourcentage, par exemple 100% ou 800.',
        'height' => 'Hauteur (px)',
        'theme' => 'Thème',
        'theme_auto' => 'Automatique (réglage du visiteur)',
        'theme_light' => 'Clair',
        'theme_dark' => 'Sombre',
        'mode' => 'Couleur des tracés',
        'options' => 'Éléments affichés',
        'show_timeline' => 'Frise des époques',
        'show_legend' => 'Légende',
        'show_vanished' => 'Lignes fermées et déposées',
        'show_attribution' => 'Lien vers VFF (recommandé)',
        'snippet_label' => 'Code à coller dans votre page',
        'copy' => 'Copier le code',
        'copied' => 'Code copié',
        'copy_failed' => 'Copie automatique impossible : le code est sélectionné, copiez-le avec Ctrl+C (Cmd+C sur Mac).',
        'preview' => 'Aperçu',
        'iframe_title' => 'Carte interactive du réseau ferré français - VFF',
        'preview_title' => 'Aperçu de la carte intégrée',
        'credit' => 'Carte : VFF',
    ],

    // Section « Lire la carte », sous la carte.
    'read' => [
        'title' => 'Lire la carte',
        'era_term' => 'Époque',
        'era_text' => 'La frise sous la carte choisit la décennie, de 1830 à aujourd’hui. Le bouton lecture fait défiler les décennies une à une.',
        'color_term' => 'Couleur',
        'color_text' => 'Sur une ligne en service, la couleur dit ce qui y circule : violet pour la grande vitesse, orange pour le réseau classique ouvert aux voyageurs et au fret, bleu pour le fret seul, vert pour une ligne exploitée par une association. Les modes Électrification et Écartement de la légende colorent les mêmes tracés selon la tension ou l’écartement des voies.',
        'line_term' => 'Trait',
        'line_text' => 'Trait plein : ligne en service. Tirets rouges : ligne fermée dont la voie est encore en place. Pointillés gris : voie déposée ou emprise disparue.',
        'sheet_term' => 'Fiches',
        'sheet_text' => 'Cliquez sur un tracé pour voir son statut à l’époque choisie et ouvrir la fiche de la ligne. Les gares apparaissent en zoomant.',
        'route_term' => 'Itinéraire',
        'route_text' => 'Indiquez deux gares et une année : la carte trace le parcours sur le réseau en service à cette époque et liste les lignes empruntées.',
        'layers_term' => 'Calques',
        'layers_text' => 'Le calque OpenRailwayMap superpose l’infrastructure actuelle : signaux, vitesses, électrification. Le bouton à l’horloge, en haut à droite de la carte, affiche des cartes anciennes de l’IGN.',
    ],

    'tech' => [
        'title' => 'Comment la carte est faite',
        'paragraph' => 'La carte est dessinée avec MapLibre GL JS sur un fond OpenStreetMap. Les tracés sont enregistrés dans une base PostgreSQL avec PostGIS, puis envoyés au navigateur en tuiles vectorielles : la carte reste fluide avec des milliers de portions de ligne réparties sur deux siècles.',
        'credits' => 'Logiciels et sources de données de VFF',
    ],

    'cta' => [
        'title' => 'Une ligne manque ou un tracé est faux ?',
        'text' => 'Proposez une correction depuis la fiche de la ligne, ou retracez-la sur la carte. Chaque proposition est relue avant d’être appliquée.',
        'contribute' => 'Proposer une correction',
        'discord' => 'Rejoindre le Discord',
    ],

    // Boutons de la carte (MapLibre) : infobulles et noms lus par les lecteurs d'écran.
    'controls' => [
        'map' => 'Carte interactive du réseau ferré',
        'zoom_in' => 'Zoom avant',
        'zoom_out' => 'Zoom arrière',
        'reset_bearing' => 'Faire glisser pour tourner la carte, cliquer pour revenir au nord',
        'fullscreen_enter' => 'Afficher en plein écran',
        'fullscreen_exit' => 'Quitter le plein écran',
        'attribution' => 'Afficher ou masquer les crédits de la carte',
        'map_feedback' => 'Signaler une erreur du fond de carte',
        'popup_close' => 'Fermer la bulle',
    ],
];
