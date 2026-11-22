<?php

// Contexte sourcé du déraillement de Bourgoin-Jallieu de 1983 (recherche du 21/11/2026).
return [
    'slug' => '1983-05-01-bourgoin-jallieu',
    'checked_on' => '2026-11-22',
    'sources' => [
        'blog-paulo-1983' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => 'Gare de Bourgoin-Jallieu (01/05/1983)',
            'publisher' => 'La Gazette (blog de Paulo)',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://leblogdepaulo.eklablog.com/gare-de-bourgoin-jallieu-01-05-1983-a93341121',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Reportage de photos légendées d\'un ancien cheminot : dimanche 1er mai 1983, ligne non électrifiée, coulée de boue, aiguille, une seule victime tuée.',
        ],
        'lachenal-gare-bourgoin' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => 'Gare de Bourgoin-Jallieu (SNCF)',
            'publisher' => 'Site de Christophe Lachenal',
            'author' => null,
            'published' => null,
            'locator' => 'chronologie, 1983',
            'url' => 'http://christophe.lachenal.free.fr/francais/infos/gare_france/fr_gare_bourgoin_jallieu.htm',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Chronologie de la gare : rame ETG T-1002/1502, train 5708 Grenoble - Lyon-Perrache, coulée de boue, 1 mort et 23 blessés, daté du 1er mars 1983.',
        ],
        'wp-liste-1980' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1980',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section 1983',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1980&oldid=240619561#1983',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'ETG Grenoble - Lyon à 130 km/h, un mort et trente blessés, coulée de boue et souche d\'arbre ; sans référence.',
        ],
        'georisques-catnat-1983' => [
            'provider' => 'sites-web',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Arrêtés de reconnaissance de l\'état de catastrophe naturelle de Bourgoin-Jallieu (base Gaspar)',
            'publisher' => 'Géorisques, ministère de la Transition écologique',
            'author' => null,
            'published' => '1983-06-24',
            'locator' => 'Arrêté NOR19830621',
            'url' => 'https://georisques.gouv.fr/api/v1/gaspar/catnat?code_insee=38053&page_size=100',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'L\'arrêté du 21 juin 1983 (Journal officiel du 24 juin) reconnaît à Bourgoin-Jallieu l\'état de catastrophe naturelle pour inondations, coulées de boue et glissement de terrain survenus du 30 avril au 1er mai 1983 ; aucun arrêté ne vise le début de mars 1983.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'L\'accident concerne une rame ETG (élément à turbine à gaz) qui circulait entre Grenoble et Lyon sur la ligne de Lyon à Grenoble, alors non électrifiée et exploitée en cantonnement manuel. Après une semaine de pluie et de neige, une coulée de boue avait déposé sur la voie une souche d\'arbre, à l\'entrée de la gare de Bourgoin-Jallieu.',
                'en' => 'The accident involved an ETG gas-turbine set running between Grenoble and Lyon on the Lyon to Grenoble line, which was not electrified at the time and was worked under manual block. After a week of rain and snow, a mudslide had left a tree stump on the track at the approach to Bourgoin-Jallieu station.',
                'de' => 'Der Unfall betraf einen ETG-Gasturbinentriebzug auf der Fahrt zwischen Grenoble und Lyon auf der damals nicht elektrifizierten, im manuellen Streckenblock betriebenen Strecke Lyon-Grenoble. Nach einer Woche mit Regen und Schnee hatte ein Erdrutsch einen Baumstumpf auf das Gleis geschoben, am Einfahrtsbereich des Bahnhofs Bourgoin-Jallieu.',
                'sources' => ['blog-paulo-1983', 'wp-liste-1980'],
            ],
            [
                'fr' => 'Le train 5708 Grenoble - Lyon-Perrache était assuré par la rame ETG T-1002/1502. Selon la liste de Wikipédia, il roulait à 130 km/h.',
                'en' => 'Train 5708 from Grenoble to Lyon-Perrache was formed of ETG set T-1002/1502. According to the Wikipedia list, it was running at 130 km/h.',
                'de' => 'Der Zug 5708 Grenoble - Lyon-Perrache wurde mit dem ETG-Triebzug T-1002/1502 gefahren. Laut der Wikipedia-Liste fuhr er mit 130 km/h.',
                'sources' => ['lachenal-gare-bourgoin', 'wp-liste-1980'],
            ],
        ],
        'course' => [
            [
                'fr' => 'À la première aiguille, la tête de la rame a pris l\'ancienne voie 4 (devenue voie C) alors que l\'arrière restait sur la voie 2. La rame s\'est couchée, a fauché les poteaux et le garde-corps d\'un passage souterrain, et sa tête a heurté des poteaux arrachés du quai. La voie 1 n\'était pas occupée.',
                'en' => 'At the first set of points, the head of the train took the former track 4 (now track C) while the rear stayed on track 2. The train overturned, cut down the posts and railing of an underpass, and its head struck posts torn from the platform. Track 1 was clear.',
                'de' => 'An der ersten Weiche nahm der Zugkopf das frühere Gleis 4 (heute Gleis C), während das Zugende auf Gleis 2 blieb. Der Triebzug kippte um, mähte die Pfosten und das Geländer einer Unterführung nieder, und sein Kopf traf vom Bahnsteig gerissene Pfosten. Gleis 1 war nicht belegt.',
                'sources' => ['blog-paulo-1983'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le blog signale un seul mort, à l\'endroit où est tombée la base d\'un poteau en béton. La liste de Wikipédia donne un mort et trente blessés ; le site de Christophe Lachenal donne un mort, un voyageur projeté hors de la rame, et 23 blessés.',
                'en' => 'The blog reports a single person killed, at the spot where the base of a concrete post fell. The Wikipedia list gives one death and thirty injured; the Christophe Lachenal site gives one death, a passenger thrown out of the train, and 23 injured.',
                'de' => 'Der Blog nennt ein einziges Todesopfer, an der Stelle, an der der Sockel eines Betonpfostens niederging. Die Wikipedia-Liste nennt einen Toten und dreißig Verletzte; die Seite von Christophe Lachenal nennt einen Toten, einen aus dem Zug geschleuderten Fahrgast, und 23 Verletzte.',
                'sources' => ['blog-paulo-1983', 'wp-liste-1980', 'lachenal-gare-bourgoin'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Les trois documents attribuent le déraillement à la coulée de boue ; le blog et la liste de Wikipédia citent aussi la souche d\'arbre qu\'elle avait entraînée sur la voie.',
                'en' => 'All three documents attribute the derailment to the mudslide; the blog and the Wikipedia list also mention the tree stump it had carried onto the track.',
                'de' => 'Alle drei Dokumente führen die Entgleisung auf den Erdrutsch zurück; der Blog und die Wikipedia-Liste nennen außerdem den Baumstumpf, den er auf das Gleis getragen hatte.',
                'sources' => ['blog-paulo-1983', 'wp-liste-1980', 'lachenal-gare-bourgoin'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'La rame a été relevée par une entreprise de levage, puis ramenée vers les ateliers derrière une locomotive diesel, à 10 km/h au plus.',
                'en' => 'The set was righted by a lifting contractor, then hauled back towards the workshops by a diesel locomotive at no more than 10 km/h.',
                'de' => 'Der Triebzug wurde von einem Hebeunternehmen aufgerichtet und dann von einer Diesellok mit höchstens 10 km/h in Richtung Werkstatt geschleppt.',
                'sources' => ['blog-paulo-1983'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le site de Christophe Lachenal date l\'accident du 1er mars 1983 et le place peu avant la gare, avec 23 blessés ; le blog et la liste de Wikipédia le datent du 1er mai 1983 et le placent en gare, la liste donnant trente blessés. Le blog date l\'accident du dimanche 1er mai et juge heureux qu\'il ne soit pas survenu un jour de semaine, quand le quai aurait été plein. La date de la fiche est gardée.',
                'en' => 'The Christophe Lachenal site dates the accident to 1 March 1983 and places it shortly before the station, with 23 injured; the blog and the Wikipedia list date it to 1 May 1983 and place it at the station, the list giving thirty injured. The blog dates the accident to Sunday 1 May and considers it fortunate that it did not happen on a weekday, when the platform would have been full. The date on the record is kept.',
                'de' => 'Die Seite von Christophe Lachenal datiert den Unfall auf den 1. März 1983 und verortet ihn kurz vor dem Bahnhof, mit 23 Verletzten; der Blog und die Wikipedia-Liste datieren ihn auf den 1. Mai 1983 und verorten ihn im Bahnhof, wobei die Liste dreißig Verletzte nennt. Der Blog datiert den Unfall auf Sonntag, den 1. Mai, und hält es für ein Glück, dass er nicht an einem Werktag geschah, an dem der Bahnsteig voll gewesen wäre. Das Datum des Eintrags bleibt.',
                'sources' => ['lachenal-gare-bourgoin', 'blog-paulo-1983', 'wp-liste-1980'],
            ],
            [
                'fr' => 'L\'arrêté de catastrophe naturelle du 21 juin 1983 vise à Bourgoin-Jallieu des coulées de boue et un glissement de terrain du 30 avril au 1er mai 1983. Cela concorde avec la date du 1er mai et avec la coulée de boue décrite par le blog et la liste.',
                'en' => 'The natural disaster order of 21 June 1983 covers mudslides and a landslip at Bourgoin-Jallieu from 30 April to 1 May 1983. This agrees with the date of 1 May and with the mudslide described by the blog and the list.',
                'de' => 'Der Erlass über den Naturkatastrophenzustand vom 21. Juni 1983 betrifft Schlammlawinen und einen Erdrutsch in Bourgoin-Jallieu vom 30. April bis 1. Mai 1983. Das stimmt mit dem Datum des 1. Mai und mit der vom Blog und der Liste beschriebenen Schlammlawine überein.',
                'sources' => ['georisques-catnat-1983', 'blog-paulo-1983', 'wp-liste-1980'],
            ],
        ],
    ],
    'corrections' => [],
];
