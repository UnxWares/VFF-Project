<?php

// Contexte sourcé de du déraillement d'un train de fret à Saint-Julien-du-Sault (Yonne) du 19 août 2020 (recherche du 23/11/2026).
return [
    'slug' => '2020-08-19-saint-julien-du-sault',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2020-08-19-saint-julien-du-sault' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Déraillement d’un train de fret à Saint-Julien-du-Sault (89)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/saint-julien-du-sault-89-19-08-2020-deraillement-d-r308.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 19 août 2020, un wagon d\'un train de fret de Dourges à Vénissieux (train n° 50233) a déraillé après la rupture d\'une fusée d\'essieu. Ce wagon, chargé de matières dangereuses, a engagé le gabarit de la voie voisine. Les dégâts sont importants sur le wagon et sur l\'infrastructure.',
                'en' => 'On 19 August 2020 a wagon of a freight train from Dourges to Vénissieux (train no. 50233) derailed after an axle journal broke. This wagon, loaded with dangerous goods, fouled the clearance of the adjacent track. The damage is considerable to the wagon and the infrastructure.',
                'de' => 'Am 19. August 2020 entgleiste ein Wagen eines Güterzugs von Dourges nach Vénissieux (Zug Nr. 50233) nach dem Bruch eines Achsschenkels. Dieser mit Gefahrgut beladene Wagen ragte in das Lichtraumprofil des Nachbargleises. Die Schäden am Wagen und an der Infrastruktur sind erheblich.',
                'sources' => ['bea-tt-2020-08-19-saint-julien-du-sault'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 6 mars 2024 (thèmes : détecteur de boîte chaude, rupture de roue, d\'essieu ou d\'attelage), avec des recommandations adressées à l\'EPSF et à SNCF Réseau.',
                'en' => 'The BEA-TT published its final report on 6 March 2024 (themes: hot axle box detector, failure of a wheel, axle or coupling), with recommendations addressed to the EPSF and SNCF Réseau.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 6. März 2024 (Themen: Heißläuferortungsanlage, Bruch von Rad, Achse oder Kupplung), mit Empfehlungen an die EPSF und SNCF Réseau.',
                'sources' => ['bea-tt-2020-08-19-saint-julien-du-sault'],
            ],
        ],
    ],
];
