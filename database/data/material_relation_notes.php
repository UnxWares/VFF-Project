<?php

/*
 * Faits sourcés sur une relation assurée par une série (App\Services\Materials\MaterialRelationNotes), lus à
 * chaque affichage : rien n'est écrit en base, le fichier suffit à les rejouer. Une note s'attache aux
 * relations de la série (LineMaterial::relations()) dont les extrémités sont les deux lieux de `relation`
 * (dans un sens ou dans l'autre ; un nom de ville vaut pour ses gares, « Paris » pour Paris-Montparnasse),
 * sur les lignes de `lines` seulement. Elle est servie par la fiche de la ligne, celle de la série, l'API REST,
 * GraphQL et le serveur MCP.
 *
 * Champs :
 *   - `material` : élément Wikidata de la série ; `relation` : les deux extrémités ; `lines` : codes RFN ;
 *   - `kind` : `fuel` (carburant de la série sur cette relation) ;
 *   - `fuel`, `feedstock` (`rapeseed` : colza) : ce que dit la source ;
 *   - `since`, `since_precision` : début, à la précision de la source (`day`, `month`, `year`) ;
 *   - `reported`, `reported_precision` : date de la source la plus récente qui l'atteste encore (un bilan, pas
 *     une fin : sans source plus récente, on ne sait pas si c'est toujours le cas) ;
 *   - `sources` : titre, éditeur, date, adresse ; leur droit de reprise est déclaré dans source_rights.php
 *     (domaines du fournisseur `tiers-cites` : faits isolés, lien et courte citation).
 *
 * Régiolis au B100 (question 27 de storage/app/reports/agents/QUESTIONS.md, tranchée le 21/11/2026) : le
 * communiqué du 17/01/2023 dit « Depuis avril 2021, les trains de la ligne Paris-Granville roulent
 * exclusivement au B100, un biocarburant 100% colza » et fait le bilan de 18 mois dans « les trains Régiolis
 * de la ligne Paris-Granville » ; la Région Normandie a voté la poursuite le 5 juillet 2022. L'article de la
 * Banque des Territoires (avec l'AFP, 14/04/2021) annonce l'essai. À revoir si une source plus récente dit
 * que c'est fini.
 *
 * @return list<array<string, mixed>>
 */
return [
    [
        'material' => 'Q432769', // Régiolis
        'relation' => ['Paris', 'Granville'],
        'lines' => ['420000', '990306', '395000', '430000', '405000'],
        'kind' => 'fuel',
        'fuel' => 'B100',
        'feedstock' => 'rapeseed',
        'since' => '2021-04-01',
        'since_precision' => 'month',
        'reported' => '2023-01-17',
        'reported_precision' => 'day',
        'sources' => [
            [
                'title' => '11 000 tonnes de CO2 évitées depuis avril 2021 grâce à l\'utilisation du biocarburant B100 sur la ligne Paris-Granville',
                'publisher' => 'Région Normandie, SNCF Voyageurs et Saipol (Oleo100), communiqué de presse',
                'date' => '2023-01-17',
                'url' => 'https://oleo100.com/oleo-assets/uploads/2023/01/2023-cp-bilan-des-18-mois-dutilisation-du-b100-vdef.pdf',
            ],
            [
                'title' => 'Un train au colza expérimenté sur la ligne Paris-Granville',
                'publisher' => 'Banque des Territoires (Localtis), avec l\'AFP',
                'date' => '2021-04-14',
                'url' => 'https://www.banquedesterritoires.fr/un-train-au-colza-experimente-sur-la-ligne-paris-granville',
            ],
        ],
    ],
];
