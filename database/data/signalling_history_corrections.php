<?php

/**
 * Corrections SOURCÉES de l'historique de la signalisation : un fait (de Wikipédia ou d'un document) que des
 * documents contredisent n'est plus montré (fiche, API, carte par ère) ; il reste en base avec la raison et les
 * sources de la correction (`GET /api/v1/lines/{line}/signalling-history`, clé `corrections`).
 *
 * Format et règles : App\Services\Signalling\SignallingCorrectionsFile. Lu par `vff:import-signalling-history`
 * (faits de Wikipédia) et `vff:import-signalling-sources` (faits des documents) ; un fait visé introuvable est
 * une erreur. Pas de tiret long.
 *
 * @return list<array<string, mixed>>
 */
return [

    // 752000 LGV Sud-Est : l'article Wikipédia (infobox, section sur la signalisation) donne « ETCS » en service
    // du 9 au 12 novembre 2024 avec la commande centralisée. Les documents décrivent ce blocus de 101 heures comme
    // le basculement des 58 postes d'aiguillage vers des enclenchements informatiques (PAI) et la mise en service
    // du centre de commande de Lyon Jean Macé ; l'ERTMS niveau 2 (projet LGV+) est visé pour 2026 et la TVM reste
    // le système en service. Recherche du 22/11/2026 : aucune mise en service de l'ETCS sur cette ligne n'est
    // annoncée à cette date (le site de SNCF Réseau refuse les robots).
    [
        'line' => '752000',
        'action' => 'neutralize',
        'target' => ['source' => 'wikipedia', 'system' => 'ETCS', 'date' => '2024-11-12'],
        'sources' => [
            [
                'publisher' => 'Railway Gazette International',
                'title' => 'French ERTMS strategy confirmed as Paris - Lyon roll-out progresses',
                'url' => 'https://railwaygazette.com/high-speed/french-ertms-strategy-confirmed-as-paris-lyon-roll-out-progresses/67803.article',
                'published' => '2024-11-13',
            ],
            [
                'publisher' => 'SYSTRA',
                'title' => 'Complete modernisation of the signalling for the Paris-Lyon high-speed line with SYSTRA',
                'url' => 'https://www.systra.com/?p=24177565',
                'published' => '2024-12-02',
            ],
        ],
        'quote' => 'the switchover to the PAI interlockings and commissioning of the new control centre required a four-day blockade on November 9-12',
        'reason' => 'le blocus du 9 au 12 novembre 2024 a mis en service les postes informatiques (PAI) et le centre de commande de Lyon, pas l\'ETCS : l\'ERTMS niveau 2 de la LGV+ Paris - Lyon était visé pour 2026',
    ],
];
