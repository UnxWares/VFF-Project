<?php

// Contexte sourcé de du choc d'une automotrice contre un isolateur à Sevran (Seine-Saint-Denis) du 1er février 2012 (recherche du 23/11/2026).
return [
    'slug' => '2012-02-01-sevran',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2012-02-01-sevran' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Choc d’un train contre un isolateur pendant depuis la caténaire à Sevran (93)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/sevran-93-1-02-2012-choc-d-une-automotrice-contre-r175.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 1er février 2012, un train Transilien de Crépy-en-Valois à Paris a percuté un isolateur en céramique de la caténaire qui pendait au-dessus de la voie à hauteur des vitres de la cabine. L\'isolateur a traversé la vitre frontale gauche et blessé très grièvement le conducteur, qui a perdu connaissance.',
                'en' => 'On 1 February 2012 a Transilien train from Crépy-en-Valois to Paris hit a ceramic catenary insulator hanging over the track at the height of the cab windows. The insulator went through the left front window and very gravely injured the driver, who lost consciousness.',
                'de' => 'Am 1. Februar 2012 prallte ein Transilien von Crépy-en-Valois nach Paris auf einen keramischen Fahrleitungsisolator, der in Höhe der Führerstandsfenster über dem Gleis hing. Der Isolator durchschlug die linke Frontscheibe und verletzte den Lokführer, der das Bewusstsein verlor, lebensgefährlich.',
                'sources' => ['bea-tt-2012-02-01-sevran'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 12 juillet 2013, puis les recommandations et les réponses des destinataires (SNCF, RFF, Bureau de normalisation ferroviaire, Saint-Gobain, AGC Glass et entreprises ferroviaires) le 17 juillet 2014.',
                'en' => 'The BEA-TT published its final report on 12 July 2013, then the recommendations and the replies of the recipients (SNCF, RFF, the railway standards office, Saint-Gobain, AGC Glass and railway undertakings) on 17 July 2014.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 12. Juli 2013, dann am 17. Juli 2014 die Empfehlungen und die Antworten der Adressaten (SNCF, RFF, Normungsstelle der Bahn, Saint-Gobain, AGC Glass und Eisenbahnunternehmen).',
                'sources' => ['bea-tt-2012-02-01-sevran'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 1,
            'sources' => ['bea-tt-2012-02-01-sevran'],
            'fr' => 'Le BEA-TT indique que le conducteur du train a été très grièvement blessé.',
            'en' => 'The BEA-TT states that the train driver was very gravely injured.',
            'de' => 'Die BEA-TT gibt an, dass der Lokführer lebensgefährlich verletzt wurde.',
        ],
    ],
];
