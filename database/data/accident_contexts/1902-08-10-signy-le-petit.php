<?php

// Contexte sourcé du déraillement de Signy-le-Petit du 10 août 1902 (recherche du 21/11/2026).
return [
    'slug' => '1902-08-10-signy-le-petit',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1902' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1902',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Août',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1902&oldid=233590581#Ao%C3%BBt',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Ligne Mézières-Charleville-Hirson, à un kilomètre de la gare, express pour Lille, trois morts, une vingtaine de blessés, fanfare ; renvoie à Le Figaro du 11 août 1902 (non lu).',
        ],
        'delpher-courant-1902-08-13' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Het spoorwegongeluk te Mézières',
            'publisher' => 'De Courant (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1902-08-13',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMKB19:003523074:mpeg21:a00006',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise reprenant les journaux français : déroulement, bilan (trois morts, dix-neuf blessés dont dix graves), cause non établie.',
        ],
        'delpher-vlaardingsche-1902-08-13' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Nieuwsberichten',
            'publisher' => 'Nieuwe Vlaardingsche Courant (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1902-08-13',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMSAVL02:000492118:mpeg21:a00004',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche du 10 août : départ de Charleville à 6 h 50, société de musique de 200 personnes du personnel des ateliers de Mohon, tender sorti des rails.',
        ],
        'delpher-middelburgsche-1902-08-12' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Chronique (rubrique \'n loterij zonder nieten)',
            'publisher' => 'Middelburgsche Courant (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1902-08-12',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010680931:mpeg21:a0011',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Récit du déraillement à 500 m de la gare : attelage rompu, locomotive repartie seule, six voitures dans le fossé, trois morts et vingt blessés.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le dimanche 10 août 1902, l\'express de Charleville à Lille, qui circulait sur la ligne de Mézières-Charleville à Hirson, était très chargé. Il transportait notamment une société de musique de quelque 200 personnes, composée de membres du personnel des ateliers de Mohon, près de Mézières, qui se rendait à un concours de musique à Lille.',
                'en' => 'On Sunday 10 August 1902, the express from Charleville to Lille, running on the Mézières-Charleville to Hirson line, was very full. It carried among others a music society of some 200 people, made up of staff of the Mohon railway workshops near Mézières, travelling to a music contest in Lille.',
                'de' => 'Am Sonntag, dem 10. August 1902, war der Schnellzug von Charleville nach Lille auf der Strecke von Mézières-Charleville nach Hirson stark besetzt. Er beförderte unter anderem einen Musikverein mit rund 200 Mitgliedern aus dem Personal der Werkstätten von Mohon bei Mézières, der zu einem Musikwettbewerb in Lille unterwegs war.',
                'sources' => ['delpher-vlaardingsche-1902-08-13', 'delpher-courant-1902-08-13', 'wp-liste-1902'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Environ 500 mètres avant la gare de Signy-le-Petit, le tender de la locomotive est sorti des rails. Le choc a rompu l\'attelage : la locomotive a continué seule sa route, tandis que les premières voitures déraillaient et basculaient dans un fossé le long de la voie. Les voyageurs des dernières voitures, indemnes, ont envoyé chercher du secours au village voisin, et un train de secours avec des médecins et des ingénieurs est arrivé vers dix heures.',
                'en' => 'About 500 metres before Signy-le-Petit station, the locomotive\'s tender left the rails. The shock broke the coupling: the locomotive carried on alone while the first coaches derailed and tipped into a ditch beside the line. Passengers in the rear coaches, unhurt, sent to the nearby village for help, and a relief train with doctors and engineers arrived at about ten o\'clock.',
                'de' => 'Etwa 500 Meter vor dem Bahnhof Signy-le-Petit entgleiste der Tender der Lokomotive. Durch den Stoß riss die Kupplung: Die Lokomotive fuhr allein weiter, während die vorderen Wagen entgleisten und in einen Graben neben der Strecke kippten. Die unverletzten Reisenden der hinteren Wagen schickten ins nahe Dorf nach Hilfe, und gegen zehn Uhr traf ein Hilfszug mit Ärzten und Ingenieuren ein.',
                'sources' => ['delpher-middelburgsche-1902-08-12', 'delpher-courant-1902-08-13', 'delpher-vlaardingsche-1902-08-13'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'L\'accident a fait trois morts parmi les voyageurs, et dix-huit à vingt blessés selon les journaux, dont dix graves d\'après l\'un d\'eux. Les médecins ont dû opérer sur place les plus gravement atteints.',
                'en' => 'The accident killed three passengers and injured between eighteen and twenty according to the newspapers, ten of them seriously according to one. Doctors had to operate on the spot on the most seriously hurt.',
                'de' => 'Bei dem Unfall starben drei Reisende, und je nach Zeitung wurden achtzehn bis zwanzig verletzt, nach einer Angabe zehn davon schwer. Die Ärzte mussten die Schwerverletzten an Ort und Stelle operieren.',
                'sources' => ['delpher-courant-1902-08-13', 'delpher-vlaardingsche-1902-08-13', 'delpher-middelburgsche-1902-08-12', 'wp-liste-1902'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La cause n\'était pas établie dans les premiers comptes rendus : une vitesse trop grande ou une anomalie des rails (De Courant, 13 août 1902). La liste de Wikipédia parle d\'un écartement des rails, sans certitude (« sans doute »).',
                'en' => 'The cause had not been established in the first reports: excessive speed or a fault in the rails (De Courant, 13 August 1902). The Wikipedia list mentions spread rails, without certainty ("probably").',
                'de' => 'Die Ursache war in den ersten Berichten nicht geklärt: zu hohe Geschwindigkeit oder ein Schienenfehler (De Courant, 13. August 1902). Die Wikipedia-Liste nennt eine Spurerweiterung der Schienen, ohne Gewissheit („wahrscheinlich“).',
                'sources' => ['delpher-courant-1902-08-13', 'wp-liste-1902'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le lieu exact est donné à un kilomètre de la gare par la liste de Wikipédia et à 500 mètres par la presse néerlandaise. L\'heure de départ de Charleville est de 6 h 50 dans une dépêche du 10 août et de 8 h dans un autre compte rendu. Le nombre de blessés varie entre 18 et 20.',
                'en' => 'The exact spot is given as one kilometre from the station by the Wikipedia list and as 500 metres by the Dutch press. The departure time from Charleville is 6.50 am in a dispatch of 10 August and 8 am in another report. The number of injured varies between 18 and 20.',
                'de' => 'Die genaue Stelle wird von der Wikipedia-Liste mit einem Kilometer und von der niederländischen Presse mit 500 Metern vom Bahnhof angegeben. Die Abfahrtszeit in Charleville lautet in einer Depesche vom 10. August 6.50 Uhr, in einem anderen Bericht 8 Uhr. Die Zahl der Verletzten schwankt zwischen 18 und 20.',
                'sources' => ['wp-liste-1902', 'delpher-middelburgsche-1902-08-12', 'delpher-vlaardingsche-1902-08-13', 'delpher-courant-1902-08-13'],
            ],
        ],
    ],
    'corrections' => [],
];
