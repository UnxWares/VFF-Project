<?php

/**
 * Liens gare - ligne faux, écartés à la main, lus par vff:attach-stations (App\Services\StationLineExclusions).
 * Voir la classe pour la forme d'une entrée et la portée (toutes méthodes de rattachement, trois modes de la commande).
 *
 * Relecture du 22/11/2026 des rattachements faibles (méthode trace, segment, junction, terminus_name) à plus de 150 m
 * du tracé de la ligne : rapports storage/app/reports/agents/aprem-2111/rattachements-lointains.md et
 * gares-hors-trace-2.md, décisions et sources dans exclusions-gare-ligne.md. Seuls les couples dont l'article de la
 * gare place la gare sur d'autres lignes, sans la ligne exclue, sont écrits ici ; les cas douteux restent rattachés.
 *
 * @return list<array{station: string, line: string, reason: string, source_url: string, reviewed_at: string}>
 */
$wp = fn (string $title) => 'https://fr.wikipedia.org/wiki/'.rawurlencode($title);
$day = '2026-11-22';

$croix = $wp('Gare de Croix-L\'Allumette');
$croixReason = 'gare de la ligne de Fives à Mouscron (frontière) ; aucune desserte par ce tramway de Roubaix-Tourcoing dans l\'article de la gare';

return [
    // Halte du Transilien R, voisine de Fontainebleau-Avon sur la ligne de Paris-Lyon à Marseille-Saint-Charles ;
    // la ligne 750000 commence plus au sud, à Moret-Veneux-les-Sablons (lien « trace » d'une trace mal affectée).
    ['station' => 'fontainebleau-foret', 'line' => 'rfn-750000', 'reason' => 'halte de la ligne de Paris-Lyon à Marseille-Saint-Charles, au nord de Moret-Veneux-les-Sablons où commence la ligne de Moret à Lyon-Perrache',
        'source_url' => $wp('Gare de Fontainebleau-Avon'), 'reviewed_at' => $day],

    // Gare du RER A (branche A4) ; la grande ceinture ne la dessert pas.
    ['station' => 'bry-sur-marne-94', 'line' => 'rfn-990000', 'reason' => 'gare du RER A, non desservie par la grande ceinture de Paris',
        'source_url' => $wp('Gare de Bry-sur-Marne'), 'reviewed_at' => $day],

    // Gare de la ligne des Invalides à Versailles-Rive-Gauche, entre Viroflay-Rive-Gauche et Versailles-Chantiers ; aucun raccordement de Viroflay cité.
    ['station' => 'porchefontaine', 'line' => 'rfn-978300', 'reason' => 'gare de la ligne des Invalides à Versailles-Rive-Gauche, pas du raccordement de Viroflay',
        'source_url' => $wp('Gare de Porchefontaine'), 'reviewed_at' => $day],

    // Gare de la ligne de La Plaine à Hirson et Anor ; la voie-mère du Bourget (Garonor) est une voie de service sans gare.
    ['station' => 'le-blanc-mesnil', 'line' => 'rfn-229606', 'reason' => 'gare de la ligne de La Plaine à Hirson et Anor, pas de la voie-mère du Bourget (Garonor)',
        'source_url' => $wp('Gare du Blanc-Mesnil'), 'reviewed_at' => $day],

    // Gare du TER sur la ligne de Paris-Lyon à Marseille ; le chemin de fer touristique d'Anse est « à proximité ».
    ['station' => 'anse', 'line' => 'hors-rfn-chemin-de-fer-touristique-anse', 'reason' => 'gare de la ligne de Paris-Lyon à Marseille-Saint-Charles ; le chemin de fer touristique d\'Anse est un autre réseau, à proximité',
        'source_url' => $wp('Gare d\'Anse'), 'reviewed_at' => $day],

    // Gare de la ligne de Sceaux (RER B), jamais desservie par la petite ceinture (correspondance seulement).
    ['station' => 'cite-universitaire', 'line' => 'rfn-955000', 'reason' => 'gare de la ligne de Sceaux (RER B), en correspondance avec la petite ceinture sans y être desservie',
        'source_url' => $wp('Gare de Cité universitaire'), 'reviewed_at' => $day],

    // Gare de la ligne de Fives à Mouscron (frontière) : cinq lignes de tramway de Roubaix et Tourcoing rattachées par proximité.
    ['station' => 'croix-lallumette', 'line' => 'archeo-tramway-de-roubaix-a-herseaux-elrt-b', 'reason' => $croixReason, 'source_url' => $croix, 'reviewed_at' => $day],
    ['station' => 'croix-lallumette', 'line' => 'archeo-tramway-de-roubaix-a-toufflers-elrt-c', 'reason' => $croixReason, 'source_url' => $croix, 'reviewed_at' => $day],
    ['station' => 'croix-lallumette', 'line' => 'archeo-tramway-de-tourcoing-grand-place-a-roubaix-grand-place-elrt-e', 'reason' => $croixReason, 'source_url' => $croix, 'reviewed_at' => $day],
    ['station' => 'croix-lallumette', 'line' => 'archeo-tramway-de-roubaix-le-cretinier-a-roubaix-place-du-travail-elrt-g', 'reason' => $croixReason, 'source_url' => $croix, 'reviewed_at' => $day],
    ['station' => 'croix-lallumette', 'line' => 'archeo-tramway-de-roubaix-gare-a-roubaix-gare-du-pile-elrt-h', 'reason' => $croixReason, 'source_url' => $croix, 'reviewed_at' => $day],
];
