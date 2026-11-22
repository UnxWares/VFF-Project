<?php

// Contexte sourcé du tamponnement d'un train de voyageurs en détresse par un train de marchandises entre Montpaon et Fondamente, ligne de Béziers à Neussargues, de septembre 1894 (recherche du 21/11/2026).
return [
    'slug' => '1894-09-06-fondamente',
    'checked_on' => '2026-11-22',
    'sources' => [
        'wp-liste-1890' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1890',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1894',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1894',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 6 septembre 1894, d\'après La Presse du 13 septembre et un rapport au Comité de l\'exploitation technique des chemins de fer : sur la ligne de Béziers à Neussargues, entre les gares de Montpaon et de Fondamente, vers 20 h 30, un train de voyageurs en détresse après la rupture de sa conduite de frein est tamponné par le train de marchandises parti derrière lui ; le conducteur du train tamponneur est tué en sautant du fourgon de tête ; 8 voyageurs blessés.',
        ],
        'ddb-1894-09-09-echo' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Rodez, 8. Sept. (Telegr.)',
            'publisher' => 'Echo der Gegenwart (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1894-09-09',
            'locator' => 'p. 8',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/UHZ2BHL22ZRB4DACXIRUWXJKHKARAOYB?issuepage=8',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Télégramme de Rodez du 8 septembre : hier, sur la ligne de Béziers à Neussargues, collision de deux trains ; quatre wagons brisés, un conducteur tué et six personnes légèrement blessées.',
        ],
        'ddb-1894-09-10-fremdenblatt' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Lille, 7. September',
            'publisher' => 'Hamburger Fremdenblatt (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1894-09-10',
            'locator' => 'p. 14',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/FZYUJKJGDDTBPTNX2MCIWCTGYOH4L24N?issuepage=14',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève datée de Lille, 7 septembre : sur la ligne de Béziers à Neussargues, deux trains se sont heurtés ; quatre wagons brisés, un mécanicien tué, six personnes blessées.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 6 septembre 1894, vers 20 h 30, sur la ligne de Béziers à Neussargues, un train de voyageurs s\'est arrêté entre les gares de Montpaon et de Fondamente après la rupture de sa conduite de frein. Le train de marchandises parti derrière lui l\'a tamponné. Quatre wagons ont été brisés.',
                'en' => 'On 6 September 1894, at about 8.30 pm, on the Béziers to Neussargues line, a passenger train came to a stand between Montpaon and Fondamente stations after its brake pipe broke. The goods train that had left behind it ran into it. Four wagons were smashed.',
                'de' => 'Am 6. September 1894 gegen 20.30 Uhr blieb an der Strecke Béziers-Neussargues ein Personenzug nach einem Bruch der Bremsleitung zwischen den Bahnhöfen Montpaon und Fondamente liegen. Der hinter ihm abgefahrene Güterzug fuhr auf. Vier Wagen wurden zertrümmert.',
                'sources' => ['wp-liste-1890', 'ddb-1894-09-09-echo', 'ddb-1894-09-10-fremdenblatt'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le conducteur du train de marchandises a été tué en sautant du fourgon de tête. Huit voyageurs ont été blessés selon la liste de Wikipédia ; le télégramme de Rodez compte six personnes légèrement blessées.',
                'en' => 'The conductor of the goods train was killed jumping from the leading van. Eight passengers were injured according to the Wikipedia list; the Rodez telegram counts six people slightly injured.',
                'de' => 'Der Zugbegleiter des Güterzugs kam beim Absprung aus dem vorderen Packwagen ums Leben. Nach der Wikipedia-Liste wurden acht Reisende verletzt; das Telegramm aus Rodez zählt sechs leicht verletzte Personen.',
                'sources' => ['wp-liste-1890', 'ddb-1894-09-09-echo'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le télégramme de Rodez, daté du 8 septembre, place la collision « hier », soit le 7 septembre ; la liste de Wikipédia la date du 6 septembre.',
                'en' => 'The Rodez telegram, dated 8 September, places the collision "yesterday", that is 7 September; the Wikipedia list dates it 6 September.',
                'de' => 'Das Telegramm aus Rodez vom 8. September setzt den Zusammenstoß auf „gestern“, also den 7. September; die Wikipedia-Liste datiert ihn auf den 6. September.',
                'sources' => ['ddb-1894-09-09-echo', 'wp-liste-1890'],
            ],
            [
                'fr' => 'Une brève datée de Lille le 7 septembre, parue dans le Hamburger Fremdenblatt, rapporte déjà la collision et dit un mécanicien tué ; la liste de Wikipédia et le télégramme de Rodez parlent d\'un conducteur.',
                'en' => 'A brief item datelined Lille, 7 September, printed in the Hamburger Fremdenblatt, already reports the collision and says a driver was killed; the Wikipedia list and the Rodez telegram speak of a conductor.',
                'de' => 'Eine im Hamburger Fremdenblatt gedruckte Kurzmeldung aus Lille vom 7. September berichtet bereits über den Zusammenstoß und nennt einen getöteten Maschinisten; die Wikipedia-Liste und das Telegramm aus Rodez sprechen von einem Zugbegleiter.',
                'sources' => ['ddb-1894-09-10-fremdenblatt', 'ddb-1894-09-09-echo', 'wp-liste-1890'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1890', 'ddb-1894-09-09-echo'],
            'fr' => 'Un conducteur tué selon la liste de Wikipédia (La Presse du 13 septembre 1894) et le télégramme de Rodez ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'One conductor killed according to the Wikipedia list (La Presse of 13 September 1894) and the Rodez telegram; the record gave no number of deaths.',
            'de' => 'Ein Zugbegleiter getötet nach der Wikipedia-Liste (La Presse vom 13. September 1894) und dem Telegramm aus Rodez; der Eintrag nannte keine Zahl von Toten.',
        ],
        [
            'field' => 'kind',
            'value' => 'collision',
            'sources' => ['wp-liste-1890', 'ddb-1894-09-09-echo'],
            'fr' => 'Train de marchandises tamponnant un train de voyageurs arrêté, selon la liste de Wikipédia et le télégramme de Rodez ; la fiche le classait en incident.',
            'en' => 'Goods train running into a stationary passenger train, according to the Wikipedia list and the Rodez telegram; the record classed it as an incident.',
            'de' => 'Güterzug fuhr auf einen haltenden Personenzug auf, nach der Wikipedia-Liste und dem Telegramm aus Rodez; der Eintrag führte den Fall als Vorkommnis.',
        ],
    ],
];
