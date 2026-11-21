<?php

/**
 * Corrections SOURCÉES de l'électrification par portion (20/11/2026). Même format que
 * line_event_corrections.php. La tension d'un événement est lue dans sa citation
 * (ElectrificationText::voltage) : la citation retenue doit donc nommer la bonne tension.
 *
 * Les chronologies d'électrification de toute une ligne (troisième rail, tensions successives,
 * mise hors tension) se corrigent dans App\Services\Traction\TractionCuration.
 *
 * @return list<array<string, mixed>>
 */
$valenceMoirans = 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Valence_%C3%A0_Moirans&oldid=241124486';

return [

    // 908000 Valence - Moirans. En 1994 (LGV Rhône-Alpes), seule la section de Valence-Ville à la LGV
    // est électrifiée : 1 500 V continu jusqu'à la section de séparation du PK 2,2, puis 25 kV 50 Hz
    // jusqu'à Saint-Marcel-lès-Valence. Romans - Saint-Marcel ne l'est qu'en 2011-2014 (événements
    // 9297, 9300, 9301). Le PK de Valence-Ville (1 179) et celui de la séparation (2 250) sont ceux du
    // jeu SNCF « électrification des lignes » ; Saint-Marcel-lès-Valence est au PK 9 760 (gares SNCF).
    //
    // L'événement Wikipédia « Romans à Valence-Ville » (9292) vient d'une phrase sur la période des
    // travaux de 2009-2013 qui parle du tronçon de Valence-Ville à la LGV ; il était lu en 1 500 V sur
    // les 9 km de Romans à Valence TGV. Il est neutralisé et remplacé par l'électrification de 1994 sur
    // sa vraie portion.
    [
        'line' => '908000',
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'electrification', 'date' => '1994-01-01', 'from' => 'Romans'],
        'label' => 'Électrification de 1994 entre Valence-Ville et la LGV Rhône-Alpes (phrase sur les travaux de 2009-2013)',
        'source_url' => $valenceMoirans,
        'quote' => 'Seuls circulent normalement les TGV transitant entre la LGV Rhône-Alpes et la gare de Valence-Ville, ce tronçon ne faisant pas l\'objet de travaux (modernisation totale, BAL, IPCS, électrification 1500 V continu et 25 kV) réalisés en 1994)',
        'reason' => 'la phrase vise le tronçon de Valence-Ville à la LGV, pas Romans - Valence TGV, électrifié seulement de 2011 à 2014',
    ],
    [
        'line' => '908000',
        'action' => 'add',
        'type' => 'electrification',
        'date' => '1994-01-01',
        'precision' => 'year',
        'from' => 'Valence-Ville',
        'to' => 'la séparation du PK 2,2',
        'pk_start' => 1179,
        'pk_end' => 2250,
        'source_url' => $valenceMoirans,
        'quote' => 'la jonction avec le 1,5 kV CC à Valence-Ville est existante depuis 1994',
        'reason' => '1 500 V continu de Valence-Ville à la section de séparation du PK 2,2, posée avec la LGV Rhône-Alpes',
    ],
    [
        'line' => '908000',
        'action' => 'relocate',
        'match' => ['source' => 'wikipedia', 'type' => 'electrification', 'date' => '1994-01-01', 'from' => 'Valence'],
        'from' => 'Valence',
        'to' => 'Saint-Marcel-lès-Valence',
        'pk_start' => 2250,
        'pk_end' => 9760,
        'source_url' => $valenceMoirans,
        'quote' => 'la ligne est électrifiée en 25 kV 50 Hz sur une dizaine de kilomètres, entre Valence et Saint-Marcel-lès-Valence (peu avant la gare de Valence TGV), avec une section de séparation entre 25000 volts alternatif et 1500 V continu au PK 2.2',
        'reason' => '25 kV 50 Hz de la séparation du PK 2,2 à Saint-Marcel-lès-Valence ; « Valence » était lu comme la gare de Valence TGV (731 m seulement)',
    ],
];
