<?php

// Page « Données ouvertes » (/open-data) : jeux produits par vff:export-open-data. Ne jamais nommer ici
// une source écartée des jeux : seules les sources citées apparaissent (crédits du catalogue).
return [
    'meta_title' => 'Données ouvertes',
    'meta_description' => 'Téléchargez les données de VFF sous licence ODbL : réseau ferré par décennie (lignes, sections, gares), événements datés, accidents et matériel roulant, en CSV et GeoJSON.',

    'title' => 'Données ouvertes',
    'subtitle' => 'Le réseau ferré par décennie, les événements datés, les accidents et le matériel roulant, à télécharger en CSV et en GeoJSON. Chaque donnée garde sa source.',

    'licence' => [
        'title' => 'Licence et attribution',
        'text' => 'Les jeux sont publiés sous la licence Open Database License (ODbL) 1.0. Vous pouvez les copier, les modifier et les réutiliser, y compris à des fins commerciales.',
        'licence_link' => 'Texte de la licence',
        'attribution' => 'Citez « VFF - Voies Ferrées de France » avec un lien vers vff-project.org, ainsi que les sources listées dans le fichier LICENCE.txt de chaque jeu.',
        'share_alike' => 'Une base de données dérivée que vous rendez publique doit être publiée sous la même licence.',
        'api' => 'Pour des requêtes ciblées, l’API sert les mêmes données à jour.',
        'api_link' => 'Accès à l’API',
    ],

    'thematic' => [
        'title' => 'Jeux thématiques',
        'intro' => 'Toutes les décennies réunies, un fichier CSV par jeu. Chaque jeu contient dictionnaire.csv : description de chaque champ (français, anglais, allemand), sa source et sa licence.',
    ],
    'network' => [
        'title' => 'Réseau par décennie',
        'intro' => 'Pour chaque décennie : les lignes, leurs sections (portions dans un même état) et les gares, en CSV et en GeoJSON. La décennie 2030 donne l’état du réseau au jour de l’export.',
        'current' => 'Aujourd’hui',
    ],

    'kinds' => [
        'events' => ['title' => 'Événements datés', 'text' => 'Ouvertures, fermetures, électrifications et autres dates des lignes, avec la portion et la source.'],
        'accidents' => ['title' => 'Accidents', 'text' => 'Accidents publiés sur VFF : date, lieu, nature, bilan et sources.'],
        'materials' => ['title' => 'Matériel roulant', 'text' => 'Séries de matériel : constructeur, exploitants, années de service et caractéristiques.'],
        'signalling' => ['title' => 'Signalisation et protection', 'text' => 'Mises en service datées du cantonnement, de la cabine, du KVB et de la commande centralisée, et état en vigueur à chaque décennie.'],
        'preserved' => ['title' => 'Matériel préservé', 'text' => 'Exemplaires conservés : numéro, lieu, état et protection au titre des monuments historiques, avec les lieux de conservation.'],
        'projects' => ['title' => 'Projets de lignes', 'text' => 'Réouvertures, lignes nouvelles et électrifications à venir, avec les étapes datées dont la source est libre.'],
        'infrastructure' => ['title' => 'Infrastructure SNCF', 'text' => 'Voies, liaison radio sol-train, quais et cantonnement actuel de SNCF Réseau, rattachés aux lignes et aux gares.'],
        'profiles' => ['title' => 'Profils en long', 'text' => 'Altitudes mesurées le long des lignes, déclivité maximale et déclivités publiées par SNCF Réseau et Wikipédia.'],
        'redirects' => ['title' => 'Redirections d’adresses', 'text' => 'Anciennes adresses de gares fusionnées et de lignes renommées, avec l’adresse actuelle.'],
    ],

    'cols' => [
        'dataset' => 'Jeu',
        'decade' => 'Décennie',
        'date' => 'Date',
        'size' => 'Taille',
        'contents' => 'Contenu',
        'download' => 'Télécharger',
    ],
    'zip' => 'Archive ZIP',
    'zip_label' => 'Télécharger l’archive ZIP du jeu :name (:size)',
    'files' => 'Fichiers séparés',
    'rows_one' => ':count élément',
    'rows_other' => ':count éléments',
    'lines_one' => ':count ligne',
    'lines_other' => ':count lignes',
    'sections_one' => ':count section',
    'sections_other' => ':count sections',
    'stations_one' => ':count gare',
    'stations_other' => ':count gares',

    'empty' => [
        'title' => 'Aucun jeu pour l’instant',
        'text' => 'Les jeux de données sont en préparation. En attendant, l’API sert déjà les mêmes données.',
    ],

    'rights' => [
        'title' => 'Ce qui n’entre pas dans les jeux',
        'intro' => 'N’entrent que les données dont la source a une licence ouverte écrite qui permet un usage commercial. Les textes (descriptions, résumés, citations) ne sont pas repris. Le reste est retiré, ou remplacé par la meilleure donnée libre disponible : par exemple, le tracé d’une section est remplacé par celui d’OpenStreetMap ou de l’IGN quand il suit la même voie à moins de 15 m.',
        'col_item' => 'Donnée',
        'col_replaced' => 'Remplacées',
        'col_removed' => 'Retirées',
        'items' => [
            'sections_geometry' => 'Tracés de sections',
            'sections_gauge' => 'Écartements de sections',
            'lines' => 'Lignes créées d’après un tracé non libre',
            'lines_name' => 'Noms de lignes',
            'stations_name' => 'Noms de gares',
            'stations_geometry' => 'Positions de gares',
            'stations_opened_on' => 'Dates d’ouverture de gares',
            'stations_closed_on' => 'Dates de fermeture de gares',
            'events' => 'Événements datés',
            'accidents' => 'Accidents',
            'accidents_location' => 'Positions d’accidents',
            'accidents_deaths' => 'Nombres de morts',
            'accidents_injuries' => 'Nombres de blessés',
            'accidents_kind' => 'Natures d’accidents',
            'accidents_name' => 'Noms d’accidents',
            'accidents_sources' => 'Sources citées d’accidents',
            'materials' => 'Séries de matériel',
            'signalling_events' => 'Faits de signalisation datés',
            'preserved_units' => 'Exemplaires de matériel préservé',
            'projects_stages' => 'Étapes de projets',
            'infrastructure_voies_csv' => 'Portions de voies SNCF',
            'infrastructure_radio_csv' => 'Portions radio SNCF',
            'infrastructure_platforms' => 'Quais',
            'infrastructure_block' => 'Portions de cantonnement',
            'accidents_references' => 'Références d’accidents',
            'redirects_stations' => 'Redirections de gares',
            'redirects_lines' => 'Redirections de lignes',
            'profiles_measures' => 'Profils en long mesurés',
            'profiles_points' => 'Points de profils en long',
            'profiles_grades' => 'Déclivités sourcées',
            'other' => 'Champ :field',
        ],
    ],

    'credits' => [
        'title' => 'Sources citées',
        'intro' => 'Fournisseurs dont les données figurent dans au moins un jeu, avec leur licence.',
    ],

    // Données structurées (JSON-LD) du jeu de données.
    'json_ld' => [
        'keywords' => 'chemin de fer, lignes ferroviaires, gares, histoire, France, données ouvertes, ODbL',
    ],
];
