<?php

// Contexte sourcé de l'accident de La Gorgue du 28 décembre 1941 (recherche du 21/11/2026).
return [
    'slug' => '1941-12-28-la-gorgue',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1940' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1940',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section 1941',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1940&oldid=239153903',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Une ligne sur l\'accident (53 morts, 41 blessés), appuyée sur un article de La Voix du Nord de 2025 non lu.',
        ],
        'blog-patricketsonvelo' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => 'Souvenirs des accidents de train SNCF à La Gorgue (1941 - 1977)',
            'publisher' => 'Blog patricketsonvelo (Overblog)',
            'author' => null,
            'published' => '2017-03',
            'locator' => null,
            'url' => 'http://patricketsonvelo.over-blog.com/2017/03/la-catastrophe-sncf-a-la-gorgue-1941-et-1977.html',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Blog d\'histoire locale sans sources nommées pour 1941 ; faits utilisés avec prudence, tous droits réservés.',
        ],
        'voix-du-nord-2025' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Il y a 84 ans, le 28 décembre, une catastrophe ferroviaire tuait 53 personnes sur la ligne La Gorgue - Armentières',
            'publisher' => 'La Voix du Nord',
            'author' => 'H. L.',
            'published' => '2025-12',
            'locator' => null,
            'url' => 'https://www.lavoixdunord.fr/',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Article cité par Wikipédia, adresse exacte non retrouvée ; à consulter.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le dimanche 28 décembre 1941, pendant l\'Occupation, deux trains de voyageurs entrent en collision sur la ligne à voie unique d\'Armentières à Berguette, près du passage à niveau du Grand-Chemin, à La Gorgue (Nord).',
                'en' => 'On Sunday 28 December 1941, during the Occupation, two passenger trains collided on the single-track Armentières to Berguette line, near the Grand-Chemin level crossing at La Gorgue (Nord).',
                'de' => 'Am Sonntag, dem 28. Dezember 1941, während der Besatzungszeit, stießen auf der eingleisigen Strecke von Armentières nach Berguette nahe dem Bahnübergang Grand-Chemin in La Gorgue (Nord) zwei Reisezüge zusammen.',
                'sources' => ['wp-liste-1940', 'blog-patricketsonvelo'],
            ],
            [
                'fr' => 'Selon un blog d\'histoire locale, la collision frontale a lieu vers 7 h 45 au lieu-dit Les Planquettes. L\'un des trains venait d\'Armentières et s\'était arrêté à Laventie pour détacher un wagon à bestiaux ; l\'autre venait de La Gorgue.',
                'en' => 'According to a local-history blog, the head-on collision took place at about 7.45 am at the place called Les Planquettes. One train had come from Armentières and had stopped at Laventie to detach a cattle wagon; the other came from La Gorgue.',
                'de' => 'Laut einem lokalgeschichtlichen Blog ereignete sich der Frontalzusammenstoß gegen 7.45 Uhr an der Stelle Les Planquettes. Der eine Zug kam aus Armentières und hatte in Laventie gehalten, um einen Viehwagen abzukuppeln; der andere kam aus La Gorgue.',
                'sources' => ['blog-patricketsonvelo'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Les secours, français et allemands, travaillent toute la journée. Les pompiers d\'Armentières utilisent une grande échelle parce qu\'une voiture s\'est dressée à la verticale, avec des corps à environ six mètres de hauteur ; une dizaine de personnes sont retrouvées mortes de froid le lendemain.',
                'en' => 'French and German rescuers worked all day. The Armentières firefighters used a tall ladder because one carriage had ended up upright, with bodies about six metres up; about ten people were found dead from cold the next day.',
                'de' => 'Französische und deutsche Helfer arbeiteten den ganzen Tag. Die Feuerwehr von Armentières setzte eine hohe Leiter ein, weil sich ein Wagen aufgerichtet hatte und Leichen etwa sechs Meter hoch lagen; am folgenden Tag wurden etwa zehn Menschen erfroren aufgefunden.',
                'sources' => ['blog-patricketsonvelo'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Wikipédia retient 53 morts et 41 blessés. Le blog local évoque 47 morts et 63 blessés dégagés dans un premier temps.',
                'en' => 'Wikipedia gives 53 dead and 41 injured. The local blog mentions 47 dead and 63 injured recovered at first.',
                'de' => 'Wikipedia nennt 53 Tote und 41 Verletzte. Der lokale Blog erwähnt zunächst 47 geborgene Tote und 63 Verletzte.',
                'sources' => ['wp-liste-1940', 'blog-patricketsonvelo'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Le blog avance que le mécanicien de l\'un des trains aurait mal interprété un signal et se serait cru autorisé à engager la voie unique. Cette explication est présentée avec réserve (« semble ») et n\'est appuyée par aucune source nommée.',
                'en' => 'The blog suggests that the driver of one train may have misread a signal and believed he could enter the single track. The explanation is given with reservation ("seems") and no named source supports it.',
                'de' => 'Der Blog nimmt an, der Lokführer eines Zuges habe ein Signal falsch gedeutet und sich zur Einfahrt in den eingleisigen Abschnitt berechtigt geglaubt. Die Erklärung wird mit Vorbehalt ("scheint") vorgetragen und durch keine benannte Quelle gestützt.',
                'sources' => ['blog-patricketsonvelo'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le bilan diffère : 53 morts et 41 blessés pour Wikipédia (d\'après La Voix du Nord) ; 47 morts et 63 blessés au premier bilan, 67 morts au 1er janvier 1942 et un bilan final inconnu, entre 70 et plus de 100 selon les estimations, pour le blog local.',
                'en' => 'The toll differs: 53 dead and 41 injured for Wikipedia (from La Voix du Nord); 47 dead and 63 injured in the first count, 67 dead by 1 January 1942 and an unknown final toll, estimated between 70 and over 100, for the local blog.',
                'de' => 'Die Bilanz weicht ab: 53 Tote und 41 Verletzte laut Wikipedia (nach La Voix du Nord); laut dem lokalen Blog 47 Tote und 63 Verletzte in der ersten Zählung, 67 Tote am 1. Januar 1942 und eine unbekannte Endbilanz, geschätzt zwischen 70 und über 100.',
                'sources' => ['wp-liste-1940', 'blog-patricketsonvelo'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'kind',
            'value' => 'collision',
            'sources' => ['wp-liste-1940', 'blog-patricketsonvelo'],
            'fr' => 'Les sources décrivent une collision frontale entre deux trains de voyageurs sur la voie unique, près d\'un passage à niveau, et non un accident de passage à niveau.',
            'en' => 'The sources describe a head-on collision between two passenger trains on the single track near a level crossing, not a level crossing accident.',
            'de' => 'Die Quellen beschreiben einen Frontalzusammenstoß zweier Personenzüge auf der eingleisigen Strecke nahe einem Bahnübergang und keinen Bahnübergangsunfall.',
        ],
    ],
];
