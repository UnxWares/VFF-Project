<?php

// Contexte sourcé de la collision du rapide Angers - Paris et d'une locomotive près de la gare de La Bohalle, ligne de Tours à Saint-Nazaire, de janvier 1903 (recherche du 21/11/2026).
return [
    'slug' => '1903-01-22-la-bohalle',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1903' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1903',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Janvier',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1903&oldid=239810319#Janvier',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 22 janvier 1903, d\'après Le Petit Journal du 23 janvier : sur la ligne d\'Angers à Tours, près de la gare de La Bohalle, vers 1 h 30, le rapide Angers - Paris heurte à pleine vitesse la locomotive d\'un train de marchandises revenant haut-le-pied sur les voies principales ; le mécanicien et le chauffeur du rapide tués, ceux de la machine heurtée grièvement blessés, ainsi que le chef de train et une dizaine de voyageurs.',
        ],
        'ddb-1903-01-24-karlsruhe' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Angers, 23. Jan. (Telegr.)',
            'publisher' => 'Karlsruher Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1903-01-24',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/YYJK2VP3TCZR3XRTBOLG4BNJDDCZDH6K?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Télégramme d\'Angers du 23 janvier : le rapide parti la veille après-midi pour Paris a heurté près de la gare de La Bohalle la locomotive d\'un train de marchandises ; deux agents de conduite tués, neuf agents et voyageurs blessés.',
        ],
        'ddb-1903-01-26-velbert' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Angers',
            'publisher' => 'Velberter Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1903-01-26',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/FQKHU7VN5BUPT22AUZPUMUX76CQT3K2S?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Récit d\'Angers : sur la ligne Angers - Paris, près de la gare de La Bohalle, le rapide a heurté une locomotive qui revenait vers Angers, si violemment qu\'il a déraillé ; les chauffeurs des deux locomotives tués sur le coup, les deux mécaniciens blessés aux jambes, dans un état désespéré ; le rapide était annoncé sur la voie, mais le mécanicien de la locomotive a vu les signaux trop tard.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le jeudi 22 janvier 1903, le rapide d\'Angers à Paris roulait sur la ligne d\'Angers à Tours. Près de la gare de La Bohalle, la locomotive d\'un train de marchandises revenait haut-le-pied vers Angers sur les voies principales.',
                'en' => 'On Thursday 22 January 1903 the Angers to Paris express was running on the Angers to Tours line. Near La Bohalle station the locomotive of a goods train was running light back towards Angers on the main tracks.',
                'de' => 'Am Donnerstag, dem 22. Januar 1903, fuhr der Schnellzug Angers-Paris auf der Strecke Angers-Tours. Nahe dem Bahnhof La Bohalle fuhr die Lokomotive eines Güterzuges als Leerfahrt auf den Hauptgleisen nach Angers zurück.',
                'sources' => ['wp-liste-1903', 'ddb-1903-01-26-velbert'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le rapide a heurté la locomotive à pleine vitesse et a déraillé. Selon un récit d\'Angers, le rapide était annoncé, mais le mécanicien de la locomotive a vu les signaux trop tard.',
                'en' => 'The express hit the engine at full speed and derailed. According to a report from Angers the express had been signalled, but the engine driver saw the signals too late.',
                'de' => 'Der Schnellzug prallte mit voller Geschwindigkeit auf die Lokomotive und entgleiste. Nach einem Bericht aus Angers war der Schnellzug angekündigt, der Führer der Lokomotive bemerkte die Signale aber zu spät.',
                'sources' => ['wp-liste-1903', 'ddb-1903-01-26-velbert'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Deux agents de conduite ont été tués. La dépêche d\'Angers compte neuf agents et voyageurs blessés ; la liste de Wikipédia cite les deux hommes de la machine heurtée, le chef de train et une dizaine de voyageurs.',
                'en' => 'Two engine crew members were killed. The Angers dispatch counts nine staff and passengers injured; the Wikipedia list names the two men on the engine that was hit, the guard and about ten passengers.',
                'de' => 'Zwei Mann des Lokomotivpersonals wurden getötet. Die Depesche aus Angers zählt neun verletzte Bedienstete und Reisende; die Wikipedia-Liste nennt die beiden Männer der getroffenen Lokomotive, den Zugführer und etwa zehn Reisende.',
                'sources' => ['ddb-1903-01-24-karlsruhe', 'wp-liste-1903'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Les sources ne s\'accordent pas sur les morts : le mécanicien et le chauffeur du rapide selon la liste de Wikipédia, les chauffeurs des deux locomotives selon le récit d\'Angers. Elles diffèrent aussi sur l\'heure : vers 1 h 30 dans la liste, l\'après-midi dans les dépêches.',
                'en' => 'The sources disagree on who died: the driver and stoker of the express according to the Wikipedia list, the stokers of both engines according to the Angers report. They also differ on the time: about 1.30 in the list, the afternoon in the dispatches.',
                'de' => 'Die Quellen nennen verschiedene Tote: Lokführer und Heizer des Schnellzugs nach der Wikipedia-Liste, die Heizer beider Lokomotiven nach dem Bericht aus Angers. Auch die Uhrzeit weicht ab: gegen 1.30 Uhr in der Liste, nachmittags in den Depeschen.',
                'sources' => ['wp-liste-1903', 'ddb-1903-01-26-velbert', 'ddb-1903-01-24-karlsruhe'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1903', 'ddb-1903-01-24-karlsruhe', 'ddb-1903-01-26-velbert'],
            'fr' => 'Deux tués selon la liste de Wikipédia (Le Petit Journal du 23 janvier 1903) et les dépêches d\'Angers ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Two killed according to the Wikipedia list (Le Petit Journal of 23 January 1903) and the Angers dispatches; the record gave no number of deaths.',
            'de' => 'Zwei Tote nach der Wikipedia-Liste (Le Petit Journal vom 23. Januar 1903) und den Depeschen aus Angers; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
