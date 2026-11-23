<?php

// Contexte sourcé de la collision en gare de Sillé-le-Guillaume du 28 novembre 1908 (recherche du 21/11/2026).
return [
    'slug' => '1908-11-28-sille-le-guillaume',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1908' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1908',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Novembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1908&oldid=233618338#Novembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Express Paris-Brest aiguillé par erreur sur une voie de garage, vers 12 h 30, trois morts et une dizaine de blessés dont un décédé ensuite ; renvoie à Le Petit Parisien du 29 novembre et Le Temps du 2 décembre 1908 (non lus).',
        ],
        'delpher-delftsche-1908-11-30' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Buitenlandsche Berichten',
            'publisher' => 'Delftsche Courant (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1908-11-30',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMKB08:000139452:mpeg21:a0003',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Sillé-le-Guillaume : erreur de manoeuvre des aiguilles, express de Brest contre un omnibus, samedi, deux morts et dix blessés.',
            'facts' => ['date' => '1908-11-28', 'commune_insee' => '72334', 'deaths' => 2, 'deaths_provisional' => true],
        ],
        'delpher-overijsselsche-1908-12-01' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Provinciale Overijsselsche en Zwolsche Courant du 1er décembre 1908',
            'publisher' => 'Provinciale Overijsselsche en Zwolsche Courant (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1908-12-01',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMHCO01:000085027:mpeg21:a0001',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même information : mauvaise position d\'aiguille, deux morts et dix blessés.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le samedi 28 novembre 1908, vers 12 h 30, en gare de Sillé-le-Guillaume (Sarthe), sur la ligne de Paris à Rennes selon la liste de Wikipédia, l\'express de Paris à Brest a été aiguillé par erreur sur la voie de garage où attendait l\'omnibus qu\'il devait dépasser.',
                'en' => 'On Saturday 28 November 1908, at about 12.30 pm, at Sillé-le-Guillaume station (Sarthe), on the Paris to Rennes line according to the Wikipedia list, the Paris to Brest express was routed by mistake onto the siding where the stopping train it was due to overtake was waiting.',
                'de' => 'Am Samstag, dem 28. November 1908, wurde gegen 12.30 Uhr im Bahnhof Sillé-le-Guillaume (Sarthe), nach der Wikipedia-Liste auf der Strecke von Paris nach Rennes, der Schnellzug von Paris nach Brest irrtümlich auf das Abstellgleis geleitet, auf dem der Personenzug wartete, den er überholen sollte.',
                'sources' => ['wp-liste-1908', 'delpher-delftsche-1908-11-30'],
            ],
        ],
        'course' => [
            [
                'fr' => 'L\'express a percuté l\'omnibus et broyé son fourgon de queue ainsi que sa dernière voiture, selon la liste de Wikipédia. La presse néerlandaise du 30 novembre parle d\'une erreur dans la manoeuvre des aiguilles et d\'un express de Brest entré dans un omnibus.',
                'en' => 'The express ran into the stopping train and crushed its rear van and last coach, according to the Wikipedia list. The Dutch press of 30 November speaks of a mistake in operating the points and of an express from Brest running into a stopping train.',
                'de' => 'Der Schnellzug prallte auf den Personenzug und zerdrückte nach der Wikipedia-Liste dessen Schlusspackwagen und letzten Wagen. Die niederländische Presse vom 30. November spricht von einem Fehler bei der Weichenbedienung und von einem Schnellzug aus Brest, der in einen Personenzug fuhr.',
                'sources' => ['wp-liste-1908', 'delpher-delftsche-1908-11-30', 'delpher-overijsselsche-1908-12-01'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Selon la liste de Wikipédia, l\'accident a fait trois morts et une dizaine de blessés, dont l\'un est mort quelques jours plus tard. Les dépêches néerlandaises des premiers jours parlent de deux morts et dix blessés.',
                'en' => 'According to the Wikipedia list, the accident killed three and injured about ten, one of whom died a few days later. The Dutch dispatches of the first days speak of two dead and ten injured.',
                'de' => 'Nach der Wikipedia-Liste starben bei dem Unfall drei Menschen, und etwa zehn wurden verletzt, von denen einer einige Tage später starb. Die niederländischen Depeschen der ersten Tage sprechen von zwei Toten und zehn Verletzten.',
                'sources' => ['wp-liste-1908', 'delpher-delftsche-1908-11-30', 'delpher-overijsselsche-1908-12-01'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La cause est une erreur d\'aiguillage : l\'express a été dirigé sur la voie occupée, ce que disent la liste de Wikipédia et les dépêches néerlandaises (mauvaise position de l\'aiguille).',
                'en' => 'The cause was a routing error: the express was sent onto the occupied track, as the Wikipedia list and the Dutch dispatches (wrong position of the points) both say.',
                'de' => 'Die Ursache war ein Weichenfehler: Der Schnellzug wurde auf das besetzte Gleis geleitet, wie die Wikipedia-Liste und die niederländischen Depeschen (falsche Weichenstellung) übereinstimmend sagen.',
                'sources' => ['wp-liste-1908', 'delpher-overijsselsche-1908-12-01'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le bilan diffère : trois morts selon la liste de Wikipédia, dont un blessé mort quelques jours plus tard, et deux morts dans les dépêches des 30 novembre et 1er décembre 1908, qui ne mentionnent pas ce décès.',
                'en' => 'The toll differs: three dead according to the Wikipedia list, one of them an injured person who died a few days later, and two dead in the dispatches of 30 November and 1 December 1908, which do not mention that death.',
                'de' => 'Die Bilanz weicht ab: drei Tote nach der Wikipedia-Liste, darunter ein Verletzter, der einige Tage später starb, und zwei Tote in den Depeschen vom 30. November und 1. Dezember 1908, die diesen Todesfall nicht erwähnen.',
                'sources' => ['wp-liste-1908', 'delpher-delftsche-1908-11-30', 'delpher-overijsselsche-1908-12-01'],
            ],
        ],
    ],
    'corrections' => [],
];
