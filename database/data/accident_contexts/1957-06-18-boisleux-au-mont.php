<?php

// Contexte sourcé de l'accident de Boisleux-au-Mont du 18 juin 1957 (recherche du 21/11/2026).
return [
    'slug' => '1957-06-18-boisleux-au-mont',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-gare-boisleux' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Gare de Boisleux',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'section sur l\'accident de 1957',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Gare_de_Boisleux&oldid=238682914',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Récit détaillé, appuyé sur un article de La Voix du Nord du 17 juin 2007 (non lu, sans lien) et sur Le Monde du 20 juin 1957.',
        ],
        'wp-liste-1950' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1950',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1957, 18 juin',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1950&oldid=235357701',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée d\'une ligne (18 juin 1957, déraillement, onze morts), qui cite La Voix du Nord (2007) et Le Monde (20 juin 1957).',
        ],
        'lemonde-1957-06-20' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le déraillement de l\'express Tourcoing-Paris a fait huit morts et vingt blessés dont trois grièvement atteints',
            'publisher' => 'Le Monde',
            'author' => null,
            'published' => '1957-06-20',
            'locator' => 'archives en ligne',
            'url' => 'https://www.lemonde.fr/archives/article/1957/06/20/le-deraillement-de-l-express-tourcoing-paris-a-fait-huit-morts-et-vingt-blesses-dont-trois-grievement-atteints_2318210_1819218.html',
            'access' => 'paywall',
            'consulted_on' => '2026-11-21',
            'note' => 'Article d\'archives, accès refusé à la lecture ; le titre donne le premier bilan (huit morts, vingt blessés).',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 18 juin 1957, en début d\'après-midi, l\'express 326 de Tourcoing à Paris traversait la gare de Boisleux (Pas-de-Calais), peu après son départ d\'Arras. Des travaux de rénovation en vue de l\'électrification de la ligne de Paris-Nord à Lille étaient en cours ; ce train devait être le dernier à emprunter cette voie ce jour-là avant la reprise du chantier.',
                'en' => 'On 18 June 1957, in the early afternoon, express 326 from Tourcoing to Paris was passing through Boisleux station (Pas-de-Calais), shortly after leaving Arras. Renovation work for the electrification of the Paris-Nord to Lille line was under way; this train was meant to be the last on that track that day before work resumed.',
                'de' => 'Am 18. Juni 1957, am frühen Nachmittag, durchfuhr der Schnellzug 326 von Tourcoing nach Paris kurz nach der Abfahrt in Arras den Bahnhof Boisleux (Pas-de-Calais). Auf der Strecke Paris-Nord - Lille liefen Erneuerungsarbeiten im Hinblick auf die Elektrifizierung; dieser Zug sollte an dem Tag der letzte auf diesem Gleis sein, bevor die Baustelle wieder aufgenommen wurde.',
                'sources' => ['wp-gare-boisleux'],
            ],
        ],
        'course' => [
            [
                'fr' => 'L\'arrière du train a déraillé à 80 km/h près de la gare. Le fourgon postal a quitté les rails et heurté un support de caténaire ; les voitures 11 et 12 se sont couchées, la 11 étant éventrée par la 12.',
                'en' => 'The rear of the train derailed at 80 km/h near the station. The mail van left the rails and hit a catenary support; coaches 11 and 12 overturned, coach 11 being ripped open by coach 12.',
                'de' => 'Das Zugende entgleiste mit 80 km/h in der Nähe des Bahnhofs. Der Postwagen verließ die Gleise und prallte gegen einen Fahrleitungsmast; die Wagen 11 und 12 kippten um, wobei Wagen 11 von Wagen 12 aufgerissen wurde.',
                'sources' => ['wp-gare-boisleux'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un premier bilan faisait état de huit morts et de vingt blessés, dont trois graves ; le bilan final est de onze morts. Les blessés ont été conduits dans les hôpitaux d\'Arras, et le bâtiment voyageurs de Boisleux a servi à entreposer les corps et à soigner les blessés légers.',
                'en' => 'An early toll reported eight dead and twenty injured, three of them seriously; the final toll is eleven dead. The injured were taken to the hospitals of Arras, and Boisleux station building was used to hold the bodies and treat the lightly injured.',
                'de' => 'Eine erste Bilanz nannte acht Tote und zwanzig Verletzte, darunter drei Schwerverletzte; die endgültige Zahl beträgt elf Tote. Die Verletzten kamen in die Krankenhäuser von Arras, das Empfangsgebäude von Boisleux diente zur Aufbahrung der Toten und zur Versorgung Leichtverletzter.',
                'sources' => ['wp-gare-boisleux', 'wp-liste-1950'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La cause n\'est pas établie avec certitude. La piste principale de l\'enquête, selon l\'article de la gare, est un affaissement de la voie lié à la préparation des travaux : trop de ballast aurait été retiré, ce qui rendait la voie instable même à faible vitesse.',
                'en' => 'The cause is not firmly established. According to the station article, the main line of inquiry is a track subsidence linked to the preparation of the works: too much ballast was reportedly removed, leaving the track unstable even at low speed.',
                'de' => 'Die Ursache ist nicht sicher geklärt. Laut dem Bahnhofsartikel ist die wichtigste Spur der Untersuchung eine Absenkung des Gleises im Zusammenhang mit der Vorbereitung der Arbeiten: Es sei zu viel Schotter entfernt worden, sodass das Gleis auch bei geringer Geschwindigkeit instabil war.',
                'sources' => ['wp-gare-boisleux'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Deux des quatre voies de la gare ont été rendues inutilisables. La circulation a été rétablie le lendemain, en début de soirée.',
                'en' => 'Two of the station\'s four tracks were put out of use. Traffic was restored the next day, in the early evening.',
                'de' => 'Zwei der vier Gleise des Bahnhofs waren unbenutzbar. Der Verkehr wurde am nächsten Tag am frühen Abend wiederhergestellt.',
                'sources' => ['wp-gare-boisleux'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le bilan évolue entre le premier compte rendu (huit morts, vingt blessés, titre du Monde du 20 juin 1957) et le bilan final de onze morts retenu par les deux pages de Wikipédia. Le nombre de blessés (vingt au premier bilan) n\'est pas confirmé par un bilan final et n\'est pas porté sur la fiche. Les pages de Wikipédia s\'appuient toutes sur un même article de La Voix du Nord (2007), non lu : le récit ne repose donc pas sur deux sources indépendantes.',
                'en' => 'The toll changes between the first report (eight dead, twenty injured, headline of Le Monde of 20 June 1957) and the final toll of eleven dead used by both Wikipedia pages. The injury count (twenty in the first toll) is not confirmed by a final figure and is not entered on the record. Both Wikipedia pages rest on the same La Voix du Nord article (2007), which was not read, so the account does not rest on two independent sources.',
                'de' => 'Die Bilanz ändert sich zwischen dem ersten Bericht (acht Tote, zwanzig Verletzte, Überschrift von Le Monde vom 20. Juni 1957) und der endgültigen Zahl von elf Toten, die beide Wikipedia-Seiten verwenden. Die Verletztenzahl (zwanzig in der ersten Bilanz) ist durch keine Endzahl bestätigt und wird nicht in den Datensatz übernommen. Beide Wikipedia-Seiten stützen sich auf denselben Artikel der Voix du Nord (2007), der nicht gelesen wurde; der Bericht beruht also nicht auf zwei unabhängigen Quellen.',
                'sources' => ['wp-gare-boisleux', 'wp-liste-1950'],
            ],
        ],
    ],
    'corrections' => [],
];
