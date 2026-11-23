<?php

// Contexte sourcé de du déraillement de wagons de matières dangereuses à Neufchâteau (Vosges) du 22 mai 2010 (recherche du 23/11/2026).
return [
    'slug' => '2010-05-22-neufchateau',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2010-05-22-neufchateau' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Déraillement de wagons de matières dangereuses à Neufchâteau (88)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/neufchateau-88-22-05-2010-deraillement-de-wagons-r136.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 22 mai 2010, les quatre derniers wagons d\'un train de fret de la SNCF ont déraillé et se sont couchés en pleine voie. Trois d\'entre eux étaient des citernes de matières dangereuses, dont l\'une, contenant du phénol, fuyait au niveau du dôme. Un périmètre de sécurité a été mis en place, puis de longues et complexes opérations de colmatage et de transvasement ont suivi. L\'accident n\'a fait aucune victime, mais les dégâts à l\'infrastructure et les conséquences sur la circulation sont importants.',
                'en' => 'On 22 May 2010 the last four wagons of an SNCF freight train derailed and fell over on the open line. Three of them were dangerous-goods tank wagons, one of which, holding phenol, leaked at the dome. A safety perimeter was set up, followed by long and complex sealing and transfer operations. The accident caused no casualties, but the damage to the infrastructure and the impact on traffic are significant.',
                'de' => 'Am 22. Mai 2010 entgleisten die letzten vier Wagen eines Güterzugs der SNCF und kippten auf freier Strecke um. Drei davon waren Gefahrgut-Kesselwagen, von denen einer, mit Phenol gefüllt, am Dom leckte. Es wurde ein Sicherheitsbereich eingerichtet, dann folgten lange und komplizierte Abdicht- und Umpumparbeiten. Der Unfall forderte keine Opfer, aber die Schäden an der Infrastruktur und die Folgen für den Verkehr sind erheblich.',
                'sources' => ['bea-tt-2010-05-22-neufchateau'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié un rapport d\'étape, puis un rapport final.',
                'en' => 'The BEA-TT published an interim report, then a final report.',
                'de' => 'Die BEA-TT veröffentlichte einen Zwischenbericht, dann einen Abschlussbericht.',
                'sources' => ['bea-tt-2010-05-22-neufchateau'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2010-05-22-neufchateau'],
            'fr' => 'Le BEA-TT indique que l\'accident n\'a fait aucune victime.',
            'en' => 'The BEA-TT states that the accident caused no casualties.',
            'de' => 'Die BEA-TT gibt an, dass der Unfall keine Opfer forderte.',
        ],
    ],
];
