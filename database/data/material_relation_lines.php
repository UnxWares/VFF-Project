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
];
