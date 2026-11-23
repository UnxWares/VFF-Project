<?php

/**
 * Dernier passage sur les documents libres pour dater la mise en service de la signalisation (cantonnement) des
 * lignes classiques en service (App\Services\Signalling\SignallingDating) : date écrite ici à la fin de chaque passage,
 * montrée sur la fiche de ligne et dans les API avec la mention « aucun document libre consulté ne date… ».
 *
 * Documents lus : articles de Wikipédia des lignes (cache de `vff:import-signalling-history`), décisions et bulletins
 * de l'EPSF, jeux de SNCF Réseau (`mode-de-cantonnement-des-lignes` et `lignes-equipees-de-kvb` : l'état actuel,
 * sans aucune date de mise en service). Relevé : storage/app/reports/agents/aprem-2111/signalisation-seuil.md.
 */
return [
    'checked_at' => '2026-11-23',
];
