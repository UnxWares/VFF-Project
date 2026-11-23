<?php

/**
 * Retraits faits à la main dans les tracés de référence, lus par vff:build-line-references à chaque reconstruction
 * (App\Services\LineReferenceExclusions : forme des entrées, portée, garde-fous). Un recalcul du moteur ne fait pas
 * revenir ce qui est retiré ici.
 *
 * - kind « trace » : la voie source (source_name, source_ref) n'entre pas dans le tracé de la ligne, en entier, ou
 *   seulement sa portion à moins de radius_m mètres du point near [lon, lat] ;
 * - kind « fragment » : la polyligne du tracé de la ligne qui passe à moins de 5 m du point at [lon, lat] et mesure
 *   au plus max_m mètres est retirée (position plutôt qu'identifiant : les ids de line_reference_parts changent à
 *   chaque calcul).
 *
 * @return list<array<string, mixed>>
 */
$fragments = 'storage/app/reports/agents/aprem-2111/fragments-60m.md (relecture sur orthophoto IGN, planche ';
$day = '2026-11-23';
$osm = 'https://www.openstreetmap.org/way/';

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

    // Fragments isolés que la règle des fragments de moins de 60 m garde par prudence (SingleAxis::dropIsolatedFragments),
    // relus comme restes et retirés sur décision de Baptiste (réponse du 22/11/2026 : « Les retirer »).
    ['kind' => 'fragment', 'line' => '272000', 'at' => [2.35763, 49.87951], 'max_m' => 70,
        'reason' => 'Longueau : bout de voie BD TOPO de 59 m dans le faisceau, à 36 m de la voie de la ligne, relié à rien',
        'source' => $fragments.'45-233773-16.png)', 'reviewed_at' => $day],
    ['kind' => 'fragment', 'line' => '261000', 'at' => [2.34500, 49.87796], 'max_m' => 30,
        'reason' => 'Amiens, pont sur la Somme : 12 m sur la voie sud du faisceau, alors que la ligne suit la voie nord à 48 m',
        'source' => $fragments.'151-205303-3.png)', 'reviewed_at' => $day],
    ['kind' => 'fragment', 'line' => 'archeo-ligne-militaire-de-somme-bionne-a-courtemont-1914-1918', 'at' => [4.75724, 49.13993], 'max_m' => 70,
        'reason' => 'tracé archeo non recalé de 51 m dans un champ, parallèle à la courbe de la ligne à 87 m',
        'source' => $fragments.'2936-235236-8.png)', 'reviewed_at' => $day],
];
