<?php

// Contexte sourcé de de l'accident de haute tension lors d'une circulation d'essai de locomotive à Zillisheim (Haut-Rhin) du 22 juillet 2024 (recherche du 23/11/2026).
return [
    'slug' => '2024-07-22-zillisheim',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2024-07-22-zillisheim' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Accident de haute tension lors d’une circulation d’essai à Zillisheim (68)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/zillisheim-68-22-07-2024-accident-de-haute-tension-r499.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Un agent a été électrisé en cabine de conduite pendant une campagne d\'essais destinée à vérifier la compatibilité d\'une nouvelle locomotive d\'Alstom avec le réseau français. L\'arc électrique dans la cabine a causé des blessures graves à un membre de l\'équipage ; les dégâts matériels sont mineurs.',
                'en' => 'A worker received an electric shock in the driving cab during a test campaign meant to check the compatibility of a new Alstom locomotive with the French network. The electric arc in the cab caused serious injuries to a crew member; the material damage is minor.',
                'de' => 'Ein Mitarbeiter erlitt im Führerstand einen Stromschlag bei einer Versuchskampagne zur Prüfung der Kompatibilität einer neuen Alstom-Lokomotive mit dem französischen Netz. Der Lichtbogen im Führerstand verursachte schwere Verletzungen bei einem Besatzungsmitglied; der Sachschaden ist gering.',
                'sources' => ['bea-tt-2024-07-22-zillisheim'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport le 23 juin 2025, sur le thème de la gestion des modifications lors d\'essais, avec des recommandations adressées à SNCF Réseau, DB System Technik et l\'EPSF, ainsi qu\'une vidéo pédagogique.',
                'en' => 'The BEA-TT published its report on 23 June 2025, on the theme of managing modifications during tests, with recommendations addressed to SNCF Réseau, DB System Technik and the EPSF, and an educational video.',
                'de' => 'Die BEA-TT veröffentlichte ihren Bericht am 23. Juni 2025 zum Thema Änderungsmanagement bei Versuchen, mit Empfehlungen an SNCF Réseau, DB System Technik und die EPSF sowie ein Lehrvideo.',
                'sources' => ['bea-tt-2024-07-22-zillisheim'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 1,
            'sources' => ['bea-tt-2024-07-22-zillisheim'],
            'fr' => 'Le BEA-TT décrit un seul blessé grave, un membre de l\'équipage.',
            'en' => 'The BEA-TT describes a single seriously injured person, a crew member.',
            'de' => 'Die BEA-TT beschreibt einen einzigen Schwerverletzten, ein Besatzungsmitglied.',
        ],
    ],
];
