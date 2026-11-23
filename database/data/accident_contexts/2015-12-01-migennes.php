<?php

// Contexte sourcé de du déraillement d'un TER à la suite d'un talonnage d'aiguille à Laroche-Migennes (Yonne) le 1er décembre 2015 (recherche du 23/11/2026).
return [
    'slug' => '2015-12-01-migennes',
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
        'bea-tt-2015-017' => [
            'provider' => 'bea-tt',
            'kind' => 'report',
            'reliability' => 'official',
            'title' => 'Rapport d\'enquête sur le déraillement d\'un TER par talonnage d\'aiguille survenu le 1er décembre 2015 en gare de Laroche-Migennes (89)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => '2016-12',
            'locator' => 'affaire BEATT-2015-017',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/IMG/pdf/rapport_beatt_2015-017.pdf',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Rapport final : la page de couverture et l\'en-tête situent le déraillement le 1er décembre 2015.',
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
                'fr' => 'Le tableau du bilan des enquêtes du BEA-TT, dont la fiche était tirée, date l\'accident du 2 décembre 2015 ; la page de l\'enquête, son adresse et le rapport final disent le 1er décembre 2015, jour retenu.',
                'en' => 'The table of investigations of the BEA-TT, from which the record was taken, dates the accident to 2 December 2015; the investigation page, its address and the final report say 1 December 2015, the day kept.',
                'de' => 'Die Übersichtstabelle der Untersuchungen der BEA-TT, aus der der Eintrag stammte, datiert den Unfall auf den 2. Dezember 2015; die Seite der Untersuchung, ihre Adresse und der Abschlussbericht nennen den 1. Dezember 2015, den beibehaltenen Tag.',
                'sources' => ['bea-tt-2015-12-02-migennes', 'bea-tt-2015-017'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'occurred_on',
            'value' => '2015-12-01',
            'sources' => ['bea-tt-2015-017', 'bea-tt-2015-12-02-migennes'],
            'fr' => 'Le rapport et la page du BEA-TT donnent le 1er décembre 2015 ; la fiche portait le 2.',
            'en' => 'The BEA-TT report and page give 1 December 2015; the record had the 2nd.',
            'de' => 'Der BEA-TT-Bericht und die Seite nennen den 1. Dezember 2015; der Eintrag nannte den 2.',
        ],
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
