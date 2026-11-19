<?php

// Voies vertes OpenStreetMap sur d'anciennes emprises (vff:link-greenways) : section de la fiche ligne.
// Données © contributeurs OpenStreetMap, ODbL.
return [
    'line' => [
        'title' => 'Bahntrassenradweg',
        'intro_one' => 'Ein Radweg folgt laut OpenStreetMap auf :length der früheren Trasse dieser Strecke.',
        'intro_other' => ':count Radwege folgen laut OpenStreetMap insgesamt auf :length der früheren Trasse dieser Strecke.',
        'on_trackbed' => ':length auf der früheren Trasse',
        'opened' => 'eröffnet :year',
        'unnamed' => 'Radweg ohne Namen',
        'osm_hint' => 'OpenStreetMap',
        'map_link' => 'Radwege auf der Karte anzeigen',
        'method' => 'Ein Radweg wird der Strecke zugeordnet, wenn er ihrer Achse auf 8 m genau folgt, und zwar dort, wo kein Gleis mehr in Betrieb ist. Ein Radweg, der nur neben einer Strecke in Betrieb verläuft, bleibt unberücksichtigt.',
        'source' => 'Daten © OpenStreetMap-Mitwirkende (Lizenz ODbL), gelesen am :date.',
    ],
];
