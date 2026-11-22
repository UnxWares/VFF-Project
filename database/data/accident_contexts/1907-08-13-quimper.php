<?php

// Contexte sourcé du déraillement de l'express de Brest à Plogonnec du 13 août 1907 (recherche du 21/11/2026).
return [
    'slug' => '1907-08-13-quimper',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1907' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1907',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Août',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1907&oldid=238956681#Ao%C3%BBt',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Ligne de Landerneau à Quimper, entrée du tunnel de Plogonnec à une dizaine de kilomètres de Quimper, rupture de l\'essieu avant de la locomotive, un mort et huit blessés.',
        ],
        'delpher-courant-1907-08-15' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Gemengde Berichten',
            'publisher' => 'De Courant (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1907-08-15',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMKB19:003502118:mpeg21:a00004',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Express de Brest déraillé à l\'entrée du tunnel de Plogonnec, tender tombé dans un ravin de 20 m, trois employés et cinq autres personnes blessés.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le mardi 13 août 1907, sur la ligne de Landerneau à Quimper, l\'express venant de Brest avait presque atteint l\'entrée du tunnel de Plogonnec, à une dizaine de kilomètres de Quimper selon la liste de Wikipédia.',
                'en' => 'On Tuesday 13 August 1907, on the Landerneau to Quimper line, the express from Brest had almost reached the entrance of the Plogonnec tunnel, about ten kilometres from Quimper according to the Wikipedia list.',
                'de' => 'Am Dienstag, dem 13. August 1907, hatte auf der Strecke von Landerneau nach Quimper der Schnellzug aus Brest den Eingang des Tunnels von Plogonnec, nach der Wikipedia-Liste etwa zehn Kilometer von Quimper entfernt, fast erreicht.',
                'sources' => ['delpher-courant-1907-08-15', 'wp-liste-1907'],
            ],
        ],
        'course' => [
            [
                'fr' => 'La locomotive a déraillé et s\'est renversée sur le côté gauche ; le tender est monté sur elle et est tombé dans un ravin de 20 mètres. La première voiture de voyageurs est restée suspendue à mi-pente et deux autres voitures se sont renversées, selon la presse néerlandaise. La liste de Wikipédia précise que la locomotive, le tender et trois voitures se sont couchés sur le remblai après la rupture de l\'essieu avant de la machine.',
                'en' => 'The locomotive derailed and overturned onto its left side; the tender climbed onto it and fell into a 20-metre ravine. The first passenger coach stayed hanging halfway down and two other coaches overturned, according to the Dutch press. The Wikipedia list adds that the locomotive, tender and three coaches lay down on the embankment after the engine\'s front axle broke.',
                'de' => 'Die Lokomotive entgleiste und kippte auf die linke Seite; der Tender stieg auf sie und stürzte in eine 20 Meter tiefe Schlucht. Der erste Reisezugwagen blieb auf halber Höhe hängen, und zwei weitere Wagen kippten um, nach der niederländischen Presse. Die Wikipedia-Liste ergänzt, Lokomotive, Tender und drei Wagen hätten sich nach dem Bruch der Vorderachse der Maschine auf den Damm gelegt.',
                'sources' => ['delpher-courant-1907-08-15', 'wp-liste-1907'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Trois employés, dont le chauffeur, ont été grièvement blessés et cinq autres personnes moins gravement, selon De Courant, qui ne signale aucun mort ; la circulation était interrompue. La liste de Wikipédia compte un mort et huit blessés.',
                'en' => 'Three employees, including the fireman, were seriously injured and five other people less seriously, according to De Courant, which reports no deaths; traffic was blocked. The Wikipedia list counts one dead and eight injured.',
                'de' => 'Drei Angestellte, darunter der Heizer, wurden schwer und fünf weitere Personen weniger schwer verletzt, laut De Courant, die keinen Todesfall meldet; der Verkehr war unterbrochen. Die Wikipedia-Liste zählt einen Toten und acht Verletzte.',
                'sources' => ['delpher-courant-1907-08-15', 'wp-liste-1907'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le mort de la fiche n\'apparaît pas dans la dépêche néerlandaise du 15 août 1907, qui compte trois blessés graves et cinq blessés, soit huit blessés au total ; la liste de Wikipédia retient un mort et huit blessés. La fiche indique Quimper, alors que l\'accident s\'est produit à l\'entrée du tunnel de Plogonnec.',
                'en' => 'The record\'s death does not appear in the Dutch dispatch of 15 August 1907, which counts three seriously injured and five injured, eight injured in all; the Wikipedia list gives one dead and eight injured. The record gives Quimper, whereas the accident took place at the entrance to the Plogonnec tunnel.',
                'de' => 'Der Todesfall des Datensatzes erscheint nicht in der niederländischen Depesche vom 15. August 1907, die drei Schwerverletzte und fünf Verletzte, also insgesamt acht Verletzte, zählt; die Wikipedia-Liste nennt einen Toten und acht Verletzte. Der Datensatz nennt Quimper, während sich der Unfall am Eingang des Tunnels von Plogonnec ereignete.',
                'sources' => ['delpher-courant-1907-08-15', 'wp-liste-1907'],
            ],
        ],
    ],
    'corrections' => [],
];
