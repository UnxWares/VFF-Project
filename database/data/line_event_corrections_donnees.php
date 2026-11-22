<?php

/**
 * Corrections sourcées d'événements de lignes relevées par la relecture des données des fiches
 * (question 18 de QUESTIONS.md, rapport storage/app/reports/agents/ui-donnees-fiches.md ; chantier
 * « donnees-corrections », 21/11/2026), ou par l'audit des tronçons (vff:audit-segments). Format : voir
 * line_event_corrections.php.
 *
 * Pas de tiret long dans les textes.
 *
 * @return list<array<string, mixed>>
 */
$wp = fn (string $title, int $oldid) => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode(str_replace(' ', '_', $title)).'&oldid='.$oldid;

return [
    // Constat 6 : 440000 Vire - Romagny. La « date d'ouverture » Wikidata (1883) est l'année de la
    // concession (convention du 17/07/1883, loi du 20/11/1883) ; la ligne ouvre de Vire aux Maures et
    // de Sourdeval à Mortain le 18/12/1887 (déjà en base, Wikipédia et lignes-oubliees). L'année 1883
    // faisait commencer la desserte de Vire sur cette ligne quatre ans trop tôt.
    [
        'line' => '440000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikidata', 'type' => 'opening', 'date' => '1883-01-01'],
        'label' => 'Année de la concession (date d\'ouverture Wikidata)',
        'source_url' => $wp('Ligne de Vire à Romagny', 226857304),
        'quote' => 'Section de Vire aux Maures : le 18 décembre 1887',
        'reason' => 'concédée le 17 juillet 1883 à la Compagnie de l\'Ouest, la ligne ouvre le 18 décembre 1887',
    ],

    // Audit des tronçons (storage/app/reports/agents/nuit-2111/audit-troncons.md) : 900000 Culoz - Modane.
    // La « fermeture de toute la ligne » du 19/09/1871, lue dans la section sur le PLM de l'article, est
    // celle du chemin de fer du Mont-Cenis : ligne provisoire de Saint-Michel-de-Maurienne à Suse par le
    // col, proposée en 1865 par M. Brassey et l'ingénieur Fell, fermée quand le tunnel du Fréjus ouvre. La
    // ligne de Culoz à Modane n'a pas fermé : la même page date du 20/10/1871 la section de Saint-Michel
    // à Modane. Cette date contredisait tous les tronçons en service de 1880 à 2030 dans l'audit.
    [
        'line' => '900000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'closure', 'date' => '1871-09-19'],
        'label' => 'Fermeture du chemin de fer du Mont-Cenis (Saint-Michel-de-Maurienne - Suse)',
        'source_url' => $wp('Ligne de Culoz à Modane (frontière)', 238204405),
        'quote' => 'Cette ligne est mise en service le 15 juin 1868 et fermée le 19 septembre 1871 à l\'ouverture du tunnel ferroviaire du Fréjus.',
        'reason' => 'fermeture de la ligne provisoire du Mont-Cenis par le col, remplacée par le tunnel du Fréjus ; Culoz - Modane reste ouverte (section Saint-Michel - Modane ouverte le 20 octobre 1871)',
    ],

    // Contrôle à la main du 21/11/2026 (storage/app/reports/agents/aprem-2111/controle-main-3.md, erreurs de
    // données ; vérifications dans aprem-2111/donnees-erreurs.md).

    // 354000 Motteville - Montérolier-Buchy : la fin des voyageurs de 1938 manquait, la ligne était notée
    // « mixte » en 2030 alors qu'elle ne voit que des trains de fret (et des détournements exceptionnels).
    [
        'line' => '354000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1938-10-02',
        'precision' => 'day',
        'from' => 'Motteville',
        'to' => 'Montérolier-Buchy',
        'source_url' => $wp('Ligne de Montérolier - Buchy à Motteville', 241046044),
        'quote' => 'l\'ensemble des circulations voyageurs sont supprimées le 2 octobre 1938 avec un report sur route',
        'reason' => 'suppression des voyageurs par la coordination des transports de 1938 (JORF du 19 octobre 1938) ; la ligne reste au fret',
    ],

    // 945000 Nice - Breil-sur-Roya : l'« ouverture » de 2023 est une phrase sur l'horaire 2023 et faisait
    // attendre une ligne fermée avant 2023 ; l'année Wikidata 1921 est celle de la reprise des travaux.
    // La ligne ouvre le 30/10/1928 (déjà en base par OHM).
    [
        'line' => '945000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '2023-01-01'],
        'label' => 'Desserte à l\'horaire 2023',
        'source_url' => $wp('Ligne de Nice à Breil-sur-Roya', 235342459),
        'quote' => 'À l\'horaire 2023, la ligne comprend 10,5 allers-retours quotidiens entre Nice-Ville et Breil-sur-Roya',
        'reason' => 'phrase sur la desserte de 2023, pas une ouverture : la ligne est en service depuis 1928',
    ],
    [
        'line' => '945000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikidata', 'type' => 'opening', 'date' => '1921-01-01'],
        'label' => 'Reprise des travaux (date d\'ouverture Wikidata)',
        'source_url' => $wp('Ligne de Nice à Breil-sur-Roya', 235342459),
        'quote' => 'La guerre de 1914-1918 et ses séquelles interrompirent les travaux qui ne purent reprendre qu\'en 1921.',
        'reason' => '1921 est la reprise des travaux ; l\'inauguration de Nice - Breil-sur-Roya - Coni a lieu le 30 octobre 1928',
    ],

    // 980000 Petite Ceinture : la « fermeture » de 1934 (infobox et Wikidata) est la fin des voyageurs ; le fret
    // a roulé jusqu'en 1993 (deuxième fermeture de l'infobox). VFF notait la ligne fermée en 1970.
    [
        'line' => '980000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'closure', 'date' => '1934-01-01'],
        'label' => 'Fin des voyageurs (infobox)',
        'source_url' => $wp('Ligne de Petite Ceinture', 241167819),
        'quote' => 'La suppression des trains de voyageurs est alors décidée : elle est effective le lundi 23 juillet 1934',
        'reason' => 'fin du service voyageurs, pas de la ligne : le fret roule jusqu\'en 1993',
    ],
    [
        'line' => '980000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikidata', 'type' => 'closure', 'date' => '1934-04-01'],
        'label' => 'Fin des voyageurs (Wikidata)',
        'source_url' => $wp('Ligne de Petite Ceinture', 241167819),
        'quote' => 'La suppression des trains de voyageurs est alors décidée : elle est effective le lundi 23 juillet 1934',
        'reason' => 'fin du service voyageurs, pas de la ligne : le fret roule jusqu\'en 1993',
    ],
    [
        'line' => '980000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1934-07-23',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Petite Ceinture', 241167819),
        'quote' => 'La suppression des trains de voyageurs est alors décidée : elle est effective le lundi 23 juillet 1934',
        'reason' => 'fin des voyageurs de la Petite Ceinture, remplacés par l\'autobus PC',
    ],

    // 265000 Templeuve - Don-Sainghin : les fins des voyageurs de 1952 et 1953 manquaient (« mixte » en 1970).
    [
        'line' => '265000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1952-01-01',
        'precision' => 'year',
        'from' => 'Seclin',
        'to' => 'Pont-à-Marcq',
        'source_url' => $wp('Ligne de Templeuve à Don-Sainghin', 235337773),
        'quote' => 'La SNCF supprime le trafic voyageurs en 1952 sur le tronçon de Seclin à Pont-à-Marcq et l\'année suivante (1953) entre Templeuve et Pont-à-Marcq',
        'reason' => 'fin des voyageurs de Seclin à Pont-à-Marcq',
    ],
    [
        'line' => '265000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1953-01-01',
        'precision' => 'year',
        'from' => 'Templeuve',
        'to' => 'Pont-à-Marcq',
        'source_url' => $wp('Ligne de Templeuve à Don-Sainghin', 235337773),
        'quote' => 'La SNCF supprime le trafic voyageurs en 1952 sur le tronçon de Seclin à Pont-à-Marcq et l\'année suivante (1953) entre Templeuve et Pont-à-Marcq',
        'reason' => 'fin des voyageurs de Templeuve à Pont-à-Marcq',
    ],

    // Perpignan - Thuir (ligne 3894, hors RFN) : fin des voyageurs en 1953, fret jusqu'en juin 1994.
    [
        'line' => '3894',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1953-01-01',
        'precision' => 'year',
        'source_url' => $wp('Ligne de Perpignan à Thuir', 236212395),
        'quote' => 'Le trafic voyageurs y a cessé en 1953',
        'reason' => 'fin des voyageurs, la ligne reste au fret',
    ],
    [
        'line' => '3894',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1994-06-01',
        'precision' => 'month',
        'source_url' => $wp('Ligne de Perpignan à Thuir', 236212395),
        'quote' => 'Reprise ensuite par la SNCF, elle fut empruntée par des trains de fret jusqu\'en juin 1994',
        'reason' => 'fin du fret, dernier trafic de la ligne',
    ],

    // 696000 Châteauroux - La Ville-Gozet : l'ouverture de La Châtre - Montluçon manquait.
    [
        'line' => '696000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1884-09-22',
        'precision' => 'day',
        'from' => 'La Châtre',
        'to' => 'Montluçon',
        'source_url' => $wp('Ligne de Châteauroux à La Ville-Gozet', 240558838),
        'quote' => 'Le 22 septembre 1884, la Compagnie du PO est autorisée à ouvrir à l\'exploitation les 66,101 kilomètres de la section située entre La Châtre et Montluçon.',
        'reason' => 'ouverture de la seconde section de la ligne (infobox : mise en service 1882 et 1884)',
    ],

    // Cohérence gares et lignes (chantier « coherence-gares-lignes », 21/11/2026, rapport
    // storage/app/reports/agents/aprem-2111/coherence-gares-lignes.md). 598000 Port-de-Piles - Argenton-sur-Creuse :
    // les deux fins du fret de la partie nord n'avaient pas de portion résolue (« La Haye - Descartes », « Tournon »
    // ne sont pas les noms des gares), le fret du Grand-Pressigny (jusqu'en 2005) n'était porté par aucun événement placé.
    [
        'line' => '598000',
        'action' => 'relocate',
        'match' => ['source' => 'wikipedia', 'type' => 'closure_freight', 'date' => '2005-01-01'],
        'from' => 'La Haye-Descartes',
        'to' => 'Tournon-St-Martin',
        'pk_start' => 289220,
        'pk_end' => 331652,
        'source_url' => $wp('Ligne de Port-de-Piles à Argenton-sur-Creuse', 224232794),
        'quote' => 'En 2005, le trafic fret cesse entre La Haye - Descartes et Tournon',
        'reason' => 'fin du fret de La Haye-Descartes (PK 289,220, où commence la partie fermée selon l\'article) à Tournon-Saint-Martin (PK 331,652, début de la partie déclassée en 1970)',
    ],
    [
        'line' => '598000',
        'action' => 'relocate',
        'match' => ['source' => 'wikipedia', 'type' => 'closure_freight', 'date' => '1970-01-01'],
        'from' => 'Tournon-St-Martin',
        'to' => 'Le Blanc',
        'pk_start' => 331652,
        'pk_end' => 347094,
        'source_url' => $wp('Ligne de Port-de-Piles à Argenton-sur-Creuse', 224232794),
        'quote' => 'La ligne a été déclassée entre Tournon-Saint-Martin et Le Blanc (PK 331,652 à 347,094), le 29 octobre 1970',
        'reason' => 'fin du fret de Tournon-Saint-Martin au Blanc, portion du déclassement de la même année (PK 331,652 à 347,094)',
    ],

    // Ligne de Nizan à Luxey (id 3873) : « la totalité de la ligne le 31 décembre 1954 » clôt la liste des fermetures
    // aux voyageurs ; le fret continue de Saint-Symphorien à Luxey jusqu'au 31/12/1970 (déjà en base). Lue comme
    // fermeture à tout trafic, elle fermait la ligne à Luxey seize ans trop tôt (fret de la gare jusqu'en 1970).
    [
        'line' => '3873',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'closure', 'date' => '1954-12-31'],
        'label' => 'Fin du service des voyageurs sur toute la ligne',
        'source_url' => $wp('Ligne du Nizan à Luxey', 221704329),
        'quote' => 'La fermeture du service des voyageurs est effectuée par section en 1950 et 1954',
        'reason' => 'fin des voyageurs (dernier tronçon Sore - Luxey), pas de tout trafic : le fret de Saint-Symphorien à Luxey dure jusqu\'au 31 décembre 1970',
    ],
    [
        'line' => '3873',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1954-12-31',
        'precision' => 'day',
        'from' => 'Sore',
        'to' => 'Luxey',
        'source_url' => $wp('Ligne du Nizan à Luxey', 221704329),
        'quote' => 'la totalité de la ligne le 31 décembre 1954 avec la fermeture du dernier tronçon entre Sore et Luxey',
        'reason' => 'fin du service des voyageurs du dernier tronçon, le fret continue',
    ],

    // Ligne d'Achiet à Marcoing (id 3374) : en 1969 les voyageurs passent sur route et la ligne ferme de Bapaume à
    // Marcoing, mais Achiet - Bapaume reste ouverte au fret (GIE Exploitation ferroviaire de Bapaume) jusqu'en 1999.
    // La fermeture OHM (tracé d'Achiet à Marcoing) fermait aussi Achiet - Bapaume en 1969.
    [
        'line' => '3374',
        'action' => 'relocate',
        'match' => ['source' => 'ohm', 'type' => 'closure', 'date' => '1969-01-01'],
        'from' => 'Bapaume',
        'to' => 'Marcoing',
        'source_url' => $wp('Gare de Bapaume', 238680685),
        'quote' => 'sur la seule section de ligne restant ouverte entre Achiet et Bapaume',
        'reason' => 'en 1969 seule la partie Bapaume - Marcoing ferme à tout trafic ; Achiet - Bapaume reste au fret',
    ],
    [
        'line' => '3374',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1969-01-01',
        'precision' => 'year',
        'from' => 'Achiet',
        'to' => 'Bapaume',
        'source_url' => $wp('Gare de Bapaume', 238680685),
        'quote' => 'Cette dernière transfère le service voyageurs sur route en 1969.',
        'reason' => 'fin des voyageurs d\'Achiet à Bapaume, la section reste au fret',
    ],
    [
        'line' => '3374',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1999-01-01',
        'precision' => 'year',
        'from' => 'Achiet',
        'to' => 'Bapaume',
        'source_url' => $wp('Gare de Bapaume', 238680685),
        'quote' => 'Ce dernier exploitant cesse le trafic ferroviaire en 1999.',
        'reason' => 'fin du trafic ferroviaire (fret du GIE Exploitation ferroviaire de Bapaume) d\'Achiet à Bapaume',
    ],

    // Recherche sur les questions ouvertes (chantier « recherche-lignes », nuit du 22/11/2026, rapport
    // storage/app/reports/agents/aprem-2111/recherche-lignes.md).

    // 892000 Longeray-Léaz - Le Bouveret, section Évian-les-Bains - Saint-Gingolph (ligne du Tonkin). Le
    // 15/05/1938 ne ferme que le service des voyageurs ; la section rouvre au fret pendant la guerre (sans date)
    // et le garde jusqu'en 1988 (réponse ministérielle publiée au JO du 21/04/2003 : « fermée à la circulation
    // depuis 1988 »). Le Rive Bleue Express y roule de 1986 à 1998, puis la section est fermée et neutralisée.
    // La « réouverture » datée du jour même de la fermeture (phrase sans date lue au 15/05/1938) faisait
    // tomber la ligne fermée en 1970 au lieu d'ouverte au fret.
    [
        'line' => '892000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'reopening', 'date' => '1938-05-15'],
        'label' => 'Réouverture au fret pendant la Deuxième Guerre mondiale (date inconnue)',
        'source_url' => $wp('Ligne de Longeray-Léaz au Bouveret', 240385701),
        'quote' => 'cette portion est rouverte durant la Deuxième Guerre mondiale',
        'reason' => 'la phrase ne donne pas de date : la réouverture a été posée au jour de la fermeture de 1938',
    ],
    [
        'line' => '892000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'closure', 'date' => '1938-05-15'],
        'label' => 'Fin du service des voyageurs d\'Évian à Saint-Gingolph',
        'source_url' => $wp('Ligne du Tonkin', 241505522),
        'quote' => 'En 1938, le trafic voyageur est supprimé sur la partie française pour être reporté sur la route.',
        'reason' => 'fin des voyageurs (remplacée par l\'événement ci-dessous) : le fret roule ensuite jusqu\'en 1988',
    ],
    [
        'line' => '892000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1938-05-15',
        'precision' => 'day',
        'from' => 'Évian-les-Bains',
        'to' => 'Saint-Gingolph',
        'source_url' => $wp('Ligne du Tonkin', 241505522),
        'quote' => 'En 1938, le trafic voyageur est supprimé sur la partie française pour être reporté sur la route.',
        'reason' => 'fin des voyageurs d\'Évian à Saint-Gingolph (jour : article de la ligne de Longeray-Léaz au Bouveret)',
    ],
    [
        'line' => '892000',
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1988-01-01',
        'precision' => 'year',
        'from' => 'Évian-les-Bains',
        'to' => 'Saint-Gingolph',
        'source_url' => 'https://www.assemblee-nationale.fr/dyn/12/questions/QANR5L12QE10935',
        'quote' => 'La section Evian - Saint-Gingolph étant fermée à la circulation depuis 1988',
        'reason' => 'fin du fret de la SNCF sur la partie française (réponse à la question écrite n° 10935, JO du 21/04/2003, p. 3238 ; article « Ligne du Tonkin » : « En 1988, le trafic marchandise est supprimé sur la partie française par la SNCF. »)',
    ],
    [
        'line' => '892000',
        'action' => 'add',
        'type' => 'heritage_start',
        'date' => '1986-01-01',
        'precision' => 'year',
        'from' => 'Évian-les-Bains',
        'to' => 'Saint-Gingolph',
        'heritage_kind' => 'tourist_train',
        'source_url' => $wp('Ligne du Tonkin', 241505522),
        'quote' => 'De 1986 à 1998, le train touristique Rive-Bleue Express circule entre Le Bouveret et Évian',
        'reason' => 'train touristique Rive Bleue Express, seul trafic de la section après la fin du fret de 1988',
    ],
    [
        'line' => '892000',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1998-01-01',
        'precision' => 'year',
        'from' => 'Évian-les-Bains',
        'to' => 'Saint-Gingolph',
        'source_url' => $wp('Ligne du Tonkin', 241505522),
        'quote' => 'En 1998, fermeture et neutralisation de la partie française entre Saint-Gingolph et Évian.',
        'reason' => 'fin du Rive Bleue Express et neutralisation de la section (infobox de la ligne de Longeray-Léaz au Bouveret : fermeture 1998)',
    ],
    // 598000 : la fin des voyageurs de Port-de-Piles au Blanc (27/06/1940) est dans la même phrase que celle du Blanc
    // à Argenton (« suivi par celui de … ») et n'avait pas été lue ; visible depuis que le tracé de la partie nord est
    // rattaché (chantier « traces-manquantes », 22/11/2026).
    [
        'line' => '598000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1940-06-27',
        'precision' => 'day',
        'from' => 'Port-de-Piles',
        'to' => 'Le Blanc',
        'source_url' => $wp('Ligne de Port-de-Piles à Argenton-sur-Creuse', 224232794),
        'quote' => 'Le tronçon du Blanc à Argenton-sur-Creuse a été fermé au service des voyageurs, le 22 juin 1940, suivi par celui de Port-de-Piles au Blanc, le 27 juin 1940.',
        'reason' => 'fin des voyageurs de la partie nord, seconde moitié de la phrase',
    ],

    // Lignes incomplètes aux ères passées (chantier « lignes-incompletes », nuit du 22/11/2026, rapport
    // storage/app/reports/agents/aprem-2111/lignes-incompletes.md) : ouvertures manquantes ou lues à tort.

    // 872000 Besançon-Viotte - Le Locle-Col-des-Roches : le 30/03/2022 est la reprise du trafic après les travaux de
    // 2021 (ligne fermée du 01/03/2021, tunnel du Col-des-Roches jusqu'au 30/03/2022). Lue comme ouverture de Morre à
    // La Chaux-de-Fonds, elle l'emportait sur l'ouverture de 1884 et la ligne n'était dessinée à aucune ère passée.
    [
        'line' => '872000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '2022-03-30'],
        'label' => 'Reprise du trafic après les travaux de 2021 et 2022',
        'source_url' => $wp('Ligne de Besançon-Viotte au Locle-Col-des-Roches', 241383279),
        'quote' => 'Le tunnel transfrontalier du Col-des-Roches, construit en 1884, a été fermé pour d\'importants travaux de rénovation du 1er mars 2021 au 30 mars 2022 sur sa partie suisse (316 mètres sur un total de 422 mètres).',
        'reason' => 'fin des travaux de renouvellement, pas une ouverture : la ligne est inaugurée le 4 août 1884 (déjà en base)',
    ],

    // 736000 Castelnaudary - Rodez : seules Albi - Carmaux (1858) et Castelnaudary - Castres (1865) étaient datées ;
    // Castres - Albi et Carmaux - Rodez (113 km) n'apparaissaient à aucune ère passée. L'article de la ligne ne donne
    // pas ces deux dates. Carmaux - Rodez : notice Mérimée du viaduc du Viaur (mise en service le 18/12/1902, après
    // l'inauguration du 05/10/1902) et annuaire Marchal 1907 des chemins de fer du Midi (« Carmaux à Rodez, 66 km,
    // 1902 »). Castres - Albi : même annuaire (« Castres à Albi, 49 km, 1869 ») et tableau des ouvertures du site
    // ruedupetittrain (Castres - Laboutarié et Laboutarié - Albi le 15/12/1869, jour non recoupé : année seule).
    [
        'line' => '736000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1902-12-18',
        'precision' => 'day',
        'from' => 'Carmaux',
        'to' => 'Rodez',
        'source_url' => 'https://pop.culture.gouv.fr/notice/merimee/PA00095650',
        'quote' => 'Mis en service le 18 décembre 1902, le viaduc présentait à son achèvement le plus long arc métallique construit',
        'reason' => 'ouverture de Carmaux à Rodez par le viaduc du Viaur (notice Mérimée PA00095650 ; année 1902 dans Marchal, Chemins de fer du Midi, 1907, https://ekeving.se/ext/F/Marchal_1907/223.pdf)',
    ],
    [
        'line' => '736000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1869-01-01',
        'precision' => 'year',
        'from' => 'Castres',
        'to' => 'Albi',
        'source_url' => 'https://ekeving.se/ext/F/Marchal_1907/223.pdf',
        'quote' => 'Castres à Albi 49 1869',
        'reason' => 'ouverture de Castres à Albi en 1869 (tableau des lignes du Midi, Marchal 1907 ; tableau des ouvertures de http://ruedupetittrain.free.fr/lignes/atlantique/castelnaudary-albi.htm : 15/12/1869, jour non recoupé)',
    ],

    // 791000 Le Puy - Lalevade-d'Ardèche (Transcévenole) : ligne jamais achevée ni ouverte, déclassée en 1941. Les
    // deux « ouvertures » OHM sont celles des voies voisines de Saint-Étienne - Le Puy (1866) et du Puy à Langogne
    // (1912, par Brives-Charensac) : elles faisaient dessiner 3,7 km de la Transcévenole dès 1866.
    [
        'line' => '791000',
        'action' => 'neutralize',
        'match' => ['source' => 'ohm', 'type' => 'opening', 'date' => '1866-05-14'],
        'label' => 'Ouverture d\'une ligne voisine au Puy (OHM)',
        'source_url' => $wp('Ligne transcévenole', 241145861),
        'quote' => 'La Transcévenole est le nom d\'une ligne de chemin de fer qui devait relier Le Puy (Haute-Loire) à Lalevade-d\'Ardèche (Ardèche), mais qui n\'a jamais été achevée.',
        'reason' => 'ligne jamais ouverte : la date OHM est celle de la gare du Puy sur la ligne de Saint-Étienne',
    ],
    [
        'line' => '791000',
        'action' => 'neutralize',
        'match' => ['source' => 'ohm', 'type' => 'opening', 'date' => '1912-07-01'],
        'label' => 'Ouverture de la ligne du Puy à Langogne (OHM)',
        'source_url' => $wp('Ligne transcévenole', 241145861),
        'quote' => 'La Transcévenole est le nom d\'une ligne de chemin de fer qui devait relier Le Puy (Haute-Loire) à Lalevade-d\'Ardèche (Ardèche), mais qui n\'a jamais été achevée.',
        'reason' => 'ligne jamais ouverte : le 1er juillet 1912 est l\'ouverture du Puy à Langogne, qui passe à Brives-Charensac',
    ],

    // 633000 Cahors - Moissac : ligne jamais achevée (travaux arrêtés en 1934, déclassée en 1941). L'« ouverture »
    // OHM du 30/08/1856 est celle de la gare de Moissac sur Bordeaux - Sète.
    [
        'line' => '633000',
        'action' => 'neutralize',
        'match' => ['source' => 'ohm', 'type' => 'opening', 'date' => '1856-08-30'],
        'label' => 'Ouverture de la gare de Moissac sur la ligne de Bordeaux à Sète (OHM)',
        'source_url' => $wp('Ligne de Cahors à Moissac', 226856040),
        'quote' => 'La ligne de Cahors à Moissac, également appelée ligne de Moissac à Cahors, est une ligne de chemin de fer française qui ne fut jamais achevée, les travaux ayant été arrêtés en 1934.',
        'reason' => 'ligne jamais ouverte : la date OHM est celle de la ligne de Bordeaux à Sète à Moissac',
    ],

    // 684000 Étampes - Beaune-la-Rolande : seuls deux morceaux OHM de la gare de Pithiviers (1872, ligne des
    // Aubrais) étaient datés. Pithiviers - Beaune-la-Rolande ouvre en 1902 (jour du 27/10/1902 donné par un seul
    // relevé, non retenu), Étampes - Pithiviers le 19/02/1905.
    [
        'line' => '684000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1905-02-19',
        'precision' => 'day',
        'from' => 'Étampes',
        'to' => 'Pithiviers',
        'source_url' => $wp('Gare de Méréville', 235033427),
        'quote' => 'La gare est inaugurée le 19 février 1905 lors de l\'ouverture de la ligne par un train inaugural parti de la gare Étampes vers 14 heures et arrivé à Pithiviers à 15 h 30.',
        'reason' => 'ouverture d\'Étampes à Pithiviers (historique de la gare de Méréville, https://www.gare91.com/histoire.php : « L\'inauguration aura lieu le 19 Février 1905. » ; année dans Marchal 1907, réseau d\'Orléans)',
    ],
    [
        'line' => '684000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1902-01-01',
        'precision' => 'year',
        'from' => 'Pithiviers',
        'to' => 'Beaune-la-Rolande',
        'source_url' => 'https://www.gare91.com/histoire.php',
        'quote' => 'En 1902, une première partie de la ligne est ouverte entre Pithiviers et Beaune la Rolande',
        'reason' => 'ouverture de Pithiviers à Beaune-la-Rolande (année aussi dans Marchal 1907, réseau d\'Orléans, https://ekeving.se/ext/F/Marchal_1907/309.pdf : « Pithiviers à Beaune-la-Rolande 21 1902 »)',
    ],

    // 724000 Cahors - Capdenac (Cajarc) : aucune ouverture en base, la ligne n'était dessinée qu'en 2030.
    [
        'line' => '724000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1886-07-14',
        'precision' => 'day',
        'from' => 'Cahors',
        'to' => 'Capdenac',
        'source_url' => $wp('Gare de Cajarc', 240303811),
        'quote' => 'La gare a été construite en 1885-1886 par la Compagnie du chemin de fer de Paris à Orléans sur la ligne de Cahors à Capdenac. Elle est mise en service le 14 juillet 1886.',
        'reason' => 'mise en service de la ligne (notice Mérimée PA00095285 de la gare de Cajarc, https://pop.culture.gouv.fr/notice/merimee/PA00095285 : « mise en service le 14 juillet 1886 » ; article de la ligne : inaugurée en 1886)',
    ],

    // 354000 Motteville - Montérolier-Buchy : seule Clères - Motteville (1876) était datée.
    [
        'line' => '354000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1867-04-18',
        'precision' => 'day',
        'from' => 'Montérolier-Buchy',
        'to' => 'Clères',
        'source_url' => $wp('Ligne de Montérolier - Buchy à Motteville', 241046044),
        'quote' => 'Le 18 avril 1867, l\'ouverture de la section de Montérolier à Clères par la Compagnie des chemins de fer du Nord.',
        'reason' => 'ouverture de la première section (article de la gare de Clères, oldid 235031377 : raccordement à Étaimpuis de la ligne venant de Montérolier ouvert à partir du 18 avril 1867)',
    ],

    // 627000 Condat-Le-Lardin - Sarlat : aucune ouverture en base (l'article de la ligne n'en donne pas).
    [
        'line' => '627000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1899-10-15',
        'precision' => 'day',
        'from' => 'Condat-Le-Lardin',
        'to' => 'Sarlat',
        'source_url' => 'https://www.patrimoine-nouvelle-aquitaine.fr/doc/Dossier/9f674e3c-7550-4c27-9ac2-219f575e1b14/gare-de-montignac?_lg=fr-FR',
        'quote' => 'la section Hautefort-Sarlat, qui dessert Montignac, est mise en service le 15 octobre 1899.',
        'reason' => 'mise en service de la ligne (Inventaire général du patrimoine culturel, Nouvelle-Aquitaine ; http://chemins.de.traverses.free.fr/04533299f60f21e0a/0453329c390c78f01.html : « La section Condat-le-Lardin - Sarlat (35.900 km) fut mise en service le 15 octobre 1899 »)',
    ],

    // 508000 Aubigné-Racan - Sablé : seule La Flèche - Sablé (1877) était datée. Jour du 19/06/1871 donné par un
    // relevé des archives de la SNCF cité sur un forum, non recoupé : année seule.
    [
        'line' => '508000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1871-01-01',
        'precision' => 'year',
        'from' => 'Aubigné-Racan',
        'to' => 'La Flèche',
        'source_url' => $wp('Gare de La Flèche', 235032439),
        'quote' => 'Le chemin de fer arriva à La Flèche en 1871 avec l\'ouverture de la ligne d\'Aubigné-Racan à Sablé et la construction de la gare.',
        'reason' => 'ouverture d\'Aubigné-Racan à La Flèche par Le Lude (https://www.cheminots.net/topic/24030-les-anciennes-gares-sarthoises : « Mise en exploitation : le 19 juin 1871 par la Compagnie du Paris-Orléans »)',
    ],

    // 289000 Fives - Abbeville : la section Frévent - Abbeville, rattachée par la passe « sections » (chantier
    // « traces-manquantes »), n'avait pas d'ouverture ; Saint-Riquier et Auxi-le-Château restaient sans ligne aux ères
    // passées. Mois donné par trois articles de gares (rédactions distinctes) ; Saint-Pol - Frévent reste sans date sûre.
    [
        'line' => '289000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1879-06-01',
        'precision' => 'month',
        'from' => 'Frévent',
        'to' => 'Abbeville',
        'source_url' => $wp('Gare d\'Auxi-le-Château', 238683030),
        'quote' => 'Cette gare est mise en service à l\'ouverture de la section d\'Abbeville à Frévent de la ligne de Fives à Abbeville, par la Compagnie des chemins de fer du Nord, en juin 1879',
        'reason' => 'ouverture d\'Abbeville à Frévent (articles des gares de Saint-Riquier, oldid 233575159, et d\'Abbeville, oldid 239672829 : « ouverte en juin 1879, sur la section entre Abbeville et Frévent »)',
    ],

    // 305000 Saint-Roch - Frévent : aucune ouverture en base. Canaples - Doullens (section commune avec la ligne de
    // Frévent à Gamaches) le 11/04/1874, Amiens - Canaples en 1877 (jour du 15/10/1877 d'un seul article, non retenu).
    // Doullens - Frévent (1874 puis 1876 selon un seul article) reste sans date sûre.
    [
        'line' => '305000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1874-04-11',
        'precision' => 'day',
        'from' => 'Canaples',
        'to' => 'Doullens',
        'source_url' => $wp('Gare de Canaples', 166057494),
        'quote' => 'La station de Canaples est mise en service le 11 avril 1874 par la Compagnie de Frévent à Gamaches, lorsqu\'elle ouvre à l\'exploitation le tronçon de Doullens à Longpré.',
        'reason' => 'ouverture de Doullens à Longpré par Canaples (Lucien Groué, Aux sources de la Nièvre, en Picardie, 2000, p. 260, cité par l\'article ; article de la gare de Doullens, oldid 232235487 : mise en service « au printemps 1874, lors de l\'ouverture de la section de Canaples à Doullens »)',
    ],
    [
        'line' => '305000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1877-01-01',
        'precision' => 'year',
        'from' => 'Saint-Roch',
        'to' => 'Canaples',
        'source_url' => $wp('Gare de Doullens', 232235487),
        'quote' => 'En outre, une ligne entre Canaples et Amiens est ouverte le 15 octobre 1877, permettant ainsi d\'atteindre la préfecture samarienne depuis Doullens.',
        'reason' => 'ouverture d\'Amiens à Canaples (année aussi dans l\'Inventaire général du patrimoine des Hauts-de-France, gare de Canaples ; jour d\'une seule source)',
    ],

    // 725000 Sévérac-le-Château - Rodez : aucune ouverture en base.
    [
        'line' => '725000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1880-05-14',
        'precision' => 'day',
        'from' => 'Sévérac-le-Château',
        'to' => 'Rodez',
        'source_url' => $wp('Gare de Sévérac-le-Château', 240368598),
        'quote' => 'Cette ligne de Millau à Rodez, via Sévérac, est mise en service par la Compagnie du Midi le 14 mai 1880.',
        'reason' => 'mise en service de l\'embranchement de Rodez avec Millau - Sévérac (fiche des Journées du patrimoine 2025 de Sévérac-Gare, https://medias.hit.enaveyron.fr/fiches/43438/documents/16b3599c-c726-4faa-b698-f390a1556cb7.pdf : « le 14 mai 1880 la portion de Millau (déjà reliée à Béziers depuis 1874) à Sévérac, et l\'embranchement vers Rodez sont ouverts. »)',
    ],

    // Ligne 4047 Saint-Michel-de-Maurienne - Mont-Cenis (chemin de fer Fell) : ouverture et fermeture OHM posées sur un
    // seul morceau (Gran Croce) ; le reste du tracé n'était jamais dessiné. Service du 15/06/1868 au 15/10/1871, le
    // tunnel du Fréjus prend le relais le 16/10/1871.
    [
        'line' => '4047',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1868-06-15',
        'precision' => 'day',
        'source_url' => $wp('Chemin de fer du Mont-Cenis', 240997680),
        'quote' => 'Mis en service le 15 juin 1868, le chemin de fer assure le transport des voyageurs, des marchandises et du courrier sur un trajet d\'environ 77 kilomètres entre la France et l\'Italie.',
        'reason' => 'mise en service de toute la ligne (P. J. G. Ransom, The Mont Cenis Fell Railway, 1999, p. 45, cité par https://en.wikipedia.org/wiki/Mont_Cenis_Pass_Railway : « The actual first passenger train ran from Susa at 7:20 am on 15 June 1868. »)',
    ],
    [
        'line' => '4047',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1871-10-16',
        'precision' => 'day',
        'source_url' => $wp('Chemin de fer du Mont-Cenis', 240997680),
        'quote' => 'le tunnel est inauguré le 17 septembre 1871 tandis que la ligne de montagne cesse son exploitation le 16 octobre suivant.',
        'reason' => 'fin de l\'exploitation de toute la ligne à l\'ouverture du service par le tunnel du Fréjus (Ransom, p. 61-62, cité par l\'article anglais : « The tunnel service started on 16 October 1871, and the MCR service ended on the previous day. »)',
    ],

    // Ligne 3866 Nevers - Saulieu (réseau de la Nièvre) : Nevers - Corbigny n'était pas daté. Chitry-les-Mines -
    // Corbigny (3 km, 1903 selon un seul article) reste sans date sûre.
    [
        'line' => '3866',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1904-12-20',
        'precision' => 'day',
        'from' => 'Nevers',
        'to' => 'Saint-Saulge',
        'source_url' => $wp('Réseau de la Nièvre', 239748249),
        'quote' => 'Nevers -  Saint-Saulge, (43 km), ouverture le 20 décembre 1904 ;',
        'reason' => 'arrêté du préfet de la Nièvre (Courrier de la Nièvre du 25/12/1904, cité par https://www.gennievre.net/wiki/index.php?title=Tacot_Nevers_Corbigny&oldid=43806 : « autorisée à ouvrir à l\'exploitation pour les voyageurs et les marchandises, à partir du 20 décembre courant, la section de ligne de Nevers à Saint-Saulge »)',
    ],
    [
        'line' => '3866',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1905-01-01',
        'precision' => 'year',
        'from' => 'Saint-Saulge',
        'to' => 'Chitry-les-Mines',
        'source_url' => $wp('Réseau de la Nièvre', 239748249),
        'quote' => 'Saint-Saulge - Chitry-les-Mines, (27km), ouverture en 1905',
        'reason' => 'ouverture de Saint-Saulge à Chitry-les-Mines (GenNièvre, même page : 1er août 1905, jour d\'une seule source)',
    ],

    // Ligne 3541 Carcassonne - Lézignan par Caunes (tramways de l'Aude) : Carcassonne - Olonzac n'était pas daté. Le
    // tableau de l'article donne deux dates pour Carcassonne - Caunes (10/03/1901 et 05/02/1902) : année 1901 seule,
    // celle d'un compte rendu de conférence (« Carcassonne à Caunes, inaugurée le 10 mars 1901 »).
    [
        'line' => '3541',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1901-01-01',
        'precision' => 'year',
        'from' => 'Carcassonne',
        'to' => 'Caunes-Minervois',
        'source_url' => $wp('Tramways de l\'Aude', 228291278),
        'quote' => 'Carcassonne - Caunes : 10 mars 1901',
        'reason' => 'ouverture de Carcassonne à Caunes (compte rendu de la conférence de M. Prévot, AVF Castelnaudary, 2023 : « Carcassonne à Caunes, inaugurée le 10 mars 1901 »)',
    ],
    [
        'line' => '3541',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1910-06-15',
        'precision' => 'day',
        'from' => 'Caunes-Minervois',
        'to' => 'Félines-Minervois',
        'source_url' => $wp('Tramways de l\'Aude', 228291278),
        'quote' => 'Caunes - Félines : 15 juin 1910',
        'reason' => 'ouverture de Caunes à Félines (carte du tramway du Minervois, office de tourisme, https://www.minervois-caroux.com/_objects/tao_medias/file/carte-velo-tramway-minervois-2025-10132.pdf : « La gare de Caunes-Minervois voit le tramway arriver le 15 juin 1910. »)',
    ],
    [
        'line' => '3541',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1908-01-01',
        'precision' => 'year',
        'from' => 'Félines-Minervois',
        'to' => 'Olonzac',
        'source_url' => $wp('Tramways de l\'Aude', 228291278),
        'quote' => 'Félines - Olonzac : 1er octobre 1908',
        'reason' => 'ouverture de Félines à Olonzac (même carte de l\'office de tourisme : « En 1908 la ligne d\'Olonzac à Félines est en service. » ; jour d\'une seule source)',
    ],

    // Ligne 3887 Pau - Aire-sur-l'Adour (Pau-Oloron-Mauléon) : Morlaàs - Saint-Laurent-Bretagne n'était pas daté.
    // Pau - Morlaàs (1902) et Garlin - Aire (1907) n'ont qu'une source chacune.
    [
        'line' => '3887',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1904-05-26',
        'precision' => 'day',
        'from' => 'Morlaàs',
        'to' => 'Saint-Laurent-Bretagne',
        'source_url' => $wp('Chemin de fer de Pau-Oloron-Mauléon', 235512001),
        'quote' => 'de Morlaàs à Saint-Laurent-Bretagne le 26 mai 1904,',
        'reason' => 'ouverture de Morlaàs à Saint-Laurent-Bretagne (article appuyé sur le Recueil général des lois, décrets et arrêtés de 1905 ; année aussi dans la liste de la FACS, https://www.facs-patrimoine-ferroviaire.fr/histoire/les-chemins-de-fer-secondaires/liste/64)',
    ],

    // Ligne 3607 Cusset - Balbigny (Chemins de fer du Centre) : Lavoine - Balbigny n'était pas daté. Jours d'un seul
    // auteur (Laederich, repris par deux articles), années confirmées par l'inventaire 5S des Archives de la Loire.
    [
        'line' => '3607',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1912-01-01',
        'precision' => 'year',
        'from' => 'Lavoine',
        'to' => 'Juré',
        'source_url' => $wp('Société des Chemins de fer du Centre', 237748308),
        'quote' => 'Lavoine-Laprugne à Saint-Just-en-Chevalet, 16 km, ouverture le 6 avril 1912',
        'reason' => 'ouverture de Lavoine à Saint-Just-en-Chevalet (06/04/1912) puis à Juré (06/07/1912) ; inventaire 5S des Archives de la Loire : « Ligne de Vichy-Cusset à Saint-Germain-Laval et embranchement de Juré à Saint-Polgues (1912-1914) »',
    ],
    [
        'line' => '3607',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1913-01-01',
        'precision' => 'year',
        'from' => 'Juré',
        'to' => 'Balbigny',
        'source_url' => $wp('Société des Chemins de fer du Centre', 237748308),
        'quote' => 'Juré à Balbigny via Saint-Germain-Laval, 30 km, ouverture le 15 novembre 1913',
        'reason' => 'ouverture de Juré à Balbigny (inventaire 5S des Archives de la Loire : « Ligne de Saint-Germain-Laval à Balbigny (1913-1927) »)',
    ],

    // Ligne 3426 Avesnes - Haspres : Avesnes-sur-Helpe - Solesmes (par Landrecies) n'était pas daté.
    [
        'line' => '3426',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1907-10-28',
        'precision' => 'day',
        'from' => 'Avesnes-sur-Helpe',
        'to' => 'Solesmes',
        'source_url' => $wp('Chemin de fer d\'Avesnes-sur-Helpe à Solesmes', 223550086),
        'quote' => 'La ligne et celle de Solesmes à Quiévy sont mises en service le 28 octobre 1907.',
        'reason' => 'ouverture d\'Avesnes-sur-Helpe à Solesmes (http://2c2m.avesnois.free.fr/2010_Bousies_histoire_rail.html : « Le 28 octobre 1907 est mise en service la ligne de chemin de fer Avesnes-sur-Helpe - Solesmes (47 km) via Landrecies. » ; année dans la liste de la FACS)',
    ],

    // Lignes secondaires dont seul un morceau de gare était daté par OHM. Années confirmées par la liste de la FACS
    // des chemins de fer secondaires (https://www.facs-patrimoine-ferroviaire.fr/histoire/les-chemins-de-fer-secondaires/liste/<département>) ;
    // le jour, quand il n'est donné que par l'article, n'est pas retenu.

    // Ligne 3389 Amiens - Aumale : Saint-Roch - Beaucamps-le-Vieux (fermeture de 1948 déjà en base).
    [
        'line' => '3389',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1891-01-01',
        'precision' => 'year',
        'from' => 'Saint-Roch',
        'to' => 'Beaucamps-le-Vieux',
        'source_url' => $wp('Chemins de fer départementaux de la Somme', 239886986),
        'quote' => 'Amiens (gare Saint-Roch) - Beaucamps-le-Vieux, ouvert le 20 juin 1891 ;',
        'reason' => 'ouverture d\'Amiens à Beaucamps-le-Vieux (FACS, Somme : 1891)',
    ],

    // Ligne 4122 Toulouse - Villemur (Chemins de fer du Sud-Ouest) : 1912 à 1937 (les dates OHM de 1906 et 1953 ne
    // couvrent qu'un morceau à Toulouse).
    [
        'line' => '4122',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1912-01-01',
        'precision' => 'year',
        'source_url' => $wp('Chemins de fer du Sud-Ouest', 241040330),
        'quote' => 'ligne de Toulouse à Villemur-sur-Tarn, depuis Toulouse-Bonnefoy, Launaguet, Montberon, Cépet, Fronton et Villemur-sur-Tarn (41 km) : ouverture 1912 ;',
        'reason' => 'ouverture de toute la ligne (FACS, Haute-Garonne : Toulouse-Bonnefoy - Villemur, 1912)',
    ],
    [
        'line' => '4122',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1937-01-01',
        'precision' => 'year',
        'source_url' => $wp('Chemins de fer du Sud-Ouest', 241040330),
        'quote' => 'En 1937 : Toulouse- Villemur',
        'reason' => 'fermeture de toute la ligne, remplacée par des cars (FACS, Haute-Garonne : fermeture 1937)',
    ],

    // Ligne 4163 Villefranche - La Clayette : Villefranche - Monsols (chemin de fer du Beaujolais, 1901 à 1934) et
    // Monsols - La Clayette (Rhône - Saône-et-Loire, 1911 à 1934).
    [
        'line' => '4163',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1901-01-01',
        'precision' => 'year',
        'from' => 'Villefranche-sur-Saône',
        'to' => 'Monsols',
        'source_url' => $wp('Chemin de fer du Beaujolais', 240146875),
        'quote' => '1901 : ouverture des deux lignes',
        'reason' => 'ouverture de Villefranche à Monsols (FACS, Rhône : 1901)',
    ],
    [
        'line' => '4163',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1911-01-01',
        'precision' => 'year',
        'from' => 'Monsols',
        'to' => 'La Clayette',
        'source_url' => $wp('Chemins de fer départementaux du Rhône - Saône-et-Loire', 239985765),
        'quote' => 'Le 16 janvier 1911, a lieu la mise en service.',
        'reason' => 'ouverture de Monsols à La Clayette (FACS, Saône-et-Loire : 1911)',
    ],
    [
        'line' => '4163',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1934-01-01',
        'precision' => 'year',
        'source_url' => $wp('Chemin de fer du Beaujolais', 240146875),
        'quote' => '1934 : l\'exploitation des lignes est définitivement arrêtée le 31 mars',
        'reason' => 'fin de l\'exploitation des deux compagnies en 1934 (Rhône - Saône-et-Loire, oldid 239985765 : « Le 15 septembre 1934, a lieu la fin du service ferroviaire » ; FACS : 1934)',
    ],

    // Ligne 4145 Varennes - Digoin (réseau secondaire de l'Allier) : Le Donjon - Digoin de 1912 à 1939. Varennes - Le
    // Donjon reste sans ouverture sûre (1906 selon l'article, 1912 selon la FACS).
    [
        'line' => '4145',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1912-01-01',
        'precision' => 'year',
        'from' => 'Le Donjon',
        'to' => 'Digoin',
        'source_url' => $wp('Réseau ferré secondaire de l\'Allier', 236465242),
        'quote' => 'Le Donjon - Digoin, 24 km, ouverture le 30 septembre 1912 ;',
        'reason' => 'ouverture du Donjon à Digoin (FACS, Allier : 1912)',
    ],
    [
        'line' => '4145',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1939-01-01',
        'precision' => 'year',
        'source_url' => $wp('Réseau ferré secondaire de l\'Allier', 236465242),
        'quote' => 'Il est décidé que l\'ensemble du réseau départemental soit abandonné. Cette décision entre en vigueur en 1939.',
        'reason' => 'fermeture de toute la ligne (FACS, Allier : 1939 ; Varennes - Le Donjon fermée le 1er juin 1939 selon l\'article)',
    ],

    // Ligne 3847 Moulins - Cosne-d'Allier (réseau secondaire de l'Allier) : trois sections de 1886 et 1887, fermeture
    // en 1950 (FACS : 1949 pour les deux premières sections, même ère).
    [
        'line' => '3847',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1886-01-01',
        'precision' => 'year',
        'from' => 'Moulins',
        'to' => 'Bourbon-l\'Archambault',
        'source_url' => $wp('Réseau ferré secondaire de l\'Allier', 236465242),
        'quote' => 'Moulins - Bourbon-l\'Archambault, 26 km, ouverture le 1er décembre 1886 ;',
        'reason' => 'ouverture de Moulins à Bourbon-l\'Archambault (FACS, Allier : 1886)',
    ],
    [
        'line' => '3847',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1887-01-01',
        'precision' => 'year',
        'from' => 'Bourbon-l\'Archambault',
        'to' => 'Cosne-d\'Allier',
        'source_url' => $wp('Réseau ferré secondaire de l\'Allier', 236465242),
        'quote' => 'Bourbon-l\'Archambault - Buxières-les-Mines, 19 km, ouverture le 1er février 1887 ;',
        'reason' => 'ouverture de Bourbon-l\'Archambault à Buxières-les-Mines (01/02/1887) puis à Cosne-sur-l\'Œil (13/10/1887) ; FACS, Allier : 1887 pour les deux sections',
    ],
    [
        'line' => '3847',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1950-01-01',
        'precision' => 'year',
        'source_url' => $wp('Réseau ferré secondaire de l\'Allier', 236465242),
        'quote' => 'Le 30 juin 1950, Moulins - Cosne-d\'Allier ;',
        'reason' => 'fermeture de toute la ligne (FACS, Allier : 1950 pour Buxières - Cosne, 1949 pour le reste)',
    ],

    // Ligne 3623 Dompierre-sur-Besbre - Lapalisse : 1893 à 1939 (OHM ne datait que la gare de Dompierre).
    [
        'line' => '3623',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1893-01-01',
        'precision' => 'year',
        'source_url' => $wp('Chemin de fer de Dompierre à Lapalisse', 234273619),
        'quote' => 'La Société Anonyme du chemin de fer d\'intérêt local de Dompierre à la Palisse met en service un chemin de fer le 26 octobre 1893.',
        'reason' => 'ouverture de toute la ligne (FACS, Allier : 1893 ; article de la gare de Dompierre-Sept-Fons, oldid 230261356 : ligne créée en 1893)',
    ],
    [
        'line' => '3623',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1939-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Dompierre-Sept-Fons', 230261356),
        'quote' => 'le département décide la fermeture de la ligne qui intervient le 1er juin 1939.',
        'reason' => 'fermeture de toute la ligne (réseau secondaire de l\'Allier, oldid 236465242 : sections fermées le 1er juin 1939 dont Dompierre - Lapalisse ; FACS : 1939)',
    ],

    // Ligne 3516 Bourg - Saint-Laurent-lès-Mâcon (tramways de l'Ain) : les deux sections du milieu (1914).
    [
        'line' => '3516',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1914-01-01',
        'precision' => 'year',
        'from' => 'Treffort',
        'to' => 'Montrevel-en-Bresse',
        'source_url' => $wp('Tramways de l\'Ain', 241508924),
        'quote' => 'Treffort - Moulins-des-Ponts, 16 km, ouverte en 1914, fermée en 1938 ;',
        'reason' => 'ouverture de Treffort à Moulins-des-Ponts et de Moulins-des-Ponts à Montrevel (« Moulins-des-Ponts - Montrevel, 19 km, ouverte en 1914, fermée en 1936 ») ; FACS, Ain : 1914',
    ],

    // Ligne 3627 Draguignan - Colomars (Central-Var) : Grasse - Colomars, ouverte avec la première section de Nice -
    // Digne (5 ou 7 juin 1892 selon les sources : mois seul). Sa fin (destruction des ponts en 1944 ou fermeture de
    // 1950) reste à établir.
    [
        'line' => '3627',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1892-06-01',
        'precision' => 'month',
        'from' => 'Grasse',
        'to' => 'Colomars',
        'source_url' => $wp('Ligne Central-Var', 229972705),
        'quote' => 'Elle est entreprise en 1890 et mise en service le 5 juin 1892, en même temps qu’une première section de la ligne Nice - Digne, à laquelle elle se raccordait à Colomars par le pont à deux étages de la Manda (un pour les véhicules routiers, un pour le chemin de fer).',
        'reason' => 'ouverture de Grasse à Colomars (Chemins de fer de Provence, oldid 240169505 : « fut mise en service en juin 1892 » ; article anglais Train des Pignes : 7 juin 1892)',
    ],

    // Ligne 4459 Nice - Digne : Nice - Colomars ouvre avec Grasse - Colomars en juin 1892 (mêmes sources), Colomars -
    // Puget-Théniers et Saint-André - Mézel en 1892. Digne - Mézel (14/08/1891 selon un seul article) reste à confirmer.
    [
        'line' => '4459',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1892-06-01',
        'precision' => 'month',
        'from' => 'Nice',
        'to' => 'Colomars',
        'source_url' => $wp('Chemins de fer de Provence', 240169505),
        'quote' => 'Enfin, la section Grasse - Nice, concédée postérieurement au reste de la ligne, fut mise en service en juin 1892, en même temps qu\'une première section de la ligne Nice - Digne, à laquelle elle se raccordait à Colomars.',
        'reason' => 'ouverture de Nice à Colomars (Ligne Central-Var, oldid 229972705 : 5 juin 1892 ; article anglais Train des Pignes : « Nice - Colomars (2 June 1892) »)',
    ],
    [
        'line' => '4459',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1892-01-01',
        'precision' => 'year',
        'from' => 'Colomars',
        'to' => 'Puget-Théniers',
        'source_url' => $wp('Ligne de Nice à Digne', 240211690),
        'quote' => 'En 1892, les tronçons Nice-Colomars (13 km), Colomars-Puget-Théniers (45 km) et Saint-André-de-Méouilles (aujourd\'hui Saint-André-les-Alpes) - Mézel (31 km) sont mis en service.',
        'reason' => 'ouverture de Colomars à Puget-Théniers (article anglais Train des Pignes : 8 août 1892)',
    ],
    [
        'line' => '4459',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1892-01-01',
        'precision' => 'year',
        'from' => 'Saint-André-les-Alpes',
        'to' => 'Mézel',
        'source_url' => $wp('Ligne de Nice à Digne', 240211690),
        'quote' => 'En 1892, les tronçons Nice-Colomars (13 km), Colomars-Puget-Théniers (45 km) et Saint-André-de-Méouilles (aujourd\'hui Saint-André-les-Alpes) - Mézel (31 km) sont mis en service.',
        'reason' => 'ouverture de Saint-André à Mézel (article anglais Train des Pignes : 15 mai 1892)',
    ],

    // Ligne 3719 La Roche-sur-Yon - Legé (tramways de la Vendée) : La Roche-sur-Yon - La Noue (33 km) en 1901, La Noue -
    // Legé (2 km) en 1904, même ère ; fermeture en 1939 (les dates OHM de 1900 et 1948 ne couvrent qu'un morceau à La
    // Roche-sur-Yon). « La Noue », lieu-dit, n'est pas reconnu par la résolution des portions : ouverture de toute la
    // ligne en 1901.
    [
        'line' => '3719',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1901-01-01',
        'precision' => 'year',
        'source_url' => $wp('Tramways de la Vendée', 236094882),
        'quote' => 'La Roche-sur-Yon - La Noue (33 km) 1901-1939 ;',
        'reason' => 'ouverture de La Roche-sur-Yon à La Noue (FACS, Vendée : 1901 ; La Noue - Legé, 2 km, le 1er avril 1904 selon l\'article, 1904 selon la FACS)',
    ],
    [
        'line' => '3719',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1939-01-01',
        'precision' => 'year',
        'source_url' => $wp('Tramways de la Vendée', 236094882),
        'quote' => 'La Roche-sur-Yon - La Noue (33 km) 1901-1939 ;',
        'reason' => 'fermeture de toute la ligne (FACS, Vendée : 1939 pour les deux sections)',
    ],

    // Gares ouvertes avant leurs lignes (rapport aprem-2111/gares-avant-lignes.md, 22/11/2026).

    // 660000 Bayonne - Saint-Jean-Pied-de-Port : les trois sections manquaient (seul figurait un 26/06/1898
    // Ossès - Saint-Étienne-de-Baïgorry, qui est l'embranchement 661000, déjà daté de son côté).
    [
        'line' => '660000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '1898-06-26'],
        'label' => 'Ouverture de l\'embranchement de Saint-Étienne-de-Baïgorry (ligne 661000)',
        'source_url' => $wp('Ligne de Bayonne à Saint-Jean-Pied-de-Port', 238754007),
        'quote' => 'Ossès à Saint-Jean-Pied-de-Port : 11 décembre 1898',
        'reason' => 'embranchement de Saint-Étienne-de-Baïgorry (ligne 661000), pas une section de Bayonne - Saint-Jean-Pied-de-Port',
    ],
    [
        'line' => '660000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1891-01-19',
        'precision' => 'day',
        'from' => 'Bayonne',
        'to' => 'Cambo-les-Bains',
        'source_url' => $wp('Ligne de Bayonne à Saint-Jean-Pied-de-Port', 238754007),
        'quote' => 'Bayonne (bif de Mousserolles) à Cambo-les-Bains : 19 janvier 1891',
        'reason' => 'première section de la ligne',
    ],
    [
        'line' => '660000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1892-08-20',
        'precision' => 'day',
        'from' => 'Cambo-les-Bains',
        'to' => 'Ossès',
        'source_url' => $wp('Ligne de Bayonne à Saint-Jean-Pied-de-Port', 238754007),
        'quote' => 'Cambo-les-Bains à Ossès : 20 août 1892',
        'reason' => 'deuxième section de la ligne',
    ],
    [
        'line' => '660000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1898-12-11',
        'precision' => 'day',
        'from' => 'Ossès',
        'to' => 'Saint-Jean-Pied-de-Port',
        'source_url' => $wp('Ligne de Bayonne à Saint-Jean-Pied-de-Port', 238754007),
        'quote' => 'Ossès à Saint-Jean-Pied-de-Port : 11 décembre 1898',
        'reason' => 'dernière section de la ligne',
    ],

    // 538000 Saint-Benoît - La Rochelle-Ville : la section Poitiers (Saint-Benoît) - Niort, où est
    // Coulombiers, ouvre le 07/07/1856 ; l'événement OHM du 07/09/1857 est celui de Niort - La Rochelle.
    [
        'line' => '538000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1856-07-07',
        'precision' => 'day',
        'from' => 'St-Benoît',
        'to' => 'Niort',
        'source_url' => $wp('Gare de Coulombiers', 235031480),
        'quote' => 'La gare de Coulombiers est mise en service le 7 juillet 1856',
        'reason' => 'section Poitiers (Saint-Benoît) - Niort de la Compagnie du Paris-Orléans (Journal des chemins de fer du 12/07/1856 et Palau, cités par l\'article de la gare)',
    ],

    // 415000 Lison - Lamballe : la section Dol-de-Bretagne - Lamballe ouvre le 29/12/1879 ; l'événement OHM
    // « 1979-12-29 » en est une faute de frappe.
    [
        'line' => '415000',
        'action' => 'neutralize',
        'match' => ['source' => 'ohm', 'type' => 'opening', 'date' => '1979-12-29'],
        'label' => 'Faute de frappe sur l\'année (1979 pour 1879)',
        'source_url' => $wp('Gare de Plancoët', 228547460),
        'quote' => 'est mise en service le 29 décembre 1879',
        'reason' => 'la section Dol-de-Bretagne - Lamballe est mise en service le 29/12/1879',
    ],
    [
        'line' => '415000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1879-12-29',
        'precision' => 'day',
        'from' => 'Dol-de-Bretagne',
        'to' => 'Lamballe',
        'source_url' => $wp('Gare de Plancoët', 228547460),
        'quote' => 'La section à voie unique de la gare de Dol-de-Bretagne à la gare de Lamballe, sur laquelle se situe la gare de Plancoët, est mise en service le 29 décembre 1879',
        'reason' => 'ouverture de Dol-de-Bretagne à Lamballe (article de la gare, Compagnie de l\'Ouest)',
    ],

    // 884000 Bourg-en-Bresse - Bellegarde : le premier tronçon, de Bourg à Simandre-sur-Suran (cinq gares dont
    // Sénissiat-Revonnas), ouvre le 10/03/1876 ; Bourg - La Cluse le 29/03/1877.
    [
        'line' => '884000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1876-03-10',
        'precision' => 'day',
        'from' => 'Bourg-en-Bresse',
        'to' => 'Simandre-sur-Suran',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Bourg-en-Bresse_%C3%A0_Bellegarde&oldid=240169436',
        'quote' => 'L\'ouverture du premier tronçon a eu lieu le 10 mars 1876',
        'reason' => 'premier tronçon de Bourg-en-Bresse à Simandre-sur-Suran, qui porte Sénissiat-Revonnas',
    ],

    // Gares ouvertes avant leurs lignes, suite (storage/app/reports/agents/aprem-2111/gares-avant-lignes-2.md).

    // 977000 Invalides - Versailles-Rive-Gauche : Versailles-Rive-Gauche ouvre le 10/09/1840 avec la ligne de
    // Paris (embarcadère du Maine) à Versailles par la rive gauche ; la ligne n'avait que l'année 1840.
    [
        'line' => '977000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1840-09-10',
        'precision' => 'day',
        'from' => 'Porchefontaine',
        'to' => 'Versailles-Rive-Gauche',
        'source_url' => $wp('Gare de Versailles-Château-Rive-Gauche', 240733330),
        'quote' => 'lors de l\'inauguration de la ligne de Paris à Versailles par la rive gauche de la Seine',
        'reason' => 'ouverture de la ligne de Paris à Versailles par la rive gauche le 10/09/1840 (article de la gare), date au jour de l\'année 1840 déjà en base',
    ],

    // 798000 Saint-Georges-d'Aurac - Saint-Étienne-Châteaucreux : le 06/10/2014 n'est pas une ouverture mais la
    // réouverture de la section Le Puy - Firminy, fermée pour travaux depuis février 2014 (« avant que
    // l'ouverture ne soit repoussée au 6 octobre en raison de contraintes techniques » ; La Montagne,
    // « Pas de train entre Firminy et Le Puy-en-Velay avant octobre », 23/08/2014).
    [
        'line' => '798000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '2014-10-06'],
        'label' => 'Réouverture après travaux (Le Puy - Firminy), pas une ouverture',
        'source_url' => $wp('Ligne de Saint-Georges-d\'Aurac à Saint-Étienne-Châteaucreux', 238752330),
        'quote' => 'avant que l\'ouverture ne soit repoussée au 6 octobre en raison de contraintes techniques',
        'reason' => 'la section Le Puy - Firminy ouvre en 1863 et 1866 ; en 2014 elle rouvre après des travaux commencés en février 2014',
    ],
    [
        'line' => '798000',
        'action' => 'add',
        'type' => 'reopening',
        'date' => '2014-10-06',
        'precision' => 'day',
        'from' => 'Le Puy',
        'to' => 'Firminy',
        'source_url' => $wp('Ligne de Saint-Georges-d\'Aurac à Saint-Étienne-Châteaucreux', 238752330),
        'quote' => 'avant que l\'ouverture ne soit repoussée au 6 octobre en raison de contraintes techniques',
        'reason' => 'réouverture de la section Le Puy - Firminy après les travaux de modernisation commencés en février 2014 (La Montagne, 23/08/2014)',
    ],

    // Sections-dates (storage/app/reports/agents/aprem-2111/sections-dates.md) : 813000 Le Martinet - Beaucaire.
    // La section Le Martinet - Saint-Julien (PK 0 à 10) ouvre le 30/08/1880, trois ans avant Uzès - Saint-Julien
    // (25/09/1883) : l'article de la gare de Saint-Julien - les Fumades date du 30 août 1880 la mise en service par
    // le PLM de « la section passant par la gare de sa ligne du Martinet à Beaucaire », et la fiche de la ligne du
    // Gard touristique dit « Le 30 août 1880, le premier tronçon entre Le Martinet et St Julien est ouvert ». Sans
    // cette ouverture, l'ouverture localisée de 1883 laissait la section hors service en 1880.
    [
        'line' => '813000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1880-08-30',
        'precision' => 'day',
        'from' => 'Le Martinet',
        'to' => 'Saint-Julien-de-Cassagnas',
        'source_url' => $wp('Gare de Saint-Julien - les Fumades', 235034251),
        'quote' => 'met en service, le 30 août 1880, la section passant par la gare de sa ligne du Martinet à Beaucaire',
        'reason' => 'premier tronçon de la ligne, du Martinet à Saint-Julien, ouvert le 30/08/1880 (article de la gare ; fiche https://www.tourismegard.com/fr/fiche/patrimoine-culturel/la-ligne-ferroviaire-le-martinet-beaucaire-le-martinet_TFO7606245/ : « Le 30 aout 1880, le premier tronçon entre Le Martinet et St Julien est ouvert », puis Saint-Julien - Uzès - Beaucaire le 25/09/1883)',
    ],

    // Restes de la fiche ligne (storage/app/reports/agents/aprem-2111/restes-fiches.md) : 005000 LGV Est européenne.
    // Deux phases (sections de `line_sections` : PK 0 à 300 000 et 300 000 à 406 000), mais une seule date de « toute la
    // ligne » pour chacune (2007 de Wikidata, 2016 de la liste de Wikipédia) : la fiche ne pouvait pas dater la seconde
    // phase. La première phase est ajoutée au jour près ; l'événement du 03/07/2016 est déplacé sur la seconde phase.
    [
        'line' => '005000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '2007-06-10',
        'precision' => 'day',
        'from' => 'Vaires-sur-Marne',
        'to' => 'Baudrecourt',
        'pk_start' => 0,
        'pk_end' => 300000,
        'source_url' => $wp('LGV Est européenne', 239516798),
        'quote' => 'Ce premier tronçon est mis en service le 10 juin 2007, après cinq ans de travaux et une inauguration le 15 mars.',
        'reason' => 'première phase de la LGV Est, de Vaires-sur-Marne à Baudrecourt (300 km), mise en service le 10/06/2007 (Wikipédia ; Wikidata donne 2007 pour toute la ligne)',
    ],
    [
        'line' => '005000',
        'action' => 'relocate',
        'match' => ['source' => 'wikidata', 'type' => 'opening', 'date' => '2007-01-01'],
        'from' => 'Vaires-sur-Marne',
        'to' => 'Baudrecourt',
        'pk_start' => 0,
        'pk_end' => 300000,
        'source_url' => $wp('LGV Est européenne', 239516798),
        'quote' => 'Ce premier tronçon est mis en service le 10 juin 2007, après cinq ans de travaux et une inauguration le 15 mars.',
        'reason' => 'l\'année 2007 de Wikidata est celle de la première phase (300 km) ; sans portion, elle comptait aussi la seconde phase en service dès 2007',
    ],
    [
        'line' => '005000',
        'action' => 'relocate',
        'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '2016-07-03'],
        'from' => 'Baudrecourt',
        'to' => 'Vendenheim',
        'pk_start' => 300000,
        'pk_end' => 406000,
        'source_url' => $wp('LGV Est européenne', 239516798),
        'quote' => 'La mise en service de cette phase est réalisée le 3 juillet 2016.',
        'reason' => 'seconde phase de la LGV Est, de Baudrecourt à Vendenheim (106 km, raccordement à la ligne de Strasbourg à Wissembourg à Eckwersheim), mise en service le 03/07/2016 ; la première phase (300 km) était en service depuis 2007',
    ],

    // Mesure de l'analyse des chronologies Wikipédia (storage/app/reports/agents/aprem-2111/chronologie-mesure.md) :
    // quatre erreurs relevées en relisant les événements gagnés par l'analyse des dates et des étapes.

    // 915000 Veynes - Briançon : « Une seconde voie est posée entre Veynes et La Freissinouse le 28 mars 1883 et
    // entre La Freissinouse et Gap le 6 février 1893 » (l'article dit juste avant « Elle sera mise en service en
    // deux étapes », « elle » étant la mise à double voie). Ce sont deux poses de seconde voie sur une ligne
    // ouverte en 1875 (Veynes - Gap, 1er février 1875), pas des ouvertures. Confirmé par Rail Passion n°171 (« une
    // seconde voie est posée de Veynes à La Freissinouse en mars 1883 ») et la page de la gare de Gap (doublement
    // de la voie vers Veynes en 1893).
    [
        'line' => '915000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '1883-03-28'],
        'label' => 'Pose de la seconde voie entre Veynes et La Freissinouse',
        'source_url' => $wp('Ligne de Veynes à Briançon', 240049469),
        'quote' => 'Une seconde voie est posée entre Veynes et La Freissinouse le 28 mars 1883 et entre La Freissinouse et Gap le 6 février 1893.',
        'reason' => 'mise à double voie d\'une section ouverte le 1er février 1875, pas une ouverture',
    ],
    [
        'line' => '915000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '1893-02-06'],
        'label' => 'Pose de la seconde voie entre La Freissinouse et Gap',
        'source_url' => $wp('Ligne de Veynes à Briançon', 240049469),
        'quote' => 'Une seconde voie est posée entre Veynes et La Freissinouse le 28 mars 1883 et entre La Freissinouse et Gap le 6 février 1893.',
        'reason' => 'mise à double voie d\'une section ouverte le 1er février 1875, pas une ouverture',
    ],

    // 766000 Dijon - Épinac : « En 1952, la ligne rouvre au service des voyageurs entre Dijon-Ville et
    // Gissey-sur-Ouche. Ce tronçon ferme en 1967 » : c'est la fin de ce service voyageurs (la section de Plombières-Canal
    // à Velars restait au fret jusqu'en 1971, de Dijon à Plombières-Canal jusqu'en 1989), pas la fermeture du tronçon. La
    // réouverture de 1952 n'était pas lue. Confirmé par le wiki Routes (réouverture voyageurs le 1er mai 1952,
    // fermeture en 1967) ; lignes-oubliees évoque un « train des pêcheurs » jusqu'au milieu des années 1960.
    [
        'line' => '766000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'closure', 'date' => '1967-01-01'],
        'label' => 'Fin du service voyageurs entre Dijon-Ville et Gissey-sur-Ouche',
        'source_url' => $wp('Ligne de Dijon-Ville à Épinac', 240497509),
        'quote' => 'En 1952, la ligne rouvre au service des voyageurs entre Dijon-Ville et Gissey-sur-Ouche. Ce tronçon ferme en 1967',
        'reason' => 'le tronçon ne ferme qu\'aux voyageurs (il reste exploité au fret) : l\'événement typé est ajouté ci-dessous',
    ],
    [
        'line' => '766000',
        'action' => 'add',
        'type' => 'reopening_passenger',
        'date' => '1952-01-01',
        'precision' => 'year',
        'from' => 'Dijon-Ville',
        'to' => 'Gissey-sur-Ouche',
        'source_url' => $wp('Ligne de Dijon-Ville à Épinac', 240497509),
        'quote' => 'En 1952, la ligne rouvre au service des voyageurs entre Dijon-Ville et Gissey-sur-Ouche.',
        'reason' => 'réouverture aux voyageurs après la fermeture du 6 février 1939 (wiki Routes : 1er mai 1952)',
    ],
    [
        'line' => '766000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1967-01-01',
        'precision' => 'year',
        'from' => 'Dijon-Ville',
        'to' => 'Gissey-sur-Ouche',
        'source_url' => $wp('Ligne de Dijon-Ville à Épinac', 240497509),
        'quote' => 'Ce tronçon ferme en 1967, et la section complète entre Velars-la-Cude et Ivry - Cussy est neutralisée en 1968, puis déclassée en 1969.',
        'reason' => 'fin du service voyageurs rouvert en 1952 (wiki Routes : 1967) ; le fret continue (Velars - Ivry-Cussy jusqu\'en 1968, Plombières - Velars jusqu\'en 1971, Dijon - Plombières jusqu\'en 1989)',
    ],

    // 985000 Choisy-le-Roi - Massy - Verrières : « La ligne ferme le 15 mai 1939. Le 25 septembre 1977, elle rouvre
    // aux voyageurs entre Pont de Rungis et Massy - Palaiseau. » C'est la fin du service voyageurs de la Grande
    // Ceinture stratégique (les pages des gares d'Orly-Ville et de Massy - Palaiseau donnent la même date ; le wiki
    // Routes : « 15 05 1939 : section Orly-Ville - Massy-Palaiseau (service voyageurs) »), pas une fermeture de la
    // ligne, qui sert au fret et à la branche du RER C. La réouverture de 1977 n'était pas lue : sans elle, la ligne
    // resterait sans voyageurs jusqu'en 2030.
    [
        'line' => '985000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'closure', 'date' => '1939-05-15'],
        'label' => 'Fin du service voyageurs de la Grande Ceinture stratégique',
        'source_url' => $wp('Ligne de Choisy-le-Roi à Massy - Verrières', 240652981),
        'quote' => 'La ligne ferme le 15 mai 1939. Le 25 septembre 1977, elle rouvre aux voyageurs entre Pont de Rungis et Massy - Palaiseau.',
        'reason' => 'la ligne ne ferme qu\'aux voyageurs (l\'article la dit rouverte aux voyageurs en 1977) : l\'événement typé est ajouté ci-dessous',
    ],
    [
        'line' => '985000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1939-05-15',
        'precision' => 'day',
        'from' => 'Choisy-le-Roi',
        'to' => 'Massy - Verrières',
        'source_url' => $wp('Ligne de Choisy-le-Roi à Massy - Verrières', 240652981),
        'quote' => 'La ligne ferme le 15 mai 1939.',
        'reason' => 'fin du service voyageurs de la Grande Ceinture stratégique (Wikipédia ; pages des gares d\'Orly-Ville et de Massy - Palaiseau ; wiki Routes)',
    ],
    [
        'line' => '985000',
        'action' => 'add',
        'type' => 'reopening_passenger',
        'date' => '1977-09-25',
        'precision' => 'day',
        'from' => 'Pont de Rungis',
        'to' => 'Massy - Palaiseau',
        'source_url' => $wp('Ligne de Choisy-le-Roi à Massy - Verrières', 240652981),
        'quote' => 'Le 25 septembre 1977, elle rouvre aux voyageurs entre Pont de Rungis et Massy - Palaiseau.',
        'reason' => 'réouverture aux voyageurs de la section parcourue depuis par la branche Massy - Pont de Rungis du RER C',
    ],

    // 785000 Saint-Germain-des-Fossés - Darsac : « Fermeture au service des voyageurs. Les sections de Vichy à Courty
    // et d'Arlanc à Darsac sont fermées le 26 novembre 1971, la section de Pont-de-Dore à Arlanc le 28 septembre 1980. »
    // La phrase est sous le titre « Fermeture au service des voyageurs » : la lecture sans trafic faisait fermer la
    // section à tout trafic en 1980, alors qu'elle reste au fret (Courpière - Arlanc jusqu'au 5 février 1988). La fin
    // des voyageurs du 28/09/1980 est déjà posée par lignes-oubliees.
    [
        'line' => '785000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'closure', 'date' => '1980-09-28'],
        'label' => 'Fin du service voyageurs entre Pont-de-Dore et Arlanc',
        'source_url' => $wp('Ligne de Saint-Germain-des-Fossés à Darsac', 235337759),
        'quote' => 'la section de Pont-de-Dore à Arlanc le 28 septembre 1980.',
        'reason' => 'phrase de la section « Fermeture au service des voyageurs » : la section reste au fret jusqu\'en 1988 (fin des voyageurs déjà ajoutée par lignes-oubliees)',
    ],


    // Retranchements annulés par le Conseil d'État (storage/app/reports/agents/aprem-2111/faits-juridiques.md).
    // Avant 2007, une section fermée était retranchée du réseau ferré national par décret au Journal officiel.
    // La FNAUT a obtenu l'annulation de plusieurs de ces décrets pour vice de procédure (décisions ci-dessous) :
    // un décret annulé est réputé n'avoir jamais existé, la section reste dans le réseau ferré national. Les
    // articles de lignes (Wikipédia) et le fichier de lignes-oubliees datent pourtant ces retranchements comme des
    // déclassements en vigueur : chaque événement devient un simple fait (« other »), sans effet sur la carte,
    // et garde la décision qui l'annule.
    [
        'line' => '460000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'declassification', 'date' => '2001-02-12'],
        'label' => 'Retranchement du 12 février 2001 annulé par le Conseil d\'État (décision n° 232574 du 3 décembre 2003)',
        'source_url' => 'https://www.legifrance.gouv.fr/ceta/id/CETATEXT000008186183/',
        'quote' => 'Le décret du 12 février 2001 est annulé.',
        'reason' => 'Châteaubriant - Saint-Vincent-des-Landes (PK 359,200 à 364,442) : décret annulé en totalité (avis de communes non adoptés par délibération du conseil municipal, procédure irrégulière)',
    ],
    [
        'line' => '728000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'declassification', 'date' => '2001-02-12'],
        'label' => 'Retranchement du 12 février 2001 annulé par le Conseil d\'État (décision n° 232574 du 3 décembre 2003)',
        'source_url' => 'https://www.legifrance.gouv.fr/ceta/id/CETATEXT000008186183/',
        'quote' => 'Le décret du 12 février 2001 est annulé.',
        'reason' => 'Tournemire - Roquefort - Saint-Affrique, totalité (PK 524,927 à 539,590) : décret annulé en totalité (avis de communes non adoptés par délibération du conseil municipal, procédure irrégulière)',
    ],
    [
        'line' => '856000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'declassification', 'date' => '2001-02-12'],
        'label' => 'Retranchement du 12 février 2001 annulé par le Conseil d\'État (décision n° 232574 du 3 décembre 2003)',
        'source_url' => 'https://www.legifrance.gouv.fr/ceta/id/CETATEXT000008186183/',
        'quote' => 'Le décret du 12 février 2001 est annulé.',
        'reason' => 'section de Vesoul (PK 468,579 à 469,344) : décret annulé en totalité (avis de communes non adoptés par délibération du conseil municipal, procédure irrégulière)',
    ],
    [
        'line' => '523000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'declassification', 'date' => '2001-02-12'],
        'label' => 'Retranchement du 12 février 2001 annulé par le Conseil d\'État (décision n° 232574 du 3 décembre 2003)',
        'source_url' => 'https://www.legifrance.gouv.fr/ceta/id/CETATEXT000008186183/',
        'quote' => 'Le décret du 12 février 2001 est annulé.',
        'reason' => 'Bressuire - Breuil-Barret (PK 88,900 à 118,810) : décret annulé en totalité (avis de communes non adoptés par délibération du conseil municipal, procédure irrégulière)',
    ],
    [
        'line' => '511000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'declassification', 'date' => '2001-02-12'],
        'label' => 'Retranchement du 12 février 2001 annulé par le Conseil d\'État (décision n° 232574 du 3 décembre 2003)',
        'source_url' => 'https://www.legifrance.gouv.fr/ceta/id/CETATEXT000008186183/',
        'quote' => 'Le décret du 12 février 2001 est annulé.',
        'reason' => 'sections de Gouis (PK 36,374 à 36,931) et de La Flèche (PK 47,100 à 48,417) : décret annulé en totalité (avis de communes non adoptés par délibération du conseil municipal, procédure irrégulière)',
    ],
    [
        'line' => '380000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'declassification', 'date' => '2001-01-01'],
        'label' => 'Retranchement du 12 février 2001 annulé par le Conseil d\'État (décision n° 232574 du 3 décembre 2003)',
        'source_url' => 'https://www.legifrance.gouv.fr/ceta/id/CETATEXT000008186183/',
        'quote' => 'Le décret du 12 février 2001 est annulé.',
        'reason' => 'section au départ de Caen (PK 0,000 à 2,365), datée seulement de 2001 par l\'article : décret annulé en totalité (avis de communes non adoptés par délibération du conseil municipal, procédure irrégulière)',
    ],
    [
        'line' => '712000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'declassification', 'date' => '2001-10-17'],
        'label' => 'Retranchement du 17 octobre 2001 annulé par le Conseil d\'État (décision n° 241137 du 4 août 2006)',
        'source_url' => 'https://www.conseil-etat.fr/fr/arianeweb/CE/decision/2006-08-04/241137',
        'quote' => 'Le décret du 17 octobre 2001 est annulé en tant qu\'il porte retranchement de sections des lignes Sablé-Montoir et Chaugey-Lons le Saulnier et des sections de ligne Fécamp-Saint-Vaast, Serqueux-Dieppe et La Courtine-Ussel.',
        'reason' => 'La Courtine - Ussel (PK 447,900 à 466,050) : décret annulé en tant qu\'il retranche cette section : la fermeture de La Courtine - Ussel avait été annulée par jugement définitif du tribunal administratif de Limoges du 31 décembre 2002, la section ne pouvait donc être retranchée',
    ],
    [
        'line' => '007000',
        'action' => 'neutralize',
        'match' => ['source' => 'lignes-oubliees', 'type' => 'declassification', 'date' => '2002-12-09'],
        'label' => 'Retranchement du 9 décembre 2002 annulé par le Conseil d\'État (décision n° 253607 du 28 juillet 2004)',
        'source_url' => 'https://www.conseil-etat.fr/fr/arianeweb/CE/decision/2004-07-28/253607',
        'quote' => 'Le décret du 9 décembre 2002 est annulé.',
        'reason' => 'Fère-Champenoise - Lenharrée (PK 0,815 à 9,000) : décret annulé en totalité (consultation des organisations nationales d\'usagers non menée, procédure irrégulière)',
    ],
    [
        'line' => '889000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'declassification', 'date' => '2002-12-09'],
        'label' => 'Retranchement du 9 décembre 2002 annulé par le Conseil d\'État (décision n° 253607 du 28 juillet 2004)',
        'source_url' => 'https://www.conseil-etat.fr/fr/arianeweb/CE/decision/2004-07-28/253607',
        'quote' => 'Le décret du 9 décembre 2002 est annulé.',
        'reason' => 'Lagnieu - Villebois (PK 7,000 à 15,769) : décret annulé en totalité (consultation des organisations nationales d\'usagers non menée, procédure irrégulière)',
    ],
    [
        'line' => '317000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'declassification', 'date' => '2002-10-15'],
        'label' => 'Retranchement du 15 octobre 2002 annulé par le Conseil d\'État (décision n° 252670 du 28 juillet 2004)',
        'source_url' => 'https://www.legifrance.gouv.fr/ceta/id/CETATEXT000008155063',
        'quote' => 'Le décret du 15 octobre 2002 est annulé.',
        'reason' => 'Ressons-le-Long - Ambleny-Fontenoy (PK 86,100 à 89,870) : décret annulé en totalité (consultation des organisations nationales d\'usagers non menée, procédure irrégulière)',
    ],
    [
        'line' => '926000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'declassification', 'date' => '2002-10-15'],
        'label' => 'Retranchement du 15 octobre 2002 annulé par le Conseil d\'État (décision n° 252670 du 28 juillet 2004)',
        'source_url' => 'https://www.legifrance.gouv.fr/ceta/id/CETATEXT000008155063',
        'quote' => 'Le décret du 15 octobre 2002 est annulé.',
        'reason' => 'Carpentras - Pernes (PK 23,602 à 28,390) : décret annulé en totalité (consultation des organisations nationales d\'usagers non menée, procédure irrégulière)',
    ],
    [
        'line' => '411000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'declassification', 'date' => '2003-06-12'],
        'label' => 'Retranchement du 12 juin 2003 annulé par le Conseil d\'État (décision n° 259398 du 18 janvier 2006)',
        'source_url' => 'https://www.conseil-etat.fr/fr/arianeweb/CE/decision/2006-01-18/259398',
        'quote' => 'Le décret du 12 juin 2003 est annulé.',
        'reason' => 'Cahan - Berjou (PK 27,182 à 29,317) : décret annulé en totalité (avis de collectivités non adoptés par leur assemblée délibérante, procédure irrégulière)',
    ],
    [
        'line' => '007000',
        'action' => 'neutralize',
        'match' => ['source' => 'lignes-oubliees', 'type' => 'declassification', 'date' => '2003-11-10'],
        'label' => 'Retranchement du 10 novembre 2003 annulé par le Conseil d\'État (décision n° 263468 du 18 janvier 2006)',
        'source_url' => 'https://www.conseil-etat.fr/fr/arianeweb/CE/decision/2006-01-18/263468',
        'quote' => 'Le décret du 10 novembre 2003 est annulé.',
        'reason' => 'Sompuis - Huiron (PK 37,641 à 47,876) : décret annulé en totalité (consultation des organisations nationales d\'usagers non menée, procédure irrégulière)',
    ],
    [
        'line' => '342000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'declassification', 'date' => '2003-11-10'],
        'label' => 'Retranchement du 10 novembre 2003 annulé par le Conseil d\'État (décision n° 263468 du 18 janvier 2006)',
        'source_url' => 'https://www.conseil-etat.fr/fr/arianeweb/CE/decision/2006-01-18/263468',
        'quote' => 'Le décret du 10 novembre 2003 est annulé.',
        'reason' => 'Gisors - Étrépagny (PK 0,344 à 15,380) : décret annulé en totalité (consultation des organisations nationales d\'usagers non menée, procédure irrégulière)',
    ],
    [
        'line' => '607000',
        'action' => 'neutralize',
        'match' => ['source' => 'lignes-oubliees', 'type' => 'declassification', 'date' => '2003-11-10'],
        'label' => 'Retranchement du 10 novembre 2003 annulé par le Conseil d\'État (décision n° 263468 du 18 janvier 2006)',
        'source_url' => 'https://www.conseil-etat.fr/fr/arianeweb/CE/decision/2006-01-18/263468',
        'quote' => 'Le décret du 10 novembre 2003 est annulé.',
        'reason' => 'Charroux - Civray (PK 423,800 à 433,800) : décret annulé en totalité (consultation des organisations nationales d\'usagers non menée, procédure irrégulière)',
    ],
];
