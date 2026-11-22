<?php

// Contexte sourcé de la collision du Nord-Express et d'un train de marchandises à la bifurcation de Jussy, mai 1900 (recherche du 22/11/2026).
return [
    'slug' => '1900-05-30-jussy',
    'checked_on' => '2026-11-22',
    'sources' => [
        'wp-liste-1900' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1900',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Mai',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1900&oldid=233589330#Mai',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Entrée du 30 mai 1900, d\'après Le Petit Parisien du 31 mai et Le Grand écho du Nord du 2 juin : sur la ligne de Creil à Jeumont, après la gare de Tergnier, vers 15 h 30, le Nord-Express pour Saint-Pétersbourg prend en écharpe la locomotive d\'un train de marchandises à la bifurcation de Jussy ; les deux mécaniciens sont tués, leurs chauffeurs grièvement blessés ; quelques voyageurs légèrement commotionnés sur trente-six.',
        ],
        'ddb-1900-05-31-velbert' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Telegramme und letzte Nachrichten (Paris, 31. Mai)',
            'publisher' => 'Velberter Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1900-05-31',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/6RCGTKQA6QWPUJHOF735BQLTNGVPHUNN?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Dépêche de Paris : grave accident à Jussy (Nord) ; le Nord-Express, attendu à 16 h à Saint-Quentin, a heurté peu avant un train de marchandises ; les deux locomotives détruites ; un mécanicien tué, l\'autre mécanicien et les deux chauffeurs grièvement blessés ; dix voyageurs blessés.',
        ],
        'ddb-1900-06-01-halle' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 31. Mai',
            'publisher' => 'Hallesche Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1900-06-01',
            'locator' => 'p. 6',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/PI33ARFLUSNL5HOFCN7MSNCGBMBIWS75?issuepage=6',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Même dépêche : collision du Nord-Express et d\'un train de marchandises à Jussy, peu avant Saint-Quentin ; un mécanicien tué, un autre et deux chauffeurs grièvement blessés, ainsi que dix voyageurs.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 30 mai 1900 vers 15 h 30, sur la ligne de Creil à Jeumont, le Nord-Express pour Saint-Pétersbourg, composé de voitures-lits, a pris en écharpe la locomotive d\'un train de marchandises à la bifurcation de Jussy, après la gare de Tergnier. Les deux locomotives ont été détruites.',
                'en' => 'On 30 May 1900 at about 3.30 p.m., on the Creil to Jeumont line, the Nord-Express to Saint Petersburg, made up of sleeping cars, struck the engine of a goods train side-on at the Jussy junction, beyond Tergnier station. Both engines were wrecked.',
                'de' => 'Am 30. Mai 1900 gegen 15.30 Uhr rammte auf der Strecke Creil-Jeumont der aus Schlafwagen gebildete Nord-Express nach Sankt Petersburg an der Abzweigung Jussy, hinter dem Bahnhof Tergnier, seitlich die Lokomotive eines Güterzugs. Beide Lokomotiven wurden zerstört.',
                'sources' => ['wp-liste-1900', 'ddb-1900-05-31-velbert', 'ddb-1900-06-01-halle'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Les équipes des deux machines ont payé le plus lourd tribut : selon la liste, les deux mécaniciens ont été tués et leurs chauffeurs grièvement blessés. Les dépêches allemandes parlent aussi de dix voyageurs blessés.',
                'en' => 'The crews of the two engines bore the brunt: according to the list, both drivers were killed and their firemen badly injured. The German dispatches also mention ten injured passengers.',
                'de' => 'Am schwersten traf es die Mannschaften der beiden Lokomotiven: Nach der Liste wurden beide Lokomotivführer getötet und ihre Heizer schwer verletzt. Die deutschen Meldungen nennen außerdem zehn verletzte Reisende.',
                'sources' => ['wp-liste-1900', 'ddb-1900-05-31-velbert'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Morts : deux mécaniciens selon la liste de Wikipédia ; la dépêche du 31 mai n\'en compte qu\'un, l\'autre mécanicien étant grièvement blessé.',
                'en' => 'Deaths: two drivers according to the Wikipedia list; the dispatch of 31 May counts only one, the other driver being badly injured.',
                'de' => 'Tote: zwei Lokomotivführer nach der Wikipedia-Liste; die Meldung vom 31. Mai nennt nur einen, der andere Lokomotivführer sei schwer verletzt.',
                'sources' => ['wp-liste-1900', 'ddb-1900-05-31-velbert'],
            ],
            [
                'fr' => 'Voyageurs : quelques-uns légèrement commotionnés selon la liste, dix blessés selon les dépêches allemandes (grièvement selon certaines, légèrement selon d\'autres).',
                'en' => 'Passengers: a few slightly shaken according to the list, ten injured according to the German dispatches (badly in some versions, slightly in others).',
                'de' => 'Reisende: einige leicht erschüttert nach der Liste, zehn Verletzte nach den deutschen Meldungen (in manchen Fassungen schwer, in anderen leicht).',
                'sources' => ['wp-liste-1900', 'ddb-1900-05-31-velbert', 'ddb-1900-06-01-halle'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1900', 'ddb-1900-05-31-velbert'],
            'fr' => 'Deux mécaniciens tués selon la liste de Wikipédia (Le Petit Parisien du 31 mai 1900) ; la dépêche allemande du 31 mai en compte un, écart écrit ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Two drivers killed according to the Wikipedia list (Le Petit Parisien of 31 May 1900); the German dispatch of 31 May counts one, a difference recorded; the record gave no number of deaths.',
            'de' => 'Zwei Lokomotivführer getötet nach der Wikipedia-Liste (Le Petit Parisien vom 31. Mai 1900); die deutsche Meldung vom 31. Mai nennt einen, die Abweichung ist vermerkt; der Eintrag nannte keine Zahl von Toten.',
        ],
        [
            'field' => 'commune_insee',
            'value' => '02397',
            'sources' => ['wp-liste-1900', 'ddb-1900-05-31-velbert'],
            'fr' => 'Collision à la bifurcation de Jussy selon la liste de Wikipédia et la dépêche de Paris ; Tergnier est la gare précédente.',
            'en' => 'Collision at the Jussy junction according to the Wikipedia list and the Paris dispatch; Tergnier is the previous station.',
            'de' => 'Zusammenstoß an der Abzweigung Jussy nach der Wikipedia-Liste und der Pariser Meldung; Tergnier ist der vorherige Bahnhof.',
        ],
    ],
];
