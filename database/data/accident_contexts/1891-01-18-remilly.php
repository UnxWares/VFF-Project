<?php

// Contexte sourcé du déraillement de l'omnibus Metz - Sarrebourg à la sortie de la gare de Remilly, ligne de Metz à Réding, de janvier 1891 (recherche du 21/11/2026).
return [
    'slug' => '1891-01-18-remilly',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1890' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1890',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1891',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1891',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 18 janvier 1891, d\'après Le Matin du 19 janvier : à 6 h 40, sur la ligne de Metz à Réding, l\'omnibus Metz - Sarrebourg déraille à la sortie de la gare de Remilly après une erreur d\'aiguillage ; la locomotive dévale le remblai, le mécanicien est tué sur le coup, le chauffeur, grièvement brûlé, mourra peu après.',
        ],
        'ddb-1891-01-20-halle' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Metz, 18. Januar',
            'publisher' => 'Hallesches Tageblatt (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1891-01-20',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/3MF77TTKCDKZTJQOHGVNTXYQLY7JENW3?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Metz du 18 janvier (même texte dans le Bürgerfreund du 22) : le train de voyageurs Strasbourg - Metz parti de Metz à 6 h 10 devait prendre un wagon à bestiaux à Remilly ; à cause d\'une erreur d\'aiguillage, semble-t-il, la locomotive, le fourgon et une voiture sont tombés du remblai ; le mécanicien est mort, le chauffeur grièvement blessé ; quelques voyageurs légèrement blessés, aucun selon une dépêche de l\'agence Wolff.',
        ],
        'ddb-1891-01-20-merkur' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Metz, 19. Jan.',
            'publisher' => 'Schwäbischer Merkur (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1891-01-20',
            'locator' => 'p. 6',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/YFMPYNSVJ3HIARGLGGLBXS3TEXKA7JZO?issuepage=6',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Récit de Metz du 19 janvier : pour éviter une manœuvre, l\'agent de service fait passer le train par la voie de dépassement, puis, oubliant ce cas inhabituel, donne le signal pour la voie principale ; le train, qui portait plus de cent voyageurs, n\'a évité que de peu une chute entière du talus.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 18 janvier 1891, au petit jour, l\'omnibus parti de Metz à 6 h 10 vers Sarrebourg et Strasbourg s\'est arrêté en gare de Remilly, où un wagon à bestiaux devait être accroché ou décroché. Pour éviter une manœuvre, l\'agent de service l\'a fait passer par la voie de dépassement.',
                'en' => 'On 18 January 1891, at daybreak, the stopping train that left Metz at 6.10 for Sarrebourg and Strasbourg stopped at Remilly station, where a cattle wagon was to be dealt with. To save shunting, the duty official routed it over the passing loop.',
                'de' => 'Am 18. Januar 1891 bei Tagesanbruch hielt der um 6.10 Uhr in Metz abgefahrene Personenzug nach Saarburg und Straßburg im Bahnhof Remilly, wo ein Viehwagen umgestellt werden sollte. Um Rangierarbeit zu sparen, ließ ihn der diensthabende Beamte über das Überholungsgleis fahren.',
                'sources' => ['ddb-1891-01-20-halle', 'ddb-1891-01-20-merkur', 'wp-liste-1890'],
            ],
        ],
        'course' => [
            [
                'fr' => 'L\'agent a ensuite donné, par habitude, le signal pour la voie principale. Le train a déraillé à la sortie de la gare : la locomotive, le fourgon et une voiture sont tombés du remblai.',
                'en' => 'The official then set the signal for the main line out of habit. The train derailed on leaving the station: the engine, the van and one coach fell down the embankment.',
                'de' => 'Der Beamte gab danach aus Gewohnheit das Signal für das Hauptgleis. Der Zug entgleiste bei der Ausfahrt: Lokomotive, Packwagen und ein Personenwagen stürzten die Böschung hinab.',
                'sources' => ['ddb-1891-01-20-merkur', 'ddb-1891-01-20-halle', 'wp-liste-1890'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien a été tué sur le coup. Le chauffeur, grièvement blessé et brûlé, est mort peu après selon la liste de Wikipédia. Quelques voyageurs ont été légèrement blessés.',
                'en' => 'The driver was killed outright. The stoker, badly injured and burnt, died shortly afterwards according to the Wikipedia list. A few passengers were slightly hurt.',
                'de' => 'Der Lokführer war sofort tot. Der schwer verletzte und verbrühte Heizer starb nach der Wikipedia-Liste kurz darauf. Einige Reisende wurden leicht verletzt.',
                'sources' => ['wp-liste-1890', 'ddb-1891-01-20-halle'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Une dépêche de l\'agence Wolff, citée par la presse de Halle, dit qu\'aucun voyageur n\'a été blessé.',
                'en' => 'A Wolff agency dispatch, quoted by the Halle press, says no passenger was injured.',
                'de' => 'Eine Depesche des Wolffschen Bureaus, die die Hallesche Presse zitiert, meldet keine verletzten Reisenden.',
                'sources' => ['ddb-1891-01-20-halle'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1890', 'ddb-1891-01-20-halle'],
            'fr' => 'Mécanicien tué sur le coup et chauffeur mort peu après selon la liste de Wikipédia (Le Matin du 19 janvier 1891) ; la dépêche de Metz du 18 janvier donne le mécanicien mort et le chauffeur grièvement blessé ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Driver killed outright and stoker died shortly afterwards according to the Wikipedia list (Le Matin of 19 January 1891); the Metz dispatch of 18 January has the driver dead and the stoker badly injured; the record gave no number of deaths.',
            'de' => 'Lokführer sofort tot und Heizer kurz darauf gestorben nach der Wikipedia-Liste (Le Matin vom 19. Januar 1891); die Depesche aus Metz vom 18. Januar meldet den Lokführer tot und den Heizer schwer verletzt; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
