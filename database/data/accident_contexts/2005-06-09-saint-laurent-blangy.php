<?php

// Contexte sourcé de de la collision entre un TER et un poids lourd au passage à niveau n° 83 de Saint-Laurent-Blangy (Pas-de-Calais) du 9 juin 2005 (recherche du 23/11/2026).
return [
    'slug' => '2005-06-09-saint-laurent-blangy',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2005-06-09-saint-laurent-blangy' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Passage à niveau de Saint Laurent Blangy',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/saint-laurent-blangy-62-9-06-2005-collision-entre-r59.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 9 juin 2005, un TER de 150 passagers a percuté un poids lourd chargé de bouteilles de gaz, bloqué sur le passage à niveau n° 83. Le choc a provoqué un incendie puis l\'explosion du chargement. L\'accident n\'a heureusement pas fait de victimes, mais les dégâts matériels sont importants.',
                'en' => 'On 9 June 2005 a regional train carrying 150 passengers hit a lorry loaded with gas cylinders stuck on level crossing no. 83. The impact started a fire and then the load exploded. Fortunately the accident caused no casualties, but the material damage is considerable.',
                'de' => 'Am 9. Juni 2005 prallte ein Regionalzug mit 150 Fahrgästen auf einen mit Gasflaschen beladenen Lkw, der auf dem Bahnübergang Nr. 83 festsaß. Der Aufprall löste einen Brand und dann die Explosion der Ladung aus. Der Unfall forderte glücklicherweise keine Opfer, aber die Sachschäden sind erheblich.',
                'sources' => ['bea-tt-2005-06-09-saint-laurent-blangy'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 15 janvier 2007, puis les recommandations et les réponses de la SNCF, de RFF, du conseil général du Pas-de-Calais et de la DGMT le 17 avril 2007.',
                'en' => 'The BEA-TT published its final report on 15 January 2007, then the recommendations and the replies of the SNCF, RFF, the Pas-de-Calais general council and the DGMT on 17 April 2007.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 15. Januar 2007, dann am 17. April 2007 die Empfehlungen und die Antworten von SNCF, RFF, dem Generalrat des Pas-de-Calais und der DGMT.',
                'sources' => ['bea-tt-2005-06-09-saint-laurent-blangy'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2005-06-09-saint-laurent-blangy'],
            'fr' => 'Le BEA-TT indique que l\'accident n\'a pas fait de victimes.',
            'en' => 'The BEA-TT states that the accident caused no casualties.',
            'de' => 'Die BEA-TT gibt an, dass der Unfall keine Opfer forderte.',
        ],
    ],
];
