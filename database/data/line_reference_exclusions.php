<?php

/**
 * Retraits (et voies partagées désignées) faits à la main dans les tracés de référence, lus par vff:build-line-references à chaque reconstruction
 * (App\Services\LineReferenceExclusions : forme des entrées, portée, garde-fous). Un recalcul du moteur ne fait pas
 * revenir ce qui est retiré ici.
 *
 * - kind « trace » : la voie source (source_name, source_ref) n'entre pas dans le tracé de la ligne, en entier, ou
 *   seulement sa portion à moins de radius_m mètres du point near [lon, lat] ;
 * - kind « fragment » : la polyligne du tracé de la ligne qui passe à moins de 5 m du point at [lon, lat] et mesure
 *   au plus max_m mètres est retirée (position plutôt qu'identifiant : les ids de line_reference_parts changent à
 *   chaque calcul) ;
 * - kind « shared » : la voie source archeo ou OHM (source_name, source_ref) d'une section que la ligne parcourt sur la
 *   voie de la ligne `over` compte comme rattachée en second : le trou est comblé par le dessin de `over` ; avec `near`
 *   et `radius_m` (5 km au plus), seule sa portion dans ce disque compte.
 *
 * @return list<array<string, mixed>>
 */
$fragments = 'storage/app/reports/agents/aprem-2111/fragments-60m.md (relecture sur orthophoto IGN, planche ';
$day = '2026-11-23';
$osm = 'https://www.openstreetmap.org/way/';
$dom = 'storage/app/reports/agents/aprem-2111/domfront.md';

return [
    // Domfront (Orne), gare : deux voies OSM abandonnées parallèles à 10-12 m (way 230093155, voie est, 715 m ; way
    // 1075679256, voie ouest, 106 m) finissent chacune par le début d'un aiguillage vers l'autre sans le joindre (6 et
    // 4 m). La ligne de La Chapelle-Anthenaise à Flers, qui passe de l'une à l'autre, y était dessinée en deux crochets
    // tête-bêche avec un doublon. Les deux bouts sont retirés de son tracé (rayons choisis pour laisser 75 m entre la
    // voie est et la voie ouest) : le moteur refait le changement de voie par une courbe estimée de rayon plausible le
    // long des deux voies (« raccord-lisse »), sans crochet ni doublon. Mesure : rapport exclusions-traces.md.
    ['kind' => 'trace', 'line' => '436000', 'source_name' => 'osm', 'source_ref' => '230093155', 'near' => [-0.667405, 48.586495], 'radius_m' => 59,
        'reason' => 'gare de Domfront : bout sud de la voie est, le long de la voie ouest puis en courbe d\'aiguillage arrêtée à 6 m d\'elle (doublon et crochet)',
        'source' => $osm.'230093155', 'reviewed_at' => $day],
    ['kind' => 'trace', 'line' => '436000', 'source_name' => 'osm', 'source_ref' => '1075679256', 'near' => [-0.667251, 48.586676], 'radius_m' => 40,
        'reason' => 'gare de Domfront : bout nord de la voie ouest, en courbe d\'aiguillage arrêtée à 4 m de la voie est puis le long d\'elle (crochet et doublon)',
        'source' => $osm.'1075679256', 'reviewed_at' => $day],

    // Les lignes d'Alençon à Domfront (432000) et de Domfront à Pontaubault (437000) roulent dans la gare sur les mêmes
    // voies que 436000 et y changent de voie au même endroit. Sans les mêmes coupes, elles gardaient les deux crochets,
    // reliés par un raccord droit de 7 à 10 m à côté de la courbe de 436000 : 7 jonctions à 0 m perdues (23/11/2026,
    // exclusions-traces/application/jonctions-perdues.csv). Mêmes coupes des deux voies OSM, et leurs traces archeo et
    // OHM (cordes droites à travers la gare) retirées à moins de 70 m du changement de voie : le trou est comblé par le
    // dessin de 436000, au bit près (JunctionRepair, règle 2 bis). Mesure : rapport domfront.md.
    ['kind' => 'trace', 'line' => '432000', 'source_name' => 'osm', 'source_ref' => '230093155', 'near' => [-0.667405, 48.586495], 'radius_m' => 59,
        'reason' => 'gare de Domfront : bout sud de la voie est (crochet), comme pour 436000 dont la ligne partage les voies',
        'source' => $osm.'230093155', 'reviewed_at' => $day],
    ['kind' => 'trace', 'line' => '432000', 'source_name' => 'osm', 'source_ref' => '1075679256', 'near' => [-0.667251, 48.586676], 'radius_m' => 40,
        'reason' => 'gare de Domfront : bout nord de la voie ouest (crochet), comme pour 436000 dont la ligne partage les voies',
        'source' => $osm.'1075679256', 'reviewed_at' => $day],
    ['kind' => 'trace', 'line' => '432000', 'source_name' => 'archeoferroviaire', 'source_ref' => '579727eb1fd0a2ef1361d16c7fbd574b', 'near' => [-0.6672706, 48.5866638], 'radius_m' => 70,
        'reason' => 'gare de Domfront : corde droite du tracé archeo à travers le changement de voie, remplacée par le dessin de 436000',
        'source' => $dom, 'reviewed_at' => $day],
    ['kind' => 'trace', 'line' => '432000', 'source_name' => 'ohm', 'source_ref' => '201186367', 'near' => [-0.6672706, 48.5866638], 'radius_m' => 70,
        'reason' => 'gare de Domfront : corde droite du tracé OHM à travers le changement de voie, remplacée par le dessin de 436000',
        'source' => 'https://www.openhistoricalmap.org/way/201186367', 'reviewed_at' => $day],
    ['kind' => 'trace', 'line' => '437000', 'source_name' => 'osm', 'source_ref' => '230093155', 'near' => [-0.667405, 48.586495], 'radius_m' => 59,
        'reason' => 'gare de Domfront : bout sud de la voie est (crochet), comme pour 436000 dont la ligne partage les voies',
        'source' => $osm.'230093155', 'reviewed_at' => $day],
    ['kind' => 'trace', 'line' => '437000', 'source_name' => 'osm', 'source_ref' => '1075679256', 'near' => [-0.667251, 48.586676], 'radius_m' => 40,
        'reason' => 'gare de Domfront : bout nord de la voie ouest (crochet), comme pour 436000 dont la ligne partage les voies',
        'source' => $osm.'1075679256', 'reviewed_at' => $day],
    ['kind' => 'trace', 'line' => '437000', 'source_name' => 'archeoferroviaire', 'source_ref' => '4d518eaae7acda903aaa97ef1cc2a6b8', 'near' => [-0.6672706, 48.5866638], 'radius_m' => 70,
        'reason' => 'gare de Domfront : corde droite du tracé archeo à travers le changement de voie, remplacée par le dessin de 436000',
        'source' => $dom, 'reviewed_at' => $day],
    ['kind' => 'trace', 'line' => '437000', 'source_name' => 'ohm', 'source_ref' => '201031326', 'near' => [-0.6672706, 48.5866638], 'radius_m' => 70,
        'reason' => 'gare de Domfront : corde droite du tracé OHM à travers le changement de voie, remplacée par le dessin de 436000',
        'source' => 'https://www.openhistoricalmap.org/way/201031326', 'reviewed_at' => $day],

    // Fragments isolés que la règle des fragments de moins de 60 m garde par prudence (SingleAxis::dropIsolatedFragments),
    // relus comme restes et retirés sur décision de Baptiste (réponse du 22/11/2026 : « Les retirer »).
    ['kind' => 'fragment', 'line' => '272000', 'at' => [2.35763, 49.87951], 'max_m' => 70,
        'reason' => 'Longueau : bout de voie BD TOPO de 59 m dans le faisceau, à 36 m de la voie de la ligne, relié à rien',
        'source' => $fragments.'45-233773-16.png)', 'reviewed_at' => $day],
    ['kind' => 'fragment', 'line' => '261000', 'at' => [2.34500, 49.87796], 'max_m' => 30,
        'reason' => 'Amiens, pont sur la Somme : 12 m sur la voie sud du faisceau, alors que la ligne suit la voie nord à 48 m',
        'source' => $fragments.'151-205303-3.png)', 'reviewed_at' => $day],
    ['kind' => 'fragment', 'line' => 'ligne-militaire-de-somme-bionne-a-courtemont-1914-1918', 'at' => [4.75724, 49.13993], 'max_m' => 70,
        'reason' => 'tracé archeo non recalé de 51 m dans un champ, parallèle à la courbe de la ligne à 87 m',
        'source' => $fragments.'2936-235236-8.png)', 'reviewed_at' => $day],

    // Nice - Breil-sur-Roya : la voie OHM 198736884 (10,4 km, de Breil vers Tende, au nord de 44,11° de latitude) est
    // un morceau de la ligne de Coni à Vintimille (946000), rattaché à 945000 par recouvrement de tracé. Elle mettait
    // dans le tracé de 945000 un morceau isolé à 19 km de Breil, que le profil en long reliait par un trou de 19 km.
    ['kind' => 'trace', 'line' => '945000', 'source_name' => 'ohm', 'source_ref' => '198736884',
        'reason' => 'voie de la ligne de Coni à Vintimille entre Breil-sur-Roya et Tende, rattachée à tort à Nice - Breil-sur-Roya',
        'source' => 'https://www.openhistoricalmap.org/way/198736884 ; storage/app/reports/agents/aprem-2111/trous-trace.md', 'reviewed_at' => $day],

    // Le Martinet - Beaucaire (813000) entre Saint-Julien-les-Fumades et la bifurcation au sud de Salindres (3,4 km) :
    // la ligne roule sur la voie du Teil à Alès (805000). Ses traces s'arrêtent à la gare (Saint-Julien - Le Martinet)
    // et reprennent à la bifurcation (Uzès - Saint-Julien) ; la section n'a que la trace archeo d'Alès à Bessèges,
    // portée par 805000 et jamais rattachée à 813000. Les PK de l'article le confirment : Saint-Julien PK 10,0, Uzès
    // PK 48,34, soit 3,4 km de tronc commun plus 35,0 km de la bifurcation à Uzès le long de la trace.
    ['kind' => 'shared', 'line' => '813000', 'over' => '805000', 'source_name' => 'archeoferroviaire', 'source_ref' => '64d00cea341f51c34d000da278a37fd7',
        'reason' => 'tronc commun avec la ligne du Teil à Alès entre Saint-Julien-les-Fumades et la bifurcation vers Uzès, sans trace propre de la ligne',
        'source' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_du_Martinet_%C3%A0_Beaucaire&oldid=227988999 (PK du schéma) ; storage/app/reports/agents/aprem-2111/trous-trace-2.md', 'reviewed_at' => $day],

    // Grande Ceinture (990000) à Versailles, de la gare des Chantiers à celle des Matelots (2,4 km) : la section fait
    // partie de la ligne de Paris-Montparnasse à Brest (420000), la Grande Ceinture y roule sur sa voie. Seule la voie
    // OHM de Paris - Brest y passe, rattachée en second à la Grande Ceinture mais sur toute sa longueur (le comblement
    // la refusait : ses bouts sont loin du tracé) ; le disque la limite à la section.
    ['kind' => 'shared', 'line' => '990000', 'over' => '420000', 'source_name' => 'ohm', 'source_ref' => '205265140', 'near' => [2.11990, 48.79550], 'radius_m' => 1180,
        'reason' => 'Versailles, des Chantiers aux Matelots : tronc commun avec la ligne de Paris-Montparnasse à Brest, sans trace propre de la Grande Ceinture',
        'source' => 'https://fr.wikipedia.org/wiki/Ligne_de_la_Grande_Ceinture_de_Paris (« de la gare des Chantiers à la gare des Matelots, à Versailles », partie de la ligne de Paris-Montparnasse à Brest) ; storage/app/reports/agents/aprem-2111/trous-trace-2.md', 'reviewed_at' => $day],
];
