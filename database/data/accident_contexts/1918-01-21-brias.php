<?php

// Contexte sourcé de la double collision de la rampe de Pernes-Camblain à Brias du 21 janvier 1918 (recherche du 21/11/2026).
return [
    'slug' => '1918-01-21-brias',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1918' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1918',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Janvier',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1918&oldid=241528217#Janvier',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : ligne de Lille à Abbeville par Saint-Pol, rampe de Pernes-Camblain à Brias, rupture d\'attelage, rame en dérive contre un train de mineurs puis contre un troisième train, 2 morts et 11 blessés.',
        ],
        'petit-parisien-1918-01-22' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Parisien du 22 janvier 1918',
            'publisher' => 'Le Petit Parisien',
            'author' => null,
            'published' => '1918-01-22',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k566418q/f3.image.r=tamponne.langFR',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Récit cité par la liste de Wikipédia.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 21 janvier 1918, sur la ligne de Lille à Abbeville par Saint-Pol-sur-Ternoise, une rupture d\'attelage s\'est produite dans la forte rampe qui monte de la gare de Pernes-Camblain vers Brias. Une rame de wagons de marchandises et sa locomotive de renfort sont parties en dérive.',
                'en' => 'On 21 January 1918, on the Lille to Abbeville line via Saint-Pol-sur-Ternoise, a coupling failed on the steep gradient climbing from Pernes-Camblain station towards Brias. A rake of goods wagons and its banking locomotive ran away backwards.',
                'de' => 'Am 21. Januar 1918 riss auf der Strecke von Lille nach Abbeville über Saint-Pol-sur-Ternoise in der starken Steigung vom Bahnhof Pernes-Camblain nach Brias eine Kupplung. Eine Rampe aus Güterwagen und ihre Vorspannlokomotive liefen rückwärts ab.',
                'sources' => ['wp-liste-1918'],
            ],
        ],
        'course' => [
            [
                'fr' => 'La rame en dérive a percuté le train de mineurs qui la suivait, dont le mécanicien n\'a pas pu faire machine arrière assez vite. Le convoi percuté a ensuite été projeté sur un troisième train, chargé de charbon, qui gravissait la pente.',
                'en' => 'The runaway rake struck the miners\' train following it, whose driver could not reverse quickly enough. The struck train was then thrown against a third train, loaded with coal, climbing the gradient.',
                'de' => 'Die ablaufende Wagengruppe prallte auf den nachfolgenden Bergarbeiterzug, dessen Lokführer nicht schnell genug zurücksetzen konnte. Der gerammte Zug wurde anschließend gegen einen dritten, mit Kohle beladenen Zug geschoben, der die Steigung hinauffuhr.',
                'sources' => ['wp-liste-1918'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'La double collision a fait deux morts et onze blessés.',
                'en' => 'The double collision killed two people and injured eleven.',
                'de' => 'Bei der doppelten Kollision starben zwei Menschen, elf wurden verletzt.',
                'sources' => ['wp-liste-1918'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'L\'entrée de la liste attribue l\'accident à la rupture d\'attelage dans la rampe, suivie de la dérive de la rame.',
                'en' => 'The list entry attributes the accident to the coupling failure on the gradient, followed by the runaway of the rake.',
                'de' => 'Der Listeneintrag führt den Unfall auf den Kupplungsbruch in der Steigung und das anschließende Abrollen der Wagengruppe zurück.',
                'sources' => ['wp-liste-1918'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'kind',
            'value' => 'runaway',
            'sources' => ['wp-liste-1918'],
            'fr' => 'La liste de Wikipédia, source de la fiche, décrit une rupture d\'attelage dans la rampe, puis une rame partie en dérive qui percute deux trains. La première circonstance est la dérive ; aucun ouvrage n\'a cédé.',
            'en' => 'The Wikipedia list, the source of the entry, describes a coupling failure on the gradient and then a runaway rake striking two trains. The first circumstance is the runaway; no structure gave way.',
            'de' => 'Die Wikipedia-Liste, Quelle des Eintrags, beschreibt einen Kupplungsbruch in der Steigung und eine abrollende Wagengruppe, die auf zwei Züge prallt. Erster Umstand ist das Abrollen; kein Bauwerk gab nach.',
        ],
    ],
];
