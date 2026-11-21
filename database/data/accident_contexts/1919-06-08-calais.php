<?php

// Contexte sourcé de la collision de la halte de Saint-Pierre, près de Calais, du 8 juin 1919 (recherche du 21/11/2026).
return [
    'slug' => '1919-06-08-calais',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1919' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1919',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Juin',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1919&oldid=241133344#Juin',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : 8 juin 1919, train de prisonniers allemands venu de Dunkerque tamponné à la halte de Saint-Pierre par deux machines haut-le-pied anglaises, 3 morts et 25 blessés parmi les prisonniers, deux militaires anglais tués.',
        ],
        'delpher-1919-06-10' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Spoorwegongeluk bij Calais (dépêche du 8 juin, publiée le 10)',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Bibliothèque royale des Pays-Bas)',
            'author' => null,
            'published' => '1919-06-10',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMRHCG04:235207206:mpeg21:a00002',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Paris : train de prisonniers de Dunkerque arrêté à Saint-Pierre, tamponné par deux locomotives anglaises à pleine vitesse, dix voitures détruites, 3 prisonniers et 2 militaires anglais tués, 25 prisonniers blessés.',
        ],
        'delpher-1919-06-11' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Een feestmaal met een droevigen afloop (rubrique Spoorwegongeluk in Frankrijk)',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Bibliothèque royale des Pays-Bas)',
            'author' => null,
            'published' => '1919-06-11',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010333565:mpeg21:a0125',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Message sans fil de Calais : même collision, trois prisonniers allemands, un soldat et un sous-officier anglais tués, prisonniers gravement blessés.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 8 juin 1919, un train de prisonniers de guerre allemands venant de Dunkerque était à l\'arrêt à la halte de Saint-Pierre, près de Calais.',
                'en' => 'On 8 June 1919, a train of German prisoners of war coming from Dunkirk was standing at the Saint-Pierre halt, near Calais.',
                'de' => 'Am 8. Juni 1919 stand ein Zug mit deutschen Kriegsgefangenen aus Dunkerque am Haltepunkt Saint-Pierre bei Calais.',
                'sources' => ['wp-liste-1919', 'delpher-1919-06-10', 'delpher-1919-06-11'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Deux locomotives anglaises roulant à pleine vitesse l\'ont tamponné par l\'arrière ; les voitures ont été presque entièrement détruites (dix voitures selon la dépêche du 10 juin). La liste de Wikipédia précise que les deux machines circulaient haut-le-pied.',
                'en' => 'Two English locomotives running at full speed struck it from behind; the coaches were almost completely destroyed (ten coaches according to the dispatch of 10 June). The Wikipedia list specifies that the two engines were running light.',
                'de' => 'Zwei englische Lokomotiven fuhren mit voller Geschwindigkeit von hinten auf den Zug auf; die Wagen wurden fast vollständig zerstört (zehn Wagen laut Meldung vom 10. Juni). Die Wikipedia-Liste präzisiert, dass die beiden Maschinen als Leerfahrt unterwegs waren.',
                'sources' => ['delpher-1919-06-10', 'delpher-1919-06-11', 'wp-liste-1919'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Trois prisonniers allemands et deux militaires anglais, dont un sous-officier, ont été tués, et vingt-cinq prisonniers ont été blessés. La liste de Wikipédia dit de même que les deux militaires anglais qui conduisaient les locomotives sont morts, en plus des trois morts parmi les prisonniers.',
                'en' => 'Three German prisoners and two English servicemen, one of them a non-commissioned officer, were killed, and twenty-five prisoners were injured. The Wikipedia list likewise says that the two English servicemen driving the locomotives died, in addition to the three dead among the prisoners.',
                'de' => 'Drei deutsche Gefangene und zwei englische Militärangehörige, darunter ein Unteroffizier, wurden getötet, und fünfundzwanzig Gefangene verletzt. Die Wikipedia-Liste sagt ebenfalls, dass die beiden englischen Militärangehörigen, die die Lokomotiven führten, ums Leben kamen, zusätzlich zu den drei Toten unter den Gefangenen.',
                'sources' => ['delpher-1919-06-10', 'delpher-1919-06-11', 'wp-liste-1919'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 5,
            'sources' => ['wp-liste-1919', 'delpher-1919-06-10', 'delpher-1919-06-11'],
            'fr' => 'Trois prisonniers et deux militaires anglais ont été tués : cinq morts au total, comme le disent la liste de Wikipédia et les dépêches de juin 1919.',
            'en' => 'Three prisoners and two English servicemen were killed: five dead in all, as the Wikipedia list and the dispatches of June 1919 say.',
            'de' => 'Drei Gefangene und zwei englische Militärangehörige wurden getötet: insgesamt fünf Tote, wie die Wikipedia-Liste und die Meldungen vom Juni 1919 sagen.',
        ],
    ],
];
