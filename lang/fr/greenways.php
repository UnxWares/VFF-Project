<?php

// Voies vertes OpenStreetMap sur d'anciennes emprises (vff:link-greenways) : section de la fiche ligne.
// Données © contributeurs OpenStreetMap, ODbL.
return [
    'line' => [
        'title' => 'Voie verte',
        'intro_one' => 'Une voie verte reprend l’ancienne emprise de cette ligne sur :length, d’après OpenStreetMap.',
        'intro_other' => ':count voies vertes reprennent l’ancienne emprise de cette ligne sur :length au total, d’après OpenStreetMap.',
        'on_trackbed' => ':length sur l’ancienne emprise',
        'opened' => 'ouverte en :year',
        'unnamed' => 'Voie verte sans nom',
        'osm_hint' => 'OpenStreetMap',
        'map_link' => 'Voir les voies vertes sur la carte',
        'method' => 'Une voie verte est rattachée à la ligne quand elle suit son axe à 8 m près, là où il ne reste aucune voie en service ni laissée en place. Une voie verte qui longe seulement une ligne en service ou des rails restés en place n’est pas retenue.',
        'source' => 'Données © contributeurs OpenStreetMap (licence ODbL), lues le :date.',
    ],
];
