<?php

// Contexte sourcé de la collision au passage à niveau n° 40 d'Amilly du 27 octobre 2012 (recherche du 21/11/2026).
return [
    'slug' => '2012-10-27-amilly',
    'checked_on' => '2026-11-21',
    'sources' => [
        'bea-tt-page-amilly' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Amilly (28), 27/10/2012 : collision entre un train et deux véhicules routiers',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/amilly-28-27-10-2012-collision-entre-un-train-et-r188.html',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Page de présentation de l\'enquête : résumé des circonstances et du bilan.',
        ],
        'bea-tt-page-rapport-amilly' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Rapport d\'enquête sur la collision entre un train et deux véhicules le 27/10/2012 à Amilly',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => '2014-08-20',
            'locator' => null,
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/rapport-d-enquete-sur-la-collision-entre-un-train-a590.html',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Page du rapport (daté de juin 2014 sur sa couverture, mis en ligne le 20 août 2014) et des recommandations, adressées à l\'EPSF, à RFF et à SNCF Infra ; le texte du PDF n\'a pas pu être lu (polices non extractibles).',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le passage à niveau n° 40 d\'Amilly, en Eure-et-Loir, se trouve sur la ligne entre Le Mans et Chartres. Le 27 octobre 2012, un train composé de deux locomotives haut le pied, circulant donc sans wagons ni voyageurs, y est arrivé alors que les barrières s\'étaient relevées prématurément.',
                'en' => 'Level crossing no. 40 at Amilly, in Eure-et-Loir, lies on the line between Le Mans and Chartres. On 27 October 2012, a train made up of two light-running locomotives, with no wagons or passengers, reached it while the barriers had lifted prematurely.',
                'de' => 'Der Bahnübergang Nr. 40 in Amilly im Département Eure-et-Loir liegt an der Strecke zwischen Le Mans und Chartres. Am 27. Oktober 2012 erreichte ihn ein aus zwei Lokomotiven bestehender Zug, also ohne Wagen und Reisende, als sich die Schranken vorzeitig gehoben hatten.',
                'sources' => ['bea-tt-page-amilly'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le train a percuté une voiture et un minibus engagés sur le passage à niveau.',
                'en' => 'The train struck a car and a minibus that had entered the level crossing.',
                'de' => 'Der Zug prallte auf ein Auto und einen Kleinbus, die in den Bahnübergang eingefahren waren.',
                'sources' => ['bea-tt-page-amilly'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le conducteur du minibus est mort. Ses quatre passagers ont été blessés, deux d\'entre eux grièvement. Le conducteur de la voiture, seul à bord, a été grièvement blessé.',
                'en' => 'The minibus driver died. His four passengers were injured, two of them seriously. The driver of the car, alone on board, was seriously injured.',
                'de' => 'Der Fahrer des Kleinbusses starb. Seine vier Fahrgäste wurden verletzt, zwei von ihnen schwer. Der Fahrer des Autos, der allein an Bord war, wurde schwer verletzt.',
                'sources' => ['bea-tt-page-amilly'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport en 2014 (couverture datée de juin 2014, mise en ligne le 20 août 2014), avec des recommandations adressées à l\'Établissement public de sécurité ferroviaire, à Réseau ferré de France et à SNCF Infra, accompagnées de leurs réponses.',
                'en' => 'The BEA-TT published its report in 2014 (cover dated June 2014, put online on 20 August 2014), with recommendations addressed to the Établissement public de sécurité ferroviaire, Réseau ferré de France and SNCF Infra, together with their replies.',
                'de' => 'Das BEA-TT veröffentlichte seinen Bericht 2014 (Titelblatt von Juni 2014, am 20. August 2014 online gestellt) mit Empfehlungen an die Sicherheitsbehörde EPSF, Réseau ferré de France und SNCF Infra, zusammen mit deren Antworten.',
                'sources' => ['bea-tt-page-rapport-amilly'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 5,
            'sources' => ['bea-tt-page-amilly'],
            'fr' => 'La page du BEA-TT donne un mort (conducteur du minibus), le conducteur de la voiture grièvement blessé et quatre passagers du minibus blessés, soit cinq blessés.',
            'en' => 'The BEA-TT page gives one dead (the minibus driver), the car driver seriously injured and four minibus passengers injured, making five injured.',
            'de' => 'Die BEA-TT-Seite nennt einen Toten (den Fahrer des Kleinbusses), den schwer verletzten Autofahrer und vier verletzte Fahrgäste des Kleinbusses, also fünf Verletzte.',
        ],
    ],
];
