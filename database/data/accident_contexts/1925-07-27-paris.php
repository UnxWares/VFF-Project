<?php

// Contexte sourcé de la collision de la gare de l'Est à Paris du 27 juillet 1925 (recherche du 21/11/2026).
return [
    'slug' => '1925-07-27-paris',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1925' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1925',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'année 1925',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1925&oldid=235357727#1925',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : 27 juillet à minuit, avant-gare de Paris-Est, rapide Bâle-Paris sans freins contre une machine haut-le-pied à 80 km/h sous le pont de l\'Aqueduc, 3 morts et 16 blessés.',
        ],
        'delpher-1925-07-28' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Spoorwegongeluk te Parijs',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Bibliothèque royale des Pays-Bas)',
            'author' => null,
            'published' => '1925-07-28',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010009821:mpeg21:a0106',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche Havas du 28 juillet : le train de Bâle est entré de nuit dans une locomotive à la gare de l\'Est, 18 blessés légers et 3 blessés graves.',
        ],
        'delpher-1925-07-29' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Frankrijk. Het spoorwegongeluk te Parijs',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Bibliothèque royale des Pays-Bas)',
            'author' => null,
            'published' => '1925-07-29',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010660099:mpeg21:a0168',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Détails tirés de la presse française : lundi soir vers minuit, locomotive du Bâle en travers des rails, deux voitures déraillées, fourgon écrasé, 3 blessés graves et 19 légers.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Dans la nuit du lundi 27 au mardi 28 juillet 1925, vers minuit, l\'express venant de Bâle est entré à la gare de l\'Est, à Paris, dans une locomotive isolée. La liste de Wikipédia précise que l\'express roulait à 80 km/h sous le pont de l\'Aqueduc, en panne de freins, et que la machine était une haut-le-pied qui regagnait le dépôt de La Villette.',
                'en' => 'In the night from Monday 27 to Tuesday 28 July 1925, at about midnight, the express from Basel ran into a loose locomotive at the Gare de l\'Est in Paris. The Wikipedia list adds that the express was travelling at 80 km/h under the Aqueduc bridge with failed brakes, and that the engine was a light engine returning to La Villette depot.',
                'de' => 'In der Nacht von Montag, dem 27., auf Dienstag, den 28. Juli 1925, gegen Mitternacht fuhr der Schnellzug aus Basel im Pariser Gare de l\'Est auf eine einzelne Lokomotive auf. Die Wikipedia-Liste ergänzt, der Schnellzug sei mit 80 km/h unter der Brücke de l\'Aqueduc und mit ausgefallenen Bremsen gefahren, die Maschine sei eine allein fahrende Lokomotive auf dem Weg zum Depot La Villette gewesen.',
                'sources' => ['delpher-1925-07-28', 'delpher-1925-07-29', 'wp-liste-1925'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Sous le choc, la locomotive de l\'express s\'est couchée en travers des voies, les deux voitures suivantes ont déraillé et le fourgon a été écrasé. Le chauffeur de la locomotive tamponnée, projetée par le choc, a sauté à temps et s\'en est sorti indemne ; il n\'a pas pu expliquer la collision.',
                'en' => 'On impact the express locomotive came to lie across the tracks, the next two coaches derailed and the baggage van was crushed. The fireman of the struck locomotive, which was thrown aside by the impact, jumped off in time and was unhurt; he could not explain the collision.',
                'de' => 'Beim Aufprall kam die Lokomotive des Schnellzugs quer über den Gleisen zu liegen, die nächsten beiden Wagen entgleisten und der Gepäckwagen wurde zerdrückt. Der heruntergesprungene Heizer der gerammten Lokomotive, die zur Seite geschleudert wurde, blieb unverletzt; er konnte die Kollision nicht erklären.',
                'sources' => ['delpher-1925-07-29'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'La presse néerlandaise des 28 et 29 juillet compte trois blessés graves (un Américain habitant Paris, le chauffeur de l\'express de Bâle et un agent des chemins de fer installé dans la première voiture) et dix-huit ou dix-neuf blessés légers, qui ont pu rentrer chez eux après pansement. La liste de Wikipédia donne trois morts et seize blessés.',
                'en' => 'The Dutch press of 28 and 29 July counts three seriously injured (an American living in Paris, the fireman of the Basel express and a railway employee in the first coach) and eighteen or nineteen slightly injured, who were able to go home after being bandaged. The Wikipedia list gives three dead and sixteen injured.',
                'de' => 'Die niederländische Presse vom 28. und 29. Juli zählt drei Schwerverletzte (einen in Paris lebenden Amerikaner, den Heizer des Basler Schnellzugs und einen Eisenbahnbediensteten im ersten Wagen) und achtzehn oder neunzehn Leichtverletzte, die nach dem Verbinden nach Hause gehen konnten. Die Wikipedia-Liste nennt drei Tote und sechzehn Verletzte.',
                'sources' => ['delpher-1925-07-28', 'delpher-1925-07-29', 'wp-liste-1925'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Les dépêches néerlandaises ne mentionnent aucun mort : elles comptent trois blessés graves, là où la liste de Wikipédia compte trois morts. Le nombre de blessés diffère aussi (21 ou 22 selon la presse néerlandaise, 16 selon la liste).',
                'en' => 'The Dutch dispatches mention no dead: they count three seriously injured where the Wikipedia list counts three dead. The number of injured also differs (21 or 22 according to the Dutch press, 16 according to the list).',
                'de' => 'Die niederländischen Meldungen erwähnen keinen Toten: Sie zählen drei Schwerverletzte, wo die Wikipedia-Liste drei Tote zählt. Auch die Zahl der Verletzten weicht ab (21 oder 22 laut niederländischer Presse, 16 laut Liste).',
                'sources' => ['delpher-1925-07-28', 'delpher-1925-07-29', 'wp-liste-1925'],
            ],
        ],
    ],
    'corrections' => [],
];
