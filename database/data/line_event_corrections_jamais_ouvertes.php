<?php

/**
 * Corrections SOURCÉES : lignes jamais ouvertes qui portaient des dates d'exploitation ESTIMÉES
 * (source « estimation-reseau », vff:import-secondary-network-dates, étape 6). Relecture du 19/11/2026,
 * rapport storage/app/reports/agents/soir/lignes-incoherences.md.
 *
 * L'estimation donne à une ligne secondaire sans date les années de début et de fin de son réseau.
 * Son garde-fou contre les lignes inachevées ne lit que le nom du tracé archeoferroviaire (« inachevé »,
 * « projet ») et la part du tracé en chantier dans OpenHistoricalMap : les deux lignes ci-dessous lui
 * ont échappé, et la carte les montrait en service en 1920 et en 1930 alors qu'aucun train commercial
 * n'y a circulé. Les deux estimations de chaque ligne sont neutralisées (type « other ») : la ligne n'a
 * plus de date d'exploitation, ce qui est exact.
 *
 * Mêmes champs et mêmes actions que database/data/line_event_corrections.php (lu avec lui par
 * vff:apply-event-corrections). Permalien Wikipédia (oldid), faits seulement, raison. Pas de tiret long.
 *
 * @return list<array<string, mixed>>
 */
$wp = fn (string $title, int $oldid) => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode(str_replace(' ', '_', $title)).'&oldid='.$oldid;

// Article du réseau d'où viennent les estimations (début 1914, fin 1935 : bornes de la seule ligne ouverte,
// Messimy - Saint-Symphorien-sur-Coise). Les autres lignes de la compagnie sont dites inachevées ou non construites.
$rhoneLoire = $wp('Chemins de fer départementaux de Rhône et Loire', 226987797);

return [

    // 3943 Ligne de Pontcharra à Sainte-Foy-l'Argentière (tracé archeoferroviaire, sans code) : ligne du premier
    // réseau des Chemins de fer départementaux de Rhône et Loire, inachevée (Première Guerre mondiale). Les
    // tronçons OpenHistoricalMap rattachés à la ligne sont « railway=construction », 1913-1920, note « jamais
    // terminé ». Ouverture 1914 et fermeture 1935 estimées : sans objet.
    [
        'line' => '3943',
        'action' => 'neutralize',
        'match' => ['source' => 'estimation-reseau', 'type' => 'opening', 'date' => '1914-01-01'],
        'label' => 'Estimation d\'après le réseau, sans objet : ligne inachevée, jamais ouverte',
        'source_url' => $rhoneLoire,
        'quote' => 'Pontcharra-sur-Turdine - Sainte-Foy-l\'Argentière (34 km), ligne inachevée',
        'reason' => 'ligne inachevée selon l\'article du réseau ; seule Messimy - Saint-Symphorien-sur-Coise a été mise en service, de 1914 à 1935',
    ],
    [
        'line' => '3943',
        'action' => 'neutralize',
        'match' => ['source' => 'estimation-reseau', 'type' => 'closure', 'date' => '1935-01-01'],
        'label' => 'Estimation d\'après le réseau, sans objet : ligne inachevée, jamais ouverte',
        'source_url' => $rhoneLoire,
        'quote' => 'Pontcharra-sur-Turdine - Sainte-Foy-l\'Argentière (34 km), ligne inachevée',
        'reason' => 'ligne jamais ouverte, donc jamais fermée ; 1935 est la fin de la ligne Messimy - Saint-Symphorien-sur-Coise',
    ],

    // 3637 Ligne de Feurs à Panissières (tracé archeoferroviaire, sans code) : la ligne complémentaire Panissières -
    // Feurs des Chemins de fer départementaux de Rhône et Loire n'a pas été construite ; le monorail Lartigue
    // construit en 1895 entre Feurs et Panissières (17 km) n'a jamais été mis en service
    // (https://fr.wikipedia.org/w/index.php?title=Monorail_syst%C3%A8me_Lartigue&oldid=241432275). Ouverture
    // 1914 et fermeture 1935 estimées : sans objet.
    [
        'line' => '3637',
        'action' => 'neutralize',
        'match' => ['source' => 'estimation-reseau', 'type' => 'opening', 'date' => '1914-01-01'],
        'label' => 'Estimation d\'après le réseau, sans objet : ligne jamais mise en service',
        'source_url' => $rhoneLoire,
        'quote' => 'Panissières - Feurs (17 km), ligne non construite',
        'reason' => 'ligne non construite par la compagnie selon l\'article du réseau ; le monorail Lartigue de 1895 entre Feurs et Panissières n\'a jamais été mis en service, selon l\'article « Monorail système Lartigue »',
    ],
    [
        'line' => '3637',
        'action' => 'neutralize',
        'match' => ['source' => 'estimation-reseau', 'type' => 'closure', 'date' => '1935-01-01'],
        'label' => 'Estimation d\'après le réseau, sans objet : ligne jamais mise en service',
        'source_url' => $rhoneLoire,
        'quote' => 'Panissières - Feurs (17 km), ligne non construite',
        'reason' => 'ligne jamais mise en service, donc jamais fermée ; 1935 est la fin de la ligne Messimy - Saint-Symphorien-sur-Coise',
    ],
];
