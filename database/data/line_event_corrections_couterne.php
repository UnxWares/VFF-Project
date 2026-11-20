<?php

/**
 * Lignes 433000 Couterne - La Ferté-Macé et 434000 Briouze - La Ferté-Macé (chantier « gares
 * absentes », 2026-10-20). Un seul article Wikipédia décrit les deux lignes (« Ligne de Briouze à
 * Couterne », infobox : 433000 de Couterne à La Ferté-Macé, 434000 de Briouze à La Ferté-Macé) :
 * vff:import-wikipedia-chronology verse ses événements sur les DEUX lignes. Chaque événement est
 * donc gardé sur la ligne de sa section, neutralisé sur l'autre, et les sections « de Briouze à
 * Bagnoles-de-l'Orne » sont ramenées à la partie de chaque ligne (La Ferté-Macé est la jonction).
 *
 * Avant (434000 seule, 433000 absente) : le déclassement du 12/11/1954 « de Couterne à
 * Bagnoles-de-l'Orne (PK 0,375 à 6,800) », PK de la ligne 433000, était posé sur 434000 par ses
 * propres PK (de Briouze vers Lonlay-le-Tesson), et la gare de Bagnoles-de-l'Orne disparaissait
 * de la carte de 1890 à 1980.
 *
 * Format : voir line_event_corrections.php. Pas de tiret long dans les textes.
 *
 * @return list<array<string, mixed>>
 */
$briouze = 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Briouze_%C3%A0_Couterne&oldid=233804148';
$bagnoles = 'https://fr.wikipedia.org/w/index.php?title=Gare_de_Bagnoles-de-l%27Orne&oldid=234445086';
$fermetures = 'https://www.lignes-oubliees.com/fichiers/fermetures.pdf';
$detail = 'contredite par les mises en service détaillées de l\'article (Briouze - La Ferté-Macé le 6 décembre 1869, Couterne - La Ferté-Macé le 15 mars 1881)';

return [
    // ── 433000 Couterne - La Ferté-Macé ─────────────────────────────────────────
    [
        'line' => '433000', 'action' => 'add', 'type' => 'opening', 'date' => '1881-03-15', 'precision' => 'day',
        'from' => 'La Ferté-Macé', 'to' => 'Couterne', 'source_url' => $briouze,
        'quote' => 'De Couterne à La Ferté-Macé, le 15 mars 1881 (Compagnie du Chemin de fer de l\'Ouest).',
        'reason' => 'mise en service de la ligne ; même date dans l\'article de la gare de Bagnoles-de-l\'Orne (« mise en service le 15 mars 1881 »), l\'article de la gare de La Ferté-Macé donne le 26 mai 1881 sans référence',
    ],
    [
        'line' => '433000', 'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '1873-01-01'],
        'label' => 'Date de l\'infobox de l\'article (deux lignes)', 'source_url' => $briouze,
        'quote' => 'mise en service = 1869 ; mise en service2 = 1873',
        'reason' => "date sans section de l'infobox d'un article qui décrit deux lignes, {$detail}",
    ],
    [
        'line' => '433000', 'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '1869-01-01'],
        'label' => 'Date de l\'infobox de l\'article (deux lignes)', 'source_url' => $briouze,
        'quote' => 'mise en service = 1869 ; mise en service2 = 1873',
        'reason' => "1869 est l'ouverture de la ligne 434000 Briouze - La Ferté-Macé, {$detail}",
    ],
    // « fermée dès 1940 » (texte sans date précise) : la liste datée et sourcée de l'article donne la
    // fermeture aux voyageurs le 17 février 1941 (Historail), aux marchandises en 1954.
    [
        'line' => '433000', 'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'closure', 'date' => '1940-01-01'],
        'label' => 'Fermeture approximative (« dès 1940 »)', 'source_url' => $briouze,
        'quote' => 'Fermeture au trafic voyageurs : De Couterne à Bagnoles-de-l\'Orne, le 17 février 1941. Fermeture au trafic marchandises : De Couterne à Bagnoles-de-l\'Orne, en 1954.',
        'reason' => 'dates précises et sourcées de la même section : voyageurs le 17 février 1941, marchandises en 1954, déclassement le 12 novembre 1954',
    ],
    // Sections « de Briouze à Bagnoles-de-l'Orne » : sur 433000, la partie La Ferté-Macé - Bagnoles.
    [
        'line' => '433000', 'action' => 'relocate',
        'match' => ['source' => 'wikipedia', 'type' => 'closure_passenger', 'date' => '1991-05-26', 'from' => 'Briouze'],
        'from' => 'La Ferté-Macé', 'to' => 'Bagnoles-de-l\'Orne', 'source_url' => $briouze,
        'quote' => 'Fermeture au trafic voyageurs : De Briouze à Bagnoles-de-l\'Orne, le 26 mai 1991.',
        'reason' => 'partie de la ligne 433000 de la section Briouze - Bagnoles-de-l\'Orne (jonction à La Ferté-Macé)',
    ],
    [
        'line' => '433000', 'action' => 'relocate',
        'match' => ['source' => 'wikipedia', 'type' => 'closure', 'date' => '1992-01-01', 'from' => 'Briouze'],
        'from' => 'La Ferté-Macé', 'to' => 'Bagnoles-de-l\'Orne', 'source_url' => $briouze,
        'quote' => 'alors que la section entre Briouze et Bagnoles-de-l\'Orne continua d\'être exploitée jusqu\'en 1992.',
        'reason' => 'partie de la ligne 433000 de la section Briouze - Bagnoles-de-l\'Orne (jonction à La Ferté-Macé)',
    ],
    // Décision administrative de fermeture (RFF) d'une section sans trafic depuis 1992 : « Événement » (sans
    // effet sur la carte). En fermeture « manual », elle primait sur la fin réelle du trafic (1992) et la
    // section restait « fret » à l'ère 2000.
    [
        'line' => '433000', 'action' => 'add', 'type' => 'other', 'date' => '2006-09-14', 'precision' => 'day',
        'from' => 'La Ferté-Macé', 'to' => 'Bagnoles-de-l\'Orne', 'source_url' => $fermetures,
        'quote' => 'Couterne - La Ferté-Macé | Bagnoles-de-l’Orne - La Ferté-Macé | 6,800 | 15,587 | 8,787 | IG | 14/09/2006',
        'reason' => 'fermeture officielle de la section encore au RFN, sans trafic depuis 1992 (liste des fermetures de lignes de RFF, reprise par lignes-oubliees)',
    ],

    // ── 434000 Briouze - La Ferté-Macé : événements de la section Couterne - Bagnoles (ligne 433000) ──
    [
        'line' => '434000', 'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'closure', 'date' => '1940-01-01'],
        'label' => 'Section de la ligne 433000', 'source_url' => $briouze,
        'quote' => 'La section de Bagnoles-de-l\'Orne à Couterne fut fermée dès 1940 et déferrée après la Seconde Guerre mondiale',
        'reason' => 'Bagnoles-de-l\'Orne - Couterne est sur la ligne 433000 Couterne - La Ferté-Macé',
    ],
    [
        'line' => '434000', 'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'closure_passenger', 'date' => '1941-02-17'],
        'label' => 'Section de la ligne 433000', 'source_url' => $briouze,
        'quote' => 'Fermeture au trafic voyageurs : De Couterne à Bagnoles-de-l\'Orne, le 17 février 1941.',
        'reason' => 'Couterne - Bagnoles-de-l\'Orne est sur la ligne 433000 Couterne - La Ferté-Macé',
    ],
    [
        'line' => '434000', 'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'closure_freight', 'date' => '1954-01-01'],
        'label' => 'Section de la ligne 433000', 'source_url' => $briouze,
        'quote' => 'Fermeture au trafic marchandises : De Couterne à Bagnoles-de-l\'Orne, en 1954.',
        'reason' => 'Couterne - Bagnoles-de-l\'Orne est sur la ligne 433000 Couterne - La Ferté-Macé',
    ],
    [
        'line' => '434000', 'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'declassification', 'date' => '1954-11-12'],
        'label' => 'Section de la ligne 433000', 'source_url' => $briouze,
        'quote' => 'Déclassement : De Couterne à Bagnoles-de-l\'Orne (PK 0,375 à 6,800), le 12 novembre 1954.',
        'reason' => 'PK de la ligne 433000 (origine à Couterne) : posés sur 434000, ils déclassaient Briouze - Lonlay-le-Tesson',
    ],
    [
        'line' => '434000', 'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '1873-01-01'],
        'label' => 'Date de l\'infobox de l\'article (deux lignes)', 'source_url' => $briouze,
        'quote' => 'mise en service = 1869 ; mise en service2 = 1873',
        'reason' => "date sans section de l'infobox d'un article qui décrit deux lignes, {$detail}",
    ],
    [
        'line' => '434000', 'action' => 'relocate',
        'match' => ['source' => 'wikipedia', 'type' => 'closure_passenger', 'date' => '1991-05-26', 'from' => 'Briouze'],
        'from' => 'Briouze', 'to' => 'La Ferté-Macé', 'source_url' => $briouze,
        'quote' => 'Fermeture au trafic voyageurs : De Briouze à Bagnoles-de-l\'Orne, le 26 mai 1991.',
        'reason' => 'partie de la ligne 434000 de la section Briouze - Bagnoles-de-l\'Orne (jonction à La Ferté-Macé)',
    ],
    [
        'line' => '434000', 'action' => 'relocate',
        'match' => ['source' => 'wikipedia', 'type' => 'closure', 'date' => '1992-01-01', 'from' => 'Briouze'],
        'from' => 'Briouze', 'to' => 'La Ferté-Macé', 'source_url' => $briouze,
        'quote' => 'alors que la section entre Briouze et Bagnoles-de-l\'Orne continua d\'être exploitée jusqu\'en 1992.',
        'reason' => 'partie de la ligne 434000 de la section Briouze - Bagnoles-de-l\'Orne (jonction à La Ferté-Macé)',
    ],
    // Fin réelle du trafic : voyageurs le 26/05/1991, marchandises en 1992 (fermeture « tout trafic » de 1992,
    // ci-dessus). La « fermeture » du 14/09/2006 est la décision administrative de RFF sur une ligne sans trafic
    // depuis 1992 : en fermeture, elle primait sur 1992 (Briouze « desservie de 1869 à 2006 » par 434000, fiche
    // gare et fiche ligne en désaccord avec la carte). Gardée comme événement daté, sans effet sur la carte.
    [
        'line' => '434000', 'action' => 'neutralize',
        'match' => ['source' => 'lignes-oubliees', 'type' => 'closure', 'date' => '2006-09-14'],
        'label' => 'Fermeture officielle de la ligne (décision RFF du 14/09/2006, sans trafic depuis 1992)', 'source_url' => $fermetures,
        'quote' => 'Briouze - La Ferté-Macé | Totalité de la ligne | 0,020 | 13,795 | 13,775 | IG | 14/09/2006',
        'reason' => 'décision administrative de fermeture d\'une ligne sans trafic depuis 1992 (voyageurs le 26/05/1991, marchandises en 1992) ; une première décision du 25/01/2001 avait été annulée le 18/11/2004 et le retranchement du 09/12/2002 annulé par le Conseil d\'État le 28/07/2004 (listes des fermetures et des déclassements de RFF reprises par lignes-oubliees)',
    ],
];
