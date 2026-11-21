<?php

// Contexte sourcé du déraillement de Plœuc du 23 juillet 1908 (recherche du 21/11/2026).
return [
    'slug' => '1908-07-23-ploeuc-sur-lie',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1908' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1908',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Juillet',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1908&oldid=233618338#Juillet',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : ligne de Saint-Brieuc à Pontivy, vers 8 h 30, déraillement d\'un train de voyageurs pour Saint-Brieuc près de la gare de Plœuc-L\'Hermitage, locomotive, fourgon et deux voitures dans un ravin, 3 morts et 15 blessés.',
        ],
        'wp-ligne-saint-brieuc-pontivy' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Ligne de Saint-Brieuc à Pontivy',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'infobox et schéma de la ligne',
            'url' => 'https://fr.wikipedia.org/wiki/Ligne_de_Saint-Brieuc_%C3%A0_Pontivy',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Ligne à voie normale ouverte en 1872, concédée à la Compagnie de l\'Ouest jusqu\'en 1908 puis reprise par l\'État en 1909 ; la gare de Plœuc - L\'Hermitage figure au PK 502,5.',
        ],
        'matin-1908-07-24' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Matin du 24 juillet 1908',
            'publisher' => 'Le Matin',
            'author' => null,
            'published' => '1908-07-24',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k568767j/f3.image',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Récit de l\'accident, cité par Wikipédia.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 23 juillet 1908, vers 8 h 30, un train de voyageurs à destination de Saint-Brieuc a déraillé près de la gare de Plœuc-L\'Hermitage, sur la ligne de Saint-Brieuc à Pontivy, alors exploitée par la Compagnie de l\'Ouest.',
                'en' => 'On 23 July 1908, at about 8.30 am, a passenger train bound for Saint-Brieuc derailed near Plœuc-L\'Hermitage station, on the Saint-Brieuc to Pontivy line, then operated by the Compagnie de l\'Ouest.',
                'de' => 'Am 23. Juli 1908 entgleiste gegen 8.30 Uhr ein Personenzug nach Saint-Brieuc in der Nähe des Bahnhofs Plœuc-L\'Hermitage auf der Strecke von Saint-Brieuc nach Pontivy, die damals von der Compagnie de l\'Ouest betrieben wurde.',
                'sources' => ['wp-liste-1908', 'wp-ligne-saint-brieuc-pontivy'],
            ],
        ],
        'course' => [
            [
                'fr' => 'La locomotive, le fourgon et deux voitures sont tombés dans un ravin.',
                'en' => 'The locomotive, the luggage van and two coaches fell into a ravine.',
                'de' => 'Die Lokomotive, der Gepäckwagen und zwei Wagen stürzten in eine Schlucht.',
                'sources' => ['wp-liste-1908'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'La liste de Wikipédia compte trois morts et quinze blessés.',
                'en' => 'The Wikipedia list counts three dead and fifteen injured.',
                'de' => 'Die Wikipedia-Liste zählt drei Tote und fünfzehn Verletzte.',
                'sources' => ['wp-liste-1908'],
            ],
        ],
    ],
];
