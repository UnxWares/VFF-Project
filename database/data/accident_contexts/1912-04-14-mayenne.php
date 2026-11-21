<?php

// Contexte sourcé du déraillement près de la gare d'Oisseau (ligne de Laval à Mayenne) du 14 avril 1912 (recherche du 21/11/2026).
return [
    'slug' => '1912-04-14-mayenne',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1912' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1912',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Avril',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1912&oldid=233727902#Avril',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : ligne de Laval à Mayenne par Landivy des Chemins de fer départementaux de la Mayenne, à 19 heures, près de la gare d\'Oisseau, un train déraille ; 2 morts et 2 blessés.',
        ],
        'le-matin-1912-04-16' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Matin, 16 avril 1912, p. 3',
            'publisher' => 'Gallica (Bibliothèque nationale de France)',
            'author' => null,
            'published' => '1912-04-16',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k570128q/f3.item',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Article cité par la liste de Wikipédia.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 14 avril 1912, vers 19 heures, un train des Chemins de fer départementaux de la Mayenne déraille sur la ligne de Laval à Mayenne par Landivy, près de la gare d\'Oisseau.',
                'en' => 'On 14 April 1912, at about 7 pm, a train of the Mayenne departmental railways derailed on the Laval to Mayenne line via Landivy, near Oisseau station.',
                'de' => 'Am 14. April 1912 entgleiste gegen 19 Uhr ein Zug der Departementsbahnen der Mayenne auf der Strecke Laval - Mayenne über Landivy in der Nähe des Bahnhofs Oisseau.',
                'sources' => ['wp-liste-1912'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le déraillement fait deux morts et deux blessés.',
                'en' => 'The derailment killed two people and injured two.',
                'de' => 'Die Entgleisung forderte zwei Tote und zwei Verletzte.',
                'sources' => ['wp-liste-1912'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'L\'entrée de la liste place l\'accident près de la gare d\'Oisseau ; la fiche de VFF le rattache à la commune de Mayenne, terminus de la ligne.',
                'en' => 'The list entry places the accident near Oisseau station; the VFF entry attaches it to the commune of Mayenne, the end of the line.',
                'de' => 'Der Listeneintrag verortet das Unglück beim Bahnhof Oisseau; der VFF-Eintrag ordnet es der Gemeinde Mayenne zu, dem Endpunkt der Strecke.',
                'sources' => ['wp-liste-1912'],
            ],
        ],
    ],
];
