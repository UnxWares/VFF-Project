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

    // 180000 Metz-Ville - Zoufftgen : l'article Wikipédia donne « ETCS » mis en service le 1er août 2021 et cite
    // l'extrait de décision n° 681 de l'EPSF. Cet extrait est daté du 1er août 2016 (référence A/CG/2016-08-001) :
    // l'autorisation de l'ETCS niveau 1 du site pilote n° 1, d'Uckange à Zoufftgen, date de 2016, pas de 2021.
    // Le fait juste (ETCS_L1, 01/08/2016) est déjà dans signalling_history_sources.php.
    [
        'line' => '180000',
        'action' => 'neutralize',
        'target' => ['source' => 'wikipedia', 'system' => 'ETCS', 'date' => '2021-08-01'],
        'sources' => [
            [
                'publisher' => 'EPSF',
                'title' => 'Extrait de décision n° 681 : autorisation de mise en exploitation commerciale en ETCS niveau 1 de la ligne 180000 entre Uckange et Zoufftgen',
                'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/bulletins/pdf/2023-03/extrait_de_decision_681.pdf',
                'published' => '2016-08-01',
                'official' => true,
            ],
            [
                'publisher' => 'EPSF',
                'title' => 'Rapport d\'activité 2017',
                'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/publications/pdf/2023-08/rapport_activite_epsf_2017.pdf',
                'published' => '2018',
                'official' => true,
            ],
        ],
        'quote' => 'Réf : 2015120007, date de décision 01/08/2016. Autorisation de mise en exploitation commerciale en ETCS niveau 1 de la ligne 180000 entre Uckange et Zoufftgen',
        'reason' => 'la décision de l\'EPSF citée par l\'article date du 1er août 2016, pas de 2021 : l\'ETCS niveau 1 d\'Uckange à Zoufftgen a été autorisé en 2016 (site pilote n° 1)',
    ],
];
