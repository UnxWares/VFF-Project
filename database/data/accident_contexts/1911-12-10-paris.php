<?php

// Contexte sourcé de la collision à l'entrée de la gare du Nord (Paris) du 10 décembre 1911 (recherche du 21/11/2026).
return [
    'slug' => '1911-12-10-paris',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1911' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1911',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Décembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1911&oldid=233727889#D%C3%A9cembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'À Paris, à l\'entrée de la gare du Nord, un express venant de Hirson et Soissons pris en écharpe par une machine haut-le-pied rentrant au dépôt de La Chapelle, deux dernières voitures éventrées, quatre morts et dix blessés ; renvoie à Le Petit Parisien du 11 décembre 1911 (non lu).',
        ],
        'delpher-courant-1911-12-12' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Spoorwegongeluk bij het Noorderstation te Parijs',
            'publisher' => 'De Courant (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1911-12-12',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMKB19:000958069:mpeg21:a00015',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Récit : dimanche à 16 h 30, train Soissons-Paris de 14 voitures flanqué par une grosse locomotive à cause d\'un mauvais aiguillage, trois dernières voitures renversées.',
        ],
        'delpher-utrechtsch-1911-12-12' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Spoorwegongeluk bij Parijs',
            'publisher' => 'Utrechtsch Provinciaal en Stedelijk Dagblad (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1911-12-12',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMUTRA04:253187281:mpeg21:a00021',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même récit : cinq morts, huit blessés graves, cause attribuée à un mauvais aiguillage, responsable inconnu.',
        ],
        'delpher-arnhemsche-1911-12-13' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Spoorwegongeluk',
            'publisher' => 'Arnhemsche Courant (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1911-12-13',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMKB08:000100557:mpeg21:a0006',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même récit, avec cinq morts et huit blessés plus ou moins graves.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le dimanche 10 décembre 1911, vers 16 h 30, le train de voyageurs de Soissons à Paris, composé de quatorze voitures, arrivait à vitesse normale à la gare du Nord et passait devant le dépôt des machines, selon la presse néerlandaise. La liste de Wikipédia parle d\'un express venant de Hirson et Soissons.',
                'en' => 'On Sunday 10 December 1911, at about 4.30 pm, the Soissons to Paris passenger train, made up of fourteen vehicles, was approaching the Gare du Nord at normal speed and passing the engine shed, according to the Dutch press. The Wikipedia list speaks of an express from Hirson and Soissons.',
                'de' => 'Am Sonntag, dem 10. Dezember 1911, näherte sich gegen 16.30 Uhr der aus vierzehn Fahrzeugen bestehende Reisezug von Soissons nach Paris laut der niederländischen Presse mit normaler Geschwindigkeit dem Gare du Nord und passierte das Lokomotivdepot. Die Wikipedia-Liste spricht von einem Schnellzug aus Hirson und Soissons.',
                'sources' => ['delpher-courant-1911-12-12', 'wp-liste-1911'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Une grosse locomotive, qui rentrait au dépôt de La Chapelle selon la liste de Wikipédia, a pris l\'arrière du train en écharpe à pleine vitesse à la suite d\'un mauvais aiguillage, d\'après la presse néerlandaise. Les trois dernières voitures (une de deuxième classe, une de troisième classe et un fourgon) ont été renversées et écrasées, et la liste précise que les deux dernières ont été éventrées.',
                'en' => 'A large locomotive, which was returning to the La Chapelle depot according to the Wikipedia list, struck the side of the train\'s rear at full speed because of wrongly set points, according to the Dutch press. The last three vehicles (a second-class coach, a third-class coach and a van) were overturned and crushed, and the list adds that the last two were torn open.',
                'de' => 'Eine große Lokomotive, die nach der Wikipedia-Liste zum Depot La Chapelle zurückfuhr, fuhr nach der niederländischen Presse infolge einer falschen Weichenstellung mit voller Geschwindigkeit seitlich in das Zugende. Die letzten drei Fahrzeuge (ein Wagen zweiter Klasse, einer dritter Klasse und ein Packwagen) wurden umgeworfen und zerdrückt; die Liste ergänzt, die letzten beiden seien aufgerissen worden.',
                'sources' => ['delpher-courant-1911-12-12', 'delpher-utrechtsch-1911-12-12', 'wp-liste-1911'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Selon les journaux néerlandais, cinq personnes sont mortes, dont une mère et son enfant, un garçon de huit ans et un homme de 50 ans, et huit ont été grièvement blessées ; l\'un des morts n\'était pas identifié au moment des articles. La liste de Wikipédia compte quatre morts et dix blessés.',
                'en' => 'According to the Dutch newspapers, five people died, including a mother and her child, an eight-year-old boy and a 50-year-old man, and eight were seriously injured; one of the dead had not been identified when the articles were written. The Wikipedia list counts four dead and ten injured.',
                'de' => 'Nach den niederländischen Zeitungen starben fünf Menschen, darunter eine Mutter mit ihrem Kind, ein achtjähriger Junge und ein 50-jähriger Mann, und acht wurden schwer verletzt; einer der Toten war zur Zeit der Artikel noch nicht identifiziert. Die Wikipedia-Liste zählt vier Tote und zehn Verletzte.',
                'sources' => ['delpher-courant-1911-12-12', 'delpher-utrechtsch-1911-12-12', 'delpher-arnhemsche-1911-12-13', 'wp-liste-1911'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La presse néerlandaise attribue l\'accident à un mauvais aiguillage et précise que le responsable n\'était pas encore connu.',
                'en' => 'The Dutch press attributes the accident to wrongly set points and says the person responsible was not yet known.',
                'de' => 'Die niederländische Presse führt den Unfall auf eine falsche Weichenstellung zurück und sagt, der Verantwortliche sei noch nicht bekannt.',
                'sources' => ['delpher-utrechtsch-1911-12-12', 'delpher-courant-1911-12-12'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le bilan diffère : quatre morts et dix blessés dans la liste de Wikipédia, cinq morts et huit blessés graves dans la presse néerlandaise. La fiche indique Hirson, d\'où venait l\'express selon la liste ; l\'accident s\'est produit à l\'entrée de la gare du Nord, à Paris.',
                'en' => 'The toll differs: four dead and ten injured in the Wikipedia list, five dead and eight seriously injured in the Dutch press. The record gives Hirson, where the express came from according to the list; the accident took place at the entrance to the Gare du Nord, in Paris.',
                'de' => 'Die Bilanz weicht ab: vier Tote und zehn Verletzte in der Wikipedia-Liste, fünf Tote und acht Schwerverletzte in der niederländischen Presse. Der Datensatz nennt Hirson, woher der Schnellzug laut Liste kam; der Unfall ereignete sich an der Einfahrt zum Gare du Nord in Paris.',
                'sources' => ['wp-liste-1911', 'delpher-utrechtsch-1911-12-12', 'delpher-courant-1911-12-12'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'commune_insee',
            'value' => '75056',
            'sources' => ['wp-liste-1911', 'delpher-courant-1911-12-12'],
            'fr' => 'La liste de Wikipédia et la presse néerlandaise du 12 décembre 1911 situent l\'accident à l\'entrée de la gare du Nord, à Paris (75056) ; Hirson n\'est que l\'origine du train.',
            'en' => 'The Wikipedia list and the Dutch press of 12 December 1911 place the accident at the entrance to the Gare du Nord, in Paris (75056); Hirson is only where the train came from.',
            'de' => 'Die Wikipedia-Liste und die niederländische Presse vom 12. Dezember 1911 verorten den Unfall an der Einfahrt zum Gare du Nord in Paris (75056); Hirson ist nur der Herkunftsort des Zuges.',
        ],
    ],
];
