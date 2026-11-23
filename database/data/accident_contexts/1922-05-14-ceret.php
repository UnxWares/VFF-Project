<?php

// Contexte sourcé de la collision d'une machine haut-le-pied et d'un train de voyageurs entre Céret et Amélie-les-Bains, le 14 mai 1922 (recherche du 21/11/2026).
return [
    'slug' => '1922-05-14-ceret',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1922' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1922',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '14 mai 1922, Céret',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1922&oldid=236708402#Mai',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Machine haut-le-pied percutant un train de voyageurs vers 13 h entre Céret et Amélie-les-Bains : un mort, trois blessés graves et une quinzaine de blessés légers ; cite Le Petit Parisien du 15 mai 1922 et Le Figaro du 27 novembre 1922 (condamnations).',
        ],
        'volk-1922-05-18' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Het Volk du 18 mai 1922',
            'publisher' => 'Het Volk (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1922-05-18',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:011082345:mpeg21:a0003',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; dépêche d\'agence. Reprend Le Petit Journal ; texte d\'OCR en partie altéré.',
        ],
        'petit-parisien-1922-05-15' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Parisien du 15 mai 1922, page 3',
            'publisher' => 'Le Petit Parisien (numérisé sur Gallica)',
            'author' => null,
            'published' => '1922-05-15',
            'locator' => null,
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1922&oldid=236708402#Mai',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Référence citée par la liste de Wikipédia, sans lien de numérisation ; à consulter, non lue.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le lundi précédant la parution du 18 mai 1922 selon Het Volk, et le 14 mai selon la liste de Wikipédia, une machine haut-le-pied de la ligne de Perpignan à Arles-sur-Tech a heurté un train de voyageurs entre les stations de Céret et d\'Amélie-les-Bains.',
                'en' => 'On the Monday before the issue of 18 May 1922 according to Het Volk, but 14 May according to the Wikipedia list, a light engine of the Perpignan to Arles-sur-Tech line struck a passenger train between Céret and Amélie-les-Bains stations.',
                'de' => 'Am Montag vor der Ausgabe vom 18. Mai 1922 laut Het Volk, aber am 14. Mai laut der Wikipedia-Liste, stieß eine allein fahrende Maschine der Strecke Perpignan-Arles-sur-Tech zwischen den Stationen Céret und Amélie-les-Bains mit einem Reisezug zusammen.',
                'sources' => ['volk-1922-05-18', 'wp-liste-1922'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Het Volk, qui cite Le Petit Journal, compte un mécanicien tué, un chauffeur grièvement blessé, deux autres agents gravement blessés et une vingtaine de voyageurs blessés ou contusionnés ; l\'état de plusieurs victimes s\'est aggravé. La liste de Wikipédia donne un mort, trois blessés graves et une quinzaine de blessés légers.',
                'en' => 'Het Volk, citing Le Petit Journal, counts a driver killed, a fireman seriously injured, two other staff seriously injured and about twenty passengers injured or bruised; the condition of several victims worsened. The Wikipedia list gives one dead, three seriously injured and about fifteen slightly injured.',
                'de' => 'Het Volk, das Le Petit Journal zitiert, zählt einen getöteten Lokführer, einen schwer verletzten Heizer, zwei weitere schwer verletzte Bedienstete und etwa zwanzig verletzte oder geprellte Reisende; der Zustand mehrerer Opfer verschlechterte sich. Die Wikipedia-Liste nennt einen Toten, drei Schwerverletzte und etwa fünfzehn Leichtverletzte.',
                'sources' => ['volk-1922-05-18', 'wp-liste-1922'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'L\'enquête a montré que le mécanicien de la machine haut-le-pied avait agi contre les ordres des chefs de gare d\'Arles et d\'Amélie-les-Bains et avait franchi tous les signaux.',
                'en' => 'The inquiry showed that the driver of the light engine had acted against the orders of the station masters at Arles and Amélie-les-Bains and had run through all the signals.',
                'de' => 'Die Untersuchung ergab, dass der Lokführer der allein fahrenden Maschine entgegen den Anweisungen der Bahnhofsvorsteher von Arles und Amélie-les-Bains gehandelt und alle Signale überfahren hatte.',
                'sources' => ['volk-1922-05-18'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Selon la liste de Wikipédia, six mois plus tard, le mécanicien de la locomotive tamponneuse et le chef de gare d\'Amélie-les-Bains ont été condamnés respectivement à un an et à un mois de prison avec sursis par le tribunal correctionnel de Céret.',
                'en' => 'According to the Wikipedia list, six months later the driver of the colliding locomotive and the station master of Amélie-les-Bains were sentenced by the Céret criminal court to suspended prison terms of one year and one month respectively.',
                'de' => 'Laut der Wikipedia-Liste wurden sechs Monate später der Lokführer der auffahrenden Lokomotive und der Bahnhofsvorsteher von Amélie-les-Bains vom Strafgericht Céret zu einem Jahr beziehungsweise einem Monat Haft auf Bewährung verurteilt.',
                'sources' => ['wp-liste-1922'],
            ],
        ],
    ],
    'corrections' => [],
];
