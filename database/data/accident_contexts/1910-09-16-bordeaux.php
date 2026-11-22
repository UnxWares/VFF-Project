<?php

// Contexte sourcé de la collision à Bordeaux (Benauge, La Bastide) du 16 septembre 1910 (recherche du 21/11/2026).
return [
    'slug' => '1910-09-16-bordeaux',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1910' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1910',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Septembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1910&oldid=233727870#Septembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'À Bordeaux, un train de voyageurs de l\'État venant de La Rochelle, après avoir quitté la gare de l\'État pour Bordeaux-Saint-Jean, pris en écharpe par une machine rentrant au dépôt de La Bastide qui défonce une voiture de troisième classe, un mort et six blessés.',
        ],
        'delpher-delftsche-1910-09-19' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Buitenlandsche Berichten',
            'publisher' => 'Delftsche Courant (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1910-09-19',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMKB08:000121098:mpeg21:a0023',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Accident sur une ligne de l\'État près de Bordeaux, à Benauge : une machine isolée sortie d\'une voie de garage heurte un train très plein venant de La Bastide ; un mort et plusieurs blessés graves.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le vendredi 16 septembre 1910, à Bordeaux, un train de voyageurs du réseau de l\'État venant de La Rochelle avait déposé l\'essentiel de ses voyageurs à la gare de l\'État et se dirigeait vers la gare de Bordeaux-Saint-Jean, selon la liste de Wikipédia.',
                'en' => 'On Friday 16 September 1910, at Bordeaux, a State network passenger train coming from La Rochelle had set down most of its passengers at the gare de l\'État and was heading for Bordeaux-Saint-Jean station, according to the Wikipedia list.',
                'de' => 'Am Freitag, dem 16. September 1910, hatte in Bordeaux ein Reisezug des Staatsbahnnetzes aus La Rochelle den Großteil seiner Reisenden am Bahnhof de l\'État abgesetzt und fuhr nach der Wikipedia-Liste Richtung Bahnhof Bordeaux-Saint-Jean.',
                'sources' => ['wp-liste-1910'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Une machine rentrant au dépôt de La Bastide l\'a pris en écharpe et a défoncé une voiture de troisième classe, selon la liste de Wikipédia. Un journal néerlandais du 19 septembre, relayant une information de Bordeaux, situe l\'accident à Benauge, sur une ligne de l\'État : une machine isolée qui venait d\'une voie de garage est entrée dans un train très plein venant de La Bastide.',
                'en' => 'An engine returning to the La Bastide depot struck it a glancing blow and smashed a third-class coach, according to the Wikipedia list. A Dutch newspaper of 19 September, relaying a report from Bordeaux, places the accident at Benauge, on a State line: a lone engine coming from a siding ran into a very full train coming from La Bastide.',
                'de' => 'Eine zum Depot La Bastide zurückfahrende Lokomotive streifte ihn und zerstörte einen Wagen dritter Klasse, nach der Wikipedia-Liste. Eine niederländische Zeitung vom 19. September, die eine Meldung aus Bordeaux weitergibt, verortet den Unfall in Benauge an einer Staatsbahnstrecke: Eine allein fahrende Lokomotive, die von einem Nebengleis kam, fuhr in einen sehr vollen Zug aus Richtung La Bastide.',
                'sources' => ['wp-liste-1910', 'delpher-delftsche-1910-09-19'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'L\'accident a fait un mort et plusieurs blessés graves selon le journal néerlandais ; la liste de Wikipédia compte un mort et six blessés.',
                'en' => 'The accident killed one and seriously injured several according to the Dutch newspaper; the Wikipedia list counts one dead and six injured.',
                'de' => 'Der Unfall forderte nach der niederländischen Zeitung einen Toten und mehrere Schwerverletzte; die Wikipedia-Liste zählt einen Toten und sechs Verletzte.',
                'sources' => ['delpher-delftsche-1910-09-19', 'wp-liste-1910'],
            ],
        ],
    ],
    'corrections' => [],
];
