<?php

// Contexte sourcé de de la survitesse d'un TGV sur la LGV Bretagne-Pays de la Loire à La Milesse (Sarthe) du 22 décembre 2019 (recherche du 23/11/2026).
return [
    'slug' => '2019-12-22-la-milesse',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2019-12-22-la-milesse' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Dépassement de la vitesse autorisée par un TGV à La Milesse (72)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/la-milesse-72-22-12-2019-survitesse-d-un-tgv-sur-r296.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 22 décembre 2019, un TGV Paris - Rennes a été dévié sur la voie contiguë à La Milesse à la suite de l\'arrêt d\'un train devant lui. La géométrie de l\'aiguillage de déviation permet de le franchir en sécurité à 100 km/h au plus, or le train l\'a abordé à 165 km/h. Il circulait sous ETCS niveau 2 et la vitesse maximale affichée au conducteur en cabine était de 170 km/h. La survitesse n\'a eu aucune conséquence matérielle ni humaine.',
                'en' => 'On 22 December 2019 a Paris - Rennes TGV was diverted onto the adjacent track at La Milesse after a train stopped ahead of it. The geometry of the diverging points allows them to be taken safely at 100 km/h at most, yet the train approached them at 165 km/h. It was running under ETCS level 2 and the maximum speed shown to the driver in the cab was 170 km/h. The overspeed had no material or human consequence.',
                'de' => 'Am 22. Dezember 2019 wurde ein TGV Paris - Rennes in La Milesse auf das Nachbargleis abgelenkt, weil vor ihm ein Zug angehalten hatte. Die Geometrie der Ablenkweiche erlaubt eine sichere Befahrung mit höchstens 100 km/h, doch der Zug fuhr sie mit 165 km/h an. Er fuhr unter ETCS Level 2, und die dem Lokführer im Führerstand angezeigte Höchstgeschwindigkeit betrug 170 km/h. Die Geschwindigkeitsüberschreitung hatte weder Sach- noch Personenfolgen.',
                'sources' => ['bea-tt-2019-12-22-la-milesse'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 10 août 2021 (thèmes : conception, procédures, survitesse), avec des recommandations adressées à Hitachi Rail RTS et à Certifer.',
                'en' => 'The BEA-TT published its final report on 10 August 2021 (themes: design, procedures, overspeed), with recommendations addressed to Hitachi Rail RTS and Certifer.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 10. August 2021 (Themen: Konstruktion, Verfahren, Geschwindigkeitsüberschreitung), mit Empfehlungen an Hitachi Rail RTS und Certifer.',
                'sources' => ['bea-tt-2019-12-22-la-milesse'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2019-12-22-la-milesse'],
            'fr' => 'Le BEA-TT indique que la survitesse n\'a eu aucune conséquence humaine.',
            'en' => 'The BEA-TT states that the overspeed had no human consequence.',
            'de' => 'Die BEA-TT gibt an, dass die Geschwindigkeitsüberschreitung keine Personenfolgen hatte.',
        ],
    ],
];
