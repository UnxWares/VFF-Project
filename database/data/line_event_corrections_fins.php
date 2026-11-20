<?php

/**
 * Corrections SOURCÉES : fins d'exploitation non datées (invariant
 * lines.km_drop_without_event de vff:check-invariants, audit du 2026-10-19).
 *
 * Symptôme : une ligne garde ses km « en service » jusqu'en 2020 puis les perd en 2030
 * (statut SNCF actuel Neutralisée / Fermée, ou voie disparue) sans aucune fermeture datée
 * entre les deux. Cause la plus fréquente : la fermeture manque, ou elle est masquée par
 * l'arbitrage du moteur (EraTimeline::dedupe) : sur une portion, la source la plus fiable
 * qui parle des FERMETURES (famille closure, closure_passenger, closure_freight) masque les
 * autres sources pour cette famille. Une fermeture au fret par section (Wikipédia) masque
 * ainsi la fermeture aux voyageurs « toute la ligne », et la portion reste « voyageurs »
 * jusqu'en 2020.
 *
 * D'où la règle de saisie de ce fichier : nos ajouts sont de source « manual » (la plus
 * fiable), donc pour chaque portion où l'on ajoute une fermeture, on redonne la
 * chronologie de fin COMPLÈTE de la portion (voyageurs, fret, totale), même si une partie
 * est déjà en base ; une fermeture de toute la ligne sur une ligne qui a des fermetures par
 * section porte les extrémités de la ligne (from / to), sinon elle serait elle-même masquée.
 *
 * Mêmes champs et mêmes actions que database/data/line_event_corrections.php (lu avec lui
 * par vff:apply-event-corrections), plus reuse et heritage_kind pour un ajout. Toujours une
 * source vérifiable (permalien Wikipédia avec oldid, fichier lignes-oubliees), une citation
 * exacte et une raison. Pas de tiret long dans les textes.
 *
 * @return list<array<string, mixed>>
 */
return [
    // #3148 Ligne d'Aulnay-sous-Bois à Senlis : tracé de la ligne inachevée d'Aulnay-sous-Bois à Verberie (jamais ouverte) : l'ouverture OHM de 1875 est celle de la ligne de Bondy à Aulnay-sous-Bois.
    [
        'line' => '3148',
        'action' => 'neutralize',
        'match' => ['source' => 'ohm', 'type' => 'opening', 'date' => '1875-08-07', 'from' => 'Rougemont Chanteloup'],
        'label' => 'Ouverture de la ligne de Bondy à Aulnay-sous-Bois (autre ligne)',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_d%27Aulnay-sous-Bois_%C3%A0_Verberie&oldid=235341551',
        'quote' => 'Abandonnés pendant la Première Guerre mondiale, les travaux de sa construction ne reprirent jamais, la priorité étant alors donnée à la reconstruction du réseau d\'avant-guerre.',
        'reason' => 'Ouverture de la ligne de Bondy à Aulnay-sous-Bois (way OHM 201202790 nommé « Ligne de Bondy à Aulnay-sous-Bois », Rougemont-Chanteloup est un arrêt de cette ligne), pas de la ligne d\'Aulnay à Verberie qui n\'a jamais été ouverte.',
    ],

    // 3171 Ligne de Biarritz-la-Negresse à Biarritz-Ville (reconstruit depuis la base du 28/10/2026 ; citation d'origine perdue, source = celle de l'événement)
    [
        'line' => '3171',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '1907-01-01'],
        'label' => 'Date d\'infobox non sourcée (ouverture réelle en 1911)',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Biarritz-la-N%C3%A9gresse_%C3%A0_Biarritz-Ville&oldid=238796058',
        'quote' => '',
        'reason' => 'année d\'infobox non sourcée, contredite par l\'article de la gare terminus (embranchement ouvert le 28 février 1911, gare mise en service en 1911)',
    ],

    // 3171 Ligne de Biarritz-la-Negresse à Biarritz-Ville (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3171',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1911-02-28',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Gare_de_Biarritz-Ville&oldid=235492084',
        'quote' => 'Afin de mieux desservir le centre-ville de Biarritz, un embranchement de 3,5 kilomètres est ouvert le 28 février 1911 entre la gare de la Négresse (actuelle gare de Biarritz)',
        'reason' => 'ouverture de toute la ligne, l\'infobox de l\'article de la ligne donnant 1907 sans source',
    ],

    // 3171 Ligne de Biarritz-la-Negresse à Biarritz-Ville (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3171',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1980-09-14',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Gare_de_Biarritz-Ville&oldid=235492084',
        'quote' => 'conduisent au report du chantier auto-couchettes à la gare de La Négresse et à la fermeture totale de l\'antenne le 14 septembre 1980',
        'reason' => 'fin de tout trafic (navettes voyageurs et trains auto-couchettes) sur toute la ligne',
    ],

    // 3171 Ligne de Biarritz-la-Negresse à Biarritz-Ville (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3171',
        'action' => 'add',
        'type' => 'declassification',
        'date' => '1981-01-01',
        'precision' => 'year',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Biarritz-la-N%C3%A9gresse_%C3%A0_Biarritz-Ville&oldid=238796058',
        'quote' => 'Infobox : concessionnaireact = Ligne déclassée ; dateconcact = 1981',
        'reason' => 'déclassement de toute la ligne',
    ],

    // #3192 Ligne de Charmes à Rambervillers : le trafic cesse dès 1939 (la « disparition » de 1938 est celle de la compagnie) ; la fermeture au fret de 1935 par section masquait toute fin du service voyageurs.
    [
        'line' => '3192',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1939-01-01',
        'precision' => 'year',
        'from' => 'Charmes',
        'to' => 'Rambervillers',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Compagnie_du_chemin_de_fer_de_Rambervillers_%C3%A0_Charmes&oldid=215322399',
        'quote' => 'Mais l\'intégration de la ligne au réseau SNCF est de courte durée : dès 1939, le trafic y cesse',
        'reason' => 'Fin de tout trafic sur toute la ligne en 1939.',
    ],

    // 3280 Ligne de Pont-de-la-Deûle à Orchies (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3280',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1883-12-20',
        'precision' => 'day',
        'from' => 'Pont-de-la-Deûle',
        'to' => 'Orchies',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Pont-de-la-De%C3%BBle_%C3%A0_Bachy_-_Mouchin&oldid=233971942',
        'quote' => 'La ligne sera inaugurée le 20 décembre 1883.',
        'reason' => 'Remplace l\'ouverture OHM de 1866, antérieure à la concession de 1875.',
    ],

    // 3280 Ligne de Pont-de-la-Deûle à Orchies (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3280',
        'action' => 'add',
        'type' => 'declassification',
        'date' => '1967-10-19',
        'precision' => 'day',
        'from' => 'Pont-de-la-Deûle',
        'to' => 'Râches',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Pont-de-la-De%C3%BBle_%C3%A0_Bachy_-_Mouchin&oldid=233971942',
        'quote' => 'De Pont-de-la-Deûle à Râches (PK 221,700 à 224,530), le 19 octobre 1967.',
        'reason' => 'Déclassement de la section Pont-de-la-Deûle - Râches (JO du 5 novembre 1967).',
    ],

    // 3280 Ligne de Pont-de-la-Deûle à Orchies (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3280',
        'action' => 'add',
        'type' => 'declassification',
        'date' => '1980-04-23',
        'precision' => 'day',
        'from' => 'Râches',
        'to' => 'Orchies',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Pont-de-la-De%C3%BBle_%C3%A0_Bachy_-_Mouchin&oldid=233971942',
        'quote' => 'De Râches à Orchies (PK 224,530 à 236,380), le 23 avril 1980.',
        'reason' => 'Déclassement de la section Râches - Orchies (JO du 27 avril 1980).',
    ],

    // 3383 Ligne d'Albi à Alban (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3383',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1909-01-01',
        'precision' => 'day',
        'from' => 'Albi',
        'to' => 'Saint-Juéry',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_d%27Albi_%C3%A0_Alban&oldid=224395291',
        'quote' => 'La ligne est mise en service le 1er janvier 1909 entre la gare d\'Albi-Orléans et Saint-Juéry',
        'reason' => 'Ouverture manquante en base pour la première section (portions non datées du tracé).',
    ],

    // 3383 Ligne d'Albi à Alban (reconstruit depuis la base du 28/10/2026 ; citation d'origine perdue, source = celle de l'événement)
    [
        'line' => '3383',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '1910-01-01', 'from' => 'Saint-Juéry'],
        'label' => 'Date d\'ouverture lue à l\'année (14 février 1910)',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_d%27Albi_%C3%A0_Alban&oldid=224395291',
        'quote' => '',
        'reason' => 'Date mal lue (année seule) alors que la source donne le 14 février 1910 ; remplacé par l\'événement manual 1910-02-14.',
    ],

    // 3383 Ligne d'Albi à Alban (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3383',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1910-02-14',
        'precision' => 'day',
        'from' => 'Saint-Juéry',
        'to' => 'Alban',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_d%27Albi_%C3%A0_Alban&oldid=224395291',
        'quote' => 'Le 14 février 1910, elle est prolongée de Saint-Juéry à Alban.',
        'reason' => 'Date exacte de l\'ouverture du prolongement (la base n\'a que l\'année 1910).',
    ],

    // 3383 Ligne d'Albi à Alban (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3383',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1939-03-31',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemins_de_fer_d%C3%A9partementaux_du_Tarn&oldid=230654538',
        'quote' => 'Le réseau Nord disparait le 31 mars 1939 et le réseau Sud le 31 décembre 1962.',
        'reason' => 'Albi - Alban appartient au groupe Nord des CFD du Tarn : fin de tout trafic le 31 mars 1939.',
    ],

    // 3421 Ligne d'Aubréville à Apremont-sur-Aire (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3421',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1930-08-01',
        'precision' => 'month',
        'from' => 'Varennes-en-Argonne',
        'to' => 'Aubréville',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_d%27Apremont-sur-Aire_%C3%A0_Aubr%C3%A9ville&oldid=235341537',
        'quote' => 'La desserte voyageurs est fermée en août 1930, celle des marchandises le 1er février 1937.',
        'reason' => 'Fin du service voyageurs sur toute la ligne (chronologie de fin complète).',
    ],

    // 3421 Ligne d'Aubréville à Apremont-sur-Aire (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3421',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1937-02-01',
        'precision' => 'day',
        'from' => 'Varennes-en-Argonne',
        'to' => 'Aubréville',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_d%27Apremont-sur-Aire_%C3%A0_Aubr%C3%A9ville&oldid=235341537',
        'quote' => 'La desserte voyageurs est fermée en août 1930, celle des marchandises le 1er février 1937.',
        'reason' => 'Fin du fret, dernier trafic de la ligne.',
    ],

    // 3450 Ligne d'Oloron à Mauléon (reconstruit depuis la base du 28/10/2026 ; citation d'origine perdue, source = celle de l'événement)
    [
        'line' => '3450',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '1901-01-01', 'from' => 'Oloron'],
        'label' => 'Ouverture d\'une autre ligne du réseau de Pau-Oloron-Mauléon',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_Pau-Oloron-Maul%C3%A9on&oldid=235512001',
        'quote' => '',
        'reason' => 'ouverture de la ligne Oloron - Sauveterre-de-Béarn, autre ligne du réseau POM, appliquée à tort à Oloron - Mauléon (ouverte le 1er mai 1904)',
    ],

    // 3450 Ligne d'Oloron à Mauléon (reconstruit depuis la base du 28/10/2026 ; citation d'origine perdue, source = celle de l'événement)
    [
        'line' => '3450',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '1902-01-01'],
        'label' => 'Ouverture d\'une autre ligne du réseau de Pau-Oloron-Mauléon',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_Pau-Oloron-Maul%C3%A9on&oldid=235512001',
        'quote' => '',
        'reason' => 'ouvertures des lignes Pau - Lembeye, Pau - Pontacq et Pau - Monein (autres lignes du réseau POM), sans rapport avec Oloron - Mauléon ; le match sans « from » vise les trois événements de cette date',
    ],

    // 3450 Ligne d'Oloron à Mauléon (reconstruit depuis la base du 28/10/2026 ; citation d'origine perdue, source = celle de l'événement)
    [
        'line' => '3450',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '1902-01-01', 'from' => 'Pau'],
        'label' => 'Ouverture d\'une autre ligne du réseau de Pau-Oloron-Mauléon',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_Pau-Oloron-Maul%C3%A9on&oldid=235512001',
        'quote' => '',
        'reason' => 'ouvertures des lignes Pau - Lembeye, Pau - Pontacq et Pau - Monein (autres lignes du réseau POM), sans rapport avec Oloron - Mauléon ; le match sans « from » vise les trois événements de cette date',
    ],

    // 3450 Ligne d'Oloron à Mauléon (reconstruit depuis la base du 28/10/2026 ; citation d'origine perdue, source = celle de l'événement)
    [
        'line' => '3450',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '1902-01-01', 'from' => 'Pau'],
        'label' => 'Ouverture d\'une autre ligne du réseau de Pau-Oloron-Mauléon',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_Pau-Oloron-Maul%C3%A9on&oldid=235512001',
        'quote' => '',
        'reason' => 'ouvertures des lignes Pau - Lembeye, Pau - Pontacq et Pau - Monein (autres lignes du réseau POM), sans rapport avec Oloron - Mauléon ; le match sans « from » vise les trois événements de cette date',
    ],

    // 3450 Ligne d'Oloron à Mauléon (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3450',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1905-09-01',
        'precision' => 'day',
        'from' => 'Lanne-en-Barétous',
        'to' => 'Tardets-Sorholus',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Lanne-en-Bar%C3%A9tous&oldid=239945563',
        'quote' => 'Le 1er septembre 1905 eut lieu l\'ouverture de la section Lanne-en-Barétous-Tardets de la ligne Oloron-Mauléon',
        'reason' => 'ouverture de la section Lanne - Tardets, après Oloron - Lanne le 1er mai 1904 (événement déjà en base)',
    ],

    // 3450 Ligne d'Oloron à Mauléon (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3450',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1931-01-01',
        'precision' => 'year',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_Pau-Oloron-Maul%C3%A9on&oldid=235512001',
        'quote' => 'Ces lignes disparaissent entre le 31 décembre 1930 et le 31 décembre 1931.',
        'reason' => 'fin de tout trafic sur toute la ligne avec la suppression du réseau Pau-Oloron-Mauléon (année retenue 1931, la source donnant un intervalle du 31 décembre 1930 au 31 décembre 1931)',
    ],

    // 3641 Ligne de Foulain à Nogent-en-Bassigny (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3641',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1947-03-31',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Nogent_%28Haute-Marne%29&oldid=235571480',
        'quote' => 'Le conseil général de Haute-Marne décide le 30 avril 1946 le démantèlement de cette ligne départementale et l\'arrêt de l\'exploitation qui intervient le 31 mars 1947.',
        'reason' => 'Fin de tout trafic de la ligne métrique le 31 mars 1947.',
    ],

    // 3686 Ligne de Herzeele à Saint-Momelin (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3686',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1910-06-23',
        'precision' => 'day',
        'from' => 'Herzeele',
        'to' => 'Esquelbecq',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_Herzeele_%C3%A0_Saint-Momelin&oldid=223550042',
        'quote' => 'La ligne est mise en service en traction vapeur le 23 juin 1910 entre la gare d\'Herzeele et la gare d\'Esquelbecq (nouvelle section).',
        'reason' => 'Ouverture de la première section, absente de la base.',
    ],

    // 3686 Ligne de Herzeele à Saint-Momelin (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3686',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1951-01-01',
        'precision' => 'year',
        'from' => 'Herzeele',
        'to' => 'Saint-Momelin',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Gare_de_Saint-Momelin&oldid=217417297',
        'quote' => 'Elle est fermée en 1951 lors de la suppression de la ligne.',
        'reason' => 'Suppression de toute la ligne en 1951 (gares de Wormhout, Zegerscappel, Bollezeele, Lederzeele, Saint-Momelin fermées en 1951).',
    ],

    // 3786 Ligne de Lyon-Est à Aoste-Saint-Génix (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3786',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1881-10-23',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_l%27Est_de_Lyon&oldid=240183241',
        'quote' => 'De Lyon (gare de l\'Est) à Saint-Génix-d\'Aoste le 23 octobre 1881. D\'une longueur de 72 km et comportant 21 gares ;',
        'reason' => 'Ouverture de toute la ligne de Lyon (gare de l\'Est) à Saint-Génix-d\'Aoste.',
    ],

    // 3786 Ligne de Lyon-Est à Aoste-Saint-Génix (reconstruit depuis la base du 28/10/2026 ; citation d'origine perdue, source = celle de l'événement)
    [
        'line' => '3786',
        'action' => 'neutralize',
        'match' => ['source' => 'estimation-reseau', 'type' => 'opening', 'date' => '1893-01-01'],
        'label' => 'Estimation d\'un autre réseau (VFD), sans rapport avec cette ligne',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=VFD_%28entreprise%29&oldid=240014065',
        'quote' => '',
        'reason' => 'Estimation tirée du réseau VFD (Jarrie - Vizille, 1893) : le CFEL n\'est pas un réseau VFD (les VFD n\'ont repris en 1939 que le service voyageurs, par la route) ; ouverture réelle le 23/10/1881.',
    ],

    // 3786 Ligne de Lyon-Est à Aoste-Saint-Génix (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3786',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1947-12-31',
        'precision' => 'day',
        'from' => 'Lyon-Est',
        'to' => 'Saint-Genix d\'Aoste',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_l%27Est_de_Lyon&oldid=240183241',
        'quote' => 'trafic voyageur (ensemble du réseau) | 31 décembre 1947',
        'reason' => 'Fin définitive du service voyageurs sur tout le réseau CFEL.',
    ],

    // 3786 Ligne de Lyon-Est à Aoste-Saint-Génix (reconstruit depuis la base du 28/10/2026 ; citation d'origine perdue, source = celle de l'événement)
    [
        'line' => '3786',
        'action' => 'neutralize',
        'match' => ['source' => 'estimation-reseau', 'type' => 'closure', 'date' => '1951-01-01'],
        'label' => 'Estimation d\'un autre réseau (VFD), sans rapport avec cette ligne',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=VFD_%28entreprise%29&oldid=240014065',
        'quote' => '',
        'reason' => 'Estimation tirée du réseau VFD (Grenoble - Uriage, 1951), sans rapport avec cette ligne ; fermetures réelles par sections de 1956 à 2003.',
    ],

    // 3786 Ligne de Lyon-Est à Aoste-Saint-Génix (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3786',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1956-05-05',
        'precision' => 'day',
        'from' => 'Morestel',
        'to' => 'Saint-Genix d\'Aoste',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_l%27Est_de_Lyon&oldid=240183241',
        'quote' => 'Morestel à Saint-Genix d\'Aoste | 5 mai 1956 (fret) | 11 février 1957',
        'reason' => 'Fin du fret, donc de tout trafic, entre Morestel et Saint-Genix-d\'Aoste.',
    ],

    // 3786 Ligne de Lyon-Est à Aoste-Saint-Génix (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3786',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1956-05-05',
        'precision' => 'day',
        'from' => 'Morestel',
        'to' => 'Saint-Genix d\'Aoste',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_l%27Est_de_Lyon&oldid=240183241',
        'quote' => 'Morestel à Saint-Genix d\'Aoste | 5 mai 1956 (fret) | 11 février 1957',
        'reason' => 'Voyageurs déjà supprimés en 1947 : dernier trafic le 5 mai 1956.',
    ],

    // 3786 Ligne de Lyon-Est à Aoste-Saint-Génix (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3786',
        'action' => 'add',
        'type' => 'declassification',
        'date' => '1957-02-11',
        'precision' => 'day',
        'from' => 'Morestel',
        'to' => 'Saint-Genix d\'Aoste',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_l%27Est_de_Lyon&oldid=240183241',
        'quote' => 'Morestel à Saint-Genix d\'Aoste | 5 mai 1956 (fret) | 11 février 1957',
        'reason' => 'Déclassement de la section le 11 février 1957 (colonne « déclassement de la ligne »).',
    ],

    // 3786 Ligne de Lyon-Est à Aoste-Saint-Génix (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3786',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1960-03-31',
        'precision' => 'day',
        'from' => 'Soleymieu - Sablonnières',
        'to' => 'Morestel',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_l%27Est_de_Lyon&oldid=240183241',
        'quote' => 'Soleymieu - Sablonnières à Morestel | 31 mars 1960 (fret) | 3 juillet 1961',
        'reason' => 'Fin du fret entre Soleymieu-Sablonnières et Morestel (le service est alors limité à Bouvesse, sur l\'antenne de Montalieu).',
    ],

    // 3786 Ligne de Lyon-Est à Aoste-Saint-Génix (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3786',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1960-03-31',
        'precision' => 'day',
        'from' => 'Soleymieu - Sablonnières',
        'to' => 'Morestel',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_l%27Est_de_Lyon&oldid=240183241',
        'quote' => 'Soleymieu - Sablonnières à Morestel | 31 mars 1960 (fret) | 3 juillet 1961',
        'reason' => 'Dernier trafic de la section le 31 mars 1960.',
    ],

    // 3786 Ligne de Lyon-Est à Aoste-Saint-Génix (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3786',
        'action' => 'add',
        'type' => 'declassification',
        'date' => '1961-07-03',
        'precision' => 'day',
        'from' => 'Soleymieu - Sablonnières',
        'to' => 'Morestel',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_l%27Est_de_Lyon&oldid=240183241',
        'quote' => 'Soleymieu - Sablonnières à Morestel | 31 mars 1960 (fret) | 3 juillet 1961',
        'reason' => 'Déclassement de la section le 3 juillet 1961.',
    ],

    // 3786 Ligne de Lyon-Est à Aoste-Saint-Génix (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3786',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1987-06-25',
        'precision' => 'day',
        'from' => 'Meyzieu-ZI',
        'to' => 'Soleymieu - Sablonnières',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_l%27Est_de_Lyon&oldid=240183241',
        'quote' => 'Meyzieu-ZI à Bouvesse | 25 juin 1987 (fret)',
        'reason' => 'Dernier trafic de la section (voyageurs supprimés depuis 1947).',
    ],

    // 3786 Ligne de Lyon-Est à Aoste-Saint-Génix (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3786',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1987-06-25',
        'precision' => 'day',
        'from' => 'Meyzieu-ZI',
        'to' => 'Soleymieu - Sablonnières',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_l%27Est_de_Lyon&oldid=240183241',
        'quote' => 'Meyzieu-ZI à Bouvesse | 25 juin 1987 (fret)',
        'reason' => 'Fin du fret de Meyzieu-ZI à Bouvesse ; sur cette ligne la portion concernée va de Meyzieu-ZI à Soleymieu-Sablonnières (Bouvesse est sur l\'antenne de Montalieu). Suppression décidée par l\'Isère, effective le 30 juin 1987.',
    ],

    // 3786 Ligne de Lyon-Est à Aoste-Saint-Génix (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3786',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1999-01-01',
        'precision' => 'year',
        'from' => 'Décines',
        'to' => 'Meyzieu-ZI',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_l%27Est_de_Lyon&oldid=240183241',
        'quote' => 'Le département du Rhône confie alors l\'exploitation à la SNCF, de la section de ligne, Lyon -Meyzieu Z.I., soit 15 km, jusqu\'en 1999, époque où la SNCF n\'exploite plus que le tronçon Lyon Part-Dieu - Décines',
        'reason' => 'Fin de tout trafic sur Décines - Meyzieu-ZI en 1999.',
    ],

    // 3786 Ligne de Lyon-Est à Aoste-Saint-Génix (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3786',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1999-01-01',
        'precision' => 'year',
        'from' => 'Décines',
        'to' => 'Meyzieu-ZI',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_l%27Est_de_Lyon&oldid=240183241',
        'quote' => 'Décines - Meyzieu-ZI | 1999 (fret)',
        'reason' => 'Fin du fret entre Décines et Meyzieu-ZI en 1999.',
    ],

    // 3786 Ligne de Lyon-Est à Aoste-Saint-Génix (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3786',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '2003-12-01',
        'precision' => 'month',
        'from' => 'Lyon-Est',
        'to' => 'Décines',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_l%27Est_de_Lyon&oldid=240183241',
        'quote' => 'Lyon-Part-Dieu - Décines | Décembre 2003',
        'reason' => 'Fin du trafic entre Lyon et Décines en décembre 2003 ; la source dit « Lyon-Part-Dieu », raccordement par lequel la ligne était exploitée, Lyon-Est étant la tête de ligne de ce tracé.',
    ],

    // 3786 Ligne de Lyon-Est à Aoste-Saint-Génix (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3786',
        'action' => 'add',
        'type' => 'closure',
        'date' => '2003-12-01',
        'precision' => 'month',
        'from' => 'Lyon-Est',
        'to' => 'Décines',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_l%27Est_de_Lyon&oldid=240183241',
        'quote' => 'Après une fin d\'exploitation progressive jusqu\'en décembre 2003, l\'emprise de la ligne comprise entre la gare de Lyon-Part-Dieu et Meyzieu Z.I. a été transformée pour permettre d\'y faire circuler la ligne T3 du tramway de Lyon',
        'reason' => 'Dernier trafic ferroviaire en décembre 2003.',
    ],

    // 3786 Ligne de Lyon-Est à Aoste-Saint-Génix (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3786',
        'action' => 'add',
        'type' => 'conversion',
        'date' => '2006-12-05',
        'precision' => 'day',
        'from' => 'Lyon-Est',
        'to' => 'Meyzieu-ZI',
        'reuse' => 'urban',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_3_du_tramway_de_Lyon&oldid=239130788',
        'quote' => 'La ligne 3 du tramway de Lyon, plus simplement nommée T3, également appelée LEA (pour Ligne de l\'Est de l\'Agglomération), est une ligne de tramway de la métropole de Lyon ouverte le 5 décembre 2006 entre la gare de la Part-Dieu à Lyon et la zone industrielle de Meyzieu',
        'reason' => 'Réemploi de l\'emprise Lyon - Meyzieu Z.I. par le tramway T3 le 5 décembre 2006 (pas une ouverture ferroviaire).',
    ],

    // 3816 Ligne de Milly-sur-Thérain à Formerie (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3816',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1935-12-31',
        'precision' => 'day',
        'from' => 'Milly-sur-Thérain',
        'to' => 'Formerie',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Gare_de_Formerie&oldid=240286506',
        'quote' => 'La ligne de Milly-sur-Thérain à Formerie ferme à tout trafic le 31 décembre 1935, et son service est transféré sur route par le conseil général de l\'Oise.',
        'reason' => 'Fin de tout trafic sur toute la ligne.',
    ],

    // 3867 Ligne de Nice à Bendejun (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3867',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1900-06-02',
        'precision' => 'day',
        'from' => 'Nice',
        'to' => 'Contes',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Tramway_de_Nice_et_du_Littoral&oldid=240449863',
        'quote' => 'Nice - Contes, le 2 juin 1900 ;',
        'reason' => 'Ouverture du tramway de Nice à Contes (liste des ouvertures des TNL).',
    ],

    // 3867 Ligne de Nice à Bendejun (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3867',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1909-02-01',
        'precision' => 'day',
        'from' => 'Contes',
        'to' => 'Bendejun',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Tramway_de_Nice_et_du_Littoral&oldid=240449863',
        'quote' => 'Contes - Bendejun, ouverte le 1er février 1909 ;',
        'reason' => 'Ouverture du prolongement départemental de Contes à Bendejun.',
    ],

    // 3867 Ligne de Nice à Bendejun (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3867',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1934-01-01',
        'precision' => 'year',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Tramway_de_Nice_et_du_Littoral&oldid=240449863',
        'quote' => 'Les lignes côtières souffrent de la forte concurrence des véhicules routiers. Elles disparaissent dès 1929. La totalité du réseau suburbain est supprimée en 1934.',
        'reason' => 'Suppression de tout le réseau suburbain TNL, dont Nice - Contes - Bendejun, en 1934.',
    ],

    // 3867 Ligne de Nice à Bendejun (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3867',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1950-01-01',
        'precision' => 'year',
        'from' => 'Nice',
        'to' => 'Contes',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Tramway_de_Nice_et_du_Littoral&oldid=240449863',
        'quote' => '1950 : ligne de Contes et ligne 6 ;',
        'reason' => 'Fermeture définitive de la ligne de Contes en 1950, après sa réouverture pendant la Seconde Guerre mondiale.',
    ],

    // 3867 Ligne de Nice à Bendejun (reconstruit depuis la base du 28/10/2026 ; citation d'origine perdue, source = celle de l'événement)
    [
        'line' => '3867',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia-reseaux', 'type' => 'closure', 'date' => '1953-01-10'],
        'label' => 'Fin du réseau urbain TNL (autre ligne)',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Tramway_de_Nice_et_du_Littoral&oldid=240449863',
        'quote' => '',
        'reason' => 'Fin de tout le réseau TNL (dernier tram, ligne 7 urbaine), pas de cette ligne fermée en 1934 puis 1950. Événement vu en base le 19/10/2026 puis disparu : à ne pas réimporter.',
    ],

    // 3870 Ligne de Nice à Menton (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3870',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1900-02-01',
        'precision' => 'day',
        'from' => 'Nice',
        'to' => 'Villefranche-sur-Mer',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Tramway_de_Nice_et_du_Littoral&oldid=240449863',
        'quote' => 'Place Masséna - Villefranche-sur-Mer, le 1er février 1900 ;',
        'reason' => 'Ouverture du tramway de la place Masséna à Villefranche-sur-Mer.',
    ],

    // 3870 Ligne de Nice à Menton (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3870',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1900-06-03',
        'precision' => 'day',
        'from' => 'Villefranche-sur-Mer',
        'to' => 'Beaulieu-sur-Mer',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Tramway_de_Nice_et_du_Littoral&oldid=240449863',
        'quote' => 'Nice - Beaulieu, le 3 juin 1900 ;',
        'reason' => 'Ouverture de Nice - Beaulieu, donc du prolongement Villefranche - Beaulieu.',
    ],

    // 3870 Ligne de Nice à Menton (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3870',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1903-11-07',
        'precision' => 'day',
        'from' => 'Beaulieu-sur-Mer',
        'to' => 'Monaco',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Tramway_de_Nice_et_du_Littoral&oldid=240449863',
        'quote' => 'Cette ligne relie Nice, Villefranche, Beaulieu, Monaco et Monte-Carlo par un tracé établi sur la route en corniche. Elle est ouverte le 7 novembre 1903.',
        'reason' => 'Ouverture de la ligne de la Corniche jusqu\'à Monaco et Monte-Carlo ; Nice - Beaulieu étant ouvert depuis 1900, la partie nouvelle est Beaulieu - Monaco.',
    ],

    // 3870 Ligne de Nice à Menton (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3870',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1903-12-20',
        'precision' => 'day',
        'from' => 'Monaco',
        'to' => 'Menton',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Tramway_de_Nice_et_du_Littoral&oldid=240449863',
        'quote' => 'Un prolongement de Monaco à Menton est ouvert le 20 décembre 1903.',
        'reason' => 'Ouverture du prolongement de Monaco à Menton.',
    ],

    // 3870 Ligne de Nice à Menton (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3870',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1929-01-01',
        'precision' => 'year',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Tramway_de_Nice_et_du_Littoral&oldid=240449863',
        'quote' => 'Les lignes côtières souffrent de la forte concurrence des véhicules routiers. Elles disparaissent dès 1929.',
        'reason' => 'Fin des tramways des lignes côtières, dont Nice - Menton, en 1929 ; confirmé par la légende « circulait sur la Corniche de 1900 à 1929 » (article Route du bord de mer).',
    ],

    // 3877 Ligne de Novéant à Gorze (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3877',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1933-07-01',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Nov%C3%A9ant_%C3%A0_Gorze&oldid=199530020',
        'quote' => 'Cela conduisit le département de la Moselle à décider la fermeture temporaire de la ligne à compter du 1er juillet 1933. Cette fermeture fut cependant définitive',
        'reason' => 'Fin de tout trafic (voyageurs et marchandises) au 1er juillet 1933.',
    ],

    // 3877 Ligne de Novéant à Gorze (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3877',
        'action' => 'add',
        'type' => 'dismantling',
        'date' => '1935-01-01',
        'precision' => 'year',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Nov%C3%A9ant_%C3%A0_Gorze&oldid=199530020',
        'quote' => 'Cette fermeture fut cependant définitive et les installations furent démantelées en 1935.',
        'reason' => 'Dépose des installations en 1935.',
    ],

    // 3879 Ligne de Noyon à Ham (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3879',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1913-01-01',
        'precision' => 'year',
        'from' => 'Guiscard',
        'to' => 'Ham',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_Noyon_%C3%A0_Ham&oldid=229885118',
        'quote' => 'En 1913, elle est prolongée à Ham.',
        'reason' => 'Ouverture du prolongement Guiscard - Ham, absente de la base.',
    ],

    // 3879 Ligne de Noyon à Ham (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '3879',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1955-01-01',
        'precision' => 'year',
        'from' => 'Noyon',
        'to' => 'Ham',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemins_de_fer_d%C3%A9partementaux_de_l%27Oise&oldid=230778728',
        'quote' => 'Noyon - Ham, 25 km (1895 - 1955), exploitée à l\'origine par la Compagnie des Chemins de fer de Milly à Formerie et de Noyon à Guiscard et à Lassigny (MFNGL).',
        'reason' => 'Fin de l\'exploitation de toute la ligne en 1955.',
    ],

    // 4013 Ligne de Saint-Etienne au Pertuiset par Firminy (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '4013',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1882-02-23',
        'precision' => 'day',
        'from' => 'Saint-Étienne-Bellevue',
        'to' => 'Firminy',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Tramway_de_Saint-%C3%89tienne&oldid=238823259',
        'quote' => 'La ligne Bellevue - Firminy, le 23 février 1882 ;',
        'reason' => 'Ouverture du tramway à vapeur des CFVE de Bellevue (Saint-Étienne) à Firminy.',
    ],

    // 4013 Ligne de Saint-Etienne au Pertuiset par Firminy (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '4013',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1931-12-01',
        'precision' => 'month',
        'from' => 'Firminy',
        'to' => 'Le Pertuiset',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Autobus_de_Saint-%C3%89tienne&oldid=240499568',
        'quote' => 'La ligne 2 est exploitée en autobus standard : elle est créée en décembre 1931 à la fin de l\'exploitation de la ligne de tramway le même mois.',
        'reason' => 'Section « Ligne 2 (Gare de Firminy-Pertuiset) » : fin du tramway Firminy - Le Pertuiset en décembre 1931, remplacé par l\'autobus.',
    ],

    // 4013 Ligne de Saint-Etienne au Pertuiset par Firminy (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '4013',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1948-06-05',
        'precision' => 'day',
        'from' => 'Saint-Étienne-Bellevue',
        'to' => 'Firminy',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Autobus_de_Saint-%C3%89tienne&oldid=240499568',
        'quote' => 'La ligne 1 est exploitée en trolleybus. Elle est créée le 6 juin 1948, à la fin de l\'exploitation de la ligne de tramway, le 5 juin 1948, dont le tracé est identique.',
        'reason' => 'Section « Ligne 1 (Bellevue-Pont Chaney) » : fin du tramway Bellevue - Firminy le 5 juin 1948, remplacé par le trolleybus (confirmé par l\'article Trolleybus de Saint-Étienne, conversion de la ligne Bellevue - Firminy en 1948).',
    ],

    // #4025 Ligne de Saint-Girons à Sentein : ligne abandonnée en 1937 au profit de la route et déclassée le 26/03/1938 : seule la fermeture aux voyageurs était connue au-delà de Castillon.
    [
        'line' => '4025',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1937-01-01',
        'precision' => 'year',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Saint-Girons_%C3%A0_Castillon_et_%C3%A0_Sentein&oldid=238515598',
        'quote' => '1937 : fermeture des 23 km de Saint-Girons à Sentein',
        'reason' => 'Fin de tout trafic sur toute la ligne (Saint-Girons - Castillon - Sentein).',
    ],

    [
        'line' => '4025',
        'action' => 'add',
        'type' => 'declassification',
        'date' => '1938-03-26',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Saint-Girons_%C3%A0_Castillon_et_%C3%A0_Sentein&oldid=238515598',
        'quote' => 'Elle est finalement déclassée le 26 mars 1938',
        'reason' => 'Déclassement de la ligne (Journal officiel du 6 avril 1938).',
    ],

    // 4062 Ligne de Saint-Waast à Berry-au-Bac (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '4062',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1919-01-01',
        'precision' => 'year',
        'from' => 'Vailly-sur-Aisne',
        'to' => 'Œuilly',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemins_de_fer_de_la_Banlieue_de_Reims&oldid=239137329',
        'quote' => 'Vailly - Œuilly | 1919 | 1953 | 16 février 1966 | CDA / NE (1922) / RTA (1952) | Voie normale',
        'reason' => 'Ouverture de la section à voie normale Vailly - Œuilly, absente de la base (la portion hérite à tort de 1920).',
    ],

    // 4062 Ligne de Saint-Waast à Berry-au-Bac (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '4062',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1933-07-10',
        'precision' => 'day',
        'from' => 'Cuiry',
        'to' => 'Concevreux',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemins_de_fer_de_la_Banlieue_de_Reims&oldid=239137329',
        'quote' => 'Cuiry - Concevreux | 10 juillet 1933 | Mai 1940 | Mai 1940 | NE | Voie normale',
        'reason' => 'Ouverture du raccordement Cuiry - Concevreux, absente de la base (la portion hérite à tort de 1920).',
    ],

    // 4062 Ligne de Saint-Waast à Berry-au-Bac (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '4062',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1940-05-01',
        'precision' => 'month',
        'from' => 'Concevreux',
        'to' => 'Berry-au-Bac',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemins_de_fer_de_la_Banlieue_de_Reims&oldid=239137329',
        'quote' => 'Concevreux - Berry-au-Bac | 10 juillet 1933 | Mai 1940 | 1er juillet 1960 | NE / RTA (1952) | Voie normale',
        'reason' => 'Chronologie complète répétée (déjà en base) au cas où la résolution de Beaurieux - Pontavert déborde vers l\'est.',
    ],

    // 4062 Ligne de Saint-Waast à Berry-au-Bac (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '4062',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1940-05-01',
        'precision' => 'month',
        'from' => 'Cuiry',
        'to' => 'Concevreux',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemins_de_fer_de_la_Banlieue_de_Reims&oldid=239137329',
        'quote' => 'Cuiry - Concevreux | 10 juillet 1933 | Mai 1940 | Mai 1940 | NE | Voie normale',
        'reason' => 'Fin du fret en mai 1940, fin de tout trafic sur la section.',
    ],

    // 4062 Ligne de Saint-Waast à Berry-au-Bac (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '4062',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1940-05-01',
        'precision' => 'month',
        'from' => 'Cuiry',
        'to' => 'Concevreux',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemins_de_fer_de_la_Banlieue_de_Reims&oldid=239137329',
        'quote' => 'Cuiry - Concevreux | 10 juillet 1933 | Mai 1940 | Mai 1940 | NE | Voie normale',
        'reason' => 'Fin du service voyageurs en mai 1940.',
    ],

    // 4062 Ligne de Saint-Waast à Berry-au-Bac (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '4062',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1953-01-01',
        'precision' => 'year',
        'from' => 'Vailly-sur-Aisne',
        'to' => 'Œuilly',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemins_de_fer_de_la_Banlieue_de_Reims&oldid=239137329',
        'quote' => 'Vailly - Œuilly | 1919 | 1953 | 16 février 1966 | CDA / NE (1922) / RTA (1952) | Voie normale',
        'reason' => 'Fin du service voyageurs Vailly - Œuilly, ligne absente de la base.',
    ],

    // 4062 Ligne de Saint-Waast à Berry-au-Bac (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '4062',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1953-01-01',
        'precision' => 'year',
        'from' => 'Œuilly',
        'to' => 'Beaurieux',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemins_de_fer_de_la_Banlieue_de_Reims&oldid=239137329',
        'quote' => 'Œuilly - Beaurieux | 1920 | 1953 | 16 février 1966 | CDA / NE (1922) / RTA (1952) | Voie normale',
        'reason' => 'Chronologie complète répétée (déjà en base) pour la section voisine.',
    ],

    // 4062 Ligne de Saint-Waast à Berry-au-Bac (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '4062',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1960-07-01',
        'precision' => 'day',
        'from' => 'Concevreux',
        'to' => 'Berry-au-Bac',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemins_de_fer_de_la_Banlieue_de_Reims&oldid=239137329',
        'quote' => 'Concevreux - Berry-au-Bac | 10 juillet 1933 | Mai 1940 | 1er juillet 1960 | NE / RTA (1952) | Voie normale',
        'reason' => 'Chronologie complète répétée (déjà en base) au cas où la résolution de Beaurieux - Pontavert déborde vers l\'est.',
    ],

    // 4062 Ligne de Saint-Waast à Berry-au-Bac (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '4062',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1962-07-01',
        'precision' => 'day',
        'from' => 'Beaurieux',
        'to' => 'Pontavert',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemins_de_fer_de_la_Banlieue_de_Reims&oldid=239137329',
        'quote' => 'Beaurieux - Pontavert | 1921 | | 1er juillet 1962 | CDA / NE (1922) / RTA (1952) | Voie normale',
        'reason' => 'Fin du fret le 1er juillet 1962, seul trafic renseigné (pas de date voyageurs) : dernier trafic de la section Beaurieux - Cuiry.',
    ],

    // 4062 Ligne de Saint-Waast à Berry-au-Bac (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '4062',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1966-02-16',
        'precision' => 'day',
        'from' => 'Vailly-sur-Aisne',
        'to' => 'Œuilly',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemins_de_fer_de_la_Banlieue_de_Reims&oldid=239137329',
        'quote' => 'Vailly - Œuilly | 1919 | 1953 | 16 février 1966 | CDA / NE (1922) / RTA (1952) | Voie normale',
        'reason' => 'Fin du fret Vailly - Œuilly, dernier trafic de la section.',
    ],

    // 4062 Ligne de Saint-Waast à Berry-au-Bac (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '4062',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1966-02-16',
        'precision' => 'day',
        'from' => 'Œuilly',
        'to' => 'Beaurieux',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemins_de_fer_de_la_Banlieue_de_Reims&oldid=239137329',
        'quote' => 'Œuilly - Beaurieux | 1920 | 1953 | 16 février 1966 | CDA / NE (1922) / RTA (1952) | Voie normale',
        'reason' => 'Chronologie complète répétée (déjà en base) pour la section voisine.',
    ],

    // 012000 Ligne de Troyes à Brienne-le-Château (reconstruit depuis la base du 28/10/2026 ; citation d'origine perdue, source = celle de l'événement)
    [
        'line' => '012000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '1853-01-01'],
        'label' => 'Ouverture d\'une autre ligne de l\'itinéraire Auxerre - Vitry',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Troyes_%C3%A0_Brienne-le-Ch%C3%A2teau&oldid=236561494',
        'quote' => '',
        'reason' => 'Ouverture d\'Auxerre - Monéteau-Gurgy (autre ligne de l\'itinéraire Auxerre - Vitry), pas de Troyes - Brienne ouverte en décembre 1886.',
    ],

    // 012000 Ligne de Troyes à Brienne-le-Château (reconstruit depuis la base du 28/10/2026 ; citation d'origine perdue, source = celle de l'événement)
    [
        'line' => '012000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '1884-01-01'],
        'label' => 'Ouverture d\'une autre ligne de l\'itinéraire Auxerre - Vitry',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Troyes_%C3%A0_Brienne-le-Ch%C3%A2teau&oldid=236561494',
        'quote' => '',
        'reason' => 'Ouverture de Brienne - Vallentigny (ligne de Jessains à Sorcy), pas de Troyes - Brienne.',
    ],

    // 012000 Ligne de Troyes à Brienne-le-Château (reconstruit depuis la base du 28/10/2026 ; citation d'origine perdue, source = celle de l'événement)
    [
        'line' => '012000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '1891-01-01'],
        'label' => 'Ouverture d\'une autre ligne de l\'itinéraire Auxerre - Vitry',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Troyes_%C3%A0_Brienne-le-Ch%C3%A2teau&oldid=236561494',
        'quote' => '',
        'reason' => 'Ouverture de Saint-Florentin - Troyes, autre ligne.',
    ],

    // 012000 Ligne de Troyes à Brienne-le-Château (reconstruit depuis la base du 28/10/2026 ; citation d'origine perdue, source = celle de l'événement)
    [
        'line' => '012000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '1926-01-01'],
        'label' => 'Ouverture d\'une autre ligne de l\'itinéraire Auxerre - Vitry',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Troyes_%C3%A0_Brienne-le-Ch%C3%A2teau&oldid=236561494',
        'quote' => '',
        'reason' => 'Ouverture de Monéteau-Gurgy - Saint-Florentin, autre ligne.',
    ],

    // 012000 Ligne de Troyes à Brienne-le-Château (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '012000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1950-12-01',
        'precision' => 'day',
        'from' => 'Troyes',
        'to' => 'Thennelières',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Troyes_%C3%A0_Brienne-le-Ch%C3%A2teau&oldid=236561494',
        'quote' => '1er décembre 1950 : fermeture au service voyageurs',
        'reason' => 'Fermeture aux voyageurs de toute la ligne, répétée par section.',
    ],

    // 012000 Ligne de Troyes à Brienne-le-Château (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '012000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1950-12-01',
        'precision' => 'day',
        'from' => 'Thennelières',
        'to' => 'Brienne-le-Château',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Troyes_%C3%A0_Brienne-le-Ch%C3%A2teau&oldid=236561494',
        'quote' => '1er décembre 1950 : fermeture au service voyageurs',
        'reason' => 'Fermeture aux voyageurs de toute la ligne, répétée par section.',
    ],

    // 012000 Ligne de Troyes à Brienne-le-Château (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '012000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '2005-01-01',
        'precision' => 'year',
        'from' => 'Thennelières',
        'to' => 'Brienne-le-Château',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Troyes_%C3%A0_Brienne-le-Ch%C3%A2teau&oldid=236561494',
        'quote' => 'fin 2005 : fermeture de la section Thenellières - Brienne-le-Château au service marchandises',
        'reason' => 'Fin du fret fin 2005 sur cette section (précision année).',
    ],

    // 012000 Ligne de Troyes à Brienne-le-Château (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '012000',
        'action' => 'add',
        'type' => 'reopening_freight',
        'date' => '2010-12-12',
        'precision' => 'day',
        'from' => 'Thennelières',
        'to' => 'Brienne-le-Château',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Troyes_%C3%A0_Brienne-le-Ch%C3%A2teau&oldid=236561494',
        'quote' => '12 décembre 2010 : réouverture au service marchandises.',
        'reason' => 'Rétablissement de la liaison fret directe Troyes - Brienne par RFF.',
    ],

    // 012000 Ligne de Troyes à Brienne-le-Château (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '012000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '2022-12-01',
        'precision' => 'month',
        'from' => 'Thennelières',
        'to' => 'Brienne-le-Château',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Troyes_%C3%A0_Brienne-le-Ch%C3%A2teau&oldid=236561494',
        'quote' => 'Ligne fermée depuis fin décembre 2022.',
        'reason' => 'Fin de tout trafic (ligne fret neutralisée) fin décembre 2022.',
    ],

    // 012000 Ligne de Troyes à Brienne-le-Château (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '012000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '2022-12-01',
        'precision' => 'month',
        'from' => 'Troyes',
        'to' => 'Thennelières',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Troyes_%C3%A0_Brienne-le-Ch%C3%A2teau&oldid=236561494',
        'quote' => 'Ligne fermée depuis fin décembre 2022.',
        'reason' => 'Fin de tout trafic (ligne fret neutralisée) fin décembre 2022.',
    ],

    // 027000 Ligne de Nançois-Tronville à Neufchâteau (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '027000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1969-03-03',
        'precision' => 'day',
        'from' => 'Velaines',
        'to' => 'Gondrecourt-le-Château',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Nan%C3%A7ois_-_Tronville_%C3%A0_Neufch%C3%A2teau&oldid=235342435',
        'quote' => 'La totalité de la ligne a été fermée au service des voyageurs le 3 mars 1969.',
        'reason' => 'Fermeture aux voyageurs de toute la ligne, répétée par section car la fermeture fret par section masque l\'événement ligne entière.',
    ],

    // 027000 Ligne de Nançois-Tronville à Neufchâteau (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '027000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1969-03-03',
        'precision' => 'day',
        'from' => 'Sionne - Midrevaux',
        'to' => 'Neufchâteau',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Nan%C3%A7ois_-_Tronville_%C3%A0_Neufch%C3%A2teau&oldid=235342435',
        'quote' => 'La totalité de la ligne a été fermée au service des voyageurs le 3 mars 1969.',
        'reason' => 'Fermeture aux voyageurs de toute la ligne, répétée par section car la fermeture fret par section masque l\'événement ligne entière.',
    ],

    // 027000 Ligne de Nançois-Tronville à Neufchâteau (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '027000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1969-03-03',
        'precision' => 'day',
        'from' => 'Grand - Avranville',
        'to' => 'Sionne - Midrevaux',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Nan%C3%A7ois_-_Tronville_%C3%A0_Neufch%C3%A2teau&oldid=235342435',
        'quote' => 'Le trafic des marchandises a été supprimé en cinq étapes : de Grand - Avranville à Sionne - Midrevaux, le 3 mars 1969 ;',
        'reason' => 'Fin du fret, dernier trafic de la section (voyageurs déjà fermés en 1969).',
    ],

    // 027000 Ligne de Nançois-Tronville à Neufchâteau (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '027000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1969-03-03',
        'precision' => 'day',
        'from' => 'Nançois-Tronville',
        'to' => 'Velaines',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Nan%C3%A7ois_-_Tronville_%C3%A0_Neufch%C3%A2teau&oldid=235342435',
        'quote' => 'La totalité de la ligne a été fermée au service des voyageurs le 3 mars 1969.',
        'reason' => 'Fermeture aux voyageurs de toute la ligne, répétée par section car la fermeture fret par section masque l\'événement ligne entière.',
    ],

    // 027000 Ligne de Nançois-Tronville à Neufchâteau (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '027000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1969-03-03',
        'precision' => 'day',
        'from' => 'Gondrecourt-le-Château',
        'to' => 'Dainville',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Nan%C3%A7ois_-_Tronville_%C3%A0_Neufch%C3%A2teau&oldid=235342435',
        'quote' => 'La totalité de la ligne a été fermée au service des voyageurs le 3 mars 1969.',
        'reason' => 'Fermeture aux voyageurs de toute la ligne, répétée par section car la fermeture fret par section masque l\'événement ligne entière.',
    ],

    // 027000 Ligne de Nançois-Tronville à Neufchâteau (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '027000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1969-03-03',
        'precision' => 'day',
        'from' => 'Dainville-Meuse',
        'to' => 'Grand - Avranville',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Nan%C3%A7ois_-_Tronville_%C3%A0_Neufch%C3%A2teau&oldid=235342435',
        'quote' => 'La totalité de la ligne a été fermée au service des voyageurs le 3 mars 1969.',
        'reason' => 'Fermeture aux voyageurs de toute la ligne, répétée par section car la fermeture fret par section masque l\'événement ligne entière.',
    ],

    // 027000 Ligne de Nançois-Tronville à Neufchâteau (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '027000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1969-03-03',
        'precision' => 'day',
        'from' => 'Grand - Avranville',
        'to' => 'Sionne - Midrevaux',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Nan%C3%A7ois_-_Tronville_%C3%A0_Neufch%C3%A2teau&oldid=235342435',
        'quote' => 'La totalité de la ligne a été fermée au service des voyageurs le 3 mars 1969.',
        'reason' => 'Fermeture aux voyageurs de toute la ligne, répétée par section car la fermeture fret par section masque l\'événement ligne entière.',
    ],

    // 027000 Ligne de Nançois-Tronville à Neufchâteau (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '027000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1971-07-05',
        'precision' => 'day',
        'from' => 'Dainville-Meuse',
        'to' => 'Grand - Avranville',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Nan%C3%A7ois_-_Tronville_%C3%A0_Neufch%C3%A2teau&oldid=235342435',
        'quote' => 'Le trafic des marchandises a été supprimé en cinq étapes : […] de Dainville-Meuse à Grand - Avranville et de Sionne - Midrevaux à Neufchâteau (PK 66,400), le 5 juillet 1971 ;',
        'reason' => 'Fin du fret, dernier trafic de la section (voyageurs déjà fermés en 1969).',
    ],

    // 027000 Ligne de Nançois-Tronville à Neufchâteau (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '027000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1971-07-05',
        'precision' => 'day',
        'from' => 'Sionne - Midrevaux',
        'to' => 'Neufchâteau',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Nan%C3%A7ois_-_Tronville_%C3%A0_Neufch%C3%A2teau&oldid=235342435',
        'quote' => 'Le trafic des marchandises a été supprimé en cinq étapes : […] de Dainville-Meuse à Grand - Avranville et de Sionne - Midrevaux à Neufchâteau (PK 66,400), le 5 juillet 1971 ;',
        'reason' => 'Fin du fret, dernier trafic de la section (voyageurs déjà fermés en 1969).',
    ],

    // 027000 Ligne de Nançois-Tronville à Neufchâteau (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '027000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1973-12-16',
        'precision' => 'day',
        'from' => 'Gondrecourt-le-Château',
        'to' => 'Dainville',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Nan%C3%A7ois_-_Tronville_%C3%A0_Neufch%C3%A2teau&oldid=235342435',
        'quote' => 'Le trafic des marchandises a été supprimé en cinq étapes : […] de Gondrecourt-le-Château à Dainville, le 16 décembre 1973 ;',
        'reason' => 'Fin du fret, dernier trafic de la section (voyageurs déjà fermés en 1969).',
    ],

    // 027000 Ligne de Nançois-Tronville à Neufchâteau (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '027000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '2014-11-01',
        'precision' => 'month',
        'from' => 'Velaines',
        'to' => 'Gondrecourt-le-Château',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Nan%C3%A7ois_-_Tronville_%C3%A0_Neufch%C3%A2teau&oldid=235342435',
        'quote' => 'Le trafic des marchandises a été supprimé en cinq étapes : […] de Velaines à Gondrecourt-le-Château, en novembre 2014 ;',
        'reason' => 'Fin du fret, dernier trafic de la section (voyageurs déjà fermés en 1969).',
    ],

    // 027000 Ligne de Nançois-Tronville à Neufchâteau (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '027000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '2020-01-01',
        'precision' => 'year',
        'from' => 'Nançois-Tronville',
        'to' => 'Velaines',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Nan%C3%A7ois_-_Tronville_%C3%A0_Neufch%C3%A2teau&oldid=235342435',
        'quote' => 'Le trafic des marchandises a été supprimé en cinq étapes : […] de Nançois-Tronville à Velaines en 2020.',
        'reason' => 'Fin du fret, dernier trafic de la section (voyageurs déjà fermés en 1969).',
    ],

    // 040000 Ligne de Jarville-la-Malgrange à Mirecourt (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '040000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '2016-12-18',
        'precision' => 'day',
        'from' => 'Xeuilley',
        'to' => 'Mirecourt',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Jarville-la-Malgrange_%C3%A0_Mirecourt&oldid=235342174',
        'quote' => '18 décembre 2016 : trafic voyageurs suspendu entre Pont-Saint-Vincent et Mirecourt.',
        'reason' => 'Fin du service voyageurs entre Pont-Saint-Vincent et Mirecourt (et au-delà vers Vittel).',
    ],

    // 040000 Ligne de Jarville-la-Malgrange à Mirecourt (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '040000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '2016-12-18',
        'precision' => 'day',
        'from' => 'Pont-Saint-Vincent',
        'to' => 'Xeuilley',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Jarville-la-Malgrange_%C3%A0_Mirecourt&oldid=235342174',
        'quote' => '18 décembre 2016 : trafic voyageurs suspendu entre Pont-Saint-Vincent et Mirecourt.',
        'reason' => 'Fin du service voyageurs au sud de Pont-Saint-Vincent ; la section reste ouverte au fret (cimenterie de Xeuilley).',
    ],

    // 040000 Ligne de Jarville-la-Malgrange à Mirecourt (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '040000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '2016-12-18',
        'precision' => 'day',
        'from' => 'Xeuilley',
        'to' => 'Mirecourt',
        'source_url' => 'https://www.assemblee-nationale.fr/dyn/15/questions/QANR5L15QOSD39',
        'quote' => 'L\'ensemble des élus se rejoignent sur la nécessité absolue de programmer des travaux sur cette ligne neutralisée depuis décembre 2016.',
        'reason' => 'La suspension voyageurs de décembre 2016 a été le dernier trafic commercial au-delà de Xeuilley : ligne neutralisée depuis, réouverture prévue fin 2027 seulement.',
    ],

    [
        'line' => '136000',
        'action' => 'neutralize',
        'match' => ['source' => 'ohm', 'type' => 'opening', 'date' => '1844-07-15', 'from' => 'St-Louis'],
        'label' => 'Ouverture de la ligne de Strasbourg à Saint-Louis (autre ligne)',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Saint-Louis_%C3%A0_Huningue&oldid=238408286',
        'quote' => 'La ligne de « Saint-Louis au Rhin » est ouverte le 11 février 1878 et se prolongeait au-delà du Rhin jusqu\'à Weil am Rhein.',
        'reason' => 'Ouverture de la grande ligne Strasbourg - Saint-Louis (115000) posée sur un doublon de 0,9 km du tracé archeoferroviaire le long de la 115000 ; la ligne de Saint-Louis à Huningue ouvre le 11/02/1878. Sans effet sur la chute 2020 - 2030.',
    ],

    [
        'line' => '150000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1938-01-01',
        'precision' => 'year',
        'from' => 'Oberhoffen',
        'to' => 'Soufflenheim',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Soufflenheim&oldid=240503276',
        'quote' => 'À partir de 1895, Soufflenheim se trouvait connectée par la ligne de chemin de fer de Haguenau à Rœschwoog et frontière. Le service voyageurs est suspendu environ en 1938, le trafic marchandises le 2 juin 1991.',
        'reason' => 'Fin du service voyageurs à Soufflenheim vers 1938, absente de la base pour la section.',
    ],

    [
        'line' => '150000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1938-01-01',
        'precision' => 'year',
        'from' => 'Soufflenheim',
        'to' => 'Rœschwoog',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Soufflenheim&oldid=240503276',
        'quote' => 'À partir de 1895, Soufflenheim se trouvait connectée par la ligne de chemin de fer de Haguenau à Rœschwoog et frontière. Le service voyageurs est suspendu environ en 1938, le trafic marchandises le 2 juin 1991.',
        'reason' => 'Fin du service voyageurs à Soufflenheim vers 1938, absente de la base pour la section.',
    ],

    // 150000 Ligne de Haguenau à Rœschwoog et frontière : aucune fermeture voyageurs en base : 1939 (Haguenau - Oberhoffen) et 1938 (au-delà), fin du fret 1973 et 1991.
    [
        'line' => '150000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1939-08-23',
        'precision' => 'day',
        'from' => 'Haguenau',
        'to' => 'Oberhoffen',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Haguenau_%C3%A0_R%C5%93schwoog_et_fronti%C3%A8re&oldid=238408468',
        'quote' => 'La section de Haguenau au camp d\'Oberhoffen est fermée au service voyageurs le 23 août 1939.',
        'reason' => 'Fermeture aux voyageurs absente de la base ; la section reste en service pour le fret (camp militaire d\'Oberhoffen).',
    ],

    [
        'line' => '150000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1973-12-16',
        'precision' => 'day',
        'from' => 'Oberhoffen',
        'to' => 'Soufflenheim',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Haguenau_%C3%A0_R%C5%93schwoog_et_fronti%C3%A8re&oldid=238408468',
        'quote' => 'La section d\'Oberhoffen à Soufflenheim est fermée au service marchandises le 16 décembre 1973.',
        'reason' => 'Dernier trafic commercial de la section, neutralisée depuis (voie maintenue pour la défense).',
    ],

    [
        'line' => '150000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1991-06-02',
        'precision' => 'day',
        'from' => 'Soufflenheim',
        'to' => 'Rœschwoog',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Haguenau_%C3%A0_R%C5%93schwoog_et_fronti%C3%A8re&oldid=238408468',
        'quote' => 'La section de Soufflenheim à Rœschwoog est fermée au service marchandises le 2 juin 1991.',
        'reason' => 'Dernier trafic commercial de la section, neutralisée depuis.',
    ],

    // 152000 Ligne de Wissembourg à Lauterbourg : fermeture voyageurs du 01/10/1947 non résolue sur les portions ; Scheibenhard - Lauterbourg fermée à tout trafic à la même date.
    [
        'line' => '152000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1947-10-01',
        'precision' => 'day',
        'from' => 'Wissembourg',
        'to' => 'Scheibenhard',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Wissembourg_%C3%A0_Lauterbourg-Gare&oldid=235342947',
        'quote' => 'Le 1er octobre 1947, la ligne a été fermée au service voyageurs, et le tronçon de Scheibenhard à Wissembourg est resté ouvert au service marchandises jusqu\'en 1958, essentiellement pour du transport d\'amidon et de pétrole.',
        'reason' => 'Fermeture aux voyageurs de toute la ligne, posée par section car l\'événement en base n\'est pas résolu.',
    ],

    [
        'line' => '152000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1947-10-01',
        'precision' => 'day',
        'from' => 'Scheibenhard',
        'to' => 'Lauterbourg',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Wissembourg_%C3%A0_Lauterbourg-Gare&oldid=235342947',
        'quote' => 'Le 1er octobre 1947, la ligne a été fermée au service voyageurs, et le tronçon de Scheibenhard à Wissembourg est resté ouvert au service marchandises jusqu\'en 1958, essentiellement pour du transport d\'amidon et de pétrole.',
        'reason' => 'Fermeture aux voyageurs de toute la ligne.',
    ],

    [
        'line' => '152000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1947-10-01',
        'precision' => 'day',
        'from' => 'Scheibenhard',
        'to' => 'Lauterbourg',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Wissembourg_%C3%A0_Lauterbourg-Gare&oldid=235342947',
        'quote' => 'Le 1er octobre 1947, la ligne a été fermée au service voyageurs, et le tronçon de Scheibenhard à Wissembourg est resté ouvert au service marchandises jusqu\'en 1958, essentiellement pour du transport d\'amidon et de pétrole.',
        'reason' => 'Seul le tronçon Scheibenhard - Wissembourg est resté ouvert aux marchandises : Scheibenhard - Lauterbourg a perdu tout trafic le 01/10/1947, avant son déclassement du 12/11/1954.',
    ],

    [
        'line' => '152000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1958-01-01',
        'precision' => 'year',
        'from' => 'Wissembourg',
        'to' => 'Scheibenhard',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Wissembourg_%C3%A0_Lauterbourg-Gare&oldid=235342947',
        'quote' => 'Le 1er octobre 1947, la ligne a été fermée au service voyageurs, et le tronçon de Scheibenhard à Wissembourg est resté ouvert au service marchandises jusqu\'en 1958, essentiellement pour du transport d\'amidon et de pétrole.',
        'reason' => 'Fin du fret (amidon, pétrole) et de tout trafic sur Scheibenhard - Wissembourg en 1958 (déjà en base).',
    ],

    [
        'line' => '160000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1970-01-01',
        'precision' => 'year',
        'from' => 'Neubourg',
        'to' => 'Schweighouse-sur-Moder',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Bouxwiller_(Bas-Rhin)&oldid=240392653',
        'quote' => 'La voie ferrée de Saverne à Bouxwiller a été posée en 1877 puis prolongée vers Haguenau en 1881 et vers Ingwiller en 1889. La portion vers cette dernière localité fut fermée dès 1953. Le reste de la ligne fut fermé aux voyageurs en 1970',
        'reason' => 'Fermeture aux voyageurs de 1970 de tout le reste de la ligne, absente de la base pour cette section.',
    ],

    [
        'line' => '160000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1970-01-01',
        'precision' => 'year',
        'from' => 'Dossenheim-sur-Zinsel',
        'to' => 'Bouxwiller',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Bouxwiller_(Bas-Rhin)&oldid=240392653',
        'quote' => 'La voie ferrée de Saverne à Bouxwiller a été posée en 1877 puis prolongée vers Haguenau en 1881 et vers Ingwiller en 1889. La portion vers cette dernière localité fut fermée dès 1953. Le reste de la ligne fut fermé aux voyageurs en 1970',
        'reason' => '« Le reste de la ligne » (Bouxwiller vers Saverne et vers Haguenau) a fermé aux voyageurs en 1970 ; la base n\'a pour cette section que la fermeture fret de 1975.',
    ],

    [
        'line' => '160000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1970-01-01',
        'precision' => 'year',
        'from' => 'Obermodern',
        'to' => 'Neubourg',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Bouxwiller_(Bas-Rhin)&oldid=240392653',
        'quote' => 'La voie ferrée de Saverne à Bouxwiller a été posée en 1877 puis prolongée vers Haguenau en 1881 et vers Ingwiller en 1889. La portion vers cette dernière localité fut fermée dès 1953. Le reste de la ligne fut fermé aux voyageurs en 1970',
        'reason' => 'Sans cette fermeture aux voyageurs, la section neutralisée reste « voyageurs » jusqu\'en 2020 malgré la fin du fret en 1990.',
    ],

    [
        'line' => '160000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1970-01-01',
        'precision' => 'year',
        'from' => 'Bouxwiller',
        'to' => 'Obermodern',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Bouxwiller_(Bas-Rhin)&oldid=240392653',
        'quote' => 'La voie ferrée de Saverne à Bouxwiller a été posée en 1877 puis prolongée vers Haguenau en 1881 et vers Ingwiller en 1889. La portion vers cette dernière localité fut fermée dès 1953. Le reste de la ligne fut fermé aux voyageurs en 1970',
        'reason' => 'Fermeture aux voyageurs de 1970 de tout le reste de la ligne, absente de la base pour cette section.',
    ],

    // 160000 Ligne de Steinbourg à Schweighouse-sur-Moder : fermeture voyageurs de 1970 absente au-delà de Dossenheim ; chronologie de fin complète par section (dépôt de munitions de Neubourg desservi jusqu'en 2012).
    [
        'line' => '160000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1970-03-31',
        'precision' => 'day',
        'from' => 'Steinbourg',
        'to' => 'Dossenheim-sur-Zinsel',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Steinbourg_%C3%A0_Schweighouse-sur-Moder&oldid=238409636',
        'quote' => 'La section de Steinbourg à Dossenheim-sur-Zinsel est fermée au trafic voyageurs le 31 mars 1970 puis au trafic marchandises le 3 août de la même année.',
        'reason' => 'Chronologie de fin complète de la section Steinbourg - Dossenheim (voyageurs).',
    ],

    [
        'line' => '160000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1970-08-03',
        'precision' => 'day',
        'from' => 'Steinbourg',
        'to' => 'Dossenheim-sur-Zinsel',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Steinbourg_%C3%A0_Schweighouse-sur-Moder&oldid=238409636',
        'quote' => 'La section de Steinbourg à Dossenheim-sur-Zinsel est fermée au trafic voyageurs le 31 mars 1970 puis au trafic marchandises le 3 août de la même année.',
        'reason' => 'Fermeture aux marchandises absente de la base (seul l\'OHM donne 1970-03-31 comme fin de tout trafic).',
    ],

    [
        'line' => '160000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1975-09-28',
        'precision' => 'day',
        'from' => 'Dossenheim-sur-Zinsel',
        'to' => 'Bouxwiller',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Steinbourg_%C3%A0_Schweighouse-sur-Moder&oldid=238409636',
        'quote' => 'La section entre Dossenheim-sur-Zinsel et Bouxwiller (PK 6,445 à 11,500) est fermée au service des marchandises le 28 septembre 1975.',
        'reason' => 'Fin du fret, reprise pour compléter la chronologie de la section (déjà en base).',
    ],

    [
        'line' => '160000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1987-05-01',
        'precision' => 'day',
        'from' => 'Bouxwiller',
        'to' => 'Obermodern',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Steinbourg_%C3%A0_Schweighouse-sur-Moder&oldid=238409636',
        'quote' => 'De même la section de Bouxwiller à Obermodern (PK 11,500 à 18,415) est fermée le 1er mai 1987.',
        'reason' => 'Fin de tout trafic, reprise pour compléter la chronologie de la section (déjà en base).',
    ],

    [
        'line' => '160000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1990-10-01',
        'precision' => 'day',
        'from' => 'Obermodern',
        'to' => 'Neubourg',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Steinbourg_%C3%A0_Schweighouse-sur-Moder&oldid=238409636',
        'quote' => 'La dernière section, d\'Obermodern à Schweighouse, est fermée au service des marchandises le 1er octobre 1990.',
        'reason' => 'Fin du fret public sur la dernière section ; au-delà du PK 25,6 environ (embranchement du dépôt de Neubourg) le trafic a continué, voir l\'événement suivant.',
    ],

    [
        'line' => '160000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '2012-01-01',
        'precision' => 'year',
        'from' => 'Neubourg',
        'to' => 'Schweighouse-sur-Moder',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Steinbourg_%C3%A0_Schweighouse-sur-Moder&oldid=238409636',
        'quote' => 'L\'embranchement particulier du dépôt de munitions de Neubourg a été utilisé jusqu\'en 2012.',
        'reason' => 'Dernier trafic (desserte de l\'embranchement militaire du dépôt de Neubourg depuis Schweighouse) en 2012, la section est ensuite neutralisée (statut SNCF actuel).',
    ],

    [
        'line' => '167000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1945-10-03',
        'precision' => 'day',
        'from' => 'Mackwiller',
        'to' => 'Diemeringen',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_R%C3%A9ding_%C3%A0_Diemeringen&oldid=238409446',
        'quote' => 'L\'intégralité de la ligne a été fermée au service voyageurs le 3 octobre 1945.',
        'reason' => 'Fermeture aux voyageurs de toute la ligne, non retenue sur cette portion qui n\'a que la fermeture fret.',
    ],

    [
        'line' => '167000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1990-01-01',
        'precision' => 'day',
        'from' => 'Mackwiller',
        'to' => 'Diemeringen',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_R%C3%A9ding_%C3%A0_Diemeringen&oldid=238409446',
        'quote' => 'La section Mackwiller - Diemeringen a été fermée au service marchandises le 1er janvier 1990.',
        'reason' => 'Fin du fret, reprise pour compléter la chronologie de la portion (déjà en base).',
    ],

    // 167000 Ligne de Réding à Diemeringen : vélorail Drulingen - Schalbach depuis le 07/07/2020 (pas 2026) ; Mackwiller - Diemeringen : voyageurs 1945, fret 1990.
    [
        'line' => '167000',
        'action' => 'add',
        'type' => 'heritage_start',
        'date' => '2020-07-07',
        'precision' => 'day',
        'from' => 'Drulingen',
        'to' => 'Schalbach',
        'heritage_kind' => 'velorail',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_R%C3%A9ding_%C3%A0_Diemeringen&oldid=238409446',
        'quote' => 'ouvre le « vélorail du pays Secret » le 7 juillet 2020 sur une section de 4,6 km entre la maison des services de Drulingen et la commune de Schalbach',
        'reason' => 'Ouverture réelle du vélorail du pays Secret (la base le date de 2026), qui met fin à la voie commerciale sur Drulingen - Schalbach.',
    ],

    [
        'line' => '167000',
        'action' => 'neutralize',
        'match' => ['source' => 'heritage', 'type' => 'heritage_start', 'date' => '2026-01-01', 'from' => 'Section de Drulingen'],
        'label' => 'Date de relevé de la liste des exploitations patrimoniales (vélorail ouvert en 2020)',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_R%C3%A9ding_%C3%A0_Diemeringen&oldid=238409446',
        'quote' => 'ouvre le « vélorail du pays Secret » le 7 juillet 2020 sur une section de 4,6 km entre la maison des services de Drulingen et la commune de Schalbach',
        'reason' => 'Date fausse : 2026 est la date de lecture de la liste des chemins de fer touristiques, le vélorail est ouvert depuis le 7 juillet 2020 (remplacé par l\'événement manuel heritage_start 2020-07-07).',
    ],

    // 208000 Ligne de Challerange à Apremont-sur-Aire (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '208000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1929-05-21',
        'precision' => 'day',
        'from' => 'Challerange',
        'to' => 'Grandpré',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Challerange_%C3%A0_Apremont&oldid=239110448',
        'quote' => 'La ligne est fermée au service voyageurs le 21 mai 1929 (une des premières avant les vagues de fermetures des années 1938-1939)',
        'reason' => 'Fermeture aux voyageurs de toute la ligne, répétée par section.',
    ],

    // 208000 Ligne de Challerange à Apremont-sur-Aire (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '208000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1929-05-21',
        'precision' => 'day',
        'from' => 'Grandpré',
        'to' => 'Apremont',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Challerange_%C3%A0_Apremont&oldid=239110448',
        'quote' => 'La ligne est fermée au service voyageurs le 21 mai 1929 (une des premières avant les vagues de fermetures des années 1938-1939)',
        'reason' => 'Fermeture aux voyageurs de toute la ligne, répétée par section.',
    ],

    // 208000 Ligne de Challerange à Apremont-sur-Aire (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '208000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1969-11-03',
        'precision' => 'day',
        'from' => 'Grandpré',
        'to' => 'Apremont',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Challerange_%C3%A0_Apremont&oldid=239110448',
        'quote' => 'La section de Grandpré à Apremont a été fermée au service du fret le 3 novembre 1969 et celle de Challerange à Grandpré vers 1990',
        'reason' => 'Fin du fret, dernier trafic de la section.',
    ],

    // 208000 Ligne de Challerange à Apremont-sur-Aire (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '208000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1990-01-01',
        'precision' => 'year',
        'from' => 'Challerange',
        'to' => 'Grandpré',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Challerange_%C3%A0_Apremont&oldid=239110448',
        'quote' => 'La section de Grandpré à Apremont a été fermée au service du fret le 3 novembre 1969 et celle de Challerange à Grandpré vers 1990',
        'reason' => 'Fin du fret vers 1990, dernier trafic de la section (statut SNCF actuel : Neutralisée).',
    ],

    // 286610 Voie-mère de Pont-à-Vendin à Violaines (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '286610',
        'action' => 'add',
        'type' => 'declassification',
        'date' => '1988-07-29',
        'precision' => 'day',
        'from' => 'Hulluch',
        'to' => 'Violaines',
        'pk_start' => 5230,
        'pk_end' => 10350,
        'source_url' => 'https://www.lignes-oubliees.com/fichiers/declassements.pdf',
        'quote' => 'Pont-à-Vendin - Violaines Hulluch - Violaines 5,230 10,350 5,120 IG DECL 29/07/1988',
        'reason' => 'Déclassement de la section Hulluch - Violaines de la voie-mère.',
    ],

    // 323000 Ligne d'Abbeville à Eu (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '323000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1993-01-01',
        'precision' => 'year',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_d%27Abbeville_%C3%A0_Eu&oldid=239669547',
        'quote' => 'La SNCF, qui souhaitait initialement fermer la ligne en 1995, avait interrompu le trafic de fret (alors assuré par des UM de BB 66000) vers le début des années 1990.',
        'reason' => 'Fin du fret au début des années 1990 ; année approchée 1993, après le train du 30/10/1992 décrit comme l\'un des derniers trains de fret (source vidéo citée par l\'article).',
    ],

    // 323000 Ligne d'Abbeville à Eu (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '323000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '2018-05-27',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_d%27Abbeville_%C3%A0_Eu&oldid=239669547',
        'quote' => 'Cette fermeture intervient effectivement le 28 mai 2018 (dernières circulations ferroviaires le 27), en raison d\'un manque de financement pour effectuer ladite modernisation',
        'reason' => 'Dernière circulation TER le 27/05/2018, fermeture effective le 28/05/2018.',
    ],

    // 323000 Ligne d'Abbeville à Eu (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '323000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '2018-05-27',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_d%27Abbeville_%C3%A0_Eu&oldid=239669547',
        'quote' => 'Cette fermeture intervient effectivement le 28 mai 2018 (dernières circulations ferroviaires le 27), en raison d\'un manque de financement pour effectuer ladite modernisation',
        'reason' => 'Fin de tout trafic (ligne voyageurs seule depuis l\'arrêt du fret), statut SNCF actuel Fermée ; réouverture sans échéance fixée (novembre 2023).',
    ],

    // 412000 Ligne de Caen à Cerisy-Belle-Étoile (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '412000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1970-05-31',
        'precision' => 'day',
        'from' => 'Caen',
        'to' => 'Cerisy-Belle-Étoile',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Caen_%C3%A0_Cerisy-Belle-%C3%89toile&oldid=238806854',
        'quote' => '31 mai 1970 : fermeture au service voyageurs de la ligne Caen - Cerisy-Belle-Étoile',
        'reason' => 'Fin du service voyageurs sur toute la ligne, à appliquer à toutes les portions (actuellement masqué par les fermetures fret par section).',
    ],

    // 412000 Ligne de Caen à Cerisy-Belle-Étoile (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '412000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1978-12-31',
        'precision' => 'day',
        'from' => 'Saint-Rémy',
        'to' => 'Berjou',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Caen_%C3%A0_Cerisy-Belle-%C3%89toile&oldid=238806854',
        'quote' => '31 décembre 1978 : fermeture au service marchandises de la section Saint-Rémy - Berjou',
        'reason' => 'Fin du fret Saint-Rémy - Berjou (portion de 1,8 km restée en service à tort).',
    ],

    // 412000 Ligne de Caen à Cerisy-Belle-Étoile (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '412000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1987-01-25',
        'precision' => 'day',
        'from' => 'Feuguerolles-Saint-André',
        'to' => 'Saint-Rémy',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Caen_%C3%A0_Cerisy-Belle-%C3%89toile&oldid=238806854',
        'quote' => '25 janvier 1987 : fermeture au service marchandises des sections Feuguerolles-Saint-André - Saint-Rémy et Berjou - Condé-sur-Noireau',
        'reason' => 'Fin du fret Feuguerolles-Saint-André - Saint-Rémy.',
    ],

    // 412000 Ligne de Caen à Cerisy-Belle-Étoile (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '412000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1987-01-25',
        'precision' => 'day',
        'from' => 'Berjou',
        'to' => 'Condé-sur-Noireau',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Caen_%C3%A0_Cerisy-Belle-%C3%89toile&oldid=238806854',
        'quote' => '25 janvier 1987 : fermeture au service marchandises des sections Feuguerolles-Saint-André - Saint-Rémy et Berjou - Condé-sur-Noireau',
        'reason' => 'Fin du fret Berjou - Condé-sur-Noireau (seconde section de la même décision, absente de la base).',
    ],

    // 412000 Ligne de Caen à Cerisy-Belle-Étoile (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '412000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1988-05-29',
        'precision' => 'day',
        'from' => 'Condé-sur-Noireau',
        'to' => 'Cerisy-Belle-Étoile',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Caen_%C3%A0_Cerisy-Belle-%C3%89toile&oldid=238806854',
        'quote' => '29 mai 1988 : fermeture au service marchandises de la section Condé-sur-Noireau - Cerisy-Belle-Étoile',
        'reason' => 'Fin du fret Condé-sur-Noireau - Cerisy-Belle-Étoile.',
    ],

    // 412000 Ligne de Caen à Cerisy-Belle-Étoile (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '412000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1991-06-01',
        'precision' => 'day',
        'from' => 'Caen',
        'to' => 'Feuguerolles-Saint-André',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Caen_%C3%A0_Cerisy-Belle-%C3%89toile&oldid=238806854',
        'quote' => '1er juin 1991 : fermeture au service marchandises de la section Caen - Feuguerolles-Saint-André',
        'reason' => 'Dernier fret de la ligne, entre Caen et Feuguerolles-Saint-André.',
    ],

    // 412000 Ligne de Caen à Cerisy-Belle-Étoile (reconstruit depuis la base du 28/10/2026 ; citation d'origine perdue, source = celle de l'événement)
    [
        'line' => '412000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'closure_passenger', 'date' => '1993-07-04'],
        'label' => 'Dernier train touristique',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Caen_%C3%A0_Cerisy-Belle-%C3%89toile&oldid=238806854',
        'quote' => '',
        'reason' => 'C\'est le dernier train touristique, pas une fermeture du service voyageurs commercial (supprimé le 31/05/1970) ; remplacé par heritage_end 1993-07-04.',
    ],

    // 412000 Ligne de Caen à Cerisy-Belle-Étoile (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '412000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '2003-03-19',
        'precision' => 'day',
        'from' => 'Caen',
        'to' => 'Cerisy-Belle-Étoile',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Caen_%C3%A0_Cerisy-Belle-%C3%89toile&oldid=238806854',
        'quote' => 'Le 19 mars 2003, Réseau ferré de France décréta la fermeture administrative de la ligne Caen-Flers.',
        'reason' => 'Fermeture administrative de toute la ligne (décision RFF du 21/03/2003 selon lignes-oubliées), à appliquer aussi à la portion Saint-Rémy - Berjou.',
    ],

    // 467000 Ligne de Vitré à Martigné-Ferchaud (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '467000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1938-01-01',
        'precision' => 'year',
        'from' => 'Vitré',
        'to' => 'Martigné-Ferchaud',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Martign%C3%A9-Ferchaud_%C3%A0_Vitr%C3%A9&oldid=235342342',
        'quote' => 'En 1938, l\'application des mesures gouvernementales de coordination rail-route entraîne sa fermeture « au service des voyageurs, bagages, chiens et colis-express », assuré désormais par des autocars.',
        'reason' => 'Fermeture voyageurs de toute la ligne en 1938 (coordination rail-route, JO du 19 octobre 1938 cité par l\'article).',
    ],

    // 467000 Ligne de Vitré à Martigné-Ferchaud (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '467000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1972-01-01',
        'precision' => 'year',
        'from' => 'Vitré',
        'to' => 'La Guerche-de-Bretagne',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Gare_de_Vitr%C3%A9&oldid=232112833',
        'quote' => 'La section Vitré - Fougères de la ligne Vitré - Pontorson a fermé le 5 mars 1972. [...] La section Vitré - La Guerche-de-Bretagne de la ligne Martigné-Ferchaud - Vitré a été fermée la même année.',
        'reason' => 'Les voyageurs étant supprimés depuis 1938, la fermeture de 1972 met fin au fret restant entre Vitré et La Guerche (la gare de La Guerche, désaffectée, est démolie en 1972).',
    ],

    // 467000 Ligne de Vitré à Martigné-Ferchaud (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '467000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1972-01-01',
        'precision' => 'year',
        'from' => 'Vitré',
        'to' => 'La Guerche-de-Bretagne',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Gare_de_Vitr%C3%A9&oldid=232112833',
        'quote' => 'La section Vitré - Fougères de la ligne Vitré - Pontorson a fermé le 5 mars 1972. [...] La section Vitré - La Guerche-de-Bretagne de la ligne Martigné-Ferchaud - Vitré a été fermée la même année.',
        'reason' => 'Fin de tout trafic sur Vitré - La Guerche-de-Bretagne en 1972.',
    ],

    // 467000 Ligne de Vitré à Martigné-Ferchaud (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '467000',
        'action' => 'add',
        'type' => 'declassification',
        'date' => '1991-12-16',
        'precision' => 'day',
        'from' => 'La Guerche-de-Bretagne',
        'to' => 'Vitré',
        'source_url' => 'https://www.lignes-oubliees.com/fichiers/declassements.pdf',
        'quote' => 'Martigné-Ferchaud - Vitré | Le Guerche-de-Bretagne - Vitré | 15,830 | 38,590 | 22,760 | IG | DECL | 16/12/1991',
        'reason' => 'Déclassement de la section La Guerche-de-Bretagne - Vitré (PK 15,830 à 38,590), ensuite transformée en voie verte Vitré - Moutiers.',
    ],

    // 472000 Ligne de Ploërmel à La Brohinière (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '472000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1972-03-06',
        'precision' => 'day',
        'from' => 'Mauron',
        'to' => 'La Brohinière',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Plo%C3%ABrmel_%C3%A0_La_Brohini%C3%A8re&oldid=236290327',
        'quote' => 'La fermeture officielle du service des voyageurs intervient, sur la totalité de la ligne, le 6 mars 1972.',
        'reason' => 'Rappel de la fermeture voyageurs (déjà en base) pour compléter la chronologie manuelle de la section.',
    ],

    // 472000 Ligne de Ploërmel à La Brohinière (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '472000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1998-01-01',
        'precision' => 'year',
        'from' => 'Mauron',
        'to' => 'La Brohinière',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Plo%C3%ABrmel_%C3%A0_La_Brohini%C3%A8re&oldid=236290327',
        'quote' => 'L\'avenir de la section de Mauron à La Brohinière, à l\'abandon depuis la fermeture du service des marchandises en 1998',
        'reason' => 'Première fin du fret en 1998 (lignes-oubliées : « vers 1998 » ; Gare de Mauron : fermeture marchandises 1998), avant la réouverture fret de 2009 déjà en base.',
    ],

    // 472000 Ligne de Ploërmel à La Brohinière (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '472000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '2018-01-01',
        'precision' => 'year',
        'from' => 'Mauron',
        'to' => 'La Brohinière',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Plo%C3%ABrmel_%C3%A0_La_Brohini%C3%A8re&oldid=236290327',
        'quote' => 'La section de Mauron à La Brohinière, réhabilité en ligne de fret, a rouvert en 2009. Son trafic, plus difficile à générer que prévu, n\'a pas de circulations depuis 2018.',
        'reason' => 'Fin du fret après la réouverture de 2009 : plus aucune circulation depuis 2018 (source citée : Les Échos, 17/09/2018).',
    ],

    // 472000 Ligne de Ploërmel à La Brohinière (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '472000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '2018-01-01',
        'precision' => 'year',
        'from' => 'Mauron',
        'to' => 'La Brohinière',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Plo%C3%ABrmel_%C3%A0_La_Brohini%C3%A8re&oldid=236290327',
        'quote' => 'La section de Mauron à La Brohinière, réhabilité en ligne de fret, a rouvert en 2009. Son trafic, plus difficile à générer que prévu, n\'a pas de circulations depuis 2018.',
        'reason' => 'Ligne uniquement fret : l\'arrêt des circulations en 2018 est la fin de tout trafic, section aujourd\'hui Neutralisée.',
    ],

    // 475000 Ligne de St-Brieuc à Pontivy (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '475000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '2006-08-31',
        'precision' => 'day',
        'from' => 'Saint-Brieuc',
        'to' => 'Loudéac',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Saint-Brieuc_%C3%A0_Pontivy&oldid=239795109',
        'quote' => 'Sur la section de Saint-Brieuc à Loudéac, devenue gare terminus, le trafic voyageurs se poursuit jusqu\'au 31 août 2006',
        'reason' => 'Dernier jour du service voyageurs Saint-Brieuc - Loudéac, jamais rétabli ensuite (lignes-oubliées donne 01/09/2006).',
    ],

    // 475000 Ligne de St-Brieuc à Pontivy (reconstruit depuis la base du 28/10/2026 ; citation d'origine perdue, source = celle de l'événement)
    [
        'line' => '475000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'reopening', 'date' => '2007-02-28'],
        'label' => 'Réouverture au fret seul (travaux du pont-rail de la RN 164)',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Saint-Brieuc_%C3%A0_Pontivy&oldid=239795109',
        'quote' => '',
        'reason' => 'La réouverture du 28/02/2007 après les travaux du pont-rail de la RN 164 n\'a concerné que le fret entre Saint-Brieuc et Loudéac ; en « reopening » générique non résolu, elle rétablit à tort le trafic voyageurs (passage en mixte en 2010-2030 des portions Saint-Gérand - Pontivy et de Saint-Brieuc, voyageurs fermés depuis 1987/2006).',
    ],

    // 475000 Ligne de St-Brieuc à Pontivy (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '475000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '2012-01-01',
        'precision' => 'year',
        'from' => 'Saint-Brieuc',
        'to' => 'Loudéac',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Saint-Brieuc_%C3%A0_Pontivy&oldid=239795109',
        'quote' => 'Le trafic de marchandises s\'arrête en 2012 entre Saint-Brieuc et Loudéac.',
        'reason' => 'Fin du fret sur Saint-Brieuc - Loudéac (confirmé par Reporterre, 18/06/2025 : « 2012 a sonné le glas du fret »).',
    ],

    // 475000 Ligne de St-Brieuc à Pontivy (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '475000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '2017-01-18',
        'precision' => 'day',
        'from' => 'Saint-Brieuc',
        'to' => 'Loudéac',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Saint-Brieuc_%C3%A0_Pontivy&oldid=239795109',
        'quote' => 'L\'exploitation de la ligne est suspendue, entre Saint-Brieuc et Loudéac, depuis le 18 janvier 2017.',
        'reason' => 'Suspension durable de tout trafic (trains touristiques CFCB compris) après le déraillement d\'un chariot de mesure, jamais levée : la section est aujourd\'hui Neutralisée.',
    ],

    // 484000 Ligne de Carhaix à Rosporden : fermée aux voyageurs le 10/04/1967 et aux marchandises le 03/07/1967 : seule la fin des voyageurs était connue (le fret durait jusqu'au déclassement de 1988).
    [
        'line' => '484000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1967-04-10',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Carhaix_%C3%A0_Rosporden&oldid=230774479',
        'quote' => 'Elle a été fermée au service des voyageurs le 10 avril 1967 et au service des marchandises le 3 juillet suivant',
        'reason' => 'Fin du service voyageurs sur toute la ligne (chronologie de fin complète).',
    ],

    [
        'line' => '484000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1967-07-03',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Carhaix_%C3%A0_Rosporden&oldid=230774479',
        'quote' => 'Elle a été fermée au service des voyageurs le 10 avril 1967 et au service des marchandises le 3 juillet suivant',
        'reason' => 'Fin du fret, dernier trafic de la ligne métrique.',
    ],

    // 529000 Ligne de Fontenay-le-Comte à Benet (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '529000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1969-03-02',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Fontenay-le-Comte_%C3%A0_Benet&oldid=238759742',
        'quote' => 'La gare fut fermée au trafic des voyageurs le 2 mars 1969 en même temps que la gare de Fontenay-le-Comte et fut remplacée par des autocars.',
        'reason' => 'Fin du service voyageurs Niort - Fontenay-le-Comte par Benet (confirmé par Gare de Benet : « La ligne Niort-Fontenay-le-Comte cessa son activité pour les voyageurs en 1969 »).',
    ],

    // 529000 Ligne de Fontenay-le-Comte à Benet (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '529000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1996-09-23',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Fontenay-le-Comte_%C3%A0_Benet&oldid=238759742',
        'quote' => 'Elle fut fermée au trafic de marchandises le 23 septembre 1996.',
        'reason' => 'Fin du fret sur la ligne (confirmé par Gare de Benet et Gare de Coulon : fret jusqu\'en 1996).',
    ],

    // 529000 Ligne de Fontenay-le-Comte à Benet (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '529000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1996-09-23',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Fontenay-le-Comte_%C3%A0_Benet&oldid=238759742',
        'quote' => 'Elle fut fermée au trafic de marchandises le 23 septembre 1996.',
        'reason' => 'Dernier trafic commercial de la ligne, aujourd\'hui Neutralisée chez SNCF Réseau.',
    ],

    // 547000 Ligne de St-Mariens-St-Yzan à Blaye (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '547000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1938-12-05',
        'precision' => 'day',
        'from' => 'Saint-Mariens-Saint-Yzan',
        'to' => 'Blaye',
        'pk_start' => 600,
        'pk_end' => 24204,
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Saint-Mariens_-_Saint-Yzan_%C3%A0_Blaye&oldid=239121113',
        'quote' => 'C\'est le cas pour la ligne de Blaye à Saint-Mariens - Saint-Yzan à partir du 5 décembre 1938, qui n\'est plus parcourue que par un trafic de fret de façon sporadique',
        'reason' => 'fin du service voyageurs, reprise ici car la fermeture manuelle masque les autres sources sur cette portion',
    ],

    // 547000 Ligne de St-Mariens-St-Yzan à Blaye (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '547000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '2004-01-01',
        'precision' => 'year',
        'from' => 'Saint-Mariens-Saint-Yzan',
        'to' => 'Blaye',
        'pk_start' => 600,
        'pk_end' => 24204,
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Saint-Mariens_-_Saint-Yzan_%C3%A0_Blaye&oldid=239121113',
        'quote' => 'La ligne est fermée à toute circulation en 2004, mais la voie est conservée.',
        'reason' => 'fin de tout trafic (fret) sur la ligne, voie conservée et aujourd\'hui neutralisée selon SNCF Réseau',
    ],

    // 555000 Ligne de Beaulieu-le-Coudray à Auneau-Embranchement (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '555000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1939-08-01',
        'precision' => 'day',
        'from' => 'Beaulieu-le-Coudray',
        'to' => 'Auneau-Embranchement',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Beaulieu-le-Coudray_%C3%A0_Auneau-Embranchement&oldid=239299942',
        'quote' => 'Elle est fermée au trafic voyageurs le 1er aout 1939 et au trafic marchandises sur la section Beaulieu-le-Coudray - Auneau-Ville le 5 juillet 1971.',
        'reason' => 'Fin du service voyageurs sur toute la ligne, absente de la base.',
    ],

    // 555000 Ligne de Beaulieu-le-Coudray à Auneau-Embranchement (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '555000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1971-07-05',
        'precision' => 'day',
        'from' => 'Beaulieu-le-Coudray',
        'to' => 'Auneau-Ville',
        'pk_start' => 1450,
        'pk_end' => 19500,
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Beaulieu-le-Coudray_%C3%A0_Auneau-Embranchement&oldid=239299942',
        'quote' => 'Elle est fermée au trafic voyageurs le 1er aout 1939 et au trafic marchandises sur la section Beaulieu-le-Coudray - Auneau-Ville le 5 juillet 1971.',
        'reason' => 'Déjà en base (wikipedia), repris en manual ; le fret subsiste sur PK 0,043 à 1,450 (desserte du dépôt céréalier de Gellainville, SNCF Exploitée).',
    ],

    // 555000 Ligne de Beaulieu-le-Coudray à Auneau-Embranchement (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '555000',
        'action' => 'add',
        'type' => 'declassification',
        'date' => '1975-02-24',
        'precision' => 'day',
        'from' => 'Beaulieu-le-Coudray',
        'to' => 'Auneau-Ville',
        'pk_start' => 1450,
        'pk_end' => 19500,
        'source_url' => 'https://www.lignes-oubliees.com/fichiers/declassements.pdf',
        'quote' => 'Beaulieu-le-Coudray - Auneau-Ville Totalité de la ligne 1,450 19,500 18,050 IG DECL 24/02/1975',
        'reason' => 'Déclassement de la section PK 1,450 à 19,500 (SNCF : Déclassée non vendue).',
    ],

    // 555000 Ligne de Beaulieu-le-Coudray à Auneau-Embranchement (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '555000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '2006-09-14',
        'precision' => 'day',
        'from' => 'Auneau',
        'to' => 'Auneau',
        'pk_start' => 19500,
        'pk_end' => 20162,
        'source_url' => 'https://www.lignes-oubliees.com/fichiers/fermetures.pdf',
        'quote' => 'Beaulieu-le-Coudray - Auneau-Embranchement Section à Auneau 19,500 20,162 0,662 IG 14/09/2006',
        'reason' => 'Déjà en base (lignes-oubliees), repris en manual car le closure_passenger manual de toute la ligne masquerait cette décision de fermeture (PK 19,500 à 20,162).',
    ],

    // 599000 Ligne de Châtellerault à Launay (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '599000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1939-09-15',
        'precision' => 'day',
        'from' => 'Châtellerault',
        'to' => 'Tournon-Saint-Martin',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Ch%C3%A2tellerault_%C3%A0_Launay&oldid=218150140',
        'quote' => 'Elle est fermée au service voyageur le 15 septembre 1939.',
        'reason' => 'fin du service voyageurs sur toute la ligne',
    ],

    // 599000 Ligne de Châtellerault à Launay (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '599000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1951-10-07',
        'precision' => 'day',
        'from' => 'Châtellerault',
        'to' => 'Pleumartin',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Ch%C3%A2tellerault_%C3%A0_Launay&oldid=218150140',
        'quote' => 'Le service marchandises est fermé le 7 octobre 1951 entre Châtellerault et Pleumartin puis le 16 décembre 1973 sur le tronçon restant entre Pleumartin et la bifurcation de Launay',
        'reason' => 'fin du fret, dernier trafic commercial entre Châtellerault et Pleumartin',
    ],

    // 599000 Ligne de Châtellerault à Launay (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '599000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1973-12-16',
        'precision' => 'day',
        'from' => 'Pleumartin',
        'to' => 'Tournon-Saint-Martin',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Ch%C3%A2tellerault_%C3%A0_Launay&oldid=218150140',
        'quote' => 'Le service marchandises est fermé le 7 octobre 1951 entre Châtellerault et Pleumartin puis le 16 décembre 1973 sur le tronçon restant entre Pleumartin et la bifurcation de Launay',
        'reason' => 'fin du fret, dernier trafic commercial entre Pleumartin et la bifurcation de Launay (à un peu plus de 2 km au nord de la gare de Tournon-Saint-Martin, nom retenu pour le géocodage)',
    ],

    // 613000 Ligne de Nexon à Brive-la-Gaillarde (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '613000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '2018-02-27',
        'precision' => 'day',
        'from' => 'Saint-Yrieix',
        'to' => 'Objat',
        'pk_start' => 442800,
        'pk_end' => 484373,
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Gare_de_Pompadour&oldid=235033816',
        'quote' => 'Depuis le 27 février 2018 au soir, en raison d’un affaissement de voie, les trains ne circulent plus entre Saint-Yrieix et Objat, la gare n\'est donc plus desservie par aucun train depuis cette date',
        'reason' => 'suspension durable de toute circulation (dernier train le 27 février 2018 au soir), section neutralisée et remplacée par un transport à la demande',
    ],

    // 617000 Ligne du Quéroy à Thiviers (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '617000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1881-12-30',
        'precision' => 'day',
        'from' => 'Le Quéroy-Pranzac',
        'to' => 'Saint-Martin-le-Pin',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_du_Qu%C3%A9roy-Pranzac_%C3%A0_Thiviers&oldid=235343245',
        'quote' => 'Le Quéroy-Pranzac - Saint-Martin-le-Pin (PK 504,516 à 533,356) : 30 décembre 1881',
        'reason' => 'ouverture manquante en base (seules les concessions y figurent)',
    ],

    // 617000 Ligne du Quéroy à Thiviers (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '617000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1883-08-05',
        'precision' => 'day',
        'from' => 'Saint-Martin-le-Pin',
        'to' => 'Nontron',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_du_Qu%C3%A9roy-Pranzac_%C3%A0_Thiviers&oldid=235343245',
        'quote' => 'Saint-Martin-le-Pin - Nontron (extension depuis PK 533,356 à 539,461) : 5 août 1883',
        'reason' => 'ouverture manquante en base',
    ],

    // 617000 Ligne du Quéroy à Thiviers (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '617000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1892-05-30',
        'precision' => 'day',
        'from' => 'Nontron',
        'to' => 'Thiviers',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_du_Qu%C3%A9roy-Pranzac_%C3%A0_Thiviers&oldid=235343245',
        'quote' => 'Nontron - Thiviers (PK 539,461 à 566,870) : 30 mai 1892',
        'reason' => 'ouverture manquante en base',
    ],

    // 617000 Ligne du Quéroy à Thiviers (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '617000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1940-06-27',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_du_Qu%C3%A9roy-Pranzac_%C3%A0_Thiviers&oldid=235343245',
        'quote' => 'Le 27 juin 1940, la ligne était fermée au trafic des voyageurs, mais un service voyageurs sera maintenu entre Thiviers et Marthon durant quasiment toute la durée de la guerre.',
        'reason' => 'fin du service voyageurs sur toute la ligne (service de guerre Thiviers - Marthon sans date de fin connue)',
    ],

    // 617000 Ligne du Quéroy à Thiviers (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '617000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1952-05-18',
        'precision' => 'day',
        'from' => 'Nontron',
        'to' => 'Saint-Pardoux-la-Rivière',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_du_Qu%C3%A9roy-Pranzac_%C3%A0_Thiviers&oldid=235343245',
        'quote' => 'De Nontron à Saint-Pardoux-la-Rivière (PK 540,600 à 546,600) : 18 mai 1952',
        'reason' => 'fin du fret, dernier trafic commercial de Nontron à Saint-Pardoux-la-Rivière',
    ],

    // 617000 Ligne du Quéroy à Thiviers (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '617000',
        'action' => 'add',
        'type' => 'declassification',
        'date' => '1965-12-07',
        'precision' => 'day',
        'from' => 'Nontron',
        'to' => 'Saint-Pardoux-la-Rivière',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_du_Qu%C3%A9roy-Pranzac_%C3%A0_Thiviers&oldid=235343245',
        'quote' => 'De Nontron à Saint-Pardoux-la-Rivière (PK 540,600 à 546,600), le 7 décembre 1965',
        'reason' => 'déclassement par décret',
    ],

    // 617000 Ligne du Quéroy à Thiviers (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '617000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1971-02-01',
        'precision' => 'day',
        'from' => 'Saint-Pardoux-la-Rivière',
        'to' => 'Planeaux',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_du_Qu%C3%A9roy-Pranzac_%C3%A0_Thiviers&oldid=235343245',
        'quote' => 'De Saint-Pardoux-la-Rivière aux Carrières de Planeaux de Thiviers (PK 546,600 à 564,500) : 1er février 1971',
        'reason' => 'fin du fret, dernier trafic commercial de Saint-Pardoux-la-Rivière aux carrières de Planeaux (lieu-dit Planeaux, commune de Thiviers)',
    ],

    // 617000 Ligne du Quéroy à Thiviers (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '617000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1971-10-31',
        'precision' => 'day',
        'from' => 'Marthon',
        'to' => 'Nontron',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_du_Qu%C3%A9roy-Pranzac_%C3%A0_Thiviers&oldid=235343245',
        'quote' => 'De Marthon à Nontron (PK 518,200 à 540,600) : 31 octobre 1971',
        'reason' => 'fin du fret, dernier trafic commercial de Marthon à Nontron',
    ],

    // 617000 Ligne du Quéroy à Thiviers (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '617000',
        'action' => 'add',
        'type' => 'declassification',
        'date' => '1976-02-18',
        'precision' => 'day',
        'from' => 'Marthon',
        'to' => 'Nontron',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_du_Qu%C3%A9roy-Pranzac_%C3%A0_Thiviers&oldid=235343245',
        'quote' => 'De Marthon à Nontron (PK 518,200 à 540,600), le 18 février 1976',
        'reason' => 'déclassement par décret',
    ],

    // 617000 Ligne du Quéroy à Thiviers (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '617000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1984-01-01',
        'precision' => 'year',
        'from' => 'Le Quéroy-Pranzac',
        'to' => 'Marthon',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_du_Qu%C3%A9roy-Pranzac_%C3%A0_Thiviers&oldid=235343245',
        'quote' => 'Du Quéroy-Pranzac à Marthon (PK 501,234 à 518,200) : 1984',
        'reason' => 'fin du fret, dernier trafic commercial du Quéroy-Pranzac à Marthon',
    ],

    // 617000 Ligne du Quéroy à Thiviers (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '617000',
        'action' => 'add',
        'type' => 'declassification',
        'date' => '1991-02-22',
        'precision' => 'day',
        'from' => 'Saint-Pardoux-la-Rivière',
        'to' => 'Planeaux',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_du_Qu%C3%A9roy-Pranzac_%C3%A0_Thiviers&oldid=235343245',
        'quote' => 'De Saint-Pardoux-la-Rivière à Thiviers (PK 546,600 à 564,500), le 22 février 1991',
        'reason' => 'déclassement par décret, jusqu\'au PK 564,5 (carrières de Planeaux) et non jusqu\'à la gare de Thiviers',
    ],

    // 617000 Ligne du Quéroy à Thiviers (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '617000',
        'action' => 'add',
        'type' => 'declassification',
        'date' => '1992-12-09',
        'precision' => 'day',
        'from' => 'Le Quéroy-Pranzac',
        'to' => 'Marthon',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_du_Qu%C3%A9roy-Pranzac_%C3%A0_Thiviers&oldid=235343245',
        'quote' => 'Du Quéroy-Pranzac à Marthon (PK 501,234 à 518,200), le 9 décembre 1992',
        'reason' => 'déclassement par décret',
    ],

    // 617000 Ligne du Quéroy à Thiviers (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '617000',
        'action' => 'add',
        'type' => 'conversion',
        'date' => '2000-01-01',
        'precision' => 'year',
        'from' => 'Saint-Pardoux-la-Rivière',
        'to' => 'Planeaux',
        'reuse' => 'greenway',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_du_Qu%C3%A9roy-Pranzac_%C3%A0_Thiviers&oldid=235343245',
        'quote' => 'En Dordogne, entre Saint-Pardoux-la-Rivière et les carrières de Planeaulx à Thiviers, elle est aménagée en voie verte en 2000-2001.',
        'reason' => 'emprise aménagée en voie verte',
    ],

    // 617000 Ligne du Quéroy à Thiviers (reconstruit depuis la base du 28/10/2026 ; citation d'origine perdue, source = celle de l'événement)
    [
        'line' => '617000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '2016-10-01', 'from' => 'Saint-Jean-de-Côle'],
        'label' => 'Inauguration d\'un sentier sur l\'emprise',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_du_Qu%C3%A9roy-Pranzac_%C3%A0_Thiviers&oldid=235343245',
        'quote' => '',
        'reason' => 'inauguration d\'un sentier d\'interprétation sur l\'emprise d\'une section fermée au fret en 1971 et déclassée en 1991, pas une ouverture ferroviaire',
    ],

    // 617000 Ligne du Quéroy à Thiviers (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '617000',
        'action' => 'add',
        'type' => 'conversion',
        'date' => '2023-01-01',
        'precision' => 'year',
        'from' => 'Javerlhac',
        'to' => 'Nontron',
        'reuse' => 'greenway',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_du_Qu%C3%A9roy-Pranzac_%C3%A0_Thiviers&oldid=235343245',
        'quote' => 'Le réaménagement est en cours jusqu\'à Nontron (section Javerlhac - Nontron effective en 2023).',
        'reason' => 'emprise aménagée en voie verte (Flow Vélo)',
    ],

    // 617000 Ligne du Quéroy à Thiviers (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '617000',
        'action' => 'add',
        'type' => 'conversion',
        'date' => '2024-01-01',
        'precision' => 'year',
        'from' => 'Nontron',
        'to' => 'Saint-Pardoux-la-Rivière',
        'reuse' => 'greenway',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_du_Qu%C3%A9roy-Pranzac_%C3%A0_Thiviers&oldid=235343245',
        'quote' => 'Le tronçon entre Nontron et Saint-Pardoux-la-Rivière a été achevé en 2024, avec de courtes déviations là où la plate-forme de voie a été éliminée',
        'reason' => 'emprise aménagée en voie verte (Flow Vélo)',
    ],

    // 652000 Ligne de Morcenx à Bagnères-de-Bigorre (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '652000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1970-10-12',
        'precision' => 'day',
        'from' => 'Mont-de-Marsan',
        'to' => 'Tarbes',
        'pk_start' => 149000,
        'pk_end' => 244300,
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Morcenx_%C3%A0_Bagn%C3%A8res-de-Bigorre&oldid=240485101',
        'quote' => 'La ligne est fermée au service voyageurs le 27 septembre 1970 de Tarbes à Bagnères-de-Bigorre et le 12 octobre 1970 de Mont-de-Marsan à Tarbes',
        'reason' => 'fin du service voyageurs de Mont-de-Marsan à Tarbes, reprise ici car la fermeture manuelle masque les autres sources sur cette portion',
    ],

    // 652000 Ligne de Morcenx à Bagnères-de-Bigorre (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '652000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '2014-01-01',
        'precision' => 'year',
        'from' => 'Mont-de-Marsan',
        'to' => 'Tarbes',
        'pk_start' => 149000,
        'pk_end' => 244300,
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Gare_de_Mont-de-Marsan&oldid=236918959',
        'quote' => 'une ligne vers Tarbes (ligne qui était ouverte au fret, jusqu\'à fermeture en 2014)',
        'reason' => 'fin du fret, dernier trafic commercial de la section Mont-de-Marsan - Tarbes, aujourd\'hui neutralisée (PK 149 à 187,8) puis fermée avec maintien de la voie (PK 187,8 à 244,3) selon SNCF Réseau',
    ],

    // 714000 Ligne d’Uzerche à Tulle (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '714000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1969-11-03',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=PO-Corr%C3%A8ze&oldid=235614381',
        'quote' => 'L\'ensemble des lignes a été ouvert en 1904 et fermé le 3 novembre 1969 au trafic voyageur et le 31 mai 1970, à celui des marchandises.',
        'reason' => 'fin du service voyageurs sur toute la ligne (fermeture de tout le réseau PO-Corrèze)',
    ],

    // 714000 Ligne d’Uzerche à Tulle (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '714000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1970-05-31',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=PO-Corr%C3%A8ze&oldid=235614381',
        'quote' => 'L\'ensemble des lignes a été ouvert en 1904 et fermé le 3 novembre 1969 au trafic voyageur et le 31 mai 1970, à celui des marchandises.',
        'reason' => 'fin du fret, dernier trafic commercial sur toute la ligne',
    ],

    // 714000 Ligne d’Uzerche à Tulle (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '714000',
        'action' => 'add',
        'type' => 'declassification',
        'date' => '1972-01-14',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=PO-Corr%C3%A8ze&oldid=235614381',
        'quote' => 'L\'ensemble des lignes est déclassé par décret le 14 janvier 1972',
        'reason' => 'déclassement de toute la ligne avec l\'ensemble du réseau PO-Corrèze',
    ],

    // 732000 Ligne de Vias à Lodève (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '732000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1957-06-28',
        'precision' => 'day',
        'from' => 'Vias',
        'to' => 'Lézignan-la-Cèbe',
        'pk_start' => 449300,
        'pk_end' => 472400,
        'source_url' => 'https://www.lignes-oubliees.com/index.php?page=lodeve',
        'quote' => 'Vias - Paulhan : 28/06/1957',
        'reason' => 'Rappel de la fin du service voyageurs sur la section (fiche « Fermeture aux voyageurs »), pour donner la chronologie de fin complète de la portion Vias - Lézignan-la-Cèbe.',
    ],

    // 732000 Ligne de Vias à Lodève (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '732000',
        'action' => 'add',
        'type' => 'heritage_start',
        'date' => '2010-01-01',
        'precision' => 'year',
        'from' => 'Saint-Thibéry',
        'to' => 'Nézignan-l\'Évêque',
        'pk_start' => 458295,
        'pk_end' => 462968,
        'heritage_kind' => 'velorail',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Vias_%C3%A0_Lod%C3%A8ve&oldid=239824923',
        'quote' => 'Elle supportait de façon très sporadique du trafic fret pour permettre l\'expédition de la production d\'une carrière, mais il n\'y a plus aucune circulation depuis 2010. À partir de la même année, des vélo-rails (service touristique) roulent sur une courte section',
        'reason' => 'Vélorail (Pédalorail) sur environ 3,5 km de Saint-Thibéry à Nézignan-l\'Évêque depuis 2010 ; PK indicatifs = section SNCF « Fermée et mise à disposition de tiers ».',
    ],

    // 732000 Ligne de Vias à Lodève (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '732000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '2010-09-01',
        'precision' => 'month',
        'from' => 'Vias',
        'to' => 'Lézignan-la-Cèbe',
        'pk_start' => 449300,
        'pk_end' => 472400,
        'source_url' => 'https://www.lignes-oubliees.com/index.php?page=lodeve',
        'quote' => 'Le trafic sur cette section reste très sporadique pour la desserte d\'une carrière. Les derniers trains ont circulé en septembre 2010.',
        'reason' => 'Note (3) de la fiche, rattachée à « Vias - Lézignan-La Cèbe : service marchandises » : derniers trains de fret en septembre 2010.',
    ],

    // 732000 Ligne de Vias à Lodève (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '732000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '2010-09-01',
        'precision' => 'month',
        'from' => 'Vias',
        'to' => 'Lézignan-la-Cèbe',
        'pk_start' => 449300,
        'pk_end' => 472400,
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Vias_%C3%A0_Lod%C3%A8ve&oldid=239824923',
        'quote' => 'La mise en place d\'une circulation régulière de fret (wagons-trémie) au cours des mois d\'août et de septembre 2010, pour la production de la carrière de Bessan, est la dernière activité de la ligne.',
        'reason' => 'Dernière circulation commerciale de la ligne en septembre 2010 ; aucune circulation depuis (section ensuite neutralisée ou fermée selon SNCF Réseau).',
    ],

    // 732000 Ligne de Vias à Lodève (reconstruit depuis la base du 28/10/2026 ; citation d'origine perdue, source = celle de l'événement)
    [
        'line' => '732000',
        'action' => 'neutralize',
        'match' => ['source' => 'heritage', 'type' => 'heritage_start', 'date' => '2026-01-01'],
        'label' => 'Date par défaut de la liste des exploitations patrimoniales (vélorail ouvert en 2010)',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_des_chemins_de_fer_touristiques_de_France&oldid=239256383',
        'quote' => '',
        'reason' => 'Date par défaut (« date de début non trouvée ; en activité en 2026 ») : le vélorail de Saint-Thibéry roule depuis 2010, remplacé par l\'événement manual heritage_start 2010.',
    ],

    // 747000 Ligne de Bourron-Marlotte-Grez à Malesherbes (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '747000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1937-08-01',
        'precision' => 'day',
        'from' => 'Bourron-Marlotte - Grez',
        'to' => 'Malesherbes',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Bourron-Marlotte_-_Grez_%C3%A0_Malesherbes&oldid=235113078',
        'quote' => 'Le service voyageurs est supprimé le 1er août 1937 par le PLM.',
        'reason' => 'Fin du service voyageurs sur toute la ligne en 1937, absente de la base.',
    ],

    // 747000 Ligne de Bourron-Marlotte-Grez à Malesherbes (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '747000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1950-03-15',
        'precision' => 'day',
        'from' => 'Bourron-Marlotte - Grez',
        'to' => 'La Chapelle-la-Reine',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Bourron-Marlotte_-_Grez_%C3%A0_Malesherbes&oldid=235113078',
        'quote' => 'La section de Bourron-Marlotte - Grez à La Chapelle-la-Reine est neutralisée le 15 mars 1950, date de fin du trafic marchandise sur cette section.',
        'reason' => 'Neutralisation = fin de tout trafic (les voyageurs étaient déjà supprimés depuis 1937).',
    ],

    // 747000 Ligne de Bourron-Marlotte-Grez à Malesherbes (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '747000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1950-03-15',
        'precision' => 'day',
        'from' => 'Bourron-Marlotte - Grez',
        'to' => 'La Chapelle-la-Reine',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Bourron-Marlotte_-_Grez_%C3%A0_Malesherbes&oldid=235113078',
        'quote' => 'La section de Bourron-Marlotte - Grez à La Chapelle-la-Reine est neutralisée le 15 mars 1950, date de fin du trafic marchandise sur cette section.',
        'reason' => 'Événement déjà en base (wikipedia), repris en manual pour que la chronologie de fin de cette portion reste complète.',
    ],

    // 780000 Ligne de St-Étienne-La Terrasse à St-Étienne-Pont-de-l'Âne (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '780000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '2016-12-20',
        'precision' => 'day',
        'from' => 'Saint-Étienne',
        'to' => 'Saint-Étienne',
        'pk_start' => 470,
        'pk_end' => 2155,
        'source_url' => 'https://www.lignes-oubliees.com/fichiers/fermetures.pdf',
        'quote' => 'Saint-Etienne la Terrasse - Saint-Etienne Pont-de-l’Ane | Section à Saint-Etienne | 0,470 | 2,155 | 1,685 | IG | 078 000 | 20/12/2016',
        'reason' => 'Décision de fermeture de la section PK 0,470-2,155 (« Section à Saint-Étienne ») le 20 décembre 2016 ; date de décision, la dernière circulation n\'est pas connue.',
    ],

    // 901000 Ligne de Lyon-Part-Dieu à Montalieu-Vercieu (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '901000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1881-10-23',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_l%27Est_de_Lyon&oldid=240183241',
        'quote' => 'De Lyon (gare de l\'Est) à Saint-Génix-d\'Aoste le 23 octobre 1881. D\'une longueur de 72 km et comportant 21 gares ;',
        'reason' => 'Ouverture de la ligne principale du CFEL depuis Lyon (gare de l\'Est), qui comprend la section Lyon - Meyzieu.',
    ],

    // 901000 Ligne de Lyon-Part-Dieu à Montalieu-Vercieu (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '901000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1947-12-31',
        'precision' => 'day',
        'from' => 'Lyon-Part-Dieu',
        'to' => 'Meyzieu-ZI',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_l%27Est_de_Lyon&oldid=240183241',
        'quote' => 'trafic voyageur (ensemble du réseau) | 31 décembre 1947',
        'reason' => 'Fin définitive du service voyageurs sur tout le réseau CFEL (tableau « Fin de l\'exploitation »).',
    ],

    // 901000 Ligne de Lyon-Part-Dieu à Montalieu-Vercieu (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '901000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1999-01-01',
        'precision' => 'year',
        'from' => 'Décines',
        'to' => 'Meyzieu-ZI',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_l%27Est_de_Lyon&oldid=240183241',
        'quote' => 'Le département du Rhône confie alors l\'exploitation à la SNCF, de la section de ligne, Lyon -Meyzieu Z.I., soit 15 km, jusqu\'en 1999, époque où la SNCF n\'exploite plus que le tronçon Lyon Part-Dieu - Décines',
        'reason' => 'Plus de voyageurs depuis 1947 : la fin du fret en 1999 est la fin de tout trafic sur Décines - Meyzieu-ZI.',
    ],

    // 901000 Ligne de Lyon-Part-Dieu à Montalieu-Vercieu (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '901000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1999-01-01',
        'precision' => 'year',
        'from' => 'Décines',
        'to' => 'Meyzieu-ZI',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_l%27Est_de_Lyon&oldid=240183241',
        'quote' => 'Décines - Meyzieu-ZI | 1999 (fret)',
        'reason' => 'Fin du fret entre Décines et Meyzieu-ZI en 1999 (la SNCF n\'exploite plus ensuite que Lyon Part-Dieu - Décines).',
    ],

    // 901000 Ligne de Lyon-Part-Dieu à Montalieu-Vercieu (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '901000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '2003-12-01',
        'precision' => 'month',
        'from' => 'Lyon-Part-Dieu',
        'to' => 'Décines',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_l%27Est_de_Lyon&oldid=240183241',
        'quote' => 'Après une fin d\'exploitation progressive jusqu\'en décembre 2003, l\'emprise de la ligne comprise entre la gare de Lyon-Part-Dieu et Meyzieu Z.I. a été transformée pour permettre d\'y faire circuler la ligne T3 du tramway de Lyon',
        'reason' => 'Dernier trafic ferroviaire de la ligne en décembre 2003, avant les travaux du tramway.',
    ],

    // 901000 Ligne de Lyon-Part-Dieu à Montalieu-Vercieu (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '901000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '2003-12-01',
        'precision' => 'month',
        'from' => 'Lyon-Part-Dieu',
        'to' => 'Décines',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_l%27Est_de_Lyon&oldid=240183241',
        'quote' => 'Lyon-Part-Dieu - Décines | Décembre 2003',
        'reason' => 'Fin du trafic (fret, voie mère d\'embranchement) entre Lyon-Part-Dieu et Décines en décembre 2003.',
    ],

    // 901000 Ligne de Lyon-Part-Dieu à Montalieu-Vercieu (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '901000',
        'action' => 'add',
        'type' => 'conversion',
        'date' => '2006-12-05',
        'precision' => 'day',
        'from' => 'Lyon-Part-Dieu',
        'to' => 'Meyzieu-ZI',
        'reuse' => 'urban',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_3_du_tramway_de_Lyon&oldid=239130788',
        'quote' => 'La ligne 3 du tramway de Lyon, plus simplement nommée T3, également appelée LEA (pour Ligne de l\'Est de l\'Agglomération), est une ligne de tramway de la métropole de Lyon ouverte le 5 décembre 2006 entre la gare de la Part-Dieu à Lyon et la zone industrielle de Meyzieu',
        'reason' => 'L\'emprise Lyon-Part-Dieu - Meyzieu Z.I. est réemployée par le tramway T3 mis en service le 5 décembre 2006 (ce n\'est pas une ouverture de la ligne ferroviaire).',
    ],

    // 937000 Ligne de Marseille-Blancarde à Marseille-Prado (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '937000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1964-05-31',
        'precision' => 'day',
        'from' => 'Marseille-Prado',
        'to' => 'Marseille-Vieux-Port',
        'source_url' => 'https://www.lignes-oubliees.com/index.php?page=marseillevp',
        'quote' => 'Marseille-Prado - Marseille-Vieux-Port : 31/05/1964',
        'reason' => 'Fin du fret (seul trafic de la ligne du tunnel, ouverte aux marchandises en 1878) entre la gare du Prado et le Vieux-Port.',
    ],

    // 937000 Ligne de Marseille-Blancarde à Marseille-Prado (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '937000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1964-05-31',
        'precision' => 'day',
        'from' => 'Marseille-Prado',
        'to' => 'Marseille-Vieux-Port',
        'source_url' => 'https://www.lignes-oubliees.com/index.php?page=marseillevp',
        'quote' => 'Marseille-Prado - Marseille-Vieux-Port : 31/05/1964',
        'reason' => 'Ligne marchandises seulement : la fin du fret le 31 mai 1964 est la fin de tout trafic sur le tunnel Prado - Carénage.',
    ],

    // 937000 Ligne de Marseille-Blancarde à Marseille-Prado (reconstruit depuis la base du 28/10/2026)
    [
        'line' => '937000',
        'action' => 'add',
        'type' => 'conversion',
        'date' => '1993-01-01',
        'precision' => 'year',
        'from' => 'Marseille-Prado',
        'to' => 'Marseille-Vieux-Port',
        'reuse' => 'road',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Tunnel_Prado-Car%C3%A9nage&oldid=238564300',
        'quote' => 'Il a été ouvert à la circulation en 1993. Ce tunnel réutilise un ancien tunnel ferroviaire et a été prolongé par le tunnel Louis-Rège.',
        'reason' => 'Le tunnel ferroviaire Prado - Carénage est réutilisé par le tunnel routier ouvert en 1993.',
    ],
];
