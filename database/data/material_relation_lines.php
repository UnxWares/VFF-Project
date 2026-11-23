<?php

/**
 * Lignes d'une relation que le plus court chemin du graphe ne tranche pas (App\Services\Materials\MaterialRelations),
 * lues par vff:import-materials : quand une phrase d'un article de série cite cette relation et que le routeur la
 * trouve ambiguë (plusieurs itinéraires proches), la série est rattachée à ces lignes, qui sont celles que tous
 * les itinéraires parcourent. Sans entrée ici, une relation ambiguë ne rattache rien.
 *
 * Format d'une entrée :
 *   [
 *       'relation' => ['Lyon', 'Saint-Gervais'],   // deux extrémités, dans un sens ou dans l'autre ; un nom de ville vaut pour ses gares
 *       'lines' => ['895000', '897000'],           // lines.code
 *       'source' => 'https://fr.wikipedia.org/w/index.php?title=…&oldid=…',
 *       'reason' => 'ce que dit la source, en une phrase',
 *   ]
 *
 * Décision de Baptiste du 22/11/2026, à la place d'une règle générale (essais du 21/11 : 322 liens créés à blanc,
 * dont des faux).
 *
 * @return list<array{relation: array{0: string, 1: string}, lines: list<string>, source: string, reason: string}>
 */
return [
    [
        'relation' => ['Lyon', 'Saint-Gervais'],
        'lines' => ['895000', '897000'],
        'source' => 'https://fr.wikipedia.org/w/index.php?title=Z_20500&oldid=241372587',
        'reason' => 'article Z 20500 : « De 2002 à 2006, certaines rames de Villeneuve ont été régulièrement détachées sur le réseau TER Rhône-Alpes durant l\'hiver afin de renforcer la desserte sur les axes « Lyon - Évian » et « Lyon - Saint-Gervais » » ; la relation passe par Annecy (897000) ou par Annemasse (890000 puis 897000 de Annemasse à La Roche-sur-Foron), et les deux itinéraires finissent par La Roche-sur-Foron - Saint-Gervais-les-Bains-Le Fayet (895000)',
    ],
    [
        'relation' => ['Paris', 'Laon'],
        'lines' => ['229000'],
        'source' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_La_Plaine_%C3%A0_Hirson_et_Anor_(fronti%C3%A8re)&oldid=241251469',
        'reason' => 'article de la ligne de La Plaine à Hirson et Anor, section « Exploitation commerciale » : « la ligne de La Plaine à Hirson et Anor est exploitée sur l\'entièreté de son parcours par les trains du TER Hauts-de-France, assurant des liaisons quotidiennes entre Paris-Nord et Laon » ; le graphe trouve aussi un itinéraire par Creil et Tergnier, 12 % plus long (RTG jusqu\'au 1er juin 1990, X 4630)',
    ],
    [
        'relation' => ['Neussargues', 'Saint-Chély-d\'Apcher'],
        'lines' => ['722000'],
        'source' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_B%C3%A9ziers_%C3%A0_Neussargues&oldid=240391025',
        'reason' => 'article de la ligne de Béziers à Neussargues, section « Trafic » : « Entre Neussargues et Saint-Chély-d\'Apcher, l\'Aubrac est le dernier train de voyageurs à circuler » et « Le train de marchandises livrant des coils à Saint-Chély-d\'Apcher depuis Neussargues est tracté grâce à deux locomotives de type BB 67400 » ; Saint-Chély-d\'Apcher est sur cette ligne, entre Neussargues et Marvejols',
    ],
    [
        'relation' => ['Nancy', 'Neufchâteau'],
        'lines' => ['032000'],
        'source' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Culmont_-_Chalindrey_%C3%A0_Toul&oldid=235261256',
        'reason' => 'article de la ligne de Culmont - Chalindrey à Toul, section « Trafic » : « Dans les années 2020, il s\'agit d\'un Intercités (reliant Nancy à Lyon via Dijon) et de quelques TER (entre Nancy et Neufchâteau ou Dijon) » ; de Nancy à Toul, les trains empruntent la ligne de Paris à Strasbourg, que la note ne fixe pas',
    ],
    [
        'relation' => ['Bordeaux', 'Limoges'],
        'lines' => ['570000', '621000', '611000'],
        'source' => 'https://fr.wikipedia.org/w/index.php?title=X_2800&oldid=241300021',
        'reason' => 'article X 2800, section « Lignes parcourues » : « Relation Limoges - Bordeaux via Périgueux » ; de Limoges à Périgueux la ligne de Limoges-Bénédictins à Périgueux, puis de Périgueux à Bordeaux le seul plus court chemin du graphe, sans autre itinéraire proche : ligne de Coutras à Tulle et ligne de Paris-Austerlitz à Bordeaux depuis Coutras (comme « Bordeaux - Coutras - Périgueux - Limoges » de l\'article CC 72000)',
    ],
    [
        'relation' => ['Limoges', 'Gannat'],
        'lines' => ['590000', '702000', '705000', '707000'],
        'source' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Commentry_%C3%A0_Gannat&oldid=231352794',
        'reason' => 'article de la ligne de Commentry à Gannat, section « Exploitation » : « Cette ligne était autrefois empruntée par les trains Intercités reliant Lyon à Bordeaux par Montluçon et Limoges » ; de Limoges à Saint-Sulpice-Laurière, puis par Montluçon et Commentry jusqu\'à Gannat',
    ],
];
