<?php

/**
 * Liens ligne × matériel gardés malgré une électrification contraire (App\Services\Materials\MaterialTractionRule).
 *
 * La règle écarte une série électrique dont aucun système (1 500 V continu, 25 kV 50 Hz…) ne couvre au moins
 * 5 % de la longueur connue de la ligne, à une ère au moins de sa période de service. Une exception ne s'ajoute
 * ici qu'avec une source qui dit explicitement que la ligne était électrifiée dans un système de la série (ou que
 * la série y a roulé sous tension), relue à la main. C'est le plus souvent une électrification que VFF ne connaît
 * pas encore (troisième rail déposé depuis, par exemple) : la donnée de la ligne est alors à compléter.
 *
 * Format d'une entrée :
 *   [
 *       'line' => '972000',                  // lines.code (ou lines.slug pour une ligne hors RFN)
 *       'material' => 'Q1234567',            // materials.wikidata_id
 *       'source' => 'https://fr.wikipedia.org/w/index.php?title=…&oldid=…',
 *       'reason' => 'ce que dit la source, en une phrase',
 *   ],
 *
 * @return list<array{line: string, material: string, source: string, reason: string}>
 */
return [
    [
        // Relu le 19/11/2026. VFF ne connaît pas le troisième rail de la ligne des Moulineaux (déposé quand la
        // ligne est devenue le tramway T2) : tous ses tronçons y sont « non électrifiés » (source SNCF actuelle).
        'line' => '972000',
        'material' => 'Q3574398', // Z 1200
        'source' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Puteaux_%C3%A0_Issy-Plaine&oldid=241186256',
        'reason' => 'La ligne des Moulineaux était électrifiée par troisième rail en 750 V (section « Desserte ») et « tous les mouvements sont assurés par automotrices dites de deuxième série (Z 1200) » jusqu\'à leur réforme en 1965.',
    ],
];
