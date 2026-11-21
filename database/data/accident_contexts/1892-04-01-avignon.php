<?php

// Contexte sourcé du déraillement d'un train de voyageurs venant d'Avignon, ligne d'Avignon à Miramas, d'avril 1892 (recherche du 21/11/2026).
return [
    'slug' => '1892-04-01-avignon',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1890' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1890',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1892',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1892',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 1er avril 1892 : sur la ligne d\'Avignon à Miramas, vers 11 h, un train de voyageurs venant d\'Avignon déraille à cause d\'un écartement de la voie ; la machine tombe du remblai et tue le chauffeur ; le mécanicien, le conducteur-chef et un voyageur blessés.',
        ],
        'delpher-1892-04-05-avondpost' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Laatste Nieuwstijdingen',
            'publisher' => 'De avondpost (presse néerlandaise numérisée, Delpher)',
            'author' => null,
            'published' => '1892-04-05',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMKB27:017842074:mpeg21:a00021',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : près d\'Avignon, un train de voyageurs a déraillé ; le chauffeur tué, le mécanicien, un conducteur et un voyageur légèrement blessés.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 1er avril 1892, vers 11 h, un train de voyageurs venant d\'Avignon a déraillé sur la ligne d\'Avignon à Miramas, à cause d\'un écartement de la voie selon la liste de Wikipédia. La machine est tombée du remblai.',
                'en' => 'On 1 April 1892, at about 11 am, a passenger train from Avignon derailed on the Avignon to Miramas line, because the rails had spread according to the Wikipedia list. The engine fell down the embankment.',
                'de' => 'Am 1. April 1892 gegen 11 Uhr entgleiste ein Personenzug aus Avignon an der Strecke Avignon-Miramas, nach der Wikipedia-Liste wegen einer Spurerweiterung. Die Lokomotive stürzte den Damm hinab.',
                'sources' => ['wp-liste-1890', 'delpher-1892-04-05-avondpost'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le chauffeur a été tué. Le mécanicien, le conducteur-chef et un voyageur ont été blessés, légèrement selon la presse néerlandaise.',
                'en' => 'The stoker was killed. The driver, the chief conductor and one passenger were injured, slightly according to the Dutch press.',
                'de' => 'Der Heizer wurde getötet. Lokführer, Oberschaffner und ein Reisender wurden verletzt, nach der niederländischen Presse leicht.',
                'sources' => ['wp-liste-1890', 'delpher-1892-04-05-avondpost'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1890', 'delpher-1892-04-05-avondpost'],
            'fr' => 'Chauffeur tué selon la liste de Wikipédia et De avondpost du 5 avril 1892 ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Stoker killed according to the Wikipedia list and De avondpost of 5 April 1892; the record gave no number of deaths.',
            'de' => 'Heizer getötet nach der Wikipedia-Liste und De avondpost vom 5. April 1892; der Eintrag nannte keine Zahl von Toten.',
        ],
        [
            'field' => 'injuries',
            'value' => 3,
            'sources' => ['wp-liste-1890', 'delpher-1892-04-05-avondpost'],
            'fr' => 'Mécanicien, conducteur et voyageur blessés selon la liste de Wikipédia et De avondpost du 5 avril 1892 ; la fiche ne donnait pas de nombre de blessés.',
            'en' => 'Driver, conductor and one passenger injured according to the Wikipedia list and De avondpost of 5 April 1892; the record gave no number of injured.',
            'de' => 'Lokführer, Schaffner und ein Reisender verletzt nach der Wikipedia-Liste und De avondpost vom 5. April 1892; der Eintrag nannte keine Zahl von Verletzten.',
        ],
    ],
];
