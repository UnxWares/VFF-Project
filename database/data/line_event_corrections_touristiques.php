<?php

/**
 * Sections exactes des exploitations touristiques (chantier « touristiques-suite », 23/11/2026).
 *
 * Les exploitations de vff:import-heritage-lines sont résolues sur les voies observées (BD TOPO, OpenStreetMap) quand
 * l'import ne donne pas de lieux : la section peut alors déborder sur une gare SNCF voisine (cas de Cernay,
 * line_event_corrections_cernay.php), s'arrêter avant un terminus ou rester non résolue. Chaque correction pose ici les
 * lieux de départ et d'arrivée que donne l'article Wikipédia de l'exploitation (version citée, citation relevée par
 * lecture automatique de l'introduction de l'article).
 *
 * Format : voir line_event_corrections.php (action relocate). Pas de tiret long dans les textes.
 *
 * @return list<array<string, mixed>>
 */
$wp = fn (string $title, int $oldid): string => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode(str_replace(' ', '_', $title)).'&oldid='.$oldid;

return [

    // 360000 Les Ifs - Étretat : le train touristique roule des Loges à Étretat (5,2 km) ; la section résolue sur les
    // voies observées faisait 6,2 km.
    [
        'line' => '360000',
        'action' => 'relocate',
        'match' => ['source' => 'heritage', 'type' => 'heritage_start', 'date' => '1990-01-01'],
        'from' => 'Les Loges-Vaucottes-sur-Mer',
        'to' => 'Étretat',
        'source_url' => $wp('Train touristique Étretat-Pays de Caux', 231443438),
        'quote' => 'utilise, entre la gare des Loges et la gare d\'Étretat, un tronçon, 5,2 km en voie normale',
        'reason' => 'section du train touristique Étretat-Pays de Caux : de la gare des Loges à la gare d\'Étretat, 5,2 km',
    ],

    // 719000 Martel : le Truffadou circule sur la partie subsistante de la ligne entre Martel et Saint-Denis-près-Martel ;
    // la portion n'était pas résolue.
    [
        'line' => '719000',
        'action' => 'relocate',
        'match' => ['source' => 'heritage', 'type' => 'heritage_start', 'date' => '1992-01-01'],
        'from' => 'Martel',
        'to' => 'Saint-Denis-près-Martel',
        'source_url' => $wp('Chemin de fer touristique du Haut Quercy', 241159830),
        'quote' => 'qui circule sur une partie subsistante de la ligne qui reliait autrefois Martel à Saint-Denis-près-Martel dans le département du Lot',
        'reason' => 'section du chemin de fer touristique du Haut Quercy (le Truffadou) : de Martel à Saint-Denis-près-Martel',
    ],

    // 600000 Train du Bas-Berry : la section va d'Argy à Valençay (l'import la nommait jusqu'à Luçay-le-Mâle).
    [
        'line' => '600000',
        'action' => 'relocate',
        'match' => ['source' => 'heritage', 'type' => 'heritage_start', 'date' => '1995-01-01', 'from' => 'Argy'],
        'from' => 'Argy',
        'to' => 'Valençay',
        'source_url' => $wp('Train du Bas-Berry', 236092659),
        'quote' => 'qui fait circuler des trains historiques sur la ligne à voie métrique entre Argy et Valençay',
        'reason' => 'section du Train du Bas-Berry : d\'Argy à Valençay (voie métrique)',
    ],

    // 749000 Train touristique de Puisaye-Forterre : Toucy-Ville - Villiers-Saint-Benoît et Toucy-Ville - Toucy-Moulins
    // (l'import allait jusqu'à Fontenoy).
    [
        'line' => '749000',
        'action' => 'relocate',
        'match' => ['source' => 'heritage', 'type' => 'heritage_start', 'date' => '1985-01-01', 'from' => 'Villiers-Saint-Benoît'],
        'from' => 'Villiers-Saint-Benoît',
        'to' => 'Toucy-Moulins',
        'source_url' => $wp('Train touristique de Puisaye-Forterre', 238812228),
        'quote' => 'Il relie Toucy-Ville à Villiers-Saint-Benoît et Toucy-Ville à Toucy-Moulins.',
        'reason' => 'sections du train touristique de Puisaye-Forterre : Villiers-Saint-Benoît - Toucy-Ville - Toucy-Moulins, sans Fontenoy',
    ],

    // 766000 Chemin de fer de la vallée de l'Ouche : de Bligny-sur-Ouche à Pont-d'Ouche (environ 7 km, voie de 600 mm).
    [
        'line' => '766000',
        'action' => 'relocate',
        'match' => ['source' => 'heritage', 'type' => 'heritage_start', 'date' => '1978-01-01'],
        'from' => 'Bligny-sur-Ouche',
        'to' => 'Pont d\'Ouche',
        'source_url' => $wp('Chemin de fer de la vallée de l\'Ouche', 241308920),
        'quote' => 'La ligne, d’environ 7 km de long, relie Bligny-sur-Ouche à Pont-d\'Ouche avec une voie étroite',
        'reason' => 'section du chemin de fer de la vallée de l\'Ouche : de Bligny-sur-Ouche à Pont-d\'Ouche, environ 7 km',
    ],

    // 676000 Train du Pays Cathare et du Fenouillèdes : de Rivesaltes à Saint-Martin-Lys (62 km).
    [
        'line' => '676000',
        'action' => 'relocate',
        'match' => ['source' => 'heritage', 'type' => 'heritage_start', 'date' => '1992-01-01'],
        'from' => 'Rivesaltes',
        'to' => 'Saint-Martin-Lys',
        'source_url' => $wp('Train du pays Cathare et du Fenouillèdes', 238419863),
        'quote' => 'la ligne Rivesaltes - Carcassonne, qui va de Rivesaltes, au bord de la mer Méditerranée, dans le département des Pyrénées-Orientales, à St Martin Lys dans le Piémont pyrénéen, dans l\'Aude',
        'reason' => 'section du Train du Pays Cathare et du Fenouillèdes : de Rivesaltes à Saint-Martin-Lys, 62 km',
    ],

];
