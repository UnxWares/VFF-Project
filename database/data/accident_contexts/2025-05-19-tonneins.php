<?php

// Contexte sourcé de du début de déraillement du TGV 8517 par affaissement de la voie à Tonneins (Lot-et-Garonne) du 19 mai 2025 (recherche du 23/11/2026).
return [
    'slug' => '2025-05-19-tonneins',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2025-05-19-tonneins' => [
            'provider' => 'bea-tt',
            'kind' => 'report',
            'reliability' => 'official',
            'title' => 'Début de déraillement du TGV 8517 survenu le 19 mai 2025 à Tonneins (47)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'fiche de présentation',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/IMG/pdf/note_etape_tonneins_mai26.pdf',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Note d\'information sur l\'avancement de l\'enquête : faits d\'après les premiers éléments.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le lundi 19 mai 2025 à 20 h 55, le TGV 8517, qui roulait à faible vitesse avec 507 personnes à bord sur la voie 1 de la ligne n° 640 000 de Bordeaux à Sète, quelques centaines de mètres après la gare de Tonneins, a vu son conducteur ressentir un mouvement anormal de la voie à la sortie d\'une courbe, au passage du ruisseau Le Caillou. Il a provoqué l\'arrêt d\'urgence à 22 km/h et lancé l\'alerte radio. Météo-France avait émis une alerte rouge orages et grêle et placé le département en vigilance orange pluie-inondations, avec de fortes précipitations sur Tonneins.',
                'en' => 'On Monday 19 May 2025 at 8.55 pm, TGV 8517, running slowly with 507 people on board on track 1 of line no. 640 000 from Bordeaux to Sète, a few hundred metres past Tonneins station, had its driver feel an abnormal movement of the track on leaving a curve, where it crosses the Le Caillou stream. He made an emergency stop at 22 km/h and raised the radio alarm. Météo-France had issued a red storm and hail alert and put the department on orange rain and flood watch, with heavy rain at Tonneins.',
                'de' => 'Am Montag, dem 19. Mai 2025 um 20.55 Uhr spürte der Fahrer des TGV 8517, der mit 507 Personen an Bord langsam auf Gleis 1 der Strecke Nr. 640 000 von Bordeaux nach Sète einige hundert Meter hinter dem Bahnhof Tonneins fuhr, am Ausgang einer Kurve bei der Überquerung des Bachs Le Caillou eine ungewöhnliche Bewegung des Gleises. Er löste eine Schnellbremsung bei 22 km/h aus und gab Funkalarm. Météo-France hatte eine rote Warnung vor Gewitter und Hagel ausgegeben und das Département unter orangene Warnung vor Regen und Überschwemmung gestellt, mit starkem Niederschlag in Tonneins.',
                'sources' => ['bea-tt-2025-05-19-tonneins'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Les voyageurs ont été évacués en fin de soirée et il n\'y a aucune victime. Le remblai a été emporté et au moins une roue d\'un bogie ne touchait plus le rail. Le trafic a été totalement interrompu sur les deux voies du 19 au soir du 27 mai 2025. Le BEA-TT a ouvert son enquête le 23 mai 2025 et publié une note d\'avancement en mai 2026.',
                'en' => 'The passengers were evacuated late in the evening and there were no casualties. The embankment had been washed away and at least one bogie wheel no longer touched the rail. Traffic was fully interrupted on both tracks from 19 to the evening of 27 May 2025. The BEA-TT opened its investigation on 23 May 2025 and published a progress note in May 2026.',
                'de' => 'Die Fahrgäste wurden am späten Abend evakuiert, es gab keine Opfer. Der Damm war weggespült, und mindestens ein Rad eines Drehgestells berührte die Schiene nicht mehr. Der Verkehr war auf beiden Gleisen vom 19. Mai bis zum Abend des 27. Mai 2025 vollständig unterbrochen. Die BEA-TT eröffnete ihre Untersuchung am 23. Mai 2025 und veröffentlichte im Mai 2026 eine Zwischennotiz.',
                'sources' => ['bea-tt-2025-05-19-tonneins'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2025-05-19-tonneins'],
            'fr' => 'Le BEA-TT indique qu\'aucune victime n\'est à déplorer.',
            'en' => 'The BEA-TT states that there were no casualties.',
            'de' => 'Die BEA-TT gibt an, dass es keine Opfer gab.',
        ],
    ],
];
