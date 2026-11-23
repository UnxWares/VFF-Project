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
    ['station' => 'croix-lallumette', 'line' => 'tramway-de-roubaix-a-herseaux-elrt-b', 'reason' => $croixReason, 'source_url' => $croix, 'reviewed_at' => $day],
    ['station' => 'croix-lallumette', 'line' => 'tramway-de-roubaix-a-toufflers-elrt-c', 'reason' => $croixReason, 'source_url' => $croix, 'reviewed_at' => $day],
    ['station' => 'croix-lallumette', 'line' => 'tramway-de-tourcoing-grand-place-a-roubaix-grand-place-elrt-e', 'reason' => $croixReason, 'source_url' => $croix, 'reviewed_at' => $day],
    ['station' => 'croix-lallumette', 'line' => 'tramway-de-roubaix-le-cretinier-a-roubaix-place-du-travail-elrt-g', 'reason' => $croixReason, 'source_url' => $croix, 'reviewed_at' => $day],
    ['station' => 'croix-lallumette', 'line' => 'tramway-de-roubaix-gare-a-roubaix-gare-du-pile-elrt-h', 'reason' => $croixReason, 'source_url' => $croix, 'reviewed_at' => $day],

    // Relecture du 22/11/2026 des 42 terminus écartés (gares-42.md) : la source place l'extrémité de la ligne ailleurs.
    ['station' => 'le-touquet-aeroport', 'line' => 'ligne-detaples-au-touquet', 'reason' => 'tramway d\'Étaples à Paris-Plage : terminus à Paris-Plage, la gare de l\'aéroport est au milieu du tracé, sans arrêt cité',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode("Tramway_d'Étaples_à_Paris-Plage").'&oldid=236094248', 'reviewed_at' => $day],
    ['station' => 'aix-en-provence', 'line' => 'ligne-daix-a-marseille', 'reason' => 'tramway Marseille - Aix de 1903, tracé distinct de la ligne PLM ; Wikidata place la gare d\'Aix sur la ligne de Rognac à Aix-en-Provence et sur Lyon-Perrache - Marseille, pas sur ce tramway',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode('Ancien_tramway_de_Marseille').'&oldid=239738009', 'reviewed_at' => $day],
    ['station' => 'excideuil-dordogne', 'line' => 'ligne-dexcideuil-a-saint-yrieix', 'reason' => 'ligne à voie métrique du réseau départemental, à Excideuil dès 1887 ; la gare de 1898 est celle d\'une autre ligne',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode('Ligne_de_Périgueux_à_Saint-Yrieix').'&oldid=238652133', 'reviewed_at' => $day],
    ['station' => 'boulogne-maritime', 'line' => 'ligne-de-boulogne-au-portel', 'reason' => 'tramway dont le terminus boulonnais est la place Dalton, devant la gare du quai Chanzy, pas la gare Maritime',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode('Ligne_de_tramway_de_Boulogne-sur-Mer_au_Portel').'&oldid=238874156', 'reviewed_at' => $day],
    ['station' => 'bourg-en-bresse', 'line' => 'ligne-de-bourg-a-villefranche', 'reason' => 'tramway de l\'Ain dont le terminus est une gare propre au tramway, à l\'est-sud-est de Bourg',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode("Tramways_de_l'Ain").'&oldid=241068902', 'reviewed_at' => $day],
    ['station' => 'marseille-st-charles', 'line' => 'ligne-de-marseille-a-allauch', 'reason' => 'tramway de la Croix-Rouge à Allauch (1908), sans lien avec la gare Saint-Charles',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode('Ancien_tramway_de_Marseille').'&oldid=239738009', 'reviewed_at' => $day],
    ['station' => 'montauban-ville-bourbon', 'line' => 'ligne-de-montauban-a-monclar', 'reason' => 'gare de tête des tramways de Tarn-et-Garonne distincte de la gare de Ville-Bourbon, « à proximité de la gare du Midi »',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode('Tramways_de_Tarn-et-Garonne').'&oldid=237184582', 'reviewed_at' => $day],
    ['station' => 'st-malo', 'line' => 'ligne-de-saint-malo-a-cancale-la-houle', 'reason' => 'ligne partant de Paramé, pas de la gare de Saint-Malo',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode('Tramways_bretons').'&oldid=227452674', 'reviewed_at' => $day],
    ['station' => 'strasbourg-neudorf', 'line' => 'ligne-de-strasbourg-a-marckolsheim', 'reason' => 'départ de la gare locale près de la porte de l\'Hôpital, non de Neudorf',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode('Ancien_tramway_de_Strasbourg').'&oldid=239874624', 'reviewed_at' => $day],
    ['station' => 'toulouse-matabiau', 'line' => 'ligne-de-toulouse-a-villemur', 'reason' => 'chemin de fer du Sud-Ouest partant de Toulouse-Bonnefoy, pas de Matabiau',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode('Chemins_de_fer_du_Sud-Ouest').'&oldid=241040330', 'reviewed_at' => $day],
    ['station' => 'lyon-part-dieu', 'line' => 'ligne-de-lyon-a-la-balme', 'reason' => 'gare ouverte en 1983, la ligne du chemin de fer de l\'Est de Lyon partait de la gare de Lyon-Est (1881-1950) ; Wikidata place Part-Dieu sur d\'autres lignes',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode("Chemin_de_fer_de_l'Est_de_Lyon").'&oldid=240183241', 'reviewed_at' => $day],
    ['station' => 'pontcharra-st-forgeux', 'line' => 'ligne-de-pontcharra-a-sainte-foy-largentiere', 'reason' => 'ligne inachevée des chemins de fer départementaux de Rhône et Loire ; la gare actuelle est de 2004',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode('Chemins_de_fer_départementaux_de_Rhône_et_Loire').'&oldid=226987797', 'reviewed_at' => $day],
    ['station' => 'marcillac', 'line' => 'ligne-de-decazeville-a-marcillac', 'reason' => 'voie étroite de 66 cm des mines de fer de Cabrol (1852-1856) ; Wikidata place la gare de Marcillac sur la ligne de Capdenac à Rodez seulement',
        'source_url' => 'https://fr.wikipedia.org/wiki/Decazeville', 'reviewed_at' => $day],

    // Relecture de l'invariant stations.opened_before_lines (chantier « invariants-gares-nuit », 23/11/2026, rapport
    // storage/app/reports/agents/aprem-2111/invariants-gares-nuit.md) : gares d'autres réseaux ou de lignes sans code,
    // rattachées par projection de segment à la ligne nationale voisine.
    // Gares des chemins de fer départementaux ou de tramways nées d'objets OpenHistoricalMap homonymes des gares du réseau
    // national (leurs objets sont déjà écartés des gares SNCF par la section 10 a de gares_corrections.php).
    ['station' => 'nogent-sur-seine-cfd', 'line' => 'rfn-1000', 'reason' => 'objet OpenHistoricalMap « Nogent sur Seine (CFD) » (1925 à 1939) : gare des chemins de fer départementaux, pas de la ligne de Paris-Est à Mulhouse-Ville',
        'source_url' => 'https://www.openhistoricalmap.org/node/2181732475', 'reviewed_at' => '2026-11-23'],
    ['station' => 'souppes-cfd', 'line' => 'rfn-750000', 'reason' => 'objet OpenHistoricalMap « Souppes (CFD) » (1888 à 1959) : gare des chemins de fer départementaux, pas de la ligne du Bourbonnais',
        'source_url' => 'https://www.openhistoricalmap.org/node/2181780573', 'reviewed_at' => '2026-11-23'],
    ['station' => 'morez-plm', 'line' => 'rfn-878000', 'reason' => 'objet OpenHistoricalMap « Morez PLM » (exploitant NStCM, 1921 à 1958) : terminus du Nyon - Saint-Cergue - Morez, pas de la ligne d\'Andelot à La Cluse',
        'source_url' => 'https://www.openhistoricalmap.org/node/2087817589', 'reviewed_at' => '2026-11-23'],
    ['station' => 'lavaur-81', 'line' => 'rfn-738000', 'reason' => 'objet OpenHistoricalMap « Lavaur » (exploitant TVT, voie de 60 cm, 1903 à 1937) : gare des tramways à vapeur du Tarn, pas de la ligne SNCF',
        'source_url' => 'https://www.openhistoricalmap.org/node/2134788821', 'reviewed_at' => '2026-11-23'],
    // Halte du Fourvière Ouest-Lyonnais (Lyon-Saint-Just - Vaugneray, 19 avril 1886), voie métrique, rattachée par projection à la
    // ligne de Paray-le-Monial à Givors-Canal qui passe à proximité.
    ['station' => 'etoile-dalai', 'line' => 'rfn-775000', 'reason' => 'halte du Fourvière Ouest-Lyonnais (ligne de Lyon-Saint-Just à Vaugneray, ouverte le 19 avril 1886), pas de la ligne de Paray-le-Monial à Givors-Canal',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode('Fourvière_Ouest-Lyonnais').'&oldid=239282879', 'reviewed_at' => '2026-11-23'],

    // Liste des gares de SNCF Open Data : deux enregistrements portent l'UIC 87783621 « Badaroux », l'un sur la ligne 723000
    // (Lozère), l'autre sur la ligne 020000 (PK 217+109, commune de Haussignemont, Marne). Ce second enregistrement est celui de
    // Blesme-Haussignemont (UIC 87174326, mêmes PK et coordonnées, lignes 020000 et 070000) dont le libellé et l'UIC ont été
    // recopiés par erreur ; la fiche de Badaroux se retrouvait à 237 km de sa ligne, au milieu des deux points.
    ['station' => 'badaroux', 'line' => 'rfn-020000', 'reason' => 'gare de la ligne de Mende à La Bastide (723000, Lozère) ; l\'enregistrement « Badaroux » de la ligne 020000 (PK 217+109, Haussignemont, Marne) est celui de Blesme-Haussignemont (UIC 87174326) dont le libellé et l\'UIC sont faux dans la liste des gares SNCF',
        'source_url' => 'https://ressources.data.sncf.com/explore/dataset/liste-des-gares/table/?q=87174326', 'reviewed_at' => '2026-11-23'],

    // Gare de départ du Train des Mouettes (chemin de fer touristique de Saujon à La Tremblade), à côté de la gare SNCF de Saujon : point
    // touristique rattaché à la ligne de Saintes à Royan par la seule géométrie (méthode segment). Décision du 23/11/2026 ; le lien avec
    // la ligne de Saujon à La Grève (545000) reste.
    ['station' => 'le-train-des-mouettes-saujon', 'line' => 'rfn-544000', 'reason' => 'gare de départ du chemin de fer touristique du Train des Mouettes (ligne de Saujon à La Grève), à proximité de la gare de Saujon sur la ligne de Saintes à Royan, qui ne la dessert pas',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Gare_de_Saujon&oldid=222377940', 'reviewed_at' => '2026-11-23'],
];
