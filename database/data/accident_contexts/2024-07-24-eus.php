<?php

// Contexte sourcé de du déraillement d'un TER à Eus (Pyrénées-Orientales) du 24 juillet 2024 (recherche du 23/11/2026).
return [
    'slug' => '2024-07-24-eus',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2024-07-24-eus' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Déraillement d’un TER près d’Eus (66)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/eus-66-24-07-2024-deraillement-d-un-ter-sur-la-r498.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 24 juillet 2024, sur la ligne de Prades à Perpignan, un TER venant de Villefranche-Vernet-les-Bains-Fuilla a heurté un dépôt de terre venu d\'un glissement du talus. Sous le choc, les premiers bogies de l\'élément de tête et du deuxième élément ont déraillé. La rame a poursuivi sa course et heurté la culée droite d\'un pont-route dont la pile est endommagée, arraché un support de caténaire et très abîmé le matériel roulant, la voie étant à renouveler sur environ 250 m.',
                'en' => 'On 24 July 2024, on the Prades to Perpignan line, a regional train from Villefranche-Vernet-les-Bains-Fuilla hit a pile of earth from a landslip off the embankment. On impact, the first bogies of the leading unit and of the second unit derailed. The train carried on and struck the right abutment of a road bridge, whose pier is damaged, tore off a catenary support and badly damaged the rolling stock, the track needing renewal over about 250 m.',
                'de' => 'Am 24. Juli 2024 prallte auf der Strecke Prades - Perpignan ein Regionalzug aus Villefranche-Vernet-les-Bains-Fuilla gegen eine Erdablagerung aus einem Böschungsrutsch. Durch den Stoß entgleisten die ersten Drehgestelle der vorderen und der zweiten Einheit. Der Zug fuhr weiter und stieß gegen das rechte Widerlager einer Straßenbrücke, deren Pfeiler beschädigt wurde, riss einen Fahrleitungsmast ab und beschädigte die Fahrzeuge stark; das Gleis muss auf etwa 250 m erneuert werden.',
                'sources' => ['bea-tt-2024-07-24-eus'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le bilan humain est d\'un blessé grave, le conducteur du train, et de quatre blessés légers.',
                'en' => 'The human toll is one seriously injured person, the train driver, and four slightly injured.',
                'de' => 'Die Bilanz lautet ein Schwerverletzter, der Zugführer, und vier Leichtverletzte.',
                'sources' => ['bea-tt-2024-07-24-eus'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 5,
            'sources' => ['bea-tt-2024-07-24-eus'],
            'fr' => 'Le BEA-TT compte un blessé grave, le conducteur, et quatre blessés légers.',
            'en' => 'The BEA-TT counts one seriously injured person, the driver, and four slightly injured.',
            'de' => 'Die BEA-TT zählt einen Schwerverletzten, den Lokführer, und vier Leichtverletzte.',
        ],
    ],
];
