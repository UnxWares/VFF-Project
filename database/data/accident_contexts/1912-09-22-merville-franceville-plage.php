<?php

// Contexte sourcé de la collision de deux trains d'excursionnistes près de Merville, le 22 septembre 1912 (recherche du 21/11/2026).
return [
    'slug' => '1912-09-22-merville-franceville-plage',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1912' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1912',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '22 septembre 1912, Merville',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1912&oldid=233727902#Septembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Place la collision vers 19 h près de la gare de Merville, sur la ligne de Cabourg à Caen des chemins de fer du Calvados ; 2 morts et 20 blessés ; cite Le Petit Parisien du 24 septembre 1912.',
        ],
        'nieuwsblad-noorden-1912-09-23' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Nieuwsblad van het Noorden du 23 septembre 1912',
            'publisher' => 'Nieuwsblad van het Noorden (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1912-09-23',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:010666577:mpeg21:a0022',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; dépêche d\'agence. Dépêches de Paris du 22 septembre.',
        ],
        'leeuwarder-1912-09-24' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Leeuwarder courant du 24 septembre 1912',
            'publisher' => 'Leeuwarder courant (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1912-09-24',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:010599550:mpeg21:a0047',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; dépêche d\'agence. Dépêches Reuter de Paris des 22 et 23 septembre.',
        ],
        'petit-parisien-1912-09-24' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Parisien du 24 septembre 1912, page 1',
            'publisher' => 'Le Petit Parisien (numérisé sur Gallica)',
            'author' => null,
            'published' => '1912-09-24',
            'locator' => null,
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k564468w',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Référence citée par la liste de Wikipédia ; à consulter, non lue.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 22 septembre 1912 vers sept heures du soir, deux trains transportant environ un millier d\'excursionnistes sont entrés en collision à Merville, entre Caen et Cabourg, selon les dépêches publiées par la presse néerlandaise.',
                'en' => 'On 22 September 1912 at about seven in the evening, two trains carrying about a thousand excursionists collided at Merville, between Caen and Cabourg, according to dispatches published by the Dutch press.',
                'de' => 'Am 22. September 1912 gegen sieben Uhr abends stießen bei Merville zwischen Caen und Cabourg zwei Züge mit etwa tausend Ausflüglern zusammen, laut den Depeschen der niederländischen Presse.',
                'sources' => ['nieuwsblad-noorden-1912-09-23', 'leeuwarder-1912-09-24', 'wp-liste-1912'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Selon la liste de Wikipédia, le train ramenant à Caen des excursionnistes marchait à vue derrière un autre dépourvu de feu arrière ; il l\'a rattrapé et percuté, écrasant ses derniers wagons.',
                'en' => 'According to the Wikipedia list, the train bringing excursionists back to Caen was running on sight behind another with no tail light; it caught up with it and struck it, crushing its last wagons.',
                'de' => 'Laut der Wikipedia-Liste fuhr der Zug, der die Ausflügler nach Caen zurückbrachte, auf Sicht hinter einem anderen ohne Schlusslicht; er fuhr auf ihn auf und zerdrückte dessen letzte Wagen.',
                'sources' => ['wp-liste-1912'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Les premières dépêches parlaient de huit morts et d\'une vingtaine de blessés. Le télégramme reçu par le ministre des Travaux publics dans la nuit comptait un mort, un mourant et quinze blessés, dont trois grièvement. La liste de Wikipédia donne deux morts et vingt blessés.',
                'en' => 'The first dispatches spoke of eight dead and about twenty injured. The telegram received by the minister of public works during the night counted one dead, one dying and fifteen injured, three of them seriously. The Wikipedia list gives two dead and twenty injured.',
                'de' => 'Die ersten Depeschen sprachen von acht Toten und etwa zwanzig Verletzten. Das in der Nacht beim Minister für öffentliche Arbeiten eingegangene Telegramm zählte einen Toten, einen Sterbenden und fünfzehn Verletzte, davon drei schwer. Die Wikipedia-Liste nennt zwei Tote und zwanzig Verletzte.',
                'sources' => ['nieuwsblad-noorden-1912-09-23', 'leeuwarder-1912-09-24', 'wp-liste-1912'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'L\'entrée est placé à Caen, terminus du train ; les dépêches et la liste situent la collision près de Merville.',
                'en' => 'The entry is placed at Caen, the train\'s destination; the dispatches and the list place the collision near Merville.',
                'de' => 'Der Eintrag ist in Caen verortet, dem Ziel des Zuges; die Depeschen und die Liste verorten den Zusammenstoß bei Merville.',
                'sources' => ['nieuwsblad-noorden-1912-09-23', 'wp-liste-1912'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'commune_insee',
            'value' => '14409',
            'sources' => ['nieuwsblad-noorden-1912-09-23', 'leeuwarder-1912-09-24', 'wp-liste-1912'],
            'fr' => 'La collision a eu lieu à Merville (Merville-Franceville-Plage), entre Caen et Cabourg, selon les dépêches et la liste de Wikipédia.',
            'en' => 'The collision took place at Merville (Merville-Franceville-Plage), between Caen and Cabourg, according to the dispatches and the Wikipedia list.',
            'de' => 'Der Zusammenstoß ereignete sich in Merville (Merville-Franceville-Plage) zwischen Caen und Cabourg, laut den Depeschen und der Wikipedia-Liste.',
        ],
    ],
];
