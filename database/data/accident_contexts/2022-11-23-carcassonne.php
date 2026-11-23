<?php

// Contexte sourcé de du déraillement d'un train de fret en gare de Carcassonne (Aude) du 23 novembre 2022 (recherche du 23/11/2026).
return [
    'slug' => '2022-11-23-carcassonne',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2022-11-23-carcassonne' => [
            'provider' => 'bea-tt',
            'kind' => 'report',
            'reliability' => 'official',
            'title' => 'Déraillement d’un train de fret à Carcassonne (11)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'fiche de présentation',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/IMG/pdf/beatt_2022_09_carcassonne_note-etape__dec2025.pdf',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Note d\'information sur l\'avancement de l\'enquête technique : faits d\'après les premiers éléments.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 23 novembre 2022, le train de marchandises n° 50924, tracté par Fret SNCF pour Novatrans (28 wagons dont des conteneurs de matières dangereuses, 672 m, 1 800 t), a déraillé et s\'est arrêté juste après la gare de Carcassonne. Le quai 2 de la gare est partiellement arraché, la voie est fortement dégradée depuis Trèbes sur environ 6 km et les essieux déraillés sont très abîmés. Il n\'y a eu aucun blessé.',
                'en' => 'On 23 November 2022 goods train no. 50924, hauled by Fret SNCF for Novatrans (28 wagons including containers of dangerous goods, 672 m, 1,800 t), derailed and stopped just past Carcassonne station. Platform 2 of the station is partly torn away, the track is badly damaged from Trèbes over about 6 km and the derailed axles are heavily damaged. Nobody was injured.',
                'de' => 'Am 23. November 2022 entgleiste der von Fret SNCF für Novatrans gezogene Güterzug Nr. 50924 (28 Wagen, darunter Container mit Gefahrgut, 672 m, 1 800 t) und hielt kurz hinter dem Bahnhof Carcassonne. Bahnsteig 2 des Bahnhofs ist teilweise weggerissen, das Gleis ist ab Trèbes auf etwa 6 km stark beschädigt, und die entgleisten Achsen sind schwer beschädigt. Es gab keine Verletzten.',
                'sources' => ['bea-tt-2022-11-23-carcassonne'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Les premiers constats situent le début du déraillement d\'au moins un essieu au franchissement d\'un appareil de voie neutralisé, pris en talon, à Trèbes. Sur le wagon double déraillé, une poignée de changement de régime de freinage était sur la mauvaise position. Le BEA-TT a ouvert son enquête le 28 novembre 2022 et une instruction judiciaire a aussi été ouverte ; il a publié une note d\'avancement le 19 décembre 2025.',
                'en' => 'The first findings place the start of the derailment of at least one axle at the crossing of a neutralised set of points, taken from the heel, at Trèbes. On the derailed double wagon a brake-regime change handle was in the wrong position. The BEA-TT opened its investigation on 28 November 2022 and a judicial inquiry was also opened; it published a progress note on 19 December 2025.',
                'de' => 'Die ersten Feststellungen verorten den Beginn der Entgleisung mindestens einer Achse beim Befahren einer außer Betrieb genommenen, von der Spitze abgewandten Weiche in Trèbes. Am entgleisten Doppelwagen stand ein Umstellgriff für die Bremsstellung in falscher Position. Die BEA-TT eröffnete ihre Untersuchung am 28. November 2022, auch ein Gerichtsverfahren wurde eingeleitet; am 19. Dezember 2025 veröffentlichte sie eine Zwischennotiz.',
                'sources' => ['bea-tt-2022-11-23-carcassonne'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2022-11-23-carcassonne'],
            'fr' => 'Le BEA-TT indique que l\'accident n\'a fait aucun blessé.',
            'en' => 'The BEA-TT states that the accident injured no one.',
            'de' => 'Die BEA-TT gibt an, dass bei dem Unfall niemand verletzt wurde.',
        ],
    ],
];
