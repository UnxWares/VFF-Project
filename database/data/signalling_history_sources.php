<?php

/**
 * Mises en service de signalisation datées par des documents plutôt que par l'article Wikipédia de la ligne
 * (vff:import-signalling-sources, App\Services\Signalling\SignallingSourcesFile) : décisions et rapports
 * d'activité de l'EPSF, bilans LOTI de SNCF Réseau et de l'État (IGEDD), presse et ingénierie.
 *
 * Règle : chaque fait est confirmé par un document officiel (SNCF Réseau, État, région, EPSF, BEA-TT, Journal
 * officiel), ou par deux documents de sites différents. Quand le document officiel ne donne que l'année ou le
 * mois, ou ne nomme pas le système, la source qui le précise est citée aussi (souvent l'article Wikipédia de
 * la LGV, qui n'est jamais la seule source). Une date d'autorisation de l'EPSF est gardée telle quelle quand
 * aucun document ne donne le premier jour de service ; le résumé le dit.
 *
 * Résumés rédigés par VFF (200 caractères au plus) ; aucun texte recopié. Liens vérifiés le 22/11/2026.
 * Relevé et sources : storage/app/reports/agents/aprem-2111/signalisation-historique.md.
 */
return [
    [
        'line' => '005000', 'system' => 'TVM-430', 'date' => '2007-06-10', 'scope' => 'section',
        'from' => 'Vaires', 'to' => 'Baudrecourt',
        'summary' => 'Mise en service commerciale de la phase 1 de la LGV Est européenne, équipée de la TVM 430.',
        'sources' => [
            ['publisher' => 'SNCF Réseau', 'title' => 'LGV Est européenne phase 2, bilan a posteriori (bilan LOTI)', 'url' => 'https://www.igedd.developpement-durable.gouv.fr/IMG/pdf/016386-01_bilan_loti_cle734b42.pdf', 'published' => '2025', 'official' => true],
            ['publisher' => 'EPSF', 'title' => 'Rapport annuel de sécurité 2013', 'url' => 'https://www.era.europa.eu/sites/default/files/reports/Rapport%20de%20securite%202013-version%20officielle.pdf', 'published' => '2014', 'official' => true],
            ['publisher' => 'Wikipédia', 'title' => 'LGV Est européenne', 'url' => 'https://fr.wikipedia.org/wiki/LGV_Est_europ%C3%A9enne'],
        ],
    ],
    [
        'line' => '005000', 'system' => 'ETCS_L2', 'date' => '2013-12-17', 'scope' => 'section',
        'from' => 'Vaires', 'to' => 'Baudrecourt',
        'summary' => 'L\'EPSF autorise l\'exploitation commerciale de l\'ERTMS niveau 2, superposé à la TVM 430, sur la phase 1 : première LGV française exploitée sous ERTMS niveau 2.',
        'sources' => [
            ['publisher' => 'EPSF', 'title' => 'Rapport annuel de sécurité 2013', 'url' => 'https://www.era.europa.eu/sites/default/files/reports/Rapport%20de%20securite%202013-version%20officielle.pdf', 'published' => '2014', 'official' => true],
        ],
    ],
    [
        'line' => '005000', 'system' => 'TVM-430', 'date' => '2016-07-03', 'scope' => 'section',
        'from' => 'Baudrecourt', 'to' => 'Vendenheim',
        'summary' => 'Mise en service de la phase 2 de la LGV Est européenne (106 km), équipée de l\'ETCS niveau 2 superposé à la TVM 430.',
        'sources' => [
            ['publisher' => 'SNCF Réseau', 'title' => 'LGV Est européenne phase 2, bilan a posteriori (bilan LOTI)', 'url' => 'https://www.igedd.developpement-durable.gouv.fr/IMG/pdf/016386-01_bilan_loti_cle734b42.pdf', 'published' => '2025', 'official' => true],
            ['publisher' => 'EPSF', 'title' => 'Rapport d\'activité 2016', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/publications/pdf/2023-08/rapport_activite_2016_A3.pdf', 'published' => '2017', 'official' => true],
            ['publisher' => 'Railway Gazette International', 'title' => 'LGV Est Phase 2 opening completes Paris - Strasbourg high speed line', 'url' => 'https://www.railwaygazette.com/high-speed/lgv-est-phase-2-opening-completes-paris-strasbourg-high-speed-line/42754.article', 'published' => '2016-07-04'],
        ],
    ],
    [
        'line' => '005000', 'system' => 'ETCS_L2', 'date' => '2016-07-03', 'scope' => 'section',
        'from' => 'Baudrecourt', 'to' => 'Vendenheim',
        'summary' => 'Mise en service de la phase 2 de la LGV Est européenne, équipée dès l\'ouverture de l\'ETCS niveau 2 superposé à la TVM 430.',
        'sources' => [
            ['publisher' => 'SNCF Réseau', 'title' => 'LGV Est européenne phase 2, bilan a posteriori (bilan LOTI)', 'url' => 'https://www.igedd.developpement-durable.gouv.fr/IMG/pdf/016386-01_bilan_loti_cle734b42.pdf', 'published' => '2025', 'official' => true],
            ['publisher' => 'EPSF', 'title' => 'Rapport d\'activité 2016', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/publications/pdf/2023-08/rapport_activite_2016_A3.pdf', 'published' => '2017', 'official' => true],
            ['publisher' => 'Railway Gazette International', 'title' => 'LGV Est Phase 2 opening completes Paris - Strasbourg high speed line', 'url' => 'https://www.railwaygazette.com/high-speed/lgv-est-phase-2-opening-completes-paris-strasbourg-high-speed-line/42754.article', 'published' => '2016-07-04'],
        ],
    ],
    [
        'line' => '014000', 'system' => 'TVM-430', 'date' => '2011-12-11', 'scope' => 'section',
        'from' => 'Villers-les-Pots', 'to' => 'Petit-Croix',
        'summary' => 'Premières circulations commerciales sur la branche Est de la LGV Rhin-Rhône (140 km), autorisée par l\'EPSF le 5 décembre 2011 ; ligne équipée de la TVM 430.',
        'sources' => [
            ['publisher' => 'EPSF', 'title' => 'Rapport d\'activité 2011', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/publications/pdf/2023-08/ENR85_Rapport%20d%27activit%C3%A9%20de%20l%27EPSF%202011.pdf', 'published' => '2012', 'official' => true],
            ['publisher' => 'Wikipédia', 'title' => 'LGV Rhin-Rhône', 'url' => 'https://fr.wikipedia.org/wiki/LGV_Rhin-Rh%C3%B4ne'],
        ],
    ],
    [
        'line' => '408000', 'system' => 'TVM-300', 'date' => '2017-07-02', 'scope' => 'whole_line',
        'summary' => 'Premières circulations commerciales sur la LGV Bretagne - Pays de la Loire, équipée de l\'ETCS niveau 2 et de la TVM 300 superposée.',
        'sources' => [
            ['publisher' => 'EPSF', 'title' => 'Rapport d\'activité 2017', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/publications/pdf/2023-08/rapport_activite_epsf_2017.pdf', 'published' => '2018', 'official' => true],
            ['publisher' => 'EPSF', 'title' => 'Extrait de décision : Autorisation de mise en exploitation commerciale de la Ligne à Grande Vitesse Bretagne - Pays de la Loire', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/bulletins/pdf/2023-03/extrait_de_decision_741.pdf', 'published' => '2017-05-15', 'official' => true],
        ],
    ],
    [
        'line' => '408000', 'system' => 'ETCS_L2', 'date' => '2017-07-02', 'scope' => 'whole_line',
        'summary' => 'Premières circulations commerciales sur la LGV Bretagne - Pays de la Loire, équipée de l\'ETCS niveau 2 sur tout son linéaire.',
        'sources' => [
            ['publisher' => 'EPSF', 'title' => 'Rapport d\'activité 2017', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/publications/pdf/2023-08/rapport_activite_epsf_2017.pdf', 'published' => '2018', 'official' => true],
            ['publisher' => 'EPSF', 'title' => 'Rapport d\'activité 2018', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/publications/pdf/2023-08/rapport_activite_2018.pdf', 'published' => '2019', 'official' => true],
        ],
    ],
    [
        'line' => '408000', 'system' => 'ETCS_L1', 'date' => '2018-07-06', 'scope' => 'section',
        'pk_start_m' => 3162, 'pk_end_m' => 26485,
        'summary' => 'L\'EPSF autorise l\'ETCS niveau 1 sur le contournement nord du Mans et les raccordements de fret de la LGV, pour les trains de fret.',
        'sources' => [
            ['publisher' => 'EPSF', 'title' => 'Extrait de décision : Autorisation de mise en exploitation commerciale du sous-système ETCS niveau 1 sur la section Nord du Mans et les raccordements de fret de la LGV BPL', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/bulletins/pdf/2023-03/extrait_de_decision_861.pdf', 'published' => '2018-07-06', 'official' => true],
            ['publisher' => 'EPSF', 'title' => 'Rapport d\'activité 2018', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/publications/pdf/2023-08/rapport_activite_2018.pdf', 'published' => '2019', 'official' => true],
            ['publisher' => 'Eiffage Rail Express', 'title' => 'ETCS 1 System Compatibility Tests (ESC 1) pour la ligne LGV BPL', 'url' => 'https://www.era.europa.eu/system/files/2022-11/ere_d_qse_2_ndeg61_-_etcs_1_pour_la_ligne_lgv_bpl_ve.pdf'],
        ],
    ],
    [
        'line' => '408305', 'system' => 'ETCS_L1', 'date' => '2018-07-06', 'scope' => 'whole_line',
        'summary' => 'L\'EPSF autorise l\'ETCS niveau 1 sur les raccordements de fret de la LGV Bretagne - Pays de la Loire, dont celui de Connerré.',
        'sources' => [
            ['publisher' => 'EPSF', 'title' => 'Extrait de décision : Autorisation de mise en exploitation commerciale du sous-système ETCS niveau 1 sur la section Nord du Mans et les raccordements de fret de la LGV BPL', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/bulletins/pdf/2023-03/extrait_de_decision_861.pdf', 'published' => '2018-07-06', 'official' => true],
            ['publisher' => 'EPSF', 'title' => 'Rapport d\'activité 2018', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/publications/pdf/2023-08/rapport_activite_2018.pdf', 'published' => '2019', 'official' => true],
        ],
    ],
    [
        'line' => '408315', 'system' => 'ETCS_L1', 'date' => '2018-07-06', 'scope' => 'whole_line',
        'summary' => 'L\'EPSF autorise l\'ETCS niveau 1 sur les raccordements de fret de la LGV Bretagne - Pays de la Loire, dont celui de La Milesse.',
        'sources' => [
            ['publisher' => 'EPSF', 'title' => 'Extrait de décision : Autorisation de mise en exploitation commerciale du sous-système ETCS niveau 1 sur la section Nord du Mans et les raccordements de fret de la LGV BPL', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/bulletins/pdf/2023-03/extrait_de_decision_861.pdf', 'published' => '2018-07-06', 'official' => true],
            ['publisher' => 'EPSF', 'title' => 'Rapport d\'activité 2018', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/publications/pdf/2023-08/rapport_activite_2018.pdf', 'published' => '2019', 'official' => true],
        ],
    ],
    [
        'line' => '566000', 'system' => 'TVM-300', 'date' => '2017-07-02', 'scope' => 'whole_line',
        'summary' => 'Premières circulations commerciales sur la LGV Sud Europe Atlantique (Tours - Bordeaux), équipée de l\'ETCS niveau 2 et de la TVM 300 superposée.',
        'sources' => [
            ['publisher' => 'EPSF', 'title' => 'Rapport d\'activité 2017', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/publications/pdf/2023-08/rapport_activite_epsf_2017.pdf', 'published' => '2018', 'official' => true],
            ['publisher' => 'EPSF', 'title' => 'Extrait de décision : Autorisation de mise en exploitation commerciale de la ligne à grande vitesse Sud Europe Atlantique Tours-Bordeaux', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/bulletins/pdf/2023-03/extrait_de_decision_751.pdf', 'published' => '2017-06-16', 'official' => true],
        ],
    ],
    [
        'line' => '566000', 'system' => 'ETCS_L2', 'date' => '2017-07-02', 'scope' => 'whole_line',
        'summary' => 'Premières circulations commerciales sur la LGV Sud Europe Atlantique, équipée de l\'ETCS niveau 2 (320 km/h ; 300 km/h sous TVM 300).',
        'sources' => [
            ['publisher' => 'EPSF', 'title' => 'Rapport d\'activité 2017', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/publications/pdf/2023-08/rapport_activite_epsf_2017.pdf', 'published' => '2018', 'official' => true],
            ['publisher' => 'EPSF', 'title' => 'Extrait de décision : Autorisation de mise en exploitation commerciale de la ligne à grande vitesse Sud Europe Atlantique Tours-Bordeaux', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/bulletins/pdf/2023-03/extrait_de_decision_751.pdf', 'published' => '2017-06-16', 'official' => true],
        ],
    ],
    // 834000 « Ligne des Angles à Lattes » (code SNCF unique) : PK 0 à 27,890 = branche de la LGV Méditerranée vers Nîmes (TVM 430,
    // 2001 ; la gare de Nîmes-Pont du Gard est au PK 26,964, jeu « liste-des-quais »), PK 27,890 à 86,388 = contournement
    // de Nîmes et Montpellier (CNM, 2017), que visent les trois faits ci-dessous. Le jeu SNCF « mode-de-cantonnement-des-lignes »
    // (TVM 430 puis ETCS 1) et l'EPSF (décision n° 787 : BAL et KVB ; rapport d'activité 2018 et décision sur le raccordement de Redessan : ETCS 1 en superposition) ne se
    // contredisent donc pas : ils décrivent deux sections de la même ligne. Reste une lacune du jeu SNCF, qui ne donne qu'un
    // libellé par tranche : sur le CNM il n'écrit que l'ETCS 1, sans le BAL ni le KVB sous-jacents (documentés ici, non remontés
    // dans `line_signalling`).
    [
        'line' => '834000', 'system' => 'BAL', 'date' => '2017-12', 'scope' => 'section',
        'from' => 'Nîmes-Pont du Gard', 'to' => 'Montpellier Sud-de-France',
        'summary' => 'Premières circulations commerciales sur le contournement de Nîmes et Montpellier (autorisé le 10 novembre 2017), en signalisation latérale BAL avec KVB, à 220 km/h.',
        'sources' => [
            ['publisher' => 'EPSF', 'title' => 'Rapport d\'activité 2017', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/publications/pdf/2023-08/rapport_activite_epsf_2017.pdf', 'published' => '2018', 'official' => true],
            ['publisher' => 'EPSF', 'title' => 'Extrait de décision : Autorisation de mise en exploitation commerciale du projet de contournement entre Nîmes et Montpellier (CNM)', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/bulletins/pdf/2023-03/extrait_de_decision_787.pdf', 'published' => '2017-11-10', 'official' => true],
        ],
    ],
    [
        'line' => '834000', 'system' => 'KVB', 'date' => '2017-12', 'scope' => 'section',
        'from' => 'Nîmes-Pont du Gard', 'to' => 'Montpellier Sud-de-France',
        'summary' => 'Premières circulations commerciales sur le contournement de Nîmes et Montpellier, équipé du KVB avec le BAL.',
        'sources' => [
            ['publisher' => 'EPSF', 'title' => 'Rapport d\'activité 2017', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/publications/pdf/2023-08/rapport_activite_epsf_2017.pdf', 'published' => '2018', 'official' => true],
            ['publisher' => 'EPSF', 'title' => 'Extrait de décision : Autorisation de mise en exploitation commerciale du projet de contournement entre Nîmes et Montpellier (CNM)', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/bulletins/pdf/2023-03/extrait_de_decision_787.pdf', 'published' => '2017-11-10', 'official' => true],
        ],
    ],
    [
        'line' => '834000', 'system' => 'ETCS_L1', 'date' => '2018-04', 'scope' => 'section',
        'from' => 'Nîmes-Pont du Gard', 'to' => 'Montpellier Sud-de-France',
        'summary' => 'Autorisation commerciale de l\'ETCS niveau 1 sur le contournement de Nîmes et Montpellier, ligne du corridor de fret européen.',
        'sources' => [
            ['publisher' => 'EPSF', 'title' => 'Rapport d\'activité 2018', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/publications/pdf/2023-08/rapport_activite_2018.pdf', 'published' => '2019', 'official' => true],
            ['publisher' => 'EPSF', 'title' => 'Extrait de décision : Autorisation de mise en exploitation commerciale du sous-système de contrôle-commande et signalisation ETCS niveau 1, sur le raccordement de Redessan', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/bulletins/pdf/2023-03/extrait_de_decision_832.pdf', 'published' => '2018-04-11', 'official' => true],
        ],
    ],
    [
        'line' => '180000', 'system' => 'ETCS_L1', 'date' => '2016-08-01', 'scope' => 'section',
        'from' => 'Uckange', 'to' => 'Zoufftgen',
        'summary' => 'L\'EPSF autorise l\'ETCS niveau 1 entre Uckange et Zoufftgen, premier tronçon de ligne classique française exploité en ERTMS (corridor fret vers le Luxembourg).',
        'sources' => [
            ['publisher' => 'EPSF', 'title' => 'Extrait de décision : Autorisation de mise en exploitation commerciale en ETCS niveau 1 de la ligne 180000 entre Uckange et Zoufftgen', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/bulletins/pdf/2023-03/extrait_de_decision_ndeg681.pdf', 'published' => '2016-08-01', 'official' => true],
            ['publisher' => 'EPSF', 'title' => 'Rapport d\'activité 2016', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/publications/pdf/2023-08/rapport_activite_2016_A3.pdf', 'published' => '2017', 'official' => true],
        ],
    ],
    [
        'line' => '202000', 'system' => 'ETCS_L1', 'date' => '2017-01-31', 'scope' => 'section',
        'from' => 'Longuyon', 'to' => 'Mont-Saint-Martin', 'pk_start_m' => 229000, 'pk_end_m' => 250500,
        'summary' => 'L\'EPSF autorise l\'ETCS niveau 1 de Longuyon (PK 229) à Mont-Saint-Martin (PK 250,5), second site pilote du corridor de fret.',
        'sources' => [
            ['publisher' => 'EPSF', 'title' => 'Extrait de décision : Autorisation de mise en exploitation commerciale en ETCS niveau 1 de la ligne 202 000 de Longuyon à Mont Saint Martin', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/bulletins/pdf/2023-03/extrait_de_decision_711.pdf', 'published' => '2017-01-31', 'official' => true],
            ['publisher' => 'EPSF', 'title' => 'Rapport d\'activité 2017', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/publications/pdf/2023-08/rapport_activite_epsf_2017.pdf', 'published' => '2018', 'official' => true],
        ],
    ],
    [
        'line' => '247000', 'system' => 'ETCS_L1', 'date' => '2022-12-08', 'scope' => 'section',
        'pk_start_m' => 231500, 'pk_end_m' => 233100,
        'summary' => 'Mise en service autorisée de l\'ETCS niveau 1 (L1LS, transition avec le KVB) sur la partie française de la section frontière Hautmont - Quévy, du PK 231,5 à la frontière.',
        'sources' => [
            ['publisher' => 'EPSF', 'title' => 'Extrait de décision : Autorisation de mise en service relative au projet ETCS L1LS Transition ETCS / KVB sur la partie française de la section frontière Hautmont - Quévy (ligne 247000/L96)', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/bulletins/pdf/2023-03/extrait_de_decision_1146.pdf', 'published' => '2022-12-08', 'official' => true],
        ],
    ],
    [
        'line' => '854000', 'system' => 'ETCS_L1', 'date' => '2019-12-06', 'scope' => 'whole_line',
        'summary' => 'Mise en service autorisée de l\'ETCS niveau 1 (L1LS) sur la ligne Belfort - Delle, rouverte en décembre 2018.',
        'sources' => [
            ['publisher' => 'EPSF', 'title' => 'Extrait de décision : Autorisation de mise en service du système de contrôle commande ETCS L1LS de la ligne Belfort-Delle', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/bulletins/pdf/2023-03/extrait_de_decision_975.pdf', 'published' => '2019-12-06', 'official' => true],
        ],
    ],
    [
        'line' => '876000', 'system' => 'ETCS_L1', 'date' => '2019-12-13', 'scope' => 'unspecified',
        'summary' => 'Mise en service autorisée de l\'ETCS niveau 1 (L1LS) sur la section frontière de Pontarlier.',
        'sources' => [
            ['publisher' => 'EPSF', 'title' => 'Extrait de décision : Autorisation de mise en service du système de contrôle commande ETCS L1LS de la section frontière de Pontarlier', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/bulletins/pdf/2023-03/extrait_de_decision_979.pdf', 'published' => '2019-12-13', 'official' => true],
        ],
    ],
    [
        'line' => '890000', 'system' => 'ETCS_L1', 'date' => '2019-12-03', 'scope' => 'section',
        'pk_start_m' => 151766, 'pk_end_m' => 152328,
        'summary' => 'Mise en service autorisée, à la demande des CFF, de l\'ETCS niveau 1 (L1LS, transition avec le KVB) à La Plaine, de la limite d\'exploitation à la frontière suisse.',
        'sources' => [
            ['publisher' => 'EPSF', 'title' => 'Extrait de décision : Autorisation de mise en service relatif au projet de transition dynamique pour les trains BL3 ETCS L1LS-KVB à la plaine', 'url' => 'https://www.securite-ferroviaire.fr/sites/default/files/bulletins/pdf/2023-03/extrait_de_decision_974.pdf', 'published' => '2019-12-03', 'official' => true],
        ],
    ],
    [
        'line' => '752000', 'system' => 'TVM', 'date' => '1981-09-27', 'scope' => 'section',
        'from' => 'Saint-Florentin', 'to' => 'Sathonay',
        'summary' => 'Première mise en service commerciale de la LGV Sud-Est, de Saint-Florentin à Sathonay, en signalisation en cabine TVM (version 260 puis 270 à l\'origine).',
        'sources' => [
            ['publisher' => 'Wikipédia', 'title' => 'LGV Sud-Est', 'url' => 'https://fr.wikipedia.org/wiki/LGV_Sud-Est'],
            ['publisher' => 'SNCF', 'title' => 'Bilan LOTI de la LGV d\'interconnexion en Île-de-France', 'url' => 'https://www.igedd.developpement-durable.gouv.fr/IMG/pdf/Lgv_InterConnex_IDF_cle0fa1af.pdf', 'published' => '2005-12', 'official' => true],
            ['publisher' => 'EPSF', 'title' => 'Rapport annuel 2020', 'url' => 'https://www.era.europa.eu/sites/default/files/reports/NSA%20Annual%20Report%202020%20France%20FR.pdf', 'published' => '2021', 'official' => true],
        ],
    ],
    [
        'line' => '752000', 'system' => 'TVM', 'date' => '1983-09-25', 'scope' => 'section',
        'from' => 'Combs-la-Ville', 'to' => 'Saint-Florentin',
        'summary' => 'Mise en service du tronçon nord de la LGV Sud-Est, de Combs-la-Ville à Saint-Florentin, en signalisation en cabine TVM.',
        'sources' => [
            ['publisher' => 'Wikipédia', 'title' => 'LGV Sud-Est', 'url' => 'https://fr.wikipedia.org/wiki/LGV_Sud-Est'],
            ['publisher' => 'SNCF', 'title' => 'Bilan LOTI de la LGV d\'interconnexion en Île-de-France', 'url' => 'https://www.igedd.developpement-durable.gouv.fr/IMG/pdf/Lgv_InterConnex_IDF_cle0fa1af.pdf', 'published' => '2005-12', 'official' => true],
        ],
    ],
    [
        'line' => '752000', 'system' => 'TVM-300', 'date' => '1992-12-13', 'scope' => 'section',
        'from' => 'Montanay', 'to' => 'Saint-Quentin-Fallavier',
        'summary' => 'Mise en service de la première section de la LGV Rhône-Alpes (42 km), équipée de la TVM 300 sur ce tronçon nord.',
        'sources' => [
            ['publisher' => 'CGEDD', 'title' => 'Rapport sur les bilans LOTI de la LGV Rhône-Alpes, de la LGV Méditerranée et des gares nouvelles (n° 005448-01)', 'url' => 'https://www.igedd.developpement-durable.gouv.fr/IMG/pdf/5448-01_cle27828d-1.pdf', 'published' => '2008-07', 'official' => true],
            ['publisher' => 'Wikipédia', 'title' => 'LGV Rhône-Alpes', 'url' => 'https://fr.wikipedia.org/wiki/LGV_Rh%C3%B4ne-Alpes'],
        ],
    ],
    [
        'line' => '752000', 'system' => 'TVM-430', 'date' => '1994-07-03', 'scope' => 'section',
        'from' => 'Saint-Quentin-Fallavier', 'to' => 'Saint-Marcel-lès-Valence',
        'summary' => 'Mise en service de la seconde section de la LGV Rhône-Alpes (73 km), équipée de la TVM 430 au sud de la bifurcation de Saint-Quentin-Fallavier.',
        'sources' => [
            ['publisher' => 'CGEDD', 'title' => 'Rapport sur les bilans LOTI de la LGV Rhône-Alpes, de la LGV Méditerranée et des gares nouvelles (n° 005448-01)', 'url' => 'https://www.igedd.developpement-durable.gouv.fr/IMG/pdf/5448-01_cle27828d-1.pdf', 'published' => '2008-07', 'official' => true],
            ['publisher' => 'Wikipédia', 'title' => 'LGV Rhône-Alpes', 'url' => 'https://fr.wikipedia.org/wiki/LGV_Rh%C3%B4ne-Alpes'],
        ],
    ],
    [
        'line' => '752000', 'system' => 'TVM-430', 'date' => '2001-06-10', 'scope' => 'section',
        'from' => 'Saint-Marcel-lès-Valence', 'to' => 'Marseille',
        'summary' => 'Mise en service commerciale de la LGV Méditerranée, équipée de la TVM 430.',
        'sources' => [
            ['publisher' => 'CGEDD', 'title' => 'Rapport sur les bilans LOTI de la LGV Rhône-Alpes, de la LGV Méditerranée et des gares nouvelles (n° 005448-01)', 'url' => 'https://www.igedd.developpement-durable.gouv.fr/IMG/pdf/5448-01_cle27828d-1.pdf', 'published' => '2008-07', 'official' => true],
            ['publisher' => 'Wikipédia', 'title' => 'LGV Méditerranée', 'url' => 'https://fr.wikipedia.org/wiki/LGV_M%C3%A9diterran%C3%A9e'],
        ],
    ],
    [
        'line' => '429000', 'system' => 'TVM-300', 'date' => '1989-09-24', 'scope' => 'whole_line',
        'summary' => 'Mise en service de la branche Ouest de la LGV Atlantique jusqu\'à Connerré, équipée de la TVM 300.',
        'sources' => [
            ['publisher' => 'CGPC', 'title' => 'Bilan LOTI du TGV Atlantique', 'url' => 'https://www.igedd.developpement-durable.gouv.fr/IMG/pdf/TGV_Atl_cle2668c5.pdf', 'official' => true],
            ['publisher' => 'Wikipédia', 'title' => 'LGV Atlantique', 'url' => 'https://fr.wikipedia.org/wiki/LGV_Atlantique'],
        ],
    ],
    [
        'line' => '431000', 'system' => 'TVM-300', 'date' => '1989-09-24', 'scope' => 'section',
        'from' => 'Montrouge', 'to' => 'Courtalain',
        'summary' => 'Mise en service de la branche Ouest de la LGV Atlantique (tronc commun depuis Paris), équipée de la TVM 300.',
        'sources' => [
            ['publisher' => 'CGPC', 'title' => 'Bilan LOTI du TGV Atlantique', 'url' => 'https://www.igedd.developpement-durable.gouv.fr/IMG/pdf/TGV_Atl_cle2668c5.pdf', 'official' => true],
            ['publisher' => 'Wikipédia', 'title' => 'LGV Atlantique', 'url' => 'https://fr.wikipedia.org/wiki/LGV_Atlantique'],
        ],
    ],
    [
        'line' => '431000', 'system' => 'TVM-300', 'date' => '1990-09-30', 'scope' => 'section',
        'from' => 'Courtalain', 'to' => 'Saint-Pierre-des-Corps',
        'summary' => 'Mise en service de la branche Sud-Ouest de la LGV Atlantique, de Courtalain vers Tours, équipée de la TVM 300.',
        'sources' => [
            ['publisher' => 'CGPC', 'title' => 'Bilan LOTI du TGV Atlantique', 'url' => 'https://www.igedd.developpement-durable.gouv.fr/IMG/pdf/TGV_Atl_cle2668c5.pdf', 'official' => true],
            ['publisher' => 'Wikipédia', 'title' => 'LGV Atlantique', 'url' => 'https://fr.wikipedia.org/wiki/LGV_Atlantique'],
        ],
    ],
    [
        'line' => '226000', 'system' => 'TVM-430', 'date' => '1993-05-23', 'scope' => 'section',
        'from' => 'Gonesse', 'to' => 'Arras',
        'summary' => 'Mise en service de la LGV Nord entre la bifurcation de Gonesse et le raccordement d\'Arras, équipée de la TVM 430.',
        'sources' => [
            ['publisher' => 'SNCF', 'title' => 'Bilan LOTI de la LGV Nord Europe', 'url' => 'https://www.igedd.developpement-durable.gouv.fr/IMG/pdf/LGV_Nord_cle58b46b.pdf', 'published' => '2005-05', 'official' => true],
            ['publisher' => 'Wikipédia', 'title' => 'LGV Nord', 'url' => 'https://fr.wikipedia.org/wiki/LGV_Nord'],
        ],
    ],
    [
        'line' => '226000', 'system' => 'TVM-430', 'date' => '1993-09-26', 'scope' => 'section',
        'from' => 'Arras', 'to' => 'Lille',
        'summary' => 'Mise en service de la LGV Nord jusqu\'à Lille et Calais, en TVM 430.',
        'sources' => [
            ['publisher' => 'SNCF', 'title' => 'Bilan LOTI de la LGV Nord Europe', 'url' => 'https://www.igedd.developpement-durable.gouv.fr/IMG/pdf/LGV_Nord_cle58b46b.pdf', 'published' => '2005-05', 'official' => true],
            ['publisher' => 'Wikipédia', 'title' => 'LGV Nord', 'url' => 'https://fr.wikipedia.org/wiki/LGV_Nord'],
        ],
    ],
    [
        'line' => '226000', 'system' => 'TVM-430', 'date' => '1996-06-02', 'scope' => 'section',
        'from' => 'Fretin', 'to' => 'frontière belge',
        'summary' => 'Mise en service de la dernière section de la LGV Nord, du triangle de Fretin à la frontière belge, en TVM 430.',
        'sources' => [
            ['publisher' => 'Wikipédia', 'title' => 'LGV Nord', 'url' => 'https://fr.wikipedia.org/wiki/LGV_Nord'],
            ['publisher' => 'SNCF', 'title' => 'Bilan LOTI de la LGV d\'interconnexion en Île-de-France', 'url' => 'https://www.igedd.developpement-durable.gouv.fr/IMG/pdf/Lgv_InterConnex_IDF_cle0fa1af.pdf', 'published' => '2005-12', 'official' => true],
        ],
    ],
    [
        'line' => '216000', 'system' => 'TVM-430', 'date' => '1993-09-26', 'scope' => 'whole_line',
        'summary' => 'Ouverture de la branche de Lille à Calais-Fréthun de la LGV Nord, en TVM 430.',
        'sources' => [
            ['publisher' => 'SNCF', 'title' => 'Bilan LOTI de la LGV Nord Europe', 'url' => 'https://www.igedd.developpement-durable.gouv.fr/IMG/pdf/LGV_Nord_cle58b46b.pdf', 'published' => '2005-05', 'official' => true],
            ['publisher' => 'Wikipédia', 'title' => 'LGV Nord', 'url' => 'https://fr.wikipedia.org/wiki/LGV_Nord'],
        ],
    ],
    [
        'line' => '405000', 'system' => 'BAPR', 'date' => '1999', 'scope' => 'whole_line',
        'summary' => 'Fin de la modernisation de Paris - Granville : passage à la voie unique à commande centralisée entre Argentan et Granville, équipée du BAPR à compteurs d\'essieux.',
        'sources' => [
            ['publisher' => 'CGEDD', 'title' => 'Avis sur le bilan LOTI de l\'aménagement de la ligne Paris - Granville (n° 006948-01, 006949-01)', 'url' => 'https://www.vie-publique.fr/files/rapport/pdf/094000493.pdf', 'published' => '2009-09', 'official' => true],
            ['publisher' => 'Wikipédia', 'title' => 'Ligne d\'Argentan à Granville', 'url' => 'https://fr.wikipedia.org/wiki/Ligne_d%27Argentan_%C3%A0_Granville'],
        ],
    ],
    [
        'line' => '330000', 'system' => 'BAPR', 'date' => '2021-03', 'scope' => 'section',
        'from' => 'Gisors', 'to' => 'Serqueux',
        'summary' => 'Réouverture au fret de la ligne Serqueux - Gisors modernisée (voyageurs le 29 mars 2021), équipée de BAPR et du GSM-R.',
        'sources' => [
            ['publisher' => 'SYSTRA', 'title' => 'Mission accomplished for SYSTRA in Normandy, France', 'url' => 'https://www.systra.com/en/news/mission-accomplished-for-systra-in-normandy-france/', 'published' => '2021-04-13'],
            ['publisher' => 'Tendance Ouest', 'title' => 'Normandie : après le fret, la ligne Serqueux-Gisors ouverte au public le 29 mars', 'url' => 'https://www.tendanceouest.com/actualite-379648-normandie-apres-le-fret-la-ligne-serqueux-gisors-ouverte-au-public-le-29-mars.html', 'published' => '2021-03-20'],
        ],
    ],
    [
        'line' => '430000', 'system' => 'BAPR', 'date' => '2009-12', 'scope' => 'section',
        'from' => 'Alençon', 'to' => 'Le Mans', 'replaces' => 'BM',
        'summary' => 'Réseau ferré de France met en service un block automatique à permissivité restreinte entre Alençon et Le Mans, à la place du système manuel (16 nouveaux signaux, 13 M€ avec la section de Tours).',
        'sources' => [
            ['publisher' => 'Réseau ferré de France', 'title' => 'Lignes Caen - Alençon - Le Mans / Le Mans - Tours : mise en service d\'une nouvelle signalisation automatique de type BAPR (communiqué de presse)', 'url' => 'https://web.archive.org/web/20150924091635/http://www.rff.fr/IMG/pdf/20100710_BAPR_Alencon_-Le_Mans-Tours.pdf', 'published' => '2010-07', 'official' => true],
        ],
    ],
    [
        'line' => '561000', 'system' => 'BAPR', 'date' => '2010-06-13', 'scope' => 'section',
        'from' => 'Arnage', 'to' => 'La Membrolle-sur-Choisille', 'replaces' => 'BM',
        'summary' => 'Le block automatique à permissivité restreinte, à compteurs d\'essieux, remplace le block manuel entre Arnage et La Membrolle-sur-Choisille (juin 2010 selon RFF, le 13 selon l\'article).',
        'sources' => [
            ['publisher' => 'Réseau ferré de France', 'title' => 'Lignes Caen - Alençon - Le Mans / Le Mans - Tours : mise en service d\'une nouvelle signalisation automatique de type BAPR (communiqué de presse)', 'url' => 'https://web.archive.org/web/20150924091635/http://www.rff.fr/IMG/pdf/20100710_BAPR_Alencon_-Le_Mans-Tours.pdf', 'published' => '2010-07', 'official' => true],
            ['publisher' => 'Wikipédia', 'title' => 'Ligne de Tours au Mans', 'url' => 'https://fr.wikipedia.org/wiki/Ligne_de_Tours_au_Mans'],
        ],
    ],
    [
        'line' => '311000', 'system' => 'BAL', 'date' => '2010-12-13', 'scope' => 'section',
        'from' => 'Rang-du-Fliers', 'to' => 'Boulogne-Ville',
        'summary' => 'Mise en service de la ligne modernisée et électrifiée de Boulogne à Rang-du-Fliers : block automatique lumineux (à partir d\'Hesdigneul) et poste informatique d\'Étaples télécommandant Rang-du-Fliers.',
        'sources' => [
            ['publisher' => 'Réseau ferré de France', 'title' => 'Modernisation de la ligne Boulogne - Rang-du-Fliers, chroniques d\'un projet (plaquette, novembre 2010)', 'url' => 'https://web.archive.org/web/20150924092332/http://www.rff.fr/IMG/pdf/RFF-collector-pages-DEF_pour_BAT_1_.pdf', 'published' => '2010-11', 'official' => true],
            ['publisher' => 'SNCF Réseau', 'title' => 'Mode de cantonnement des lignes (données ouvertes)', 'url' => 'https://www.data.gouv.fr/datasets/mode-de-cantonnement-des-lignes', 'published' => '2022-03', 'official' => true],
        ],
    ],
    [
        'line' => '527000', 'system' => 'BAPR', 'date' => '2021-03-29', 'scope' => 'whole_line', 'replaces' => 'CT',
        'summary' => 'Fin de la modernisation de Clisson - Cholet : la nouvelle signalisation, un block automatique à permissivité restreinte, remplace le cantonnement téléphonique (installation les 27 et 28 mars 2021).',
        'sources' => [
            ['publisher' => 'Région des Pays de la Loire', 'title' => 'Inauguration de la ligne modernisée Clisson - Cholet (dossier de presse)', 'url' => 'https://www.paysdelaloire.fr/sites/default/files/2019-12/dp_19-04-12_transport-inauguration-ligne-modernisee-clisson-cholet.pdf', 'published' => '2019-04-12', 'official' => true],
            ['publisher' => 'Breizh-info', 'title' => 'Transport ferroviaire en Bretagne : les lignes Dol-Dinan et Nantes-Cholet musclées', 'url' => 'https://www.breizh-info.com/2021/03/26/161258/transport-ferroviaire-en-bretagne-les-lignes-dol-dinan-et-nantes-cholet-musclees/', 'published' => '2021-03-26'],
        ],
    ],
];
