<?php

/**
 * Corrections sourcées d'événements de lignes relevées par l'invariant stations.active_without_track,
 * famille « tracé de l'ère arrêté avant la gare ou passant à côté » (chantier « traces-tronques », 23/11/2026,
 * rapport storage/app/reports/agents/aprem-2111/traces-tronques.md). La gare est juste, la voie existe sur
 * l'orthophoto et dans le tracé de référence : c'est la portion datée de l'ère qui est mal découpée.
 * Format : voir line_event_corrections.php.
 *
 * Pas de tiret long dans les textes.
 *
 * @return list<array<string, mixed>>
 */
$wp = fn (string $title, int $oldid) => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode(str_replace(' ', '_', $title)).'&oldid='.$oldid;

return [
    // 500000 Chartres - Bordeaux, section de Cavignac à Bordeaux : aucune ouverture datée. Les 11 km de Gauriaguet à
    // La Grave-d'Ambarès prenaient par voisinage l'ouverture de 2017 (ci-dessous) et n'apparaissaient qu'à l'ère 2020 ;
    // la halte de La Grave-d'Ambarès flottait sans voie de 1880 à 2010 (14 ères).
    [
        'line' => '500000',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1886-01-01',
        'precision' => 'year',
        'from' => 'Cavignac',
        'to' => 'Cenon',
        'source_url' => $wp('Gare de Saint-Mariens - Saint-Yzan', 239281710),
        'quote' => 'La connexion directe avec la capitale régionale est devenue possible en 1886 lors de la construction d\'un pont franchissant la Dordogne sur la commune de Cubzac-les-Ponts.',
        'reason' => 'section de Cavignac à Bordeaux (déclarée d\'utilité publique le 3 août 1881, article de la ligne oldid 241089765) ouverte avec le pont de Cubzac en 1886 ; avant, les trains de la Compagnie des Charentes gagnaient Bordeaux par Cavignac et Coutras (ouverture du 19 octobre 1874, même article) ; elle rejoint la ligne de Paris à Bordeaux à la bifurcation de Cenon',
    ],
    // 500000 : la phrase de 2017 ne parle que du prolongement de la voie de délestage de Cenon jusqu'à Bordeaux-Saint-Jean.
    // Résolue d'Ambarès-et-Lagrave au bout du tracé, elle couvrait La Grave - Cenon (en service bien avant 1978, même
    // article) et datait par voisinage tout le tronçon jusqu'à Gauriaguet.
    [
        'line' => '500000',
        'action' => 'relocate',
        'match' => ['source' => 'wikipedia', 'type' => 'opening', 'date' => '2017-01-01'],
        'from' => 'Cenon',
        'to' => 'Bordeaux-Saint-Jean',
        'source_url' => $wp('Ligne de Chartres à Bordeaux-Saint-Jean', 241089765),
        'quote' => 'La section de Cenon à Ambarès-et-Lagrave est réhabilitée et électrifiée en 1 500 V avant 1978 [...]. Utilisée depuis en délestage, cette section est prolongée, en 2017, jusqu\'à Bordeaux-Saint-Jean avec suppression de la bifurcation de Saintes située à Cenon.',
        'reason' => 'seul le prolongement de Cenon à Bordeaux-Saint-Jean est de 2017 ; la section d\'Ambarès-et-Lagrave à Cenon existait déjà',
    ],
    // 253000 Valenciennes-Faubourg-de-Paris - Hautmont : l'ouverture OpenHistoricalMap de 1883 (Valenciennes-Faubourg -
    // Douzies, 32,8 km) battait, comme ouverture localisée, l'ouverture de Wikidata du 9 février 1880 : la gare de Douzies
    // flottait sans voie à l'ère 1880. 1883 est l'année de la reprise de la ligne par la Compagnie du Nord, pas une ouverture.
    // À relire : l'article ne date pas l'ouverture (Wikidata la tient de Wikipédia en néerlandais).
    [
        'line' => '253000',
        'action' => 'neutralize',
        'match' => ['source' => 'ohm', 'type' => 'opening', 'date' => '1883-01-01'],
        'label' => 'Reprise de la ligne par la Compagnie du Nord',
        'source_url' => $wp('Ligne de Valenciennes-Faubourg-de-Paris à Hautmont', 240564961),
        'quote' => 'La ligne est reprise définitivement par la Compagnie des chemins de fer du Nord selon les termes d\'une convention signée entre le ministre des Travaux publics et la compagnie le 5 juin 1883.',
        'reason' => 'date de la convention de reprise par le Nord ; la ligne était exploitée avant (traité d\'exploitation avec le Nord approuvé le 20 mai 1876, même article ; ouverture du 9 février 1880 chez Wikidata)',
    ],
];
