<?php

/**
 * Faits de signalisation relus dans les articles Wikipédia (vff:import-signalling-history,
 * App\Services\Signalling\SignallingSupplementsFile) : mises en service, remplacements, que l'extracteur prudent
 * laisse passer (date donnée par un modèle, phrase avec un lien vers une autre page, portion dite autrement,
 * fait relevé dans l'article d'une gare ou d'une LGV).
 *
 * Règle : seulement ce que la phrase dit, avec l'année au moins ; jamais déduit (« depuis », « précédée dès »
 * et « accompagnée de » sont des mots de l'article, une date calculée n'en est pas une). `quote` est la phrase de
 * l'article (liens ôtés, tirets écrits « - »), cherchée dans l'article lu à chaque import : si l'article change,
 * la commande le dit au lieu de ranger un fait sans appui. `article` : titre de l'article qui porte la phrase
 * quand ce n'est pas celui de la ligne.
 * Relevé et vérifications : storage/app/reports/agents/aprem-2111/signalisation-dates.md.
 */
return [
    // Rennes - Redon : le BAPR laisse la place au BAL.
    [
        'line' => '468000', 'system' => 'BAL', 'date' => '2018-07-31', 'scope' => 'whole_line',
        'quote' => 'À partir du 31 juillet 2018, l\'intégralité de la signalisation de la ligne est passée du block automatique à permissivité restreinte (BAPR) au block automatique lumineux (BAL).',
    ],
    // Saint-Denis - Dieppe : première application du BAPR en France ; BAL de la banlieue nord (article de la ligne H).
    [
        'line' => '330000', 'system' => 'BAPR', 'date' => '1968', 'scope' => 'section', 'from' => 'Pontoise', 'to' => 'Gisors',
        'quote' => 'Le tronçon de Pontoise à Gisors voit la première application en France de la signalisation ferroviaire de type block automatique à permissivité restreinte (BAPR) en 1968',
    ],
    [
        'line' => '330000', 'article' => 'Ligne H du Transilien', 'system' => 'BAL', 'date' => '1965', 'scope' => 'section', 'from' => 'Saint-Denis', 'to' => 'Ermont - Eaubonne',
        'quote' => 'précédée dès 1965 (jusqu\'à Ermont), ou 1968 (au-delà), de la signalisation par block automatique lumineux (BAL) mettant fin au block mécanique Lartigue',
    ],
    [
        'line' => '330000', 'article' => 'Ligne H du Transilien', 'system' => 'BAL', 'date' => '1968', 'scope' => 'section', 'from' => 'Ermont - Eaubonne', 'to' => 'Pontoise',
        'quote' => 'précédée dès 1965 (jusqu\'à Ermont), ou 1968 (au-delà), de la signalisation par block automatique lumineux (BAL) mettant fin au block mécanique Lartigue',
    ],
    // Pierrelaye - Creil : BAL de Pontoise à Creil avec l'électrification.
    [
        'line' => '329000', 'system' => 'BAL', 'date' => '1969-04-15', 'scope' => 'section', 'from' => 'Pontoise', 'to' => 'Creil',
        'quote' => 'Le 15 avril 1969, la traction électrique est en service sur Pontoise - Creil et le 21 mai 1969 sur Saint-Denis - Pontoise, accompagnée de la signalisation par block automatique lumineux.',
    ],
    // Paris-Saint-Lazare - Ermont-Eaubonne (Argenteuil - Ermont) : BAL avec l'électrification.
    [
        'line' => '334900', 'system' => 'BAL', 'date' => '1983', 'scope' => 'unspecified',
        'quote' => 'La modernisation de ce tronçon avec électrification par caténaire en 25 kV et introduction du block automatique lumineux (BAL) ne pose pas de difficultés et est réalisé de manière anticipée en mai 1983',
    ],
    // Narbonne - Port-Bou : BAL en deux temps.
    [
        'line' => '677000', 'system' => 'BAL', 'date' => '1981-09-23', 'scope' => 'section', 'from' => 'Narbonne', 'to' => 'Elne',
        'quote' => 'la signalisation de type block automatique lumineux (BAL) datent du 23 septembre 1981 pour la première section de Narbonne à Elne, et du 29 avril 1982 pour la seconde d\'Elne à Port-Bou',
    ],
    [
        'line' => '677000', 'system' => 'BAL', 'date' => '1982-04-29', 'scope' => 'section', 'from' => 'Elne', 'to' => 'Port-Bou',
        'quote' => 'la signalisation de type block automatique lumineux (BAL) datent du 23 septembre 1981 pour la première section de Narbonne à Elne, et du 29 avril 1982 pour la seconde d\'Elne à Port-Bou',
    ],
    // Lyon - Marseille par Grenoble : BAL à la place du block manuel de voie unique sur le tronçon commun.
    [
        'line' => '905000', 'system' => 'BAL', 'date' => '1968', 'scope' => 'section', 'from' => 'Grenoble', 'to' => 'Pont-de-Claix',
        'quote' => 'En 1968, lorsque la ligne Grenoble - Montmélian est déviée vers le sud de Grenoble, le tronçon commun aux deux lignes le long du Cours Jean Jaurès est mis à double voie, tandis que le block automatique lumineux (BAL) remplace le BMU jusqu\'à Pont-de-Claix.',
    ],
    // Elne - Arles-sur-Tech.
    [
        'line' => '680000', 'system' => 'BAPR', 'date' => '2019', 'scope' => 'whole_line',
        'quote' => 'Depuis 2019, l\'espacement des trains est assuré au moyen du BAPR à compteur d\'essieux.',
    ],
    // Douai - Blanc-Misseron : le BAL prend la place du block Lartigue Nord, section par section.
    [
        'line' => '262000', 'system' => 'BAL', 'date' => '1952', 'scope' => 'section', 'from' => 'Valenciennes', 'to' => 'Beuvrages',
        'quote' => 'le block Lartigue Nord laissant la place au block automatique lumineux (BAL) en 1952 sur le tronc commun entre Valenciennes et Beuvrages, puis en 1956 entre Douai et Somain',
    ],
    [
        'line' => '262000', 'system' => 'BAL', 'date' => '1956', 'scope' => 'section', 'from' => 'Douai', 'to' => 'Somain',
        'quote' => 'le block Lartigue Nord laissant la place au block automatique lumineux (BAL) en 1952 sur le tronc commun entre Valenciennes et Beuvrages, puis en 1956 entre Douai et Somain',
    ],
    // Tours - Saint-Nazaire : BAL et BAPR, mis en service section par section.
    [
        'line' => '515000', 'system' => 'BAL', 'date' => '1978', 'scope' => 'section', 'from' => 'Tours', 'to' => 'Saint-Genouph',
        'quote' => 'La sécurité des circulations des trains est assurée entre Tours (ou Saint-Pierre-des-Corps) et Saint-Genouph par du block automatique lumineux (BAL) mis en service en 1978',
    ],
    [
        'line' => '515000', 'system' => 'BAPR', 'date' => '1978', 'scope' => 'section', 'from' => 'Saint-Genouph', 'to' => 'Saumur',
        'quote' => 'du block automatique à permissivité restreinte (BAPR) entre Saint-Genouph et Angers, mis en service en 1978 de Saint-Genouph à Saumur et en 1974 de Saumur à Angers',
    ],
    [
        'line' => '515000', 'system' => 'BAPR', 'date' => '1974', 'scope' => 'section', 'from' => 'Saumur', 'to' => 'Angers',
        'quote' => 'du block automatique à permissivité restreinte (BAPR) entre Saint-Genouph et Angers, mis en service en 1978 de Saint-Genouph à Saumur et en 1974 de Saumur à Angers',
    ],
    [
        'line' => '515000', 'system' => 'BAL', 'date' => '1975', 'scope' => 'section', 'from' => 'Angers', 'to' => 'La Possonnière',
        'quote' => 'c\'est du block automatique lumineux (BAL) mis en service entre Angers et La Possonnière en 1975, en 1976 entre La Possonnière et Ancenis, en 1977 entre Ancenis et Nantes et en 1986 entre Nantes et Saint-Nazaire',
    ],
    [
        'line' => '515000', 'system' => 'BAL', 'date' => '1976', 'scope' => 'section', 'from' => 'La Possonnière', 'to' => 'Ancenis',
        'quote' => 'c\'est du block automatique lumineux (BAL) mis en service entre Angers et La Possonnière en 1975, en 1976 entre La Possonnière et Ancenis, en 1977 entre Ancenis et Nantes et en 1986 entre Nantes et Saint-Nazaire',
    ],
    [
        'line' => '515000', 'system' => 'BAL', 'date' => '1977', 'scope' => 'section', 'from' => 'Ancenis', 'to' => 'Nantes',
        'quote' => 'c\'est du block automatique lumineux (BAL) mis en service entre Angers et La Possonnière en 1975, en 1976 entre La Possonnière et Ancenis, en 1977 entre Ancenis et Nantes et en 1986 entre Nantes et Saint-Nazaire',
    ],
    [
        'line' => '515000', 'system' => 'BAL', 'date' => '1986', 'scope' => 'section', 'from' => 'Nantes', 'to' => 'Saint-Nazaire',
        'quote' => 'c\'est du block automatique lumineux (BAL) mis en service entre Angers et La Possonnière en 1975, en 1976 entre La Possonnière et Ancenis, en 1977 entre Ancenis et Nantes et en 1986 entre Nantes et Saint-Nazaire',
    ],
    // Saint-Nazaire - Le Croisic : block manuel unifié de voie unique sur toute la ligne.
    [
        'line' => '516000', 'system' => 'BM', 'date' => '1951', 'scope' => 'whole_line',
        'quote' => 'La ligne est cantonnée par téléphone jusqu\'en 1951, où le block-manuel unifié de voie unique (BMVU) est installé sur l\'ensemble de la ligne',
    ],
    // Saint-Germain-des-Fossés - Nîmes : BAL de Riom à Issoire.
    [
        'line' => '790000', 'system' => 'BAL', 'date' => '1984', 'scope' => 'section', 'from' => 'Riom', 'to' => 'Clermont-Ferrand',
        'quote' => 'Le BAL a été mis en service en 1984 sur la section de Riom à Clermont-Ferrand et en 1987 de Clermont-Ferrand à Issoire, celle-ci remplaçant le block manuel',
    ],
    [
        'line' => '790000', 'system' => 'BAL', 'date' => '1987', 'scope' => 'section', 'from' => 'Clermont-Ferrand', 'to' => 'Issoire',
        'quote' => 'Le BAL a été mis en service en 1984 sur la section de Riom à Clermont-Ferrand et en 1987 de Clermont-Ferrand à Issoire, celle-ci remplaçant le block manuel',
    ],
    // Bordeaux - Irun : BAL à la place du block Paul et Ducousso (Landes).
    [
        'line' => '655000', 'system' => 'BAL', 'date' => '1979', 'scope' => 'section', 'from' => 'Morcenx', 'to' => 'Dax',
        'quote' => 'Le block automatique lumineux (BAL) remplace le block Paul et Ducousso entre Lamothe et Dax en commençant par Morcenx - Dax en 1979 et en terminant par Lamothe - Lugos en 1984',
    ],
    [
        'line' => '655000', 'system' => 'BAL', 'date' => '1984', 'scope' => 'section', 'from' => 'Lamothe', 'to' => 'Lugos',
        'quote' => 'Le block automatique lumineux (BAL) remplace le block Paul et Ducousso entre Lamothe et Dax en commençant par Morcenx - Dax en 1979 et en terminant par Lamothe - Lugos en 1984',
    ],
    // Lyon-Saint-Clair - Bourg-en-Bresse : BAL des 20 km remis à double voie.
    [
        'line' => '886000', 'system' => 'BAL', 'date' => '2008-08', 'scope' => 'section', 'from' => 'Les Échets', 'to' => 'Villars-les-Dombes',
        'quote' => 'Le BAL à cantons longs (2,400 m) est installé depuis fin août 2008 sur les 20 km remis à double voie entre Les Échets et Villars-les-Dombes',
    ],
    // Bondy - Aulnay-sous-Bois : BAL avec l'électrification de Bondy à Gargan.
    [
        'line' => '958000', 'system' => 'BAL', 'date' => '1962-05-22', 'scope' => 'section', 'from' => 'Bondy', 'to' => 'Gargan',
        'quote' => 'Le 22 mai 1962, dans le cadre du programme d\'électrification du réseau Est, la section Bondy - Gargan est mise sous tension en 25 kV - 50Hz, avec introduction du block automatique lumineux entre Bondy et Gargan',
    ],
    // Paris-Montparnasse - Brest : BAL de Paris au Mans avec l'électrification de 1937 (article de la gare).
    [
        'line' => '420000', 'article' => 'Gare de Paris-Montparnasse', 'system' => 'BAL', 'date' => '1937', 'scope' => 'section', 'from' => 'Paris-Montparnasse', 'to' => 'Le Mans',
        'quote' => 'l\'électrification de la ligne entre Le Mans et Paris en 1500 volts, qui est aussi l\'occasion d\'installer la signalisation par block automatique lumineux, est mise en service le 15 mai 1937',
    ],
    // Lyon - Genève : BAL d'Ambérieu à Culoz (article de la gare de Culoz).
    [
        'line' => '890000', 'article' => 'Gare de Culoz', 'system' => 'BAL', 'date' => '1967', 'scope' => 'section', 'from' => 'Ambérieu', 'to' => 'Culoz',
        'quote' => 'En 1967, le block automatique lumineux (BAL) est mis en service entre Ambérieu et Culoz en conséquence de l\'augmentation du trafic.',
    ],
    // Aix-les-Bains - Annemasse : BAL de 1993 (article d'une gare de la ligne).
    [
        'line' => '897000', 'article' => 'Gare de Lovagny-Gorges-du-Fier', 'system' => 'BAL', 'date' => '1993', 'scope' => 'unspecified',
        'quote' => 'En 1993, à la suite de la mise en service du Block automatique lumineux (BAL), la gare est fermée au service des voyageurs',
    ],
    // Vierzon - Saincaize : BAL de 1988, à la place du block Lartigue (article de la gare de Bengy).
    [
        'line' => '690000', 'article' => 'Gare de Bengy', 'system' => 'BAL', 'date' => '1988', 'scope' => 'unspecified',
        'quote' => 'En 1988, mise en service du Block automatique lumineux en remplacement du Block Lartigues.',
    ],
    // Saint-Agne - Auch : BAPR de la section L'Isle-Jourdain - Auch (article de la gare de Gimont-Cahuzac).
    [
        'line' => '648000', 'article' => 'Gare de Gimont-Cahuzac', 'system' => 'BAPR', 'date' => '2010', 'scope' => 'section', 'from' => 'L\'Isle-Jourdain', 'to' => 'Auch',
        'quote' => 'En 2010, la gare est équipée du système de signalisation Block automatique à permissivité restreinte (BAPR) dans le cadre de l\'équipement de l\'ensemble de la ligne',
    ],
    // Relevé du 23/11 (signalisation-classique) : articles de lignes classiques, phrases datées.
    // Longueau - Boulogne : premier BAL du Nord, Amiens - Saint-Roch.
    [
        'line' => '311000', 'system' => 'BAL', 'date' => '1937', 'scope' => 'section', 'from' => 'Amiens', 'to' => 'Saint-Roch',
        'quote' => 'En 1937, peu avant sa dissolution, la Compagnie du Nord met en place le block automatique lumineux sur la courte section d\'Amiens à la bifurcation de Saint-Roch',
    ],
    // Boulogne - Calais : BAL de Boulogne-Ville à Wimille.
    [
        'line' => '314000', 'system' => 'BAL', 'date' => '1980', 'scope' => 'section', 'from' => 'Boulogne-Ville', 'to' => 'Wimille',
        'quote' => 'En 1980, il est étendu jusqu\'à Wimille - Wimereux, tandis que le block automatique lumineux (BAL) est installé de Boulogne-Ville à Wimille.',
    ],
    // Sainte-Pazanne - Pornic : BAPR à compteurs d'essieux.
    [
        'line' => '536000', 'system' => 'BAPR', 'date' => '2010', 'scope' => 'whole_line',
        'quote' => 'Le block automatique à permissivité restreinte (BAPR) de régime de voie banalisée par compteurs d\'essieux équipe la ligne depuis 2010.',
    ],
    // Thionville - Apach : BAL au jour près.
    [
        'line' => '178000', 'system' => 'BAL', 'date' => '1988-05-24', 'scope' => 'section', 'from' => 'Thionville', 'to' => 'Apach',
        'quote' => 'le 24 mai 1988, la ligne est dotée du block automatique lumineux (BAL) de Thionville à Apach',
    ],
    // Tours - Le Mans : BAPR à la place du block manuel de double voie.
    [
        'line' => '561000', 'system' => 'BAPR', 'date' => '2010-06-13', 'scope' => 'section', 'from' => 'Arnage', 'to' => 'La Membrolle-sur-Choisille',
        'quote' => 'est remplacé par le block automatique à permissivité restreinte à comptage d\'essieux le 13 juin 2010.',
    ],
    // Clisson - Cholet : BAPR après la régénération.
    [
        'line' => '527000', 'system' => 'BAPR', 'date' => '2021-03-29', 'scope' => 'whole_line',
        'quote' => 'La mise en service du BAPR est effective le 29 mars 2021',
    ],
    // Saint-Benoît - La Rochelle : BAPR avec l'électrification pour le TGV.
    [
        'line' => '538000', 'system' => 'BAPR', 'date' => '1993', 'scope' => 'whole_line',
        'quote' => 'électrification en 25 kV 50 Hz et mise en place du BAPR et d\'une commande centralisée de voie unique à Niort en remplacement du block manuel.',
    ],
    // Mâcon - Ambérieu : BAL en gares pour le passage des TGV.
    [
        'line' => '883000', 'system' => 'BAL', 'date' => '1981-07-16', 'scope' => 'unspecified',
        'quote' => 'le 16 juillet 1981, pour permettre la circulation des TGV par le LGV, mise en service du BAL en gares de Pont-de-Veyle et de Vonnas',
    ],
    // Ermont - Champ-de-Mars : BAL de Saint-Ouen à Champ-de-Mars (l'année est celle de la phrase).
    [
        'line' => '962000', 'system' => 'BAL', 'date' => '1988', 'scope' => 'section', 'from' => 'Saint-Ouen', 'to' => 'Champ-de-Mars',
        'quote' => 'est mise sous tension le 21 juin 1988 ; trois jours après, le tronçon Saint-Ouen - Champ-de-Mars voit la mise en service du BAL et des IPCS',
    ],
    // Dijon - Vallorbe : BAL à la place du block manuel unifié.
    [
        'line' => '850000', 'system' => 'BAL', 'date' => '1976', 'scope' => 'unspecified',
        'quote' => 'L\'espacement des trains est assuré par le BAL mis en service en 1976 en remplacement d\'un block manuel unifié SNCF.',
    ],
];
