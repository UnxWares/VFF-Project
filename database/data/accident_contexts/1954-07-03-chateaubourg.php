<?php

// Contexte sourcé de la collision du 3 juillet 1954 (Châteaubourg, Ardèche ; fiche « Tournon-sur-Rhône ») (recherche du 21/11/2026).
return [
    'slug' => '1954-07-03-chateaubourg',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-de' => [
            'provider' => 'wikipedia-de',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Eisenbahnunfall von Tournon-sur-Rhône',
            'publisher' => 'Wikipedia (Deutsch)',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://de.wikipedia.org/wiki/Eisenbahnunfall_von_Tournon-sur-Rhône',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'S\'appuie sur Semmens, Katastrophen auf Schienen (Transpress, 1996, ISBN 3-344-71030-3, p. 152) ; place l\'accident à environ 10 km de Tournon.',
        ],
        'cheminots-forum' => [
            'provider' => 'sites-web',
            'kind' => 'forum',
            'reliability' => 'forum',
            'title' => '3 juillet 1954 - Collision de Châteaubourg (Ardèche)',
            'publisher' => 'Le Web des Cheminots (forum)',
            'author' => null,
            'published' => '2014-08',
            'locator' => null,
            'url' => 'https://www.cheminots.net/topic/38980-3-juillet-1954-collision-de-ch%C3%A2teaubourg-ard%C3%A8che/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Fil de forum qui reprend des photographies des archives du Progrès (31 juillet 2014) ; fiabilité limitée.',
        ],
        'trainsdumidi' => [
            'provider' => 'sites-web',
            'kind' => 'forum',
            'reliability' => 'forum',
            'title' => 'Commune de Châteaubourg (07)',
            'publisher' => 'Trains du Midi (forum)',
            'author' => null,
            'published' => '2017',
            'locator' => null,
            'url' => 'https://www.trainsdumidi.com/t15221-commune-de-chateaubourg-07',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Reprend le même message de forum ; donne 37 morts dont 2 décédés ensuite et près de 50 blessés.',
        ],
        'wp-fr-liste-1950' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1950',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => '3 juillet 1954',
            'url' => 'https://fr.wikipedia.org/wiki/Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1950',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Situe la collision à Châteaubourg (Ardèche), d\'après Le Progrès (30/07/2019) et Le Monde (07/07/1954).',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 3 juillet 1954, sur la ligne de la rive droite du Rhône (Lyon-Perrache, Givors-Canal, Tournon-sur-Rhône, Le Teil, Nîmes), à Châteaubourg (Ardèche), un autorail direct Lyon-Nîmes du soir circule sur une voie unique temporaire.',
                'en' => 'On 3 July 1954, on the right-bank Rhône line (Lyon-Perrache, Givors-Canal, Tournon-sur-Rhône, Le Teil, Nîmes), at Châteaubourg (Ardèche), a through evening railcar from Lyon to Nîmes was running on a temporary single track.',
                'de' => 'Am 3. Juli 1954 fuhr auf der Strecke am rechten Rhoneufer (Lyon-Perrache, Givors-Canal, Tournon-sur-Rhône, Le Teil, Nîmes) bei Châteaubourg (Ardèche) ein durchgehender Abend-Triebwagen von Lyon nach Nîmes auf einem vorübergehend eingleisigen Abschnitt.',
                'sources' => ['cheminots-forum', 'trainsdumidi'],
            ],
            [
                'fr' => 'L\'article allemand décrit une ligne à double voie dont une voie était en réparation, les trains circulant dans les deux sens sur l\'autre voie. L\'autorail, avec deux remorques, venait de Lyon ; en sens inverse circulait un train de marchandises.',
                'en' => 'The German article describes a double-track line on which one track was under repair, trains running in both directions on the other. The railcar, with two trailers, came from Lyon; a goods train was coming the other way.',
                'de' => 'Der deutsche Artikel beschreibt eine zweigleisige Strecke, auf der ein Gleis in Reparatur war und die Züge in beide Richtungen auf dem anderen verkehrten. Der Triebwagen mit zwei Beiwagen kam aus Lyon; in Gegenrichtung fuhr ein Güterzug.',
                'sources' => ['wp-de'],
            ],
        ],
        'course' => [
            [
                'fr' => 'L\'autorail et un train circulant en sens inverse (train de marchandises selon un témoignage, locomotive d\'un train de travaux selon un autre) se percutent de front. L\'avant de l\'autorail et la première remorque sont gravement endommagés.',
                'en' => 'The railcar and a train coming the other way (a goods train according to one account, the locomotive of a works train according to another) collided head-on. The front of the railcar and the first trailer were severely damaged.',
                'de' => 'Der Triebwagen und ein entgegenkommender Zug (laut einer Darstellung ein Güterzug, laut einer anderen die Lokomotive eines Bauzuges) stießen frontal zusammen. Das Vorderteil des Triebwagens und der erste Beiwagen wurden schwer beschädigt.',
                'sources' => ['wp-de', 'cheminots-forum', 'trainsdumidi'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Les messages de forum comptent 37 morts, dont 2 décédés plus tard des suites de l\'accident, et près de 50 blessés ; l\'article allemand donne 33 morts sans mentionner de blessés.',
                'en' => 'The forum posts count 37 dead, 2 of whom died later of their injuries, and nearly 50 injured; the German article gives 33 dead and does not mention injured.',
                'de' => 'Die Forenbeiträge nennen 37 Tote, davon 2 später an den Folgen Verstorbene, und fast 50 Verletzte; der deutsche Artikel gibt 33 Tote an und erwähnt keine Verletzten.',
                'sources' => ['cheminots-forum', 'trainsdumidi', 'wp-de'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La cause avancée est une erreur d\'aiguillage : après la fin des travaux, un agent a poursuivi l\'exploitation à voie unique temporaire au lieu de rétablir la circulation sur deux voies, de sorte que les deux trains se sont trouvés face à face sur la même voie. Cette explication provient de l\'article allemand ; le forum parle seulement d\'une erreur d\'aiguillage.',
                'en' => 'The cause put forward is a points or signalling error: after the works ended, a member of staff kept running single-track working instead of restoring double-track operation, so that the two trains met head-on on the same track. This explanation comes from the German article; the forum speaks only of a switching error.',
                'de' => 'Als Ursache wird ein Weichen- bzw. Stellfehler genannt: Nach Abschluss der Arbeiten setzte ein Bediensteter den eingleisigen Betrieb fort, statt den zweigleisigen wiederherzustellen, sodass die beiden Züge auf demselben Gleis aufeinander zufuhren. Diese Erklärung stammt aus dem deutschen Artikel; das Forum spricht nur von einem Weichenfehler.',
                'sources' => ['wp-de', 'trainsdumidi', 'cheminots-forum'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le lieu est discuté : la fiche et l\'article allemand parlent de Tournon-sur-Rhône (« à environ 10 km »), les messages de forum et la liste française des accidents des années 1950 de Châteaubourg, commune voisine. Le bilan varie de 33 à 37 morts, et la nature du second train (marchandises ou train de travaux) diffère aussi.',
                'en' => 'The place is disputed: the record and the German article speak of Tournon-sur-Rhône ("about 10 km away"), the forum posts and the French list of 1950s accidents of Châteaubourg, a neighbouring commune. The toll varies from 33 to 37 dead, and the nature of the second train (goods or works train) also differs.',
                'de' => 'Der Ort ist umstritten: Der Datensatz und der deutsche Artikel sprechen von Tournon-sur-Rhône („etwa 10 km entfernt“), die Forenbeiträge und die französische Liste der Unfälle der 1950er Jahre von Châteaubourg, einer Nachbargemeinde. Die Opferzahl schwankt zwischen 33 und 37, und auch die Art des zweiten Zuges (Güter- oder Bauzug) wird unterschiedlich angegeben.',
                'sources' => ['wp-de', 'cheminots-forum', 'trainsdumidi'],
            ],
        ],
    ],
    'images' => [],
    'corrections' => [
        [
            'field' => 'commune_insee',
            'value' => '07059',
            'sources' => ['wp-fr-liste-1950', 'cheminots-forum'],
            'fr' => 'La liste française des accidents (appuyée sur Le Progrès et Le Monde) et les messages des cheminots situent la collision à Châteaubourg, au nord de Tournon-sur-Rhône.',
            'en' => 'The French list of accidents (based on Le Progrès and Le Monde) and the railway workers\' posts place the collision at Châteaubourg, north of Tournon-sur-Rhône.',
            'de' => 'Die französische Unfallliste (gestützt auf Le Progrès und Le Monde) und die Beiträge der Eisenbahner verorten den Zusammenstoß in Châteaubourg nördlich von Tournon-sur-Rhône.',
        ],
    ],
];
