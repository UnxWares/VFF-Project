<?php

// Dokumente aus Gallica (BnF) zu den Strecken (vff:import-gallica): Abschnitt „In den Archiven“ der Streckenseite.
return [
    'line' => [
        'title' => 'In den Archiven',
        'intro' => 'Digitalisierte Dokumente aus Gallica, der digitalen Bibliothek der BnF (Französische Nationalbibliothek), deren Titel diese Strecke nennt. Jeder Link öffnet das Dokument auf der Website der BnF.',
        'undated' => 'Ohne Datum',
        'circa' => 'um :year',
        'before' => 'vor :year',
        'after' => 'nach :year',
        'types' => [
            'monograph' => 'Druckschrift',
            'map' => 'Karte oder Plan',
            'image' => 'Bild',
            'manuscript' => 'Handschrift',
        ],
        'shelfmark_one' => 'Signatur :shelfmark',
        'shelfmark_other' => 'Signaturen :shelfmark',
        'other_views' => 'Weitere Ansichten:',
        'view' => 'Ansicht :number',
        'gallica_hint' => 'Gallica',
        'show_more_one' => 'Das weitere Dokument anzeigen',
        'show_more_other' => 'Die :count weiteren Dokumente anzeigen',
        'show_less' => 'Weniger Dokumente anzeigen',
        'method' => 'Ein Dokument wird aufgeführt, wenn sein Titel zwei Orte der Strecke verbindet („Eisenbahn von X nach Y“) oder ihre beiden Endpunkte nennt, ohne einen Ort außerhalb der Strecke. Außerdem muss er von der Eisenbahn sprechen und ein Datum tragen, das zur Geschichte der Strecke passt. Zeitungen werden noch nicht durchsucht.',
        'source' => 'Quelle: gallica.bnf.fr / BnF. Katalogdaten der Bibliothèque nationale de France (Licence Ouverte), abgerufen am :date.',
    ],
];
