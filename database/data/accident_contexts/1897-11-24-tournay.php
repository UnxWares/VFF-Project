<?php

// Contexte sourcé de l'accident de Tournay du 24 novembre 1897 (recherche du 21/11/2026).
return [
    'slug' => '1897-11-24-tournay',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1890' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1890',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1897',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 24 novembre 1897 ; elle renvoie à Le Figaro du 25 novembre 1897, p. 2, et à la page de gares.loucrup65.fr.',
        ],
        'loucrup' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => 'L\'accident du train à Tournay (Hautes-Pyrénées) en 1897',
            'publisher' => 'Gares des Hautes-Pyrénées (gares.loucrup65.fr)',
            'author' => null,
            'published' => '1897',
            'locator' => null,
            'url' => 'http://gares.loucrup65.fr/accidenttournay.htm',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Page qui rapporte un compte rendu de L\'Illustration de 1897 ; faits seulement, rien recopié.',
        ],
        'figaro-1897' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Figaro, 25 novembre 1897',
            'publisher' => 'Gallica (BnF)',
            'author' => null,
            'published' => '1897-11-25',
            'locator' => 'p. 2',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k2842082/f2.image.r=collision.langFR',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Article de presse cité par Wikipédia ; non lisible depuis la machine de recherche, à consulter.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 24 novembre 1897, vers 6 h 30, sur la ligne de Toulouse à Bayonne, un train mixte de voyageurs et de marchandises venant de Montréjeau s\'arrête en gare de Tournay (Hautes-Pyrénées), après avoir descendu la rampe de Capvern.',
                'en' => 'On 24 November 1897, at about 6.30 a.m., on the Toulouse to Bayonne line, a mixed passenger and goods train from Montréjeau stopped at Tournay station (Hautes-Pyrénées) after coming down the Capvern bank.',
                'de' => 'Am 24. November 1897 gegen 6.30 Uhr hielt auf der Strecke von Toulouse nach Bayonne ein gemischter Personen- und Güterzug aus Montréjeau im Bahnhof Tournay (Hochpyrenäen), nachdem er das Gefälle von Capvern hinabgefahren war.',
                'sources' => ['wp-liste-1890'],
            ],
            [
                'fr' => 'Un train de travaux, dit train de ballast, devait suivre ce train à vingt minutes d\'intervalle. Il comprenait deux fourgons et douze plates-formes lourdement chargées.',
                'en' => 'A works train, known as a ballast train, was due to follow it at a twenty-minute interval. It consisted of two vans and twelve heavily loaded flat wagons.',
                'de' => 'Ein Bauzug, ein sogenannter Schotterzug, sollte dem Zug im Abstand von zwanzig Minuten folgen. Er bestand aus zwei Gepäckwagen und zwölf schwer beladenen Plattformwagen.',
                'sources' => ['loucrup'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le train de travaux prend de la vitesse dans la pente de 33 pour mille, ne parvient pas à freiner sur le rail glissant et percute le train arrêté. Sa locomotive monte sur le convoi et écrase les voitures de queue.',
                'en' => 'The works train gathered speed on the 33 per thousand gradient, could not brake on the slippery rail and ran into the stationary train. Its locomotive rode up onto the train and crushed the rear coaches.',
                'de' => 'Der Bauzug nahm im Gefälle von 33 Promille Fahrt auf, konnte auf der schlüpfrigen Schiene nicht bremsen und fuhr auf den haltenden Zug auf. Seine Lokomotive schob sich auf den Zug und zerdrückte die letzten Wagen.',
                'sources' => ['wp-liste-1890', 'loucrup'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le bilan retenu par la liste est de quatorze morts et huit blessés très graves, surtout parmi les ouvriers du train de travaux.',
                'en' => 'The toll given in the list is fourteen dead and eight very seriously injured, mostly among the workers on the works train.',
                'de' => 'Die Liste nennt vierzehn Tote und acht Schwerverletzte, überwiegend unter den Arbeitern des Bauzugs.',
                'sources' => ['wp-liste-1890'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La catastrophe est attribuée à l\'emballement du train de travaux sur une rampe de douze kilomètres aux rails humides, malgré les freins serrés au point de faire fondre les patins par le frottement.',
                'en' => 'The disaster was put down to the works train running away on a twelve-kilometre incline with damp rails, despite brakes applied so hard that friction melted the brake blocks.',
                'de' => 'Die Katastrophe wird dem Durchgehen des Bauzugs auf einer zwölf Kilometer langen Steigung mit feuchten Schienen zugeschrieben, obwohl die Bremsen so stark angezogen waren, dass die Bremsklötze durch Reibung schmolzen.',
                'sources' => ['loucrup'],
            ],
        ],
        'memory' => [
            [
                'fr' => 'Au cimetière de Tarbes (La Sède), la tombe du chauffeur du train de travaux porte la mention de victime de la catastrophe de Tournay.',
                'en' => 'In the Tarbes cemetery (La Sède), the grave of the fireman of the works train bears the inscription of a victim of the Tournay disaster.',
                'de' => 'Auf dem Friedhof von Tarbes (La Sède) trägt das Grab des Heizers des Bauzugs die Inschrift als Opfer der Katastrophe von Tournay.',
                'sources' => ['loucrup'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La liste de Wikipédia donne quatorze morts, tandis que le compte rendu de L\'Illustration rapporté sur la page locale parle d\'une quinzaine de personnes tuées sur le coup ou mortes de leurs blessures.',
                'en' => 'The Wikipedia list gives fourteen dead, whereas the L\'Illustration report relayed on the local page speaks of about fifteen people killed outright or dead from their injuries.',
                'de' => 'Die Wikipedia-Liste nennt vierzehn Tote, während der auf der lokalen Seite wiedergegebene Bericht der Zeitschrift L\'Illustration von etwa fünfzehn sofort getöteten oder später ihren Verletzungen erlegenen Personen spricht.',
                'sources' => ['wp-liste-1890', 'loucrup'],
            ],
        ],
    ],
];
