<?php

/*
 * Dates de service d'une série corrigées d'après une source lue (App\Support\MaterialDateCorrections), appliquées
 * par vff:import-materials à la place de la valeur de Wikidata. Seulement quand la source contredit Wikidata
 * et dit clairement ce que vaut la date ; la valeur remplacée reste lisible dans la provenance (`replaces`).
 *
 * Champs : `material` (élément Wikidata), `field` (`in_service_from` ou `in_service_to`), `year`, `replaces`
 * (année de Wikidata remplacée : la correction ne s'applique plus si Wikidata change), `source` (permalien),
 * `quote` (courte citation exacte), `reason`.
 *
 * Relecture du 21/11/2026 (rapport storage/app/reports/agents/matin-2111/materiel-verif.md).
 *
 * @return list<array{material: string, field: string, year: int, replaces: int|null, source: string, quote: string, reason: string}>
 */
return [
    [
        'material' => 'Q2931172', // CC 80000
        'field' => 'in_service_to',
        'year' => 1985,
        'replaces' => 1964,
        'source' => 'https://fr.wikipedia.org/w/index.php?title=CC_80000&oldid=225162460',
        'quote' => 'Cette période active prend fin en 1985 car la locomotive doit subir une révision qui n\'a en définitive pas lieu',
        'reason' => 'le retrait du 25 février 1964 est celui des deux prototypes à turbine 060 GA, garés puis vendus ; la 060 GA 1, reconstruite par les CFD en juillet 1967 sous le numéro CC 80001, roule sur Paris - Caen - Cherbourg puis Paris - Argentan - Granville jusqu\'en 1977, hors inventaire SNCF, puis pour Desquenne et Giral jusqu\'en 1985 (Loco Revue n° 256 et 257, mai et juin 1990, cités par l\'article)',
    ],
];
