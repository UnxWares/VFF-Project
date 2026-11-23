<?php

/**
 * Ligne 131000 Cernay - Sewen : le Train Thur Doller Alsace part de la halte de Cernay-Saint-André, pas de la gare
 * SNCF de Cernay (chantier « jonctions latérales », 23/11/2026).
 *
 * Avant : la section de l'exploitation associative (« Cernay-Saint-André » - « Sentheim ») était résolue par les voies
 * observées (BD TOPO, OpenStreetMap), qui comprennent les voies de la gare SNCF de Cernay. À l'ère 2030, les 650
 * premiers mètres de la ligne, dans la gare SNCF (PK 0 à 0,570, non déclassés), étaient classés « train touristique »,
 * puis 1,5 km « abandonnés », puis de nouveau « train touristique » à partir de Saint-André ; l'invariant
 * segments.current_heritage_on_served_station comptait la gare SNCF dans un tronçon patrimonial.
 *
 * Après : section de Saint-André (gare « Saint-André » de la ligne, PK 2,408) à Sentheim, résolue sur le tracé de la
 * ligne ; la voie de la gare SNCF redevient une voie en service.
 *
 * Format : voir line_event_corrections.php. Pas de tiret long dans les textes.
 *
 * @return list<array<string, mixed>>
 */
$ttda = 'https://fr.wikipedia.org/w/index.php?title=Train_Thur_Doller_Alsace&oldid=238310486';

return [

    [
        'line' => '131000',
        'action' => 'relocate',
        'match' => ['source' => 'heritage', 'type' => 'heritage_start', 'date' => '1971-01-01', 'from' => 'Cernay-Saint-André'],
        'from' => 'Saint-André',
        'to' => 'Sentheim',
        'source_url' => $ttda,
        'quote' => 'il n\'y a pas de liaison depuis la gare SNCF de Cernay située dans le centre-ville.',
        'reason' => 'le train touristique part de la halte de Cernay-Saint-André (« cette halte ferroviaire fait actuellement office de point de départ du train touristique »), à 2,4 km de la gare SNCF de Cernay, dont les voies ne sont pas touristiques',
    ],

];
