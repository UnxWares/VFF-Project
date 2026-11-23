<?php

// Contexte sourcé de de la collision de deux trains en gare de Longueville (Seine-et-Marne) du 16 février 2005 (recherche du 23/11/2026).
return [
    'slug' => '2005-02-16-longueville',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2005-02-16-longueville' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Collision de 2 TER Longueville',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/longueville-77-16-02-2005-collision-de-deux-trains-r38.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 16 février 2005, le train n° 117 710, venant de Provins, a pris en écharpe le train n° 117 578 en gare de Longueville. Il n\'y a pas eu de blessés, mais les dégâts sont importants sur le matériel roulant et l\'infrastructure.',
                'en' => 'On 16 February 2005 train no. 117 710, coming from Provins, sideswiped train no. 117 578 in Longueville station. Nobody was injured, but the damage is heavy to the rolling stock and the infrastructure.',
                'de' => 'Am 16. Februar 2005 streifte der aus Provins kommende Zug Nr. 117 710 im Bahnhof Longueville den Zug Nr. 117 578. Es gab keine Verletzten, aber die Schäden am rollenden Material und an der Infrastruktur sind erheblich.',
                'sources' => ['bea-tt-2005-02-16-longueville'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 23 janvier 2006 (thèmes : collision, freins, procédures), avec des recommandations adressées à la SNCF, à la CFTA et à la DGMT.',
                'en' => 'The BEA-TT published its final report on 23 January 2006 (themes: collision, brakes, procedures), with recommendations addressed to the SNCF, the CFTA and the DGMT.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 23. Januar 2006 (Themen: Zusammenstoß, Bremsen, Verfahren), mit Empfehlungen an die SNCF, die CFTA und die DGMT.',
                'sources' => ['bea-tt-2005-02-16-longueville'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2005-02-16-longueville'],
            'fr' => 'Le BEA-TT indique qu\'il n\'y a pas de blessés.',
            'en' => 'The BEA-TT states that there were no injuries.',
            'de' => 'Die BEA-TT gibt an, dass es keine Verletzten gab.',
        ],
    ],
];
