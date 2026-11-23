<?php

// Contexte sourcé de du déraillement d'un TER à la suite d'un talonnage d'aiguille à Laroche-Migennes (Yonne) en décembre 2015 (recherche du 23/11/2026).
return [
    'slug' => '2015-12-02-migennes',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2015-12-02-migennes' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Déraillement d’un TER suite à un talonnage d’aiguille dans la gare de Laroche-Migennes (89)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/laroche-migennes-89-1-12-2015-deraillement-d-un-r242.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Un TER de Dijon à Auxerre a déraillé à 20 km/h sur une aiguille en gare de Laroche-Migennes. L\'accident n\'a pas fait de victimes. La circulation sur la voie concernée a été interrompue jusqu\'au lendemain, quatre essieux du TER ont dû être remplacés ainsi que plusieurs mètres de rail.',
                'en' => 'A regional train from Dijon to Auxerre derailed at 20 km/h on a set of points in Laroche-Migennes station. There were no casualties. Traffic on the track concerned was interrupted until the next day, four axles of the train had to be replaced as well as several metres of rail.',
                'de' => 'Ein Regionalzug von Dijon nach Auxerre entgleiste mit 20 km/h an einer Weiche im Bahnhof Laroche-Migennes. Es gab keine Opfer. Der Verkehr auf dem betroffenen Gleis war bis zum nächsten Tag unterbrochen, vier Achsen des Zuges und mehrere Meter Schiene mussten ersetzt werden.',
                'sources' => ['bea-tt-2015-12-02-migennes'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 20 décembre 2016 (thèmes : déraillement, talonnage d\'aiguille), avec des recommandations adressées à SNCF Réseau.',
                'en' => 'The BEA-TT published its final report on 20 December 2016 (themes: derailment, running through points), with recommendations addressed to SNCF Réseau.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 20. Dezember 2016 (Themen: Entgleisung, Auffahren auf Weichen), mit Empfehlungen an SNCF Réseau.',
                'sources' => ['bea-tt-2015-12-02-migennes'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le BEA-TT date l\'accident du 1er décembre 2015 dans le titre de sa page et de son rapport, alors que la fiche est datée du 2 décembre ; la date de la fiche est à vérifier avant d\'être corrigée.',
                'en' => 'The BEA-TT dates the accident to 1 December 2015 in the title of its page and report, whereas the record is dated 2 December; the record\'s date needs checking before it is corrected.',
                'de' => 'Die BEA-TT datiert den Unfall im Titel ihrer Seite und ihres Berichts auf den 1. Dezember 2015, während der Datensatz den 2. Dezember nennt; das Datum des Datensatzes ist zu prüfen, bevor es korrigiert wird.',
                'sources' => ['bea-tt-2015-12-02-migennes'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2015-12-02-migennes'],
            'fr' => 'Le BEA-TT indique que l\'accident n\'a pas fait de victimes.',
            'en' => 'The BEA-TT states that the accident caused no casualties.',
            'de' => 'Die BEA-TT gibt an, dass der Unfall keine Opfer forderte.',
        ],
    ],
];
