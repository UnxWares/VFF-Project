<?php

/*
 * Itinéraires de Wikipédia qui ne sont pas des lignes de VFF : un article comme « Ligne de Paris à
 * Granville » décrit une relation qui enchaîne plusieurs lignes du réseau. Quand l'article d'une série
 * renvoie vers lui (« [[Ligne de Paris à Granville|Paris - Granville]] »), l'import du matériel
 * (MaterialRelations) rattache la série à chacune des lignes listées ici, avec une confiance moyenne.
 *
 * Clés : titre de l'article Wikipédia (première lettre en capitale, espaces).
 *   - `stops` : gares de passage dans l'ordre. Les arrêts intermédiaires servent de garde-fou : une phrase
 *     qui nomme l'un d'eux (« axe régional sur la ligne de Paris à Granville avec la gare de Dreux ») ne
 *     parle que d'une partie de l'itinéraire, et rien n'est rattaché ;
 *   - `lines` : codes RFN des lignes de VFF empruntées, dans l'ordre ;
 *   - `source` : article qui décrit l'itinéraire ;
 *   - `checked` : comment la suite de lignes a été vérifiée.
 *
 * Itinéraires trouvés dans les articles de séries le 20/11/2026 et laissés de côté : « Ligne du Sillon
 * Alpin » et « Ligne des Alpes » (étendue variable selon les sources, et citées seulement pour des projets
 * ou avec un seul terminus), « Ligne de Perpignan à Figueras » (citée pour une impossibilité technique),
 * « Ligne du Luxembourg » (Belgique), lignes d'Algérie et du Tonkin.
 */
return [
    'Ligne de Paris à Granville' => [
        'stops' => ['Paris-Montparnasse', 'Versailles-Chantiers', 'Dreux', 'Surdon', 'Argentan', 'Flers', 'Vire', 'Folligny', 'Granville'],
        'lines' => ['420000', '395000', '430000', '405000'],
        'source' => 'https://fr.wikipedia.org/wiki/Ligne_de_Paris_%C3%A0_Granville',
        'checked' => '20/11/2026 : plus court chemin de Paris-Montparnasse à Granville sur le graphe de l\'époque actuelle (station_edges), par Dreux, Surdon, Argentan et Folligny, sans autre itinéraire à moins de 15 %. 21/11/2026 : raccordement de Versailles-Matelots (990306) retiré ; l\'article (révision 239953957) décrit la section de Paris-Montparnasse à Saint-Cyr de la ligne 420000 puis la ligne de Saint-Cyr à Surdon, sans raccordement ; les 148 m de 990306 lus dans le graphe sont des arêtes de la gare de Versailles-Chantiers partagées avec la ligne 420000.',
    ],
    'Ligne de Caen à Rennes' => [
        'stops' => ['Caen', 'Lison', 'Saint-Lô', 'Coutances', 'Folligny', 'Pontorson', 'Dol-de-Bretagne', 'Rennes'],
        'lines' => ['366000', '415000', '441000', '420000'],
        'source' => 'https://fr.wikipedia.org/wiki/Ligne_de_Caen_%C3%A0_Rennes',
        'checked' => '20/11/2026 : plus courts chemins Caen - Coutances, Coutances - Folligny, Folligny - Dol-de-Bretagne et Dol-de-Bretagne - Rennes sur le graphe de l\'époque actuelle, chacun sans autre itinéraire à moins de 15 %.',
    ],
];
