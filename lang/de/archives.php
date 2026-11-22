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
        'intro_both' => 'Archivdokumente und Archivnachweise, deren Titel diese Strecke nennt. Jeder Link öffnet das Dokument auf der Website der Einrichtung, die es aufbewahrt.',
        'gallica_heading' => 'Gallica (BnF)',
        'gallica_hint' => 'Gallica',
        'show_more_one' => 'Das weitere Dokument anzeigen',
        'show_more_other' => 'Die :count weiteren Dokumente anzeigen',
        'show_less' => 'Weniger Dokumente anzeigen',
        'method' => 'Ein Dokument wird aufgeführt, wenn sein Titel zwei Orte der Strecke verbindet („Eisenbahn von X nach Y“) oder ihre beiden Endpunkte nennt, ohne einen Ort außerhalb der Strecke. Außerdem muss er von der Eisenbahn sprechen und ein Datum tragen, das zur Geschichte der Strecke passt. Zeitungen werden noch nicht durchsucht.',
        'source' => 'Quelle: gallica.bnf.fr / BnF. Katalogdaten der Bibliothèque nationale de France (Licence Ouverte), abgerufen am :date.',
    ],
    // Nachweise aus den Archiven der SNCF zu den Strecken (vff:import-sncf-archives), unter den Dokumenten aus Gallica.
    'records' => [
        'heading' => 'Archive der SNCF-Gruppe',
        'intro' => 'Akten, Fotografien und Filme aus den Archiven der SNCF-Gruppe, deren Titel diese Strecke nennt. Jeder Link öffnet den Nachweis auf archives.sncf.com.',
        'types' => [
            'document' => 'Archivakte',
            'image' => 'Fotografie oder Plakat',
            'video' => 'Film',
        ],
        'reference' => 'Akte :reference',
        'hint' => 'archives.sncf.com',
        'method' => 'Ein Nachweis wird aufgeführt, wenn sein Titel zwei Orte der Strecke verbindet („an der Strecke von Le Mans nach Mézidon“) oder ihre beiden Endpunkte nennt und sein Datum zur Geschichte der Strecke passt. Ein Firmenname („Compagnie du chemin de fer de Paris à Orléans“) bezeichnet keine Strecke. Nachweise über einzelne Bedienstete werden nicht übernommen.',
        'source' => 'Quelle: SNCF, Archiv- und Dokumentationsdienst (Datensatz „Archives SNCF“, Lizenz ODbL), abgerufen am :date.',
    ],
];
