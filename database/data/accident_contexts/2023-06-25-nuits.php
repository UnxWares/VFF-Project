<?php

// Contexte sourcé de de la mise en danger de voyageurs lors de la détresse d'un train OSLO à Nuits (Yonne) du 25 juin 2023 (recherche du 23/11/2026).
return [
    'slug' => '2023-06-25-nuits',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2023-06-25-nuits' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Mise en danger de personnes lors de la détresse d’un train de voyageurs à Nuits (89)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/nuits-89-25-06-2023-mise-en-danger-de-voyageurs-et-r349.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Un train de voyageurs de l\'entreprise OSLO, qui allait de Paris à Lyon, s\'est arrêté après une panne de sa locomotive. Faute de climatisation, la température à bord a dépassé 40 °C et certains voyageurs ont eu besoin d\'une prise en charge médicale. Les secours sont intervenus dans les emprises ferroviaires sans mesure de protection pour leur sécurité. Il n\'y a eu aucun blessé.',
                'en' => 'A passenger train of the operator OSLO, running from Paris to Lyon, stopped after its locomotive broke down. With no air conditioning, the temperature on board rose above 40 C and some passengers needed medical care. The emergency services worked inside the railway boundary with no protection measures in place for their safety. Nobody was injured.',
                'de' => 'Ein Personenzug des Unternehmens OSLO von Paris nach Lyon blieb nach einem Lokschaden stehen. Ohne Klimaanlage stieg die Temperatur an Bord über 40 °C, und einige Fahrgäste brauchten medizinische Betreuung. Die Rettungskräfte arbeiteten im Gleisbereich, ohne dass Schutzmaßnahmen für ihre Sicherheit getroffen worden waren. Es gab keine Verletzten.',
                'sources' => ['bea-tt-2023-06-25-nuits'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport le 17 mars 2025, sur le thème des procédures de secours, avec des recommandations aux entreprises concernées et à la DGSCGC, puis des ressources pour approfondir le sujet le 10 septembre 2025.',
                'en' => 'The BEA-TT published its report on 17 March 2025, on the theme of rescue procedures, with recommendations to the companies concerned and to the DGSCGC, then resources to explore the subject further on 10 September 2025.',
                'de' => 'Die BEA-TT veröffentlichte ihren Bericht am 17. März 2025 zum Thema Rettungsverfahren, mit Empfehlungen an die betroffenen Unternehmen und die DGSCGC, am 10. September 2025 dann Material zur Vertiefung.',
                'sources' => ['bea-tt-2023-06-25-nuits'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2023-06-25-nuits'],
            'fr' => 'Le BEA-TT précise qu\'aucun blessé n\'est à déplorer.',
            'en' => 'The BEA-TT states that no one was injured.',
            'de' => 'Die BEA-TT gibt an, dass niemand verletzt wurde.',
        ],
    ],
];
