<?php

/**
 * Corrections sourcées d'événements de lignes relevées par le contrôle de l'invariant stations.opened_before_lines
 * (chantier « invariants-gares-nuit », 23/11/2026, rapport storage/app/reports/agents/aprem-2111/invariants-gares-nuit.md) :
 * sections dont l'ouverture manque aux événements de la ligne alors que l'article de la ligne ou celui de la gare la date
 * au jour (la gare porte la bonne date, la ligne était incomplète), et dates de section d'OpenHistoricalMap contredites
 * par l'article de la ligne. Format : voir line_event_corrections.php.
 *
 * Pas de tiret long dans les textes.
 *
 * @return list<array<string, mixed>>
 */
$wp = fn (string $title, int $oldid) => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode(str_replace(' ', '_', $title)).'&oldid='.$oldid;

return [
    // 002000 Gretz-Armainvilliers - Sézanne : la section Gretz-Armainvilliers - Mortcerf (2 février 1861) manquait, seule
    // l'année 1861 (Wikidata, ligne entière) figurait ; Mortcerf n'avait que la section de 1863 comme repère.
    [
        'line' => '002000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1861-02-02',
        'precision' => 'day',
        'from' => 'Gretz-Armainvilliers',
        'to' => 'Mortcerf',
        'source_url' => $wp('Ligne de Gretz-Armainvilliers à Sézanne', 240817135),
        'quote' => 'La Compagnie ouvre la ligne de Gretz-Armainvilliers à Mortcerf (16 km) le 2 février 1861.',
        'reason' => 'section Gretz-Armainvilliers - Mortcerf mise en service le 2 février 1861 (article de la ligne, d\'après Palau ; article de la gare de Mortcerf), avant Mortcerf - Coulommiers du 2 avril 1863',
    ],

    // 020000 Blesme-Haussignémont - Chaumont : la section Saint-Dizier - Donjeux (17 juillet 1855) manquait.
    [
        'line' => '020000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1855-07-17',
        'precision' => 'day',
        'from' => 'Saint-Dizier',
        'to' => 'Donjeux',
        'source_url' => $wp('Ligne de Blesme-Haussignémont à Chaumont', 235341775),
        'quote' => 'la Compagnie des chemins de fer de l\'Est a ouvert à la circulation la section de Saint-Dizier à Donjeux, le 17 juillet 1855.',
        'reason' => 'section Saint-Dizier - Donjeux ouverte le 17 juillet 1855 (article de la ligne et article de la gare de Donjeux), avant Donjeux - Chaumont du 25 avril 1857',
    ],

    // 905000 Lyon - Grenoble par les Alpes : Lyon - Bourgoin ouvre le 1er juillet 1858 (article de la ligne et articles de
    // huit gares de la section : Vénissieux, Saint-Priest, Heyrieux, Saint-Quentin, La Verpillière, Chandieu - Toussieu,
    // Vaulx-Milieu, Bourgoin). Le 12 juillet 1858 d'OpenHistoricalMap n'a pas de source.
    [
        'line' => '905000',
        'action' => 'neutralize',
        'match' => ['source' => 'ohm', 'type' => 'opening', 'date' => '1858-07-12', 'from' => 'Port-Édouard-Herriot'],
        'label' => 'Date d\'OpenHistoricalMap de Lyon - Bourgoin (le 12 juillet), contredite par l\'article de la ligne (1er juillet 1858)',
        'source_url' => $wp('Ligne de Lyon-Perrache P1 à Marseille-Saint-Charles (via Grenoble)', 241143131),
        'quote' => 'De Lyon à Bourgoin le 1 juillet 1858',
        'reason' => 'Lyon - Bourgoin ouvre le 1er juillet 1858 : l\'article de la ligne et huit articles de gares de la section s\'accordent',
    ],
    [
        'line' => '905000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1858-07-01',
        'precision' => 'day',
        'from' => 'Port-Édouard-Herriot',
        'to' => 'Bourgoin-Jallieu',
        'source_url' => $wp('Ligne de Lyon-Perrache P1 à Marseille-Saint-Charles (via Grenoble)', 241143131),
        'quote' => 'De Lyon à Bourgoin le 1 juillet 1858',
        'reason' => 'première section de la ligne de Lyon à Grenoble (Compagnie des chemins de fer du Dauphiné) : « La station est mise en service le 1 juillet 1858 [...] lorsqu\'elle ouvre à l\'exploitation la première section de Lyon à Bourgoin » (article de la gare de Vénissieux)',
    ],

    // 180000 Metz-Ville - Zoufftgen : Thionville - frontière du Luxembourg ouvre le 11 août 1859 (article de la ligne, d'après
    // Palau ; article de la gare d'Hettange-Grande) ; le 5 octobre 1859 d'OpenHistoricalMap n'a pas de source.
    [
        'line' => '180000',
        'action' => 'neutralize',
        'match' => ['source' => 'ohm', 'type' => 'opening', 'date' => '1859-10-05'],
        'label' => 'Date d\'OpenHistoricalMap de Thionville - frontière du Luxembourg (le 5 octobre), contredite par l\'article de la ligne (11 août 1859)',
        'source_url' => $wp('Ligne de Metz-Ville à Zoufftgen', 241549739),
        'quote' => 'Thionville-Frontière du Luxembourg : 11 août 1859',
        'reason' => 'le prolongement de Thionville à la frontière ouvre le 11 août 1859 (Palau, cité par l\'article de la ligne ; article de la gare d\'Hettange-Grande)',
    ],
    [
        'line' => '180000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1859-08-11',
        'precision' => 'day',
        'from' => 'Thionville',
        'to' => 'Zoufftgen',
        'source_url' => $wp('Gare de Hettange-Grande', 241364340),
        'quote' => 'La station de Hettange est mise en service le 11 août 1859 par la Compagnie des chemins de fer de l\'Est, lorsqu\'elle ouvre à l\'exploitation le prolongement, de Thionville à la frontière du Luxembourg',
        'reason' => 'prolongement de Thionville à la frontière du Luxembourg ouvert le 11 août 1859',
    ],

    // 584000 Ravezies - Pointe-de-Grave : Soulac - Le Verdon (14 août 1875) manquait, seul Le Verdon - Pointe-de-Grave (1902) figurait.
    [
        'line' => '584000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1875-08-14',
        'precision' => 'day',
        'from' => 'Soulac',
        'to' => 'Le Verdon',
        'source_url' => $wp('Gare du Verdon', 231923740),
        'quote' => 'La Compagnie du chemin de fer du Médoc met en service la gare du Verdon le 14 août 1875 lors de l\'ouverture de la section entre la gare de Soulac et Le Verdon, de son chemin de fer de Bordeaux au Verdon.',
        'reason' => 'section Soulac - Le Verdon ouverte le 14 août 1875 (article de la gare ; l\'article de la ligne dit 1875 pour Soulac - Le Verdon et 1902 pour Le Verdon - Pointe-de-Grave)',
    ],

    // 722000 Béziers - Neussargues : La Tour-sur-Orb - Le Bousquet-d'Orb (11 mai 1872) manquait.
    [
        'line' => '722000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1872-05-11',
        'precision' => 'day',
        'from' => 'La Tour-sur-Orb',
        'to' => 'Le Bousquet-d\'Orb',
        'source_url' => $wp('Ligne de Béziers à Neussargues', 240391025),
        'quote' => '11 mai 1872, de La Tour-sur-Orb au Bousquet d\'Orb ;',
        'reason' => 'première section ouverte par la Compagnie du Midi, prolongeant l\'ancienne ligne minière (article de la gare du Bousquet-d\'Orb : « met en service, le 11 mai 1872, la gare avec l\'ouverture du premier tronçon »)',
    ],

    // 640000 Bordeaux - Sète : Béziers - Sète (19 janvier 1857) manquait, seule l'ouverture de Toulouse à Sète (22 avril 1857) figurait.
    [
        'line' => '640000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1857-01-19',
        'precision' => 'day',
        'from' => 'Béziers',
        'to' => 'Sète',
        'source_url' => $wp('Gare de Marseillan-Plage', 241267734),
        'quote' => 'met en service la station des Onglous avec la section de Béziers à Sète le 19 janvier 1857, elle inaugure la ligne ouverte dans son intégralité le 22 avril 1857.',
        'reason' => 'section Béziers - Sète mise en service le 19 janvier 1857, la ligne étant inaugurée en totalité le 22 avril 1857',
    ],

    // 884000 Bourg-en-Bresse - Bellegarde : Simandre-sur-Suran - Cize-Bolozon (6 juillet 1876) manquait entre le premier tronçon (10 mars 1876) et La Cluse (29 mars 1877).
    [
        'line' => '884000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1876-07-06',
        'precision' => 'day',
        'from' => 'Simandre-sur-Suran',
        'to' => 'Cize-Bolozon',
        'source_url' => $wp('Gare de Cize-Bolozon', 241399069),
        'quote' => 'La gare est ouverte le 6 juillet 1876 par la Compagnie des Dombes et des chemins de fer du Sud-Est lors de l\'ouverture de la section de Simandre-sur-Suran à Cize - Bolozon.',
        'reason' => 'section Simandre-sur-Suran - Cize-Bolozon ouverte le 6 juillet 1876',
    ],

    // 895000 La Roche-sur-Foron - Saint-Gervais : La Roche-sur-Foron - Cluses ouvre le 1er juin 1890 (article de la gare de Cluses
    // et de la ligne : « Elle a été ouverte à l'exploitation le 1 juin 1890 ») ; Cluses - Saint-Gervais suit en 1898.
    [
        'line' => '895000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1890-06-01',
        'precision' => 'day',
        'from' => 'La Roche-sur-Foron',
        'to' => 'Cluses',
        'source_url' => $wp('Gare de Cluses', 239113351),
        'quote' => 'La gare de Cluses est mise en service le 1 juin 1890 par la Compagnie des chemins de fer de Paris à Lyon et à la Méditerranée (PLM) lorsqu\'elle ouvre à l\'exploitation la section de La Roche-sur-Foron à Cluses.',
        'reason' => 'section La Roche-sur-Foron - Cluses ouverte le 1er juin 1890 ; seule la section Cluses - Saint-Gervais (1898) était datée au jour sur une section résolue',
    ],

    // 326000 Neuville - Cergy-le-Haut : le premier prolongement jusqu'à Cergy-Saint-Christophe (29 septembre 1985) manquait.
    [
        'line' => '326000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1985-09-29',
        'precision' => 'day',
        'from' => 'Cergy-Préfecture',
        'to' => 'Cergy-Saint-Christophe',
        'source_url' => $wp('Gare de Cergy-Saint-Christophe', 236159249),
        'quote' => 'La gare de Cergy-Saint-Christophe est mise en service le 29 septembre 1985 par la Société nationale des chemins de fer français (SNCF), lorsqu\'elle ouvre à l\'exploitation le premier prolongement de la ligne de la bifurcation de Neuville à Cergy-Préfecture.',
        'reason' => 'premier prolongement de Cergy-Préfecture à Cergy-Saint-Christophe, avant celui de Cergy-le-Haut (29 août 1994)',
    ],

    // 810000 Tarascon - Sète : Nîmes - Beaucaire ouvre le 15 juillet 1839 ; le 19 août 1840 est l'ouverture d'Alès à Nîmes
    // (donc de la ligne d'Alès à Beaucaire en totalité) : article de la gare de Jonquières-Saint-Vincent (Bellegarde) et
    // article de la ligne de Saint-Germain-des-Fossés à Nîmes (19 août 1840 : Alès - Nîmes).
    [
        'line' => '810000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '1840-08-19', 'from' => 'Nîmes'],
        'label' => 'Ouverture de la ligne d\'Alès à Beaucaire en totalité (19 août 1840), prise pour la section Nîmes - Beaucaire',
        'source_url' => $wp('Gare de Jonquières-Saint-Vincent', 237294035),
        'quote' => 'mise en service le 15 juillet 1839, lors de la mise en service de la section de Nîmes à Beaucaire de la ligne d\'Alais à Beaucaire ouverte en totalité le 19 août 1840',
        'reason' => 'Nîmes - Beaucaire ouvre le 15 juillet 1839 ; le 19 août 1840 est l\'ouverture d\'Alès à Nîmes (ligne en totalité)',
    ],
    [
        'line' => '810000',
        'action' => 'neutralize',
        'match' => ['source' => 'ohm', 'type' => 'opening', 'date' => '1840-08-19', 'from' => 'Beaucaire'],
        'label' => 'Ouverture de la ligne d\'Alès à Beaucaire en totalité (19 août 1840), prise pour la section Nîmes - Beaucaire',
        'source_url' => $wp('Gare de Jonquières-Saint-Vincent', 237294035),
        'quote' => 'mise en service le 15 juillet 1839, lors de la mise en service de la section de Nîmes à Beaucaire de la ligne d\'Alais à Beaucaire ouverte en totalité le 19 août 1840',
        'reason' => 'Nîmes - Beaucaire ouvre le 15 juillet 1839 ; le 19 août 1840 est l\'ouverture d\'Alès à Nîmes (ligne en totalité)',
    ],
    [
        'line' => '810000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1839-07-15',
        'precision' => 'day',
        'from' => 'Nîmes',
        'to' => 'Beaucaire',
        'source_url' => $wp('Gare de Jonquières-Saint-Vincent', 237294035),
        'quote' => 'La station de Bellegarde est mise en service par la Compagnie des mines de la Grand\'Combe et des chemins de fer du Gard le 15 juillet 1839, lors de la mise en service de la section de Nîmes à Beaucaire',
        'reason' => 'section Nîmes - Beaucaire de la ligne d\'Alais à Beaucaire mise en service le 15 juillet 1839',
    ],

    // 447000 Morlaix - Roscoff : mise en service le 10 juin 1883 (article de la ligne, d'après J.-P. Nennig ; articles de six
    // gares : Roscoff, Saint-Pol-de-Léon, Taulé-Henvic, Henvic, Plouénan). Le 10 août 1883 d'OpenHistoricalMap n'a pas de source.
    [
        'line' => '447000',
        'action' => 'neutralize',
        'match' => ['source' => 'ohm', 'type' => 'opening', 'date' => '1883-08-10'],
        'label' => 'Date d\'OpenHistoricalMap de Morlaix - Roscoff (le 10 août), contredite par l\'article de la ligne (10 juin 1883)',
        'source_url' => $wp('Ligne de Morlaix à Roscoff', 240554161),
        'quote' => 'Le 10 juin 1883, mise en service',
        'reason' => 'la ligne est mise en service le 10 juin 1883 (chronologie de l\'article de la ligne, articles de la gare de Roscoff et de Saint-Pol-de-Léon)',
    ],
    [
        'line' => '447000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1883-06-10',
        'precision' => 'day',
        'from' => 'Morlaix',
        'to' => 'Roscoff',
        'source_url' => $wp('Gare de Roscoff', 235040828),
        'quote' => 'La gare de Roscoff est mise en service le 10 juin 1883, par la compagnie des chemins de fer de l\'Ouest lors de l\'inauguration de la ligne de Morlaix à Roscoff',
        'reason' => 'mise en service de la ligne de Morlaix à Roscoff le 10 juin 1883, par la Compagnie de l\'Ouest',
    ],

    // 684000 Étampes - Beaune-la-Rolande : chute de 22,4 à 10,5 km exploités entre 2020 et 2030 sans événement daté. Le vélorail
    // d'Étampes à Méréville (13 km) est mis en service le 9 juillet 2022 (article de la ligne) ; l'événement « vélorail » existant
    // n'avait que « en activité en 2026 », sans date de début.
    [
        'line' => '684000',
        'action' => 'neutralize',
        'match' => ['source' => 'heritage', 'type' => 'heritage_start', 'date' => '2026-01-01'],
        'label' => 'Vélorail de la Vallée de la Juine, début non daté (2026 = année de la liste), daté du 9 juillet 2022 par l\'article de la ligne',
        'source_url' => $wp('Ligne d\'Étampes à Beaune-la-Rolande', 239193697),
        'quote' => 'Les travaux commencent à l\'automne 2021 pour une mise en service le 9 juillet 2022',
        'reason' => 'le vélorail de la Juine circule sur la ligne d\'Étampes à Méréville depuis le 9 juillet 2022 (événement remplacé par un événement daté)',
    ],
    [
        'line' => '684000',
        'action' => 'add',
        'type' => 'heritage_start',
        'heritage_kind' => 'velorail',
        'date' => '2022-07-09',
        'precision' => 'day',
        'from' => 'Étampes',
        'to' => 'Méréville',
        'source_url' => $wp('Ligne d\'Étampes à Beaune-la-Rolande', 239193697),
        'quote' => 'la mise en place d\'un vélorail circulant sur les 13 km de la ligne d\'Étampes à Méréville [...] Les travaux commencent à l\'automne 2021 pour une mise en service le 9 juillet 2022',
        'reason' => 'début du vélorail d\'Étampes à Méréville (13 km), seul usage de la section depuis la fin des circulations ferroviaires',
    ],

    // 830000 Paris-Lyon - Marseille-Saint-Charles : la section Chalon-sur-Saône - Lyon-Vaise (10 juillet 1854) n'était datée que
    // d'un tronçon de Senozan à Crêches-sur-Saône (OpenHistoricalMap), qui laissait Lyon-Vaise sans événement d'ouverture.
    [
        'line' => '830000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1854-07-10',
        'precision' => 'day',
        'from' => 'Chalon-sur-Saône',
        'to' => 'Lyon-Vaise',
        'source_url' => $wp('Ligne de Paris-Lyon à Marseille-Saint-Charles', 241214704),
        'quote' => 'La section complémentaire, reliant Chalon-sur-Saône à Lyon-Vaise, ouvrit trois ans plus tard, le 10 juillet 1854.',
        'reason' => 'section Chalon-sur-Saône - Lyon-Vaise ouverte le 10 juillet 1854 (article de la ligne et article de la gare de Lyon-Vaise)',
    ],
];
