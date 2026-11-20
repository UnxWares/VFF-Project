<?php

/*
| Carte du réseau (/maps).
*/
return [
    // Vue ferroviaire : plan schématique d'une époque (App\Services\Schematic), choix « Vue ferroviaire » de la
    // carte et point d'accès GET /api/v1/eras/{year}/schematic.geojson. false : le choix n'est pas proposé et
    // le point d'accès répond 404.
    'schematic' => (bool) env('VFF_SCHEMATIC_MAP', true),
];
