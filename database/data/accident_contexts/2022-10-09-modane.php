<?php

// Contexte sourcé de de l'immobilisation prolongée d'un TGV dans le tunnel du Fréjus (Savoie) du 9 octobre 2022 (recherche du 23/11/2026).
return [
    'slug' => '2022-10-09-modane',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2022-10-09-modane' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Immobilisation prolongée d’un TGV dans le tunnel du Fréjus (73)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/synthese-et-rapport-final-a1442.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 9 octobre 2022, le TGV n° 9 251 de Paris-Gare de Lyon à Milan, exploité par SNCF Voyages Italia, a quitté Modane à 17 h 00. Dans le tunnel ferroviaire du Fréjus, qui marque la frontière entre la France et l\'Italie, une alarme incendie s\'est déclenchée dans la motrice de tête. À 17 h 05, le train s\'est arrêté dans la partie française du tunnel faute de traction. Le conducteur n\'a pas vu de feu et ses procédures de dépannage, seul puis avec une aide technique française à distance, n\'ont pas abouti. Les secours ont été demandés à 18 h 45 et une locomotive de Modane a tracté le train, avec des difficultés, jusqu\'à Modane à 21 h 26, près de 4 h 30 après l\'arrêt. Il n\'y a eu aucune victime.',
                'en' => 'On 9 October 2022 TGV no. 9 251 from Paris-Gare de Lyon to Milan, operated by SNCF Voyages Italia, left Modane at 5.00 pm. In the Fréjus rail tunnel, which marks the border between France and Italy, a fire alarm went off in the leading power car. At 5.05 pm the train stopped in the French part of the tunnel for lack of traction. The driver saw no fire and his troubleshooting procedures, alone and then with remote French technical help, failed. Help was requested at 6.45 pm and a locomotive from Modane hauled the train, with difficulty, to Modane at 9.26 pm, nearly 4 h 30 after the initial stop. There were no casualties.',
                'de' => 'Am 9. Oktober 2022 verließ der TGV Nr. 9 251 von Paris-Gare de Lyon nach Mailand, betrieben von SNCF Voyages Italia, um 17.00 Uhr Modane. Im Eisenbahntunnel von Fréjus, der die Grenze zwischen Frankreich und Italien markiert, löste im vorderen Triebkopf ein Brandalarm aus. Um 17.05 Uhr blieb der Zug im französischen Teil des Tunnels wegen fehlender Zugkraft stehen. Der Lokführer sah kein Feuer, und seine Störungsbehebung, allein und dann mit technischer Fernhilfe aus Frankreich, blieb erfolglos. Um 18.45 Uhr wurde Hilfe angefordert, und eine Lokomotive aus Modane zog den Zug mit Schwierigkeiten um 21.26 Uhr nach Modane, fast 4 Stunden 30 nach dem ersten Halt. Es gab keine Opfer.',
                'sources' => ['bea-tt-2022-10-09-modane'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'L\'enquête a été menée conjointement par la France et l\'Italie. Le rapport final, publié le 9 octobre 2025 avec ses recommandations, est un document unique rédigé en français, langue faisant foi, puis traduit en italien, sur les thèmes du secours, de l\'alarme incendie, de l\'alerte et de la frontière.',
                'en' => 'The investigation was carried out jointly by France and Italy. The final report, published on 9 October 2025 with its recommendations, is a single document written in French, the authoritative language, then translated into Italian, on the themes of rescue, fire alarm, alerting and the border.',
                'de' => 'Die Untersuchung wurde gemeinsam von Frankreich und Italien geführt. Der Abschlussbericht, am 9. Oktober 2025 mit seinen Empfehlungen veröffentlicht, ist ein einziges Dokument, auf Französisch als maßgeblicher Sprache verfasst und dann ins Italienische übersetzt, zu den Themen Rettung, Brandalarm, Alarmierung und Grenze.',
                'sources' => ['bea-tt-2022-10-09-modane'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2022-10-09-modane'],
            'fr' => 'Le BEA-TT indique qu\'aucune victime n\'est à déplorer.',
            'en' => 'The BEA-TT states that there were no casualties.',
            'de' => 'Die BEA-TT gibt an, dass es keine Opfer gab.',
        ],
    ],
];
