<?php

// Contexte sourcé de l'accident de Douai du 22 décembre 1919 (recherche du 21/11/2026).
return [
    'slug' => '1919-12-22-douai',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1919' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1919',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Décembre, 22 décembre 1919',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1919&oldid=241133344',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée détaillée, appuyée sur Le Petit Parisien du 23 décembre 1919, page 1 (Gallica, non lu).',
        ],
        'gallica-petit-parisien-1919-12-23' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Parisien du 23 décembre 1919, page 1',
            'publisher' => 'Le Petit Parisien',
            'author' => null,
            'published' => '1919-12-23',
            'locator' => 'page 1',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k567096r.r=tamponne.langFR',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Compte rendu contemporain cité par la liste de Wikipédia ; non lu (Gallica).',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 22 décembre 1919, vers 14 h 30, peu avant l\'entrée de la gare de Douai (Nord), sur la ligne de Paris-Nord à Lille, un express pour Paris était arrêté à un carré (signal fermé).',
                'en' => 'On 22 December 1919, at about 2.30 pm, shortly before the entrance to Douai station (Nord), on the Paris-Nord to Lille line, an express for Paris was standing at a closed signal.',
                'de' => 'Am 22. Dezember 1919, gegen 14.30 Uhr, stand kurz vor der Einfahrt in den Bahnhof Douai (Nord) an der Strecke Paris-Nord - Lille ein Schnellzug nach Paris an einem geschlossenen Signal.',
                'sources' => ['wp-liste-1919'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Un train de marchandises venant de Dunkerque, arrivé par une ligne adjacente, a percuté l\'arrière de l\'express. Le fourgon et les trois voitures de queue ont été broyés.',
                'en' => 'A freight train from Dunkirk, arriving on an adjacent line, ran into the rear of the express. The luggage van and the last three coaches were crushed.',
                'de' => 'Ein aus Dünkirchen kommender Güterzug fuhr auf einem benachbarten Gleis heran und prallte auf das Ende des Schnellzugs. Der Packwagen und die letzten drei Wagen wurden zerquetscht.',
                'sources' => ['wp-liste-1919'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le bilan est de douze morts et quarante-cinq blessés d\'après la liste de Wikipédia, qui renvoie au Petit Parisien du 23 décembre 1919.',
                'en' => 'The toll is twelve dead and forty-five injured according to the Wikipedia list, which refers to Le Petit Parisien of 23 December 1919.',
                'de' => 'Die Bilanz lautet laut der Wikipedia-Liste zwölf Tote und fünfundvierzig Verletzte; sie verweist auf Le Petit Parisien vom 23. Dezember 1919.',
                'sources' => ['wp-liste-1919'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Toutes les données reposent sur une seule entrée de liste. Aucune enquête, aucun jugement ni aucun récit local n\'ont été trouvés ; les causes précises (franchissement du signal par le train de marchandises, conditions de visibilité) ne sont pas documentées. Le point kilométrique de la fiche, rattaché à deux lignes, est à vérifier : la liste ne cite que la ligne de Paris-Nord à Lille.',
                'en' => 'All the data rest on a single list entry. No inquiry, court ruling or local account was found; the precise causes (the freight train passing the signal, visibility conditions) are not documented. The record\'s kilometre point, attached to two lines, needs checking: the list cites only the Paris-Nord to Lille line.',
                'de' => 'Alle Angaben beruhen auf einem einzigen Listeneintrag. Eine Untersuchung, ein Urteil oder ein lokaler Bericht wurde nicht gefunden; die genauen Ursachen (Überfahren des Signals durch den Güterzug, Sichtverhältnisse) sind nicht dokumentiert. Der Kilometerpunkt des Datensatzes, der zwei Strecken zugeordnet ist, ist zu prüfen: Die Liste nennt nur die Strecke Paris-Nord - Lille.',
                'sources' => ['wp-liste-1919'],
            ],
        ],
    ],
    'corrections' => [],
];
