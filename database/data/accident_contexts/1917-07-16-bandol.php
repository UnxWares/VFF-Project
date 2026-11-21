<?php

// Contexte sourcé de la collision près de Bandol du 16 juillet 1917 (recherche du 21/11/2026).
return [
    'slug' => '1917-07-16-bandol',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1917' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1917',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Juillet',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1917&oldid=235357718#Juillet',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : ligne de Marseille à Vintimille près de Bandol, vers 7 h, rame détachée après rupture d\'attelage percutée par le marchandises suivant, 2 morts et plusieurs blessés ; renvoie au Figaro du 17 juillet 1917.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 16 juillet 1917, vers 7 heures, sur la ligne de Marseille à Vintimille près de Bandol, une rame de voitures s\'est détachée d\'un train de voyageurs à la suite d\'une rupture d\'attelage. Elle a été percutée par le train de marchandises qui suivait.',
                'en' => 'On 16 July 1917, at about 7 am, on the Marseille to Ventimiglia line near Bandol, a rake of carriages became detached from a passenger train after a coupling failure. It was struck by the goods train following behind.',
                'de' => 'Am 16. Juli 1917 gegen 7 Uhr löste sich auf der Strecke von Marseille nach Ventimiglia bei Bandol nach einem Kupplungsbruch eine Wagengruppe von einem Reisezug. Der nachfolgende Güterzug fuhr auf sie auf.',
                'sources' => ['wp-liste-1917'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'La collision a fait deux morts et plusieurs blessés, dont le nombre n\'est pas donné.',
                'en' => 'The collision killed two people and injured several others, whose number is not given.',
                'de' => 'Bei dem Zusammenstoß starben zwei Menschen, mehrere weitere wurden verletzt; ihre Zahl wird nicht genannt.',
                'sources' => ['wp-liste-1917'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La rupture d\'attelage a laissé une partie du train isolée sur la voie, sur le parcours du train de marchandises suivant.',
                'en' => 'The coupling failure left part of the train stranded on the track, in the path of the following goods train.',
                'de' => 'Der Kupplungsbruch ließ einen Teil des Zuges auf der Strecke zurück, im Weg des nachfolgenden Güterzugs.',
                'sources' => ['wp-liste-1917'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'kind',
            'value' => 'collision',
            'sources' => ['wp-liste-1917'],
            'fr' => 'La liste de Wikipédia, source de la fiche, décrit une rame détachée après une rupture d\'attelage puis percutée par le train de marchandises suivant : une collision entre trains. Aucun ouvrage n\'a cédé.',
            'en' => 'The Wikipedia list, the source of the entry, describes a rake left behind after a coupling failure and then struck by the following goods train: a collision between trains. No structure gave way.',
            'de' => 'Die Wikipedia-Liste, Quelle des Eintrags, beschreibt eine nach einem Kupplungsbruch abgetrennte Wagengruppe, auf die der nachfolgende Güterzug auffuhr: ein Zusammenstoß von Zügen. Kein Bauwerk gab nach.',
        ],
    ],
];
