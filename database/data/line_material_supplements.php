<?php

/**
 * Liens ligne × matériel et périodes de circulation lus à la main dans une phrase citée, quand la lecture
 * automatique ne peut pas les prendre (App\Services\Materials\LineMaterialSupplements, lus par vff:import-materials
 * pour les liens et par vff:import-material-periods pour les périodes) : date posée dans la phrase précédente,
 * série nommée sans lien, phrase d'une section de commandes que l'import ne lit pas.
 *
 * `links` : une preuve ajoutée au couple ligne × série (le lien est créé s'il n'existe pas), avec sa période quand
 * la phrase la date pour cette ligne (`from`, `to` : date ISO et précision `day`, `month` ou `year`).
 *   [
 *       'line' => '366000',                  // lines.code (ou lines.slug pour une ligne hors RFN)
 *       'material' => 'Q120253257',          // materials.wikidata_id
 *       'kind' => 'wikipedia_line_article',  // ou wikipedia_material_article : article de la ligne ou de la série
 *       'article' => 'Regio 2N', 'revid' => 241123224, 'section' => 'titre de la section',
 *       'quote' => 'citation exacte (tirets longs remplacés par un tiret simple), 255 caractères au plus',
 *       'from' => ['date' => '2020-02-17', 'precision' => 'day'],   // facultatif
 *       'to' => null,                                               // facultatif
 *   ],
 *
 * `dropped_periods` : période lue par l'import mais contredite par une autre source (début d'une exclusivité lu
 * comme un début de circulation…), retirée par vff:import-material-periods. `start` et `end` : dates telles que
 * l'import les écrit (null pour une borne absente).
 *
 * Relecture du 23/11/2026 (rapports storage/app/reports/agents/aprem-2111/regio2n-omneo.md et materiel-manques.md).
 *
 * @return array{
 *     links: list<array{line: string, material: string, kind: string, article: string, revid: int, section: string, quote: string, from?: array{date: string, precision: string}|null, to?: array{date: string, precision: string}|null}>,
 *     dropped_periods: list<array{line: string, material: string, start: ?string, end: ?string, source: string, reason: string}>
 * }
 */
return [
    'links' => [
        [
            'line' => '366000',
            'material' => 'Q120253257', // Z 56600 (Omneo Premium)
            'kind' => 'wikipedia_material_article',
            'article' => 'Regio 2N',
            'revid' => 241123224,
            'section' => 'Dessertes prévues et commandes',
            'quote' => 'Commandées à 40 exemplaires en 2016, les Z 56600 sont progressivement mises en service, depuis le 17 février 2020, sur les lignes Paris - Caen - Cherbourg / Trouville - Deauville et Paris - Rouen - Le Havre',
            'from' => ['date' => '2020-02-17', 'precision' => 'day'],
        ],
        [
            'line' => '340000',
            'material' => 'Q120253257', // Z 56600 (Omneo Premium) : la phrase ne date pas la venue sur Paris - Le Havre
            'kind' => 'wikipedia_material_article',
            'article' => 'Regio 2N',
            'revid' => 241123224,
            'section' => 'Dessertes prévues et commandes',
            'quote' => 'Commandées à 40 exemplaires en 2016, les Z 56600 sont progressivement mises en service, depuis le 17 février 2020, sur les lignes Paris - Caen - Cherbourg / Trouville - Deauville et Paris - Rouen - Le Havre',
        ],
        [
            'line' => '366000',
            'material' => 'Q604865', // BB 26000 : nommée sans lien dans la phrase
            'kind' => 'wikipedia_line_article',
            'article' => 'Ligne de Mantes-la-Jolie à Cherbourg',
            'revid' => 241344631,
            'section' => 'Desserte > Liaisons Krono+ (grandes lignes)',
            'quote' => 'La dernière circulation des BB 26000 a eu lieu le 2 février 2021. Depuis cette date, des rames Omneo Premium assurent le service sur la ligne.',
            'to' => ['date' => '2021-02-02', 'precision' => 'day'],
        ],
        [
            'line' => '366000',
            'material' => 'Q265728', // Rame à turbine à gaz : citée sans lien (article « Turbotrain ») par l'article de la ligne
            'kind' => 'wikipedia_material_article',
            'article' => 'Rame à turbine à gaz',
            'revid' => 239497908,
            'section' => 'Version française > Relations françaises desservies par les RTG',
            'quote' => 'Paris - Cherbourg : de 1975 au 1 juin 1996, en remplacement des ETG mutés progressivement à Vénissieux.',
            'from' => ['date' => '1975-01-01', 'precision' => 'year'],
            'to' => ['date' => '1996-06-01', 'precision' => 'day'],
        ],
        [
            'line' => '366000',
            'material' => 'Q3588105', // Élément à turbine à gaz : fin sur la relation, remplacé par les RTG
            'kind' => 'wikipedia_material_article',
            'article' => 'Élément à turbine à gaz',
            'revid' => 225161072,
            'section' => 'Introduction',
            'quote' => 'Ils sont cependant remplacés dès 1975, sur cette relation dont la fréquentation s\'est fortement accrue, par les rames à turbine à gaz (RTG, turbotrains de seconde génération), plus puissantes et d\'une capacité supérieure.',
            'to' => ['date' => '1975-01-01', 'precision' => 'year'],
        ],
        [
            'line' => '366000',
            'material' => 'Q3570526', // X 72500 : Caen - Mézidon est la section de la ligne que la phrase dessert (liste TER Normandie)
            'kind' => 'wikipedia_material_article',
            'article' => 'X 72500',
            'revid' => 239956152,
            'section' => 'Relations assurées > TER Normandie',
            'quote' => 'Caen - Mézidon - Argentan - Alençon - Le Mans - Tours',
        ],
        [
            'line' => '340000',
            'material' => 'Q137669875', // Z 56800 (Omneo Premium Citi, seconde commande normande)
            'kind' => 'wikipedia_material_article',
            'article' => 'Regio 2N',
            'revid' => 241518027,
            'section' => 'Relations effectuées > TER > Normandie',
            'quote' => 'Paris-Saint-Lazare - Rouen-Rive-Droite (Omneo Premium Citi, Z 56800)',
        ],
        [
            'line' => '366000',
            'material' => 'Q137669875', // Z 56800 : Paris - Serquigny passe par Mantes - Évreux - Serquigny
            'kind' => 'wikipedia_material_article',
            'article' => 'Regio 2N',
            'revid' => 241518027,
            'section' => 'Relations effectuées > TER > Normandie',
            'quote' => 'Paris-Saint-Lazare - Serquigny (Omneo Premium Citi, Z 56800)',
        ],
    ],
    'dropped_periods' => [
        [
            'line' => '366000',
            'material' => 'Q1131858', // Voiture Corail
            'start' => '1996-01-01',
            'end' => null,
            'source' => 'https://fr.wikipedia.org/w/index.php?title=BB_67400&oldid=240659828',
            'reason' => '« Depuis 1996, ces deux liaisons étaient exclusivement assurées par des rames Corail » date le début de l\'exclusivité, pas l\'arrivée des Corail : l\'article BB 67400 les fait rouler sur Paris - Caen - Cherbourg avec les RTG jusqu\'en 1996',
        ],
    ],
];
