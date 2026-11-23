<?php

// Historique de la signalisation (vff:import-signalling-history) : frise de la fiche ligne.
// Faits relevés dans les articles de ligne de Wikipédia (CC BY-SA 4.0).
return [
    'title' => 'Signalisierung und Zugbeeinflussung im Lauf der Zeit',
    'intro' => 'Inbetriebnahmedaten aus dem Wikipedia-Artikel der Strecke und aus weiteren Dokumenten (Entscheidungen und Berichte der EPSF, Bilanzen von SNCF Réseau und des Staates, Presse). Die Liste ist unvollständig: Ein System, das fehlt, kann in Betrieb gegangen sein, ohne dass eines dieser Dokumente ein Datum nennt.',
    'system' => [
        'BM' => 'BM, Handblock',
        'CT' => 'Fernsprechblock',
        'CC' => 'Zentrale Fernsteuerung (CCVU, CCR, PCD)',
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
        'document_hint' => ':title, neuer Tab',
        'note_documents' => 'Trägt der Link den Namen eines Herausgebers, stammt das Datum aus diesem Dokument und ist durch eine amtliche Quelle oder durch zwei unabhängige Quellen bestätigt. Tag oder Monat stehen nur da, wenn diese Dokumente sie nennen.',
    ],
    'at' => [
        'intro' => 'Im Abschnitt „:section“ gewähltes Datum: :date. Jede Inbetriebnahme wird zu diesem Datum eingeordnet.',
        'change' => 'Anderes Datum wählen',
    ],
    'status' => [
        'in_force' => 'An diesem Datum in Betrieb',
        'replaced' => 'Ersetzt durch :system (:when)',
        'later' => 'An diesem Datum noch nicht in Betrieb',
        'uncertain_year' => 'Datum nur auf das Jahr genau bekannt: vor oder nach diesem Datum in Betrieb',
        'uncertain_month' => 'Datum nur auf den Monat genau bekannt: vor oder nach diesem Datum in Betrieb',
    ],
    // Durch Dokumente widerlegte Daten (belegte Korrekturen), unter der Zeitleiste eingeklappt.
    'corrections' => [
        'summary' => 'Durch Dokumente widerlegte Daten (:count)',
        'intro' => 'Diese Daten, gelesen in einem Artikel oder einem Dokument, werden durch zuverlässigere Dokumente widerlegt. Sie stehen weder in der Zeitleiste noch auf der Karte.',
        'read_in' => 'Datum gelesen in',
        'original_document' => 'einem Dokument',
        'action' => [
            'neutralize' => 'Verworfen',
            'replace' => 'In der Zeitleiste durch das Datum der Dokumente ersetzt',
        ],
        'reason' => 'Warum (auf Französisch):',
        'documents' => 'Widerlegt durch:',
    ],
];
