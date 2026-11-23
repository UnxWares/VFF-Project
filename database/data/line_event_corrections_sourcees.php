<?php

/**
 * Corrections SOURCÉES du chantier « données à sourcer » (2026-10-20) : chutes de km sans
 * événement (invariant lines.km_drop_without_event), dates en conflit (705000), fausses dates
 * appliquées à toute une ligne (4171).
 *
 * Recherches et citations vérifiées : storage/app/reports/agents/donnees-sourcees/research-{A,B,C}.json
 * (pages lues gardées dans work-A, work-B, work-C). Même format que line_event_corrections.php :
 * permalien Wikipédia (oldid) ou URL exacte de la fiche, citation exacte, raison. Pas de tiret long.
 *
 * @return list<array<string, mixed>>
 */
$massifCentral179 = 'http://www.massifcentralferroviaire.com/fiches/fichesec.php?VARsectionsID=179';

return [

    // 705000 Montluçon - Moulins, section Commentry - Moulins : fin des voyageurs en 1972, pas en 1969.
    // « En 1969 … reporté sur la route » (livre de 2005 cité par l'article) est contredit par l'article
    // lui-même (« plus de trafic voyageurs entre Commentry et Moulins depuis 1972 »), par lignes-oubliees
    // (06/03/1972), par les articles des gares de Chavenon et de Souvigny (6 mars 1972) et par Raildusud
    // (« suppression du service ferroviaire voyageurs entre Moulins et Montluçon en 1972 »). Le 06/03/1972
    // de lignes-oubliees reste en base.
    [
        'line' => '705000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'closure_passenger', 'date' => '1969-01-01'],
        'label' => 'Report partiel des voyageurs sur la route',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Gare_de_Chavenon&oldid=229434482',
        'quote' => 'fermée au service des voyageurs le 6 mars 1972 par la SNCF lorsqu\'elle ferme à ce trafic la section de la gare de Commentry à celle de Moulins',
        'reason' => 'la fin du service voyageurs Commentry - Moulins est du 6 mars 1972 (quatre sources concordantes) ; 1969 n\'est qu\'un report sur la route cité d\'un livre',
    ],

    // 4171 Voiron - Saint-Béron : l'infobox « fermeture2 = 1953 » ne vise que le court tronçon résiduel
    // Saint-Béron - usine électro-métallurgique (non localisable) ; appliquée à toute la ligne, elle est
    // fausse : fin de tout trafic le 30/09/1936 (déjà en base), déclassement en 1939.
    [
        'line' => '4171',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia-reseaux', 'type' => 'closure', 'date' => '1953-01-01'],
        'label' => 'Fermeture du court tronçon de Saint-Béron à une usine',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_de_Voiron_%C3%A0_Saint-B%C3%A9ron&oldid=236510781',
        'quote' => 'Le déclassement de la ligne intervient en 1939, sauf un court tronçon entre Saint-Béron et une usine électro-métallurgique, qui fermera en 1953.',
        'reason' => 'la date de 1953 ne vaut que pour le tronçon résiduel côté Saint-Béron, pas pour la ligne fermée le 30/09/1936',
    ],

    // 136000 Saint-Louis - Huningue : la ligne se prolongeait au-delà de Huningue par le pont du Rhin
    // jusqu'à Weil am Rhein ; le pont est démonté en 1937 (année seule), fin de tout trafic sur ce tronçon.
    // Saint-Louis - Huningue reste ouverte au fret (voyageurs fermés le 31/12/1945, déjà en base).
    [
        'line' => '136000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1937-01-01',
        'precision' => 'year',
        'from' => 'Huningue',
        'to' => 'Weil am Rhein',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Saint-Louis_%C3%A0_Huningue&oldid=238408286',
        'quote' => 'Le trafic voyageurs et marchandises étant faible, le pont sur le Rhin est démonté en 1937',
        'reason' => 'fin de tout trafic au-delà de Huningue (vers le Rhin et Weil am Rhein) avec la dépose du pont',
    ],
    [
        'line' => '136000',
        'action' => 'add',
        'type' => 'dismantling',
        'date' => '1937-01-01',
        'precision' => 'year',
        'from' => 'Huningue',
        'to' => 'Weil am Rhein',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Saint-Louis_%C3%A0_Huningue&oldid=238408286',
        'quote' => 'Le trafic voyageurs et marchandises étant faible, le pont sur le Rhin est démonté en 1937',
        'reason' => 'dépose du pont sur le Rhin, qui coupe la ligne vers Weil am Rhein',
    ],

    // Vertaizon - Billom (ligne 4154, sans code RFN) : voyageurs fermés le 12/12/1960, fret le 12/12/2002
    // (« 2004 selon d'autres sources », gardé 2002, date au jour de l'inventaire) ; vélorail des Volcans
    // entre Chignat et Espirat depuis le 17/04/2013. Inventaire Massif Central Ferroviaire, section 179.
    [
        'line' => '4154',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1960-12-12',
        'precision' => 'day',
        'source_url' => $massifCentral179,
        'quote' => '12/12/1960 | Fermeture au trafic voyageurs',
        'reason' => 'fin du service voyageurs de Vertaizon à Billom',
    ],
    [
        'line' => '4154',
        'action' => 'add',
        'type' => 'closure',
        'date' => '2002-12-12',
        'precision' => 'day',
        'source_url' => $massifCentral179,
        'quote' => '12/12/2002 | Fermeture au trafic marchandises | 2004 | selon d\'autres sources.',
        'reason' => 'fin du fret, dernier trafic commercial de la ligne (2004 selon d\'autres sources)',
    ],
    [
        'line' => '4154',
        'action' => 'add',
        'type' => 'heritage_start',
        'heritage_kind' => 'velorail',
        'date' => '2013-04-17',
        'precision' => 'day',
        'from' => 'Chignat',
        'to' => 'Espirat',
        'source_url' => $massifCentral179,
        'quote' => '17/04/2013 | A noter | Mise en service d\'une exploitation de vélorails entre Chignat et Espirat.',
        'reason' => 'début du vélorail des Volcans entre Chignat et Espirat',
    ],

    // Ligne militaire de Ban-Gâvres à Pointe-Saint-Germain (2799) : réseau du polygone (voie métrique)
    // « fermé dans les années 1930 » (OHM : 1930) ; la ligne Todt Magouër - Plouhinec (voie normale,
    // 1940) cesse à la Libération (OHM : end_date 1945). Une portion portant l'ouverture de 1940 sans sa
    // fin restait « en service » jusqu'en 2020 : fin de toute exploitation de la ligne en 1945.
    [
        'line' => '2799',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1945-01-01',
        'precision' => 'year',
        'source_url' => 'https://www.openhistoricalmap.org/way/200655461',
        'quote' => 'name=Ligne Magouër-Plouhinec, gauge=1435, usage=industrial, railway=disused, end_date=1945, start_date=1940',
        'reason' => 'fin de la ligne Todt à la Libération, dernière exploitation du réseau (voir aussi archeoferroviaire, http://archeoferroviaire.free.fr/v31/spip.php?article536)',
    ],

    // 176000 Bouzonville - Guerstling (section frontière vers Niedaltdorf) : voyageurs supprimés en 1945.
    [
        'line' => '176000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1945-01-01',
        'precision' => 'year',
        // Niedaltdorf (Sarre) n'est pas sur le tracé de la ligne française : portion arrêtée à la
        // frontière (Guerstling), section française du service supprimé.
        'from' => 'Bouzonville',
        'to' => 'Guerstling',
        'source_url' => 'https://de.wikipedia.org/w/index.php?title=Niedtalbahn&oldid=269057972',
        'quote' => 'Der Personenverkehr zwischen Bouzonville und Niedaltdorf wurde nach Ende des Zweiten Weltkrieges 1945 eingestellt.',
        'reason' => 'fin du service voyageurs transfrontalier entre Bouzonville et Niedaltdorf, ici sa section française Bouzonville - Guerstling',
    ],

    // 456000 Sillé-le-Guillaume - Juigné : voyageurs fermés le 15 mai 1938 (Centre des archives historiques de
    // la SNCF, cité par le forum Rail Bretagne - Pays de la Loire) ; ligne encore au fascicule de 1957 (fret).
    // Fin du fret et dépose non datées : la chute de km 2020 - 2030 reste à dater (revue humaine).
    [
        'line' => '456000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1938-05-15',
        'precision' => 'day',
        'source_url' => 'https://railbreopdl.forumactif.org/t845-sille-le-guillaume-loue-brulon-juigne-sur-sarthe-sable-sur-sarthe',
        'quote' => 'Fermeture au service voyageurs : le 15 mai 1938. Source : Centre des Archives Historiques de la SNCF.',
        'reason' => 'fin du service voyageurs de toute la ligne (coordination des transports de 1938)',
    ],

    // Wintersdorf - Rastatt (3332, en Allemagne) : voyageurs supprimés le 7 octobre 1950.
    [
        'line' => '3332',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1950-10-07',
        'precision' => 'day',
        'source_url' => 'https://de.wikipedia.org/w/index.php?title=Rheinbr%C3%BCcke_Wintersdorf&oldid=268379264',
        'quote' => 'Am 7. Oktober 1950 wurde der Reisezugverkehr zwischen Rastatt und Wintersdorf eingestellt.',
        'reason' => 'fin du service voyageurs entre Rastatt et Wintersdorf',
    ],

    // 713000 Le Palais - Eygurande-Merlines : « Le tunnel de La Saulière (86 m) [...] a été supprimé en 1964 par le creusement d'une
    // tranchée ouverte » est une phrase d'infrastructure, lue à tort comme l'ouverture de la portion Barsanges - Ambrugeat
    // (chantier « datation-gares », 23/11/2026).
    [
        'line' => '713000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '1964-01-01'],
        'label' => 'Suppression du tunnel de La Saulière',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_du_Palais_%C3%A0_Eygurande_-_Merlines&oldid=238876015',
        'quote' => 'Le tunnel de La Saulière (86 m), en très mauvais état, situé entre les gares de Barsanges et Ambrugeat a été supprimé en 1964 par le creusement d\'une tranchée ouverte.',
        'reason' => 'la suppression d\'un tunnel n\'est pas l\'ouverture de la portion Barsanges - Ambrugeat (section Eymoutiers - Meymac ouverte le 8 octobre 1883, même article)',
    ],

    // 559000 Pont-de-Braye - Blois : « Le tronçon de Troo à Sougé (6 km) est inauguré en juin 2026 » est la réouverture
    // touristique d'un tronçon de la ligne ouverte le 20 novembre 1881, pas une ouverture : elle datait Troo de 2026.
    [
        'line' => '559000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '2026-06-01'],
        'label' => 'Réouverture touristique du tronçon Troo - Sougé',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Pont-de-Braye_%C3%A0_Blois&oldid=239961138',
        'quote' => 'Le tronçon de Troo à Sougé (6 km) est inauguré en juin 2026.',
        'reason' => 'réouverture d\'un tronçon de la ligne ouverte le 20 novembre 1881 (même article) : la gare de Troo date de 1881',
    ],

    // Gares fréquentées en 2020 absentes de l'ère 2020 (chantier « gares-2020 », 23/11/2026, rapport
    // storage/app/reports/agents/aprem-2111/gares-2020.md).

    // 544000 Saintes - Royan : la fermeture aux voyageurs du 15/05/1939 est celle de la ligne Pons - Saujon - La Tremblade ;
    // lue pour toute la ligne, elle mettait Saintes - Royan au fret seul de 1940 à 2020 (Royan, Saujon : 158 081 et 29 426
    // voyageurs SNCF en 2020, desserte TER).
    [
        'line' => '544000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'closure_passenger', 'date' => '1939-05-15'],
        'label' => 'Fermeture aux voyageurs de la ligne Pons - Saujon - La Tremblade',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Saintes_%C3%A0_Royan&oldid=241024740',
        'quote' => 'La crise économique de l\'entre-deux-guerres a pour conséquence la fermeture au trafic voyageurs de la ligne reliant Pons, Saujon et La Tremblade, le 15 mai 1939.',
        'reason' => 'fermeture d\'une autre ligne (Pons - Saujon - La Tremblade) ; Saintes - Royan garde ses voyageurs (TER Nouvelle-Aquitaine, même article)',
    ],

    // 944000 Cannes-la-Bocca - Grasse : la réouverture de 2005 porte sur toute la ligne, mais la fermeture de la section
    // Cannes-la-Bocca - Ranguin du 26/11/1995, plus précise, l'emportait sur elle : section au fret seul de 2000 à 2020
    // (haltes du Bosquet et de La Frayère, 52 529 et 45 627 voyageurs SNCF en 2020). Réouverture posée sur la section.
    [
        'line' => '944000',
        'action' => 'add',
        'type' => 'reopening_passenger',
        'date' => '2005-03-26',
        'precision' => 'day',
        'from' => 'Cannes-la-Bocca',
        'to' => 'Ranguin',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Cannes-la-Bocca_%C3%A0_Grasse&oldid=237782258',
        'quote' => 'Dix ans plus tard, en 2005, elle est rouverte à ce trafic, cette fois sur la totalité de son parcours',
        'reason' => 'réouverture aux voyageurs de toute la ligne le 26/03/2005 (même article), section Cannes-la-Bocca - Ranguin comprise',
    ],

    // 960000 Tangentielle Nord : aucune date, la ligne manquait à toutes les ères historiques (gares du T11 Express absentes de
    // l'ère 2020). Premier tronçon Épinay-sur-Seine - Le Bourget ouvert le 01/07/2017.
    [
        'line' => '960000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '2017-07-01',
        'precision' => 'day',
        'from' => 'Épinay-sur-Seine',
        'to' => 'Le Bourget',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Gare_de_Dugny_-_La_Courneuve&oldid=236142240',
        'quote' => 'ouverte en même temps que le premier tronçon de la ligne 11 du tramway d\'Île-de-France, le 1er juillet 2017',
        'reason' => 'mise en service du tram-train T11 Express sur la Tangentielle Nord (premier tronçon, d\'Épinay-sur-Seine au Bourget)',
    ],
];
