<?php

// Contexte sourcé de la collision de Thumeries du 17 février 1948 (recherche du 21/11/2026).
return [
    'slug' => '1948-02-17-thumeries',
    'checked_on' => '2026-11-21',
    'sources' => [
        'amis-pp-temoignage' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'association',
            'title' => 'La catastrophe de Thumeries du 17/02/1948 (témoignage d\'un chef-cantonnier)',
            'publisher' => 'Association Les Amis du PP',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'http://lesamisdupp.online.fr/recit%20marquis.htm',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Témoignage du premier agent intervenu, avec des précisions de l\'association (charge du train de marchandises, point kilométrique, nature de la courbe) ; fiabilité limitée à un souvenir.',
        ],
        'lvn-2004-02-14' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Mémoire : mardi 17, les Amis du PP organiseront une cérémonie à Thumeries (édition du 14 février 2004)',
            'publisher' => 'La Voix du Nord (reproduit sur le site des Amis du PP)',
            'author' => null,
            'published' => '2004-02-14',
            'locator' => null,
            'url' => 'http://lesamisdupp.free.fr/presse/lvn%2020040214%20commemoration.htm',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Article de presse régionale qui reconstitue le déroulement, le signal de départ donné trop tôt et le bilan de 24 morts.',
        ],
        'observateur-douaisis-2023' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Orchésis : 75 ans après la catastrophe, les gares du PP ornées en mémoire des Béghinettes',
            'publisher' => 'L\'Observateur du Douaisis',
            'author' => null,
            'published' => '2023-10-01',
            'locator' => null,
            'url' => 'https://www.lobservateur.fr/orchesis-75-ans-apres-la-catastrophe-les-gares-du-pp-ornees-en-memoire-des-beghinettes/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Bilan de 24 morts et plus de 80 blessés, commémoration du 75e anniversaire et panneaux de mémoire.',
        ],
        'wp-liste-1940' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1940',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section 1948',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1940&oldid=239153903#1948',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Heure (17 h 13), ligne de Pont-à-Marcq à Douai, 22 morts et 80 blessés ; cite une page numérisée de la BCU Lausanne.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'L\'accident s\'est produit sur la ligne à voie unique de 29 km de Pont-de-la-Deûle à Pont-à-Marcq, ouverte en 1896 et surnommée « le PP » ou « le tortillard ». Le mardi 17 février 1948, le train de voyageurs s\'était arrêté à 17 heures, comme chaque jour, en gare de Thumeries, où montaient de nombreuses ouvrières de la sucrerie Béghin, qui employait alors près de 3 000 personnes, surtout de jeunes femmes.',
                'en' => 'The accident took place on the 29 km single-track line from Pont-de-la-Deûle to Pont-à-Marcq, opened in 1896 and nicknamed "le PP" or "le tortillard". On Tuesday 17 February 1948, the passenger train had stopped at 5 p.m., as every day, at Thumeries station, where many women workers from the Béghin sugar factory boarded; the factory then employed nearly 3,000 people, mostly young women.',
                'de' => 'Der Unfall ereignete sich auf der 29 km langen eingleisigen Strecke von Pont-de-la-Deûle nach Pont-à-Marcq, die 1896 eröffnet wurde und «le PP» oder «le tortillard» genannt wurde. Am Dienstag, dem 17. Februar 1948, hielt der Reisezug wie jeden Tag um 17 Uhr im Bahnhof Thumeries, wo zahlreiche Arbeiterinnen der Zuckerfabrik Béghin zustiegen; die Fabrik beschäftigte damals fast 3 000 Personen, vor allem junge Frauen.',
                'sources' => ['lvn-2004-02-14', 'observateur-douaisis-2023'],
            ],
            [
                'fr' => 'Un train de marchandises venant de Pont-de-la-Deûle, qui tractait environ 500 tonnes de charbon pour la sucrerie, arrivait en sens inverse. Chaque jour, le chef de gare ne donnait le départ du train de voyageurs qu\'après le passage de ce convoi.',
                'en' => 'A goods train from Pont-de-la-Deûle, hauling about 500 tonnes of coal for the sugar factory, was arriving from the opposite direction. Every day, the station master gave the passenger train the signal to leave only after this train had passed.',
                'de' => 'Ein Güterzug aus Pont-de-la-Deûle, der etwa 500 Tonnen Kohle für die Zuckerfabrik zog, kam aus der Gegenrichtung. Jeden Tag gab der Bahnhofsvorsteher dem Reisezug das Abfahrtssignal erst nach der Durchfahrt dieses Zuges.',
                'sources' => ['lvn-2004-02-14', 'amis-pp-temoignage'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le 17 février 1948, le chef de gare a donné le signal du départ au train de voyageurs avant le passage du train de marchandises. S\'apercevant de son erreur, il a tenté de téléphoner à Moncheaux pour faire arrêter le train de marchandises et a envoyé un cycliste à la poursuite du train de voyageurs.',
                'en' => 'On 17 February 1948, the station master gave the passenger train the signal to leave before the goods train had passed. Realising his mistake, he tried to telephone Moncheaux to have the goods train stopped and sent a cyclist after the passenger train.',
                'de' => 'Am 17. Februar 1948 gab der Bahnhofsvorsteher dem Reisezug das Abfahrtssignal, bevor der Güterzug durchgefahren war. Als er seinen Fehler bemerkte, versuchte er, in Moncheaux anzurufen, um den Güterzug anhalten zu lassen, und schickte einen Radfahrer hinter dem Reisezug her.',
                'sources' => ['lvn-2004-02-14'],
            ],
            [
                'fr' => 'Peu après 17 h 15, au lieu-dit La Corne du Bois, dans une courbe marquée entre un talus et un bois, les deux trains se sont télescopés frontalement, à environ 500 mètres de la gare de Thumeries (point kilométrique 10,0 contre 10,5 pour la gare).',
                'en' => 'Shortly after 5.15 p.m., at the place called La Corne du Bois, in a pronounced curve between an embankment and a wood, the two trains collided head-on, about 500 metres from Thumeries station (kilometre point 10.0 against 10.5 for the station).',
                'de' => 'Kurz nach 17.15 Uhr prallten die beiden Züge an der Stelle La Corne du Bois in einer ausgeprägten Kurve zwischen einer Böschung und einem Wald frontal aufeinander, etwa 500 Meter vom Bahnhof Thumeries entfernt (Kilometerpunkt 10,0 gegenüber 10,5 beim Bahnhof).',
                'sources' => ['lvn-2004-02-14', 'amis-pp-temoignage'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Dix-huit personnes sont mortes sur le coup, surtout de jeunes filles de moins de vingt ans, et six sont décédées les jours suivants, soit 24 morts ; environ 80 personnes ont été blessées, dont une dizaine gravement. Près des deux tiers des occupants des vieux wagons en bois ont été éjectés lors de l\'éclatement des parois et ont ainsi survécu.',
                'en' => 'Eighteen people died at once, mostly girls under twenty, and six died in the following days, making 24 dead; about 80 people were injured, around ten of them seriously. Nearly two thirds of the occupants of the old wooden coaches were thrown out when the sides burst open and thus survived.',
                'de' => 'Achtzehn Menschen starben sofort, überwiegend Mädchen unter zwanzig Jahren, sechs weitere in den folgenden Tagen, insgesamt also 24 Tote; etwa 80 Personen wurden verletzt, rund zehn davon schwer. Fast zwei Drittel der Insassen der alten Holzwagen wurden beim Aufplatzen der Wände hinausgeschleudert und überlebten dadurch.',
                'sources' => ['lvn-2004-02-14', 'observateur-douaisis-2023'],
            ],
            [
                'fr' => 'Le premier agent arrivé sur place, chef de l\'équipe d\'entretien de la voie, a vu les deux locomotives se dresser l\'une contre l\'autre. Les secours ont dû dégager les voyageurs pris au piège entre les banquettes. Une salle des fêtes de la maison Béghin a accueilli les morts et les familles, et les blessés ont été dirigés vers les hôpitaux voisins.',
                'en' => 'The first employee on the scene, a track maintenance foreman, saw the two locomotives rear up against each other. Rescuers had to free passengers trapped between the seats. A function room belonging to the Béghin company received the dead and their families, and the injured were taken to nearby hospitals.',
                'de' => 'Der erste Bahnangestellte vor Ort, ein Streckenwart-Vorarbeiter, sah, wie sich die beiden Lokomotiven gegeneinander aufbäumten. Die Helfer mussten zwischen den Sitzbänken eingeklemmte Fahrgäste befreien. Ein Festsaal der Firma Béghin nahm die Toten und die Angehörigen auf, die Verletzten wurden in nahe gelegene Krankenhäuser gebracht.',
                'sources' => ['amis-pp-temoignage'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'L\'accident résulte d\'une erreur d\'exploitation : le chef de gare a donné le départ au train de voyageurs trop tôt. Sur cette ligne à voie unique, le mécanicien devait recevoir un « témoin » des mains du mécanicien du train venant en sens inverse ou du chef de gare, preuve que la voie était libre.',
                'en' => 'The accident resulted from an operating error: the station master gave the passenger train the departure signal too early. On this single-track line, the driver had to receive a "token" from the driver of the oncoming train or from the station master, proof that the line was clear.',
                'de' => 'Der Unfall war die Folge eines Betriebsfehlers: Der Bahnhofsvorsteher gab dem Reisezug das Abfahrtssignal zu früh. Auf dieser eingleisigen Strecke musste der Lokführer vom Lokführer des Gegenzugs oder vom Bahnhofsvorsteher einen «Stab» («témoin») erhalten, der bestätigte, dass die Strecke frei war.',
                'sources' => ['lvn-2004-02-14', 'observateur-douaisis-2023'],
            ],
        ],
        'memory' => [
            [
                'fr' => 'L\'association Les Amis du PP entretient la mémoire de la ligne et de l\'accident. En 2004, elle a organisé une cérémonie à Thumeries le 17 février et souhaitait faire ériger une stèle.',
                'en' => 'The association Les Amis du PP keeps alive the memory of the line and of the accident. In 2004 it held a ceremony at Thumeries on 17 February and wished to have a stele erected.',
                'de' => 'Der Verein Les Amis du PP pflegt die Erinnerung an die Strecke und den Unfall. 2004 veranstaltete er am 17. Februar eine Gedenkfeier in Thumeries und wollte eine Stele errichten lassen.',
                'sources' => ['lvn-2004-02-14'],
            ],
            [
                'fr' => 'Pour le 75e anniversaire, en septembre 2023, l\'association a dévoilé une plaque en gare de Mons-en-Pévèle pendant les Journées du patrimoine, et installé devant d\'anciens bâtiments de gare des panneaux avec une carte du parcours et un résumé de la catastrophe, financés avec le soutien de communes, du Département et de la communauté de communes Pévèle Carembault.',
                'en' => 'For the 75th anniversary, in September 2023, the association unveiled a plaque at Mons-en-Pévèle station during the Heritage Days, and installed in front of former station buildings signs with a map of the route and a summary of the disaster, funded with the support of communes, the Département and the Pévèle Carembault community of communes.',
                'de' => 'Zum 75. Jahrestag enthüllte der Verein im September 2023 während der Tage des Denkmals eine Gedenktafel im Bahnhof Mons-en-Pévèle und brachte vor ehemaligen Bahnhofsgebäuden Tafeln mit einer Streckenkarte und einer Zusammenfassung des Unglücks an, finanziert mit Unterstützung von Gemeinden, des Départements und der Gemeindeverbands Pévèle Carembault.',
                'sources' => ['observateur-douaisis-2023'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le bilan varie : 24 morts (La Voix du Nord de 2004, L\'Observateur du Douaisis de 2023), 22 morts (liste de Wikipédia), 18 morts retirés des décombres selon le témoin, ce qui correspond au nombre de morts sur le coup. L\'heure est aussi donnée différemment : 17 h 13 (liste de Wikipédia), peu après 17 h 15 (La Voix du Nord), environ 17 h 30 selon le témoin.',
                'en' => 'The toll varies: 24 dead (La Voix du Nord of 2004, L\'Observateur du Douaisis of 2023), 22 dead (Wikipedia list), 18 dead pulled from the wreckage according to the witness, which matches the number killed at once. The time is also given differently: 5.13 p.m. (Wikipedia list), shortly after 5.15 p.m. (La Voix du Nord), about 5.30 p.m. according to the witness.',
                'de' => 'Die Bilanz schwankt: 24 Tote (La Voix du Nord 2004, L\'Observateur du Douaisis 2023), 22 Tote (Wikipedia-Liste), 18 aus den Trümmern geborgene Tote laut Augenzeuge, was der Zahl der sofort Getöteten entspricht. Auch die Uhrzeit wird unterschiedlich angegeben: 17.13 Uhr (Wikipedia-Liste), kurz nach 17.15 Uhr (La Voix du Nord), etwa 17.30 Uhr laut Augenzeuge.',
                'sources' => ['lvn-2004-02-14', 'observateur-douaisis-2023', 'wp-liste-1940', 'amis-pp-temoignage'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 24,
            'sources' => ['lvn-2004-02-14', 'observateur-douaisis-2023'],
            'fr' => 'Dix-huit morts sur le coup et six décès dans les jours suivants : 24 morts selon la presse régionale de 2004 et de 2023.',
            'en' => 'Eighteen killed at once and six deaths in the following days: 24 dead according to the regional press in 2004 and 2023.',
            'de' => 'Achtzehn sofort Getötete und sechs Todesfälle in den folgenden Tagen: 24 Tote laut regionaler Presse 2004 und 2023.',
        ],
    ],
];
