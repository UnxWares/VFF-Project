<?php

// Contexte sourcé de la collision près de Saint-Germain-de-Tallevende du 15 janvier 1911 (recherche du 21/11/2026).
return [
    'slug' => '1911-01-15-saint-germain-de-tallevende-la-lande-vaumont',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1911' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1911',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Janvier',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1911&oldid=233727889#Janvier',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Ligne de Vire à Romagny, train de voyageurs vers Saint-Hilaire-du-Harcouët contre un train de ballast, trois morts et neuf blessés, tous employés de l\'Ouest-État ; renvoie à Le Petit Journal du 16 janvier 1911 et à Le Temps du 21 janvier 1911 (non lus).',
        ],
        'delpher-zuidwillemsvaart-1911-01-18' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'De Zuid-Willemsvaart du 18 janvier 1911',
            'publisher' => 'De Zuid-Willemsvaart (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1911-01-18',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMRHCE01:000038092:mpeg21:a0005',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche du dimanche 15 janvier : train de Vire à Saint-Hilaire-du-Harcouët contre un train de marchandises entre Saint-Germain-de-Tallevende et un lieu lu Les Maures ; trois cheminots tués.',
        ],
        'delpher-veendammer-1911-01-18' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Nieuwe Veendammer Courant du 18 janvier 1911',
            'publisher' => 'Nieuwe Veendammer Courant (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1911-01-18',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMVEEN01:000079390:mpeg21:a0006',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même dépêche : deux mécaniciens et un chauffeur tués, nombreux blessés dont deux en danger de mort, tous du personnel des trains.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le dimanche 15 janvier 1911, vers 14 h 15, sur la ligne de Vire à Romagny (prolongement de la ligne de Caen à Vire), un train de voyageurs allant de Vire vers Saint-Hilaire-du-Harcouët circulait près de Saint-Germain-de-Tallevende, selon la liste de Wikipédia.',
                'en' => 'On Sunday 15 January 1911, at about 2.15 pm, on the Vire to Romagny line (an extension of the Caen to Vire line), a passenger train going from Vire towards Saint-Hilaire-du-Harcouët was running near Saint-Germain-de-Tallevende, according to the Wikipedia list.',
                'de' => 'Am Sonntag, dem 15. Januar 1911, gegen 14.15 Uhr, fuhr nach der Wikipedia-Liste auf der Strecke von Vire nach Romagny (einer Verlängerung der Strecke von Caen nach Vire) ein Reisezug von Vire Richtung Saint-Hilaire-du-Harcouët in der Nähe von Saint-Germain-de-Tallevende.',
                'sources' => ['wp-liste-1911', 'delpher-zuidwillemsvaart-1911-01-18'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Entre Saint-Germain-de-Tallevende et un lieu que la presse néerlandaise écrit Les Maures, le train de voyageurs est entré en collision avec un train composé d\'une locomotive et de quelques wagons : un train de ballast selon la liste de Wikipédia.',
                'en' => 'Between Saint-Germain-de-Tallevende and a place the Dutch press spells Les Maures, the passenger train collided with a train made up of a locomotive and a few wagons: a ballast train according to the Wikipedia list.',
                'de' => 'Zwischen Saint-Germain-de-Tallevende und einem Ort, den die niederländische Presse Les Maures schreibt, stieß der Reisezug mit einem aus einer Lokomotive und einigen Wagen bestehenden Zug zusammen: nach der Wikipedia-Liste ein Schotterzug.',
                'sources' => ['delpher-zuidwillemsvaart-1911-01-18', 'delpher-veendammer-1911-01-18', 'wp-liste-1911'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Trois cheminots ont été tués (deux mécaniciens et un chauffeur selon la presse néerlandaise), et de nombreux autres ont été blessés, dont deux en danger de mort ; les victimes appartenaient au personnel des trains. La liste de Wikipédia compte trois morts et neuf blessés, tous employés du réseau de l\'Ouest-État, et précise que cinq d\'entre eux sont morts les jours suivants.',
                'en' => 'Three railwaymen were killed (two drivers and a fireman according to the Dutch press), and many others were injured, two of them in danger of death; the victims were train staff. The Wikipedia list counts three dead and nine injured, all employees of the Ouest-État network, and adds that five of them died in the following days.',
                'de' => 'Drei Eisenbahner wurden getötet (nach der niederländischen Presse zwei Lokführer und ein Heizer), zahlreiche weitere wurden verletzt, zwei davon lebensgefährlich; die Opfer gehörten zum Zugpersonal. Die Wikipedia-Liste zählt drei Tote und neun Verletzte, alle Angestellte des Netzes der Ouest-État, und fügt hinzu, dass fünf von ihnen in den folgenden Tagen starben.',
                'sources' => ['delpher-zuidwillemsvaart-1911-01-18', 'delpher-veendammer-1911-01-18', 'wp-liste-1911'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le bilan n\'est pas cohérent d\'une source à l\'autre : la liste de Wikipédia annonce trois morts et neuf blessés dont cinq mourront ensuite, ce qui laisserait supposer plus de trois morts au total ; les dépêches du 18 janvier donnent trois morts. Le nombre définitif de morts n\'est pas établi.',
                'en' => 'The toll is not consistent from one source to another: the Wikipedia list gives three dead and nine injured, five of whom later died, which would suggest more than three deaths in all; the dispatches of 18 January give three dead. The final number of deaths is not established.',
                'de' => 'Die Bilanz ist von Quelle zu Quelle nicht stimmig: Die Wikipedia-Liste nennt drei Tote und neun Verletzte, von denen fünf später starben, was insgesamt mehr als drei Todesopfer vermuten ließe; die Depeschen vom 18. Januar nennen drei Tote. Die endgültige Zahl der Todesopfer ist nicht belegt.',
                'sources' => ['wp-liste-1911', 'delpher-zuidwillemsvaart-1911-01-18'],
            ],
        ],
    ],
    'corrections' => [],
];
