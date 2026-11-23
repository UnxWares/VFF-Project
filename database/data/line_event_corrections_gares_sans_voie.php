<?php

/**
 * Corrections sourcées d'événements de lignes relevées par l'invariant stations.active_without_track
 * (chantier « gares-sans-voie », 23/11/2026, rapport storage/app/reports/agents/aprem-2111/gares-sans-voie.md) :
 * ligne dont le tracé existe près d'une gare mais sans aucun événement daté, donc absente de toutes les ères
 * historiques. Format : voir line_event_corrections.php.
 *
 * Pas de tiret long dans les textes.
 *
 * @return list<array<string, mixed>>
 */
$wp = fn (string $title, int $oldid) => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode(str_replace(' ', '_', $title)).'&oldid='.$oldid;

return [
    // 312311 Raccordement de Boulogne-Maritime : tracé à 91 m de la gare de Boulogne-Maritime (SNCF) et 953 m déposés (BD TOPO),
    // mais aucun événement : la gare (1875 à 1995 pour le service ferroviaire) flottait sans voie aux ères 1900 à 1950.
    [
        'line' => '312311',
        'action' => 'add',
        'type' => 'opening',
        'date' => '1875-01-01',
        'precision' => 'year',
        'from' => 'Boulogne-Ville',
        'to' => 'Boulogne-Maritime',
        'source_url' => $wp('Gare de Boulogne-Maritime', 237856348),
        'quote' => 'Une première gare maritime est créée en 1875 sur le site de l\'Éperon, directement reliée à la gare de Boulogne-Ville',
        'reason' => 'la gare maritime de 1875 est directement reliée à Boulogne-Ville : mise en service du raccordement avec elle',
    ],
    [
        'line' => '312311',
        'action' => 'add',
        'type' => 'closure',
        'date' => '1995-01-21',
        'precision' => 'day',
        'from' => 'Boulogne-Ville',
        'to' => 'Boulogne-Maritime',
        'source_url' => $wp('Gare de Boulogne-Maritime', 237856348),
        'quote' => 'L\'ouverture du tunnel sous la Manche et du service Eurostar en 1994 provoque la fermeture du service ferroviaire de la gare maritime le 21 janvier 1995. Les voies reliant la gare maritime à la gare de Boulogne-Ville sont démontées dans les années qui suivent.',
        'reason' => 'fin du service ferroviaire de la gare maritime le 21 janvier 1995 ; les voies de liaison sont ensuite démontées (date non donnée)',
    ],
];
