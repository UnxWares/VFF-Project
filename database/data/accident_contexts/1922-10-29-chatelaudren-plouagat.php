<?php

// Contexte sourcé de l'accident de Châtelaudren du 29 octobre 1922 (recherche du 21/11/2026).
return [
    'slug' => '1922-10-29-chatelaudren-plouagat',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1922' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1922',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1922&oldid=236708402#Octobre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 29 octobre 1922 ; elle renvoie à Le Petit Parisien, Le Figaro et L\'Ouest-Éclair (1922 et 1923).',
        ],
        'appl' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'association',
            'title' => 'Cimetière du Père Lachaise : fiche d\'une victime de la catastrophe de Châtelaudren',
            'publisher' => 'Amis et Passionnés du Père Lachaise (APPL)',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.appl-lachaise.net/david-leon-rene-1898-1922/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'La fiche reprend un extrait du Petit Écho du Châté, bulletin municipal de Châtelaudren (2e trimestre 2017) ; faits seulement, sans nom.',
        ],
        'cheminots' => [
            'provider' => 'sites-web',
            'kind' => 'forum',
            'reliability' => 'forum',
            'title' => 'Les catastrophes ferroviaires, page 6',
            'publisher' => 'Le Web des Cheminots (forum)',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.cheminots.net/topic/22490-les-catastrophes-ferroviaires/page/6/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Message qui reprend la liste de Wikipédia ; un intervenant signale une plaque dans la gare.',
        ],
        'manche-ocean' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'association',
            'title' => 'Accidents',
            'publisher' => 'Manche Océan (histoire de la ligne)',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.manche-ocean.fr/exploitation/accidents.htm',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Liste d\'accidents sur la ligne ; confirme la collision du rapide Paris-Brest en gare le 29 octobre 1922.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 29 octobre 1922 vers 6 h, en gare de Châtelaudren (Côtes-du-Nord, aujourd\'hui Côtes-d\'Armor), sur la ligne de Paris à Brest, une tempête de neige perturbe le fonctionnement des signaux.',
                'en' => 'On 29 October 1922 at about 6 a.m., at Châtelaudren station (Côtes-du-Nord, now Côtes-d\'Armor) on the Paris to Brest line, a snowstorm disrupted the signals.',
                'de' => 'Am 29. Oktober 1922 gegen 6 Uhr störte im Bahnhof Châtelaudren (Côtes-du-Nord, heute Côtes-d\'Armor) an der Strecke Paris-Brest ein Schneesturm das Funktionieren der Signale.',
                'sources' => ['wp-liste-1922', 'appl'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le rapide Paris-Brest heurte un train de marchandises qui manœuvrait pour lui libérer la voie. Les trois voitures de tête du rapide sont écrasées contre une locomotive déraillée.',
                'en' => 'The Paris to Brest express ran into a goods train that was shunting to clear the line for it. The first three coaches of the express were crushed against a derailed locomotive.',
                'de' => 'Der Schnellzug Paris-Brest fuhr auf einen Güterzug auf, der rangierte, um ihm das Gleis freizumachen. Die ersten drei Wagen des Schnellzugs wurden gegen eine entgleiste Lokomotive gedrückt.',
                'sources' => ['wp-liste-1922', 'appl', 'manche-ocean'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'L\'accident fait quatorze morts et une quarantaine de blessés, dont cinq meurent par la suite, soit dix-neuf morts au total. Soixante chevaux ont aussi été tués.',
                'en' => 'The accident killed fourteen people and injured about forty, five of whom died later, nineteen deaths in all. Sixty horses were also killed.',
                'de' => 'Bei dem Unfall starben vierzehn Menschen, etwa vierzig wurden verletzt, von denen fünf später starben, insgesamt also neunzehn Tote. Sechzig Pferde kamen ebenfalls um.',
                'sources' => ['wp-liste-1922', 'appl'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Le signal avancé ne fonctionnait pas et la sonnerie annonçant sa fermeture n\'a pas retenti. Le chef de gare avait fermé les signaux pour couvrir la manœuvre, mais le rapide ne ralentit pas.',
                'en' => 'The distant signal was not working and the bell announcing that it had closed did not ring. The stationmaster had closed the signals to protect the shunting, but the express did not slow down.',
                'de' => 'Das Vorsignal funktionierte nicht, und das Läutewerk, das sein Schließen anzeigen sollte, ertönte nicht. Der Bahnhofsvorsteher hatte die Signale zur Deckung der Rangierfahrt geschlossen, doch der Schnellzug verlangsamte nicht.',
                'sources' => ['appl'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le 31 juillet 1923, la cour d\'appel de Rennes condamne pour homicide involontaire le sous-chef de gare de Saint-Brieuc à trois mois de prison ferme et un agent de la gare de Châtelaudren à un mois avec sursis. Le mécanicien du rapide, accusé de ne pas avoir respecté des consignes de ralentissement, est acquitté.',
                'en' => 'On 31 July 1923, the Rennes court of appeal convicted the assistant stationmaster of Saint-Brieuc of involuntary manslaughter, with three months in prison without suspension, and a Châtelaudren station employee, with one month suspended. The driver of the express, accused of ignoring speed-reduction instructions, was acquitted.',
                'de' => 'Am 31. Juli 1923 verurteilte das Berufungsgericht Rennes den stellvertretenden Bahnhofsvorsteher von Saint-Brieuc wegen fahrlässiger Tötung zu drei Monaten Haft ohne Bewährung und einen Bediensteten des Bahnhofs Châtelaudren zu einem Monat auf Bewährung. Der Lokführer des Schnellzugs, dem vorgeworfen worden war, Anweisungen zur Geschwindigkeitsverringerung missachtet zu haben, wurde freigesprochen.',
                'sources' => ['wp-liste-1922', 'appl', 'cheminots'],
            ],
        ],
        'memory' => [
            [
                'fr' => 'Un intervenant d\'un forum de cheminots dit avoir vu une plaque commémorative dans la gare, sans en donner le texte ni la date. Le bulletin municipal de Châtelaudren a consacré un article à la catastrophe en 2017.',
                'en' => 'A contributor to a railway workers\' forum says he saw a commemorative plaque in the station, without giving its text or date. The Châtelaudren municipal bulletin devoted an article to the disaster in 2017.',
                'de' => 'Ein Teilnehmer eines Eisenbahnerforums gibt an, im Bahnhof eine Gedenktafel gesehen zu haben, ohne Text oder Datum zu nennen. Das Gemeindeblatt von Châtelaudren widmete der Katastrophe 2017 einen Artikel.',
                'sources' => ['cheminots', 'appl'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La fiche de l\'APPL, d\'après le bulletin municipal, donne dix-neuf morts et trente-six blessés ; la liste de Wikipédia donne quatorze morts et une quarantaine de blessés, dont cinq morts ensuite. Les deux totaux de morts concordent.',
                'en' => 'The APPL entry, based on the municipal bulletin, gives nineteen dead and thirty-six injured; the Wikipedia list gives fourteen dead and about forty injured, five of whom died later. The two totals of deaths agree.',
                'de' => 'Der Eintrag des APPL nennt nach dem Gemeindeblatt neunzehn Tote und sechsunddreißig Verletzte; die Wikipedia-Liste nennt vierzehn Tote und etwa vierzig Verletzte, von denen fünf später starben. Die beiden Gesamtzahlen der Toten stimmen überein.',
                'sources' => ['appl', 'wp-liste-1922'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 19,
            'sources' => ['wp-liste-1922', 'appl'],
            'fr' => 'Quatorze morts sur le coup et cinq blessés morts ensuite (liste de Wikipédia), soit dix-neuf, total confirmé par la fiche de l\'APPL d\'après le bulletin municipal.',
            'en' => 'Fourteen killed at once and five injured who died later (Wikipedia list), nineteen in all, a total confirmed by the APPL entry based on the municipal bulletin.',
            'de' => 'Vierzehn sofort Getötete und fünf später gestorbene Verletzte (Wikipedia-Liste), insgesamt neunzehn; diese Summe bestätigt der Eintrag des APPL nach dem Gemeindeblatt.',
        ],
    ],
];
