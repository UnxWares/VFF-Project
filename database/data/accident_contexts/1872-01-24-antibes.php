<?php

// Contexte sourcé de l'effondrement du pont de la Brague, près d'Antibes, le 24 janvier 1872 (recherche du 21/11/2026).
return [
    'slug' => '1872-01-24-antibes',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1870' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1870',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1872, 24 janvier',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1870&oldid=235357691#1872',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche, d\'après Le Petit Journal du 28 janvier 1872.',
        ],
        'vdr-brague' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1872, Le pont de Brague s\'écroule suite à des pluies diluviennes',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => '2025-11-27',
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1872-le-pont-de-brague-secroule-suite-a-des-pluies-diluviennes/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit le Journal de Villefranche du 27 janvier et Le Petit Journal du 28 janvier 1872.',
        ],
        'petit-journal-1872-01-28' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Journal du 28 janvier 1872',
            'publisher' => 'Le Petit Journal',
            'author' => null,
            'published' => '1872-01-28',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k5913550/f3.item',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'En janvier 1872, la Brague, torrent d\'ordinaire très modeste, avait été grossie par la fonte des neiges et inondait la plaine près d\'Antibes. Le pont du kilomètre 206 de la ligne de Marseille à Vintimille, quatre arches sur 14 m bâties sur du gravier, était miné par le courant. L\'express venait d\'y passer.',
                'en' => 'In January 1872, the Brague, usually a very small stream, had been swollen by melting snow and was flooding the plain near Antibes. The bridge at kilometre 206 of the Marseille to Ventimiglia line, four arches over 14 m built on gravel, was being undermined by the current. The express had just crossed it.',
                'de' => 'Im Januar 1872 war die Brague, sonst ein sehr kleiner Wildbach, durch die Schneeschmelze angeschwollen und überflutete die Ebene bei Antibes. Die Brücke bei Kilometer 206 der Strecke Marseille-Ventimiglia, vier Bögen auf 14 m Länge auf Kies gegründet, wurde von der Strömung unterspült. Der Schnellzug hatte sie gerade passiert.',
                'sources' => ['vdr-brague', 'wp-liste-1870'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Prévenu de la destruction du pont, le chef de gare d\'Antibes s\'est porté sur place avec son personnel, mais une plaine inondée de quatre kilomètres le séparait du train 492 venant de Menton, et ses signaux sont restés vains. Le soir du 24 janvier, le train est arrivé à pleine vitesse et la locomotive et dix de ses quatorze voitures sont tombées dans le torrent.',
                'en' => 'Warned that the bridge had gone, the Antibes stationmaster went to the site with his staff, but four kilometres of flooded plain separated him from train 492 coming from Menton, and his signals were in vain. On the evening of 24 January the train arrived at full speed, and the locomotive and ten of its fourteen coaches fell into the torrent.',
                'de' => 'Der Bahnhofsvorsteher von Antibes, der von der Zerstörung der Brücke erfahren hatte, eilte mit seinem Personal zur Stelle, doch vier Kilometer überflutete Ebene trennten ihn von Zug 492 aus Menton, und seine Signale blieben wirkungslos. Am Abend des 24. Januar kam der Zug mit voller Geschwindigkeit an, und die Lokomotive sowie zehn seiner vierzehn Wagen stürzten in den Wildbach.',
                'sources' => ['vdr-brague', 'wp-liste-1870'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le train transportait une trentaine de voyageurs. Six morts étaient connus le lendemain, dont le mécanicien et le chauffeur ; le conducteur du train et dix voyageurs étaient blessés. Le Petit Journal signalait encore neuf personnes non retrouvées, sans doute emportées par le torrent.',
                'en' => 'The train carried about thirty passengers. Six dead were known the next day, including the driver and the fireman; the guard and ten passengers were injured. Le Petit Journal still reported nine people missing, probably swept away by the torrent.',
                'de' => 'Der Zug beförderte etwa dreißig Reisende. Am nächsten Tag waren sechs Tote bekannt, darunter Lokführer und Heizer; der Zugführer und zehn Reisende waren verletzt. Le Petit Journal meldete noch neun Vermisste, die vermutlich vom Wildbach fortgerissen worden waren.',
                'sources' => ['vdr-brague'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Selon Le Petit Journal, l\'inondation avait déjà ébranlé les talus et les culées, sans toucher la voie, et le passage de l\'express a pu donner au pont une dernière secousse ; l\'écroulement a été constaté environ 35 minutes après.',
                'en' => 'According to Le Petit Journal, the flood had already weakened the embankments and abutments without reaching the track, and the passing express may have given the bridge a final jolt; the collapse was noticed about 35 minutes later.',
                'de' => 'Laut Le Petit Journal hatte das Hochwasser die Böschungen und Widerlager bereits geschwächt, ohne das Gleis zu erreichen, und die Durchfahrt des Schnellzugs könnte der Brücke einen letzten Stoß versetzt haben; der Einsturz wurde etwa 35 Minuten später bemerkt.',
                'sources' => ['vdr-brague'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Wikipédia parle d\'un omnibus de Menton à Grasse, le Journal de Villefranche d\'un train de banlieue entre Menton et Cannes. La fiche retient six morts, nombre connu le lendemain, alors que neuf personnes manquaient encore.',
                'en' => 'Wikipedia speaks of a stopping train from Menton to Grasse, the Journal de Villefranche of a local train between Menton and Cannes. The record keeps six dead, the figure known the next day, while nine people were still missing.',
                'de' => 'Wikipedia spricht von einem Personenzug von Menton nach Grasse, das Journal de Villefranche von einem Vorortzug zwischen Menton und Cannes. Der Eintrag nennt sechs Tote, die Zahl vom nächsten Tag, während noch neun Menschen vermisst wurden.',
                'sources' => ['wp-liste-1870', 'vdr-brague'],
            ],
        ],
    ],
    'corrections' => [],
];
