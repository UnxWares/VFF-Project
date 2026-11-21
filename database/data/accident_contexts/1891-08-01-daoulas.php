<?php

// Contexte sourcé du déraillement d'un train Brest - Quimper entre Dirinon et Daoulas, ligne de Savenay à Landerneau, d'août 1891 (recherche du 21/11/2026).
return [
    'slug' => '1891-08-01-daoulas',
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
            'locator' => '1891',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1891',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 1er août 1891, d\'après Le Matin du 2 août : sur la ligne de Landerneau à Nantes, à 8 h, un train de voyageurs Brest - Quimper déraille entre Dirinon et Daoulas ; le mécanicien est tué, le chauffeur grièvement blessé, les voyageurs indemnes.',
        ],
        'ddb-1891-08-05-fremdenblatt' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Lille, 4. August',
            'publisher' => 'Hamburger Fremdenblatt (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1891-08-05',
            'locator' => 'p. 13',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/AXGMYOIKVHORSVL424AK4OCFEQT7DO22?issuepage=13',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche datée de Lille du 4 août (reprise par plusieurs journaux du 5 au 7 août) : entre Dirinon et Daoulas, un train de voyageurs de Brest à Quimper a déraillé ; le mécanicien tué, le chauffeur grièvement blessé, les voyageurs indemnes ; dégâts matériels très importants.',
        ],
        'ddb-1891-08-05-iserlohn' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Lille, 4. Aug.',
            'publisher' => 'Iserlohner Kreisanzeiger (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1891-08-05',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/L2AUNGFAI4OTMC5GXN6PABHHEKGC7RC6?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même dépêche : déraillement d\'un train Brest - Quimper entre Dirinon et Daoulas, mécanicien tué, chauffeur grièvement blessé, voyageurs indemnes.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 1er août 1891, vers 8 h, un train de voyageurs de Brest à Quimper a déraillé entre Dirinon et Daoulas, sur la ligne de Landerneau à Nantes. Les dégâts matériels ont été très importants.',
                'en' => 'On 1 August 1891, at about 8 am, a Brest to Quimper passenger train derailed between Dirinon and Daoulas on the Landerneau to Nantes line. The damage was very heavy.',
                'de' => 'Am 1. August 1891 gegen 8 Uhr entgleiste ein Personenzug von Brest nach Quimper zwischen Dirinon und Daoulas auf der Strecke Landerneau-Nantes. Der Sachschaden war sehr bedeutend.',
                'sources' => ['wp-liste-1890', 'ddb-1891-08-05-fremdenblatt'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien a été tué et le chauffeur grièvement blessé. Les voyageurs n\'ont pas été blessés.',
                'en' => 'The driver was killed and the stoker seriously injured. The passengers were unhurt.',
                'de' => 'Der Lokführer wurde getötet, der Heizer schwer verletzt. Die Reisenden blieben unverletzt.',
                'sources' => ['wp-liste-1890', 'ddb-1891-08-05-fremdenblatt', 'ddb-1891-08-05-iserlohn'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1890', 'ddb-1891-08-05-fremdenblatt', 'ddb-1891-08-05-iserlohn'],
            'fr' => 'Mécanicien tué selon la liste de Wikipédia (Le Matin du 2 août 1891) et la dépêche du 4 août ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Driver killed according to the Wikipedia list (Le Matin of 2 August 1891) and the dispatch of 4 August; the record gave no number of deaths.',
            'de' => 'Lokführer getötet nach der Wikipedia-Liste (Le Matin vom 2. August 1891) und der Depesche vom 4. August; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
