<?php

/**
 * Liens ligne × matériel gardés malgré un écartement contraire (App\Services\Materials\MaterialGaugeRule).
 *
 * La règle écarte une série dont l'écartement couvre moins du quart de la longueur de la ligne, toutes
 * ères confondues (série à voie normale sur une ligne métrique, par exemple). Une exception ne s'ajoute
 * ici qu'avec une source qui dit explicitement que la série a roulé sur cette ligne (voie à trois files,
 * raccordement à voie normale parcouru, transport sur wagon porteur…), relue à la main.
 *
 * Format d'une entrée :
 *   [
 *       'line' => '600000',                  // lines.code
 *       'material' => 'Q1234567',            // materials.wikidata_id
 *       'source' => 'https://fr.wikipedia.org/w/index.php?title=…&oldid=…',
 *       'reason' => 'ce que dit la source, en une phrase',
 *   ],
 *
 * Aucune exception à ce jour (relecture du 19/11/2026 : les quatre liens écartés étaient faux).
 *
 * @return list<array{line: string, material: string, source: string, reason: string}>
 */
return [];
