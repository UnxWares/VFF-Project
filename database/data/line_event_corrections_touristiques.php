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
 * Chantier « touristiques-incoherences » (23/11/2026) : sept cas où l'import (nom, date de fondation de l'association
 * prise pour le début, portion résolue sur les voies observées) contredisait l'exploitation actuelle, tranchés par
 * deux sources au moins (article Wikipédia en version citée et site de l'exploitant) : actions redate (date de début),
 * neutralize (section plus exploitée) et add (fin d'exploitation que la source ne dit pas, lue par TouristRailways).
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

    // 751000 Fontenoy - Saint-Sauveur : l'import (article d'un état ancien) la donnait exploitée depuis 1985. Les deux
    // sources actuelles disent le contraire : le train ne roule plus que de Toucy-Ville à Villiers-Saint-Benoît (10 km,
    // site de l'AATY) et la section vers Saint-Sauveur et l'étang de Moutiers n'est plus exploitée (mauvais état de la
    // voie) ; l'article n'y situe qu'un train de 1987 à 1992. 1985 est l'année de création de l'association : l'événement
    // n'est plus une exploitation, il devient un fait (le train actuel reste porté par 749000 et le catalogue).
    [
        'line' => '751000',
        'action' => 'neutralize',
        'match' => ['source' => 'heritage', 'type' => 'heritage_start', 'date' => '1985-01-01', 'from' => 'Fontenoy'],
        'label' => 'Création de l\'association des autorails touristiques de l\'Yonne (1985) ; la section de Fontenoy à Saint-Sauveur-en-Puisaye n\'est plus exploitée',
        'source_url' => 'https://www.aaty.org/association/',
        'quote' => 'La section Toucy-Ville à Etang-de-Moutiers n\'est plus exploité pour des raisons de sécurité du fait du mauvais état de la voie.',
        'reason' => 'site de l\'AATY (le train va de Toucy-Ville à Villiers-Saint-Benoît, 10 km) et article Wikipédia (train de 1987 à 1992 entre Saint-Sauveur-en-Puisaye et les étangs de Moutiers, parcours actuel de 12 km de Toucy à Villiers-Saint-Benoît)',
    ],

    // 210000 sud des Ardennes : l'import datait le début de 1979 (l'article ne le dit pas) et l'article parle encore de
    // Challerange. Article et site de l'association : exploitation touristique depuis 1988 ; article de la ligne : la
    // section touristique est limitée à Amagne - Lucquy - Attigny (rien au-delà : plus de trafic entre Attigny et
    // Challerange) ; site de l'association (2026) : départs d'Attigny vers Rilly-sur-Aisne et retour vers Amagne.
    [
        'line' => '210000',
        'action' => 'redate',
        'match' => ['source' => 'heritage', 'type' => 'heritage_start', 'date' => '1979-01-01', 'from' => 'Amagne - Lucquy'],
        'date' => '1988-01-01',
        'precision' => 'year',
        'date_text' => 'entre Amagne - Lucquy et Attigny, depuis 1988 (d\'après article Wikipédia de l\'exploitation)',
        'source_url' => $wp('Chemin de fer touristique du sud des Ardennes', 234894106),
        'quote' => 'L\'association commence l\'exploitation touristique de la ligne Amagne - Challerange en 1988.',
        'reason' => 'début de l\'exploitation touristique en 1988 (article de l\'exploitation, et site de l\'association : « Depuis 1988 ») ; section actuelle Amagne - Lucquy - Attigny (article de la ligne, version 238309937 : « ce dernier est limité à la section d\'Amagne - Lucquy à Attigny »)',
    ],

    // 474000 Centre-Bretagne (Napoléon Express) : l'import prenait la fondation de l'association (1990, Wikidata) pour le
    // début ; l'exploitation sur Auray - Pontivy commence le 28 juin 2021 (Pontivy - Lambel-Camors) et, selon le site de
    // l'association et celui de l'office de tourisme de la vallée du Blavet, le train va aujourd'hui de Pontivy à Auray.
    [
        'line' => '474000',
        'action' => 'redate',
        'match' => ['source' => 'heritage', 'type' => 'heritage_start', 'date' => '1990-01-01', 'from' => 'Pontivy'],
        'date' => '2021-06-28',
        'precision' => 'day',
        'from' => 'Pontivy',
        'to' => 'Auray',
        'date_text' => 'entre Pontivy et Auray, depuis le 28 juin 2021 (d\'après article Wikipédia de l\'exploitation)',
        'source_url' => $wp('Chemins de fer du Centre-Bretagne', 238058175),
        'quote' => 'À compter du 28 juin 2021, et pour la saison estivale, l\'association s\'est vue confier l\'exploitation du train touristique « Napoléon Express » entre les gares de Pontivy et de Lambel - Camors, sur la ligne d\'Auray à Pontivy.',
        'reason' => 'le Napoléon Express commence le 28 juin 2021 entre Pontivy et Lambel-Camors (article et site de l\'association : « En 2021 ... une nouvelle exploitation sur la ligne Pontivy-Auray ») et circule aujourd\'hui entre Pontivy et Auray (site de l\'association, site de l\'office de tourisme de la vallée du Blavet : 55 km)',
    ],

    // 475000 Saint-Brieuc - Pontivy : la même association y a fait rouler ses trains de 2007 à 2017 (Quintin - Ploeuc en
    // 2007, toute la ligne de Saint-Brieuc à Loudéac depuis 2011), puis la ligne a été fermée par SNCF Réseau en 2017 :
    // plus d'exploitation depuis. Le 1990 de l'import est la fondation de l'association ; les 35 km étaient ceux de la
    // ligne d'Auray à Pontivy, comptés deux fois.
    [
        'line' => '475000',
        'action' => 'redate',
        'match' => ['source' => 'heritage', 'type' => 'heritage_start', 'date' => '1990-01-01'],
        'date' => '2007-01-01',
        'precision' => 'year',
        'from' => 'Saint-Brieuc',
        'to' => 'Loudéac',
        'date_text' => 'entre Saint-Brieuc et Loudéac, depuis 2007 (d\'après le site de l\'association)',
        'source_url' => 'https://www.cfcb.bzh/l-association/historique-de-l-association/',
        'quote' => 'en 2007 entre Quintin et Ploeuc l\'Hermitage [...] À partir de 2011, l\'association met en marche [...] des circulations touristiques sur l\'ensemble de la ligne St-Brieuc-Loudéac.',
        'reason' => 'site de l\'association (premiers trains sur la ligne en 2007, ensemble de Saint-Brieuc à Loudéac depuis 2011) ; article Wikipédia : trains touristiques de 2005 à 2017 sur Saint-Brieuc - Pontivy',
    ],
    [
        'line' => '475000',
        'action' => 'add',
        'type' => 'heritage_end',
        'heritage_kind' => 'tourist_train',
        'date' => '2017-01-01',
        'precision' => 'year',
        'from' => 'Saint-Brieuc',
        'to' => 'Loudéac',
        'source_url' => $wp('Chemins de fer du Centre-Bretagne', 238058175),
        'quote' => 'L\'exploitation de rames touristiques n\'est plus possible depuis 2017, à la suite de dégradations involontaires de la part d\'agents de la SNCF.',
        'reason' => 'fin des trains des Chemins de fer du Centre-Bretagne en 2017 (article et site de l\'association : ligne fermée par SNCF Réseau après la dégradation des installations par un engin de maintenance)',
    ],

    // 487000 Bon-Repos : l'import résolvait 4,3 km sur la plate-forme métrique de Carhaix à Loudéac, qui n'est qu'un
    // projet (4,1 km à reposer de la halte de Bon-Repos à Gouarec). Le train roule en voie de 60 cm : 1 700 m posés en
    // 2017 depuis la gare de Gouarec jusqu'à la « gare aux Korrigans » (PK 33,606 à 35,306 de la ligne).
    [
        'line' => '487000',
        'action' => 'relocate',
        'match' => ['source' => 'heritage', 'type' => 'heritage_start', 'date' => '2015-01-01'],
        'from' => 'Gouarec',
        'to' => 'Gare aux Korrigans',
        'pk_start' => 33606,
        'pk_end' => 35306,
        'source_url' => $wp('Chemin de fer de Bon-Repos', 239891497),
        'quote' => 'la voie ferrée est posée à nouveau, au départ de la gare de Gouarec sur une distance de 1 700 m et dessert un terminus provisoire, dénommé la « gare aux korrigans ».',
        'reason' => 'section réellement exploitée : 1,7 km de voie de 60 cm au départ de Gouarec (article et site de l\'association : la remise en service de la voie métrique, 4,1 km, est un projet)',
    ],

    // 785000 AGRIVAP : « Courpière - Giroux » ne pesait 84 km que parce que la section résolue débordait ; les trains
    // réguliers vont d'Ambert à La Chaise-Dieu (42 km, site de l'association : 52 km exploités avec le vélorail
    // d'Ambert à Châtelet ; l'article parle de 85 km de voies sauvegardées, qui ne sont pas des voies exploitées).
    [
        'line' => '785000',
        'action' => 'relocate',
        'match' => ['source' => 'heritage', 'type' => 'heritage_start', 'date' => '1986-05-09', 'from' => 'Courpière'],
        'from' => 'Ambert',
        'to' => 'La Chaise-Dieu',
        'source_url' => $wp('AGRIVAP Les trains de la découverte', 240559213),
        'quote' => 'Depuis la saison 2016, seule la partie sud de la ligne d\'Ambert à La Chaise-Dieu est empruntée par les trains réguliers estivaux.',
        'reason' => 'section touristique d\'AGRIVAP : d\'Ambert à La Chaise-Dieu, 42 km (article, et site de l\'association : « le kilométrage exploité par Agrivap est de 52 kms avec la partie Ambert/La Chaise-Dieu en Train Touristique et Ambert/Châtelet en Vélorail »)',
    ],

];
