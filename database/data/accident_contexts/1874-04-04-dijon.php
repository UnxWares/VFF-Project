<?php

// Contexte sourcé de la collision de l'express-poste n° 2 à la tranchée de Perrigny, le 4 avril 1874 (recherche du 21/11/2026).
return [
    'slug' => '1874-04-04-dijon',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1870' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1870',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '4 avril 1874, Perrigny',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1870&oldid=235357691#1874',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Date du 4 avril 1874, 22 h 45, à Perrigny avant Dijon ; deux morts ; cite La Presse des 6 et 7 avril 1874.',
        ],
        'vdr-dijon-1874' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1874, L\'Express-poste No 2 tamponne un train de marchandises près de Dijon',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1874-lexpress-poste-no-2-tamponne-un-train-de-marchandises-pres-de-dijon/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit La Presse du 6 avril 1874 (lettre de Dijon du 5 avril).',
        ],
        'presse-1874-04-06' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'La Presse du 6 avril 1874',
            'publisher' => 'La Presse (numérisée sur Gallica)',
            'author' => null,
            'published' => '1874-04-06',
            'locator' => null,
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k514639n/f2',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 4 avril 1874 à 22 h 45, le train express-poste n° 2, parti de Marseille à 10 h 50 du matin et de Lyon à 19 h 15, a tamponné à la tranchée de Périgny, à cinq kilomètres de Dijon, un train de marchandises arrêté sur la voie à la suite d\'un accident survenu à sa machine.',
                'en' => 'On 4 April 1874 at 10.45 pm, express mail train no. 2, which had left Marseille at 10.50 am and Lyon at 7.15 pm, ran into a goods train stopped on the track after an accident to its engine, in the Périgny cutting, five kilometres from Dijon.',
                'de' => 'Am 4. April 1874 um 22.45 Uhr fuhr der Postschnellzug Nr. 2, der um 10.50 Uhr in Marseille und um 19.15 Uhr in Lyon abgefahren war, im Einschnitt von Périgny, fünf Kilometer von Dijon entfernt, auf einen Güterzug auf, der wegen eines Maschinenschadens auf der Strecke stand.',
                'sources' => ['vdr-dijon-1874', 'wp-liste-1870'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le conducteur d\'arrière du train de marchandises a été tué sur le coup. Un voyageur du premier wagon de l\'express, dont le wagon a été entièrement brisé, est mort presque immédiatement. Le mécanicien, le chauffeur et le conducteur-chef de l\'express ont été blessés, six agents des postes légèrement contusionnés, et les autres voyageurs indemnes.',
                'en' => 'The rear guard of the goods train was killed on the spot. A passenger in the first wagon of the express, which was completely smashed, died almost at once. The driver, fireman and head guard of the express were injured, six postal staff slightly bruised, and the other passengers unhurt.',
                'de' => 'Der Schaffner am Ende des Güterzugs war sofort tot. Ein Reisender im ersten Wagen des Schnellzugs, der völlig zertrümmert wurde, starb fast sofort. Lokführer, Heizer und Zugführer des Schnellzugs wurden verletzt, sechs Postbedienstete leicht geprellt, die übrigen Reisenden blieben unverletzt.',
                'sources' => ['vdr-dijon-1874', 'wp-liste-1870'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La Presse attribue l\'accident au conducteur d\'arrière du train de marchandises, qui aurait dû, après l\'arrêt forcé du train, se porter à 800 mètres en avant du train express pour avertir que la voie n\'était pas libre. Le conducteur-chef du train de marchandises a été arrêté.',
                'en' => 'La Presse attributes the accident to the rear guard of the goods train, who, after the forced stop, should have gone 800 metres ahead of the express to warn that the line was not clear. The head guard of the goods train was arrested.',
                'de' => 'La Presse führt das Unglück auf den Schaffner am Ende des Güterzugs zurück, der nach dem erzwungenen Halt 800 Meter vor den Schnellzug hätte laufen müssen, um zu warnen, dass die Strecke nicht frei war. Der Zugführer des Güterzugs wurde festgenommen.',
                'sources' => ['vdr-dijon-1874'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'commune_insee',
            'value' => '21481',
            'sources' => ['vdr-dijon-1874', 'wp-liste-1870'],
            'fr' => 'L\'accident a eu lieu à la tranchée de Périgny (Perrigny-lès-Dijon), à cinq kilomètres de Dijon, selon La Presse et la liste de Wikipédia.',
            'en' => 'The accident took place in the Périgny cutting (Perrigny-lès-Dijon), five kilometres from Dijon, according to La Presse and the Wikipedia list.',
            'de' => 'Der Unfall ereignete sich im Einschnitt von Périgny (Perrigny-lès-Dijon), fünf Kilometer von Dijon entfernt, laut La Presse und der Wikipedia-Liste.',
        ],
    ],
];
