<?php

/**
 * Cas légitimes écartés des invariants de App\Services\DataInvariants (forme et règles : App\Services\InvariantExceptions).
 * Relecture du 23/11/2026 (chantier « invariants-gares-nuit », rapport
 * storage/app/reports/agents/aprem-2111/invariants-gares-nuit.md). Une entrée par invariant et par slug ; la donnée est juste
 * ou ne peut pas être tranchée, c'est la règle qui ne la comprend pas.
 *
 * Pas de tiret long dans les textes (consigne éditoriale).
 *
 * @return list<array{invariant: string, slug: string, reason: string, source_url: string, reviewed_at: string}>
 */
$wp = fn (string $title, int $oldid) => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode(str_replace(' ', '_', $title)).'&oldid='.$oldid;
$day = '2026-11-23';
$before = 'stations.opened_before_lines';
$offSection = 'stations.ohm_opened_off_section';

$sceaux = $wp('Ligne de Sceaux', 240177929);
$sceauxReason = 'la ligne de Sceaux est inaugurée le 7 juin 1846 et ouverte au public le 23 juin (article de la ligne) ; les articles des gares donnent l\'une ou l\'autre de ces deux dates : inauguration distincte de l\'ouverture au public';

return [
    // ── Gares ouvertes avant leur ligne : inauguration distincte de l'ouverture au public, ou gare d'une ligne plus ancienne ──
    ['invariant' => $before, 'slug' => 'chalons-en-champagne', 'source_url' => $wp('Gare de Châlons-en-Champagne', 241435050), 'reviewed_at' => $day,
        'reason' => 'la section Épernay - Châlons est inaugurée le 6 novembre 1849 (général Cavaignac) ; « quatre jours plus tard la liaison entre Châlons et Paris devient quotidienne », le 10 novembre, date de l\'événement de la ligne'],
    ['invariant' => $before, 'slug' => 'arcueil-cachan', 'source_url' => $sceaux, 'reviewed_at' => $day, 'reason' => $sceauxReason],
    ['invariant' => $before, 'slug' => 'denfert-rochereau', 'source_url' => $sceaux, 'reviewed_at' => $day, 'reason' => $sceauxReason],
    ['invariant' => $before, 'slug' => 'aimargues', 'source_url' => $wp('Gare d\'Aimargues', 229439135), 'reviewed_at' => $day,
        'reason' => 'la gare est mise en service le 27 janvier 1868 avec la ligne d\'Arles à Lunel, rattachée sans PK (ligne de Lunel à Aimargues) ; la ligne de Saint-Césaire à Aigues-Mortes, seule ligne à PK, ouvre le 19 mai 1873 en tronc commun avec elle'],
    ['invariant' => $before, 'slug' => 'balbigny', 'source_url' => $wp('Gare de Balbigny', 230262798), 'reviewed_at' => $day,
        'reason' => 'la gare est ouverte aux voyageurs « lors de la première circulation sur la ligne le 15 mars 1833 » (ligne d\'Andrézieux à Roanne, antérieure à la section de la ligne de Moret à Lyon-Perrache du 24 novembre 1857 qui la porte aujourd\'hui)'],
    ['invariant' => $before, 'slug' => 'feurs', 'source_url' => $wp('Gare de Feurs', 230262903), 'reviewed_at' => $day,
        'reason' => 'mise en service du 15 mars 1833 (infobox de l\'article), première circulation de la ligne d\'Andrézieux à Roanne, antérieure à la section de la ligne de Moret à Lyon-Perrache du 24 novembre 1857 qui la porte aujourd\'hui'],

    // ── Gares dont la date OpenHistoricalMap est juste mais postérieure à la section de leur PK : halte ou gare nouvelle ──
    ['invariant' => $offSection, 'slug' => 'les-ramassiers', 'source_url' => $wp('Gare des Ramassiers', 234282927), 'reviewed_at' => $day,
        'reason' => 'halte de la ligne de Saint-Agne à Auch « mise en service en 2003 par la SNCF » : créée vingt-six ans après la section de 1877'],
    ['invariant' => $offSection, 'slug' => 'st-martin-du-touch', 'source_url' => $wp('Gare de Saint-Martin-du-Touch', 240370477), 'reviewed_at' => $day,
        'reason' => 'halte de la ligne de Saint-Agne à Auch « mise en service en 2003 par la SNCF » : créée vingt-six ans après la section de 1877'],
    ['invariant' => $offSection, 'slug' => 'le-toec', 'source_url' => $wp('Gare de Saint-Martin-du-Touch', 240370477), 'reviewed_at' => $day,
        'reason' => 'halte de la même ligne de Saint-Agne à Auch, ouverte avec les haltes de Saint-Martin-du-Touch et des Ramassiers en 2003 (pas d\'article propre : Wikidata, 2003) ; la section est de 1877'],
    ['invariant' => $offSection, 'slug' => 'vitrolles-aeroport-marseille-provence', 'source_url' => $wp('Gare de Vitrolles-Aéroport-Marseille-Provence', 240400749), 'reviewed_at' => $day,
        'reason' => '« Ouverte le 14 décembre 2008, elle est inaugurée le 23 janvier 2009 » : nouvelle gare remplaçant l\'ancienne gare de Vitrolles (1847), distincte de celle-ci'],
    ['invariant' => $offSection, 'slug' => 'irigny-yvours', 'source_url' => $wp('Gare d\'Irigny-Yvours', 241247137), 'reviewed_at' => $day,
        'reason' => '« La halte a été mise en service le 9 septembre 2019 » : halte créée sur une section ouverte au XIXe siècle'],
    ['invariant' => $offSection, 'slug' => 'blanzy', 'source_url' => $wp('Gare de Blanzy', 240430075), 'reviewed_at' => $day,
        'reason' => 'halte mise en service en 1897 par le PLM (« halte de Blanzy »), après la section de 1861 de la ligne du Coteau à Montchanin'],
    ['invariant' => $offSection, 'slug' => 'boutheon', 'source_url' => $wp('Gare de Bouthéon', 230262844), 'reviewed_at' => $day,
        'reason' => '« La gare est ouverte le 15 juin 1864 par la Compagnie des chemins de fer de Paris à Lyon et à la Méditerranée » : date juste, la section projetée par PK est antérieure'],
    ['invariant' => $offSection, 'slug' => 'alai', 'source_url' => $wp('Gare d\'Alaï', 236508689), 'reviewed_at' => $day,
        'reason' => '« Ouverte en 1992 » : halte créée sur la ligne de Paray-le-Monial à Givors-Canal, dont la section est de 1910'],
    ['invariant' => $offSection, 'slug' => 'croix-wasquehal', 'source_url' => $wp('Gare de Croix-Wasquehal', 239140331), 'reviewed_at' => $day,
        'reason' => 'gare de marchandises inaugurée le 1er mai 1873, « précédant de quelques mois l\'ouverture de la halte des voyageurs » : gare postérieure à la section de la ligne de Fives à Mouscron (1842)'],

    // ── Lignes dont la perte de kilomètres n'est pas un événement oublié ──
    ['invariant' => 'lines.km_drop_without_event', 'slug' => 'ligne-militaire-de-coucy-les-eppes', 'reviewed_at' => $day,
        'source_url' => 'https://vff-project.org/lines/ligne-militaire-de-coucy-les-eppes',
        'reason' => 'ligne militaire hors réseau national datée de son seul début (1953, nom du tracé) et sans fin connue : l\'ère 2030, état du jour, ne garde que les portions confirmées en service (0,4 km sur 2,2)'],
    ['invariant' => 'lines.km_drop_without_event', 'slug' => 'ligne-militaire-du-champ-de-tir-de-kergroix', 'reviewed_at' => $day,
        'source_url' => 'https://vff-project.org/lines/ligne-militaire-du-champ-de-tir-de-kergroix',
        'reason' => 'ligne militaire hors réseau national datée de son seul début (1916, OpenHistoricalMap) et sans fin connue : l\'ère 2030, état du jour, ne garde que les portions confirmées en service (0,8 km sur 3,7)'],
    ['invariant' => 'lines.km_drop_without_event', 'slug' => 'lignes-minieres-du-bassin-minier-du-nord-secteur-de-vimy', 'reviewed_at' => $day,
        'source_url' => 'https://vff-project.org/lines/lignes-minieres-du-bassin-minier-du-nord-secteur-de-vimy',
        'reason' => 'ligne minière hors réseau national datée de son seul début (1866, OpenHistoricalMap) et sans fin connue : aucune portion confirmée en service en 2030'],
    ['invariant' => 'lines.km_drop_without_event', 'slug' => 'voie-industrielle-de-bouee-a-cordemais', 'reviewed_at' => $day,
        'source_url' => 'https://vff-project.org/lines/voie-industrielle-de-bouee-a-cordemais',
        'reason' => 'voie industrielle hors réseau national datée de ses seuls débuts (1970 et 1982, OpenHistoricalMap) et sans fin connue : aucune portion confirmée en service en 2030'],
    // ── Gares déplacées : la fiche porte la position de la gare actuelle, le tracé de l'ère suit l'ancien emplacement
    // (traces-tronques, 23/11/2026, rapport storage/app/reports/agents/aprem-2111/traces-tronques.md) ──
    ['invariant' => 'stations.active_without_track', 'slug' => 'ste-marie-aux-mines', 'source_url' => $wp('Gare de Sainte-Marie-aux-Mines', 238409554), 'reviewed_at' => $day,
        'reason' => '« La gare de 1864 a été remplacée en 1937 par une gare à un nouvel emplacement dans l\'axe du tunnel » : aux ères 1870 à 1930, le tracé (ancienne gare en impasse, face à l\'avenue Robert Zeller) passe à 188 m de la position, celle de la gare de 1937'],
    ['invariant' => 'stations.active_without_track', 'slug' => 'strasbourg-neudorf', 'source_url' => $wp('Gare de Strasbourg-Neudorf', 240408199), 'reviewed_at' => $day,
        'reason' => 'la « station » de 1861 est « installée entre la route de l\'hôpital et la route de Colmar » ; « la voie ferrée est déplacée vers le sud de Neudorf au début du XXe siècle » et la gare voyageurs actuelle est de 1905 : à l\'ère 1870, le tracé de l\'ancienne voie passe à 269 m de la position de la gare de 1905'],
];
