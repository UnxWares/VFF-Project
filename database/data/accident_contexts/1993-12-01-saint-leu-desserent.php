<?php

// Contexte sourcé de l'accident de Saint-Leu-d'Esserent du 1er décembre 1993 (recherche du 21/11/2026).
return [
    'slug' => '1993-12-01-saint-leu-desserent',
    'checked_on' => '2026-11-21',
    'sources' => [
        'cpi-saint-leu' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => 'Accident de train : collision meurtrière entre deux trains, 1er décembre 1993',
            'publisher' => 'Site local de Saint-Leu-d\'Esserent (copie de l\'Internet Archive du 19 mars 2022)',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'http://web.archive.org/web/20220319034013/http://cpi.saint.leu.free.fr/train.htm',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Page locale qui reproduit un article de presse (source indiquée : Le Parisien) : description des lieux, des deux trains, de la pièce métallique et des propos du président de la SNCF.',
        ],
        'cheminots-net-1993' => [
            'provider' => 'sites-web',
            'kind' => 'forum',
            'reliability' => 'forum',
            'title' => '1er décembre 1993. Saint-Leu-d\'Esserent dans l\'Oise. Déraillement suivi d\'une collision',
            'publisher' => 'cheminots.net (forum)',
            'author' => null,
            'published' => '2009-12-04',
            'locator' => null,
            'url' => 'http://www.cheminots.net/forum/topic/21942-1er-d%C3%A9cembre-1993/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Message de forum non signé : cause, matériel impliqué, suites (clôtures) ; fiabilité limitée.',
        ],
        'wp-liste-1990' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1990',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section 1993',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1990&oldid=239974394#1993',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : matériel, vitesse, bilan et suites ; renvoie à des pages locales et à L\'Humanité.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 1er décembre 1993, l\'autorail Persan-Beaumont - Creil, une composition de deux éléments de 425 chevaux, roulait à un peu plus de 70 km/h quand il a déraillé à 400 mètres au sud de la gare de Saint-Leu-d\'Esserent, dans l\'Oise, en engageant le gabarit de la voie 2. Il transportait environ une centaine de passagers, surtout des lycéens se rendant à Creil, et le brouillard couvrait les lieux.',
                'en' => 'On 1 December 1993, the Persan-Beaumont to Creil railcar, a unit of two 425 hp elements, was running at a little over 70 km/h when it derailed 400 metres south of Saint-Leu-d\'Esserent station, in the Oise, fouling the clearance of track 2. It carried about a hundred passengers, mostly pupils on their way to school in Creil, and fog covered the area.',
                'de' => 'Am 1. Dezember 1993 entgleiste der Triebwagen Persan-Beaumont - Creil, eine Einheit aus zwei Fahrzeugen mit je 425 PS, der mit etwas über 70 km/h fuhr, 400 Meter südlich des Bahnhofs Saint-Leu-d\'Esserent im Département Oise und ragte dabei in das Profil von Gleis 2. Er beförderte etwa hundert Fahrgäste, vor allem Schüler auf dem Weg nach Creil, und es herrschte Nebel.',
                'sources' => ['cpi-saint-leu', 'wp-liste-1990'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Une rame de banlieue de trois éléments, annoncée vide, arrivait en sens inverse sur la voie 2 à environ 100 km/h. Elle a heurté l\'autorail déraillé et lui a arraché tout le côté droit de la première voiture.',
                'en' => 'A three-unit suburban train, reported as empty, was arriving in the opposite direction on track 2 at about 100 km/h. It struck the derailed railcar and tore away the whole right side of its first coach.',
                'de' => 'Ein als leer gemeldeter Vorortzug aus drei Einheiten kam mit etwa 100 km/h aus der Gegenrichtung auf Gleis 2. Er prallte gegen den entgleisten Triebwagen und riss die gesamte rechte Seite von dessen erstem Wagen auf.',
                'sources' => ['cpi-saint-leu', 'wp-liste-1990'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'L\'accident a fait quatre morts : le conducteur de l\'autorail et trois lycéens, dont un est décédé à l\'hôpital. Plusieurs personnes ont été grièvement blessées ; le conducteur de l\'autre train n\'a été que légèrement blessé.',
                'en' => 'Four people died: the railcar driver and three pupils, one of whom died in hospital. Several people were seriously injured; the driver of the other train was only slightly hurt.',
                'de' => 'Vier Menschen starben: der Fahrer des Triebwagens und drei Schüler, von denen einer im Krankenhaus starb. Mehrere Personen wurden schwer verletzt; der Fahrer des anderen Zuges wurde nur leicht verletzt.',
                'sources' => ['cpi-saint-leu', 'wp-liste-1990'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'L\'autorail a buté sur une plaque d\'appareil de dilatation de 33 kg posée sur la voie. La SNCF a évoqué un acte de malveillance, la pièce ne pouvant pas s\'y trouver par hasard puisqu\'un train y était passé sans encombre une demi-heure plus tôt. Selon la liste de Wikipédia et le forum, un adolescent l\'avait posée « pour voir ce que ça ferait ».',
                'en' => 'The railcar hit a 33 kg expansion-joint plate lying on the track. SNCF spoke of malicious intent, as the piece could not have been there by chance since a train had passed the spot without incident half an hour earlier. According to the Wikipedia list and the forum, a teenager had placed it there "to see what it would do".',
                'de' => 'Der Triebwagen stieß auf eine 33 kg schwere Dehnungsplatte, die auf dem Gleis lag. Die SNCF sprach von Böswilligkeit, da das Teil nicht zufällig dort gelegen haben könne: Eine halbe Stunde zuvor sei ein Zug unbehelligt vorbeigefahren. Nach der Wikipedia-Liste und dem Forum hatte ein Jugendlicher sie dort abgelegt, „um zu sehen, was passiert“.',
                'sources' => ['cpi-saint-leu', 'wp-liste-1990', 'cheminots-net-1993'],
            ],
            [
                'fr' => 'La rame de banlieue, normalement vide, transportait ce jour-là quelques voyageurs jusqu\'à Persan-Beaumont à la suite d\'une rupture de correspondance à Creil, selon le message du forum.',
                'en' => 'The suburban train, normally empty, was carrying a few passengers that day to Persan-Beaumont after a missed connection at Creil, according to the forum post.',
                'de' => 'Der Vorortzug, der sonst leer fährt, beförderte an diesem Tag laut dem Forumsbeitrag nach einem verpassten Anschluss in Creil einige Fahrgäste nach Persan-Beaumont.',
                'sources' => ['cheminots-net-1993'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'À la suite de l\'accident, la SNCF a décidé de clôturer les abords des voies en Île-de-France ; sur la ligne concernée, cela n\'a été fait qu\'une dizaine d\'années plus tard.',
                'en' => 'After the accident, SNCF decided to fence the sides of the tracks in the Île-de-France region; on the line concerned this was done only about ten years later.',
                'de' => 'Nach dem Unfall beschloss die SNCF, die Bahnstrecken in der Île-de-France einzuzäunen; auf der betroffenen Strecke geschah dies erst etwa zehn Jahre später.',
                'sources' => ['wp-liste-1990', 'cheminots-net-1993'],
            ],
        ],
    ],
    'corrections' => [],
];
