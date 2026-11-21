<?php

// Contexte sourcé de la collision d'Arveyres du 2 octobre 1907 (recherche du 21/11/2026).
return [
    'slug' => '1907-10-02-arveyres',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1907' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1907',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Octobre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1907&oldid=238956681#Octobre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Gare d\'Arveyres, ligne Paris-Bordeaux, voie unique établie après un premier accident, le rapide Paris-Bordeaux tamponne à 7 h un train mixte à l\'arrêt, un mort (le chef du train tamponneur) et une vingtaine de blessés.',
        ],
        'delpher-zutphensche-1907-10-04' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Spoorwegongeluk',
            'publisher' => 'Zutphensche Courant (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1907-10-04',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMRAZ02:000369083:mpeg21:a00012',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Un train de Paris entré en collision avec un train de Bordeaux à Arveyres, douze voyageurs légèrement blessés et trois plus gravement.',
        ],
        'delpher-leeuwarder-1907-10-04' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Spoorwegongeluk in Frankrijk',
            'publisher' => 'Leeuwarder Courant (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1907-10-04',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010598032:mpeg21:a0014',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Un express de Paris à Bordeaux heurte un autre train à Arveyres, huit blessés graves et douze légers.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le matin du 2 octobre 1907 selon la liste de Wikipédia, à la gare d\'Arveyres, peu après Libourne sur la ligne de Paris à Bordeaux, le rapide de Paris à Bordeaux est entré en collision avec un autre train, d\'après la presse néerlandaise du 4 octobre. La liste de Wikipédia précise qu\'il a tamponné vers 7 h un train mixte à l\'arrêt, sur une voie unique établie après un premier accident.',
                'en' => 'On the morning of 2 October 1907 according to the Wikipedia list, at Arveyres station, shortly after Libourne on the Paris to Bordeaux line, the Paris to Bordeaux express collided with another train, according to the Dutch press of 4 October. The Wikipedia list adds that it ran into a stationary mixed train at about 7 am, on a single track laid after an earlier accident.',
                'de' => 'Am Morgen des 2. Oktober 1907 nach der Wikipedia-Liste stieß im Bahnhof Arveyres kurz hinter Libourne an der Strecke Paris-Bordeaux der Schnellzug von Paris nach Bordeaux nach der niederländischen Presse vom 4. Oktober mit einem anderen Zug zusammen. Die Wikipedia-Liste ergänzt, er sei gegen 7 Uhr auf einen haltenden gemischten Zug aufgefahren, und zwar auf einem nach einem früheren Unfall eingerichteten eingleisigen Abschnitt.',
                'sources' => ['delpher-zutphensche-1907-10-04', 'delpher-leeuwarder-1907-10-04', 'wp-liste-1907'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Les dépêches néerlandaises parlent de douze voyageurs légèrement blessés et de trois plus gravement, ou de huit blessés graves et douze légers ; aucun mort n\'y est signalé. La liste de Wikipédia compte un mort, le chef du train tamponneur, et une vingtaine de blessés.',
                'en' => 'The Dutch dispatches speak of twelve passengers slightly injured and three more seriously, or of eight seriously and twelve slightly injured; no deaths are reported in them. The Wikipedia list counts one dead, the head of the striking train, and about twenty injured.',
                'de' => 'Die niederländischen Depeschen sprechen von zwölf leicht und drei schwerer verletzten Reisenden oder von acht Schwer- und zwölf Leichtverletzten; Todesfälle werden darin nicht gemeldet. Die Wikipedia-Liste zählt einen Toten, den Zugführer des auffahrenden Zuges, und etwa zwanzig Verletzte.',
                'sources' => ['delpher-zutphensche-1907-10-04', 'delpher-leeuwarder-1907-10-04', 'wp-liste-1907'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La date diffère : la liste de Wikipédia donne le 2 octobre 1907, alors que les journaux néerlandais datés du 4 octobre parlent de « hier matin », ce qui désignerait le 3 octobre. Le bilan des blessés varie (15 ou 20 selon les dépêches, une vingtaine pour la liste) et le mort indiqué par la liste de Wikipédia n\'apparaît pas dans les dépêches néerlandaises du 4 octobre 1907.',
                'en' => 'The date differs: the Wikipedia list gives 2 October 1907, whereas the Dutch newspapers dated 4 October speak of "yesterday morning", which would mean 3 October. The toll of injured varies (15 or 20 according to the dispatches, about twenty in the list) and the death given by the Wikipedia list does not appear in the Dutch dispatches of 4 October 1907.',
                'de' => 'Das Datum weicht ab: Die Wikipedia-Liste nennt den 2. Oktober 1907, während die niederländischen Zeitungen vom 4. Oktober von „gestern Morgen“ sprechen, was den 3. Oktober bedeuten würde. Die Zahl der Verletzten schwankt (15 oder 20 nach den Depeschen, etwa zwanzig in der Liste), und der von der Wikipedia-Liste genannte Todesfall erscheint nicht in den niederländischen Depeschen vom 4. Oktober 1907.',
                'sources' => ['delpher-zutphensche-1907-10-04', 'delpher-leeuwarder-1907-10-04', 'wp-liste-1907'],
            ],
        ],
    ],
    'corrections' => [],
];
