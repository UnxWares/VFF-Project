<?php

// Contexte sourcé de la collision de Saint-Saviol du 15 avril 1924 (recherche du 21/11/2026).
return [
    'slug' => '1924-04-15-saint-saviol',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1924' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1924',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Avril',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1924&oldid=233501493#Avril',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : 15 avril vers 3 h, gare de Saint-Saviol, lanterne de sémaphore éteinte, le Paris-Côte d\'Argent percute une locomotive en manoeuvre, 1 mort (le mécanicien) et 8 blessés légers.',
        ],
        'delpher-1924-04-16' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Weer een spoorwegongeluk in Frankrijk',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1924-04-16',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010603068:mpeg21:a0058',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche du 16 avril : express Paris-Biarritz contre un train de marchandises à Saint-Saviol, le mécanicien tué, deux voyageurs et des cheminots blessés ; neuvième accident depuis janvier.',
        ],
        'delpher-1924-04-17' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Frankrijk',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1924-04-17',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMUTRA04:253212183:mpeg21:a00004',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même information : seul le mécanicien de l\'express tué, deux voyageurs blessés.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 15 avril 1924, l\'express Paris-Biarritz (le train de luxe Paris-Côte d\'Argent selon la liste de Wikipédia) est entré en collision avec un train de marchandises à la gare de Saint-Saviol. La liste précise que la lanterne du sémaphore était éteinte et que la locomotive heurtée manoeuvrait.',
                'en' => 'On 15 April 1924, the Paris-Biarritz express (the Paris-Côte d\'Argent luxury train according to the Wikipedia list) collided with a goods train at Saint-Saviol station. The list adds that the semaphore lamp was out and that the struck locomotive was shunting.',
                'de' => 'Am 15. April 1924 stieß der Schnellzug Paris-Biarritz (laut Wikipedia-Liste der Luxuszug Paris-Côte d\'Argent) im Bahnhof Saint-Saviol mit einem Güterzug zusammen. Die Liste ergänzt, die Laterne des Semaphors sei erloschen gewesen und die gerammte Lokomotive habe rangiert.',
                'sources' => ['delpher-1924-04-16', 'wp-liste-1924'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien de l\'express a été tué ; deux voyageurs et plusieurs cheminots ont été blessés. La liste de Wikipédia compte huit blessés légers, six cheminots et deux voyageurs.',
                'en' => 'The express driver was killed; two passengers and several railwaymen were injured. The Wikipedia list counts eight slightly injured, six railwaymen and two passengers.',
                'de' => 'Der Lokführer des Schnellzugs wurde getötet; zwei Reisende und mehrere Eisenbahner wurden verletzt. Die Wikipedia-Liste zählt acht Leichtverletzte, sechs Eisenbahner und zwei Reisende.',
                'sources' => ['delpher-1924-04-16', 'delpher-1924-04-17', 'wp-liste-1924'],
            ],
        ],
        'memory' => [
            [
                'fr' => 'La presse néerlandaise des 16 et 17 avril présente cet accident comme le neuvième accident ferroviaire en France depuis janvier 1924.',
                'en' => 'The Dutch press of 16 and 17 April presents this accident as the ninth rail accident in France since January 1924.',
                'de' => 'Die niederländische Presse vom 16. und 17. April stellt diesen Unfall als den neunten Eisenbahnunfall in Frankreich seit Januar 1924 dar.',
                'sources' => ['delpher-1924-04-16', 'delpher-1924-04-17'],
            ],
        ],
    ],
    'corrections' => [],
];
