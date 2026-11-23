<?php

// Contexte sourcé du déraillement de la gare du Nord à Paris du 14 juillet 1922 (recherche du 21/11/2026).
return [
    'slug' => '1922-07-14-paris',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1922' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1922',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Juillet',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1922&oldid=236708402#Juillet',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : 14 juillet, vers 18 h 10, train venant d\'Écouen en gare du Nord, aiguillage manoeuvré pendant le passage, rupture d\'attelage et déraillement de deux voitures contre le pont Saint-Ange, 2 morts et une trentaine de blessés.',
        ],
        'delpher-1922-07-15a' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Frankrijk',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1922-07-15',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMKB19:000163013:mpeg21:a00052',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Article du 15 juillet : train de fêtards cassé en deux à l\'entrée de la gare du Nord, 50 blessés dont 16 en danger, 4 morts, aiguillage mal placé.',
        ],
        'delpher-1922-07-15b' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Buitenlandsche Berichten',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1922-07-15',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMRAZ02:000408014:mpeg21:a00057',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : train déraillé près de la gare du Nord à Paris, 2 morts et 50 blessés.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 14 juillet 1922 en début de soirée (vers 18 h 10 selon la liste de Wikipédia), un train venant d\'Écouen, bondé de personnes allant fêter le 14 juillet, arrivait à la gare du Nord, à Paris.',
                'en' => 'On the early evening of 14 July 1922 (at about 6.10 p.m. according to the Wikipedia list), a train from Écouen, packed with people going to celebrate Bastille Day, was arriving at the Gare du Nord in Paris.',
                'de' => 'Am frühen Abend des 14. Juli 1922 (laut Wikipedia-Liste gegen 18.10 Uhr) fuhr ein aus Écouen kommender, mit Feiernden des 14. Juli überfüllter Zug in den Pariser Gare du Nord ein.',
                'sources' => ['wp-liste-1922', 'delpher-1922-07-15a', 'delpher-1922-07-15b'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le train s\'est rompu en deux : la partie avant est entrée en gare, la partie arrière a heurté les butoirs. Selon la liste de Wikipédia, un changement brutal d\'aiguillage au passage du train a provoqué la rupture d\'attelage et le déraillement de deux voitures, écrasées contre les piles du pont du boulevard de la Chapelle (pont Saint-Ange). Plusieurs voitures de troisième classe ont versé et l\'une a été entièrement broyée.',
                'en' => 'The train broke in two: the front part entered the station, the rear part hit the buffer stops. According to the Wikipedia list, a sudden change of points as the train passed caused the coupling to break and two coaches to derail, crushed against the piers of the Boulevard de la Chapelle bridge (Pont Saint-Ange). Several third-class coaches overturned and one was completely crushed.',
                'de' => 'Der Zug riss in zwei Teile: Der vordere Teil fuhr in den Bahnhof ein, der hintere prallte gegen die Prellböcke. Laut Wikipedia-Liste löste ein plötzliches Umstellen der Weiche bei der Durchfahrt den Kupplungsbruch und die Entgleisung zweier Wagen aus, die gegen die Pfeiler der Brücke am Boulevard de la Chapelle (Pont Saint-Ange) gedrückt wurden. Mehrere Wagen dritter Klasse kippten um, einer wurde völlig zerdrückt.',
                'sources' => ['delpher-1922-07-15a', 'wp-liste-1922'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Le ministère des Travaux publics a attribué l\'accident à une mauvaise position d\'un aiguillage, selon l\'article du 15 juillet.',
                'en' => 'The Ministry of Public Works attributed the accident to a wrongly set set of points, according to the article of 15 July.',
                'de' => 'Das Ministerium für öffentliche Arbeiten führte den Unfall laut Artikel vom 15. Juli auf eine falsch gestellte Weiche zurück.',
                'sources' => ['delpher-1922-07-15a'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'La liste de Wikipédia compte deux morts et une trentaine de blessés. Le 15 juillet, la presse néerlandaise compte cinquante blessés dont seize en danger ; une brève parle de deux morts, un article de quatre blessés déjà décédés.',
                'en' => 'The Wikipedia list counts two dead and about thirty injured. On 15 July the Dutch press counts fifty injured, sixteen of them in danger; one item speaks of two dead, one article of four injured people who had already died.',
                'de' => 'Die Wikipedia-Liste zählt zwei Tote und etwa dreißig Verletzte. Am 15. Juli zählt die niederländische Presse fünfzig Verletzte, davon sechzehn in Lebensgefahr; eine Notiz spricht von zwei Toten, ein Artikel von vier bereits gestorbenen Verletzten.',
                'sources' => ['wp-liste-1922', 'delpher-1922-07-15a', 'delpher-1922-07-15b'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le bilan varie : 2 morts et une trentaine de blessés (liste), 2 morts et 50 blessés (brève), 4 morts et 50 blessés (article). Le lieu est la gare du Nord, à Paris, et non Écouen, commune d\'origine du train.',
                'en' => 'The toll varies: 2 dead and about thirty injured (list), 2 dead and 50 injured (item), 4 dead and 50 injured (article). The place is the Gare du Nord in Paris, not Écouen, the train\'s origin.',
                'de' => 'Die Bilanz schwankt: 2 Tote und etwa dreißig Verletzte (Liste), 2 Tote und 50 Verletzte (Notiz), 4 Tote und 50 Verletzte (Artikel). Der Ort ist der Pariser Gare du Nord, nicht Écouen, der Herkunftsort des Zuges.',
                'sources' => ['wp-liste-1922', 'delpher-1922-07-15a', 'delpher-1922-07-15b'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'commune_insee',
            'value' => '75056',
            'sources' => ['wp-liste-1922', 'delpher-1922-07-15a'],
            'fr' => 'L\'accident a eu lieu à la gare du Nord, à Paris, et non à Écouen, d\'où venait le train.',
            'en' => 'The accident took place at the Gare du Nord in Paris, not at Écouen, where the train came from.',
            'de' => 'Der Unfall ereignete sich im Gare du Nord in Paris, nicht in Écouen, dem Herkunftsort des Zuges.',
        ],
        [
            'field' => 'kind',
            'value' => 'derailment',
            'sources' => ['wp-liste-1922', 'delpher-1922-07-15a', 'delpher-1922-07-15b'],
            'fr' => 'Le train s\'est rompu en deux et des voitures ont déraillé et versé : il s\'agit d\'un déraillement.',
            'en' => 'The train broke in two and coaches derailed and overturned: this was a derailment.',
            'de' => 'Der Zug riss in zwei Teile, und Wagen entgleisten und kippten um: Es handelte sich um eine Entgleisung.',
        ],
    ],
];
