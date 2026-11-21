<?php

// Contexte sourcé du déraillement d'une rame de coke sur le chemin de fer de ceinture de Nancy, près de Jarville, de septembre 1907 (recherche du 21/11/2026).
return [
    'slug' => '1907-09-01-jarville-la-malgrange',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1907' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1907',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Septembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1907&oldid=238956681#Septembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 1er septembre 1907, d\'après Le Petit Parisien du 2 septembre : à Nancy, vers 11 h, sur le chemin de fer de ceinture entre Champigneulles et Jarville, une rame de coke pour les hauts-fourneaux de Jarville déraille à un passage à niveau ; la locomotive se renverse et abat un mur de briques ; le mécanicien et le chauffeur sont tués.',
        ],
        'noordbrabantsche-1907-09-03' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Provinciale Noordbrabantsche en \'s-Hertogenbossche courant du 3 septembre 1907',
            'publisher' => 'Provinciale Noordbrabantsche en \'s-Hertogenbossche courant',
            'author' => null,
            'published' => '1907-09-03',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMSADB01:000064027:mpeg21:a0007',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; dépêche d\'agence. Brève : déraillement la veille sur le chemin de fer de ceinture de Nancy ; un mécanicien et un chauffeur tués, un conducteur grièvement blessé.',
        ],
        'huisgezin-1907-09-03' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Het huisgezin du 3 septembre 1907',
            'publisher' => 'Het huisgezin',
            'author' => null,
            'published' => '1907-09-03',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMKB19:000004101:mpeg21:a00010',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; dépêche d\'agence. Brève : près de Nancy, un train a déraillé samedi ; le chauffeur et le mécanicien ont été tués sur le coup, le chef de train grièvement blessé.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 1er septembre 1907 vers 11 h, sur le chemin de fer de ceinture de Nancy, entre Champigneulles et Jarville, une rame de coke destinée aux hauts-fourneaux de Jarville a déraillé à un passage à niveau. La locomotive s\'est renversée et a abattu un mur de briques.',
                'en' => 'At about 11 am on 1 September 1907, on the Nancy belt railway between Champigneulles and Jarville, a train of coke for the Jarville blast furnaces derailed at a level crossing. The engine overturned and brought down a brick wall.',
                'de' => 'Am 1. September 1907 gegen 11 Uhr entgleiste auf der Gürtelbahn von Nancy zwischen Champigneulles und Jarville an einem Bahnübergang ein Kokszug für die Hochöfen von Jarville. Die Lokomotive stürzte um und riss eine Ziegelmauer ein.',
                'sources' => ['wp-liste-1907', 'noordbrabantsche-1907-09-03'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien et le chauffeur ont été tués. La presse néerlandaise ajoute un conducteur, ou chef de train, grièvement blessé.',
                'en' => 'The driver and the stoker were killed. The Dutch press adds a guard who was badly hurt.',
                'de' => 'Lokomotivführer und Heizer wurden getötet. Die niederländische Presse nennt außerdem einen schwer verletzten Zugbegleiter.',
                'sources' => ['wp-liste-1907', 'noordbrabantsche-1907-09-03', 'huisgezin-1907-09-03'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Date : la liste de Wikipédia et la brève du 3 septembre qui dit « hier » placent l\'accident le dimanche 1er septembre ou le 2 ; Het huisgezin parle de samedi, soit le 31 août.',
                'en' => 'Date: the Wikipedia list and the item of 3 September saying "yesterday" place the accident on Sunday 1 September or on the 2nd; Het huisgezin says Saturday, which was 31 August.',
                'de' => 'Datum: Die Wikipedia-Liste und die Kurzmeldung vom 3. September („gestern“) legen den Unfall auf Sonntag, den 1. September, oder den 2.; Het huisgezin nennt Samstag, also den 31. August.',
                'sources' => ['wp-liste-1907', 'noordbrabantsche-1907-09-03', 'huisgezin-1907-09-03'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1907', 'noordbrabantsche-1907-09-03', 'huisgezin-1907-09-03'],
            'fr' => 'Mécanicien et chauffeur tués selon la liste de Wikipédia (Le Petit Parisien du 2 septembre 1907) et la presse néerlandaise du 3 ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Driver and stoker killed according to the Wikipedia list (Le Petit Parisien of 2 September 1907) and the Dutch press of the 3rd; the record gave no number of deaths.',
            'de' => 'Lokomotivführer und Heizer getötet nach der Wikipedia-Liste (Le Petit Parisien vom 2. September 1907) und der niederländischen Presse vom 3.; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
