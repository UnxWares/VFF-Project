<?php

/**
 * Corrections SOURCÉES du chantier « fusions de lignes » (vff:merge-split-lines, 2026-10-20) :
 * dates manquantes des lignes qui ont reçu une section fusionnée, relevées en vérifiant
 * leur histoire après la fusion. Rapport : storage/app/reports/agents/fusions.md.
 *
 * Mêmes règles de saisie que line_event_corrections_fins_2.php : permalien Wikipédia (oldid),
 * citation exacte, raison ; pas de tiret long dans les textes.
 *
 * @return list<array<string, mixed>>
 */
$ifsEtretat = 'https://fr.wikipedia.org/w/index.php?title=Ligne_des_Ifs_%C3%A0_%C3%89tretat&oldid=238125959';
$mezidonTrouville = 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_M%C3%A9zidon_%C3%A0_Trouville_-_Deauville&oldid=235017825';

return [

    // 379000 Ligne de Mézidon à Trouville-Deauville (reçoit la section « Ligne de Mézidon à Dives ») :
    // l'import n'a lu que la moitié de la phrase du 18 septembre 1882 (Trouville - Villers) ; la
    // section Dives - Houlgate (Beuzeval), sans date, prenait celle de Mézidon - Dives (1879) et
    // paraissait ouverte dès l'ère 1880. Le 20 juillet 1884 « sur toute sa longueur » ouvre la
    // section centrale restante, Houlgate - Villers-sur-Mer.
    [
        'line' => '379000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1882-09-18',
        'precision' => 'day',
        'from' => 'Dives-Cabourg',
        'to' => 'Houlgate',
        'source_url' => $mezidonTrouville,
        'quote' => 'Les sections de Dives à Beuzeval (Houlgate) et de Trouville - Deauville à Villers-sur-Mer sont ouvertes le 18 septembre 1882.',
        'reason' => 'ouverture de la section Dives - Houlgate (Beuzeval), absente de la base (Journal des transports 1882 p. 153 cité par l\'article)',
    ],
    [
        'line' => '379000',
        'action' => 'relocate',
        'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '1884-07-20'],
        'from' => 'Houlgate',
        'to' => 'Villers-sur-Mer',
        'source_url' => $mezidonTrouville,
        'quote' => 'L\'État termine les travaux de la section manquante. La ligne est ouverte sur toute sa longueur le 20 juillet 1884.',
        'reason' => 'la « section manquante » ouverte le 20 juillet 1884 est la section centrale Houlgate - Villers-sur-Mer (Dives - Houlgate et Trouville - Villers ouvertes en 1882, Mézidon - Dives en 1879)',
    ],

    // 360000 Ligne des Ifs à Étretat (reçoit la section « Ligne des Loges à Etretat » le 2026-10-20) :
    // l'import ne datait l'ouverture qu'à l'année (infobox) et ne lisait pas la fin des voyageurs
    // (infobox « fermeture = 1951 », texte au 30 juin 1951) : la ligne restait « mixte » jusqu'en 1995.
    [
        'line' => '360000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1895-06-22',
        'precision' => 'day',
        'source_url' => $ifsEtretat,
        'quote' => 'Les travaux trainèrent en longueur et la courte antenne ne fut mise en service que le 22 juin 1895',
        'reason' => 'date du jour de la mise en service de toute la ligne (article de ligne, source primaire citée : Le Journal des transports du 6 juillet 1895, p. 529)',
    ],
    [
        'line' => '360000',
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1951-06-30',
        'precision' => 'day',
        'source_url' => $ifsEtretat,
        'quote' => 'Un trafic régulier fut restauré en 1945 à raison de deux navettes quotidiennes mais elles furent supprimées 30 juin 1951, remplacées par des autobus.',
        'reason' => 'fin du service voyageurs de toute la ligne (Banaudo 1982 p. 18), fret seul ensuite jusqu\'au 3 avril 1972',
    ],
];
