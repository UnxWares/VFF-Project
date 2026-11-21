<?php

// Contexte sourcé de la collision de Thomery du 21 octobre 1855 (recherche du 21/11/2026).
return [
    'slug' => '1855-10-21-thomery',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1850' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1850',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => '1855',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1850&oldid=233367195#1855',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée détaillée (heure, rampe, trains, bilan, jugement), appuyée sur La Presse du 25 octobre 1855 et le Journal des débats de novembre 1855.',
        ],
        'larousse-xixe' => [
            'provider' => 'sites-web',
            'kind' => 'book',
            'reliability' => 'scholarly',
            'title' => 'Grand dictionnaire universel du XIXe siècle, tome 3, article « Chemin de fer » (accident de Moret, octobre 1855), p. 211',
            'publisher' => 'Wikisource (transcription non corrigée de l\'ouvrage de Pierre Larousse)',
            'author' => 'Pierre Larousse',
            'published' => '1867',
            'locator' => 'p. 211',
            'url' => 'https://fr.wikisource.org/wiki/Page:Larousse_-_Grand_dictionnaire_universel_du_XIXe_si%C3%A8cle_-_Tome_3,_part._4,_Chao-Chemin.djvu/211',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Récit de l\'époque, texte reconnu automatiquement (erreurs possibles) et interrompu au milieu du récit ; ne donne pas le bilan total.',
        ],
        'gallica-presse-1855' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'La Presse, 25 octobre 1855, p. 1',
            'publisher' => 'Gallica (BnF)',
            'author' => null,
            'published' => '1855-10-25',
            'locator' => 'p. 1',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k477114k/f1.item.r=Moret.zoom',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lien cité par Wikipédia, non lu (Gallica inaccessible).',
        ],
        'gallica-debats-1855' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Journal des débats politiques et littéraires, 27 novembre 1855, p. 2 (jugement du tribunal de Fontainebleau)',
            'publisher' => 'Gallica (BnF)',
            'author' => null,
            'published' => '1855-11-27',
            'locator' => 'p. 2',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k450731k/f2.item.r=Moret.zoom',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lien cité par Wikipédia, non lu.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 21 octobre 1855, vers 4 h du matin, sur la ligne de Paris à Lyon, un train de bestiaux venant de la Nièvre et de Bourgogne (moutons et bœufs destinés à la consommation parisienne) peine sur la rampe de 5 pour mille qui sépare les gares de Moret et de Thomery. Le brouillard rend les rails glissants et le train avance à l\'allure d\'un homme au pas.',
                'en' => 'On 21 October 1855, at about 4 a.m., on the Paris to Lyon line, a cattle train coming from the Nièvre and Burgundy (sheep and oxen intended for Parisian consumption) was struggling up the 5 per mille gradient between Moret and Thomery stations. Fog had made the rails slippery and the train was moving at walking pace.',
                'de' => 'Am 21. Oktober 1855 gegen 4 Uhr morgens quälte sich auf der Strecke von Paris nach Lyon ein Viehzug aus der Nièvre und dem Burgund (Schafe und Ochsen für den Pariser Verbrauch) die Steigung von 5 Promille zwischen den Bahnhöfen Moret und Thomery hinauf. Nebel hatte die Schienen glatt gemacht, und der Zug bewegte sich in Schrittgeschwindigkeit.',
                'sources' => ['wp-liste-1850'],
            ],
            [
                'fr' => 'Une relation de l\'époque rapporte que ce train, arrivé sans retard à Montereau, avait ensuite ralenti sur la rampe, faute de puissance suffisante pour sa charge, d\'après le dictionnaire de Larousse, avec des rails rendus glissants par le brouillard.',
                'en' => 'A contemporary account reports that this train, having reached Montereau on time, had then slowed on the gradient, according to Larousse\'s dictionary because of insufficient power for its load, with rails made slippery by fog.',
                'de' => 'Ein zeitgenössischer Bericht hält fest, dass dieser Zug, der Montereau pünktlich erreicht hatte, danach auf der Steigung langsamer geworden war, nach Larousses Lexikon wegen zu geringer Zugkraft für seine Last, bei vom Nebel glatten Schienen.',
                'sources' => ['larousse-xixe'],
            ],
        ],
        'course' => [
            [
                'fr' => 'L\'express Lyon-Paris, remorqué par une locomotive Crampton, arrive à environ 65 km/h et heurte le train de bestiaux par l\'arrière. Il détruit le fourgon de queue et la voiture qui transportait les personnes chargées de convoyer le bétail, au nombre de 27.',
                'en' => 'The Lyon-Paris express, hauled by a Crampton locomotive, came up at about 65 km/h and struck the cattle train from behind. It destroyed the rear brake van and the carriage carrying the 27 people in charge of escorting the livestock.',
                'de' => 'Der Schnellzug Lyon-Paris, von einer Crampton-Lokomotive gezogen, kam mit etwa 65 km/h heran und fuhr dem Viehzug von hinten auf. Er zerstörte den Schlusswagen und den Wagen, in dem die 27 für die Begleitung des Viehs zuständigen Personen reisten.',
                'sources' => ['wp-liste-1850'],
            ],
            [
                'fr' => 'Selon Larousse, l\'équipe de l\'express roulait à pleine vitesse sur une voie supposée libre et n\'a aperçu le train de bestiaux qu\'à une trentaine de mètres, trop tard pour freiner ; le brouillard avait aussi caché le feu rouge arrière du dernier wagon. La locomotive est montée sur les derniers wagons et est restée en équilibre au-dessus des débris.',
                'en' => 'According to Larousse, the express crew was running at full speed on a line supposed to be clear and saw the cattle train only about thirty metres ahead, too late to brake; fog had also hidden the red tail lamp of the last wagon. The locomotive rode up onto the last wagons and came to rest above the wreckage.',
                'de' => 'Nach Larousse fuhr die Besatzung des Schnellzugs mit voller Geschwindigkeit auf einer als frei geltenden Strecke und sah den Viehzug erst etwa dreißig Meter voraus, zu spät zum Bremsen; der Nebel hatte außerdem das rote Schlusslicht des letzten Wagens verdeckt. Die Lokomotive schob sich auf die letzten Wagen und blieb über den Trümmern stehen.',
                'sources' => ['larousse-xixe'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'L\'accident fait 16 morts et 6 blessés. La plupart des morts se trouvaient dans la dernière voiture, occupée par les convoyeurs du bétail.',
                'en' => 'The accident caused 16 deaths and 6 injuries. Most of the dead were in the last carriage, occupied by the livestock escorts.',
                'de' => 'Bei dem Unfall starben 16 Menschen, 6 wurden verletzt. Die meisten Toten befanden sich im letzten Wagen, der von den Viehbegleitern besetzt war.',
                'sources' => ['wp-liste-1850', 'larousse-xixe'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'L\'accident résulte du ralentissement d\'un train lent dans le brouillard, sur une rampe, et de l\'arrivée d\'un express qui n\'a pas pu l\'éviter. Le tribunal reproche au chef du train tamponné de ne pas avoir couvert son train en détresse conformément aux règlements.',
                'en' => 'The accident resulted from a slow train being held up in fog on a gradient and an express arriving that could not avoid it. The court blamed the head of the struck train for not protecting his train in distress as the regulations required.',
                'de' => 'Der Unfall ging darauf zurück, dass ein langsamer Zug im Nebel auf einer Steigung aufgehalten wurde und ein Schnellzug herankam, der nicht mehr ausweichen konnte. Das Gericht warf dem Zugführer des aufgefahrenen Zuges vor, seinen in Not geratenen Zug nicht vorschriftsmäßig gedeckt zu haben.',
                'sources' => ['wp-liste-1850', 'larousse-xixe'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le 26 novembre 1855, le tribunal correctionnel de Fontainebleau condamne le chef du train tamponné à deux ans de prison et 500 francs d\'amende.',
                'en' => 'On 26 November 1855 the Fontainebleau criminal court sentenced the head of the struck train to two years in prison and a 500-franc fine.',
                'de' => 'Am 26. November 1855 verurteilte das Strafgericht Fontainebleau den Zugführer des aufgefahrenen Zuges zu zwei Jahren Haft und 500 Francs Geldstrafe.',
                'sources' => ['wp-liste-1850'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'kind',
            'value' => 'collision',
            'sources' => ['wp-liste-1850', 'larousse-xixe'],
            'fr' => 'Collision par l\'arrière entre un express et un train de bestiaux ralenti, et non un simple incident d\'exploitation.',
            'en' => 'Rear-end collision between an express and a slowed cattle train, not a mere operating incident.',
            'de' => 'Auffahrunfall zwischen einem Schnellzug und einem verlangsamten Viehzug, kein bloßer Betriebsvorfall.',
        ],
    ],
];
