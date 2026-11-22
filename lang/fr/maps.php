<?php

return [
    'meta_title' => 'Carte du rail français',
    'meta_description' => 'Carte interactive du réseau ferré français, en service et disparu, par époque.',

    'hero' => [
        'title' => 'La carte du rail français, de 1830 à aujourd’hui',
        'subtitle' => 'Les lignes en service, fermées ou déposées, décennie par décennie. Cliquez sur un tracé pour ouvrir sa fiche.',
    ],

    // Titre (lu, non affiché) de la zone de la carte.
    'map_title' => 'Carte du réseau',
    // Raccourcis à côté du titre, vers les sections sous la carte.
    'jumps_label' => 'Sur cette page',
    'era_selector' => 'Époque',

    'search' => [
        'label' => 'Chercher une ligne ou une gare',
        'placeholder' => 'Ligne, gare ou code',
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
        'none' => 'Aucun',
        'accidents' => 'Accidents',
        'level_crossings' => 'Passages à niveau',
        'signalling' => 'Signalisation',
        'greenways' => 'Voies vertes',
    ],

    // Bouton « Calques » de la barre d'outils : calques de VFF à cocher, un fond OpenRailwayMap au plus.
    'layers' => [
        'button' => 'Calques',
        'active_one' => ':count calque affiché',
        'active_other' => ':count calques affichés',
        'data_legend' => 'Ajouter à la carte',
        'today_hint' => 'Passages à niveau : réseau d’aujourd’hui seulement. Signalisation des époques passées : mises en service datées par Wikipédia.',
        'orm_legend' => 'Fond OpenRailwayMap',
        'orm_hint' => 'Un seul fond à la fois, sous les lignes de VFF.',
        'basemap_legend' => 'Fond de carte',
        'tint' => 'Teinte des époques anciennes',
        'tint_hint' => 'Plus l’époque est ancienne, plus le fond pâlit et tire vers le sépia. Le réseau d’aujourd’hui n’est pas teinté.',
        'tint_hint_contrast' => 'Teinte coupée : votre appareil demande un contraste renforcé.',
        'orm' => [
            'standard' => 'Infrastructure',
            'maxspeed' => 'Vitesses',
            'signals' => 'Signaux',
            'electrification' => 'Électrification',
            'gauge' => 'Écartement',
        ],
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
        'low' => '1 000 V et moins (troisième rail)',
        'other' => 'Autre tension (12 kV du Midi…)',
        'unknown_voltage' => 'Électrifiée, tension inconnue',
        'unknown_date' => 'Électrifiée aujourd’hui, date inconnue',
        'none' => 'Non électrifiée',
        'since' => 'électrifiée depuis :year',
    ],

    'gauge' => [
        'title' => 'Écartement des voies',
        'standard' => 'Voie normale (1 435 mm)',
        'metre' => 'Voie métrique (1 000 mm)',
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
        'use_view' => 'Reprendre la vue actuelle (position et zoom)',
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
        'era_text' => 'La frise sous la carte choisit la décennie, de 1830 à aujourd’hui. Le bouton lecture fait défiler les décennies une à une. Plus l’époque est ancienne, plus le fond de carte tire vers le sépia ; la case « Teinte des époques anciennes », dans le bouton Calques, coupe cette teinte.',
        'color_term' => 'Couleur',
        'color_text' => 'Sur une ligne en service, la couleur dit ce qui y circule : violet pour la grande vitesse, orange pour le réseau classique ouvert aux voyageurs et au fret, bleu pour le fret seul, vert pour une ligne exploitée par une association. Les modes Électrification et Écartement de la légende colorent les mêmes tracés selon la tension ou l’écartement des voies.',
        'line_term' => 'Trait',
        'line_text' => 'Trait plein : ligne en service. Tirets rouges : ligne fermée dont la voie est encore en place. Pointillés gris : voie déposée ou emprise disparue.',
        'sheet_term' => 'Fiches',
        'sheet_text' => 'Cliquez sur un tracé pour voir son statut à l’époque choisie et ouvrir la fiche de la ligne. Les gares apparaissent en zoomant.',
        'route_term' => 'Itinéraire',
        'route_text' => 'Indiquez deux gares et une année : la carte trace le parcours sur le réseau en service à cette époque et liste les lignes empruntées. Avec « Comparer avec une autre époque », le même trajet est tracé à une seconde année, en tirets sur liseré bleu pour la plus ancienne.',
        'layers_term' => 'Calques',
        'layers_text' => 'Le bouton Calques, au-dessus de la carte, en affiche plusieurs à la fois. Un fond OpenRailwayMap (un seul à la fois) superpose l’infrastructure actuelle : signaux, vitesses, électrification. Le bouton à l’horloge, en haut à droite de la carte, affiche des cartes anciennes de l’IGN. Le calque Accidents place les accidents survenus jusqu’à l’époque affichée ; un cercle pâle signale que seule la commune est connue. Le calque Passages à niveau montre les passages publiés par SNCF Réseau, à partir de l’échelle d’un département. Le calque Signalisation colore les lignes d’aujourd’hui selon leur mode de cantonnement. Le calque Voies vertes dessine, sur l’emprise de lignes disparues ou fermées, les voies vertes d’OpenStreetMap ; il se superpose à toutes les époques.',
        'views_term' => 'Comparer les époques',
        'views_text' => 'Le choix « Vue », au-dessus de la carte, compare deux décennies (lignes ouvertes, fermées ou déposées entre les deux) ou superpose toutes les époques pour voir quand chaque ligne a quitté le service. L’adresse de la page garde ce choix : vous pouvez la partager.',
        'schema_term' => 'Vue ferroviaire',
        'schema_text' => 'Le choix « Schéma », dans « Vue », ouvre la vue ferroviaire : le réseau en service à l’époque choisie, dessiné comme un plan de transport, sans fond de carte. Les lignes y sont redressées à 45 ou 90 degrés ; bifurcations, correspondances et terminus sont des cercles, les autres gares de petits traits qui apparaissent en zoomant. Sur la France entière, les grandes gares portent le nom de leur ville ; en zoomant, chaque gare retrouve le sien. Une ligne qu’aucune voie en service ne relie au reste du réseau (souvent un train touristique) est dessinée à sa place, sans trait vers les autres lignes. Paris, Lyon, Lille et Marseille sont agrandies pour que leurs gares tiennent : les positions et les distances ne sont donc pas à l’échelle. Un clic sur une ligne ou une gare ouvre la même fiche que sur la carte.',
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
    // Vues par époque (sélecteur « Vue » au-dessus de la carte).
    'view' => [
        'label' => 'Vue',
        'era' => 'Une époque',
        'compare' => 'Comparer',
        'stack' => 'Toutes les époques',
        'schema' => 'Schéma',
    ],

    // Vue ferroviaire : plan schématique d'une époque (SchematicMap, légende, résumé lu par les lecteurs d'écran).
    'schema' => [
        'map_label' => 'Vue ferroviaire du réseau (plan schématique)',
        'legend_title' => 'Vue ferroviaire, :year',
        'legend_title_current' => 'Vue ferroviaire, aujourd’hui',
        'legend_intro' => 'Plan schématique du réseau en service : les positions et les distances ne sont pas à l’échelle.',
        'stations_group' => 'Gares',
        'node' => 'Ville, bifurcation, correspondance ou terminus',
        'stop' => 'Gare intermédiaire (en zoomant)',
        'loading' => 'Chargement du plan…',
        'error' => 'Le plan de cette époque ne peut pas être chargé pour le moment.',
        'attribution' => 'Plan VFF d’après les sources de la carte',
        'summary' => ':when, le plan montre :km km de voies en service (:parts) et :stations gares ouvertes. Les positions et les distances n’y sont pas à l’échelle.',
        'summary_at' => 'En :year',
        'summary_today' => 'Aujourd’hui',
        'summary_part' => ':km km :category',
        'categories' => [
            'lgv' => 'à grande vitesse',
            'mixed' => 'ouverts aux voyageurs et au fret',
            'freight' => 'réservés au fret',
            'unknown' => 'au trafic non renseigné',
            'heritage' => 'exploités par une association',
        ],
    ],

    // Comparaison de deux décennies (barre sous la carte, légende, bulles).
    'compare' => [
        'aria_label' => 'Comparaison de deux décennies',
        'from' => 'De',
        'to' => 'à',
        'legend_title' => 'De :from à :to',
        'today_inline' => 'aujourd’hui',
        'loading' => 'Calcul des différences…',
        'error' => 'Les différences ne peuvent pas être calculées pour le moment.',
        'opened' => 'Ouverte ou rouverte',
        'closed' => 'Fermée, voie en place',
        'removed' => 'Déposée ou disparue',
        'kept' => 'En service aux deux dates',
        'balance' => 'Réseau en service : :before km (:from), puis :after km (:to).',
        'none' => 'Aucun changement entre ces deux décennies.',
        'announce' => 'Entre :from et :to : :opened km ouverts ou rouverts, :closed km fermés avec la voie en place, :removed km déposés ou disparus. Réseau en service : :before km, puis :after km.',
        'popup' => [
            'opened' => 'Ouverte ou rouverte entre :from et :to',
            'closed' => 'Fermée entre :from et :to, voie en place',
            'removed' => 'Déposée ou disparue entre :from et :to',
            'kept' => 'En service aux deux dates (:from, :to)',
        ],
    ],

    // Toutes les époques empilées (teinte = dernière décennie en service).
    'stack' => [
        'legend_title' => 'Toutes les époques',
        'intro' => 'Chaque tracé prend la teinte de la dernière décennie où il était en service : plus il est foncé, plus il a duré.',
        'group' => 'Dernière décennie en service',
        'until' => ':year ou avant',
        'range' => ':from à :to',
        'today' => 'Encore en service aujourd’hui',
        'note' => 'Une décennie montre le réseau au 31 décembre de son année.',
        'popup_single' => 'En service sur la seule carte de :year',
        'popup_span' => 'En service sur les cartes de :from à :to',
        'popup_today' => 'En service depuis la carte de :from, encore aujourd’hui',
    ],

    // Calque « Accidents » (choix « Calque ») : légende, compte et bulle. Ton sobre et factuel.
    'accidents' => [
        'title' => 'Accidents',
        'until' => 'Survenus jusqu’en :year',
        'until_today' => 'Survenus jusqu’à aujourd’hui',
        'between' => 'Survenus de :from à :to',
        'all' => 'Toutes les dates',
        'count_one' => ':count accident localisé',
        'count_other' => ':count accidents localisés',
        'count_none' => 'Aucun accident localisé sur cette période',
        'point_exact' => 'Lieu connu',
        'point_commune' => 'Seule la commune est connue',
        'note' => 'Les accidents dont le lieu n’est pas connu figurent seulement dans la liste.',
        'list_link' => 'Liste des accidents',
        'loading' => 'Chargement des accidents…',
        'error' => 'Les accidents n’ont pas pu être chargés. Décochez puis cochez de nouveau le calque pour réessayer.',
        'view_sheet' => 'Voir la fiche de l’accident',
    ],

    // Calque « Voies vertes » (OpenStreetMap, sur d'anciennes emprises) : légende, bulle.
    'greenways' => [
        'title' => 'Voies vertes',
        'entry' => 'Voie verte sur une ancienne ligne',
        'note' => 'Voies vertes et chemins cyclables qui suivent l’emprise d’une ligne disparue ou fermée. Celles qui longent seulement une ligne en service ou des rails restés en place ne sont pas dessinées.',
        'source' => 'Source : © contributeurs OpenStreetMap (ODbL)',
        'line' => 'Ligne',
        'length' => 'Sur l’ancienne emprise',
        'opened' => 'Ouverture',
        'view_osm' => 'Voir sur OpenStreetMap',
        'fallback_title' => 'Voie verte',
    ],

    // Calque « Passages à niveau » (SNCF Réseau) : légende, bulle.
    'level_crossings' => [
        'title' => 'Passages à niveau',
        'zoom_hint' => 'Zoomez sur la carte : les passages à niveau apparaissent à l’échelle d’un département.',
        'current_only' => 'Les passages à niveau ne sont connus que pour le réseau d’aujourd’hui.',
        'show_today' => 'Afficher la carte d’aujourd’hui',
        'note' => 'Un passage situé sur plusieurs lignes n’est dessiné qu’une fois.',
        'source' => 'Source : SNCF Réseau (ODbL)',
        'pk' => 'PK',
        'line' => 'Ligne',
        'fallback_title' => 'Passage à niveau',
        'view_line' => 'Voir la fiche de la ligne',
    ],

    // Calque « Signalisation » : modes de cantonnement (le KVB n'en est pas un), légende, bulle.
    'signalling' => [
        'title' => 'Signalisation',
        'intro' => 'Mode de cantonnement, qui espace les trains, sur le réseau d’aujourd’hui.',
        'families' => [
            'automatic_block' => 'Block automatique lumineux (BAL)',
            'restricted_block' => 'Block automatique à permissivité restreinte (BAPR)',
            'manual_block' => 'Block manuel (tirets)',
            'telephone' => 'Cantonnement téléphonique ou CAPI (pointillés)',
            'cab' => 'Signalisation en cabine (TVM, ETCS)',
        ],
        'kvb_note' => 'Le KVB contrôle la vitesse des trains, ce n’est pas un mode de cantonnement : il est indiqué dans la bulle de chaque tranche.',
        'placement_note' => 'Les limites des tranches sont placées d’après les points kilométriques, à moins de 200 m près dans 9 cas sur 10.',
        'era_view_only' => 'La signalisation n’est pas dessinée dans la comparaison ni dans la vue empilée : choisissez une seule époque.',
        'history_notice' => 'Pour cette époque, seules les mises en service datées par Wikipédia sont dessinées. Une ligne sans trait avait peut-être une signalisation que l’article ne date pas.',
        // Époques passées : mises en service datées (vff:import-signalling-history), bulle et légende.
        'history' => [
            'intro' => 'Dernier système mis en service sur chaque portion au 31 décembre :year, d’après les dates relevées dans les articles Wikipédia des lignes.',
            'unknown_row' => 'Trait fin et pâle : ligne entière, section inconnue',
            'source_note' => 'Source : Wikipédia (CC BY-SA 4.0). Une portion est placée par ses gares d’extrémité et leurs points kilométriques.',
            'date_label' => 'Mise en service',
            'portion_label' => 'Portion',
            'whole_line' => 'Toute la ligne, d’après l’article',
            'unknown' => 'Ligne entière, section inconnue',
            'unplaced' => 'Ligne entière, section inconnue (l’article cite :from - :to, que la carte ne sait pas placer)',
            'opening' => 'Date d’ouverture de la ligne : une ligne à grande vitesse n’a pas de signaux au sol et roule en signalisation en cabine dès son ouverture.',
            'source' => 'Wikipédia, « :title » (révision lue, CC BY-SA 4.0)',
        ],
        'block_label' => 'Cantonnement',
        'cab_label' => 'Signalisation en cabine',
        'protection_label' => 'Contrôle de vitesse',
        'pk_label' => 'PK',
        'source' => 'Source : SNCF Réseau (ODbL). Limites placées à quelques centaines de mètres près.',
    ],

    // Bouton « Me localiser » : messages quand la position n'est pas disponible.
    'geolocate' => [
        'denied' => 'Vous n’avez pas autorisé l’accès à votre position. Autorisez-le dans les réglages du site de votre navigateur, puis cliquez de nouveau sur « Me localiser ».',
        'unavailable' => 'Votre position n’a pas pu être déterminée. Vérifiez que la localisation est activée sur votre appareil, puis réessayez.',
        'dismiss' => 'Fermer le message',
    ],

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
        'locate' => 'Me localiser (votre position sert seulement à centrer la carte : elle n’est ni envoyée à VFF ni enregistrée)',
        'locate_unavailable' => 'Localisation indisponible : accès refusé, ou non pris en charge par ce navigateur',
        // Message de MapLibre quand la carte demande deux doigts (téléphone) ou une touche (ordinateur).
        'gestures_windows' => 'Ctrl + molette pour zoomer sur la carte',
        'gestures_mac' => '⌘ + molette pour zoomer sur la carte',
        'gestures_mobile' => 'Utilisez deux doigts pour déplacer la carte',
        'marker' => 'Repère sur la carte',
        'logo' => 'Logo de MapLibre',
        'globe_enable' => 'Afficher le globe',
        'globe_disable' => 'Revenir à la carte à plat',
        'terrain_enable' => 'Afficher le relief',
        'terrain_disable' => 'Masquer le relief',
    ],
];
