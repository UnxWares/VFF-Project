<?php

// Contexte sourcé de la collision de Perrigny près de Dijon du 22 août 1908 (recherche du 21/11/2026).
return [
    'slug' => '1908-08-22-dole',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1908' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1908',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Août',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1908&oldid=233618338#Ao%C3%BBt',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'À Perrigny, près de Dijon, vers 22 h 30, train de voyageurs pour Dole dont le mécanicien a mal compris la signalisation, pris en écharpe sur un train de marchandises à une bifurcation, une voiture écrasée, un mort et une dizaine de blessés.',
        ],
        'delpher-franeker-1908-08-27' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Brève de France',
            'publisher' => 'Franeker Courant (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1908-08-27',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMTRES04:211431069:mpeg21:a00011',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'À Perrigny, à 2,5 km de Dijon, un train de marchandises en collision avec un train de voyageurs, un mort et sept blessés, mauvais signaux comme cause probable.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'L\'accident s\'est produit à Perrigny, à environ deux kilomètres et demi de Dijon, à une bifurcation, d\'après la liste de Wikipédia et la presse néerlandaise du 27 août 1908. La liste date le choc du 22 août 1908, vers 22 h 30.',
                'en' => 'The accident took place at Perrigny, about two and a half kilometres from Dijon, at a junction, according to the Wikipedia list and the Dutch press of 27 August 1908. The list dates the impact to 22 August 1908, at about 10.30 pm.',
                'de' => 'Der Unfall ereignete sich nach der Wikipedia-Liste und der niederländischen Presse vom 27. August 1908 in Perrigny, etwa zweieinhalb Kilometer von Dijon entfernt, an einer Abzweigung. Die Liste datiert den Zusammenstoß auf den 22. August 1908 gegen 22.30 Uhr.',
                'sources' => ['delpher-franeker-1908-08-27', 'wp-liste-1908'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Un train de voyageurs pour Dole a pris en écharpe un train de marchandises à la bifurcation ; une des voitures a été écrasée, selon la liste de Wikipédia. Le journal néerlandais parle d\'un train de marchandises en collision avec un train de voyageurs.',
                'en' => 'A passenger train for Dole struck a goods train a glancing blow at the junction; one of the coaches was crushed, according to the Wikipedia list. The Dutch newspaper speaks of a goods train colliding with a passenger train.',
                'de' => 'Ein Reisezug nach Dole streifte an der Abzweigung einen Güterzug; einer der Wagen wurde zerdrückt, nach der Wikipedia-Liste. Die niederländische Zeitung spricht von einem Güterzug, der mit einem Reisezug zusammenstieß.',
                'sources' => ['wp-liste-1908', 'delpher-franeker-1908-08-27'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le journal néerlandais compte un mort et sept blessés ; la liste de Wikipédia un mort et une dizaine de blessés.',
                'en' => 'The Dutch newspaper counts one dead and seven injured; the Wikipedia list one dead and about ten injured.',
                'de' => 'Die niederländische Zeitung zählt einen Toten und sieben Verletzte; die Wikipedia-Liste einen Toten und etwa zehn Verletzte.',
                'sources' => ['delpher-franeker-1908-08-27', 'wp-liste-1908'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La presse néerlandaise écrit que de mauvais signaux semblent être la cause ; la liste de Wikipédia précise que le mécanicien du train de voyageurs avait mal compris la signalisation.',
                'en' => 'The Dutch press writes that wrong signals seem to be the cause; the Wikipedia list adds that the passenger train\'s driver had misunderstood the signalling.',
                'de' => 'Die niederländische Presse schreibt, falsche Signale schienen die Ursache zu sein; die Wikipedia-Liste ergänzt, der Lokführer des Reisezugs habe die Signalisierung missverstanden.',
                'sources' => ['delpher-franeker-1908-08-27', 'wp-liste-1908'],
            ],
        ],
    ],
    'corrections' => [],
];
