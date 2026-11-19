<?php

return [
    'meta_title' => 'VFF - Voies Ferrées de France',
    'meta_description' => 'Encyclopédie collaborative du chemin de fer français, de 1830 à aujourd’hui : lignes, gares, carte du réseau à chaque décennie et archives sourcées.',
    'share_image_alt' => 'Le logo de Voies Ferrées de France sur la carte des chemins de fer d’Alexandre Vuillemin (1865)',

    // Grand visuel (disque VFF entre deux TGV) : phrase sous le disque et deux actions.
    'hero' => [
        'tagline' => 'L’encyclopédie des voies ferrées de France, de 1830 à aujourd’hui',
        'open_map' => 'Ouvrir la carte',
        'search' => 'Rechercher une ligne ou une gare',
        // Fond : détail de la carte de Vuillemin (1865), cf. credits.media.partners.home_map_*.
        'credit' => "Fond\u{00A0}: carte des chemins de fer d’Alexandre Vuillemin (1865), domaine public",
    ],

    // Présentation (dessin d'origine : logo, titre, texte, deux boutons).
    'whoarewe' => [
        'title' => 'VFF - À la croisée des rails',
        'paragraph' => 'Projet d’UnxWares, mené par ses deux associés et ses contributeurs, VFF est une encyclopédie collaborative du chemin de fer français, de 1830 à aujourd’hui. Elle décrit chaque ligne, en service ou disparue : son tracé, ses gares, ses dates et la source de chaque information. Vous pouvez la consulter, proposer des corrections et réutiliser ses données grâce à son API publique.',
        'cta_history' => 'Notre histoire',
        'cta_donate' => 'Faites un don',
    ],

    // Encarts de mobilisation (lignes soutenues par VFF). Titre, message et bouton viennent de la base.
    'advocacy' => [
        'label' => 'Lignes soutenues par VFF',
        'kind_threatened' => 'Ligne menacée',
        'kind_reopening' => 'Campagne de réouverture',
        'threatened' => 'Ligne menacée',
        'reopening' => 'Pour la réouverture de la ligne',
        'see_line' => 'Voir la fiche de la ligne',
        'act' => 'Agir',
        'default_title_threatened' => 'Ligne menacée de fermeture',
        'default_title_reopening' => 'Pour la réouverture de la ligne',
        'default_message_threatened' => 'Nous soutenons le maintien de cette ligne.',
        'default_message_reopening' => 'Nous soutenons la réouverture de cette ligne.',
        'default_cta' => 'Agir pour la ligne',
    ],

    // Bande orange : trois portes d'entrée (dessin d'origine).
    'maps' => [
        'box1_title' => 'Carte temporelle',
        'box1_text' => 'Le réseau ferré français décennie après décennie, de 1830 à aujourd’hui : les lignes ouvertes, fermées ou déposées à chaque époque.',
        'box1_link' => 'Ouvrir la carte',
        'box2_title' => 'Fiches détaillées',
        'box2_text' => 'Pour chaque ligne : son tracé, ses gares, sa chronologie datée, des photographies d’archives et la source de chaque information.',
        'box2_link' => 'Parcourir les lignes',
        'box3_title' => 'Données ouvertes',
        'box3_text' => 'Les données de VFF sont accessibles par une API publique (REST et GraphQL), pour vos recherches et vos projets, selon la licence de chaque source.',
        'box3_link' => 'Lire la documentation de l’API',
    ],

    'genesis' => [
        'title' => 'Avant que les rails ne s’effacent',
        'p1' => 'Vers 1930, la France comptait près de 60 000 kilomètres de lignes de chemin de fer, réseaux secondaires compris. Moins de 30 000 sont encore en service. Les autres ont été fermées, déposées, parfois effacées du paysage, et leur histoire est dispersée entre archives, cartes anciennes et souvenirs.',
        'p2' => 'VFF rassemble cette mémoire : recenser chaque ligne, la dater, la placer sur la carte, citer ses sources, et la partager pour que d’autres puissent la compléter.',
        'signature' => 'Les fondateurs',
    ],

    // Profil du réseau : longueur des lignes ouvertes à chaque décennie (données de la base).
    'network' => [
        'title' => 'Le réseau, décennie après décennie',
        'intro' => 'De 1830 à 1930, le réseau ferré français ne cesse de s’étendre. Il se resserre ensuite et a perdu depuis la moitié de sa longueur.',
        'chart_caption' => 'Longueur des lignes ouvertes au 31 décembre de chaque décennie, en kilomètres, d’après les dates d’ouverture et de fermeture recensées dans VFF. Choisissez une décennie pour ouvrir sa carte.',
        'today' => 'Aujourd’hui',
        'bar' => ':year : :km, :lines. Ouvrir la carte de cette décennie.',
        'bar_today' => 'Aujourd’hui : :km, :lines. Ouvrir la carte du réseau actuel.',
        'km' => ":value\u{00A0}km",
        'lines_one' => ":value\u{00A0}ligne",
        'lines_other' => ":value\u{00A0}lignes",
        'table_toggle' => 'Afficher les chiffres par décennie',
        'table_caption' => 'Longueur des lignes ouvertes et nombre de lignes, par décennie',
        'col_decade' => 'Décennie',
        'col_length' => 'Longueur',
        'col_lines' => 'Lignes ouvertes',
        'milestones_title' => 'Repères',
        'm1827' => 'Saint-Étienne - Andrézieux, la première ligne française, transporte la houille de la Loire dans des wagons tirés par des chevaux.',
        'm1842' => 'La loi du 11 juin 1842 organise un réseau en étoile autour de Paris, construit avec l’État et exploité par des compagnies.',
        'm1879' => 'Le plan Freycinet lance des milliers de kilomètres de lignes pour desservir chaque sous-préfecture.',
        'm1930' => 'Le réseau atteint son extension maximale : près de 60 000 km, réseaux secondaires compris.',
        'm1938' => 'Les grandes compagnies sont réunies dans la SNCF, créée le 1er janvier 1938.',
        'm1981' => 'La ligne à grande vitesse Paris - Lyon ouvre sa première section en septembre 1981.',
        'cta' => 'Ouvrir la carte',
    ],

    // Galerie : vraies images de Wikimedia Commons rattachées aux lignes.
    'archives' => [
        'title' => 'Le rail en images',
        'intro' => 'Photographies et cartes postales publiées sur Wikimedia Commons et rattachées aux fiches des lignes, avec leur auteur et leur licence.',
        'decade' => 'Années :decade',
        'author_unknown' => 'Auteur inconnu',
        'source' => 'Wikimedia Commons',
        'cta' => 'Parcourir les lignes',
    ],

    // Accès rapide, juste après le grand en-tête : recherche et entrées directes (noms : nav.*).
    'quick' => [
        'label' => 'Accès rapide',
        'shortcut_hint' => 'Sur toutes les pages, :keys ouvre la recherche.',
        'map_detail' => 'De 1830 à aujourd’hui',
        'materials_detail' => 'Locomotives, automotrices, voitures',
        'api_detail' => 'REST, GraphQL, GeoJSON',
        'contribute_detail' => 'Corriger une ligne, citer une source',
    ],

    // Sommaire de l'encyclopédie, avec les comptes réels de la base.
    'contents' => [
        'title' => 'Dans l’encyclopédie',
        'lines_title' => 'Lignes',
        'lines_text' => 'Le tracé, les gares, la chronologie et les sources de chaque ligne, en service ou disparue.',
        'lines_count_one' => ":value\u{00A0}fiche",
        'lines_count_other' => ":value\u{00A0}fiches",
        'stations_title' => 'Gares',
        'stations_text' => 'Les gares et les haltes, avec leurs dates et les lignes qui les desservent.',
        'stations_count_one' => ":value\u{00A0}fiche",
        'stations_count_other' => ":value\u{00A0}fiches",
        'materials_title' => 'Matériel roulant',
        'materials_text' => 'Locomotives, automotrices et voitures, avec les lignes où elles ont circulé.',
        'materials_count_one' => ":value\u{00A0}fiche",
        'materials_count_other' => ":value\u{00A0}fiches",
        'map_title' => 'Carte historique',
        'map_text' => 'Le réseau tel qu’il était à chaque décennie, de 1830 à aujourd’hui.',
        'map_count_one' => ":value\u{00A0}décennie",
        'map_count_other' => ":value\u{00A0}décennies",
        'media_title' => 'Images d’archives',
        'media_text' => 'Les photographies et documents rattachés aux lignes, avec leur auteur et leur licence.',
        'media_count_one' => ":value\u{00A0}image",
        'media_count_other' => ":value\u{00A0}images",
        'api_title' => 'API et données',
        'api_text' => 'Les données de VFF en REST, GraphQL et GeoJSON, à réutiliser selon la licence de chaque source.',
    ],

    // Invitation finale (une seule bande « cta » par page).
    'contribute' => [
        'title' => 'Participer à l’encyclopédie',
        'subtitle' => 'VFF se construit avec ses lecteurs. Deux validateurs relisent chaque proposition avant sa publication, et chaque information cite sa source.',
        'data_title' => 'Documenter une ligne',
        'data_text' => 'Corrigez une date ou complétez un tracé dans l’éditeur, en indiquant votre source.',
        'media_title' => 'Partager des archives',
        'media_text' => 'Vous avez des photographies, des plans ou des documents anciens ? Écrivez-nous pour les publier avec leur auteur et leur licence.',
        'code_title' => 'Améliorer le code',
        'code_text' => 'Le code source de VFF est publié sur GitHub sous licence MIT : signalez un problème, proposez une traduction ou une correction.',
        'cta' => 'Comment contribuer',
        'donate' => 'Faites un don',
        'discord' => 'Discord',
        'github' => 'GitHub',
    ],
];
