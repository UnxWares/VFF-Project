<?php

/**
 * Cantonnement déduit du régime d'exploitation, pour les tranches que SNCF Réseau libelle « _Autre » dans
 * `mode-de-cantonnement-des-lignes` (le jeu ne dit alors pas le système). Lu par
 * App\Services\Signalling\SignallingRegimeRules et appliqué par `vff:import-sncf-signalling` : la tranche « _Autre »
 * est découpée selon les portions du jeu `regime-dexploitation-des-lignes` (SNCF Réseau, ODbL). Une portion dont le
 * régime n'est pas ici reste sans système : on n'invente rien (la « voie unique » seule, 908 km d'« _Autre »,
 * admet plusieurs systèmes).
 *
 * Source unique : document pédagogique de l'EPSF « Les signaux - Les régimes d'exploitation des lignes - Les systèmes
 * d'espacement des trains » (v1, mars 2023), https://www.securite-ferroviaire.fr/sites/default/files/reglementations/pdf/2023-03/document-pedagogique-signaux-regimes-exploitation-v1.pdf
 *   - 3.3.2 : voie unique à signalisation simplifiée, « La gestion de l'espacement est assurée par le cantonnement téléphonique » ;
 *   - 3.3.3 : voie unique à trafic restreint (VUTR), « ne comportent normalement pas de signaux », « Aucun enclenchement n'est
 *     prévu », sécurité assurée par le chef de ligne et un programme de circulation ; et la liste des modes de cantonnement du
 *     3.3.1 (cantonnement téléphonique, CAPI, CAPI 95, BMVU) n'en cite aucun pour la VUTR ;
 *   - 3.3.4 : exploitation en navette, « la présence [d']une seule circulation sur la section », ce qui « par conception
 *     limite les risques de collision » : aucun cantonnement à assurer.
 * `NONE` = « sans cantonnement » au sens de VFF (aucun mode de cantonnement technique), déduit et non publié tel quel par
 * SNCF Réseau pour ces tranches : la `note` de la tranche le dit.
 *
 * @var array<string, array{system: string, source: string}>
 */
return [
    'Voie unique à signalisation simplifiée' => ['system' => 'CT-VU', 'source' => 'EPSF, document pédagogique des régimes d\'exploitation, 3.3.2 (cantonnement téléphonique)'],
    'Voie unique à trafic restreint (consigne de ligne)' => ['system' => 'NONE', 'source' => 'EPSF, document pédagogique des régimes d\'exploitation, 3.3.3 (VUTR : ni signaux ni enclenchement, programme de circulation)'],
    'Régime en navette' => ['system' => 'NONE', 'source' => 'EPSF, document pédagogique des régimes d\'exploitation, 3.3.4 (une seule circulation sur la section)'],
];
