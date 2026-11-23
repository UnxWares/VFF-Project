<?php

/**
 * Lignes EXPLOITÉES absentes des sources de tracés de lignes (formes SNCF,
 * archeoferroviaire, OHM), déclarées à la main avec leurs sources et rejouées par
 * vff:seed-off-network-lines (première étape de vff:rebuild-history).
 *
 * Champs d'une ligne créée :
 *   - slug (« hors-rfn-… »), name (« Ligne de X à Y »), network (principal | secondaire |
 *     militaire | particulier, cf. lines.network), code (code RFN d'une ancienne ligne SNCF
 *     déclassée), gauge_mm, operator (exploitant actuel),
 *     historical_operator, wikipedia (titre de l'article FR), wikidata (QID), aliases,
 *     description, sources (liens consultés : permaliens, fiches) ;
 *   - ou line (slug d'une ligne EXISTANTE) : seuls trace et events sont alors ajoutés.
 *   - trace : forme déclarée = union des tracés OSM / BD TOPO désignés, dans bbox
 *     [lon min, lat min, lon max, lat max] :
 *       osm      => [layers, name (regex), not_name, operator (regex), service (bool)] ;
 *       bdtopo   => [layers, toponyme (regex)] ;
 *       osm_ways => identifiants de ways ; exclude_osm_ways => ways à écarter.
 *     vff:match-current-network y rattache ensuite les voies BD TOPO et OSM.
 *   - events : [type, date AAAA-MM-JJ, precision (day | month | year), from / to (gares,
 *     omis = toute la ligne), portion (emprise [lon min, lat min, lon max, lat max] quand une
 *     extrémité n'est pas géocodable), heritage_kind (tourist_train | velorail | museum),
 *     source_url (permalien), quote (citation exacte, 300 car. max), reason].
 *     Une ouverture sans portion à la date de la première mise en service couvre les morceaux
 *     qu'aucune section ne couvre (abords des gares) : ils prennent l'ouverture voisine.
 *
 * Catégories : le RER de la RATP, le tunnel sous la Manche et l'antenne CFL sont
 * d'intérêt général (principal) ; un chemin de fer touristique construit pour le
 * tourisme ou né d'une voie industrielle est particulier ; une ligne historique
 * reprise par une association garde la catégorie de sa construction.
 * Un parc d'attractions (Disneyland, Nigloland, la Mer de Sable…) n'est pas une
 * ligne ferroviaire : il n'est pas déclaré ici.
 *
 * Pas de tiret long dans les textes (consigne éditoriale).
 *
 * @return list<array<string, mixed>>
 */
return [
    // ───────────────────────── RER de la RATP ─────────────────────────
    // Partie RATP du RER A construite pour lui (les branches de Saint-Germain-en-Laye et de
    // Boissy-Saint-Léger sont les anciennes lignes 975000 et 956000, déjà dans VFF). Pas de QID
    // ni d'article sur la ligne : Q741818 et l'article décrivent tout le RER A, branches SNCF
    // comprises (leurs dates de Cergy ou Poissy ne concernent pas ce tracé).

    [
        'slug' => 'hors-rfn-rer-a-nanterre-vincennes',
        'name' => 'Ligne de Nanterre-Préfecture à Vincennes (RER A)',
        'network' => 'principal',
        'gauge_mm' => 1435,
        'operator' => 'RATP',
        'historical_operator' => 'RATP',
        'aliases' => ['Tronçon central du RER A'],
        'description' => 'Tronçon central du RER A construit par la RATP : tunnel de La Défense à Nation et raccordement à l\'ancienne ligne de Vincennes à la limite de Saint-Mandé et de Vincennes.',
        'sources' => ['https://fr.wikipedia.org/w/index.php?title=Ligne_A_du_RER_d%27%C3%8Ele-de-France&oldid=240180021', 'https://www.wikidata.org/wiki/Q741818'],
        'trace' => [
            'bbox' => [2.205, 48.835, 2.425, 48.905],
            'osm' => ['layers' => ['rail'], 'operator' => '^RATP', 'name' => '^RER A|^Voie 4$|^Ligne de Paris-Lyon à Marseille'],
        ],
        'events' => [
            // Première mise en service de la ligne : seuls les morceaux qu'aucune section ne couvre
            // (abords des gares, raccords) en dépendent, et prennent l'ouverture de la section voisine.
            ['type' => 'opening', 'date' => '1969-12-14',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Gare_de_Nation&oldid=240298681',
                'quote' => 'La gare, mise en service le 14 décembre 1969, fut le terminus occidental du premier tronçon du RER A, nommé provisoirement à cette époque le Métro régional, qui circulait alors uniquement vers l\'est jusqu\'à Boissy-Saint-Léger.',
                'reason' => 'première mise en service de la ligne'],
            ['type' => 'opening', 'date' => '1969-12-14', 'from' => 'Nation', 'to' => 'Saint-Mandé',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Gare_de_Nation&oldid=240298681',
                'quote' => 'La gare, mise en service le 14 décembre 1969, fut le terminus occidental du premier tronçon du RER A, nommé provisoirement à cette époque le Métro régional, qui circulait alors uniquement vers l\'est jusqu\'à Boissy-Saint-Léger.',
                'reason' => 'tunnel de Nation à l\'ancienne ligne de Vincennes, rejointe à Saint-Mandé, ouvert avec son transfert à la RATP'],
            ['type' => 'opening', 'date' => '1970-01-19', 'from' => 'Charles-de-Gaulle - Étoile', 'to' => 'La Défense',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_A_du_RER_d%27%C3%8Ele-de-France&oldid=240180021',
                'quote' => 'Le 19 janvier 1970, est ouverte la section occidentale, longtemps attendue, entre Étoile, renommée à cette occasion « Charles-de-Gaulle - Étoile », et La Défense.'],
            ['type' => 'opening', 'date' => '1971-11-23', 'from' => 'Charles-de-Gaulle - Étoile', 'to' => 'Auber',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_A_du_RER_d%27%C3%8Ele-de-France&oldid=240180021',
                'quote' => 'Le 23 novembre 1971, la navette Étoile - La Défense est prolongée vers l\'est jusqu\'à la nouvelle gare d\'Auber, alors plus grand complexe souterrain au monde.'],
            ['type' => 'opening', 'date' => '1972-10-01', 'from' => 'La Défense', 'to' => 'Nanterre-Université',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Gare_de_la_D%C3%A9fense&oldid=238099456',
                'quote' => 'Le 1er octobre 1972, la gare est connectée à l\'ancienne ligne de Paris-Saint-Lazare à Saint-Germain-en-Laye, désormais exploitée par la RATP à partir de la gare de Nanterre-Université.',
                'reason' => 'raccordement de La Folie vers l\'ancienne ligne de Saint-Germain ; la gare de Nanterre-Préfecture ouvre sur cette section le 1er octobre 1973'],
            ['type' => 'opening', 'date' => '1977-12-09', 'from' => 'Auber', 'to' => 'Nation',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Gare_de_Ch%C3%A2telet_-_Les_Halles&oldid=240490353',
                'quote' => 'Inaugurée, comme le réseau express régional, le 8 décembre 1977 par le président Valéry Giscard d\'Estaing, la gare est mise en service le 9 décembre en étant desservie par les RER A et B.',
                'reason' => 'jonction Auber - Châtelet - Les Halles - Gare de Lyon - Nation, inaugurée le 8 décembre 1977, ouverte au public le lendemain'],
        ],
    ],
    [
        'slug' => 'hors-rfn-rer-a-fontenay-chessy',
        'name' => 'Ligne de Fontenay-sous-Bois à Marne-la-Vallée - Chessy (RER A)',
        'network' => 'principal',
        'gauge_mm' => 1435,
        'operator' => 'RATP',
        'historical_operator' => 'RATP',
        'aliases' => ['Branche de Marne-la-Vallée du RER A'],
        'description' => 'Branche A4 du RER A, construite pour la ville nouvelle de Marne-la-Vallée : elle quitte l\'ancienne ligne de Vincennes à la bifurcation de Fontenay, entre Vincennes et Fontenay-sous-Bois.',
        'sources' => ['https://fr.wikipedia.org/w/index.php?title=Ligne_A_du_RER_d%27%C3%8Ele-de-France&oldid=240180021', 'https://fr.wikipedia.org/w/index.php?title=Gare_du_Val_de_Fontenay&oldid=240031610'],
        'trace' => [
            'bbox' => [2.44, 48.83, 2.80, 48.88],
            'osm' => ['layers' => ['rail'], 'name' => 'Fontenay-Sous-Bois à Marne'],
        ],
        'events' => [
            // Première mise en service de la branche (cf. tronçon central).
            ['type' => 'opening', 'date' => '1977-12-09',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Gare_de_Noisy-le-Grand-Mont_d%27Est&oldid=237430421',
                'quote' => 'La gare de Noisy-le-Grand-Mont d\'Est fut ouverte le 9 décembre 1977 dans le but d\'accompagner le développement de la ville nouvelle de Marne-la-Vallée',
                'reason' => 'première mise en service de la branche'],
            // Portions déclarées par emprise : la gare de Noisy-le-Grand - Mont d'Est (positions de ses
            // sources à 294 m les unes des autres) n'est pas géocodable par le résolveur.
            ['type' => 'opening', 'date' => '1977-12-09', 'from' => 'Vincennes', 'to' => 'Noisy-le-Grand - Mont d’Est',
                'portion' => [2.44, 48.82, 2.5505, 48.88],
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Gare_de_Noisy-le-Grand-Mont_d%27Est&oldid=237430421',
                'quote' => 'La gare de Noisy-le-Grand-Mont d\'Est fut ouverte le 9 décembre 1977 dans le but d\'accompagner le développement de la ville nouvelle de Marne-la-Vallée',
                'reason' => 'de la bifurcation de Fontenay à Noisy-le-Grand - Mont d\'Est (8 553 m)'],
            ['type' => 'opening', 'date' => '1980-12-19', 'from' => 'Noisy-le-Grand - Mont d’Est', 'to' => 'Torcy',
                'portion' => [2.5505, 48.82, 2.657, 48.88],
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_A_du_RER_d%27%C3%8Ele-de-France&oldid=240180021',
                'quote' => 'Le 19 décembre 1980, la branche de Noisy-le-Grand est prolongée de 8 764 mètres en direction de l\'est jusqu\'à Torcy - Marne-la-Vallée.'],
            ['type' => 'opening', 'date' => '1992-04-01', 'from' => 'Torcy', 'to' => 'Marne-la-Vallée - Chessy',
                'portion' => [2.657, 48.82, 2.80, 48.89],
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_A_du_RER_d%27%C3%8Ele-de-France&oldid=240180021',
                'quote' => 'Le 1er avril 1992, la ligne est prolongée dans Marne-la-Vallée jusqu\'à Marne-la-Vallée - Chessy, onze jours avant l\'inauguration du parc Disneyland.'],
        ],
    ],
    [
        // Le tunnel Châtelet - Gare du Nord (RER B et D sur les mêmes voies) est déjà la ligne 981000.
        'slug' => 'hors-rfn-rer-b-luxembourg-chatelet',
        'name' => 'Ligne de Luxembourg à Châtelet - Les Halles (RER B)',
        'network' => 'principal',
        'gauge_mm' => 1435,
        'operator' => 'RATP',
        'historical_operator' => 'RATP',
        'description' => 'Prolongement souterrain de la ligne de Sceaux de Luxembourg à Châtelet - Les Halles, construit et exploité par la RATP.',
        'sources' => ['https://fr.wikipedia.org/w/index.php?title=Ligne_B_du_RER_d%27%C3%8Ele-de-France&oldid=237656878', 'https://fr.wikipedia.org/w/index.php?title=Gare_de_Ch%C3%A2telet_-_Les_Halles&oldid=240490353'],
        'trace' => [
            'bbox' => [2.338, 48.845, 2.352, 48.862],
            'osm' => ['layers' => ['rail'], 'operator' => '^RATP', 'name' => '^RER B$'],
        ],
        'events' => [
            // Première mise en service de la ligne (morceaux qu'aucune section ne couvre, cf. RER A).
            ['type' => 'opening', 'date' => '1977-12-09',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Gare_de_Ch%C3%A2telet_-_Les_Halles&oldid=240490353',
                'quote' => 'Inaugurée, comme le réseau express régional, le 8 décembre 1977 par le président Valéry Giscard d\'Estaing, la gare est mise en service le 9 décembre en étant desservie par les RER A et B.',
                'reason' => 'première mise en service de la ligne'],
            ['type' => 'opening', 'date' => '1977-12-09', 'from' => 'Luxembourg', 'to' => 'Châtelet - Les Halles',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Gare_de_Ch%C3%A2telet_-_Les_Halles&oldid=240490353',
                'quote' => 'Inaugurée, comme le réseau express régional, le 8 décembre 1977 par le président Valéry Giscard d\'Estaing, la gare est mise en service le 9 décembre en étant desservie par les RER A et B.'],
        ],
    ],

    // ───────────────────────── Tunnel sous la Manche ─────────────────────────
    // Partie française : terminal de Coquelles, puis tunnel jusqu'à la frontière (dans la géométrie
    // OSM, découpée à la frontière). QID Q10257 cité en source seulement : l'article décrit aussi
    // les incendies et interruptions, qui ne sont pas des fermetures.

    [
        'slug' => 'hors-rfn-tunnel-sous-la-manche',
        'name' => 'Ligne de Coquelles à la frontière (tunnel sous la Manche)',
        'network' => 'principal',
        'gauge_mm' => 1435,
        'operator' => 'Getlink',
        'historical_operator' => 'Eurotunnel',
        'aliases' => ['Tunnel sous la Manche', 'Terminal de Coquelles'],
        'description' => 'Terminal de Coquelles et partie française du tunnel sous la Manche, concédés à Eurotunnel (Getlink depuis 2017).',
        'sources' => ['https://fr.wikipedia.org/w/index.php?title=Tunnel_sous_la_Manche&oldid=239953436',
            'https://fr.wikipedia.org/w/index.php?title=Getlink&oldid=240036315', 'https://www.wikidata.org/wiki/Q10257'],
        'trace' => [
            'bbox' => [1.40, 50.88, 1.90, 51.05],
            'osm' => ['layers' => ['rail'], 'operator' => 'get ?link|eurotunnel'],
        ],
        'events' => [
            ['type' => 'opening', 'date' => '1994-06-01',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Tunnel_sous_la_Manche&oldid=239953436',
                'quote' => 'Le tunnel est inauguré le 6 mai 1994 et ouvert au service commercial depuis le 1er juin 1994.',
                'reason' => 'premier train de marchandises le 1er juin 1994, premier Eurostar le 14 novembre 1994'],
        ],
    ],

    // ───────────────────────── CFL (Luxembourg) ─────────────────────────
    // Partie française de la ligne 6b des CFL (Q801958), sans numéro d'infrastructure en France.

    [
        'slug' => 'hors-rfn-cfl-volmerange',
        'name' => 'Ligne de Bettembourg à Volmerange-les-Mines (CFL)',
        'network' => 'principal',
        'gauge_mm' => 1435,
        'operator' => 'CFL',
        'description' => 'Extrémité française de la ligne 6b des Chemins de fer luxembourgeois, prolongée en 2003 jusqu\'à la gare de Volmerange-les-Mines.',
        'sources' => ['https://fr.wikipedia.org/w/index.php?title=Ligne_6b_%28CFL%29&oldid=223187864',
            'https://fr.wikipedia.org/w/index.php?title=Gare_de_Volmerange-les-Mines&oldid=237816371', 'https://www.wikidata.org/wiki/Q801958'],
        'trace' => [
            'bbox' => [6.07, 49.45, 6.09, 49.48],
            'osm_ways' => [598500264, 598500266],
        ],
        'events' => [
            ['type' => 'opening', 'date' => '2003-12-15',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_6b_%28CFL%29&oldid=223187864',
                'quote' => 'comprennent notamment le prolongement de la voie ferrée par un tronçon de 850 mètres électrifié jusqu\'à Volmerange-les-Mines, mais aussi un réaménagement de la gare de Dudelange-Usines. La nouvelle voie est inaugurée le 10 décembre et la mise en service a lieu le 15 décembre 2003.',
                'reason' => 'prolongement de Dudelange-Usines à Volmerange-les-Mines ; Wikidata P1619 concorde'],
        ],
    ],

    // ───────────────────────── Trains touristiques sur voie propre ou sur ligne absente de VFF ─────────────────────────
    // Chemin de fer de la baie de Somme : trois lignes historiques sans tracé archeo / OHM / SNCF.
    // Exploitation touristique (CFBS) : interruption de 1975-1976 non modélisée (entre deux cartes).

    [
        'slug' => 'hors-rfn-cfbs-noyelles-le-crotoy',
        'name' => 'Ligne de Noyelles-sur-Mer au Crotoy',
        'network' => 'secondaire',
        'gauge_mm' => 1000,
        'operator' => 'Chemin de fer de la baie de Somme (CFBS)',
        'historical_operator' => 'Société générale des chemins de fer économiques',
        'wikidata' => 'Q120330462',
        'description' => 'Ligne à voie métrique des Chemins de fer départementaux de la Somme (réseau des Bains de mer), exploitée par le CFBS depuis 1971.',
        'sources' => ['https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_la_baie_de_Somme&oldid=239672428', 'https://fr.wikipedia.org/w/index.php?title=Chemins_de_fer_d%C3%A9partementaux_de_la_Somme&oldid=239886986', 'https://fr.wikipedia.org/w/index.php?title=Gare_du_Crotoy&oldid=213916167'],
        'trace' => [
            'bbox' => [1.625, 50.185, 1.710, 50.225],
            'osm' => ['layers' => ['rail'], 'name' => '^Ligne de Noyelles au Crotoy'],
        ],
        'events' => [
            ['type' => 'opening', 'date' => '1887-07-01',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Gare_du_Crotoy&oldid=213916167',
                'quote' => 'La gare du Crotoy est mise en service le 1er juillet 1887, par la Société générale des chemins de fer économiques (SE) lorsqu\'elle ouvre à l\'exploitation la ligne à écartement métrique de Noyelles au Crotoy du réseau des Chemins de fer départementaux de la Somme.'],
            ['type' => 'closure', 'date' => '1969-12-31',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Gare_du_Crotoy&oldid=213916167',
                'quote' => 'Le 31 décembre 1969, face à la concurrence de la route, malgré des protestations locales pour la sauvegarde de la ligne Noyelles - Le Crotoy, celle-ci a été fermée, ce qui a entraîné la fermeture de la gare.'],
            ['type' => 'heritage_start', 'heritage_kind' => 'tourist_train', 'date' => '1971-07-04',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_la_baie_de_Somme&oldid=239672428',
                'quote' => '4 juillet 1971 : Début des circulations régulières entre la gare de Noyelles-sur-Mer et celle du Crotoy.'],
        ],
    ],
    [
        'slug' => 'hors-rfn-cfbs-noyelles-saint-valery',
        'name' => 'Ligne de Noyelles-sur-Mer à Saint-Valery-Canal',
        'code' => '324000',
        'network' => 'principal',
        'gauge_mm' => 1435,
        'operator' => 'Chemin de fer de la baie de Somme (CFBS)',
        'historical_operator' => 'Compagnie des chemins de fer du Nord',
        'wikidata' => 'Q106629931',
        'aliases' => ['Ligne de Noyelles à Saint-Valery'],
        'description' => 'Embranchement de la ligne d\'Amiens à Boulogne, à double écartement (normal et métrique) depuis 1887, déclassé du réseau ferré national en 1994 ; exploité par le CFBS.',
        'sources' => ['https://fr.wikipedia.org/w/index.php?title=Gare_de_Saint-Valery-Canal&oldid=214492738', 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_la_baie_de_Somme&oldid=239672428', 'https://fr.wikipedia.org/w/index.php?title=Chemins_de_fer_d%C3%A9partementaux_de_la_Somme&oldid=239886986',
            'https://www.lignes-oubliees.com/fichiers/declassements.pdf'],
        'trace' => [
            'bbox' => [1.640, 50.170, 1.710, 50.195],
            'osm' => ['layers' => ['rail'], 'name' => '^Ligne de Noyelles à Saint-Valery'],
        ],
        'events' => [
            ['type' => 'opening', 'date' => '1858-06-05',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Gare_de_Saint-Valery-Canal&oldid=214492738',
                'quote' => 'La « gare de Saint-Valery » est mise en service le 5 juin 1858 par la Compagnie des chemins de fer du Nord, lorsqu\'elle ouvre à l\'exploitation sa ligne de Noyelles à Saint-Valery, court embranchement de la ligne d\'Amiens à Boulogne.',
                'reason' => 'avec la voie du port, ouverte aux marchandises la même année'],
            ['type' => 'closure_passenger', 'date' => '1972-12-31',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_la_baie_de_Somme&oldid=239672428',
                'quote' => '31 décembre 1972 : Arrêt de l\'exploitation par les Chemins de fer et de transports automobiles (CFTA) de la ligne Noyelles - Cayeux-sur-Mer, le CFBS reprend l\'exploitation de cette ligne, après avoir signé un accord avec la SNCF pour la desserte du port de Saint-Valery-sur-Somme.',
                'reason' => 'fin des trains de voyageurs Noyelles - Cayeux, qui empruntaient la voie métrique de la ligne'],
            ['type' => 'heritage_start', 'heritage_kind' => 'tourist_train', 'date' => '1973-01-01', 'precision' => 'year',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemins_de_fer_d%C3%A9partementaux_de_la_Somme&oldid=239886986',
                'quote' => 'en 1973, sur la ligne Noyelles - Saint-Valery - Cayeux.'],
            ['type' => 'closure_freight', 'date' => '1989-02-06',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemins_de_fer_d%C3%A9partementaux_de_la_Somme&oldid=239886986',
                'quote' => 'La SNCF reprit l\'exploitation fret de la ligne Noyelles - Saint-Valery-Port, grâce à son double écartement voie normale / voie métrique, du 31 décembre 1972 à sa fermeture définitive, le 6 février 1989.'],
            ['type' => 'declassification', 'date' => '1994-10-17',
                'source_url' => 'https://www.lignes-oubliees.com/fichiers/declassements.pdf',
                'quote' => 'Noyelles - Saint-Valéry-Canal | Totalité de la ligne | 189,812 | 194,635 | 4,823 | IG | DECL | 17/10/1994',
                'reason' => 'déclassement de la ligne 324000 du réseau ferré national ; le CFBS continue de l\'exploiter'],
        ],
    ],
    [
        'slug' => 'hors-rfn-cfbs-saint-valery-cayeux',
        'name' => 'Ligne de Saint-Valery-Ville à Cayeux-sur-Mer',
        'network' => 'secondaire',
        'gauge_mm' => 1000,
        'operator' => 'Chemin de fer de la baie de Somme (CFBS)',
        'historical_operator' => 'Société générale des chemins de fer économiques',
        // Q116786812 décrit toute la relation Noyelles - Cayeux (avec la ligne de Saint-Valery) : cité en source seulement.
        'description' => 'Ligne à voie métrique des Chemins de fer départementaux de la Somme (réseau des Bains de mer), prolongeant la ligne de Noyelles à Saint-Valery ; exploitée par le CFBS depuis 1973.',
        'sources' => ['https://fr.wikipedia.org/w/index.php?title=Chemins_de_fer_d%C3%A9partementaux_de_la_Somme&oldid=239886986', 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_la_baie_de_Somme&oldid=239672428', 'https://www.wikidata.org/wiki/Q116786812'],
        'trace' => [
            'bbox' => [1.495, 50.160, 1.650, 50.185],
            'osm' => ['layers' => ['rail'], 'name' => '^Ligne de Saint-Valery à Cayeux'],
        ],
        'events' => [
            ['type' => 'opening', 'date' => '1887-09-06',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemins_de_fer_d%C3%A9partementaux_de_la_Somme&oldid=239886986',
                'quote' => 'Cette ligne est la poursuite de la ligne de Noyelles à Saint-Valery, mais est uniquement à voie métrique, et a été créée par la SE le 6 septembre 1887.'],
            ['type' => 'closure', 'date' => '1972-12-31',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_la_baie_de_Somme&oldid=239672428',
                'quote' => 'Le 31 décembre 1972, la ligne Noyelles-sur-Mer - Cayeux-sur-Mer ferme à son tour et le CFBS la reprend signant un accord avec la SNCF afin que la société continue de desservir le port de Saint-Valery jusqu\'en 1989.'],
            ['type' => 'heritage_start', 'heritage_kind' => 'tourist_train', 'date' => '1973-01-01', 'precision' => 'year',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemins_de_fer_d%C3%A9partementaux_de_la_Somme&oldid=239886986',
                'quote' => 'en 1973, sur la ligne Noyelles - Saint-Valery - Cayeux.'],
        ],
    ],

    [
        'slug' => 'hors-rfn-train-artouste',
        'name' => 'Ligne de la Sagette au lac d\'Artouste',
        'network' => 'particulier',
        'gauge_mm' => 500,
        'operator' => 'Régie d\'Artouste',
        'historical_operator' => 'Compagnie des chemins de fer du Midi',
        'wikipedia' => 'Petit train d\'Artouste',
        'wikidata' => 'Q198366',
        'aliases' => ['Petit train d\'Artouste'],
        'description' => 'Voie de chantier du barrage d\'Artouste, ouverte aux touristes depuis 1932.',
        'sources' => ['https://fr.wikipedia.org/w/index.php?title=Petit_train_d%27Artouste&oldid=239680293'],
        'trace' => [
            'bbox' => [-0.41, 42.86, -0.33, 42.905],
            'osm' => ['layers' => ['narrow_gauge'], 'name' => '^Petit Train d.Artouste'],
        ],
        'events' => [
            ['type' => 'opening', 'date' => '1920-01-01', 'precision' => 'year',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Petit_train_d%27Artouste&oldid=239680293',
                'quote' => 'En 1920, sur le site de la vallée d\'Ossau, pour faciliter les travaux de construction du barrage, la compagnie pose à Sagette un chemin de fer de chantier',
                'reason' => 'voie de chantier ; 1922 ou 1924 selon d\'autres sources'],
            ['type' => 'heritage_start', 'heritage_kind' => 'tourist_train', 'date' => '1932-01-01', 'precision' => 'year',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Petit_train_d%27Artouste&oldid=239680293',
                'quote' => 'En 1932, le Conseil Général du département souhaitant développer le tourisme, soumet à la Compagnie des chemins de fer du Midi (CCM) l\'idée d\'exploiter à des fins touristique le paysage de la haute vallée d\'Ossau.'],
        ],
    ],
    [
        'slug' => 'hors-rfn-ptit-train-saint-trojan',
        'name' => 'Ligne de Saint-Trojan à Maumusson',
        'network' => 'particulier',
        'gauge_mm' => 600,
        'operator' => 'Société du p\'tit train de Saint-Trojan (STTST)',
        'wikipedia' => 'Le p\'tit train de Saint-Trojan',
        'wikidata' => 'Q3228494',
        'aliases' => ['Le p\'tit train de Saint-Trojan'],
        'description' => 'Chemin de fer touristique de l\'île d\'Oléron, construit pour le tourisme en 1963 (sans lien avec l\'ancien chemin de fer de l\'île).',
        'sources' => ['https://fr.wikipedia.org/w/index.php?title=Le_p%27tit_train_de_Saint-Trojan&oldid=240190899'],
        'trace' => [
            'bbox' => [-1.24, 45.80, -1.20, 45.84],
            'osm' => ['layers' => ['narrow_gauge'], 'name' => 'p.tit train de Saint-Trojan'],
        ],
        'events' => [
            ['type' => 'heritage_start', 'heritage_kind' => 'tourist_train', 'date' => '1963-07-01',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Le_p%27tit_train_de_Saint-Trojan&oldid=240190899',
                'quote' => 'Le service débuta au 1er juillet 1963, et reliait, à l\'époque, l\'actuelle gare de Saint-Trojan à la petite gare de Gatseau, située le long de la plage du même nom.',
                'reason' => 'première mise en service de la ligne'],
            ['type' => 'heritage_start', 'heritage_kind' => 'tourist_train', 'date' => '1963-07-01', 'from' => 'Saint-Trojan', 'to' => 'Gatseau',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Le_p%27tit_train_de_Saint-Trojan&oldid=240190899',
                'quote' => 'Le service débuta au 1er juillet 1963, et reliait, à l\'époque, l\'actuelle gare de Saint-Trojan à la petite gare de Gatseau, située le long de la plage du même nom.'],
            ['type' => 'heritage_start', 'heritage_kind' => 'tourist_train', 'date' => '1965-01-01', 'precision' => 'year', 'from' => 'Gatseau', 'to' => 'Maumusson',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Le_p%27tit_train_de_Saint-Trojan&oldid=240190899',
                'quote' => 'La ligne connaissant un vif succès, elle fut prolongée, en 1965, vers les dunes de la côte sauvage, situées à l\'ouest de l\'île.'],
        ],
    ],
    [
        // Prolongement d'avril 2013 vers la digue de Pincemaille non localisé (ancien évitement « Port ») :
        // la ligne entière est datée de 1990.
        'slug' => 'hors-rfn-chemin-de-fer-de-rille',
        'name' => 'Chemin de fer du lac de Rillé',
        'network' => 'particulier',
        'gauge_mm' => 600,
        'operator' => 'AECFM',
        'wikipedia' => 'Chemin de fer de Rillé',
        'wikidata' => 'Q3536555',
        'aliases' => ['Train de Rillé'],
        'description' => 'Chemin de fer touristique de l\'Association pour l\'exploitation et la conservation du matériel ferroviaire (AECFM), au bord du lac de Rillé.',
        'sources' => ['https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_Rill%C3%A9&oldid=239887788'],
        'trace' => [
            'bbox' => [0.215, 47.454, 0.232, 47.463],
            'osm' => ['layers' => ['narrow_gauge'], 'name' => 'lac de Rillé'],
        ],
        'events' => [
            ['type' => 'heritage_start', 'heritage_kind' => 'tourist_train', 'date' => '1990-08-26',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_Rill%C3%A9&oldid=239887788',
                'quote' => 'après trois mois 1/2 de travaux est mise partiellement en service le 26 août 1990 et officiellement inaugurée le 26 mai 1991 d\'une longueur de 1,5 km',
                'reason' => 'prolongée vers la digue de Pincemaille en avril 2013'],
        ],
    ],
    [
        // Voie de 381 mm (« voie de 38 »), matériel à l'échelle 1/4 : ligne associative sur terrain public
        // (passages à niveau, tunnel), pas un train de parc. Interruption de 1989 (travaux de l'A46)
        // et mise en service du prolongement vers Anse-Plage non datées.
        'slug' => 'hors-rfn-chemin-de-fer-touristique-anse',
        'name' => 'Ligne d\'Anse-Pont à Anse-Plage',
        'network' => 'particulier',
        'gauge_mm' => 381,
        'operator' => 'Chemin de fer touristique d\'Anse (CFTA)',
        'wikipedia' => 'Chemin de fer touristique d\'Anse',
        'wikidata' => 'Q2962403',
        'aliases' => ['Chemin de fer touristique d\'Anse'],
        'sources' => ['https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_touristique_d%27Anse&oldid=235829676', 'https://www.cftanse.fr/index.php/historique'],
        'trace' => [
            'bbox' => [4.72, 45.935, 4.73, 45.952],
            'osm' => ['layers' => ['narrow_gauge'], 'name' => 'Touristique d.Anse'],
        ],
        'events' => [
            ['type' => 'heritage_start', 'heritage_kind' => 'tourist_train', 'date' => '1972-06-01', 'precision' => 'month',
                'source_url' => 'https://www.cftanse.fr/index.php/historique',
                'quote' => 'Juin 1972 Ouverture du premier tronçon de ligne, de la gare Jean Vacher actuelle jusqu’au pont de l’autoroute A6 sur l’Azergues.',
                'reason' => 'prolongée en 1974, puis vers le plan d\'eau des Communaux (Anse-Plage) en 2003-2004'],
        ],
    ],
    [
        // Article et QID partagés avec l'ancien tramway (ligne « Tramway du Cap-Ferret », archeo) : sources seulement.
        'slug' => 'hors-rfn-petit-train-cap-ferret',
        'name' => 'Ligne de Bélisaire à la plage de l\'Horizon',
        'network' => 'particulier',
        'gauge_mm' => 600,
        'operator' => 'Transports ferroviaires du Cap-Ferret (régie municipale)',
        'aliases' => ['Petit train du Cap-Ferret'],
        'description' => 'Petit train du Cap-Ferret, voie de 60 cm construite en 1952 entre l\'embarcadère de Bélisaire et la plage océane.',
        'sources' => ['https://fr.wikipedia.org/w/index.php?title=Tramway_du_Cap-Ferret&oldid=239596989', 'https://www.wikidata.org/wiki/Q3537236'],
        'trace' => [
            'bbox' => [-1.26, 44.654, -1.238, 44.66],
            'bdtopo' => ['toponyme' => 'Petit Train du Cap Ferret'],
        ],
        'events' => [
            ['type' => 'heritage_start', 'heritage_kind' => 'tourist_train', 'date' => '1952-07-14',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Tramway_du_Cap-Ferret&oldid=239596989',
                'quote' => 'Le 14 juillet 1952 a lieu l\'inauguration de la nouvelle ligne, qui traverse la presqu\'île de l\'embarcadère de Bélisaire à la plage de l\'Horizon.'],
        ],
    ],
    [
        // Saisons 2010-2011 limitées à Les Fiancés - Les Tilliers (travaux) : interruption non modélisée.
        'slug' => 'hors-rfn-chemin-de-fer-chanteraines',
        'name' => 'Ligne des Vallons aux Tilliers',
        'network' => 'particulier',
        'gauge_mm' => 600,
        'operator' => 'Association du chemin de fer des Chanteraines',
        'wikipedia' => 'Chemin de fer des Chanteraines',
        'wikidata' => 'Q2962384',
        'aliases' => ['Chemin de fer des Chanteraines'],
        'description' => 'Chemin de fer du parc départemental des Chanteraines (Hauts-de-Seine), en correspondance avec le RER C aux Tilliers.',
        'sources' => ['https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_des_Chanteraines&oldid=239572322'],
        'trace' => [
            'bbox' => [2.305, 48.930, 2.326, 48.950],
            'osm' => ['layers' => ['narrow_gauge'], 'name' => 'Chemin de fer des Chanteraines'],
        ],
        'events' => [
            ['type' => 'heritage_start', 'heritage_kind' => 'tourist_train', 'date' => '1981-01-01', 'precision' => 'year',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_des_Chanteraines&oldid=239572322',
                'quote' => '1981 : mise en service de la ligne entre le Pont d\'Épinay (aujourd\'hui Les Mariniers) et le Passage de Verdure situé au-dessus de l\'autoroute A86, soit une distance de 3,1 kilomètres.',
                'reason' => 'première mise en service de la ligne'],
            ['type' => 'heritage_start', 'heritage_kind' => 'tourist_train', 'date' => '1981-01-01', 'precision' => 'year',
                'from' => 'Les Vallons', 'to' => 'Le Passage de Verdure', 'portion' => [2.300, 48.9382, 2.330, 48.955],
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_des_Chanteraines&oldid=239572322',
                'quote' => '1981 : mise en service de la ligne entre le Pont d\'Épinay (aujourd\'hui Les Mariniers) et le Passage de Verdure situé au-dessus de l\'autoroute A86, soit une distance de 3,1 kilomètres.'],
            ['type' => 'heritage_start', 'heritage_kind' => 'tourist_train', 'date' => '1991-07-01', 'precision' => 'month',
                'from' => 'Le Passage de Verdure', 'to' => 'Les Tilliers', 'portion' => [2.300, 48.925, 2.330, 48.9382],
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_des_Chanteraines&oldid=239572322',
                'quote' => 'Juillet 1991 : extension du parc permettant le prolongement de la ligne jusqu\'au nouveau terminus Les Tilliers RER, en correspondance avec le RER C.'],
        ],
    ],
    [
        // Voie propre à écartement normal le long du littoral, distincte de la ligne SNCF de Ravezies à
        // Pointe-de-Grave. Voie de travaux des années 1920, abandonnée après 1945 : années non sourcées.
        'slug' => 'hors-rfn-train-touristique-pgvs',
        'name' => 'Ligne de la Pointe de Grave à Soulac-Les Arros',
        'network' => 'particulier',
        'gauge_mm' => 1435,
        'operator' => 'SIVU Le Verdon-sur-Mer / Soulac-sur-Mer',
        'wikipedia' => 'Train touristique PGVS',
        'wikidata' => 'Q48748163',
        'aliases' => ['Petit train touristique PGVS'],
        'sources' => ['https://fr.wikipedia.org/w/index.php?title=Train_touristique_PGVS&oldid=240007895'],
        'trace' => [
            'bbox' => [-1.115, 45.52, -1.066, 45.57],
            'bdtopo' => ['toponyme' => 'P\.G\.V\.S'],
            'osm' => ['layers' => ['rail'], 'name' => 'Pointe de Grave le Verdon'],
        ],
        'events' => [
            ['type' => 'heritage_start', 'heritage_kind' => 'tourist_train', 'date' => '1986-01-01', 'precision' => 'year',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Train_touristique_PGVS&oldid=240007895',
                'quote' => 'En 1986, le tracé de la ligne est revu et les voies sont réaménagées. La ligne relie alors la gare de la Pointe de Grave-PGVS à son nouveau terminus : la gare de Soulac-Les Arros-PGVS.'],
        ],
    ],
    [
        // Partie française (Lasauvage - Saulnes) du chemin de fer minier de la MMR-A repris par la
        // Minièresbunn Doihl ; ouverture minière non datée par les sources.
        'slug' => 'hors-rfn-minieresbunn-saulnes',
        'name' => 'Ligne de Lasauvage à Saulnes (Minièresbunn)',
        'network' => 'particulier',
        'gauge_mm' => 700,
        'operator' => 'Minièresbunn Doihl',
        'wikidata' => 'Q86464798',
        'sources' => ['https://minieresbunn.lu/uber-uns/', 'https://rail.lu/events/20020413inauglignesaulnes.html'],
        'trace' => [
            'bbox' => [5.82, 49.525, 5.84, 49.537],
            'osm' => ['layers' => ['narrow_gauge'], 'name' => '^Minièresbunn'],
            'bdtopo' => ['toponyme' => 'Minièresbunn'],
        ],
        'events' => [
            ['type' => 'heritage_start', 'heritage_kind' => 'tourist_train', 'date' => '2002-04-13',
                'source_url' => 'https://rail.lu/events/20020413inauglignesaulnes.html',
                'quote' => 'Locomotive à vapeur "Krauss & Cie" (No 3665 de 1897) de la Minièresbunn Doihl à Lasauvage avant le départ du train inaugural de la remise en service du chemin de fer minier à voie étroite (700 mm) entre Lasauvage (Luxembourg) et Saulnes (France).'],
        ],
    ],
    [
        // Chemin de fer à crémaillère (Strub, voie métrique) ; l'arrêt du 28 octobre 2012 au 2 mai 2013
        // (déraillement, changement d'exploitant) n'est pas modélisé.
        'slug' => 'hors-rfn-panoramique-des-domes',
        'name' => 'Ligne de la Maison de site au sommet du puy de Dôme',
        'network' => 'particulier',
        'gauge_mm' => 1000,
        'operator' => 'TC Dôme',
        'wikipedia' => 'Panoramique des Dômes',
        'wikidata' => 'Q3362410',
        'aliases' => ['Panoramique des Dômes'],
        'description' => 'Train à crémaillère construit en partie sur la plateforme de l\'ancien chemin de fer du puy de Dôme (1907-1925).',
        'sources' => ['https://fr.wikipedia.org/w/index.php?title=Panoramique_des_D%C3%B4mes&oldid=238874624'],
        'trace' => [
            'bbox' => [2.95, 45.76, 3.0, 45.78],
            'bdtopo' => ['toponyme' => 'Panoramique des Dômes'],
            'osm' => ['layers' => ['narrow_gauge'], 'operator' => 'TC Dôme'],
        ],
        'events' => [
            ['type' => 'heritage_start', 'heritage_kind' => 'tourist_train', 'date' => '2012-05-26',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Panoramique_des_D%C3%B4mes&oldid=238874624',
                'quote' => 'Inauguré de façon anticipée le samedi 26 mai 2012, le nouveau train à crémaillère a été arrêté pour une durée indéterminée l\'après-midi même'],
        ],
    ],

    [
        // Voie de 60 posée sur l'ancien chemin de halage (date de la convention avec les Domaines ;
        // L'Observateur, 2021, situe la création vers 1986).
        'slug' => 'hors-rfn-chemin-de-fer-vapeur-scarpe',
        'name' => 'Chemin de fer à vapeur de la vallée de la Scarpe',
        'network' => 'particulier',
        'gauge_mm' => 600,
        'operator' => 'AAMCS',
        'aliases' => ['Train touristique de la vallée de la Scarpe'],
        'sources' => ['http://marc-andre-dubout.org/cf/lvdc/lvdc0120/scarpe.htm'],
        'trace' => [
            'bbox' => [3.405, 50.428, 3.432, 50.444],
            'osm' => ['layers' => ['narrow_gauge'], 'name' => 'vallée de la Scarpe'],
            'bdtopo' => ['toponyme' => 'Vapeur de la Scarpe'],
        ],
        'events' => [
            ['type' => 'heritage_start', 'heritage_kind' => 'tourist_train', 'date' => '1998-01-01', 'precision' => 'year',
                'source_url' => 'http://marc-andre-dubout.org/cf/lvdc/lvdc0120/scarpe.htm',
                'quote' => 'En 1998, le Train Touristique de la Vallée de la Scarpe signe une convention avec les Domaines pour avoir l\'autorisation de poser de la voie de 60 sur le chemin de halage et exploiter un chemin de fer.'],
        ],
    ],
    [
        // Prolongement de 800 m vers la ferme Saint-Chrysole en 2003 non localisé : ligne datée de 1995.
        'slug' => 'hors-rfn-amitram-deule',
        'name' => 'Ligne de Marquette-lez-Lille à la ferme Saint-Chrysole (AMITRAM)',
        'network' => 'particulier',
        'gauge_mm' => 1000,
        'operator' => 'AMITRAM',
        'wikipedia' => 'Tramway touristique de la vallée de la Deûle',
        'wikidata' => 'Q9360961',
        'aliases' => ['Tramway touristique de la vallée de la Deûle'],
        'description' => 'Tramway touristique à voie métrique posé de 1989 à 1995 entre Marquette-lez-Lille et Wambrechies.',
        'sources' => ['http://www.amitram.fr/asso_presentation.php',
            'https://fr.wikipedia.org/w/index.php?title=Tramway_touristique_de_la_vall%C3%A9e_de_la_De%C3%BBle&oldid=235184764'],
        'trace' => [
            'bbox' => [3.04, 50.674, 3.063, 50.695],
            'osm' => ['layers' => ['historic'], 'name' => 'Amitram'],
            'bdtopo' => ['toponyme' => 'Vallée de la Deûle'],
        ],
        'events' => [
            ['type' => 'heritage_start', 'heritage_kind' => 'tourist_train', 'date' => '1995-04-01',
                'source_url' => 'http://www.amitram.fr/asso_presentation.php',
                'quote' => 'L’inauguration de la ligne eut lieu le 1er avril 1995. Elle s’étendait du Pont Mabile (Marquette) au Vent de Bise (Wambrechies).',
                'reason' => 'prolongée de 800 m vers la ferme Saint-Chrysole en 2003'],
        ],
    ],
    // Réseau de 600 mm du Creusot (Chemins de fer du Creusot) : circuit des Combes et ligne des Deux
    // Vallées, en partie sur la plateforme du tacot Schneider des Crouillottes (1896 - années 1950, dates
    // discordantes, non reprises). Le parc d'attractions voisin n'est pas la ligne.
    [
        'slug' => 'hors-rfn-circuit-des-combes',
        'name' => 'Circuit des Combes (Le Creusot)',
        'network' => 'particulier',
        'gauge_mm' => 600,
        'operator' => 'Chemins de fer du Creusot',
        'wikidata' => 'Q117813172',
        'aliases' => ['Train des Combes'],
        'sources' => ['https://parcdescombes.com/histoire-du-parc/?lang=fr', 'https://fr.wikipedia.org/w/index.php?title=Chemins_de_fer_du_Creusot&oldid=239441523'],
        'trace' => [
            'bbox' => [4.40, 46.805, 4.432, 46.82],
            'osm' => ['layers' => ['narrow_gauge'], 'name' => '^Train des Combes'],
        ],
        'events' => [
            ['type' => 'heritage_start', 'heritage_kind' => 'tourist_train', 'date' => '1990-01-01', 'precision' => 'year',
                'source_url' => 'https://parcdescombes.com/histoire-du-parc/?lang=fr',
                'quote' => '1990 Inauguration du premier tronçon de 2,5 kilomètres',
                'reason' => 'second tronçon inauguré en 1995'],
        ],
    ],
    [
        'slug' => 'hors-rfn-ligne-des-deux-vallees',
        'name' => 'Ligne du Creusot à la halte des Mineurs (Deux Vallées)',
        'network' => 'particulier',
        'gauge_mm' => 600,
        'operator' => 'Chemins de fer du Creusot',
        'aliases' => ['Train des Deux Vallées'],
        'sources' => ['https://parcdescombes.com/histoire-du-parc/?lang=fr'],
        'trace' => [
            'bbox' => [4.40, 46.805, 4.432, 46.82],
            'osm' => ['layers' => ['narrow_gauge'], 'name' => '^Train des Deux Vallées'],
        ],
        'events' => [
            ['type' => 'heritage_start', 'heritage_kind' => 'tourist_train', 'date' => '1999-01-01', 'precision' => 'year',
                'source_url' => 'https://parcdescombes.com/histoire-du-parc/?lang=fr',
                'quote' => '1999 Nouvel accès Les visiteurs peuvent désormais embarquer dans le petit train depuis la gare du Chemin de Fer des Combes du Creusot, près de la gare SNCF, le long d’un trajet de 10 kilomètres.'],
        ],
    ],
    [
        // Réseau actuel de 600 m (1988), porté à 1,4 km de 1992 à 2005 : extension non localisée.
        'slug' => 'hors-rfn-train-touristique-lavieres',
        'name' => 'Train touristique des Lavières',
        'network' => 'particulier',
        'gauge_mm' => 500,
        'operator' => 'Chemin de fer touristique d\'Is-sur-Tille (CFTI)',
        'wikipedia' => 'Train touristique des Lavières',
        'wikidata' => 'Q3536590',
        'sources' => ['https://fr.wikipedia.org/w/index.php?title=Train_touristique_des_Lavi%C3%A8res&oldid=238492974'],
        'trace' => [
            'bbox' => [5.086, 47.504, 5.099, 47.512],
            'osm' => ['layers' => ['narrow_gauge'], 'name' => 'Lavières'],
            'bdtopo' => ['toponyme' => 'Lavières'],
        ],
        'events' => [
            ['type' => 'heritage_start', 'heritage_kind' => 'tourist_train', 'date' => '1988-01-01', 'precision' => 'year',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Train_touristique_des_Lavi%C3%A8res&oldid=238492974',
                'quote' => 'C\'est en 1988 que l\'association créa un nouveau réseau de 600 mètres de long situé à 400 mètres de l\'ancien.',
                'reason' => 'réseau porté à 1,4 km de 1992 à 2005'],
        ],
    ],
    [
        'slug' => 'hors-rfn-chemin-de-fer-semur-en-vallon',
        'name' => 'Chemin de fer de Semur-en-Vallon',
        'network' => 'particulier',
        'gauge_mm' => 600,
        'operator' => 'Compagnie du chemin de fer de Semur-en-Vallon',
        'wikipedia' => 'Compagnie du chemin de fer de Semur-en-Vallon',
        'wikidata' => 'Q98456516',
        'sources' => ['https://fr.wikipedia.org/w/index.php?title=Compagnie_du_chemin_de_fer_de_Semur-en-Vallon&oldid=222607379'],
        'trace' => [
            'bbox' => [0.648, 48.012, 0.659, 48.019],
            'osm' => ['layers' => ['narrow_gauge'], 'name' => 'Semur en Vallon'],
        ],
        'events' => [
            ['type' => 'heritage_start', 'heritage_kind' => 'tourist_train', 'date' => '1972-01-01', 'precision' => 'year',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Compagnie_du_chemin_de_fer_de_Semur-en-Vallon&oldid=222607379',
                'quote' => 'L’aventure a commencé en 1968, avec un petit train à voies de 60 autour d’un plan d’eau en 1972.'],
        ],
    ],
    [
        // Domaine privé (Mas de Méjanes), pas un parc d'attractions : cas limite à faire relire.
        'slug' => 'hors-rfn-petit-train-mejanes',
        'name' => 'Petit train de Méjanes',
        'network' => 'particulier',
        'gauge_mm' => 1000,
        'operator' => 'Domaine de Méjanes',
        'sources' => ['https://mejanes-camargue.fr/fr/visites-touristiques-le-petit-train-de-mejanes'],
        'trace' => [
            'bbox' => [4.494, 43.560, 4.51, 43.571],
            'osm' => ['layers' => ['narrow_gauge'], 'name' => 'Méjanes'],
        ],
        'events' => [
            ['type' => 'heritage_start', 'heritage_kind' => 'tourist_train', 'date' => '1968-01-01', 'precision' => 'year',
                'source_url' => 'https://mejanes-camargue.fr/fr/visites-touristiques-le-petit-train-de-mejanes',
                'quote' => 'Imaginé par Paul Ricard, et inauguré en 1968, le Petit Train de Méjanes est l’unique train touristique de Camargue qui se déplace sur rails.'],
        ],
    ],
    [
        // Embranchement industriel et portuaire (fret seul, jamais de service voyageurs commercial),
        // prolongement au nord de la ligne 120000 au-delà de Volgelsheim ; ce n'est pas l'ancienne ligne
        // métrique de Colmar à Marckolsheim. Section exploitée par le CFTR (Q1069302) : tracé IGN nommé.
        'slug' => 'hors-rfn-embranchement-volgelsheim-marckolsheim',
        'name' => 'Embranchement de Volgelsheim à Marckolsheim (port rhénan)',
        'network' => 'particulier',
        'gauge_mm' => 1435,
        'operator' => 'Port rhénan de Colmar / Neuf-Brisach ; Chemin de fer touristique du Rhin (CFTR)',
        'aliases' => ['Chemin de fer touristique du Rhin'],
        'sources' => ['https://fr.wikipedia.org/w/index.php?title=Ligne_de_Colmar-Central_%C3%A0_Neuf-Brisach&oldid=239020068',
            'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_touristique_du_Rhin&oldid=236351215',
            'https://cftr.evolutive.org/amateur/histoire.php', 'https://www.wikidata.org/wiki/Q1069302'],
        'trace' => [
            'bbox' => [7.54, 48.015, 7.58, 48.12],
            'bdtopo' => ['toponyme' => 'Chemin de Fer Touristique du Rhin'],
        ],
        'events' => [
            ['type' => 'opening', 'date' => '1960-01-01', 'precision' => 'year',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Colmar-Central_%C3%A0_Neuf-Brisach&oldid=239020068',
                'quote' => 'L’embranchement qui dessert le port de Marckolsheim a été construit entre 1959 et 1960.'],
            ['type' => 'heritage_start', 'heritage_kind' => 'tourist_train', 'date' => '1983-07-09',
                'source_url' => 'https://cftr.evolutive.org/amateur/histoire.php',
                'quote' => 'sa première circulation officielle eut lieu le 9 juillet 1983, soit un peu plus d\'un an après sa création.',
                'reason' => 'trains touristiques de Volgelsheim à Sans-Souci'],
        ],
    ],

    // ───────────────────────── Lignes existantes ─────────────────────────

    [
        // Tramway de Pithiviers à Toury : la voie touristique va jusqu'à la base de loisirs de Bellébat
        // (prolongement de 1971) ; forme déclarée pour que le tracé de la ligne l'atteigne.
        'line' => 'ligne-de-pithiviers-a-toury',
        'trace' => [
            'bbox' => [2.20, 48.17, 2.25, 48.20],
            'bdtopo' => ['toponyme' => 'Pithiviers-Bellébat'],
            'osm' => ['layers' => ['narrow_gauge'], 'name' => 'touristique de Pithiviers'],
        ],
        'events' => [
            ['type' => 'opening', 'date' => '1892-07-25',
                'source_url' => 'https://en.wikipedia.org/w/index.php?title=Tramway_de_Pithiviers_%C3%A0_Toury&oldid=1355425898',
                'quote' => 'The line opened on 25 July 1892.',
                'reason' => 'Wikipédia en français et Wikidata donnent 1893'],
            // Portion : la voie touristique (IGN « Ligne Touristique Pithiviers-Bellébat », 4,3 km), pas les
            // 32 km du tramway déposé. 1966 : de Pithiviers aux Ormes, le long de l'ancien tracé du TPT (la voie
            // OSM le quitte à l'ouest de 2,2095° E : boucle de Bellébat) ; 1971 : prolongement vers Bellébat.
            ['type' => 'heritage_start', 'heritage_kind' => 'tourist_train', 'date' => '1966-04-23',
                'from' => 'Pithiviers', 'to' => 'Les Ormes', 'portion' => [2.2095, 48.17, 2.25, 48.20],
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Tramway_de_Pithiviers_%C3%A0_Toury&oldid=234550923',
                'quote' => 'l\'exploitation a pu commencer le 23 avril 1966, ainsi que l\'ouverture du musée des transports de Pithiviers dans les locaux de l\'ancien dépôt. Les circulations se firent sur un tronçon de 3,2 km entre Pithiviers et les Ormes.',
                'reason' => 'voie touristique de Pithiviers aux Ormes, le long de la route'],
            ['type' => 'heritage_start', 'heritage_kind' => 'tourist_train', 'date' => '1971-01-01', 'precision' => 'year',
                'from' => 'Les Ormes', 'to' => 'Bellébat', 'portion' => [2.20, 48.17, 2.2095, 48.20],
                'source_url' => 'https://amtp-cfpithiviers.fr/notre-histoire',
                'quote' => 'Il faudra attendre 1971 pour prolonger la ligne vers le terminus actuel de Bellebat, rajoutant environ 800m de parcours.',
                'reason' => 'prolongement de la voie touristique des Ormes au terminus de Bellébat (site de l\'association du musée des transports de Pithiviers)'],
        ],
    ],
    [
        // Ligne de Sceaux (552000, RER B) : la section Laplace - Bourg-la-Reine de l'ouverture de 1846 n'est
        // pas résolue par lieux (tracé de référence fragmenté) : portion déclarée par emprise. Les autres
        // ouvertures de la ligne sont dans database/data/line_event_corrections.php.
        'line' => 'ligne-de-sceaux',
        'trace' => [
            'bbox' => [2.05, 48.69, 2.345, 48.85],
            'osm' => ['layers' => ['rail'], 'operator' => '^RATP', 'name' => '^Ligne de Sceaux'],
        ],
        'events' => [
            ['type' => 'opening', 'date' => '1846-06-23', 'from' => 'Laplace', 'to' => 'Bourg-la-Reine',
                'portion' => [2.305, 48.7815, 2.337, 48.8085],
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Sceaux&oldid=240177929',
                'quote' => 'Il est inauguré le 7 juin 1846, et ouvert au public le 23 juin.',
                'reason' => 'ligne de la barrière d\'Enfer à Sceaux, inaugurée le 7 juin 1846, ouverte au public le 23 juin'],
        ],
    ],

    // ───────────────────── Lignes du RFN sans forme SNCF ─────────────────────
    // 433000 Couterne - La Ferté-Macé : ligne du RFN (code de l'infobox de l'article, liste des
    // fermetures de RFF, gare de Bagnoles-de-l'Orne au PK 6,978 dans liste-des-gares) dont la SNCF ne
    // publie pas la forme. Son tracé est la moitié sud du tracé archeoferroviaire « Briouze Couterne »,
    // découpé à La Ferté-Macé par vff:link-traces (l'autre moitié est la ligne 434000). Dates : articles
    // de la ligne (Wikipédia, vff:import-wikipedia-chronology), lignes-oubliees et
    // database/data/line_event_corrections_couterne.php.
    [
        'slug' => 'rfn-433000',
        'name' => 'Ligne de Couterne à La Ferté-Macé',
        'code' => '433000',
        'network' => 'principal',
        'gauge_mm' => 1435,
        'historical_operator' => 'Compagnie des chemins de fer de l\'Ouest',
        'wikidata' => 'Q106368424',
        'wikipedia' => 'Ligne de Briouze à Couterne',
        'aliases' => ['Ligne de La Ferté-Macé à Couterne'],
        'description' => 'Section sud de la ligne de Briouze à Couterne : de La Ferté-Macé à Couterne (ligne d\'Alençon à Domfront) par Bagnoles-de-l\'Orne, ouverte le 15 mars 1881. De Bagnoles à Couterne, fermée aux voyageurs en 1941, aux marchandises et déclassée en 1954 ; de La Ferté-Macé à Bagnoles, fermée aux voyageurs en 1991 et aux marchandises en 1992.',
        'sources' => ['https://fr.wikipedia.org/w/index.php?title=Ligne_de_Briouze_%C3%A0_Couterne&oldid=233804148',
            'https://fr.wikipedia.org/w/index.php?title=Gare_de_Bagnoles-de-l%27Orne&oldid=234445086',
            'https://www.wikidata.org/wiki/Q106368424',
            'https://www.lignes-oubliees.com/fichiers/fermetures.pdf'],
    ],
];
