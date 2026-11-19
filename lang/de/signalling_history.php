<?php

// Historique de la signalisation (vff:import-signalling-history) : frise de la fiche ligne.
// Faits relevés dans les articles de ligne de Wikipédia (CC BY-SA 4.0).
return [
    'title' => 'Signalisierung im Lauf der Zeit',
    'intro' => 'Inbetriebnahmedaten aus dem Wikipedia-Artikel der Strecke. Die Liste ist unvollständig: Ein System, das fehlt, kann in Betrieb gegangen sein, ohne dass der Artikel ein Datum nennt.',
    'system' => [
        'BM' => 'BM, Handblock',
        'CT' => 'Fernsprechblock',
        'TVM' => 'TVM',
        'ETCS' => 'ETCS',
    ],
    'section' => [
        'whole_line' => 'Ganze Strecke',
        'unspecified' => 'Abschnitt nicht angegeben',
        'between' => 'Von :from bis :to',
        'from' => 'Ab :from',
        'to' => 'Bis :to',
        'pk' => ':pk :from bis :to',
    ],
    // Herkunft einer Angabe, die nicht aus einem Satz des Artikels stammt.
    'origin' => [
        'opening' => 'Eröffnungsdatum der Strecke: Eine Schnellfahrstrecke fährt vom ersten Tag an mit Führerstandssignalisierung.',
        'infobox' => 'Datum aus der Infobox des Artikels.',
        'table' => 'Datum aus einer Tabelle des Artikels.',
    ],
    'source' => [
        'link' => 'Wikipedia',
        'hint' => 'Artikel „:title“, neuer Tab',
        'note_one' => 'Fakten aus dem Wikipedia-Artikel „:title“ (Lizenz CC BY-SA 4.0). Tag oder Monat stehen nur da, wenn der Artikel sie nennt.',
        'note_other' => 'Fakten aus den Wikipedia-Artikeln „:title“ (Lizenz CC BY-SA 4.0). Tag oder Monat stehen nur da, wenn die Artikel sie nennen.',
    ],
];
