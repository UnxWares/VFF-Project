<?php

// Seite „Offene Daten“ (/open-data): Datensätze aus vff:export-open-data.
return [
    'meta_title' => 'Offene Daten',
    'meta_description' => 'Laden Sie die Daten von VFF unter der ODbL herunter: das französische Bahnnetz nach Jahrzehnten (Strecken, Abschnitte, Bahnhöfe), datierte Ereignisse, Unfälle und Fahrzeuge, als CSV und GeoJSON.',

    'title' => 'Offene Daten',
    'subtitle' => 'Das Bahnnetz nach Jahrzehnten, datierte Ereignisse, Unfälle und Fahrzeuge zum Herunterladen als CSV und GeoJSON. Jeder Wert behält seine Quelle.',

    'licence' => [
        'title' => 'Lizenz und Namensnennung',
        'text' => 'Die Datensätze werden unter der Open Database License (ODbL) 1.0 veröffentlicht. Sie dürfen sie kopieren, verändern und weiterverwenden, auch zu kommerziellen Zwecken.',
        'licence_link' => 'Lizenztext',
        'attribution' => 'Nennen Sie „VFF - Voies Ferrées de France“ mit einem Link auf vff-project.org sowie die Quellen aus der Datei LICENCE.txt jedes Datensatzes.',
        'share_alike' => 'Eine abgeleitete Datenbank, die Sie veröffentlichen, muss unter derselben Lizenz stehen.',
        'api' => 'Für gezielte Abfragen liefert die API dieselben Daten in aktueller Fassung.',
        'api_link' => 'Zugang zur API',
    ],

    'thematic' => [
        'title' => 'Thematische Datensätze',
        'intro' => 'Alle Jahrzehnte zusammen, eine CSV-Datei je Datensatz.',
    ],
    'network' => [
        'title' => 'Netz nach Jahrzehnten',
        'intro' => 'Für jedes Jahrzehnt: die Strecken, ihre Abschnitte (Teilstücke in einheitlichem Zustand) und die Bahnhöfe, als CSV und GeoJSON. Das Jahrzehnt 2030 zeigt den Zustand des Netzes am Tag des Exports.',
        'current' => 'Heute',
    ],

    'kinds' => [
        'events' => ['title' => 'Datierte Ereignisse', 'text' => 'Eröffnungen, Stilllegungen, Elektrifizierungen und weitere Daten der Strecken, mit Abschnitt und Quelle.'],
        'accidents' => ['title' => 'Unfälle', 'text' => 'Auf VFF veröffentlichte Unfälle: Datum, Ort, Art, Opferzahlen und Quellen.'],
        'materials' => ['title' => 'Fahrzeuge', 'text' => 'Fahrzeugbaureihen: Hersteller, Betreiber, Einsatzjahre und technische Daten.'],
    ],

    'cols' => [
        'dataset' => 'Datensatz',
        'decade' => 'Jahrzehnt',
        'date' => 'Datum',
        'size' => 'Größe',
        'contents' => 'Inhalt',
        'download' => 'Herunterladen',
    ],
    'zip' => 'ZIP-Archiv',
    'zip_label' => 'ZIP-Archiv des Datensatzes :name herunterladen (:size)',
    'files' => 'Einzelne Dateien',
    'rows_one' => ':count Eintrag',
    'rows_other' => ':count Einträge',
    'lines_one' => ':count Strecke',
    'lines_other' => ':count Strecken',
    'sections_one' => ':count Abschnitt',
    'sections_other' => ':count Abschnitte',
    'stations_one' => ':count Bahnhof',
    'stations_other' => ':count Bahnhöfe',

    'empty' => [
        'title' => 'Noch kein Datensatz',
        'text' => 'Die Datensätze werden vorbereitet. Bis dahin liefert die API bereits dieselben Daten.',
    ],

    'rights' => [
        'title' => 'Was nicht in den Datensätzen steht',
        'intro' => 'Aufgenommen werden nur Daten, deren Quelle eine schriftliche offene Lizenz hat, die kommerzielle Nutzung erlaubt. Texte (Beschreibungen, Zusammenfassungen, Zitate) werden nicht übernommen. Alles andere wird entfernt oder durch den besten freien Wert ersetzt: So wird etwa der Verlauf eines Abschnitts durch den von OpenStreetMap oder des IGN ersetzt, wenn dieser demselben Gleis im Abstand von höchstens 15 m folgt.',
        'col_item' => 'Angabe',
        'col_replaced' => 'Ersetzt',
        'col_removed' => 'Entfernt',
        'items' => [
            'sections_geometry' => 'Verläufe von Abschnitten',
            'sections_gauge' => 'Spurweiten von Abschnitten',
            'lines' => 'Nach einer nicht freien Trasse angelegte Strecken',
            'lines_name' => 'Streckennamen',
            'stations_name' => 'Bahnhofsnamen',
            'stations_geometry' => 'Lage von Bahnhöfen',
            'stations_opened_on' => 'Eröffnungsdaten von Bahnhöfen',
            'stations_closed_on' => 'Schließungsdaten von Bahnhöfen',
            'events' => 'Datierte Ereignisse',
            'accidents' => 'Unfälle',
            'accidents_location' => 'Lage von Unfällen',
            'accidents_deaths' => 'Zahl der Toten',
            'accidents_injuries' => 'Zahl der Verletzten',
            'accidents_kind' => 'Unfallarten',
            'accidents_name' => 'Unfallnamen',
            'accidents_sources' => 'Zitierte Unfallquellen',
            'materials' => 'Fahrzeugbaureihen',
            'other' => 'Feld :field',
        ],
    ],

    'credits' => [
        'title' => 'Zitierte Quellen',
        'intro' => 'Anbieter, deren Daten in mindestens einem Datensatz stehen, mit ihrer Lizenz.',
    ],

    // Données structurées (JSON-LD) du jeu de données.
    'json_ld' => [
        'keywords' => 'Eisenbahn, Bahnstrecken, Bahnhöfe, Geschichte, Frankreich, offene Daten, ODbL',
    ],
];
