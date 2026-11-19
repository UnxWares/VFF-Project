<?php

// Voies vertes OpenStreetMap sur d'anciennes emprises (vff:link-greenways) : section de la fiche ligne.
// Données © contributeurs OpenStreetMap, ODbL.
return [
    'line' => [
        'title' => 'Greenway',
        'intro_one' => 'A greenway follows the former trackbed of this line for :length, according to OpenStreetMap.',
        'intro_other' => ':count greenways follow the former trackbed of this line for :length in total, according to OpenStreetMap.',
        'on_trackbed' => ':length on the former trackbed',
        'opened' => 'opened in :year',
        'unnamed' => 'Unnamed greenway',
        'osm_hint' => 'OpenStreetMap',
        'map_link' => 'Show greenways on the map',
        'method' => 'A greenway is linked to the line when it follows its axis to within 8 m, where no track is in service or left in place. A greenway that only runs beside a line in service, or beside rails left in place, is left out.',
        'source' => 'Data © OpenStreetMap contributors (ODbL licence), read on :date.',
    ],
];
