<?php

return [
    /*
    | Règles facultatives du calcul des ères de gares (vff:build-station-eras), recommandées par l'audit des
    | gares et désactivées par défaut (App\Services\StationTimeline::ERA_RULES) :
    |
    |   g3 : ouverture déduite des lignes bornée par la carte (« entre l'année de la ligne et la première ère
    |        desservie ») ; les ères « fermée » avant la première ère desservie sont retirées ;
    |   g6 : fermeture d'une gare fermée sans date tirée de la fin des voyageurs de ses lignes, rattachée à
    |        sa section par PK.
    |
    | Liste séparée par des virgules (VFF_STATION_ERA_RULES=g3,g6). L'option --rules= de la commande passe
    | avant (--rules=none : aucune).
    */
    'era_rules' => array_values(array_filter(array_map('trim', explode(',', (string) env('VFF_STATION_ERA_RULES', ''))))),
];
