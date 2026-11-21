<?php

// Contexte sourcé de la collision au passage à niveau n° 44 de Péronnas du 15 janvier 2021 (recherche du 21/11/2026).
return [
    'slug' => '2021-01-15-peronnas',
    'checked_on' => '2026-11-21',
    'sources' => [
        'bea-tt-2021-01' => [
            'provider' => 'bea-tt',
            'kind' => 'report',
            'reliability' => 'official',
            'title' => 'Rapport d\'enquête technique sur la collision entre un TER et un véhicule léger, le 15/01/2021 à Péronnas (01)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => '2023-03-24',
            'locator' => 'affaire BEATT-2021-01',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/IMG/pdf/beatt-2021-01_peronnas.pdf',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Rapport complet : lieux, reconstitution des trajectoires, causes possibles, quatre recommandations.',
        ],
        'bea-tt-page-peronnas' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Péronnas (01), 15/01/2021 : collision entre un TER et un véhicule léger',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/peronnas-01-15-01-21-collision-entre-un-ter-et-un-r303.html',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Page de présentation : vitesse du train, 146 occupants indemnes.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le passage à niveau n° 44 se trouve hors agglomération, à Péronnas, sur le chemin de Monternoz, route communale limitée à 80 km/h, de 6,15 mètres de large qui se réduit à 4,30 mètres sur le passage à niveau. Aucun régime d\'alternat n\'y règle les priorités et deux véhicules ne peuvent pas s\'y croiser aisément. De chaque côté, la route comporte un virage prononcé. Le passage est sur une ligne à voie unique.',
                'en' => 'Level crossing no. 44 lies outside the built-up area, at Péronnas, on Chemin de Monternoz, a local road limited to 80 km/h, 6.15 metres wide, narrowing to 4.30 metres on the crossing. No alternating-priority system regulates traffic there and two vehicles cannot easily pass each other. On each side the road has a sharp bend. The crossing is on a single-track line.',
                'de' => 'Der Bahnübergang Nr. 44 liegt außerhalb der Ortschaft in Péronnas am Chemin de Monternoz, einer Gemeindestraße mit Tempo 80, die 6,15 Meter breit ist und sich am Übergang auf 4,30 Meter verengt. Eine Vorrangregelung im Wechselverkehr gibt es dort nicht, und zwei Fahrzeuge können einander nicht leicht passieren. Auf beiden Seiten hat die Straße eine scharfe Kurve. Der Übergang liegt an einer eingleisigen Strecke.',
                'sources' => ['bea-tt-2021-01'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le vendredi 15 janvier 2021 à 14 h 07, le TER 889 474 de Lyon à Bourg-en-Bresse, avec 146 personnes à bord, a percuté une voiture sur le passage à niveau à 134 km/h. L\'agent de conduite, occupé à reconnecter la radio sol-train, a aperçu la voiture sur les voies sans avoir le temps d\'actionner le sifflet. Le train s\'est arrêté plus de 600 mètres après le passage à niveau, sans dérailler.',
                'en' => 'On Friday 15 January 2021 at 2.07 pm, TER 889 474 from Lyon to Bourg-en-Bresse, with 146 people on board, struck a car on the level crossing at 134 km/h. The driver, busy reconnecting the ground-to-train radio, saw the car on the tracks without time to sound the horn. The train stopped more than 600 metres beyond the crossing, without derailing.',
                'de' => 'Am Freitag, dem 15. Januar 2021, um 14.07 Uhr erfasste der TER 889 474 von Lyon nach Bourg-en-Bresse mit 146 Personen an Bord am Bahnübergang mit 134 km/h ein Auto. Der Triebfahrzeugführer, der gerade den Zugfunk neu verband, sah das Auto auf den Gleisen, ohne Zeit zu haben, das Signalhorn zu betätigen. Der Zug hielt mehr als 600 Meter hinter dem Übergang, ohne zu entgleisen.',
                'sources' => ['bea-tt-2021-01', 'bea-tt-page-peronnas'],
            ],
            [
                'fr' => 'La voiture a été projetée sur le côté droit, a heurté des équipements du passage à niveau, a fait au moins un tonneau et s\'est immobilisée sur le toit. Une demi-barrière et son mécanisme ont été arrachés, et un feu rouge est resté éteint, faute d\'alimentation. Les secours, appelés à 14 h 11, sont arrivés à 14 h 24.',
                'en' => 'The car was thrown to the right, hit crossing equipment, rolled over at least once and came to rest upside down. A half-barrier and its mechanism were torn off, and one red light stayed out for lack of power. The emergency services, called at 2.11 pm, arrived at 2.24 pm.',
                'de' => 'Das Auto wurde nach rechts geschleudert, prallte gegen Einrichtungen des Bahnübergangs, überschlug sich mindestens einmal und blieb auf dem Dach liegen. Eine Halbschranke samt Mechanismus wurde abgerissen, und ein rotes Licht blieb mangels Stromversorgung aus. Die um 14.11 Uhr alarmierten Rettungskräfte trafen um 14.24 Uhr ein.',
                'sources' => ['bea-tt-2021-01'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'La conductrice de la voiture est morte sur le coup. Aucun des passagers du train n\'a été blessé et l\'agent de conduite n\'a pas été blessé physiquement, mais a subi un choc psychologique important. Les voyageurs ont terminé leur trajet en autocar.',
                'en' => 'The car driver died at the scene. None of the train\'s passengers was injured and the train driver was not physically hurt, but suffered a serious psychological shock. Passengers completed their journey by coach.',
                'de' => 'Die Fahrerin des Autos starb an der Unfallstelle. Keiner der Fahrgäste des Zuges wurde verletzt, und der Triebfahrzeugführer wurde körperlich nicht verletzt, erlitt aber einen schweren psychischen Schock. Die Fahrgäste setzten ihre Reise mit dem Bus fort.',
                'sources' => ['bea-tt-2021-01', 'bea-tt-page-peronnas'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Le BEA-TT reconnaît ne pas pouvoir identifier avec certitude l\'enchaînement des événements et les causes. Il estime qu\'un endormissement ou un malaise est peu compatible avec les traces observées, qui montrent que le virage avait été bien abordé, et ne retient aucune hypothèse de panne ou de perte de contrôle. Il évoque une moindre perception de la fermeture du passage à niveau (feux, barrières), et juge peu probable une voiture arrêtée sur les voies avant l\'abaissement des barrières. Les données d\'un calculateur secondaire de la voiture n\'étaient pas encore analysées à la publication.',
                'en' => 'The BEA-TT acknowledges that it cannot identify the sequence of events and the causes with certainty. It considers that falling asleep or being taken ill is hard to square with the marks observed, which show that the bend had been taken properly, and finds no support for a breakdown or loss of control. It raises reduced awareness of the crossing closing (lights, barriers), and considers a car stopped on the tracks before the barriers came down unlikely. Data from a secondary computer in the car had not yet been analysed at the time of publication.',
                'de' => 'Das BEA-TT räumt ein, die Abfolge der Ereignisse und die Ursachen nicht sicher bestimmen zu können. Es hält Einschlafen oder eine plötzliche Unpässlichkeit für schwer vereinbar mit den beobachteten Spuren, die zeigen, dass die Kurve ordentlich durchfahren wurde, und sieht keinen Anhaltspunkt für eine Panne oder einen Kontrollverlust. Es nennt eine geringere Wahrnehmung des sich schließenden Übergangs (Lichter, Schranken) und hält ein vor dem Senken der Schranken auf den Gleisen stehendes Auto für unwahrscheinlich. Daten eines zweiten Steuergeräts des Autos waren bei der Veröffentlichung noch nicht ausgewertet.',
                'sources' => ['bea-tt-2021-01'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a ouvert son enquête le 18 janvier 2021 et formulé quatre recommandations : améliorer la gestion des priorités entre usagers routiers, les conditions géométriques d\'approche et de franchissement, la visibilité des équipements du passage à niveau, en particulier le feu rouge clignotant de droite, et sensibiliser les agents de conduite à l\'initialisation de la radio sol-train à proximité des passages à niveau.',
                'en' => 'The BEA-TT opened its investigation on 18 January 2021 and made four recommendations: to improve the management of priority between road users, the geometry of the approach and crossing, the visibility of the crossing equipment, in particular the right-hand flashing red light, and to make train drivers aware of the issues of initialising the ground-to-train radio near level crossings.',
                'de' => 'Das BEA-TT eröffnete seine Untersuchung am 18. Januar 2021 und sprach vier Empfehlungen aus: das Vorrangmanagement zwischen den Straßennutzern, die geometrischen Bedingungen von Annäherung und Überfahrt und die Sichtbarkeit der Einrichtungen des Bahnübergangs, insbesondere des rechten blinkenden roten Lichts, zu verbessern sowie die Triebfahrzeugführer für die Initialisierung des Zugfunks in der Nähe von Bahnübergängen zu sensibilisieren.',
                'sources' => ['bea-tt-2021-01'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2021-01', 'bea-tt-page-peronnas'],
            'fr' => 'Aucun des occupants du train n\'est blessé ; l\'agent de conduite n\'a subi qu\'un choc psychologique et la conductrice de la voiture est morte.',
            'en' => 'None of the train occupants was injured; the driver suffered only psychological shock and the car driver died.',
            'de' => 'Keiner der Zuginsassen wurde verletzt; der Triebfahrzeugführer erlitt nur einen psychischen Schock, die Autofahrerin starb.',
        ],
    ],
];
